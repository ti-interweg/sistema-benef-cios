<?php
/**
 * =====================================================
 * API — ENVIO DE INFORMATIVO DE SINISTRALIDADE POR E-MAIL
 * =====================================================
 * Envia o corpo do e-mail montado no front (já com Power BI/WeeBi),
 * adicionando a assinatura institucional do analista logado.
 * Registra cada envio em sinistralidade_emails_log (cria a tabela se
 * não existir, de forma transparente).
 */

require_once __DIR__ . '/config.php';
auth();

header('Content-Type: application/json; charset=utf-8');

function _sj($d) { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function _sbody() { return json_decode(file_get_contents('php://input'), true) ?: []; }

$action = $_GET['action'] ?? '';

if ($action !== 'enviar_email_sinistralidade') {
    _sj(['ok' => false, 'msg' => 'Ação desconhecida: ' . $action]);
}

$d         = _sbody();
$empresa   = trim($d['empresa'] ?? '');
$destRaw   = trim($d['destinatarios'] ?? '');
$assunto   = trim($d['assunto'] ?? '');
$corpoTxt  = trim($d['corpo'] ?? '');
$imgRaw    = trim($d['imagem_informativo'] ?? ''); // data:image/jpeg;base64,...
$weebiLink  = trim($d['weebi_link']  ?? '');
$weebiLogin = trim($d['weebi_login'] ?? '');
$weebiSenha = trim($d['weebi_senha'] ?? '');

// O corpo de texto é opcional: o informativo (imagem) pode ser o próprio corpo do e-mail.
if (!$destRaw || !$assunto || ($corpoTxt === '' && $imgRaw === '')) {
    _sj(['ok' => false, 'msg' => 'Campos obrigatórios faltando (destinatários, assunto e ao menos o informativo ou uma mensagem).']);
}

// Extrai mime + base64 puro da imagem do informativo
$imgBase64 = '';
$imgMime   = 'image/jpeg';
if ($imgRaw !== '') {
    if (preg_match('/^data:(image\/(?:png|jpeg|jpg));base64,(.+)$/i', $imgRaw, $m)) {
        $imgMime   = strtolower($m[1]);
        $imgBase64 = $m[2];
    } else {
        // Assume base64 puro
        $imgBase64 = $imgRaw;
    }
}

// Valida destinatários (aceita ; ou ,)
$partes  = preg_split('/[;,]/', $destRaw);
$validos = [];
foreach ($partes as $em) {
    $em = trim($em);
    if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) $validos[] = $em;
}
if (!count($validos)) _sj(['ok' => false, 'msg' => 'Nenhum destinatário válido.']);

// Monta corpo HTML com a assinatura institucional (mesmo padrão do resumo mensal)
$analistaNome = trim($_SESSION['user_nome'] ?? '') ?: 'Equipe Interweg';
$corpoHtml = '';

// O texto do corpo é dividido em parágrafos. Se ele contém a linha
// "Segue em anexo o informativo", a gente substitui essa linha pela
// imagem inline do informativo gerado (referência cid:informativo).
$temImagem = ($imgBase64 !== '');
$cidImg    = 'informativo' . rand(1000,9999);

$paragrafos = preg_split('/\r?\n\r?\n/', $corpoTxt);
$imagemInserida = false;
foreach ($paragrafos as $par) {
    $par = trim($par);
    if ($par === '') continue;
    $corpoHtml .= '<p>' . nl2br(htmlspecialchars($par, ENT_QUOTES, 'UTF-8')) . '</p>' . "\n";
    // Insere a imagem logo após a linha "Segue em anexo o informativo..."
    if ($temImagem && !$imagemInserida && stripos($par, 'em anexo') !== false) {
        $corpoHtml .= '<p style="text-align:center;margin:18px 0">' .
            '<img src="cid:' . $cidImg . '" alt="Informativo de Sinistralidade" style="width:100%;max-width:100%;height:auto;border:1px solid #ddd;border-radius:6px;display:block;margin:0 auto"/>' .
            '</p>' . "\n";
        $imagemInserida = true;
    }
}
// Se a imagem não foi inserida (texto sem a frase âncora), coloca no topo
if ($temImagem && !$imagemInserida) {
    $corpoHtml = '<p style="text-align:center;margin:0 0 18px">' .
        '<img src="cid:' . $cidImg . '" alt="Informativo de Sinistralidade" style="width:100%;max-width:100%;height:auto;border:1px solid #ddd;border-radius:6px;display:block;margin:0 auto"/>' .
        '</p>' . "\n" . $corpoHtml;
}

// Acesso ao WeeBi: BOTÃO clicável (em HTML real, logo abaixo da imagem do informativo)
if ($weebiLink !== '' || $weebiLogin !== '' || $weebiSenha !== '') {
    $linkHref = $weebiLink;
    if ($linkHref !== '' && !preg_match('~^https?://~i', $linkHref)) $linkHref = 'https://' . $linkHref;
    // Só o botão (CTA único). As credenciais já aparecem no card dentro da imagem.
    // Ícone Material "open_in_new" embutido como SVG (sem fonte externa, sem emoji).
    if ($weebiLink !== '') {
        $svgIcon = '<img src="https://interweg-beneficios.com.br/icone_weebi.png" width="20" height="20" alt="" style="vertical-align:-4px;margin-right:8px;border:0"/>';
        $bloco  = '<div style="max-width:680px;margin:4px auto 20px;text-align:center;font-family:Arial,Helvetica,sans-serif">';
        $bloco .= '<a href="' . htmlspecialchars($linkHref, ENT_QUOTES, 'UTF-8') . '" target="_blank" '
                . 'style="display:inline-block;background:#0a8a8d;color:#ffffff;text-decoration:none;font-weight:700;'
                . 'font-size:17px;padding:14px 38px;border-radius:8px;border:1px solid #097073">'
                . $svgIcon . 'Acessar WeeBi</a>';
        $bloco .= '</div>' . "\n";
        $corpoHtml .= $bloco;
    }
}

$corpoHtml .=
    '<p style="margin-bottom:2px">Atenciosamente,</p>' .
    '<p style="margin:8px 0 0;font-weight:700;color:#0a8a8d;font-size:15px">' . htmlspecialchars($analistaNome, ENT_QUOTES, 'UTF-8') . '</p>' .
    '<p style="margin:0;color:#444;font-size:13px">Enfermagem &middot; Interweg Benefícios</p>' .
    '<p style="margin:4px 0 0;font-size:12px;color:#666">Tel. (31) 3508-6000 &nbsp;|&nbsp; interweg.com.br &nbsp;|&nbsp; Av. Raja Gabáglia, 4.987 &ndash; 302 &ndash; Belo Horizonte/MG</p>' .
    '<p><img src="https://interweg-beneficios.com.br/assinatura_interweg.png" alt="Interweg Seguros e Benefícios" style="max-width:480px;width:100%;height:auto;display:block;margin-top:10px"/></p>';

// Garante a tabela de log (cria se não existir)
try {
    db()->exec("CREATE TABLE IF NOT EXISTS sinistralidade_emails_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa VARCHAR(255) NULL,
        enviado_por INT NULL,
        enviado_por_nome VARCHAR(150) NULL,
        destinatarios TEXT NULL,
        assunto VARCHAR(255) NULL,
        corpo LONGTEXT NULL,
        status ENUM('enviado','erro') NOT NULL,
        erro_msg TEXT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_criado (criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci");
} catch (PDOException $e) { /* segue */ }

// Envia via SMTP
$mailFile = __DIR__ . '/mail_smtp.php';
if (!is_file($mailFile)) _sj(['ok' => false, 'msg' => 'mail_smtp.php não encontrado no servidor.']);
require_once $mailFile;
if (!function_exists('enviarEmailSMTP')) _sj(['ok' => false, 'msg' => 'Função de envio SMTP indisponível.']);

$replyTo = trim($_SESSION['user_email'] ?? '');
if ($replyTo === '') $replyTo = trim($_SESSION['user_login'] ?? '');
$erroEnvio = '';

if ($temImagem && function_exists('enviarEmailSMTPComImagem')) {
    // Envia com a imagem do informativo embutida inline (cid:)
    $sent = enviarEmailSMTPComImagem(
        implode(',', $validos), $assunto, $corpoHtml,
        $imgBase64, $cidImg, $imgMime, $erroEnvio, $replyTo
    );
} else {
    // Fallback: envia só HTML (sem imagem) — caso a função nova não esteja disponível
    $sent = enviarEmailSMTP(implode(',', $validos), $assunto, $corpoHtml, $erroEnvio, $replyTo);
}
$status = $sent ? 'enviado' : 'erro';

// Log (sempre, mesmo se falhar)
try {
    $ins = db()->prepare("INSERT INTO sinistralidade_emails_log
        (empresa, enviado_por, enviado_por_nome, destinatarios, assunto, corpo, status, erro_msg)
        VALUES (?,?,?,?,?,?,?,?)");
    $ins->execute([
        $empresa,
        $_SESSION['user_id'] ?? null,
        $_SESSION['user_nome'] ?? '',
        implode('; ', $validos),
        $assunto,
        $corpoHtml,
        $status,
        $sent ? null : ($erroEnvio ?: 'falha desconhecida')
    ]);
} catch (PDOException $e) { /* não bloqueia */ }

if ($sent) _sj(['ok' => true, 'enviado_em' => date('Y-m-d H:i:s')]);
_sj(['ok' => false, 'msg' => 'Falha no envio SMTP: ' . ($erroEnvio ?: 'servidor recusou')]);
