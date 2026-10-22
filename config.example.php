<?php
// =========================================================
//  config.php — Configurações do sistema
//  IMPORTANTE: Altere DB_PASS com a senha que você criou
// =========================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'u207808890_beneficios');
define('DB_USER', 'u207808890_beneficios');
define('DB_PASS', 'COLOQUE_A_SENHA_DO_BANCO');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'Controle de Demandas — Benefícios');
define('SITE_URL',  'https://interweg-beneficios.com.br');
define('CLAUDE_API_KEY', 'COLOQUE_SUA_CHAVE_ANTHROPIC');


// Sessão — configurada pra durar 12 horas de inatividade (dia útil)
// Antes do session_start é a única hora que essas configs valem.
session_name('beneficios_sess');
if (session_status() === PHP_SESSION_NONE) {
    // Tempo de vida do cookie e da sessão no servidor (12h = 43200 segundos)
    ini_set('session.gc_maxlifetime', 43200);
    ini_set('session.cookie_lifetime', 43200);
    // Probabilidade do garbage collector rodar (1 em 100 requisições)
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    // Cookie acessível só via HTTP(S) e apenas em conexões seguras (HTTPS)
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    session_set_cookie_params([
        'lifetime' => 43200,
        'path'     => '/',
        'httponly' => true,
        'secure'   => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Sliding session — renova o cookie a cada requisição
    if (!empty($_SESSION['user_id'])) {
        setcookie(session_name(), session_id(), [
            'expires'  => time() + 43200,
            'path'     => '/',
            'httponly' => true,
            'secure'   => true,
            'samesite' => 'Lax',
        ]);
    }
}

// Conexão PDO (lazy)
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// Helpers
function auth(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php'); exit;
    }
}

// "Admin" engloba também Head e Coordenador — os 3 perfis têm exatamente
// o mesmo nível de permissão (acesso total). Pra checar se é STRITAMENTE
// admin (em casos raros como gestão de licenças, etc), usar isAdminPuro().
function isAdmin(): bool {
    $t = $_SESSION['user_tipo'] ?? '';
    return in_array($t, ['admin','head','coordenador'], true);
}

// Apenas admin de verdade (caso futuro: gestão de planos, billing, etc).
// Por enquanto não é usado em lugar nenhum — fica reservado.
function isAdminPuro(): bool {
    return ($_SESSION['user_tipo'] ?? '') === 'admin';
}

// Usuário "Novos Negócios" (ex.: Luiza) — vê só a área de Ferramentas
// (Cotações, Formulário Cotação, Apresentação, Controle de Cotação).
function isNovosNegocios(): bool {
    return ($_SESSION['user_tipo'] ?? '') === 'novos_negocios';
}

// Usuário "Financeiro" — vê só Faturamento e SÓ pode editar comissão recebida.
// Não pode cadastrar/alterar empresas nem outros campos do faturamento.
function isFinanceiro(): bool {
    return ($_SESSION['user_tipo'] ?? '') === 'financeiro';
}

// Usuário "Visualizador" — vê TUDO (igual admin) mas NÃO pode editar nada.
// Útil pra diretoria (Mucida, Walter, Ricardo).
function isVisualizador(): bool {
    return ($_SESSION['user_tipo'] ?? '') === 'visualizador';
}

// Pode ver todos os registros da carteira (não filtra por analista_nome).
// Admin e Visualizador têm visão completa.
function canViewAll(): bool {
    return isAdmin() || isVisualizador();
}

// Enfermeira (área assistencial / ações de saúde)
function isEnfermeira(): bool {
    $t = $_SESSION['user_tipo'] ?? '';
    return $t === 'enfermeira' || $t === 'enfermagem';
}

// Coordenador (acompanha equipe; ainda sem permissão especial — espelho de analista)
function isCoordenador(): bool {
    return ($_SESSION['user_tipo'] ?? '') === 'coordenador';
}

// Comercial (Cotação) — vê SOMENTE Controle de Cotação e Fechamento (ex.: Frederico).
function isComercialCotacao(): bool {
    return ($_SESSION['user_tipo'] ?? '') === 'comercial_cotacao';
}

// Label amigável pra cada tipo de usuário (usado nos cards da equipe).
function getRoleLabel(?string $tipo): string {
    switch ((string)$tipo) {
        case 'admin':           return 'Admin';
        case 'head':            return 'Head';
        case 'coordenador':     return 'Coordenador';
        case 'enfermeira':
        case 'enfermagem':      return 'Enfermeira';
        case 'financeiro':      return 'Financeiro';
        case 'novos_negocios':  return 'Novos Negócios';
        case 'comercial_cotacao': return 'Comercial (Cotação)';
        case 'visualizador':    return 'Visualizador';
        case 'analista':        return 'Analista';
        default:                return $tipo ?: 'Analista';
    }
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function jsonOut($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

