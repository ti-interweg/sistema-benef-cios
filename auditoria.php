<?php
/**
 * Log de auditoria — Interweg Benefícios
 *
 * Registra ACESSOS (páginas visitadas) e ALTERAÇÕES (ações de escrita da API),
 * além de login/logout. Cada registro guarda: data/hora, IP, usuário/ID/perfil,
 * o processo (ação/página) e um resumo do que foi enviado.
 *
 * Regra de ouro: auditoria NUNCA quebra o sistema — tudo em try/catch.
 * Requer config.php carregado antes (usa db() e $_SESSION).
 */

if (!function_exists('db')) {
    require_once __DIR__ . '/config.php';
}

/* ─────────────────────────────────────────────────────────────
 *  Quem pode VER o histórico/auditoria (restrito).
 *  Edite esta lista com os logins/e-mails autorizados.
 *  Compara com o login E com o e-mail da sessão (sem diferenciar
 *  maiúsc./minúsc.). O registro continua rodando para todos —
 *  isto controla apenas quem consegue CONSULTAR.
 * ───────────────────────────────────────────────────────────── */
$GLOBALS['AUD_VER'] = [
    'ti@interweg.com.br',
    'admin@interweg-beneficios.com.br',   // <-- ajuste/adicione seu login aqui
];

// Token do link público de indicadores (gera e guarda se não existir).
function aud_token_publico() {
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS app_config (chave VARCHAR(60) PRIMARY KEY, valor TEXT)");
        $q = db()->prepare("SELECT valor FROM app_config WHERE chave = 'indicadores_token'");
        $q->execute();
        $tok = (string)$q->fetchColumn();
        if ($tok === '') {
            $tok = bin2hex(random_bytes(16));
            db()->prepare("INSERT INTO app_config (chave, valor) VALUES ('indicadores_token', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")->execute([$tok]);
        }
        return $tok;
    } catch (Throwable $e) { return ''; }
}

function aud_pode_ver() {
    // Precisa estar logado.
    if (empty($_SESSION['user_id'])) return false;
    $lista = array_map(function ($x) { return strtolower(trim($x)); }, $GLOBALS['AUD_VER'] ?? []);
    $login = strtolower(trim((string)($_SESSION['user_login'] ?? '')));
    $email = strtolower(trim((string)($_SESSION['user_email'] ?? '')));
    return ($login !== '' && in_array($login, $lista, true))
        || ($email !== '' && in_array($email, $lista, true));
}

/* ── Cria a tabela (idempotente, roda no máx. 1x por request) ── */
function aud_migrar_once() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS log_auditoria (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            criado_em DATETIME NOT NULL,
            user_id INT NULL,
            user_nome VARCHAR(160) NULL,
            user_tipo VARCHAR(40) NULL,
            ip VARCHAR(64) NULL,
            evento VARCHAR(20) NOT NULL,
            acao VARCHAR(90) NULL,
            entidade VARCHAR(60) NULL,
            entidade_id VARCHAR(40) NULL,
            detalhe TEXT NULL,
            INDEX idx_criado (criado_em),
            INDEX idx_user (user_id),
            INDEX idx_evento (evento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

/* ── IP do cliente (considera proxy/CDN da Hostinger) ── */
function aud_ip() {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/* ── Remove/limita campos sensíveis ou gigantes do detalhe ── */
function aud_sanitizar($arr) {
    if (!is_array($arr)) return null;
    $ocultar = ['senha', 'password', 'pass', 'secret', 'mfa_secret', 'codigo', 'token',
                'htmlbody', 'html', 'corpohtml', 'corpo', 'imagembase64', 'imagem', 'base64'];
    $out = [];
    foreach ($arr as $k => $v) {
        $kl = strtolower((string)$k);
        $hide = false;
        foreach ($ocultar as $h) { if (strpos($kl, $h) !== false) { $hide = true; break; } }
        if ($hide) { $out[$k] = '(oculto)'; continue; }
        if (is_array($v)) { $out[$k] = '(lista: ' . count($v) . ')'; continue; }
        $s = (string)$v;
        if (mb_strlen($s) > 200) $s = mb_substr($s, 0, 200) . '…';
        $out[$k] = $s;
    }
    return $out;
}

/* ── Grava um registro de auditoria ── */
function aud_log($acao, $entidade = '', $entidadeId = null, $detalhe = null, $evento = 'alteracao') {
    try {
        aud_migrar_once();
        $det = null;
        if ($detalhe !== null) {
            $det = is_string($detalhe) ? $detalhe : json_encode(aud_sanitizar($detalhe), JSON_UNESCAPED_UNICODE);
            if ($det !== null && mb_strlen($det) > 2000) $det = mb_substr($det, 0, 2000) . '…';
        }
        $st = db()->prepare("INSERT INTO log_auditoria
            (criado_em, user_id, user_nome, user_tipo, ip, evento, acao, entidade, entidade_id, detalhe)
            VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $st->execute([
            $_SESSION['user_id']   ?? null,
            $_SESSION['user_nome'] ?? null,
            $_SESSION['user_tipo'] ?? null,
            aud_ip(),
            (string)$evento,
            (string)$acao,
            (string)$entidade,
            ($entidadeId !== null && $entidadeId !== '') ? (string)$entidadeId : null,
            $det,
        ]);
    } catch (Throwable $e) { /* nunca propaga */ }
}

/* ── Decide se a ação da API é de escrita (deve ser auditada) ── */
function aud_eh_escrita($action) {
    $action = (string)$action;
    if ($action === '') return false;
    // Leitura NÃO gera log de alteração (o acesso a páginas já é registrado à parte).
    $leitura = ['listar', 'buscar', 'stats', 'dashboard', 'exportar', 'carregar',
                'get', 'ver_', 'detalhe', 'totais', 'resumo_dados', 'filtros', 'opcoes',
                'consultar', 'checar', 'validar_'];
    foreach ($leitura as $p) { if (strpos($action, $p) === 0) return false; }
    // Verbos de escrita conhecidos.
    $escrita = ['criar', 'editar', 'excluir', 'deletar', 'remover', 'salvar', 'atualizar',
                'importar', 'enviar', 'virar', 'gerar', 'aplicar', 'reset', 'mover',
                'duplicar', 'inserir', 'definir', 'marcar', 'concluir', 'cancelar',
                'arquivar', 'restaurar', 'vincular', 'desvincular', 'trocar', 'novo', 'add'];
    foreach ($escrita as $p) { if (strpos($action, $p) === 0) return true; }
    return false; // desconhecida: não loga como alteração (evita ruído)
}

/* ── Chamado no topo da API (antes do switch) ── */
function aud_registrar_api($action) {
    if (!aud_eh_escrita($action)) return;
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];
    $id = $body['id'] ?? ($_GET['id'] ?? null);
    $parts = explode('_', (string)$action, 2);
    $entidade = $parts[1] ?? '';
    // Ações genéricas criar/editar/excluir atuam sobre Demandas (sem sufixo).
    if ($entidade === '' && in_array((string)$action, ['criar', 'editar', 'excluir'], true)) {
        $entidade = 'demanda';
    }
    $ex = ['excluir', 'deletar', 'remover'];
    $evento = 'alteracao';
    foreach ($ex as $p) { if (strpos((string)$action, $p) === 0) { $evento = 'exclusao'; break; } }
    aud_log($action, $entidade, $id, $body, $evento);
}
