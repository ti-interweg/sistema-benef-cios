<?php
require_once 'config.php';
if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/auditoria.php';
    aud_log('logout', 'sessao', $_SESSION['user_id'], null, 'logout');
}
session_destroy();
header('Location: login.php');
exit;
