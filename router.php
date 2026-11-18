<?php
// Router for PHP's development server; Apache uses .htaccess.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('#(^|/)(\.|var(?:/|$)|vendor(?:/|$)|composer\.(?:json|lock)$|database(?:/|$)|docs(?:/|$)|scripts(?:/|$)|tests(?:/|$)|backend/(?:includes|config)(?:/|$))#i', $path)) {
    http_response_code(404);
    exit('Not found');
}
if ($path === '/') { require __DIR__ . '/index.php'; return true; }
return false;
