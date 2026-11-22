<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/errors.php';

/** Firebase RS256 verification; tests can inject public keys, HTTP cannot. */
final class FirebaseVerifier
{
    private const CERT_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    public function __construct(private string $projectId, private mixed $keyProvider = null) {}

    public function verify(string $token): array
    {
        if ($this->projectId === '' || strlen($token) > 16384 || substr_count($token, '.') !== 2) {
            throw new ApiException('Firebase ID token không hợp lệ.', 401);
        }
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoload)) throw new ApiException('Máy chủ chưa cài thư viện Firebase. Hãy chạy composer install.', 503);
        require_once $autoload;
        try {
            $header = json_decode(\Firebase\JWT\JWT::urlsafeB64Decode(explode('.', $token, 3)[0]), true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) { throw new ApiException('Firebase ID token không hợp lệ.', 401); }
        // This is only a format/algorithm gate; no identity is trusted before signature verification.
        if (!is_array($header) || ($header['alg'] ?? '') !== 'RS256' || !is_string($header['kid'] ?? null) || $header['kid'] === '' || strlen($header['kid']) > 128) throw new ApiException('Firebase ID token không hợp lệ.', 401);
        $certificates = $this->keyProvider ? ($this->keyProvider)() : $this->certificates();
        $keys = [];
        foreach ($certificates as $kid => $certificate) $keys[$kid] = new \Firebase\JWT\Key($certificate, 'RS256');
        try {
            // A fixed RS256 Key set prevents alg confusion/unsigned token acceptance.
            $claims = (array) \Firebase\JWT\JWT::decode($token, $keys);
        } catch (Throwable $error) {
            throw new ApiException('Phiên Firebase không hợp lệ hoặc đã hết hạn. Hãy đăng nhập lại.', 401);
        }
        $now = time();
        foreach (['exp', 'iat', 'auth_time'] as $field) {
            if (!isset($claims[$field]) || !is_int($claims[$field])) throw new ApiException('Firebase ID token thiếu thông tin thời gian.', 401);
        }
        if (($claims['aud'] ?? null) !== $this->projectId || ($claims['iss'] ?? null) !== 'https://securetoken.google.com/' . $this->projectId
            || $claims['exp'] <= $now || $claims['exp'] <= $claims['iat'] || $claims['iat'] > $now || $claims['iat'] < 1 || $claims['auth_time'] > $claims['iat'] || $claims['auth_time'] < 1
            || !is_string($claims['sub'] ?? null) || $claims['sub'] === '' || mb_strlen($claims['sub']) > 128
        ) {
            throw new ApiException('Firebase ID token không thuộc project hoặc người dùng hợp lệ.', 401);
        }
        $email = $claims['email'] ?? null;
        $phone = $claims['phone_number'] ?? null;
        if (($email !== null && (!is_string($email) || strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)))
            || ($phone !== null && (!is_string($phone) || !preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone)))
            || ($email === null && $phone === null)) {
            throw new ApiException('Firebase ID token thiếu email hoặc số điện thoại hợp lệ.', 401);
        }
        return $claims;
    }

    private function certificates(): array
    {
        $path = dirname(__DIR__, 2) . '/var/firebase-public-keys.json';
        $cached = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (is_array($cached) && ($cached['expires_at'] ?? 0) > time() && is_array($cached['certificates'] ?? null)) return $cached['certificates'];
        $curl = curl_init(self::CERT_URL);
        $cacheControl = '';
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$cacheControl): int {
                if (stripos($line, 'Cache-Control:') === 0) $cacheControl = $line;
                return strlen($line);
            },
        ]);
        $caBundle = appConfig('TLS_CA_BUNDLE', __DIR__ . '/../config/cacert.pem');
        if (is_file($caBundle)) curl_setopt($curl, CURLOPT_CAINFO, $caBundle);
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($response === false || $status !== 200) {
            error_log('Firebase public key retrieval failed: HTTP ' . $status . ' ' . curl_error($curl));
            throw new ApiException('Chưa kết nối được máy chủ xác minh Firebase. Vui lòng thử lại.', 503);
        }
        $certificates = json_decode($response, true);
        if (!is_array($certificates) || !$certificates || strlen($response) > 65536) throw new ApiException('Chứng thư Firebase không hợp lệ.', 503);
        $ttl = preg_match('/max-age=(\d+)/i', $cacheControl, $match) ? min(86400, (int) $match[1]) : 300;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        // Public certificates only; no private keys or ID tokens are cached.
        if (file_put_contents($path, json_encode(['expires_at' => time() + $ttl, 'certificates' => $certificates]), LOCK_EX) === false) error_log('Firebase public key cache is not writable.');
        return $certificates;
    }
}

function firebaseVerifier(): FirebaseVerifier
{
    return new FirebaseVerifier(appConfig('FIREBASE_PROJECT_ID', 'bta-store'));
}

function firebaseUser(PDO $db, array $claims): array
{
    $uid = $claims['sub'];
    $email = isset($claims['email']) ? strtolower($claims['email']) : null;
    $phone = $claims['phone_number'] ?? null;
    return transaction($db, static function () use ($db, $claims, $uid, $email, $phone): array {
        $stmt = $db->prepare('SELECT * FROM users WHERE firebase_uid = ? FOR UPDATE');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if (!$user) {
            if ($email !== null) {
                $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) throw new ApiException('Email đã có tài khoản cũ. Nhờ quản trị liên kết UID để giữ giỏ hàng/đơn; hệ thống không tự gán tài khoản theo email.', 409);
            }
            $name = is_string($claims['name'] ?? null) && trim($claims['name']) !== '' ? mb_substr(trim($claims['name']), 0, 100) : ($email !== null ? mb_substr(explode('@', $email)[0], 0, 100) : 'Khách hàng ' . substr($phone, -4));
            $deliveryPhone = $phone && preg_match('/^\+84[0-9]{9}$/D', $phone) ? '0' . substr($phone, 3) : '';
            try {
                $db->prepare("INSERT INTO users (firebase_uid, name, email, password, role, email_verified, firebase_phone, phone) VALUES (?, ?, ?, NULL, 'user', ?, ?, ?)")
                    ->execute([$uid, $name, $email, $email !== null && ($claims['email_verified'] ?? false) === true ? 1 : 0, $phone, $deliveryPhone]);
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') throw new ApiException('Tài khoản đang được cập nhật. Hãy thử lại.', 409);
                throw $error;
            }
            $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([(int) $db->lastInsertId()]);
            $user = $stmt->fetch();
        }
        $verified = $email !== null && ($claims['email_verified'] ?? false) === true;
        $db->prepare('UPDATE users SET email_verified = ?, firebase_phone = ? WHERE id = ?')->execute([$verified ? 1 : 0, $phone, $user['id']]);
        $user['email_verified'] = $verified;
        $user['firebase_phone'] = $phone;
        return $user;
    });
}
