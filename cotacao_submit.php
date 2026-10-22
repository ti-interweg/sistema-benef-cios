<?php
// =========================================================
// cotacao_submit.php
// Endpoint PÚBLICO para receber a resposta do cliente.
// Não passa pelo auth() — qualquer um com o token pode submeter.
// =========================================================

// Inclui APENAS a conexão de banco e helpers básicos do config.php,
// sem chamar auth() (que redirecionaria pra login).
$_SKIP_AUTH = true;

// Carrega só o necessário do config (db, helpers)
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function jsonOut($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$d = json_decode(file_get_contents('php://input'), true) ?: [];

$token = trim($d['token'] ?? '');
$resp  = $d['resposta'] ?? null;

if (!$token || !preg_match('/^[a-zA-Z0-9]{16,32}$/', $token)) {
    jsonOut(['ok'=>false,'msg'=>'Token inválido.']);
}
if (!is_array($resp)) {
    jsonOut(['ok'=>false,'msg'=>'Resposta inválida.']);
}

try {
    $stCh = db()->prepare("SELECT id, status FROM cotacoes_envio WHERE token = ?");
    $stCh->execute([$token]);
    $row = $stCh->fetch(PDO::FETCH_ASSOC);
    if (!$row) jsonOut(['ok'=>false,'msg'=>'Cotação não encontrada.']);
    if ($row['status'] === 'respondida') jsonOut(['ok'=>false,'msg'=>'Esta cotação já foi respondida.']);

    $cliNome  = trim($d['cliente_nome']  ?? '');
    $cliEmail = trim($d['cliente_email'] ?? '');

    $stmt = db()->prepare("UPDATE cotacoes_envio
        SET resposta_json=?, cliente_nome=?, cliente_email=?, respondido_em=NOW(), status='respondida'
        WHERE token=?");
    $stmt->execute([
        json_encode($resp, JSON_UNESCAPED_UNICODE),
        $cliNome, $cliEmail, $token
    ]);
    jsonOut(['ok'=>true]);
} catch (Exception $e) {
    jsonOut(['ok'=>false,'msg'=>'Erro: '.$e->getMessage()]);
}
