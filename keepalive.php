<?php
/* ============================================================
   keepalive.php — renova o relógio de inatividade da sessão.
   Chamado pelo JS (assets/sessao.js) enquanto a pessoa está
   interagindo com a tela, para não expirar durante o uso ativo.
   Também informa quantos segundos faltam para expirar.
   ============================================================ */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'expirado' => true]);
    exit;
}

if (!defined('IDLE_TIMEOUT')) define('IDLE_TIMEOUT', 12 * 60 * 60); // 12 horas
$agora = time();

// Se veio um ping "renovar" (padrão), atualiza o relógio.
$renovar = ($_GET['acao'] ?? 'renovar') !== 'status';
if ($renovar) {
    $_SESSION['last_activity'] = $agora;
    echo json_encode(['ok' => true, 'restante' => IDLE_TIMEOUT]);
    exit;
}

// acao=status: só informa quanto falta, sem renovar.
$ultimo = (int)($_SESSION['last_activity'] ?? $agora);
$restante = max(0, IDLE_TIMEOUT - ($agora - $ultimo));
echo json_encode(['ok' => true, 'restante' => $restante]);
