<?php
/* ============================================================
   Upload / Remoção da imagem FIXA do informativo de sinistralidade
   - POST  (multipart com 'imagem'): salva como informativo_saude.png
   - POST  (?action=remove):         restaura a imagem padrão (apaga o upload)
   - GET   (?action=check):          retorna {exists, mtime, url}
   ============================================================ */
require_once __DIR__.'/config.php';
auth();
if (!isAdmin() && !isEnfermeira()) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Sem permissão.']); exit; }
header('Content-Type: application/json; charset=utf-8');

$alvo  = __DIR__ . '/informativo_saude.png';
$bkup0 = __DIR__ . '/informativo_saude_padrao.png';

// Na primeira execução, guarda o arquivo "padrão" (vinha no projeto) como backup
if (is_file($alvo) && !is_file($bkup0)) {
    @copy($alvo, $bkup0);
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'check') {
    $exists = is_file($alvo);
    echo json_encode([
        'ok'     => true,
        'exists' => $exists,
        'mtime'  => $exists ? filemtime($alvo) : null,
        'url'    => $exists ? ('informativo_saude.png?v=' . filemtime($alvo)) : null,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'remove') {
    // Restaura o padrão (se existir backup) ou apenas apaga
    if (is_file($bkup0)) {
        @copy($bkup0, $alvo);
    } else {
        @unlink($alvo);
    }
    echo json_encode(['ok'=>true, 'msg'=>'Imagem fixa removida — voltou pro padrão.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['imagem'])) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>'Envie um arquivo (campo "imagem") por POST.']);
    exit;
}

$f = $_FILES['imagem'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok'=>false,'msg'=>'Erro no upload (código '.$f['error'].')']);
    exit;
}
// Limite de 8 MB
if ($f['size'] > 8 * 1024 * 1024) {
    echo json_encode(['ok'=>false,'msg'=>'Imagem grande demais (máx. 8 MB).']);
    exit;
}
// Aceita JPG/PNG/WEBP — converte para PNG ao salvar
$mime = mime_content_type($f['tmp_name']);
$ok = ['image/png','image/jpeg','image/jpg','image/webp'];
if (!in_array($mime, $ok, true)) {
    echo json_encode(['ok'=>false,'msg'=>'Formato inválido. Use PNG, JPG ou WEBP.']);
    exit;
}

// Salva direto (sem reencode — preserva qualidade)
if (!move_uploaded_file($f['tmp_name'], $alvo)) {
    echo json_encode(['ok'=>false,'msg'=>'Falha ao salvar a imagem no servidor.']);
    exit;
}
@chmod($alvo, 0644);

echo json_encode([
    'ok'    => true,
    'msg'   => 'Imagem fixa atualizada — vai aparecer em todos os informativos.',
    'mtime' => filemtime($alvo),
    'url'   => 'informativo_saude.png?v=' . filemtime($alvo),
]);
