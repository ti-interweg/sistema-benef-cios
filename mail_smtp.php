<?php
/**
 * =====================================================
 * Envio de e-mail via SMTP autenticado (sem dependências)
 * =====================================================
 * A Hostinger bloqueia o mail() nativo. Este helper envia pelo SMTP
 * da Hostinger usando uma caixa de e-mail real do domínio.
 *
 * >>> PASSO ÚNICO DE CONFIGURAÇÃO <<<
 * 1) No hPanel da Hostinger → E-mails → crie (ou use) uma caixa, ex.:
 *    noreply@interweg-beneficios.com.br  (anote a senha).
 * 2) Preencha 'user' e 'pass' abaixo com essa caixa e senha.
 * (Host/porta da Hostinger já vêm preenchidos.)
 */

$SMTP_CONFIG = [
    'host'      => 'smtp.hostinger.com',
    'port'      => 465,
    'secure'    => 'ssl',                                   // 'ssl' (465) ou 'tls' (587)
    'user'      => 'noreply@interweg-beneficios.com.br',    // <-- caixa de e-mail real
    'pass'      => 'COLOQUE_A_SENHA_AQUI',                  // <-- NÃO coloque a senha aqui: use o arquivo smtp_senha.txt
    'from_name' => 'INTERWEG Benefícios',
];

// ── Senha fora do PHP (à prova de caracteres especiais) ──
// Se existir o arquivo smtp_senha.txt, o conteúdo dele (senha pura, uma linha)
// substitui a senha acima. Assim você cola a senha nesse .txt sem precisar
// escapar aspas/barras no PHP. Por segurança, procura PRIMEIRO na pasta ACIMA
// do public_html (não acessível pela web) e depois na própria pasta.
// Opcional: smtp_usuario.txt troca o usuário, na mesma lógica.
// Limpa BOM (UTF-8/UTF-16), espaços, quebras de linha e aspas que possam
// ter sido coladas junto com a senha no arquivo de texto.
if (!function_exists('__smtp_limpa')) {
    function __smtp_limpa($s) {
        $s = (string)$s;
        $s = str_replace(["\xEF\xBB\xBF", "\xFF\xFE", "\xFE\xFF"], '', $s); // BOMs
        $s = trim($s);                         // espaços / \r / \n / \t nas pontas
        $s = trim($s, "\"'");                  // aspas acidentais
        $s = trim($s);                         // espaço entre aspas e texto
        return $s;
    }
}
foreach ([dirname(__DIR__) . '/smtp_senha.txt', __DIR__ . '/smtp_senha.txt'] as $__f) {
    if (is_file($__f)) {
        $__p = __smtp_limpa(@file_get_contents($__f));
        if ($__p !== '') { $SMTP_CONFIG['pass'] = $__p; break; }
    }
}
foreach ([dirname(__DIR__) . '/smtp_usuario.txt', __DIR__ . '/smtp_usuario.txt'] as $__f) {
    if (is_file($__f)) {
        $__u = __smtp_limpa(@file_get_contents($__f));
        if ($__u !== '') { $SMTP_CONFIG['user'] = $__u; break; }
    }
}

/**
 * Envia um e-mail HTML. Retorna true/false. Em caso de erro, preenche $erro.
 * $to pode ser uma string com vários e-mails separados por vírgula.
 */
function enviarEmailSMTP($to, $subject, $htmlBody, &$erro = null, $replyTo = '', $attachments = []) {
    // Homologação NÃO dispara e-mail real (evita mandar pra cliente por engano).
    if (is_file(__DIR__ . '/ambiente.php')) { require_once __DIR__ . '/ambiente.php'; }
    if (!defined('IS_HOMOLOG')) define('IS_HOMOLOG', false);
    if (IS_HOMOLOG) { $erro = ''; return true; }

    global $SMTP_CONFIG;
    $cfg = $SMTP_CONFIG;

    if (empty($cfg['pass']) || $cfg['pass'] === 'COLOQUE_A_SENHA_AQUI') {
        $erro = 'SMTP não configurado: preencha a senha da caixa de e-mail em mail_smtp.php.';
        return false;
    }

    $destinatarios = array_filter(array_map('trim', explode(',', $to)));
    if (!count($destinatarios)) { $erro = 'Sem destinatário válido.'; return false; }

    $host = ($cfg['secure'] === 'ssl' ? 'ssl://' : '') . $cfg['host'];
    $fp = @stream_socket_client($host . ':' . $cfg['port'], $errno, $errstr, 20,
            STREAM_CLIENT_CONNECT, stream_context_create([
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]));
    if (!$fp) { $erro = "Conexão SMTP falhou: $errstr ($errno)"; return false; }
    stream_set_timeout($fp, 20);

    $leia = function() use ($fp) {
        $data = '';
        while ($linha = fgets($fp, 515)) {
            $data .= $linha;
            if (isset($linha[3]) && $linha[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function($c) use ($fp, $leia) { fputs($fp, $c . "\r\n"); return $leia(); };

    $ok = function($resp, $code) { return substr(trim($resp), 0, 3) === (string)$code; };

    $r = $leia(); // banner
    if (!$ok($r, 220)) { $erro = 'SMTP não respondeu: ' . trim($r); fclose($fp); return false; }

    $host_local = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $r = $cmd('EHLO ' . $host_local);
    if (!$ok($r, 250)) { $r = $cmd('HELO ' . $host_local); }

    // TLS (porta 587)
    if ($cfg['secure'] === 'tls') {
        $r = $cmd('STARTTLS');
        if (!$ok($r, 220)) { $erro = 'STARTTLS falhou: ' . trim($r); fclose($fp); return false; }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $erro = 'Falha ao ativar TLS.'; fclose($fp); return false;
        }
        $cmd('EHLO ' . $host_local);
    }

    // Autenticação
    $r = $cmd('AUTH LOGIN');
    if (!$ok($r, 334)) { $erro = 'AUTH não suportado: ' . trim($r); fclose($fp); return false; }
    $r = $cmd(base64_encode($cfg['user']));
    if (!$ok($r, 334)) { $erro = 'Usuário SMTP recusado: ' . trim($r); fclose($fp); return false; }
    $r = $cmd(base64_encode($cfg['pass']));
    if (!$ok($r, 235)) { $erro = 'Senha SMTP recusada (verifique a caixa/senha): ' . trim($r); fclose($fp); return false; }

    // Envelope
    $r = $cmd('MAIL FROM:<' . $cfg['user'] . '>');
    if (!$ok($r, 250)) { $erro = 'MAIL FROM recusado: ' . trim($r); fclose($fp); return false; }
    foreach ($destinatarios as $dest) {
        $r = $cmd('RCPT TO:<' . $dest . '>');
        if (!$ok($r, 250) && !$ok($r, 251)) { $erro = "Destinatário recusado ($dest): " . trim($r); fclose($fp); return false; }
    }

    // Corpo
    $r = $cmd('DATA');
    if (!$ok($r, 354)) { $erro = 'DATA recusado: ' . trim($r); fclose($fp); return false; }

    $fromName = '=?UTF-8?B?' . base64_encode($cfg['from_name']) . '?=';
    $headers  = "From: $fromName <" . $cfg['user'] . ">\r\n";
    // Reply-To = e-mail do analista logado: a resposta do cliente vai pra ele.
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= "Reply-To: " . $replyTo . "\r\n";
    }
    $headers .= "To: " . implode(', ', $destinatarios) . "\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    // Normaliza anexos: cada um = ['filename','mime','content_base64']
    $atoks = [];
    if (is_array($attachments)) {
        foreach ($attachments as $att) {
            $c = (string)($att['content_base64'] ?? '');
            // aceita data URI (data:...;base64,XXXX) — pega só o conteúdo
            if (strpos($c, 'base64,') !== false) $c = substr($c, strpos($c, 'base64,') + 7);
            $c = preg_replace('/\s+/', '', $c);
            if ($c === '') continue;
            $atoks[] = [
                'fn' => preg_replace('/[\r\n"]/', '', (string)($att['filename'] ?? 'anexo.pdf')),
                'mt' => (string)($att['mime'] ?? 'application/pdf'),
                'c'  => $c,
            ];
        }
    }

    if (count($atoks)) {
        $boundary = 'iw_' . md5(uniqid('', true));
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= rtrim(chunk_split(base64_encode($htmlBody))) . "\r\n";
        foreach ($atoks as $a) {
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: " . $a['mt'] . "; name=\"" . $a['fn'] . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"" . $a['fn'] . "\"\r\n\r\n";
            $body .= rtrim(chunk_split($a['c'])) . "\r\n";
        }
        $body .= "--$boundary--";
        $msg = $headers . "\r\n" . $body . "\r\n.";
    } else {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";
        $corpo = rtrim(chunk_split(base64_encode($htmlBody)));
        $msg = $headers . "\r\n" . $corpo . "\r\n.";
    }
    $r = $cmd($msg);
    if (!$ok($r, 250)) { $erro = 'Envio recusado pelo servidor: ' . trim($r); fclose($fp); return false; }

    $cmd('QUIT');
    fclose($fp);
    return true;
}

/**
 * Envia e-mail HTML com UMA imagem inline (embutida via cid:).
 * O HTML deve referenciar a imagem como <img src="cid:CID_DA_IMAGEM">.
 *
 * @param string $to             e-mails separados por vírgula
 * @param string $subject        assunto
 * @param string $htmlBody       corpo HTML (usar src="cid:$cidImagem")
 * @param string $imagemBase64   PNG ou JPEG já em base64 (puro, SEM "data:image/...;base64,")
 * @param string $cidImagem      identificador único usado em src="cid:..."
 * @param string $mimeImagem     "image/png" ou "image/jpeg"
 * @param string &$erro          recebe mensagem de erro
 * @param string $replyTo        Reply-To opcional
 */
function enviarEmailSMTPComImagem($to, $subject, $htmlBody, $imagemBase64, $cidImagem, $mimeImagem, &$erro = null, $replyTo = '') {
    // Homologação NÃO dispara e-mail real.
    if (is_file(__DIR__ . '/ambiente.php')) { require_once __DIR__ . '/ambiente.php'; }
    if (!defined('IS_HOMOLOG')) define('IS_HOMOLOG', false);
    if (IS_HOMOLOG) { $erro = ''; return true; }

    global $SMTP_CONFIG;
    $cfg = $SMTP_CONFIG;

    if (empty($cfg['pass']) || $cfg['pass'] === 'COLOQUE_A_SENHA_AQUI') {
        $erro = 'SMTP não configurado: preencha a senha da caixa de e-mail em mail_smtp.php.';
        return false;
    }

    $destinatarios = array_filter(array_map('trim', explode(',', $to)));
    if (!count($destinatarios)) { $erro = 'Sem destinatário válido.'; return false; }

    $host = ($cfg['secure'] === 'ssl' ? 'ssl://' : '') . $cfg['host'];
    $fp = @stream_socket_client($host . ':' . $cfg['port'], $errno, $errstr, 30,
            STREAM_CLIENT_CONNECT, stream_context_create([
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]));
    if (!$fp) { $erro = "Conexão SMTP falhou: $errstr ($errno)"; return false; }
    stream_set_timeout($fp, 30);

    $leia = function() use ($fp) {
        $data = '';
        while ($linha = fgets($fp, 515)) {
            $data .= $linha;
            if (isset($linha[3]) && $linha[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function($c) use ($fp, $leia) { fputs($fp, $c . "\r\n"); return $leia(); };
    $ok  = function($resp, $code) { return substr(trim($resp), 0, 3) === (string)$code; };

    $r = $leia();
    if (!$ok($r, 220)) { $erro = 'SMTP não respondeu: ' . trim($r); fclose($fp); return false; }

    $host_local = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $r = $cmd('EHLO ' . $host_local);
    if (!$ok($r, 250)) { $r = $cmd('HELO ' . $host_local); }

    if ($cfg['secure'] === 'tls') {
        $r = $cmd('STARTTLS');
        if (!$ok($r, 220)) { $erro = 'STARTTLS falhou: ' . trim($r); fclose($fp); return false; }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $erro = 'Falha ao ativar TLS.'; fclose($fp); return false;
        }
        $cmd('EHLO ' . $host_local);
    }

    $r = $cmd('AUTH LOGIN');
    if (!$ok($r, 334)) { $erro = 'AUTH não suportado: ' . trim($r); fclose($fp); return false; }
    $r = $cmd(base64_encode($cfg['user']));
    if (!$ok($r, 334)) { $erro = 'Usuário SMTP recusado: ' . trim($r); fclose($fp); return false; }
    $r = $cmd(base64_encode($cfg['pass']));
    if (!$ok($r, 235)) { $erro = 'Senha SMTP recusada: ' . trim($r); fclose($fp); return false; }

    $r = $cmd('MAIL FROM:<' . $cfg['user'] . '>');
    if (!$ok($r, 250)) { $erro = 'MAIL FROM recusado: ' . trim($r); fclose($fp); return false; }
    foreach ($destinatarios as $dest) {
        $r = $cmd('RCPT TO:<' . $dest . '>');
        if (!$ok($r, 250) && !$ok($r, 251)) { $erro = "Destinatário recusado ($dest): " . trim($r); fclose($fp); return false; }
    }

    $r = $cmd('DATA');
    if (!$ok($r, 354)) { $erro = 'DATA recusado: ' . trim($r); fclose($fp); return false; }

    // ── Monta o e-mail multipart/related com a imagem inline ──
    $boundary = 'iw_bnd_' . bin2hex(random_bytes(8));
    $fromName = '=?UTF-8?B?' . base64_encode($cfg['from_name']) . '?=';

    $headers  = "From: $fromName <" . $cfg['user'] . ">\r\n";
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= "Reply-To: " . $replyTo . "\r\n";
    }
    $headers .= "To: " . implode(', ', $destinatarios) . "\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/related; boundary=\"$boundary\"; type=\"text/html\"\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $cidLimpo = preg_replace('/[^A-Za-z0-9._-]/', '', $cidImagem);
    if ($cidLimpo === '') $cidLimpo = 'img1';

    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= rtrim(chunk_split(base64_encode($htmlBody))) . "\r\n";

    $body .= "--$boundary\r\n";
    $body .= "Content-Type: $mimeImagem; name=\"informativo.jpg\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-ID: <$cidLimpo>\r\n";
    $body .= "Content-Disposition: inline; filename=\"informativo.jpg\"\r\n\r\n";
    $body .= rtrim(chunk_split($imagemBase64)) . "\r\n";

    $body .= "--$boundary--\r\n";

    $msg = $headers . "\r\n" . $body . "\r\n.";
    $r = $cmd($msg);
    if (!$ok($r, 250)) { $erro = 'Envio recusado pelo servidor: ' . trim($r); fclose($fp); return false; }

    $cmd('QUIT');
    fclose($fp);
    return true;
}