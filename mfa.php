<?php
/**
 * MFA (autenticação em dois fatores) — Interweg Benefícios
 *
 * TOTP compatível com Google Authenticator / Authy / Microsoft Authenticator
 * (RFC 6238, SHA-1, 6 dígitos, janela de 30s) + fallback por e-mail.
 *
 * Implementação em PHP puro (sem dependências externas).
 * Requer config.php carregado antes (usa db()).
 *
 * Perfis obrigados nesta fase: admin, head, coordenador.
 */

if (!function_exists('db')) {
    // Garante que a base (config.php) esteja disponível.
    require_once __DIR__ . '/config.php';
}

/* ─────────────────────────────────────────────────────────────
 *  Config
 * ───────────────────────────────────────────────────────────── */

// ── Chave mestra: liga/desliga o 2º fator para todo o sistema. ──
// Para REATIVAR, troque para true. Quem já cadastrou o app continua válido
// (o segredo fica salvo), então não precisa refazer o cadastro.
const MFA_ATIVO = true;

// FASE DE TESTE: 2FA só para os logins listados aqui.
// Deixe a lista VAZIA ( [] ) para voltar a exigir por PERFIL (admin/head/coordenador/gerente).
const MFA_LOGINS = ['rhudson@interweg-beneficios.com.br', 'alessandra@interweg-beneficios.com.br'];

// Decide se o usuário precisa de MFA.
function mfa_obrigatorio($tipo, $login = '') {
    if (!MFA_ATIVO) return false;
    // Logins de TI/serviço isentos do 2FA (acesso admin sem 2º fator).
    $isentos = ['ti@interweg-beneficios.com.br', 'annaluiza@interweg-beneficios.com.br'];
    if (in_array(strtolower(trim((string)$login)), $isentos, true)) return false;
    // Se houver lista de logins, só eles caem na regra (fase de teste).
    $somente = array_filter(array_map(function ($x) { return strtolower(trim($x)); }, MFA_LOGINS));
    if (!empty($somente)) {
        return in_array(strtolower(trim((string)$login)), $somente, true);
    }
    // Lista vazia → volta a valer por perfil.
    $t = strtolower(trim((string)$tipo));
    return in_array($t, ['admin', 'head', 'coordenador', 'gerente'], true);
}

// Nome que aparece no app autenticador.
const MFA_ISSUER = 'Interweg Beneficios';
// E-mail de suporte da TI (contato em caso de perda de acesso).
const MFA_TI_EMAIL = 'ti@interweg.com.br';
// Validade do código enviado por e-mail (segundos).
const MFA_EMAIL_TTL = 300;
// Tentativas máximas do código por e-mail.
const MFA_EMAIL_MAX_TRIES = 5;

/* ─────────────────────────────────────────────────────────────
 *  Migração idempotente das colunas
 * ───────────────────────────────────────────────────────────── */

function mfa_migrar() {
    foreach (['analistas', 'usuarios'] as $t) {
        try { db()->exec("ALTER TABLE $t ADD COLUMN mfa_enabled TINYINT NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE $t ADD COLUMN mfa_secret VARCHAR(64) NULL"); } catch (Throwable $e) {}
    }
}

/* ─────────────────────────────────────────────────────────────
 *  Base32 (RFC 4648) — sem padding, maiúsculas
 * ───────────────────────────────────────────────────────────── */

function mfa_base32_encode($data) {
    if ($data === '') return '';
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0, $n = strlen($data); $i < $n; $i++) {
        $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    for ($i = 0, $n = strlen($bits); $i < $n; $i += 5) {
        $chunk = substr($bits, $i, 5);
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $out .= $alphabet[bindec($chunk)];
    }
    return $out;
}

function mfa_base32_decode($b32) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', (string)$b32));
    $bits = '';
    for ($i = 0, $n = strlen($b32); $i < $n; $i++) {
        $v = strpos($alphabet, $b32[$i]);
        if ($v === false) continue;
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    for ($i = 0, $n = strlen($bits); $i + 8 <= $n; $i += 8) {
        $bytes .= chr(bindec(substr($bits, $i, 8)));
    }
    return $bytes;
}

/* ─────────────────────────────────────────────────────────────
 *  Geração de segredo + URI otpauth
 * ───────────────────────────────────────────────────────────── */

// Segredo aleatório de 160 bits -> 32 caracteres base32.
function mfa_gerar_secret() {
    return mfa_base32_encode(random_bytes(20));
}

// otpauth://totp/Issuer:conta?secret=...&issuer=...&digits=6&period=30
function mfa_otpauth_uri($secret, $conta) {
    $label  = rawurlencode(MFA_ISSUER) . ':' . rawurlencode($conta);
    $params = http_build_query([
        'secret' => $secret,
        'issuer' => MFA_ISSUER,
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => 30,
    ]);
    return "otpauth://totp/{$label}?{$params}";
}

/* ─────────────────────────────────────────────────────────────
 *  TOTP (RFC 6238)
 * ───────────────────────────────────────────────────────────── */

function mfa_hotp($secretBin, $counter) {
    // Contador de 8 bytes big-endian.
    $bin = pack('N', 0) . pack('N', $counter);
    $hash = hash_hmac('sha1', $bin, $secretBin, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
    $part = (ord($hash[$offset]) & 0x7f) << 24
          | (ord($hash[$offset + 1]) & 0xff) << 16
          | (ord($hash[$offset + 2]) & 0xff) << 8
          | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string)($part % 1000000), 6, '0', STR_PAD_LEFT);
}

function mfa_totp_atual($secretB32, $time = null, $period = 30) {
    if ($time === null) $time = time();
    return mfa_hotp(mfa_base32_decode($secretB32), intval(floor($time / $period)));
}

// Verifica com tolerância de ±$window janelas (compensa relógio dessincronizado).
function mfa_totp_verificar($secretB32, $codigo, $window = 1, $period = 30) {
    $codigo = preg_replace('/\D/', '', (string)$codigo);
    if (strlen($codigo) !== 6) return false;
    $secretBin = mfa_base32_decode($secretB32);
    if ($secretBin === '') return false;
    $base = intval(floor(time() / $period));
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(mfa_hotp($secretBin, $base + $i), $codigo)) return true;
    }
    return false;
}

/* ─────────────────────────────────────────────────────────────
 *  Fallback por e-mail (código de 6 dígitos)
 * ───────────────────────────────────────────────────────────── */

// Gera, guarda na sessão e envia o código por e-mail. Retorna [ok, msg].
function mfa_email_enviar($destino, $nome = '') {
    $destino = trim((string)$destino);
    if ($destino === '' || !filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Não há um e-mail válido cadastrado para este usuário.'];
    }
    $mailFile = __DIR__ . '/mail_smtp.php';
    if (!is_file($mailFile)) return [false, 'Envio de e-mail indisponível no servidor.'];
    require_once $mailFile;
    if (!function_exists('enviarEmailSMTP')) return [false, 'Função de envio de e-mail indisponível.'];

    $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['mfa_email'] = [
        'code'  => $codigo,
        'exp'   => time() + MFA_EMAIL_TTL,
        'tries' => 0,
        'to'    => $destino,
    ];

    $mins = intval(MFA_EMAIL_TTL / 60);
    $safeNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
    $html = "<div style=\"font-family:Arial,Helvetica,sans-serif;max-width:460px;margin:auto;color:#1f2733\">"
          . "<h2 style=\"color:#0a8a8d;margin:0 0 6px\">Código de verificação</h2>"
          . "<p style=\"font-size:14px\">Olá" . ($safeNome ? " {$safeNome}" : "") . ", use o código abaixo para concluir seu acesso ao sistema Interweg Benefícios:</p>"
          . "<div style=\"font-size:34px;font-weight:800;letter-spacing:8px;color:#0a8a8d;background:#f4f6f8;border-radius:12px;padding:16px;text-align:center;margin:14px 0\">{$codigo}</div>"
          . "<p style=\"font-size:12px;color:#6b7280\">O código expira em {$mins} minutos. Se você não tentou acessar o sistema, ignore este e-mail e avise a TI (" . MFA_TI_EMAIL . ").</p>"
          . "</div>";

    // Envia para a pessoa com cópia para a TI (recuperação/suporte).
    $destinatarios = $destino;
    if (strcasecmp($destino, MFA_TI_EMAIL) !== 0) {
        $destinatarios .= ',' . MFA_TI_EMAIL;
    }

    $erro = null;
    $ok = enviarEmailSMTP($destinatarios, 'Interweg Benefícios — código de verificação', $html, $erro);
    if (!$ok) {
        unset($_SESSION['mfa_email']);
        return [false, 'Falha ao enviar o e-mail: ' . ($erro ?: 'servidor recusou')];
    }
    return [true, 'Código enviado para ' . mfa_mascarar_email($destino) . ' (com cópia para a TI).'];
}

// Verifica o código enviado por e-mail (com expiração e limite de tentativas).
function mfa_email_verificar($codigo) {
    $codigo = preg_replace('/\D/', '', (string)$codigo);
    $st = $_SESSION['mfa_email'] ?? null;
    if (!$st) return [false, 'Nenhum código foi enviado. Clique em "Receber por e-mail".'];
    if (time() > ($st['exp'] ?? 0)) { unset($_SESSION['mfa_email']); return [false, 'O código expirou. Envie um novo.']; }
    if (($st['tries'] ?? 0) >= MFA_EMAIL_MAX_TRIES) { unset($_SESSION['mfa_email']); return [false, 'Muitas tentativas. Envie um novo código.']; }
    $_SESSION['mfa_email']['tries'] = ($st['tries'] ?? 0) + 1;
    if (strlen($codigo) === 6 && hash_equals((string)($st['code'] ?? ''), $codigo)) {
        unset($_SESSION['mfa_email']);
        return [true, ''];
    }
    return [false, 'Código incorreto.'];
}

function mfa_mascarar_email($email) {
    $email = (string)$email;
    $at = strpos($email, '@');
    if ($at === false) return $email;
    $user = substr($email, 0, $at);
    $dom  = substr($email, $at);
    $vis  = substr($user, 0, min(2, strlen($user)));
    return $vis . str_repeat('•', max(1, strlen($user) - strlen($vis))) . $dom;
}

/* ─────────────────────────────────────────────────────────────
 *  Estado pendente + concessão de sessão
 * ───────────────────────────────────────────────────────────── */

// Lê mfa_enabled/mfa_secret com segurança (nunca quebra o login).
function mfa_status_usuario($src, $id) {
    $out = ['enabled' => 0, 'secret' => ''];
    if (!in_array($src, ['analistas', 'usuarios'], true)) return $out;
    try {
        $q = db()->prepare("SELECT COALESCE(mfa_enabled,0), mfa_secret FROM {$src} WHERE id = ? LIMIT 1");
        $q->execute([$id]);
        $r = $q->fetch(PDO::FETCH_NUM);
        if ($r) { $out['enabled'] = intval($r[0]); $out['secret'] = (string)($r[1] ?? ''); }
    } catch (Throwable $e) {}
    return $out;
}

// Salva o segredo confirmado e ativa o MFA do usuário.
function mfa_ativar($src, $id, $secret) {
    if (!in_array($src, ['analistas', 'usuarios'], true)) return false;
    $st = db()->prepare("UPDATE {$src} SET mfa_secret = ?, mfa_enabled = 1 WHERE id = ?");
    return $st->execute([$secret, $id]);
}

// Reset (celular perdido): zera o cadastro; força novo no próximo login.
function mfa_resetar($src, $id) {
    if (!in_array($src, ['analistas', 'usuarios'], true)) return false;
    $st = db()->prepare("UPDATE {$src} SET mfa_secret = NULL, mfa_enabled = 0 WHERE id = ?");
    return $st->execute([$id]);
}

// Converte o estado pendente (pós-senha, pré-MFA) em sessão completa.
function mfa_conceder_sessao(array $p) {
    $_SESSION['user_id']    = $p['uid'];
    $_SESSION['user_nome']  = $p['nome'];
    $_SESSION['user_tipo']  = $p['tipo'];
    $_SESSION['user_login'] = $p['login'];
    $_SESSION['user_src']   = $p['src'];
    $_SESSION['user_email'] = $p['email'];
    $_SESSION['must_change_pw'] = (!empty($p['must']) && $p['must'] == 1) ? 1 : 0;
    $_SESSION['mfa_ok'] = 1;
    unset($_SESSION['mfa_pending'], $_SESSION['mfa_setup_secret'], $_SESSION['mfa_email']);
    // Auditoria: login concluído (2º fator validado).
    if (is_file(__DIR__ . '/auditoria.php')) {
        require_once __DIR__ . '/auditoria.php';
        if (function_exists('aud_log')) aud_log('login', 'sessao', $p['uid'], ['mfa' => 1], 'login');
    }
}

// Destino após o MFA (troca de senha obrigatória tem prioridade).
function mfa_destino_pos_login(array $p) {
    return (!empty($p['must']) && $p['must'] == 1) ? 'trocar_senha.php' : 'demandas.php';
}

/* ─────────────────────────────────────────────────────────────
 *  "Confiar neste dispositivo por 14 dias"
 *  Depois de validar o 2FA uma vez, aquele navegador não pede o
 *  código de novo por MFA_LEMBRAR_DIAS. A senha continua sendo pedida.
 * ───────────────────────────────────────────────────────────── */
if (!defined('MFA_LEMBRAR_DIAS'))  define('MFA_LEMBRAR_DIAS', 14);
if (!defined('MFA_TRUST_COOKIE'))  define('MFA_TRUST_COOKIE', 'iw_mfa_trust');

function mfa_confianca_migrar() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS mfa_confianca (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            token_hash CHAR(64) NOT NULL,
            user_src VARCHAR(12) NOT NULL,
            user_id INT NOT NULL,
            expira_em DATETIME NOT NULL,
            criado_em DATETIME NOT NULL,
            INDEX idx_hash (token_hash),
            INDEX idx_user (user_src, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

// Registra este navegador como confiável por MFA_LEMBRAR_DIAS dias.
function mfa_confianca_registrar($src, $uid) {
    try {
        mfa_confianca_migrar();
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $exp   = date('Y-m-d H:i:s', time() + MFA_LEMBRAR_DIAS * 86400);
        db()->prepare("INSERT INTO mfa_confianca (token_hash, user_src, user_id, expira_em, criado_em) VALUES (?, ?, ?, ?, NOW())")
            ->execute([$hash, ($src === 'usuarios' ? 'usuarios' : 'analistas'), intval($uid), $exp]);
        $seguro = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(MFA_TRUST_COOKIE, $token, [
            'expires'  => time() + MFA_LEMBRAR_DIAS * 86400,
            'path'     => '/',
            'secure'   => $seguro,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } catch (Throwable $e) {}
}

// Diz se este navegador já é confiável para ESTE usuário (pula o 2FA).
function mfa_confianca_valida($src, $uid) {
    try {
        $token = $_COOKIE[MFA_TRUST_COOKIE] ?? '';
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return false;
        mfa_confianca_migrar();
        $hash = hash('sha256', $token);
        $q = db()->prepare("SELECT 1 FROM mfa_confianca
            WHERE token_hash = ? AND user_src = ? AND user_id = ? AND expira_em > NOW() LIMIT 1");
        $q->execute([$hash, ($src === 'usuarios' ? 'usuarios' : 'analistas'), intval($uid)]);
        return (bool)$q->fetchColumn();
    } catch (Throwable $e) { return false; }
}
