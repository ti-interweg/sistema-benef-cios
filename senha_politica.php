<?php
/**
 * Política de senha — Interweg Benefícios
 *
 * Regra de força: mínimo 8 caracteres, com letra MAIÚSCULA, minúscula
 * e caractere especial.
 * Expiração: 30 dias (no login, se a senha estiver vencida, força a troca).
 *
 * Regra de ouro: nunca quebra o login — tudo em try/catch.
 * Requer config.php (usa db()).
 */
if (!function_exists('db')) require_once __DIR__ . '/config.php';

if (!defined('SENHA_MIN_LEN'))  define('SENHA_MIN_LEN', 8);
if (!defined('SENHA_EXP_DIAS')) define('SENHA_EXP_DIAS', 30);

/** Texto curto da regra (para dicas/placeholders). */
function senha_regra_txt() {
    return 'mínimo 8 caracteres, com maiúscula, minúscula e caractere especial';
}

/** Valida a força da senha. Retorna true/false e preenche $erro. */
function senha_forte($s, &$erro = null) {
    $s = (string)$s;
    if (strlen($s) < SENHA_MIN_LEN) {
        $erro = 'A senha precisa ter pelo menos ' . SENHA_MIN_LEN . ' caracteres.'; return false;
    }
    if (!preg_match('/[A-Z]/', $s)) { $erro = 'Inclua pelo menos uma letra MAIÚSCULA.'; return false; }
    if (!preg_match('/[a-z]/', $s)) { $erro = 'Inclua pelo menos uma letra minúscula.'; return false; }
    if (!preg_match('/[^A-Za-z0-9]/', $s)) {
        $erro = 'Inclua pelo menos um caractere especial (ex.: ! @ # $ % & *).'; return false;
    }
    $erro = null;
    return true;
}

/** Cria a coluna senha_alterada_em (idempotente) e inicia o relógio para quem está sem data. */
function senha_migrar() {
    static $done = false;
    if ($done) return;
    $done = true;
    foreach (['analistas', 'usuarios'] as $t) {
        try { db()->exec("ALTER TABLE $t ADD COLUMN senha_alterada_em DATETIME NULL"); } catch (Throwable $e) {}
        // Inicia o relógio na 1ª vez (evita forçar troca de todo mundo no deploy).
        try { db()->exec("UPDATE $t SET senha_alterada_em = NOW() WHERE senha_alterada_em IS NULL"); } catch (Throwable $e) {}
    }
}

/** Diz se a senha do usuário está vencida (mais de SENHA_EXP_DIAS dias sem trocar). */
function senha_expirada($src, $userId) {
    $src = ($src === 'usuarios') ? 'usuarios' : 'analistas';
    try {
        senha_migrar();
        $q = db()->prepare("SELECT senha_alterada_em FROM {$src} WHERE id = ? LIMIT 1");
        $q->execute([$userId]);
        $dt = $q->fetchColumn();
        if (!$dt) return false;
        $idadeDias = (time() - strtotime((string)$dt)) / 86400;
        return $idadeDias > SENHA_EXP_DIAS;
    } catch (Throwable $e) { return false; }
}
