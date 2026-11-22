<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../backend/includes/bootstrap.php';
require_once __DIR__ . '/../backend/includes/firebase.php';
require_once __DIR__ . '/../backend/includes/checkout.php';
require_once __DIR__ . '/../vendor/autoload.php';
restore_exception_handler();
use Firebase\JWT\JWT;
$checks = 0;
function check(bool $condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function rejects(callable $call, int $status = 401): void
{
    try { $call(); } catch (ApiException $error) { check($error->status === $status, 'Expected HTTP ' . $status . ', got ' . $error->status); return; }
    throw new RuntimeException('Expected rejection.');
}
$key = openssl_pkey_new(['private_key_bits'=>2048, 'private_key_type'=>OPENSSL_KEYTYPE_RSA, 'config'=>__DIR__ . '/openssl.cnf']);
if (!$key) throw new RuntimeException('RSA test fixture could not be generated.');
openssl_pkey_export($key, $privateKey, null, ['config'=>__DIR__ . '/openssl.cnf']);
$publicKey = openssl_pkey_get_details($key)['key'];
$verifier = new FirebaseVerifier('bta-store', static fn() => ['test-kid' => $publicKey]);
$claims = ['sub'=>'test-uid-' . bin2hex(random_bytes(6)), 'aud'=>'bta-store', 'iss'=>'https://securetoken.google.com/bta-store', 'iat'=>time()-5, 'auth_time'=>time()-10, 'exp'=>time()+3600, 'email'=>'test-' . bin2hex(random_bytes(6)) . '@example.test', 'name'=>'Khách Firebase', 'email_verified'=>true];
$encode = static fn(array $payload, string $kid = 'test-kid') => JWT::encode($payload, $privateKey, 'RS256', $kid);
$verified = $verifier->verify($encode($claims));
check($verified['sub'] === $claims['sub'], 'Valid signed Firebase claims');
$phoneClaims = $claims;
unset($phoneClaims['email'], $phoneClaims['email_verified'], $phoneClaims['name']);
$phoneClaims['sub'] = 'phone-' . bin2hex(random_bytes(6));
$phoneClaims['phone_number'] = '+84912345678';
$phoneClaims['firebase'] = ['sign_in_provider' => 'phone'];
$verifiedPhone = $verifier->verify($encode($phoneClaims));
check($verifiedPhone['phone_number'] === '+84912345678' && !isset($verifiedPhone['email']), 'Signed phone-only token accepted');
foreach ([['phone_number'=>'0912345678'], ['phone_number'=>[]], ['phone_number'=>null], ['phone_number'=>'+'.str_repeat('1',16)], ['aud'=>'wrong-project']] as $override) rejects(static fn() => $verifier->verify($encode(array_replace($phoneClaims,$override))));
foreach ([['aud'=>'another-project'], ['iss'=>'https://attacker.invalid'], ['exp'=>time()-1], ['iat'=>time()+3600], ['auth_time'=>time()+3600], ['sub'=>''], ['sub'=>str_repeat('a',129)], ['email'=>['not-a-string']], ['email'=>'bad-email'], ['iat'=>0]] as $override) {
    rejects(static fn() => $verifier->verify($encode(array_replace($claims,$override))));
}
$parts = [rtrim(strtr(base64_encode('{"alg":"RS256","kid":"test-kid"}'),'+/','-_'),'='), rtrim(strtr(base64_encode(json_encode(array_replace($claims,['exp'=>'not-an-int']))),'+/','-_'),'=')];
openssl_sign(implode('.',$parts),$signature,$privateKey,OPENSSL_ALGO_SHA256);
$malformedTime = implode('.',$parts) . '.' . rtrim(strtr(base64_encode($signature),'+/','-_'),'=');
rejects(static fn() => $verifier->verify($malformedTime));
rejects(static fn() => $verifier->verify($encode($claims,'unknown-kid')));
$badSignature = $encode($claims);
$parts = explode('.', $badSignature); $parts[1] = rtrim(strtr(base64_encode(json_encode(array_replace($claims,['role'=>'admin','email'=>'admin@ltweb.com']))), '+/', '-_'), '=');
rejects(static fn() => $verifier->verify(implode('.',$parts)));
rejects(static fn() => $verifier->verify(JWT::encode($claims, str_repeat('secret',20),'HS256','test-kid')));
$unsigned = rtrim(strtr(base64_encode('{"alg":"none","kid":"test-kid"}'),'+/','-_'),'=') . '.' . rtrim(strtr(base64_encode(json_encode($claims)),'+/','-_'),'=') . '.';
rejects(static fn() => $verifier->verify($unsigned));
rejects(static fn() => $verifier->verify('not-a-token'));
check(normalizePhone('0912 345 678') === '0912345678','Whitespace phone');
check(normalizePhone('0912.345.678') === '0912345678','Dotted phone');
check(normalizePhone('+84 912-345-678') === '0912345678','International phone');
rejects(static fn() => normalizePhone('0912wrong'),422);
check(orderAmounts(1999999)['shipping_fee'] === 30000,'Below free threshold');
check(orderAmounts(2000000)['shipping_fee'] === 0,'Free threshold inclusive');
check(orderAmounts(2000001)['shipping_fee'] === 0,'Above threshold');
check(orderAmounts(0)['total'] === 0,'Empty cart no delivery fee');

if (appConfig('TV5_TEST_DATABASE') === 'true') {
    $db = getDB();
    $phoneUser = firebaseUser($db, $verifiedPhone + ['role'=>'admin']);
    check($phoneUser['email'] === null && $phoneUser['password'] === null && $phoneUser['role'] === 'user', 'Phone-only user stores no fake email/password or frontend role');
    check($phoneUser['phone'] === '0912345678' && $phoneUser['firebase_phone'] === '+84912345678', 'Verified identity separated from delivery phone');
    $db->prepare('UPDATE users SET phone=?, address=? WHERE id=?')->execute(['0987654321','Địa chỉ đã lưu',$phoneUser['id']]);
    $phoneAgain = firebaseUser($db, $verifiedPhone);
    check($phoneAgain['id'] === $phoneUser['id'] && $phoneAgain['phone'] === '0987654321' && $phoneAgain['address'] === 'Địa chỉ đã lưu', 'Phone login keeps profile and internal ID');
    $otherPhone = firebaseUser($db, array_replace($verifiedPhone,['sub'=>'other-' . $verifiedPhone['sub'],'phone_number'=>'+84987654321']));
    check($otherPhone['id'] !== $phoneUser['id'], 'Delivery phone match never merges accounts');
    establishSession($phoneAgain, 'firebase', time()+3600);
    check(publicUser(getCurrentUser())['phone_verified'] === true, 'Phone identity survives PHP session');
    check(hasVerifiedFirebaseIdentity(getCurrentUser()), 'Verified phone satisfies checkout identity requirement');
    check(!hasVerifiedFirebaseIdentity(['email_verified'=>false,'phone'=>'0912345678','firebase_phone'=>null]), 'Editable delivery phone never bypasses checkout verification');
    $user = firebaseUser($db, $verified + ['role'=>'admin']);
    check($user['role'] === 'user','Client claims cannot grant admin');
    check($user['password'] === null,'No Firebase password in MySQL');
    $again = firebaseUser($db, $verified);
    check($again['id'] === $user['id'],'Same UID keeps internal user id');
    $caseClaims = array_replace($verified,['sub'=>strtoupper($verified['sub']),'email'=>'case-' . $verified['email']]);
    check(firebaseUser($db,$caseClaims)['id'] !== $user['id'],'UID comparisons are case-sensitive');
    rejects(static fn() => firebaseUser($db,array_replace($verified,['sub'=>'attacker-' . bin2hex(random_bytes(4)),'email'=>'admin@ltweb.com'])),409);
    $seed = $db->query("SELECT id, role, firebase_uid FROM users WHERE email='admin@ltweb.com'")->fetch();
    check($seed['role'] === 'admin' && !$seed['firebase_uid'],'Collision does not bind legacy admin');
    establishSession($user,'firebase',time()+3600);
    check(getCurrentUser()['id'] === $user['id'],'Verified app session');
    $db->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$user['id']]);
    check(getCurrentUser()['role'] === 'admin','Role re-read from MySQL');
    $db->prepare("UPDATE users SET role='user' WHERE id=?")->execute([$user['id']]);
    check(getCurrentUser()['role'] === 'user','Demotion takes effect immediately');
    $_SESSION['expires_at'] = time()-1;
    check(getCurrentUser() === null,'Expired session rejected');
    establishSession($user,'firebase',time()+3600);
    $_SESSION['firebase_project'] = 'wrong-project';
    check(getCurrentUser() === null,'Session from another project rejected');
}
echo "PASS: $checks TV5 assertions (RSA signatures, claims, phone, fees" . (appConfig('TV5_TEST_DATABASE') === 'true' ? ', MySQL mapping and sessions' : '') . ").\n";
