<?php
require_once __DIR__ . '/../includes/bootstrap.php';
allowMethods(['GET']);
getDB()->query('SELECT 1');
jsonResponse(true, ['database' => 'connected']);
