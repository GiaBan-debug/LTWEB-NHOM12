<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Location: ../../frontend/pages/admin/index.html', true, 302);
exit;
