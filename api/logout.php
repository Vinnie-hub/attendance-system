<?php
// api/logout.php
require_once __DIR__ . '/../includes/auth.php';
auth_logout();
header('Location: ' . BASE_URL . '/index.php');
exit;
