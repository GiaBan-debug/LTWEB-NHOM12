<?php
require_once __DIR__ . '/../includes/bootstrap.php';
allowMethods(['GET']);
jsonResponse(true, ['csrf_token' => $_SESSION['csrf_token']]);
