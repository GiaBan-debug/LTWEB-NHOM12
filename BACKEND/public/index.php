<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/Api.php';

loadEnvironment(dirname(__DIR__) . '/.env');

$configuredOrigins = array_values(array_filter(array_map('trim', explode(',', envValue('CORS_ORIGINS', 'http://localhost:5500,http://127.0.0.1:5500')))));
$jsonOrigins = json_decode(envValue('CORS_ORIGINS'), true);
if (is_array($jsonOrigins)) {
    $configuredOrigins = array_values(array_filter($jsonOrigins, 'is_string'));
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $configuredOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    (new Api(database()))->run();
} catch (ApiException $error) {
    $response = ['detail' => $error->getMessage()];
    if ($error->errors !== []) {
        $response['errors'] = $error->errors;
    }
    sendJson($response, $error->statusCode);
} catch (PDOException $error) {
    error_log('Database error: ' . $error->getMessage());
    sendJson(['detail' => 'Không thể kết nối hoặc truy vấn cơ sở dữ liệu.'], 503);
} catch (Throwable $error) {
    error_log('API error: ' . $error->getMessage());
    sendJson(['detail' => 'Đã xảy ra lỗi máy chủ.'], 500);
}
