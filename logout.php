<?php
require_once __DIR__ . '/includes/functions.php';
if (isLoggedIn()) {
    auditLog('LOGOUT');
}
session_destroy();
header('Location: /koperasi/login.php');
exit;
