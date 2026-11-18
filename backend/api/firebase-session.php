<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/firebase.php';
allowMethods(['POST']);
if (authMode() !== 'firebase') throw new ApiException('Firebase chưa được bật trên máy chủ.', 409);
$input = getJsonBody();
$token = inputString($input, 'id_token', 16384, true);
$claims = firebaseVerifier()->verify($token);
$user = firebaseUser(getDB(), $claims);
establishSession($user, 'firebase', (int) $claims['exp']);
jsonResponse(true, publicUser($user), 'Đăng nhập Firebase thành công.');
