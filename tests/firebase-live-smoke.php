<?php
// Read-only cloud probe: fetch public signing certificates and try a nonexistent account.
// No Firebase account is created, changed or emailed by this script.
declare(strict_types=1);
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/includes/firebase.php';
$curl = curl_init('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com');
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2]);
curl_setopt($curl, CURLOPT_CAINFO, __DIR__ . '/../backend/config/cacert.pem');
$result = curl_exec($curl);
if ($result === false) { fwrite(STDERR, 'PUBLIC_KEYS_ERROR: ' . curl_error($curl) . "\n"); exit(1); }
echo 'PUBLIC_KEYS_HTTP=' . curl_getinfo($curl, CURLINFO_RESPONSE_CODE) . "\n";
echo 'PUBLIC_KEYS_COUNT=' . count(json_decode($result, true) ?: []) . "\n";
$certificates = json_decode($result, true);
$kid = array_key_first($certificates);
$encode = static fn($value) => rtrim(strtr(base64_encode(json_encode($value)), '+/', '-_'), '=');
$forged = $encode(['alg'=>'RS256','kid'=>$kid]) . '.' . $encode(['sub'=>'forged','aud'=>'bta-store','iss'=>'https://securetoken.google.com/bta-store','iat'=>time()-5,'auth_time'=>time()-5,'exp'=>time()+60,'email'=>'forged@example.invalid']) . '.aW52YWxpZA';
try { firebaseVerifier()->verify($forged); throw new RuntimeException('Forged token unexpectedly accepted.'); }
catch (ApiException $error) { if ($error->status !== 401) throw $error; echo "FORGED_TOKEN_REJECTED=401\n"; }
$apiKey = appConfig('FIREBASE_WEB_API_KEY', 'AIzaSyCY2kHt5owLohtsLdIqtt61NwHBFMljHu4');
$curl = curl_init('https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=' . rawurlencode($apiKey));
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_POSTFIELDS=>json_encode(['email'=>'bta-nonexistent-probe-' . time() . '@example.invalid','password'=>'Not-a-real-password-845737','returnSecureToken'=>true]), CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2]);
curl_setopt($curl, CURLOPT_CAINFO, __DIR__ . '/../backend/config/cacert.pem');
$result = curl_exec($curl);
if ($result === false) { fwrite(STDERR, 'AUTH_PROBE_ERROR: ' . curl_error($curl) . "\n"); exit(1); }
$data = json_decode($result, true);
echo 'FIREBASE_AUTH_PROBE=' . ($data['error']['message'] ?? 'Unexpected response') . "\n";
