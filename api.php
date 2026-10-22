<?php
/**
 * =====================================================
 * API - INTERWEG Benefícios
 * =====================================================
 * Todos os endpoints consumidos pelo index.php
 */

require_once 'config.php';
require_once __DIR__ . '/_perm_silmara.php';

// ── Auth pra API: NÃO redireciona pra login (isso quebra AJAX),
// retorna JSON 401 pra o frontend tratar (ex: avisar usuário + reload) ──
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'session_expired' => true,
        'msg' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

// ── Escopo "Renovação" (tipo cliente interno) enxerga tudo nas telas dele ──
if (!function_exists('iw_ve_tudo')) {
    function iw_ve_tudo() {
        return canViewAll() || (strtolower(trim($_SESSION['user_tipo'] ?? '')) === 'renovacao');
    }
}
// Pode EDITAR renovações: admin ou o escopo Renovação (não inclui visualizador).
if (!function_exists('iw_edita_renovacao')) {
    function iw_edita_renovacao() {
        return isAdmin() || (strtolower(trim($_SESSION['user_tipo'] ?? '')) === 'renovacao');
    }
}

// Resolve a tabela de faturamento (lista branca) para os módulos espelho.
// Permite que os endpoints *_faturamento_backup gravem/leiam numa tabela
// alternativa (ex.: faturamento_outubro) via parâmetro "tabela", sem risco
// de injeção (só valores da lista são aceitos).
if (!function_exists('fb_pode_alterar_outubro')) {
    function fb_pode_alterar_outubro() {
        if (function_exists('isAdmin') && isAdmin()) return true;
        $n = strtolower(trim($_SESSION['user_nome'] ?? ''));
        return (strpos($n, 'leticia') !== false || strpos($n, 'letícia') !== false);
    }
}
if (!function_exists('fb_tabela')) {
    // $permitirTodos=true libera a gravação nos meses novos para qualquer analista
    // (usado pelo "Ajuste rápido"); caso contrário, só a Letícia/admin.
    function fb_tabela($d, $permitirTodos = false) {
        $permitidas = ['faturamento_backup', 'faturamento_outubro', 'faturamento_novembro'];
        $t = is_array($d) ? trim((string)($d['tabela'] ?? '')) : '';
        $t = in_array($t, $permitidas, true) ? $t : 'faturamento_backup';
        if (!$permitirTodos
            && in_array($t, ['faturamento_outubro', 'faturamento_novembro'], true)
            && !fb_pode_alterar_outubro()) {
            json(['ok' => false, 'msg' => 'Apenas a Letícia pode alterar este faturamento.']);
        }
        return $t;
    }
}

// Garante a coluna de soft-delete das atas (idempotente, roda 1x por request).
if (!function_exists('iw_atas_softdel')) {
    function iw_atas_softdel() {
        static $done = false;
        if ($done) return;
        $done = true;
        try { db()->exec("ALTER TABLE atas_visitas ADD COLUMN IF NOT EXISTS excluida_em DATETIME NULL"); } catch (Throwable $e) {}
    }
}

// ── Consulta UF/município de um CNPJ na Receita (BrasilAPI, fallback ReceitaWS) ──
if (!function_exists('cc_uf_lookup')) {
    function cc_uf_lookup($cnpj){
        $d = preg_replace('/\D/', '', (string)$cnpj);
        if (strlen($d) !== 14) return null;
        $tryUrl = function($url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8,
                CURLOPT_FOLLOWLOCATION=>true, CURLOPT_HTTPHEADER=>['Accept: application/json']]);
            $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            if ($code === 200 && $resp) return json_decode($resp, true);
            return null;
        };
        $j = $tryUrl("https://brasilapi.com.br/api/cnpj/v1/$d");
        if (is_array($j)) {
            $uf = strtoupper(trim($j['uf'] ?? ''));
            if ($uf) return ['uf'=>$uf, 'municipio'=>trim($j['municipio'] ?? '')];
        }
        $j = $tryUrl("https://receitaws.com.br/v1/cnpj/$d");
        if (is_array($j)) {
            $uf = strtoupper(trim($j['uf'] ?? ''));
            if ($uf) return ['uf'=>$uf, 'municipio'=>trim($j['municipio'] ?? '')];
        }
        return null;
    }
}

// ── Helpers ─────────────────────────────────────────
function json($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonBody() {
    // Lê o corpo UMA vez e guarda em cache (evita qualquer conflito com a
    // auditoria, que também precisa inspecionar o corpo da requisição).
    static $cache = null;
    if ($cache === null) {
        $cache = json_decode(file_get_contents('php://input'), true) ?: [];
    }
    return $cache;
}

function adminOnly() {
    if (!isAdmin()) {
        json(['ok' => false, 'msg' => 'Acesso negado']);
    }
}

// Cadastros → Equipe: admin e enfermagem gerenciam usuários (criar/editar/excluir).
function equipeAdmin() {
    if (!isAdmin() && !isEnfermeira()) {
        json(['ok' => false, 'msg' => 'Acesso negado']);
    }
}

// Normaliza qualquer string de porte (vinda do XLSX, planilha legada ou input
// manual) para um dos 4 valores oficiais do ENUM `empresas.porte`:
//   Até 30 vidas | Entre 30 e 100 vidas | Entre 100 e 900 | Acima de 1000
// Aceita formatos antigos ('3 a 29 vidas', '30 a 99 vidas', 'acima de 100 vidas',
// 'até 29', '30 a 100' etc.) e devolve o equivalente atual. Compatível com PHP 7.2.
function _mapearPorteParaEnum($v) {
    if ($v === null || $v === '') return null;
    $s = mb_strtolower(trim((string)$v));
    $has = function($needle) use ($s) { return strpos($s, $needle) !== false; };
    if ($has('até 30') || $has('ate 30') || $has('3 a 29') || $has('até 29') || $has('ate 29')) return 'Até 30 vidas';
    if ($has('30 e 100') || $has('30 a 99') || $has('30 a 100')) return 'Entre 30 e 100 vidas';
    if ($has('1000') || $has('acima de 1000')) return 'Acima de 1000';
    if ($has('100 e 900') || $has('100 a 900') || $has('acima de 100') || $has('acima')) return 'Entre 100 e 900';
    return null; // valor não reconhecido vai NULL pra não dar erro de enum
}

// ── Validação de CNPJ (algoritmo Receita Federal) ──
function validarCNPJ($cnpj) {
    $cnpj = preg_replace('/\D/', '', (string)$cnpj);
    if (strlen($cnpj) !== 14) return false;
    if (preg_match('/^(\d)\1{13}$/', $cnpj)) return false;

    $pesos1 = [5,4,3,2,9,8,7,6,5,4,3,2];
    $soma = 0;
    for ($i = 0; $i < 12; $i++) $soma += intval($cnpj[$i]) * $pesos1[$i];
    $resto = $soma % 11;
    $dv1   = ($resto < 2) ? 0 : 11 - $resto;
    if (intval($cnpj[12]) !== $dv1) return false;

    $pesos2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
    $soma = 0;
    for ($i = 0; $i < 13; $i++) $soma += intval($cnpj[$i]) * $pesos2[$i];
    $resto = $soma % 11;
    $dv2   = ($resto < 2) ? 0 : 11 - $resto;
    if (intval($cnpj[13]) !== $dv2) return false;

    return true;
}

function formatarCNPJ($cnpj) {
    $cnpj = preg_replace('/\D/', '', (string)$cnpj);
    if (strlen($cnpj) !== 14) return $cnpj;
    return substr($cnpj,0,2).'.'.substr($cnpj,2,3).'.'.substr($cnpj,5,3).'/'.substr($cnpj,8,4).'-'.substr($cnpj,12,2);
}

// ── Router ──────────────────────────────────────────
$action = $_GET['action'] ?? '';

// ── Trava do portal do cliente: tipo 'cliente' só acessa os endpoints do portal ──
if (($_SESSION['user_tipo'] ?? '') === 'cliente'
    && !in_array($action, ['cliente_listar_demandas','cliente_criar_demanda'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Acesso negado']);
    exit;
}

// ── Auditoria: registra ações de escrita (data/hora, IP, usuário, processo) ──
require_once __DIR__ . '/auditoria.php';
aud_registrar_api($action, jsonBody());
require_once __DIR__ . '/senha_politica.php';
senha_migrar();

// ── Permissão de time operacional ─────────────────────────────
// Retorna a carteira operacional que um analista enxerga além da própria.
// (mesma lógica usada no Faturamento). Ex.: Gabriela -> 'Operacao 1'.
if (!function_exists('carteiraOperacional')) {
    function carteiraOperacional($nome) {
        $mapa = [
            'nivea' => 'Operacao 1', 'gabriela' => 'Operacao 1', 'nayara' => 'Operacao 1',
            'operacao 1' => 'Operacao 1', 'operação 1' => 'Operacao 1',
            'angela' => 'Operacao 2', 'welberth' => 'Operacao 2', 'welbert' => 'Operacao 2',
            'operacao 2' => 'Operacao 2', 'operação 2' => 'Operacao 2',
        ];
        $k = mb_strtolower(trim((string)$nome));
        return $mapa[$k] ?? null;
    }
}

switch ($action) {

    // ═══════════════════════════════════════════════════
    //  KEEPALIVE — Mantém a sessão viva enquanto a pessoa
    //  estiver com a aba aberta. JS faz ping a cada 5 min.
    //  Sem isso, depois de 12h SEM nenhum ping, expira.
    //  COM ping a cada 5 min, a sessão nunca cai enquanto
    //  o sistema estiver aberto.
    // ═══════════════════════════════════════════════════
    case 'keepalive':
        json(['ok' => true, 'ts' => time(), 'user' => $_SESSION['user_nome'] ?? null]);
        break;

    // ═══════════════════════════════════════════════════
    //  DEMANDAS
    // ═══════════════════════════════════════════════════

    case 'listar':
        $sql    = "SELECT d.*, COALESCE(a.nome, d.analista_nome) AS analista_nome FROM demandas d LEFT JOIN analistas a ON d.analista_id = a.id WHERE 1=1";
        $params = [];

        // Filtro: busca geral
        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $like     = "%{$search}%";
            $sql     .= " AND (d.dados_beneficiario LIKE ? OR d.empresa LIKE ? OR d.grupo LIKE ? OR d.protocolo LIKE ? OR d.seguradora LIKE ? OR d.assunto_email LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Filtro: analista
        $analista = trim($_GET['analista'] ?? '');
        // Segurança: analista comum (não admin/visualizador) fica TRAVADO na própria
        // carteira — ignora qualquer analista pedido no parâmetro e usa o do login.
        if (!canViewAll()) {
            $analista = trim($_SESSION['user_nome'] ?? '');
        }
        if ($analista) {
            // Filtro estrito: cada pessoa vê SOMENTE as demandas no próprio nome.
            // (Não puxa mais a carteira operacional do time — ex.: Welbert não vê "Operação 2".)
            $sql     .= " AND COALESCE(a.nome, d.analista_nome) = ?";
            $params[] = $analista;
        }

        // Filtro: situação
        $situacao = trim($_GET['situacao'] ?? '');
        if ($situacao) {
            $sql     .= " AND d.situacao = ?";
            $params[] = $situacao;
        }
        // As demandas em TRIAGEM (enviadas pelo cliente, ainda não distribuídas)
        // não aparecem na lista de Demandas — só na tela de Triagem. Elas entram
        // aqui depois de atribuídas (quando a situação deixa de ser 'Triagem').
        if ($situacao !== 'Triagem') {
            $sql .= " AND d.situacao <> 'Triagem'";
        }

        // Filtro: ramo
        $ramo = trim($_GET['ramo'] ?? '');
        if ($ramo) {
            $sql     .= " AND d.ramo = ?";
            $params[] = $ramo;
        }

        // Filtro: tipo
        $tipo = trim($_GET['tipo'] ?? '');
        if ($tipo) {
            $sql     .= " AND d.tipo_demanda = ?";
            $params[] = $tipo;
        }

        // Filtro: origem
        $origemF = trim($_GET['origem'] ?? '');
        if ($origemF) {
            $sql     .= " AND d.origem = ?";
            $params[] = $origemF;
        }

        // Filtro: intervalo de data de recebimento (usado pelo Exportar Excel
        // e também pelos filtros novos f-receb-de / f-receb-ate da tela)
        $dataIni = trim($_GET['data_ini'] ?? $_GET['receb_de'] ?? '');
        if ($dataIni && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataIni)) {
            $sql     .= " AND d.recebimento >= ?";
            $params[] = $dataIni;
        }
        $dataFim = trim($_GET['data_fim'] ?? $_GET['receb_ate'] ?? '');
        if ($dataFim && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
            $sql     .= " AND d.recebimento <= ?";
            $params[] = $dataFim;
        }

        // Filtro: intervalo de PRÓXIMA AÇÃO (novos)
        $proxDe = trim($_GET['prox_de'] ?? '');
        if ($proxDe && preg_match('/^\d{4}-\d{2}-\d{2}$/', $proxDe)) {
            $sql     .= " AND d.proxima_acao >= ?";
            $params[] = $proxDe;
        }
        $proxAte = trim($_GET['prox_ate'] ?? '');
        if ($proxAte && preg_match('/^\d{4}-\d{2}-\d{2}$/', $proxAte)) {
            $sql     .= " AND d.proxima_acao <= ?";
            $params[] = $proxAte;
        }

        // Filtro: ATRASADAS (próxima ação vencida e não concluída)
        // Disparado pelo clique no card vermelho "Atrasadas"
        if (!empty($_GET['atrasado']) && $_GET['atrasado'] === '1') {
            $sql     .= " AND d.proxima_acao < CURDATE() AND d.situacao != 'Concluído'";
        }

        $sql .= " ORDER BY d.id DESC";

        // Paginação (LIMIT máximo 5000, default 2000)
        $limit  = max(1, min(5000, intval($_GET['limit']  ?? 2000)));
        $offset = max(0, intval($_GET['offset'] ?? 0));
        $sql .= " LIMIT $limit OFFSET $offset";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json(['ok' => true, 'data' => $rows, 'limit' => $limit, 'offset' => $offset]);
        break;

    case 'buscar':
        $id   = intval($_GET['id'] ?? 0);
        $stmt = db()->prepare("SELECT d.*, COALESCE(a.nome, d.analista_nome) AS analista_nome FROM demandas d LEFT JOIN analistas a ON d.analista_id = a.id WHERE d.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) json(['ok' => false, 'msg' => 'Demanda não encontrada']);
        json(['ok' => true, 'data' => $row]);
        break;

    case 'criar':
        $d = jsonBody();

        // Regra: origem vazia sempre vira 'INTERWEG'
        $origemDem = trim($d['origem'] ?? '');
        if ($origemDem === '') $origemDem = 'INTERWEG';

        // Aceita 'observacoes' como alias de 'detalhamento' (compat com JS antigo)
        if (empty($d['detalhamento']) && !empty($d['observacoes'])) {
            $d['detalhamento'] = $d['observacoes'];
        }

        $stmt = db()->prepare("
            INSERT INTO demandas
                (origem, grupo, empresa, seguradora, ramo, tipo_demanda, situacao,
                 dados_beneficiario, descricao_demanda, assunto_email, analista_id,
                 analista_nome, recebimento, inicio_tratativa, proxima_acao,
                 data_conclusao, protocolo, detalhamento)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $origemDem,
            trim($d['grupo'] ?? ''),
            trim($d['empresa'] ?? ''),
            trim($d['seguradora'] ?? ''),
            trim($d['ramo'] ?? ''),
            trim($d['tipo_demanda'] ?? ''),
            trim($d['situacao'] ?? 'Em análise'),
            trim($d['dados_beneficiario'] ?? ''),
            trim($d['descricao_demanda'] ?? ''),
            trim($d['assunto_email'] ?? ''),
            intval($d['analista_id'] ?? 0) ?: null,
            trim($d['analista_nome'] ?? ''),
            $d['recebimento'] ?: null,
            $d['inicio_tratativa'] ?: null,
            $d['proxima_acao'] ?: null,
            $d['data_conclusao'] ?: null,
            trim($d['protocolo'] ?? ''),
            trim($d['detalhamento'] ?? ''),
        ]);

        $novaDemandaId = db()->lastInsertId();

        // ── Notifica o analista responsável (sino) sobre a nova demanda ──
        // Só notifica se a demanda foi atribuída a OUTRA pessoa (não a si mesmo).
        try {
            $destN = trim($d['analista_nome'] ?? '');
            $quem  = trim($_SESSION['user_nome'] ?? '');
            if ($destN !== '' && $destN !== $quem) {
                db()->exec("CREATE TABLE IF NOT EXISTS notificacoes (
                    id INT AUTO_INCREMENT PRIMARY KEY, tipo VARCHAR(40) NOT NULL DEFAULT 'demanda',
                    titulo VARCHAR(200) NOT NULL, mensagem VARCHAR(500) NULL, url VARCHAR(300) NULL,
                    empresa_id INT NULL, criado_por INT NULL, criado_por_nome VARCHAR(120) NULL,
                    destino_nome VARCHAR(190) NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                try { db()->exec("ALTER TABLE notificacoes ADD COLUMN destino_nome VARCHAR(190) NULL"); } catch (Throwable $e) {}
                $emp = trim($d['empresa'] ?? '') ?: trim($d['grupo'] ?? '');
                $tit = 'Nova demanda' . ($emp !== '' ? ': ' . $emp : '');
                $partes = [];
                if (!empty($d['tipo_demanda'])) $partes[] = trim($d['tipo_demanda']);
                if (!empty($d['seguradora']))   $partes[] = trim($d['seguradora']);
                if ($quem !== '') $partes[] = 'atribuída por ' . $quem;
                $stN = db()->prepare("INSERT INTO notificacoes (tipo, titulo, mensagem, url, criado_por, criado_por_nome, destino_nome)
                                      VALUES ('demanda', ?, ?, 'demandas.php', ?, ?, ?)");
                $stN->execute([$tit, implode(' · ', $partes), (intval($_SESSION['user_id'] ?? 0) ?: null), ($quem ?: null), $destN]);
            }
        } catch (Throwable $e) { /* não bloqueia a criação da demanda */ }

        json(['ok' => true, 'id' => $novaDemandaId]);
        break;

    // ═══════════ PORTAL DO CLIENTE (ex.: Mercantil) ═══════════
    case 'cliente_listar_demandas':
        if (($_SESSION['user_tipo'] ?? '') !== 'cliente') json(['ok'=>false,'msg'=>'Acesso negado']);
        $grp = trim($_SESSION['user_grupo'] ?? '');
        $uid = intval($_SESSION['user_id'] ?? 0);
        if ($grp === '') json(['ok'=>false,'msg'=>'Login sem grupo configurado.']);
        try {
            try { db()->exec("ALTER TABLE demandas ADD COLUMN cliente_uid INT NULL"); } catch (Throwable $e) {}
            try { db()->exec("ALTER TABLE demandas ADD COLUMN anexo_url VARCHAR(255) NULL"); } catch (Throwable $e) {}
            $st = db()->prepare("SELECT id, tipo_demanda, ramo, empresa, dados_beneficiario, situacao,
                                        recebimento, protocolo, detalhamento, anexo_url, criado_em
                                 FROM demandas
                                 WHERE grupo = ? AND cliente_uid = ?
                                 ORDER BY id DESC LIMIT 500");
            $st->execute([$grp, $uid]);
            json(['ok'=>true, 'grupo'=>$grp, 'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { json(['ok'=>false,'msg'=>'Erro ao listar: '.$e->getMessage()]); }
        break;

    case 'cliente_criar_demanda':
        if (($_SESSION['user_tipo'] ?? '') !== 'cliente') json(['ok'=>false,'msg'=>'Acesso negado']);
        $grp = trim($_SESSION['user_grupo'] ?? '');
        $uid = intval($_SESSION['user_id'] ?? 0);
        if ($grp === '') json(['ok'=>false,'msg'=>'Login sem grupo configurado.']);
        // multipart (tem anexo) — usa $_POST/$_FILES
        $tipoDem = trim($_POST['tipo_demanda'] ?? '');
        $ramo    = trim($_POST['ramo'] ?? '');
        $seguradora = trim($_POST['seguradora'] ?? '');
        $empresa = trim($_POST['empresa'] ?? '');
        $benNome = trim($_POST['beneficiario_nome'] ?? '');
        $benCpf  = trim($_POST['beneficiario_cpf'] ?? '');
        $obs     = trim($_POST['observacao'] ?? '');
        if (!in_array($tipoDem, ['Inclusão','Exclusão'], true)) json(['ok'=>false,'msg'=>'Tipo deve ser Inclusão ou Exclusão.']);
        if ($empresa === '' || $benNome === '') json(['ok'=>false,'msg'=>'Informe a empresa e o nome do beneficiário.']);
        try {
            try { db()->exec("ALTER TABLE demandas ADD COLUMN cliente_uid INT NULL"); } catch (Throwable $e) {}
            try { db()->exec("ALTER TABLE demandas ADD COLUMN anexo_url VARCHAR(255) NULL"); } catch (Throwable $e) {}
            // anexo opcional
            $anexoUrl = null;
            if (!empty($_FILES['anexo']) && ($_FILES['anexo']['error'] ?? 4) === 0) {
                $f = $_FILES['anexo'];
                if ($f['size'] > 10*1024*1024) json(['ok'=>false,'msg'=>'Anexo maior que 10MB.']);
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf','jpg','jpeg','png','webp','doc','docx','xls','xlsx','csv'], true)) json(['ok'=>false,'msg'=>'Tipo de anexo não permitido.']);
                $dir = __DIR__ . '/uploads_cliente';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $f['name']);
                $fn = date('Ymd_His') . '_' . substr(md5(uniqid('', true)),0,8) . '_' . $safe;
                if (move_uploaded_file($f['tmp_name'], "$dir/$fn")) $anexoUrl = 'uploads_cliente/' . $fn;
            }
            $benExib = $benNome . ($benCpf !== '' ? ' (CPF: '.$benCpf.')' : '');
            $proto = 'MERC-' . date('ymd') . '-' . substr((string)microtime(true), -5);
            $ins = db()->prepare("INSERT INTO demandas
                (origem, grupo, empresa, seguradora, ramo, tipo_demanda, situacao, dados_beneficiario,
                 descricao_demanda, recebimento, protocolo, detalhamento, cliente_uid, anexo_url)
                VALUES (?,?,?,?,?,?, 'Triagem', ?,?, CURDATE(), ?,?,?,?)");
            $ins->execute([$grp, $grp, $empresa, ($seguradora ?: null), $ramo, $tipoDem, $benExib,
                           $tipoDem.' — '.$benExib, $proto, $obs, $uid, $anexoUrl]);
            json(['ok'=>true, 'id'=>db()->lastInsertId(), 'protocolo'=>$proto]);
        } catch (PDOException $e) { json(['ok'=>false,'msg'=>'Erro ao criar: '.$e->getMessage()]); }
        break;

    // ═══════════ TRIAGEM (equipe interna distribui as demandas do cliente) ═══════════
    case 'triagem_listar':
        if (!canViewAll()) json(['ok'=>false,'msg'=>'Acesso negado']);
        try {
            try { db()->exec("ALTER TABLE demandas ADD COLUMN anexo_url VARCHAR(255) NULL"); } catch (Throwable $e) {}
            $rows = db()->query("SELECT id, origem, grupo, empresa, seguradora, ramo, tipo_demanda, situacao,
                                        dados_beneficiario, detalhamento, protocolo, recebimento, anexo_url, criado_em
                                 FROM demandas WHERE situacao = 'Triagem' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            json(['ok'=>true, 'data'=>$rows]);
        } catch (PDOException $e) { json(['ok'=>false,'msg'=>'Erro: '.$e->getMessage()]); }
        break;

    case 'triagem_atribuir':
        if (!canViewAll()) json(['ok'=>false,'msg'=>'Acesso negado']);
        $d = jsonBody();
        $id  = intval($d['id'] ?? 0);
        $aid = intval($d['analista_id'] ?? 0) ?: null;
        $an  = trim($d['analista_nome'] ?? '');
        if (!$id || $an === '') json(['ok'=>false,'msg'=>'Informe a demanda e o analista.']);
        try {
            db()->prepare("UPDATE demandas SET analista_id=?, analista_nome=?, situacao='Em análise',
                           inicio_tratativa=COALESCE(inicio_tratativa, CURDATE()) WHERE id=?")
                ->execute([$aid, $an, $id]);
            json(['ok'=>true]);
        } catch (PDOException $e) { json(['ok'=>false,'msg'=>'Erro ao atribuir: '.$e->getMessage()]); }
        break;

    case 'criar_lote':
        // ── DIAGNÓSTICO: grava log detalhado em /tmp ou no diretório do app
        $__loteLog = __DIR__ . '/lote_debug.log';
        $__loteLogFn = function($msg) use ($__loteLog) {
            @file_put_contents($__loteLog, '['.date('Y-m-d H:i:s').'] '.$msg."\n", FILE_APPEND);
        };
        $__rawBody = file_get_contents('php://input');
        $__loteLogFn('=== INÍCIO criar_lote — content_length='.($_SERVER['CONTENT_LENGTH'] ?? '?').' bytes_lidos='.strlen($__rawBody));

        $d       = jsonBody();
        $comuns  = $d['comuns'] ?? [];
        $linhas  = $d['linhas'] ?? [];

        $__loteLogFn('Linhas recebidas: '.count($linhas));
        if (count($linhas) > 0) {
            $__loteLogFn('Amostra linha 1: '.json_encode($linhas[0], JSON_UNESCAPED_UNICODE));
        }

        // Validação dos campos comuns
        $grupo        = trim($comuns['grupo'] ?? '');
        $empresa      = trim($comuns['empresa'] ?? '');
        $seguradora   = trim($comuns['seguradora'] ?? '');
        $ramo         = trim($comuns['ramo'] ?? '');
        $analista_id  = intval($comuns['analista_id'] ?? 0);
        $analista_nome= trim($comuns['analista_nome'] ?? '');
        $situacao     = trim($comuns['situacao'] ?? 'Em Análise');
        $recebimento  = $comuns['recebimento'] ?: null;

        // O analista agora vem de cada linha (coluna Analista da planilha
        // ou da própria tabela). Se a linha não tiver, cai no comum (que pode
        // estar vazio — backend aceita NULL e mostra "—" no card).
        if (!is_array($linhas) || !count($linhas)) {
            json(['ok' => false, 'msg' => 'Nenhuma linha para inserir.']);
        }
        if (count($linhas) > 50) {
            json(['ok' => false, 'msg' => 'Máximo de 50 linhas por lote.']);
        }

        $db = db();
        $stmt = $db->prepare("
            INSERT INTO demandas
                (grupo, empresa, seguradora, ramo, tipo_demanda, situacao,
                 dados_beneficiario, descricao_demanda, assunto_email, analista_id,
                 analista_nome, recebimento, inicio_tratativa, proxima_acao,
                 data_conclusao, protocolo, detalhamento)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        try {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            $db->beginTransaction();
            $n = 0;
            $puladas = 0;
            foreach ($linhas as $idx => $l) {
                $tipo = trim($l['tipo_demanda'] ?? '');
                if (!$tipo) {
                    $puladas++;
                    $__loteLogFn("  → PULADA linha #$idx: tipo_demanda vazio. Conteúdo: ".json_encode($l, JSON_UNESCAPED_UNICODE));
                    continue;
                }
                // Cada linha pode sobrescrever os campos comuns (grupo, empresa,
                // seguradora, ramo, situação, recebimento). Quando a linha não
                // informa, cai no valor comum.
                $grupoLinha   = trim($l['grupo']            ?? '') ?: $grupo;
                $empresaLinha = trim($l['empresa']          ?? '') ?: $empresa;
                $segLinha     = trim($l['seguradora']       ?? '') ?: $seguradora;
                $ramoLinha    = trim($l['ramo']             ?? '') ?: $ramo;
                $sitLinha     = trim($l['situacao']         ?? '') ?: $situacao;
                $recebLinha   = ($l['recebimento']          ?? null) ?: $recebimento;
                $iniLinha     = ($l['inicio_tratativa']     ?? null) ?: null;
                $assuntoLinha = trim($l['assunto_email']    ?? '');
                $detLinha     = trim($l['detalhamento']     ?? '');
                // Analista por linha tem prioridade sobre o comum
                $analistaIdLinha   = !empty($l['analista_id_linha']) ? intval($l['analista_id_linha']) : $analista_id;
                $analistaNomeLinha = trim($l['analista_nome_linha'] ?? '') ?: $analista_nome;
                // Rede de segurança: se chegou só o nome (id null), tenta resolver pelo nome
                // (case+acento-insensível). Salva sempre o nome canônico do banco.
                if (!$analistaIdLinha && $analistaNomeLinha) {
                    static $_analCache = null;
                    if ($_analCache === null) {
                        $_analCache = [];
                        try {
                            $stA = db()->query("SELECT id, nome FROM analistas");
                            while ($a = $stA->fetch(PDO::FETCH_ASSOC)) {
                                // Remove acentos sem precisar da extensão intl
                                $key = strtolower(strtr($a['nome'],
                                    'ÀÁÂÃÄÅàáâãäåÒÓÔÕÖØòóôõöøÈÉÊËèéêëÌÍÎÏìíîïÙÚÛÜùúûüÑñÇç',
                                    'AAAAAAaaaaaaOOOOOOooooooEEEEeeeeIIIIiiiiUUUUuuuuNnCc'));
                                $_analCache[$key] = $a;
                            }
                        } catch (Throwable $e) {}
                    }
                    $alvo = strtolower(strtr($analistaNomeLinha,
                        'ÀÁÂÃÄÅàáâãäåÒÓÔÕÖØòóôõöøÈÉÊËèéêëÌÍÎÏìíîïÙÚÛÜùúûüÑñÇç',
                        'AAAAAAaaaaaaOOOOOOooooooEEEEeeeeIIIIiiiiUUUUuuuuNnCc'));
                    if (isset($_analCache[$alvo])) {
                        $analistaIdLinha   = (int)$_analCache[$alvo]['id'];
                        $analistaNomeLinha = $_analCache[$alvo]['nome'];
                    }
                }
                $stmt->execute([
                    $grupoLinha,
                    $empresaLinha,
                    $segLinha,
                    $ramoLinha,
                    $tipo,
                    $sitLinha,
                    trim($l['dados_beneficiario'] ?? ''),
                    trim($l['descricao_demanda']  ?? ''),
                    $assuntoLinha,
                    $analistaIdLinha ?: null,
                    $analistaNomeLinha,
                    $recebLinha,
                    $iniLinha,
                    $l['proxima_acao']   ?: null,
                    $l['data_conclusao'] ?: null,
                    trim($l['protocolo'] ?? ''),
                    $detLinha,
                ]);
                $n++;
            }
            $db->commit();
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            // Confirma quantas linhas a tabela tem agora — sanidade contra rollbacks fantasmas
            $totalAgora = (int) db()->query("SELECT COUNT(*) FROM demandas")->fetchColumn();
            $__loteLogFn("=== FIM criar_lote — inseridas=$n puladas=$puladas total_tabela_pos_commit=$totalAgora");
            json(['ok' => true, 'inseridos' => $n, 'puladas' => $puladas, 'total_atual' => $totalAgora]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            try { $db->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Throwable $e2) {}
            $__loteLogFn('!! EXCEÇÃO: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
            json(['ok' => false, 'msg' => 'Erro ao inserir lote: '.$e->getMessage()]);
        }
        break;

    case 'editar':
        $d  = jsonBody();
        $id = intval($d['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);

        // Regra: origem vazia sempre vira 'INTERWEG'
        $origemDem = trim($d['origem'] ?? '');
        if ($origemDem === '') $origemDem = 'INTERWEG';

        // Aceita 'observacoes' como alias de 'detalhamento' (compat com JS antigo)
        if (empty($d['detalhamento']) && !empty($d['observacoes'])) {
            $d['detalhamento'] = $d['observacoes'];
        }

        // SEGURANÇA: se a edição vier SEM analista (campo em branco no modal),
        // preserva o analista atual — evita a demanda "sumir" da carteira.
        $analistaIdIn   = intval($d['analista_id'] ?? 0) ?: null;
        $analistaNomeIn = trim($d['analista_nome'] ?? '');
        if ($analistaIdIn === null && $analistaNomeIn === '') {
            try {
                $curA = db()->prepare("SELECT analista_id, analista_nome FROM demandas WHERE id = ?");
                $curA->execute([$id]);
                if ($rowA = $curA->fetch()) {
                    $analistaIdIn   = ($rowA['analista_id'] !== null && $rowA['analista_id'] !== '') ? intval($rowA['analista_id']) : null;
                    $analistaNomeIn = (string)($rowA['analista_nome'] ?? '');
                }
            } catch (Throwable $e) {}
        }

        $stmt = db()->prepare("
            UPDATE demandas SET
                origem = ?, grupo = ?, empresa = ?, seguradora = ?, ramo = ?,
                tipo_demanda = ?, situacao = ?, dados_beneficiario = ?,
                descricao_demanda = ?, assunto_email = ?, analista_id = ?,
                analista_nome = ?, recebimento = ?, inicio_tratativa = ?,
                proxima_acao = ?, data_conclusao = ?, protocolo = ?, detalhamento = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $origemDem,
            trim($d['grupo'] ?? ''),
            trim($d['empresa'] ?? ''),
            trim($d['seguradora'] ?? ''),
            trim($d['ramo'] ?? ''),
            trim($d['tipo_demanda'] ?? ''),
            trim($d['situacao'] ?? 'Em análise'),
            trim($d['dados_beneficiario'] ?? ''),
            trim($d['descricao_demanda'] ?? ''),
            trim($d['assunto_email'] ?? ''),
            $analistaIdIn,
            $analistaNomeIn,
            $d['recebimento'] ?: null,
            $d['inicio_tratativa'] ?: null,
            $d['proxima_acao'] ?: null,
            $d['data_conclusao'] ?: null,
            trim($d['protocolo'] ?? ''),
            trim($d['detalhamento'] ?? ''),
            $id,
        ]);

        json(['ok' => true]);
        break;

    case 'excluir':
        // Admin pode excluir qualquer demanda. Analista pode excluir só
        // demandas da própria carteira (analista_nome = sessão).
        if (isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $id = intval($_GET['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);

        if (isAdmin()) {
            db()->prepare("DELETE FROM demandas WHERE id = ?")->execute([$id]);
        } else {
            $stmt = db()->prepare("DELETE FROM demandas WHERE id = ? AND analista_nome = ?");
            $stmt->execute([$id, $_SESSION['user_nome'] ?? '']);
            if ($stmt->rowCount() === 0) {
                json(['ok' => false, 'msg' => 'Você só pode excluir demandas da sua carteira']);
            }
        }
        json(['ok' => true]);
        break;

    case 'concluir_demandas_lote':
        // Conclui várias demandas de uma vez: situação = Concluído + data escolhida.
        // Admin conclui qualquer uma; demais perfis só as da própria carteira.
        if (isFinanceiro() || isVisualizador()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d        = jsonBody();
        $ids      = $d['ids'] ?? [];
        $dataConc = $d['data_conclusao'] ?? null;
        if (!is_array($ids) || !count($ids)) json(['ok' => false, 'msg' => 'Nenhuma demanda selecionada']);
        $ids = array_values(array_filter(array_map('intval', $ids), function($v){ return $v > 0; }));
        if (!count($ids)) json(['ok' => false, 'msg' => 'IDs inválidos']);
        if ($dataConc === '') $dataConc = null;
        if ($dataConc !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataConc)) {
            json(['ok' => false, 'msg' => 'Data de conclusão inválida']);
        }
        $place = implode(',', array_fill(0, count($ids), '?'));
        try {
            if (isAdmin() || canViewAll()) {
                $sql = "UPDATE demandas SET situacao = 'Concluído', data_conclusao = ? WHERE id IN ($place)";
                $params = array_merge([$dataConc], $ids);
            } else {
                $sql = "UPDATE demandas SET situacao = 'Concluído', data_conclusao = ? WHERE analista_nome = ? AND id IN ($place)";
                $params = array_merge([$dataConc, $_SESSION['user_nome'] ?? ''], $ids);
            }
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            json(['ok' => true, 'afetadas' => $stmt->rowCount()]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao concluir em lote: ' . $e->getMessage()]);
        }
        break;

    case 'stats':
        $analista = trim($_GET['analista'] ?? '');
        // Analista comum: travado na própria carteira (ignora o parâmetro).
        if (!canViewAll()) { $analista = trim($_SESSION['user_nome'] ?? ''); }
        $join   = "demandas d LEFT JOIN analistas a ON d.analista_id = a.id";
        $where  = "";
        $params = [];
        if ($analista) {
            // Filtro estrito: conta SOMENTE as demandas no próprio nome (sem carteira do time).
            $where  = " WHERE COALESCE(a.nome, d.analista_nome) = ?";
            $params = [$analista];
        }

        $stTotal = db()->prepare("SELECT COUNT(*) FROM $join$where");
        $stTotal->execute($params);
        $total = $stTotal->fetchColumn();

        $stAnalise = db()->prepare("SELECT COUNT(*) FROM $join" . ($where ? $where . " AND" : " WHERE") . " d.situacao = 'Em Análise'");
        $stAnalise->execute($params);
        $analise = $stAnalise->fetchColumn();

        $stConc = db()->prepare("SELECT COUNT(*) FROM $join" . ($where ? $where . " AND" : " WHERE") . " d.situacao = 'Concluído'");
        $stConc->execute($params);
        $concluido = $stConc->fetchColumn();

        $hoje = date('Y-m-d');
        $paramsAt = array_merge($params, [$hoje]);
        $stAt = db()->prepare("SELECT COUNT(*) FROM $join" . ($where ? $where . " AND" : " WHERE") . " d.proxima_acao < ? AND d.situacao != 'Concluído'");
        $stAt->execute($paramsAt);
        $atrasadas = $stAt->fetchColumn();

        json(['ok' => true, 'data' => [
            'total'     => (int) $total,
            'analise'   => (int) $analise,
            'concluido' => (int) $concluido,
            'atrasadas' => (int) $atrasadas,
        ]]);
        break;

    // ═══════════════════════════════════════════════════
    //  COTAÇÕES — link público para o cliente preencher
    // ═══════════════════════════════════════════════════

    case 'gerar_link_cotacao':
        $d = jsonBody();
        $tipo     = trim($d['tipo']     ?? '');
        $tipoNome = trim($d['tipo_nome'] ?? '');
        $template = $d['template']      ?? null;
        if (!$tipo || !is_array($template)) {
            json(['ok'=>false,'msg'=>'Dados incompletos pra gerar o link.']);
        }
        // Limpa valores do template (cliente preenche do zero)
        foreach (['dados','contrato','coberturas'] as $sec) {
            if (!empty($template[$sec]) && is_array($template[$sec])) {
                foreach ($template[$sec] as &$f) { unset($f['valor']); }
                unset($f);
            }
        }
        $template['vidas_rows'] = []; // cliente começa sem linhas

        $token = bin2hex(random_bytes(12)); // 24 chars
        $criadoPor = $_SESSION['user_nome'] ?? 'Sistema';
        $stmt = db()->prepare("INSERT INTO cotacoes_envio (token, tipo, tipo_nome, template_json, criado_por) VALUES (?,?,?,?,?)");
        $stmt->execute([$token, $tipo, $tipoNome, json_encode($template, JSON_UNESCAPED_UNICODE), $criadoPor]);

        // URL absoluta
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'] ?? '';
        $url   = $proto.'://'.$host.'/cotacao_cliente.php?t='.$token;
        json(['ok'=>true,'token'=>$token,'url'=>$url]);
        break;

    case 'salvar_resposta_cotacao':
        // Endpoint público (cliente acessa sem login)
        $d = jsonBody();
        $token = trim($d['token'] ?? '');
        $resp  = $d['resposta'] ?? null;
        if (!$token || !is_array($resp)) {
            json(['ok'=>false,'msg'=>'Dados incompletos.']);
        }
        $stCh = db()->prepare("SELECT id, status FROM cotacoes_envio WHERE token = ?");
        $stCh->execute([$token]);
        $row = $stCh->fetch(PDO::FETCH_ASSOC);
        if (!$row) json(['ok'=>false,'msg'=>'Cotação não encontrada.']);
        if ($row['status'] === 'respondida') json(['ok'=>false,'msg'=>'Esta cotação já foi respondida.']);

        $cliNome  = trim($d['cliente_nome']  ?? '');
        $cliEmail = trim($d['cliente_email'] ?? '');
        $stmt = db()->prepare("UPDATE cotacoes_envio
            SET resposta_json=?, cliente_nome=?, cliente_email=?, respondido_em=NOW(), status='respondida'
            WHERE token=?");
        $stmt->execute([json_encode($resp, JSON_UNESCAPED_UNICODE), $cliNome, $cliEmail, $token]);
        json(['ok'=>true]);
        break;

    case 'listar_cotacoes_enviadas':
        $rows = db()->query("SELECT id, token, tipo_nome, criado_por, cliente_nome, cliente_email,
                                    criado_em, respondido_em, status
                             FROM cotacoes_envio
                             ORDER BY criado_em DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
        json(['ok'=>true,'data'=>$rows]);
        break;

    case 'consultar_cnpj':
        // Consulta dados do CNPJ na Receita (BrasilAPI, fallback ReceitaWS).
        if (isVisualizador()) json(['ok'=>false,'msg'=>'Acesso negado']);
        $cnpjC = preg_replace('/\D/', '', (string)($_GET['cnpj'] ?? ''));
        if (strlen($cnpjC) !== 14) json(['ok'=>false,'msg'=>'CNPJ inválido (precisa de 14 dígitos).']);
        $tryCnpj = function($url){
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8,
                CURLOPT_FOLLOWLOCATION=>true, CURLOPT_HTTPHEADER=>['Accept: application/json']]);
            $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            return ($code === 200 && $resp) ? json_decode($resp, true) : null;
        };
        $j = $tryCnpj("https://brasilapi.com.br/api/cnpj/v1/$cnpjC");
        if (is_array($j) && !empty($j['razao_social'])) {
            json(['ok'=>true, 'data'=>[
                'razao_social'  => trim($j['razao_social']),
                'nome_fantasia' => trim($j['nome_fantasia'] ?? ''),
                'uf'            => strtoupper(trim($j['uf'] ?? '')),
                'municipio'     => trim($j['municipio'] ?? ''),
                'situacao'      => trim($j['descricao_situacao_cadastral'] ?? ''),
                'fonte'         => 'BrasilAPI',
            ]]);
        }
        $j = $tryCnpj("https://receitaws.com.br/v1/cnpj/$cnpjC");
        if (is_array($j) && (($j['status'] ?? '') !== 'ERROR') && !empty($j['nome'])) {
            json(['ok'=>true, 'data'=>[
                'razao_social'  => trim($j['nome']),
                'nome_fantasia' => trim($j['fantasia'] ?? ''),
                'uf'            => strtoupper(trim($j['uf'] ?? '')),
                'municipio'     => trim($j['municipio'] ?? ''),
                'situacao'      => trim($j['situacao'] ?? ''),
                'fonte'         => 'ReceitaWS',
            ]]);
        }
        json(['ok'=>false, 'msg'=>'CNPJ não encontrado ou serviço indisponível no momento.']);
        break;

    // ═══════════════════════════════════════════════════
    //  ANALISTAS (listagem + CRUD)
    // ═══════════════════════════════════════════════════

    case 'analistas':
        try { db()->exec("ALTER TABLE analistas ADD COLUMN grupo VARCHAR(120) NULL"); } catch (Throwable $e) {}
        $rows = db()->query("SELECT id, nome, login, email, tipo, grupo, papel, tarefas, foto FROM analistas WHERE ativo = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
        json(['ok' => true, 'data' => $rows]);
        break;

    case 'editar_meu_perfil':
        // Salva o próprio perfil. Funciona para usuários da tabela `analistas`
        // (login + senha + tipo) E da tabela `usuarios` (email + senha + tipo).
        $id   = intval($_SESSION['user_id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'Sessão inválida']);
        $d     = jsonBody();
        $nome  = trim($d['nome']  ?? '');
        $login = trim($d['login'] ?? '');
        $senha = $d['senha'] ?? '';
        if (!$nome || !$login) json(['ok' => false, 'msg' => 'Nome e login são obrigatórios']);

        // Detecta de qual tabela o usuário veio
        $stChk = db()->prepare("SELECT 1 FROM analistas WHERE id = ?");
        $stChk->execute([$id]);
        $isAnalista = (bool) $stChk->fetchColumn();

        try {
            if ($isAnalista) {
                if ($senha) {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    db()->prepare("UPDATE analistas SET nome=?, login=?, senha=? WHERE id=?")
                        ->execute([$nome, $login, $hash, $id]);
                } else {
                    db()->prepare("UPDATE analistas SET nome=?, login=? WHERE id=?")
                        ->execute([$nome, $login, $id]);
                }
            } else {
                // tabela `usuarios` usa coluna `email` em vez de `login`
                if ($senha) {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    db()->prepare("UPDATE usuarios SET nome=?, email=?, senha=? WHERE id=?")
                        ->execute([$nome, $login, $hash, $id]);
                } else {
                    db()->prepare("UPDATE usuarios SET nome=?, email=? WHERE id=?")
                        ->execute([$nome, $login, $id]);
                }
            }
            $_SESSION['user_nome'] = $nome;
            json(['ok' => true]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao salvar: ' . $e->getMessage()]);
        }
        break;

    case 'meu_perfil':
        $id   = intval($_SESSION['user_id'] ?? 0);
        $nomeSession = $_SESSION['user_nome'] ?? '';
        $tipoSession = $_SESSION['user_tipo'] ?? '';

        // 1) Tenta na tabela analistas (login + tipo)
        $stmt = db()->prepare("SELECT id, nome, login, tipo, 'analistas' AS origem_tabela FROM analistas WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2) Fallback: tabela usuarios (admin legado, visualizador, financeiro, etc.)
        if (!$row) {
            $stmt2 = db()->prepare("SELECT id, nome, email AS login, tipo, 'usuarios' AS origem_tabela FROM usuarios WHERE id = ?");
            $stmt2->execute([$id]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        }

        // 3) Se ainda assim não achou, devolve dados da sessão pra não quebrar a tela
        if (!$row) {
            $row = ['id' => $id, 'nome' => $nomeSession, 'login' => '', 'tipo' => $tipoSession, 'origem_tabela' => null];
        }

        json(['ok' => true, 'data' => $row]);
        break;

    case 'criar_analista':
        equipeAdmin();
        $d     = jsonBody();
        $nome  = trim($d['nome']  ?? '');
        $login = trim($d['login'] ?? '');
        $email = trim($d['email'] ?? '');
        $senha = $d['senha'] ?? '';
        $tiposValidos = ['admin','analista','coordenador','enfermeira','enfermagem','financeiro','head','gerente','novos_negocios','comercial_cotacao','visualizador','cliente','renovacao'];
        $tipo = in_array($d['tipo'] ?? '', $tiposValidos, true) ? $d['tipo'] : 'analista';
        $grupoCliente = ($tipo === 'cliente') ? (trim($d['grupo'] ?? '') ?: null) : null;
        try { db()->exec("ALTER TABLE analistas MODIFY COLUMN tipo VARCHAR(30) NOT NULL DEFAULT 'analista'"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE analistas ADD COLUMN grupo VARCHAR(120) NULL"); } catch (Throwable $e) {}
        if ($tipo === 'cliente' && !$grupoCliente) {
            json(['ok' => false, 'msg' => 'Para tipo Cliente, informe o Grupo (ex.: MERCANTIL).']);
        }

        if (!$nome || !$login || !$senha) {
            json(['ok' => false, 'msg' => 'Nome, login e senha são obrigatórios']);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json(['ok' => false, 'msg' => 'E-mail inválido']);
        }

        // Verificar se login já existe
        $check = db()->prepare("SELECT id FROM analistas WHERE login = ?");
        $check->execute([$login]);
        if ($check->fetch()) {
            json(['ok' => false, 'msg' => 'Este login já está em uso']);
        }

        $erroSenha = null;
        if (!senha_forte($senha, $erroSenha)) {
            json(['ok' => false, 'msg' => $erroSenha]);
        }
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = db()->prepare("INSERT INTO analistas (nome, login, email, senha, tipo, grupo, ativo, senha_alterada_em) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$nome, $login, ($email ?: null), $hash, $tipo, $grupoCliente]);

        json(['ok' => true, 'id' => db()->lastInsertId()]);
        break;

    case 'editar_analista':
        $d   = jsonBody();
        $id  = intval($d['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);

        // Admin e enfermagem editam qualquer um; demais só a si mesmos.
        if (!isAdmin() && !isEnfermeira() && ($_SESSION['user_id'] ?? 0) != $id) {
            json(['ok' => false, 'msg' => 'Acesso negado']);
        }

        $nome  = trim($d['nome']  ?? '');
        $login = trim($d['login'] ?? '');
        $email = trim($d['email'] ?? '');
        if (!$nome || !$login) json(['ok' => false, 'msg' => 'Nome e login são obrigatórios']);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json(['ok' => false, 'msg' => 'E-mail inválido']);
        }
        $emailVal = ($email !== '' ? $email : null);

        // Verifica duplicidade de login em outro registro
        $check = db()->prepare("SELECT id FROM analistas WHERE login = ? AND id != ?");
        $check->execute([$login, $id]);
        if ($check->fetch()) json(['ok' => false, 'msg' => 'Este login já está em uso']);

        // Pega o nome ANTIGO antes do UPDATE — usado pra propagar mudança
        // pra tabelas que guardam snapshot do nome (faturamento, demandas, etc).
        $stOld = db()->prepare("SELECT nome FROM analistas WHERE id = ?");
        $stOld->execute([$id]);
        $nomeAntigo = $stOld->fetchColumn();

        $tiposValidos = ['admin','analista','coordenador','enfermeira','enfermagem','financeiro','head','gerente','novos_negocios','comercial_cotacao','visualizador','cliente','renovacao'];
        try { db()->exec("ALTER TABLE analistas MODIFY COLUMN tipo VARCHAR(30) NOT NULL DEFAULT 'analista'"); } catch (Throwable $e) {}
        $tipo   = isAdmin() ? (in_array($d['tipo'] ?? '', $tiposValidos, true) ? $d['tipo'] : 'analista') : null;
        $papel  = trim($d['papel']   ?? '');
        $tarefas = trim($d['tarefas'] ?? '');
        try { db()->exec("ALTER TABLE analistas ADD COLUMN grupo VARCHAR(120) NULL"); } catch (Throwable $e) {}
        $grupoVal = ($tipo === 'cliente') ? (trim($d['grupo'] ?? '') ?: null) : null;
        if ($tipo === 'cliente' && !$grupoVal) json(['ok' => false, 'msg' => 'Para tipo Cliente, informe o Grupo (ex.: MERCANTIL).']);

        if (!empty($d['senha'])) {
            $erroSenha = null;
            if (!senha_forte($d['senha'], $erroSenha)) {
                json(['ok' => false, 'msg' => $erroSenha]);
            }
            $hash = password_hash($d['senha'], PASSWORD_DEFAULT);
            if ($tipo !== null) {
                db()->prepare("UPDATE analistas SET nome=?, login=?, email=?, senha=?, tipo=?, grupo=?, papel=?, tarefas=?, senha_alterada_em=NOW() WHERE id=?")->execute([$nome, $login, $emailVal, $hash, $tipo, $grupoVal, $papel, $tarefas, $id]);
            } else {
                db()->prepare("UPDATE analistas SET nome=?, login=?, email=?, senha=?, papel=?, tarefas=?, senha_alterada_em=NOW() WHERE id=?")->execute([$nome, $login, $emailVal, $hash, $papel, $tarefas, $id]);
            }
        } else {
            if ($tipo !== null) {
                db()->prepare("UPDATE analistas SET nome=?, login=?, email=?, tipo=?, grupo=?, papel=?, tarefas=? WHERE id=?")->execute([$nome, $login, $emailVal, $tipo, $grupoVal, $papel, $tarefas, $id]);
            } else {
                db()->prepare("UPDATE analistas SET nome=?, login=?, email=?, papel=?, tarefas=? WHERE id=?")->execute([$nome, $login, $emailVal, $papel, $tarefas, $id]);
            }
        }

        // ── PROPAGAÇÃO DO RENAME ─────────────────────────────────────
        // Atualiza o snapshot do nome em todas as tabelas que guardam analista_nome.
        // Estratégia: UPDATE por analista_id (seguro) + fallback por nome antigo
        // (registros legados que não têm analista_id preenchido).
        // IMPORTANTE: empresas precisa estar nessa lista porque Renovação puxa
        // o analista da empresa via JOIN (não tem analista próprio).
        $propagadas = ['empresas'=>0,'faturamento'=>0,'demandas'=>0,'demandas_assistenciais'=>0,'acoes_saude'=>0,'atas_visitas'=>0];
        if ($nomeAntigo !== false && $nomeAntigo !== $nome) {
            $tabelas = ['empresas','faturamento','demandas','demandas_assistenciais','acoes_saude','atas_visitas'];
            foreach ($tabelas as $t) {
                try {
                    // 1) por analista_id (preferencial, mais seguro)
                    $st1 = db()->prepare("UPDATE $t SET analista_nome = ? WHERE analista_id = ?");
                    $st1->execute([$nome, $id]);
                    $propagadas[$t] = $st1->rowCount();

                    // 2) por nome antigo, só quando analista_id é NULL
                    //    (registros legados sem vínculo direto)
                    $st2 = db()->prepare("UPDATE $t SET analista_nome = ?, analista_id = ?
                                          WHERE analista_id IS NULL AND analista_nome = ?");
                    $st2->execute([$nome, $id, $nomeAntigo]);
                    $propagadas[$t] += $st2->rowCount();
                } catch (PDOException $e) {
                    // Tabela pode não existir em ambiente antigo — ignora silenciosamente
                }
            }
        }

        // Atualiza sessão se o próprio usuário editou seus dados
        if (($_SESSION['user_id'] ?? 0) == $id) {
            $_SESSION['user_nome'] = $nome;
        }

        json(['ok' => true, 'propagadas' => $propagadas]);
        break;

    case 'excluir_analista':
        equipeAdmin();
        $id = intval($_GET['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);

        db()->prepare("UPDATE analistas SET ativo = 0 WHERE id = ?")->execute([$id]);
        json(['ok' => true]);
        break;

    case 'upload_foto_analista':
        equipeAdmin();
        $id = intval($_POST['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);

        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            json(['ok' => false, 'msg' => 'Nenhuma foto enviada']);
        }

        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $permitidos = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $permitidos)) {
            json(['ok' => false, 'msg' => 'Formato inválido. Use JPG, PNG, WEBP ou GIF']);
        }

        // Cria pasta de uploads se não existir
        $uploadDir = __DIR__ . '/uploads/fotos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Remove foto anterior se existir
        $stmt = db()->prepare("SELECT foto FROM analistas WHERE id = ?");
        $stmt->execute([$id]);
        $fotoAnterior = $stmt->fetchColumn();
        if ($fotoAnterior && file_exists(__DIR__ . '/' . $fotoAnterior)) {
            unlink(__DIR__ . '/' . $fotoAnterior);
        }

        // Salva nova foto
        $nomeArquivo = 'analista_' . $id . '_' . time() . '.' . $ext;
        $caminhoRelativo = 'uploads/fotos/' . $nomeArquivo;
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $nomeArquivo)) {
            json(['ok' => false, 'msg' => 'Erro ao salvar arquivo']);
        }

        // Atualiza banco
        db()->prepare("UPDATE analistas SET foto = ? WHERE id = ?")->execute([$caminhoRelativo, $id]);
        json(['ok' => true, 'foto' => $caminhoRelativo]);
        break;

    case 'excluir_foto_analista':
        equipeAdmin();
        $d  = jsonBody();
        $id = intval($d['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);

        // Remove arquivo do disco
        $stmt = db()->prepare("SELECT foto FROM analistas WHERE id = ?");
        $stmt->execute([$id]);
        $foto = $stmt->fetchColumn();
        if ($foto && file_exists(__DIR__ . '/' . $foto)) {
            unlink(__DIR__ . '/' . $foto);
        }

        // Limpa no banco
        db()->prepare("UPDATE analistas SET foto = NULL WHERE id = ?")->execute([$id]);
        json(['ok' => true]);
        break;

    // ═══════════════════════════════════════════════════
    //  SEGURADORAS
    // ═══════════════════════════════════════════════════

    case 'seguradoras':
        try {
            $rows = db()->query("SELECT id, nome FROM seguradoras WHERE ativo = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $rows = [];
        }
        json(['ok' => true, 'data' => $rows]);
        break;

    case 'criar_seguradora':
        $d    = jsonBody();
        $nome = trim($d['nome'] ?? '');
        if (!$nome) json(['ok' => false, 'msg' => 'Nome da seguradora é obrigatório']);

        $stmt = db()->prepare("INSERT IGNORE INTO seguradoras (nome) VALUES (?)");
        $stmt->execute([$nome]);

        if ($stmt->rowCount() === 0) {
            json(['ok' => false, 'msg' => 'Esta seguradora já existe']);
        }

        json(['ok' => true, 'id' => db()->lastInsertId()]);
        break;

    case 'excluir_seguradora':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);

        db()->prepare("UPDATE seguradoras SET ativo = 0 WHERE id = ?")->execute([$id]);
        json(['ok' => true]);
        break;

    // ═══════════════════════════════════════════════════
    //  EMPRESAS / GRUPOS
    // ═══════════════════════════════════════════════════

    case 'empresas':
        try { db()->exec("ALTER TABLE empresas ADD COLUMN etapa_cadastro VARCHAR(20) NOT NULL DEFAULT 'cliente'"); } catch (\Throwable $e0) {}
        // Filtro OPCIONAL: só clientes concluídos. Usado pelos módulos de enfermagem
        // (somente_clientes=1). SEM o parâmetro, retorna TODAS as empresas ativas
        // (comportamento antigo) — pra não sumir opção em Demandas, Cadastros, etc.
        $whereEtapa = !empty($_GET['somente_clientes']) ? " AND etapa_cadastro = 'cliente'" : "";
        try {
            $rows = db()->query("SELECT id, origem, ramo, seguradora, numero_contrato, cnpj, tipo_estipulacao,
                                        grupo, nome, porte, analista_id, analista_nome,
                                        data_emissao, data_vencimento,
                                        inicio_vigencia, mes_renovacao, numero_vidas,
                                        percentual_comissao,
                                        emails_faturamento, resp_nome, resp_email, resp_telefone, observacao,
                                        origem_cadastro, planos, valor_fatura, comercial_interweg,
                                        corretora, modalidade_contratacao, tipo_passagem,
                                        receita_comissao_anual, margem_para_acao
                                 FROM empresas
                                 WHERE ativo = 1$whereEtapa
                                 ORDER BY grupo, nome, seguradora")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback 1: se as colunas novas de passagem (tipo_passagem etc.) não existem,
            // tenta o SELECT antigo sem elas (continua trazendo a maioria dos campos).
            try {
                $rows = db()->query("SELECT id, origem, ramo, seguradora, numero_contrato, cnpj, tipo_estipulacao,
                                            grupo, nome, porte, analista_id, analista_nome,
                                            data_emissao, data_vencimento,
                                            inicio_vigencia, mes_renovacao, numero_vidas,
                                            percentual_comissao,
                                            emails_faturamento, resp_nome, resp_email, resp_telefone, observacao,
                                            origem_cadastro
                                     FROM empresas
                                     WHERE ativo = 1$whereEtapa
                                     ORDER BY grupo, nome, seguradora")->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e2) {
                // Fallback 2: mínimo absoluto pra não quebrar a tela
                try {
                    $rows = db()->query("SELECT id, grupo, nome, cnpj, tipo_estipulacao, observacao FROM empresas WHERE ativo = 1 ORDER BY grupo, nome")->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e3) { $rows = []; }
            }
        }
        json(['ok' => true, 'data' => $rows]);
        break;

    case 'enviar_faturamento_empresa':
        // Envia (ou reenvia) uma empresa existente para o Faturamento da competência
        // atual — cria a linha se ainda não existir. Retorna motivo claro em caso de erro.
        if (isVisualizador()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $b   = jsonBody();
        $eid = intval($b['empresa_id'] ?? $_GET['empresa_id'] ?? 0);
        if (!$eid) json(['ok' => false, 'msg' => 'ID da empresa inválido']);

        $stE = db()->prepare("SELECT * FROM empresas WHERE id = ? AND ativo = 1");
        $stE->execute([$eid]);
        $e = $stE->fetch(PDO::FETCH_ASSOC);
        if (!$e) json(['ok' => false, 'msg' => 'Empresa não encontrada ou inativa']);

        $mesesFat = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        $compAtual = $mesesFat[(int)date('n')-1] . '/' . substr(date('Y'), -2);

        // Dedup NULL-safe (<=>) — evita duplicar na mesma competência
        $stChk = db()->prepare("SELECT id FROM faturamento
            WHERE cnpj <=> ? AND numero_contrato <=> ? AND empresa = ?
              AND competencia = ? AND seguradora <=> ? LIMIT 1");
        $stChk->execute([$e['cnpj'], $e['numero_contrato'], $e['nome'], $compAtual, $e['seguradora']]);
        if ($stChk->fetchColumn()) {
            json(['ok' => true, 'ja_existia' => true, 'competencia' => $compAtual,
                  'msg' => 'Essa empresa já está no Faturamento de ' . $compAtual . '.']);
        }
        try {
            $ins = db()->prepare("INSERT INTO faturamento
                (origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa,
                 analista_id, analista_nome, competencia, data_emissao, data_vencimento,
                 percentual_comissao, emails_envio, status_envio)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Pendente emissão')");
            $ins->execute([
                $e['origem'], $e['ramo'], $e['seguradora'], $e['numero_contrato'], $e['cnpj'],
                $e['grupo'], $e['nome'], $e['analista_id'], $e['analista_nome'], $compAtual,
                $e['data_emissao'], $e['data_vencimento'], $e['percentual_comissao'], $e['emails_faturamento']
            ]);
            json(['ok' => true, 'criado' => true, 'competencia' => $compAtual,
                  'msg' => 'Empresa enviada para o Faturamento de ' . $compAtual . '.']);
        } catch (PDOException $ex) {
            error_log('enviar_faturamento_empresa falhou (empresa ' . $eid . '): ' . $ex->getMessage());
            json(['ok' => false, 'msg' => 'Não foi possível inserir no faturamento: ' . $ex->getMessage()]);
        }
        break;

    case 'criar_empresa':
    case 'editar_empresa':
        if (isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado: financeiro não pode cadastrar/alterar empresas']);
        $isEdit = ($action === 'editar_empresa');
        $d      = jsonBody();
        $id     = intval($d['id'] ?? 0);
        if ($isEdit && !$id) json(['ok' => false, 'msg' => 'ID inválido']);

        // Campos principais
        $grupo     = trim($d['grupo'] ?? '');
        $nome      = trim($d['nome']  ?? '');
        $cnpj      = trim($d['cnpj']  ?? '');
        $tipoEst   = trim($d['tipo_estipulacao'] ?? '');
        $obs       = trim($d['observacao'] ?? '');

        // Campos da base mestre (opcionais — podem ser preenchidos depois)
        $origem       = trim($d['origem']      ?? '');
        $ramo         = trim($d['ramo']        ?? '');
        $seguradora   = trim($d['seguradora']  ?? '');
        $numContrato  = trim($d['numero_contrato'] ?? '');
        $porte        = trim($d['porte']       ?? '');
        $analistaId   = intval($d['analista_id'] ?? 0) ?: null;
        $analistaNm   = trim($d['analista_nome'] ?? '');
        $dataEmi      = $d['data_emissao']    ?: null;
        $dataVenc     = $d['data_vencimento'] ?: null;
        $iniVig       = $d['inicio_vigencia'] ?: null;
        $mesRen       = $d['mes_renovacao']   ?: null;
        $vidasEmp     = isset($d['numero_vidas']) && $d['numero_vidas'] !== '' && $d['numero_vidas'] !== null ? intval($d['numero_vidas']) : null;
        $percCom      = isset($d['percentual_comissao']) && $d['percentual_comissao'] !== ''
                        ? floatval(str_replace(',', '.', $d['percentual_comissao'])) : null;
        $emailsFat    = trim($d['emails_faturamento'] ?? '');
        $respNome     = trim($d['resp_nome']     ?? '');
        $respEmail    = trim($d['resp_email']    ?? '');
        $respTelefone = trim($d['resp_telefone'] ?? '');

        if (!$grupo || !$nome) json(['ok' => false, 'msg' => 'Grupo e nome são obrigatórios']);

        // Validações de opcionais com enum
        $cnpjFmt = null;
        if ($cnpj !== '') {
            if (!validarCNPJ($cnpj)) json(['ok' => false, 'msg' => 'CNPJ inválido']);
            $cnpjFmt = formatarCNPJ($cnpj);
        }
        if ($tipoEst !== '' && !in_array($tipoEst, ['Estipulante','Subestipulante'], true)) {
            json(['ok' => false, 'msg' => 'Tipo de estipulação inválido']);
        }
        if ($origem !== '' && !in_array($origem, ['Interweg MG','Interweg JF','Interweg SP','INTERWEG','FINLÂNDIA','INTERWEG | FINLÂNDIA','VALENTE JUNIOR'], true)) {
            json(['ok' => false, 'msg' => 'Origem inválida']);
        }
        if ($ramo !== '' && !in_array($ramo, ['Saúde','Odonto','Saúde/Odonto','Vida','Enfermagem'], true)) {
            json(['ok' => false, 'msg' => 'Ramo inválido']);
        }
        if ($porte !== '' && !in_array($porte, ['Até 30 vidas','Entre 30 e 100 vidas','Entre 100 e 900','Acima de 1000'], true)) {
            json(['ok' => false, 'msg' => 'Porte inválido']);
        }

        // Normaliza valores vazios para NULL
        $tipoEst      = $tipoEst      === '' ? null : $tipoEst;
        $obs          = $obs          === '' ? null : $obs;
        $origem       = $origem       === '' ? null : $origem;
        $ramo         = $ramo         === '' ? null : $ramo;
        $seguradora   = $seguradora   === '' ? null : $seguradora;
        $numContrato  = $numContrato  === '' ? null : $numContrato;
        $porte        = $porte        === '' ? null : $porte;
        $analistaNm   = $analistaNm   === '' ? null : $analistaNm;
        $emailsFat    = $emailsFat    === '' ? null : $emailsFat;
        $respNome     = $respNome     === '' ? null : $respNome;
        $respEmail    = $respEmail    === '' ? null : $respEmail;
        $respTelefone = $respTelefone === '' ? null : $respTelefone;

        try {
            // Desliga FK_CHECKS pra evitar barramento por FK legado em analista_id
            db()->exec("SET FOREIGN_KEY_CHECKS = 0");
            if ($isEdit) {
                // ── Pega o registro ANTIGO antes de atualizar (pra propagar mudanças) ──
                $stOld = db()->prepare("SELECT cnpj, numero_contrato, seguradora, grupo, nome,
                                               origem, ramo, analista_id, analista_nome,
                                               percentual_comissao, emails_faturamento,
                                               resp_nome, resp_email, resp_telefone,
                                               data_emissao, data_vencimento
                                          FROM empresas WHERE id = ?");
                $stOld->execute([$id]);
                $oldEmp = $stOld->fetch(PDO::FETCH_ASSOC);

                $stmt = db()->prepare("
                    UPDATE empresas SET
                        grupo=?, nome=?, cnpj=?, tipo_estipulacao=?, observacao=?,
                        origem=?, ramo=?, seguradora=?, numero_contrato=?, porte=?,
                        analista_id=?, analista_nome=?, data_emissao=?, data_vencimento=?,
                        inicio_vigencia=?, mes_renovacao=?, numero_vidas=?,
                        percentual_comissao=?, emails_faturamento=?,
                        resp_nome=?, resp_email=?, resp_telefone=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $grupo, $nome, $cnpjFmt, $tipoEst, $obs,
                    $origem, $ramo, $seguradora, $numContrato, $porte,
                    $analistaId, $analistaNm, $dataEmi, $dataVenc,
                    $iniVig, $mesRen, $vidasEmp,
                    $percCom, $emailsFat,
                    $respNome, $respEmail, $respTelefone,
                    $id
                ]);

                // ── Propaga mudanças para FATURAMENTO e RENOVAÇÃO ──────────
                // Match nos valores ANTIGOS (cnpj + numero_contrato + seguradora + EMPRESA)
                // — IMPORTANTE: incluir empresa no match porque várias empresas (subfaturas)
                // podem compartilhar o mesmo cnpj + contrato + seguradora (casos como TECHBIZ).
                $fatAfetados = 0;
                $renAfetados = 0;
                if ($oldEmp && $oldEmp['cnpj'] && $oldEmp['numero_contrato'] && $oldEmp['nome']) {
                    try {
                        $stmtFatUpd = db()->prepare("
                            UPDATE faturamento SET
                                origem = ?, ramo = ?, seguradora = ?, numero_contrato = ?, cnpj = ?,
                                grupo = ?, empresa = ?,
                                analista_id = ?, analista_nome = ?,
                                percentual_comissao = COALESCE(?, percentual_comissao),
                                data_emissao    = COALESCE(data_emissao, ?),
                                data_vencimento = COALESCE(data_vencimento, ?),
                                emails_envio = ?
                            WHERE cnpj = ? AND numero_contrato = ? AND empresa = ?
                              AND (seguradora = ? OR (seguradora IS NULL AND ? IS NULL))
                        ");
                        $stmtFatUpd->execute([
                            $origem, $ramo, $seguradora, $numContrato, $cnpjFmt,
                            $grupo, $nome,
                            $analistaId, $analistaNm,
                            $percCom,
                            $dataEmi, $dataVenc,
                            $emailsFat,
                            $oldEmp['cnpj'], $oldEmp['numero_contrato'], $oldEmp['nome'],
                            $oldEmp['seguradora'], $oldEmp['seguradora']
                        ]);
                        $fatAfetados = $stmtFatUpd->rowCount();
                    } catch (PDOException $e) { /* silencioso */ }

                    try {
                        // NULLIF(..., '0000-00-00') trata "zero date" do MySQL como NULL
                        // pra que o COALESCE consiga preencher com o valor novo do cadastro.
                        $stmtRenUpd = db()->prepare("
                            UPDATE renovacao SET
                                origem = ?, ramo = ?, seguradora = ?, numero_contrato = ?, cnpj = ?,
                                grupo = ?, empresa = ?,
                                analista_id = ?, analista_nome = ?,
                                percentual_comissao = COALESCE(?, percentual_comissao),
                                inicio_vigencia = COALESCE(NULLIF(inicio_vigencia, '0000-00-00'), ?),
                                mes_renovacao   = COALESCE(NULLIF(mes_renovacao,   '0000-00-00'), ?),
                                numero_vidas    = COALESCE(numero_vidas, ?),
                                resp_nome = ?, resp_email = ?, resp_telefone = ?
                            WHERE cnpj = ? AND numero_contrato = ? AND empresa = ?
                              AND (seguradora = ? OR (seguradora IS NULL AND ? IS NULL))
                        ");
                        $stmtRenUpd->execute([
                            $origem, $ramo, $seguradora, $numContrato, $cnpjFmt,
                            $grupo, $nome,
                            $analistaId, $analistaNm,
                            $percCom,
                            $iniVig, $mesRen,
                            $vidasEmp,
                            $respNome, $respEmail, $respTelefone,
                            $oldEmp['cnpj'], $oldEmp['numero_contrato'], $oldEmp['nome'],
                            $oldEmp['seguradora'], $oldEmp['seguradora']
                        ]);
                        $renAfetados = $stmtRenUpd->rowCount();
                    } catch (PDOException $e) { /* silencioso */ }
                }

                // NÃO retorna aqui: continua para o bloco de auto-criação abaixo, para
                // GARANTIR que a empresa exista em Faturamento (competência atual) mesmo
                // quando foi cadastrada antes e ficou de fora — ex.: subestipulantes.
                $empresaId = $id;
            } else {
                // Antes de inserir, checa se já existe uma empresa INATIVA com
                // os mesmos dados-chave (grupo + nome + seguradora + nº contrato).
                // Se sim, REATIVA em vez de dar erro de duplicata.
                $isReactivated = false;
                $reativarId = null;
                $stCheck = db()->prepare("
                    SELECT id FROM empresas
                    WHERE grupo = ? AND nome = ?
                      AND (seguradora = ? OR (seguradora IS NULL AND ? IS NULL))
                      AND (numero_contrato = ? OR (numero_contrato IS NULL AND ? IS NULL))
                      AND ativo = 0
                    LIMIT 1
                ");
                $stCheck->execute([$grupo, $nome, $seguradora, $seguradora, $numContrato, $numContrato]);
                $reativarId = $stCheck->fetchColumn();

                if ($reativarId) {
                    // Reativa e atualiza com os dados do form (NÃO dá return aqui —
                    // continua o fluxo pra rodar a auto-criação de faturamento/renovação)
                    $stReativ = db()->prepare("
                        UPDATE empresas SET
                            ativo = 1,
                            cnpj = ?, tipo_estipulacao = ?, observacao = ?,
                            origem = ?, ramo = ?, porte = ?,
                            analista_id = ?, analista_nome = ?,
                            data_emissao = ?, data_vencimento = ?,
                            inicio_vigencia = ?, mes_renovacao = ?, numero_vidas = ?,
                            percentual_comissao = ?, emails_faturamento = ?,
                            resp_nome = ?, resp_email = ?, resp_telefone = ?
                        WHERE id = ?
                    ");
                    $stReativ->execute([
                        $cnpjFmt, $tipoEst, $obs,
                        $origem, $ramo, $porte,
                        $analistaId, $analistaNm,
                        $dataEmi, $dataVenc,
                        $iniVig, $mesRen, $vidasEmp,
                        $percCom, $emailsFat,
                        $respNome, $respEmail, $respTelefone,
                        $reativarId
                    ]);
                    $empresaId = $reativarId;
                    $isReactivated = true;
                    // Pula o INSERT — a empresa já existe e foi atualizada
                    goto _empresa_inserida;
                }

                $stmt = db()->prepare("
                    INSERT INTO empresas
                        (grupo, nome, cnpj, tipo_estipulacao, observacao,
                         origem, ramo, seguradora, numero_contrato, porte,
                         analista_id, analista_nome, data_emissao, data_vencimento,
                         inicio_vigencia, mes_renovacao, numero_vidas,
                         percentual_comissao, emails_faturamento,
                         resp_nome, resp_email, resp_telefone)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([
                    $grupo, $nome, $cnpjFmt, $tipoEst, $obs,
                    $origem, $ramo, $seguradora, $numContrato, $porte,
                    $analistaId, $analistaNm, $dataEmi, $dataVenc,
                    $iniVig, $mesRen, $vidasEmp,
                    $percCom, $emailsFat,
                    $respNome, $respEmail, $respTelefone
                ]);
                $empresaId = db()->lastInsertId();
            }

            // Ponto comum de CRIAR, REATIVAR e EDITAR: garante Faturamento/Renovação
            _empresa_inserida:

                // ── Auto-criação em FATURAMENTO e RENOVAÇÃO ──────────────
                // REGRA NOVA (pedida pela usuária): TODA empresa cadastrada vai pra
                // Renovação E Faturamento automaticamente, mesmo sem todos os campos.
                // Os campos faltantes podem ser preenchidos depois nas próprias telas.
                $temMinimo = !empty($grupo) && !empty($nome);
                $fatCriados = 0; $renCriados = 0;
                if ($temMinimo) {
                    // Competência atual no formato Mes/AA (ex.: Abr/26)
                    $meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
                    $competenciaAtual = $meses[(int)date('n')-1] . '/' . substr(date('Y'), -2);

                    // Faturamento — INSERT IGNORE-style: só cria se ainda não
                    // existe na competência atual (evita duplicar quando empresa é reativada).
                    // Empresa nova cadastrada gera faturamento no FATURAMENTO ATUAL (faturamento_backup),
                    // nao nos Anteriores.
                    try {
                        $stChkFat = db()->prepare("SELECT 1 FROM faturamento_backup
                            WHERE cnpj = ? AND numero_contrato = ? AND empresa = ?
                              AND competencia = ?
                              AND (seguradora = ? OR (seguradora IS NULL AND ? IS NULL))
                            LIMIT 1");
                        $stChkFat->execute([$cnpjFmt, $numContrato, $nome, $competenciaAtual, $seguradora, $seguradora]);
                        if (!$stChkFat->fetchColumn()) {
                            $novoIdBk = (int) db()->query("SELECT COALESCE(MAX(id),0)+1 FROM faturamento_backup")->fetchColumn();
                            $stmtFat = db()->prepare("INSERT INTO faturamento_backup
                                (id, origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa,
                                 analista_nome, competencia,
                                 data_emissao, data_vencimento,
                                 percentual_comissao, emails_envio, status_envio)
                                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Pendente emissão')");
                            $stmtFat->execute([
                                $novoIdBk, $origem, $ramo, $seguradora, $numContrato, $cnpjFmt, $grupo, $nome,
                                $analistaNm, $competenciaAtual,
                                $dataEmi, $dataVenc,
                                $percCom, $emailsFat
                            ]);
                            $fatCriados = 1;
                        }
                    } catch (PDOException $e) { error_log('auto-faturamento (Atual) falhou (empresa '.$empresaId.'): '.$e->getMessage()); }

                    // Faturamento OUTUBRO — mesma regra do Atual (estipulante E sub),
                    // mês de envio Out/2026. Vidas e valores ficam em branco.
                    try {
                        db()->exec("CREATE TABLE IF NOT EXISTS faturamento_outubro LIKE faturamento_backup");
                        $compOut = 'Out/' . date('Y'); // Out/2026
                        $stChkOut = db()->prepare("SELECT 1 FROM faturamento_outubro
                            WHERE cnpj = ? AND numero_contrato = ? AND empresa = ?
                              AND competencia = ?
                              AND (seguradora = ? OR (seguradora IS NULL AND ? IS NULL))
                            LIMIT 1");
                        $stChkOut->execute([$cnpjFmt, $numContrato, $nome, $compOut, $seguradora, $seguradora]);
                        if (!$stChkOut->fetchColumn()) {
                            $novoIdOut = (int) db()->query("SELECT COALESCE(MAX(id),0)+1 FROM faturamento_outubro")->fetchColumn();
                            $stmtOut = db()->prepare("INSERT INTO faturamento_outubro
                                (id, origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa,
                                 analista_nome, competencia,
                                 data_emissao, data_vencimento,
                                 percentual_comissao, emails_envio, status_envio)
                                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Pendente emissão')");
                            $stmtOut->execute([
                                $novoIdOut, $origem, $ramo, $seguradora, $numContrato, $cnpjFmt, $grupo, $nome,
                                $analistaNm, $compOut,
                                $dataEmi, $dataVenc,
                                $percCom, $emailsFat
                            ]);
                        }
                    } catch (PDOException $e) { error_log('auto-faturamento (Outubro) falhou (empresa '.$empresaId.'): '.$e->getMessage()); }

                    // Renovação — auto-cria APENAS se a empresa for ESTIPULANTE
                    // (regra de negócio: subestipulantes não geram renovação própria,
                    // o ciclo é controlado pelo estipulante principal).
                    if ($tipoEst === 'Estipulante') {
                        try {
                            // Calcula mes_renovacao com fallback: form > vigência+1 ano > hoje+1 ano
                            $mesRenAuto = $mesRen
                                ?: ($iniVig ? date('Y-m-d', strtotime($iniVig . ' +1 year')) : null)
                                ?: date('Y-m-d', strtotime('+1 year'));
                            $anoCiclo = intval(date('Y', strtotime($mesRenAuto)));

                            // Só cria se ainda não existe ciclo desse ano pra essa empresa
                            $stChkRen = db()->prepare("SELECT 1 FROM renovacao
                                WHERE empresa_id = ? AND ano_ciclo = ? LIMIT 1");
                            $stChkRen->execute([$empresaId, $anoCiclo]);
                            if (!$stChkRen->fetchColumn()) {
                                $stmtRen = db()->prepare("INSERT INTO renovacao
                                    (empresa_id, ano_ciclo, mes_renovacao,
                                     status, tipo, aditivo,
                                     numero_vidas_ciclo, porte_ciclo,
                                     analista_seguradora,
                                     criado_em, atualizado_em)
                                    VALUES (?, ?, ?, 'A iniciar', 'Renovação', NULL, ?, ?, ?, NOW(), NOW())");
                                $stmtRen->execute([
                                    $empresaId,
                                    $anoCiclo,
                                    $mesRenAuto,
                                    $vidasEmp,
                                    $porte,
                                    $analistaNm
                                ]);
                                $renCriados = 1;
                            }
                        } catch (PDOException $e) { error_log('auto-renovacao falhou (empresa '.$empresaId.'): '.$e->getMessage()); }
                    }
                }

                db()->exec("SET FOREIGN_KEY_CHECKS = 1");
                json([
                    'ok' => true,
                    'id' => $empresaId,
                    'auto_criado' => $temMinimo,
                    'reativado' => $isReactivated ?? false,
                    'fat_atualizados' => ($fatAfetados ?? 0) + ($fatCriados ?? 0),
                    'ren_atualizados' => ($renAfetados ?? 0) + ($renCriados ?? 0),
                    'fat_criado' => (($fatCriados ?? 0) > 0),
                    'msg' => ($isReactivated ?? false)
                        ? 'Empresa estava inativa — foi reativada com os dados atualizados'
                        : null
                ]);
        } catch (PDOException $e) {
            db()->exec("SET FOREIGN_KEY_CHECKS = 1");
            // Conflito de chave única (grupo, nome, seguradora, numero_contrato)
            if ($e->errorInfo[1] == 1062) {
                json(['ok' => false, 'msg' => 'Já existe um cadastro com este Grupo + Empresa + Seguradora + Nº Contrato']);
            }
            json(['ok' => false, 'msg' => 'Erro ao salvar: '.$e->getMessage()]);
        }
        break;

    case 'excluir_empresa':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);

        db()->prepare("UPDATE empresas SET ativo = 0 WHERE id = ?")->execute([$id]);
        json(['ok' => true]);
        break;

    // ═══════════════════════════════════════════════════
    //  CONFIGURAÇÕES
    // ═══════════════════════════════════════════════════

    case 'get_config':
        adminOnly();
        try {
            $rows = db()->query("SELECT chave, valor FROM configuracoes")->fetchAll(PDO::FETCH_ASSOC);
            $config = [];
            foreach ($rows as $r) {
                $config[$r['chave']] = $r['valor'];
            }
        } catch (PDOException $e) {
            $config = [];
        }
        json(['ok' => true, 'data' => $config]);
        break;

    case 'save_config':
        adminOnly();
        $d = jsonBody();

        $campos = ['email_notificacao', 'alerta_dias', 'resumo_horario', 'resumo_dias'];

        foreach ($campos as $chave) {
            if (!isset($d[$chave])) continue;
            $valor = trim($d[$chave]);

            $stmt = db()->prepare("
                INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)
            ");
            $stmt->execute([$chave, $valor]);
        }

        json(['ok' => true]);
        break;

    case 'test_email':
        adminOnly();
        $d  = jsonBody();
        $to = trim($d['to'] ?? '');

        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            json(['ok' => false, 'msg' => 'E-mail inválido']);
        }

        $subject = 'INTERWEG - E-mail de Teste';
        $body    = '
            <div style="font-family:Segoe UI,sans-serif;max-width:500px;margin:0 auto;padding:30px;background:#161b22;color:#e6edf3;border-radius:12px;border:1px solid #30363d">
                <div style="text-align:center;margin-bottom:20px">
                    <span style="font-size:24px;font-weight:700;color:#e6edf3">INTER<span style="color:#00c9a7">WEG</span></span>
                    <div style="font-size:11px;color:#8b949e;text-transform:uppercase;letter-spacing:1px">Seguros</div>
                </div>
                <div style="background:#0d1117;border-radius:8px;padding:20px;border:1px solid #30363d;text-align:center">
                    <div style="font-size:32px;margin-bottom:10px">&#9989;</div>
                    <h2 style="color:#00c9a7;margin-bottom:8px">E-mail de Teste</h2>
                    <p style="color:#8b949e;font-size:14px">Se você está lendo esta mensagem, as notificações por e-mail estão funcionando corretamente.</p>
                </div>
                <p style="color:#484f58;font-size:11px;text-align:center;margin-top:16px">INTERWEG Benefícios &bull; Sistema de Controle de Demandas</p>
            </div>';

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: INTERWEG Sistema <noreply@interweg-beneficios.com.br>\r\n";

        if (is_file(__DIR__ . '/ambiente.php')) { require_once __DIR__ . '/ambiente.php'; }
        if (!defined('IS_HOMOLOG')) define('IS_HOMOLOG', false);
        $sent = IS_HOMOLOG ? true : @mail($to, $subject, $body, $headers);

        json([
            'ok'  => $sent,
            'msg' => $sent ? 'E-mail de teste enviado com sucesso!' : 'Erro ao enviar. Verifique a configuração do servidor de e-mail.',
        ]);
        break;

    // ═══════════════════════════════════════════════════
    //  FLUXO COMERCIAL
    // ═══════════════════════════════════════════════════

    case 'listar_fluxo':
        $sql = "SELECT d.*, COALESCE(a.nome, d.analista_nome) AS analista_nome
                FROM demandas d LEFT JOIN analistas a ON d.analista_id = a.id
                WHERE d.situacao != 'Concluído'
                  AND (d.etapa IS NULL OR d.etapa != 'concluido')";
        $params = [];

        $analista = trim($_GET['analista'] ?? '');
        if ($analista) {
            $sql .= " AND COALESCE(a.nome, d.analista_nome) = ?";
            $params[] = $analista;
        }

        $sql .= " ORDER BY d.recebimento DESC, d.id DESC";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular SLA status dinâmico para cada demanda
        $hoje = new DateTime();
        foreach ($rows as &$row) {
            if (empty($row['etapa'])) $row['etapa'] = 'prospeccao';
            if (!empty($row['sla_previsto'])) {
                $sla = new DateTime($row['sla_previsto']);
                $diff = (int) $hoje->diff($sla)->format('%R%a');
                if ($diff < 0) $row['sla_status'] = 'estourado';
                elseif ($diff <= 1) $row['sla_status'] = 'atencao';
                else $row['sla_status'] = 'no_prazo';
            } else {
                $row['sla_status'] = 'no_prazo';
            }
        }
        unset($row);

        json(['ok' => true, 'data' => $rows]);
        break;

    case 'criar_fluxo':
        $d = jsonBody();

        // Verificar se as colunas de fluxo existem (migration pode não ter sido rodada)
        $hasFluxoCols = true;
        try {
            db()->query("SELECT etapa FROM demandas LIMIT 0");
        } catch (PDOException $e) {
            $hasFluxoCols = false;
        }

        if ($hasFluxoCols) {
            $stmt = db()->prepare("
                INSERT INTO demandas
                    (grupo, empresa, seguradora, ramo, tipo_demanda, situacao, etapa, vidas,
                     tipo_apresentacao, sla_previsto, recebimento, analista_id, analista_nome,
                     responsavel_posvenda, obs_fluxo)
                VALUES (?, ?, ?, ?, 'Comercial', 'Em Análise', ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            // Buscar nome do analista
            $analistaId = intval($d['analista_id'] ?? 0) ?: null;
            $analNome = '';
            if ($analistaId) {
                $st = db()->prepare("SELECT nome FROM analistas WHERE id = ?");
                $st->execute([$analistaId]);
                $analNome = $st->fetchColumn() ?: '';
            }

            $stmt->execute([
                trim($d['grupo'] ?? ''),
                trim($d['empresa'] ?? ''),
                trim($d['seguradora'] ?? ''),
                trim($d['ramo'] ?? ''),
                trim($d['etapa'] ?? 'prospeccao'),
                intval($d['vidas'] ?? 0) ?: null,
                $d['tipo_apresentacao'] ?? null,
                $d['sla_previsto'] ?? null,
                $d['recebimento'] ?: null,
                $analistaId,
                $analNome,
                trim($d['responsavel_posvenda'] ?? ''),
                trim($d['obs_fluxo'] ?? ''),
            ]);
        } else {
            // Fallback: criar sem colunas de fluxo
            $analistaId = intval($d['analista_id'] ?? 0) ?: null;
            $analNome = '';
            if ($analistaId) {
                $st = db()->prepare("SELECT nome FROM analistas WHERE id = ?");
                $st->execute([$analistaId]);
                $analNome = $st->fetchColumn() ?: '';
            }

            $stmt = db()->prepare("
                INSERT INTO demandas
                    (grupo, empresa, seguradora, ramo, tipo_demanda, situacao,
                     recebimento, analista_id, analista_nome, detalhamento)
                VALUES (?, ?, ?, ?, 'Comercial', 'Em Análise', ?, ?, ?, ?)
            ");
            $stmt->execute([
                trim($d['grupo'] ?? ''),
                trim($d['empresa'] ?? ''),
                trim($d['seguradora'] ?? ''),
                trim($d['ramo'] ?? ''),
                $d['recebimento'] ?: null,
                $analistaId,
                $analNome,
                trim($d['obs_fluxo'] ?? ''),
            ]);
        }

        $newId = db()->lastInsertId();

        // Registrar no histórico de etapas
        try {
            $stmt2 = db()->prepare("INSERT INTO demanda_etapas (demanda_id, etapa_de, etapa_para, usuario_id, usuario_nome) VALUES (?, NULL, ?, ?, ?)");
            $stmt2->execute([$newId, trim($d['etapa'] ?? 'prospeccao'), $_SESSION['user_id'], $_SESSION['user_nome']]);
        } catch (PDOException $e) { /* tabela pode não existir ainda */ }

        json(['ok' => true, 'id' => $newId]);
        break;

    case 'editar_fluxo':
        $d  = jsonBody();
        $id = intval($d['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);

        // Buscar etapa anterior para log
        $etapaAnterior = null;
        try {
            $st = db()->prepare("SELECT etapa FROM demandas WHERE id = ?");
            $st->execute([$id]);
            $etapaAnterior = $st->fetchColumn();
        } catch (PDOException $e) {}

        $hasFluxoCols = true;
        try {
            db()->query("SELECT etapa FROM demandas LIMIT 0");
        } catch (PDOException $e) {
            $hasFluxoCols = false;
        }

        $analistaId = intval($d['analista_id'] ?? 0) ?: null;
        $analNome = '';
        if ($analistaId) {
            $st = db()->prepare("SELECT nome FROM analistas WHERE id = ?");
            $st->execute([$analistaId]);
            $analNome = $st->fetchColumn() ?: '';
        }

        if ($hasFluxoCols) {
            // Verificar se coluna pendencia existe
            $hasPendencia = true;
            try { db()->query("SELECT pendencia FROM demandas LIMIT 0"); } catch (PDOException $e) { $hasPendencia = false; }

            if ($hasPendencia) {
                $stmt = db()->prepare("
                    UPDATE demandas SET
                        grupo = ?, empresa = ?, seguradora = ?, ramo = ?,
                        etapa = ?, vidas = ?, tipo_apresentacao = ?, sla_previsto = ?,
                        recebimento = ?, analista_id = ?, analista_nome = ?,
                        responsavel_posvenda = ?, obs_fluxo = ?,
                        pendencia = ?, motivo_pendencia = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    trim($d['grupo'] ?? ''),
                    trim($d['empresa'] ?? ''),
                    trim($d['seguradora'] ?? ''),
                    trim($d['ramo'] ?? ''),
                    trim($d['etapa'] ?? 'prospeccao'),
                    intval($d['vidas'] ?? 0) ?: null,
                    $d['tipo_apresentacao'] ?? null,
                    $d['sla_previsto'] ?? null,
                    $d['recebimento'] ?: null,
                    $analistaId,
                    $analNome,
                    trim($d['responsavel_posvenda'] ?? ''),
                    trim($d['obs_fluxo'] ?? ''),
                    intval($d['pendencia'] ?? 0),
                    trim($d['motivo_pendencia'] ?? '') ?: null,
                    $id,
                ]);
            } else {
                $stmt = db()->prepare("
                    UPDATE demandas SET
                        grupo = ?, empresa = ?, seguradora = ?, ramo = ?,
                        etapa = ?, vidas = ?, tipo_apresentacao = ?, sla_previsto = ?,
                        recebimento = ?, analista_id = ?, analista_nome = ?,
                        responsavel_posvenda = ?, obs_fluxo = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    trim($d['grupo'] ?? ''),
                    trim($d['empresa'] ?? ''),
                    trim($d['seguradora'] ?? ''),
                    trim($d['ramo'] ?? ''),
                    trim($d['etapa'] ?? 'prospeccao'),
                    intval($d['vidas'] ?? 0) ?: null,
                    $d['tipo_apresentacao'] ?? null,
                    $d['sla_previsto'] ?? null,
                    $d['recebimento'] ?: null,
                    $analistaId,
                    $analNome,
                    trim($d['responsavel_posvenda'] ?? ''),
                    trim($d['obs_fluxo'] ?? ''),
                    $id,
                ]);
            }
        } else {
            $stmt = db()->prepare("
                UPDATE demandas SET
                    grupo = ?, empresa = ?, seguradora = ?, ramo = ?,
                    recebimento = ?, analista_id = ?, analista_nome = ?,
                    detalhamento = ?
                WHERE id = ?
            ");
            $stmt->execute([
                trim($d['grupo'] ?? ''),
                trim($d['empresa'] ?? ''),
                trim($d['seguradora'] ?? ''),
                trim($d['ramo'] ?? ''),
                $d['recebimento'] ?: null,
                $analistaId,
                $analNome,
                trim($d['obs_fluxo'] ?? ''),
                $id,
            ]);
        }

        // Log mudança de etapa
        $novaEtapa = trim($d['etapa'] ?? 'prospeccao');
        if ($etapaAnterior !== null && $etapaAnterior !== $novaEtapa) {
            try {
                $stmt2 = db()->prepare("INSERT INTO demanda_etapas (demanda_id, etapa_de, etapa_para, usuario_id, usuario_nome) VALUES (?, ?, ?, ?, ?)");
                $stmt2->execute([$id, $etapaAnterior, $novaEtapa, $_SESSION['user_id'], $_SESSION['user_nome']]);
            } catch (PDOException $e) {}
        }

        json(['ok' => true]);
        break;

    // ═══════════════════════════════════════════════════
    //  FATURAMENTO — Controle de Faturamento "A iniciar"
    // ═══════════════════════════════════════════════════

    case 'salvar_sinistralidade':
        // Grava (upsert) os números do Informativo de Sinistralidade para o Dashboard.
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS sinistralidade_painel (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa VARCHAR(255) NOT NULL,
                competencia VARCHAR(50) NOT NULL DEFAULT '',
                sinistralidade_mes DECIMAL(7,2) NULL,
                acumulado_12 DECIMAL(7,2) NULL,
                breakeven DECIMAL(7,2) NULL,
                variacao VARCHAR(20) NULL,
                analista_nome VARCHAR(120) NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_emp_comp (empresa(150), competencia(40))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {}
        $d = jsonBody();
        $empresa = trim($d['empresa'] ?? '');
        if ($empresa === '') json(['ok' => false, 'msg' => 'Empresa em branco']);
        $comp = trim($d['competencia'] ?? '');
        $numOrNull = function($v){ return ($v === null || $v === '') ? null : floatval($v); };
        try {
            $st = db()->prepare("INSERT INTO sinistralidade_painel
                (empresa, competencia, sinistralidade_mes, acumulado_12, breakeven, variacao, analista_nome)
                VALUES (?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  sinistralidade_mes=VALUES(sinistralidade_mes), acumulado_12=VALUES(acumulado_12),
                  breakeven=VALUES(breakeven), variacao=VALUES(variacao),
                  analista_nome=VALUES(analista_nome), atualizado_em=CURRENT_TIMESTAMP");
            $st->execute([
                $empresa, $comp,
                $numOrNull($d['sinistralidade_mes'] ?? null),
                $numOrNull($d['acumulado_12'] ?? null),
                $numOrNull($d['breakeven'] ?? null),
                trim($d['variacao'] ?? ''),
                trim($_SESSION['user_nome'] ?? '')
            ]);
            json(['ok' => true]);
        } catch (Throwable $e) { json(['ok' => false, 'msg' => $e->getMessage()]); }
        break;

    case 'listar_sinistralidade':
        // Lista os informativos salvos (mais recente por empresa) para o Dashboard.
        try {
            $rows = db()->query("SELECT empresa, competencia, sinistralidade_mes, acumulado_12,
                                        breakeven, variacao, analista_nome, atualizado_em
                                 FROM sinistralidade_painel
                                 ORDER BY atualizado_em DESC")->fetchAll(PDO::FETCH_ASSOC);
            json(['ok' => true, 'data' => $rows]);
        } catch (Throwable $e) { json(['ok' => true, 'data' => []]); }
        break;

    case 'listar_envios_sinistralidade':
        // Controle de envio: log de envios + informativos salvos (pra apontar pendentes).
        $log = []; $painel = [];
        try {
            $log = db()->query("SELECT id, empresa, enviado_por_nome, destinatarios, assunto, status, criado_em
                                FROM sinistralidade_emails_log ORDER BY criado_em DESC LIMIT 2000")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}
        try {
            $painel = db()->query("SELECT empresa, MAX(competencia) AS competencia, MAX(atualizado_em) AS atualizado_em
                                   FROM sinistralidade_painel GROUP BY empresa ORDER BY empresa")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}
        json(['ok' => true, 'log' => $log, 'painel' => $painel]);
        break;

    // ═══════════════════════════════════════════════════
    //  SINISTRALIDADE — Base de empresas + Controle mensal (BI / Recebimento / Envio)
    //  (baseado na planilha "Envio de sinistro")
    // ═══════════════════════════════════════════════════
    case 'sin_base_listar':
    case 'sin_etapas_listar':
    case 'sin_etapa_salvar':
    case 'sin_base_salvar':
    case 'sin_base_editar':
    case 'sin_base_excluir':
    case 'sin_importar':
    case 'sin_virar_mes':
        // Garante as tabelas (idempotente).
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS sinistralidade_base (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa VARCHAR(150) NOT NULL,
                operadora VARCHAR(80) NOT NULL DEFAULT '',
                emails TEXT NULL,
                indice_12m DECIMAL(7,2) NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_emp_op (empresa, operadora)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            db()->exec("CREATE TABLE IF NOT EXISTS sinistralidade_etapas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa VARCHAR(150) NOT NULL,
                operadora VARCHAR(80) NOT NULL DEFAULT '',
                competencia VARCHAR(7) NOT NULL,
                bi TINYINT(1) NOT NULL DEFAULT 0,
                recebimento TINYINT(1) NOT NULL DEFAULT 0,
                envio TINYINT(1) NOT NULL DEFAULT 0,
                atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_emp_op_comp (empresa, operadora, competencia)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {}

        if ($action === 'sin_base_listar') {
            try {
                $rows = db()->query("SELECT id, empresa, operadora, emails, indice_12m, ativo
                                     FROM sinistralidade_base WHERE ativo=1
                                     ORDER BY empresa, operadora")->fetchAll(PDO::FETCH_ASSOC);
                json(['ok' => true, 'data' => $rows]);
            } catch (Throwable $e) { json(['ok' => true, 'data' => []]); }
        }

        if ($action === 'sin_etapas_listar') {
            // Lista TODAS as empresas da base + a etapa daquela competência (0 se não houver).
            $comp = trim($_GET['competencia'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}$/', $comp)) json(['ok' => false, 'msg' => 'Competência inválida (use AAAA-MM)']);
            try {
                $st = db()->prepare("
                    SELECT b.empresa, b.operadora, b.emails, b.indice_12m,
                           COALESCE(e.bi,0) AS bi, COALESCE(e.recebimento,0) AS recebimento, COALESCE(e.envio,0) AS envio
                    FROM sinistralidade_base b
                    LEFT JOIN sinistralidade_etapas e
                      ON e.empresa=b.empresa AND e.operadora=b.operadora AND e.competencia=?
                    WHERE b.ativo=1
                    ORDER BY b.empresa, b.operadora");
                $st->execute([$comp]);
                json(['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC), 'competencia' => $comp]);
            } catch (Throwable $e) { json(['ok' => false, 'msg' => $e->getMessage()]); }
        }

        if ($action === 'sin_etapa_salvar') {
            if (isVisualizador()) json(['ok' => false, 'msg' => 'Acesso negado']);
            $d = jsonBody();
            $emp = trim($d['empresa'] ?? ''); $op = trim($d['operadora'] ?? '');
            $comp = trim($d['competencia'] ?? ''); $campo = trim($d['campo'] ?? '');
            $valor = (intval($d['valor'] ?? 0) === 1) ? 1 : 0;
            if ($emp === '' || !preg_match('/^\d{4}-\d{2}$/', $comp)) json(['ok' => false, 'msg' => 'Dados inválidos']);
            if (!in_array($campo, ['bi','recebimento','envio'], true)) json(['ok' => false, 'msg' => 'Campo inválido']);
            try {
                // Cria a linha se não existir, depois atualiza só o campo pedido.
                $ins = db()->prepare("INSERT IGNORE INTO sinistralidade_etapas (empresa,operadora,competencia) VALUES (?,?,?)");
                $ins->execute([$emp,$op,$comp]);
                $upd = db()->prepare("UPDATE sinistralidade_etapas SET $campo=? WHERE empresa=? AND operadora=? AND competencia=?");
                $upd->execute([$valor,$emp,$op,$comp]);
                json(['ok' => true]);
            } catch (Throwable $e) { json(['ok' => false, 'msg' => $e->getMessage()]); }
        }

        if ($action === 'sin_base_salvar') {
            if (isVisualizador()) json(['ok' => false, 'msg' => 'Acesso negado']);
            $d = jsonBody();
            $emp = trim($d['empresa'] ?? ''); $op = trim($d['operadora'] ?? '');
            if ($emp === '') json(['ok' => false, 'msg' => 'Empresa em branco']);
            $emails = trim($d['emails'] ?? '');
            $idx = ($d['indice_12m'] ?? '') === '' ? null : floatval($d['indice_12m']);
            $ativo = (intval($d['ativo'] ?? 1) === 0) ? 0 : 1;
            try {
                $st = db()->prepare("INSERT INTO sinistralidade_base (empresa,operadora,emails,indice_12m,ativo)
                                     VALUES (?,?,?,?,?)
                                     ON DUPLICATE KEY UPDATE emails=VALUES(emails), indice_12m=VALUES(indice_12m), ativo=VALUES(ativo)");
                $st->execute([$emp,$op,($emails===''?null:$emails),$idx,$ativo]);
                json(['ok' => true]);
            } catch (Throwable $e) { json(['ok' => false, 'msg' => $e->getMessage()]); }
        }

        if ($action === 'sin_base_editar') {
            if (isVisualizador()) json(['ok' => false, 'msg' => 'Acesso negado']);
            $d = jsonBody();
            $oe = trim($d['old_empresa'] ?? '');   $oo = trim($d['old_operadora'] ?? '');
            $ne = trim($d['empresa'] ?? '');        $no = trim($d['operadora'] ?? '');
            $emails = trim($d['emails'] ?? '');
            $idx = ($d['indice_12m'] ?? '') === '' ? null : floatval(str_replace(',', '.', (string)$d['indice_12m']));
            if ($oe === '' || $ne === '') json(['ok' => false, 'msg' => 'Dados inválidos']);
            try {
                db()->prepare("UPDATE sinistralidade_base SET empresa=?, operadora=?, emails=?, indice_12m=?
                               WHERE empresa=? AND operadora=?")
                   ->execute([$ne, $no, ($emails===''?null:$emails), $idx, $oe, $oo]);
                // Mantém o histórico de etapas ligado se a empresa/operadora mudou de nome.
                if ($ne !== $oe || $no !== $oo) {
                    db()->prepare("UPDATE sinistralidade_etapas SET empresa=?, operadora=? WHERE empresa=? AND operadora=?")
                       ->execute([$ne, $no, $oe, $oo]);
                }
                json(['ok' => true]);
            } catch (PDOException $e) {
                $msg = (strpos($e->getMessage(),'uq_emp_op')!==false) ? 'Já existe uma empresa com essa operadora.' : $e->getMessage();
                json(['ok' => false, 'msg' => $msg]);
            }
        }

        if ($action === 'sin_base_excluir') {
            if (isVisualizador()) json(['ok' => false, 'msg' => 'Acesso negado']);
            $d = jsonBody();
            $emp = trim($d['empresa'] ?? ''); $op = trim($d['operadora'] ?? '');
            if ($emp === '') json(['ok' => false, 'msg' => 'Empresa em branco']);
            try {
                db()->prepare("UPDATE sinistralidade_base SET ativo=0 WHERE empresa=? AND operadora=?")->execute([$emp, $op]);
                json(['ok' => true]);
            } catch (Throwable $e) { json(['ok' => false, 'msg' => $e->getMessage()]); }
        }

        if ($action === 'sin_importar') {
            // Importa/atualiza em lote a partir de uma planilha (base + etapas por competência).
            if (isVisualizador()) json(['ok' => false, 'msg' => 'Acesso negado']);
            $d = jsonBody(); $linhas = $d['linhas'] ?? [];
            if (!is_array($linhas) || !count($linhas)) json(['ok' => false, 'msg' => 'Nada para importar']);
            $nb = 0; $ne = 0;
            try {
                $stB = db()->prepare("INSERT INTO sinistralidade_base (empresa,operadora,emails,indice_12m,ativo)
                                      VALUES (?,?,?,?,1)
                                      ON DUPLICATE KEY UPDATE
                                        emails=COALESCE(VALUES(emails), emails),
                                        indice_12m=COALESCE(VALUES(indice_12m), indice_12m)");
                $stE = db()->prepare("INSERT INTO sinistralidade_etapas (empresa,operadora,competencia,bi,recebimento,envio)
                                      VALUES (?,?,?,?,?,?)
                                      ON DUPLICATE KEY UPDATE bi=VALUES(bi), recebimento=VALUES(recebimento), envio=VALUES(envio)");
                foreach ($linhas as $l) {
                    $emp = trim($l['empresa'] ?? ''); if ($emp === '') continue;
                    $op  = trim($l['operadora'] ?? '');
                    $emails = trim($l['emails'] ?? '');
                    $idx = ($l['indice_12m'] ?? '') === '' ? null : floatval($l['indice_12m']);
                    $stB->execute([$emp,$op,($emails===''?null:$emails),$idx]); $nb++;
                    $comp = trim($l['competencia'] ?? '');
                    if (preg_match('/^\d{4}-\d{2}$/', $comp)) {
                        $bi=(intval($l['bi']??0)===1)?1:0; $re=(intval($l['recebimento']??0)===1)?1:0; $en=(intval($l['envio']??0)===1)?1:0;
                        $stE->execute([$emp,$op,$comp,$bi,$re,$en]); $ne++;
                    }
                }
                json(['ok' => true, 'base' => $nb, 'etapas' => $ne]);
            } catch (Throwable $e) { json(['ok' => false, 'msg' => $e->getMessage()]); }
        }

        if ($action === 'sin_virar_mes') {
            // Cria as fichas EM BRANCO do próximo mês para todas as empresas ativas,
            // sem tocar nos meses anteriores (que ficam guardados como estão).
            if (isVisualizador()) json(['ok' => false, 'msg' => 'Acesso negado']);
            $d = jsonBody(); $para = trim($d['competencia'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}$/', $para)) json(['ok' => false, 'msg' => 'Competência inválida (AAAA-MM)']);
            try {
                $st = db()->prepare("INSERT IGNORE INTO sinistralidade_etapas (empresa, operadora, competencia)
                                     SELECT empresa, operadora, ? FROM sinistralidade_base WHERE ativo=1");
                $st->execute([$para]);
                json(['ok' => true, 'criadas' => $st->rowCount(), 'competencia' => $para]);
            } catch (Throwable $e) { json(['ok' => false, 'msg' => $e->getMessage()]); }
        }
        break;

    case 'listar_faturamento_backup':
        // Garante a coluna de data/hora de criação (idempotente).
        try { db()->exec("ALTER TABLE faturamento_backup ADD COLUMN criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) {}
        // Listagem enxuta do Faturamento Atual (faturamento_backup) para o Dashboard.
        $sql = "SELECT id, origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa,
                       analista_nome, competencia, competencia_real, data_emissao, data_vencimento,
                       status_envio, data_envio, numero_vidas, premio_liquido, percentual_comissao,
                       valor_previsto, comissao_recebida, criado_em
                FROM faturamento_backup WHERE 1=1";
        $params = [];
        if (!isAdmin() && !isFinanceiro() && !isVisualizador()) {
            $mapaOp = [
                'operacao 1'=>'Operacao 1','operação 1'=>'Operacao 1',
                'nivea'=>'Operacao 1','gabriela'=>'Operacao 1','nayara'=>'Operacao 1',
                'operacao 2'=>'Operacao 2','operação 2'=>'Operacao 2',
                'angela'=>'Operacao 2','welberth'=>'Operacao 2','welbert'=>'Operacao 2',
            ];
            $unome = trim($_SESSION['user_nome'] ?? '');
            $alvoOp = $mapaOp[mb_strtolower($unome)] ?? null;
            if ($alvoOp !== null) { $sql .= " AND analista_nome IN (?, ?)"; $params[] = $alvoOp; $params[] = $unome; }
            else { $sql .= " AND analista_nome = ?"; $params[] = $unome; }
        }
        $sql .= " ORDER BY analista_nome, empresa";
        try {
            $st = db()->prepare($sql); $st->execute($params);
            json(['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Throwable $e) { json(['ok' => false, 'data' => [], 'msg' => $e->getMessage()]); }
        break;

    // ═══════════ AUTOMAÇÃO: BACKUP DIÁRIO DO FATURAMENTO ATUAL ═══════════
    case 'get_auto_faturamento':
        $fonteG   = (($_GET['fonte'] ?? '') === 'outubro') ? 'outubro' : 'atual';
        $prefixoG = $fonteG === 'outubro' ? 'fatout_auto_' : 'fat_auto_';
        if ($fonteG === 'outubro') { if (!fb_pode_alterar_outubro()) json(['ok' => false, 'msg' => 'Acesso negado']); }
        elseif (!isAdmin() && !isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        try {
            $cfg = db()->query("SELECT chave, valor FROM configuracoes WHERE chave LIKE '$prefixoG%'")->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable $e) { $cfg = []; }
        json(['ok' => true, 'data' => [
            'ativo'        => ($cfg[$prefixoG.'ativo'] ?? '0') === '1',
            'hora'         => $cfg[$prefixoG.'hora'] ?? '18:00',
            'destinatario' => $cfg[$prefixoG.'dest'] ?? 'beneficios3@interweg.com.br',
            'ultimo_envio' => $cfg[$prefixoG.'ultimo'] ?? '',
        ]]);
        break;

    case 'salvar_auto_faturamento':
        $d = jsonBody();
        $fonteS   = (($d['fonte'] ?? '') === 'outubro') ? 'outubro' : 'atual';
        $prefixoS = $fonteS === 'outubro' ? 'fatout_auto_' : 'fat_auto_';
        if ($fonteS === 'outubro') { if (!fb_pode_alterar_outubro()) json(['ok' => false, 'msg' => 'Apenas a Letícia pode alterar a automação do Outubro']); }
        elseif (!isAdmin()) json(['ok' => false, 'msg' => 'Apenas admin pode alterar a automação']);
        $ativo = !empty($d['ativo']) ? '1' : '0';
        $hora  = trim($d['hora'] ?? '18:00');
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $hora)) $hora = '18:00';
        $dest  = trim($d['destinatario'] ?? '');
        foreach (preg_split('/[;,]/', $dest) as $em) {
            $em = trim($em);
            if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) json(['ok' => false, 'msg' => 'E-mail inválido: ' . $em]);
        }
        if ($dest === '') json(['ok' => false, 'msg' => 'Informe ao menos um destinatário.']);
        try {
            $up = db()->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
                                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $up->execute([$prefixoS.'ativo', $ativo]);
            $up->execute([$prefixoS.'hora',  $hora]);
            $up->execute([$prefixoS.'dest',  $dest]);
            json(['ok' => true]);
        } catch (Throwable $e) { json(['ok' => false, 'msg' => 'Erro ao salvar: ' . $e->getMessage()]); }
        break;

    case 'enviar_backup_agora':
        $d = jsonBody();
        $fonteE  = (($d['fonte'] ?? '') === 'outubro') ? 'outubro' : 'atual';
        $tabelaE = $fonteE === 'outubro' ? 'faturamento_outubro' : 'faturamento_backup';
        $prefixoE = $fonteE === 'outubro' ? 'fatout_auto_' : 'fat_auto_';
        if ($fonteE === 'outubro') { if (!fb_pode_alterar_outubro()) json(['ok' => false, 'msg' => 'Acesso negado']); }
        elseif (!isAdmin() && !isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $dest = trim($d['destinatario'] ?? '');
        if ($dest === '') {
            try { $dest = db()->query("SELECT valor FROM configuracoes WHERE chave='{$prefixoE}dest'")->fetchColumn() ?: ''; } catch (Throwable $e) {}
        }
        if ($dest === '') $dest = 'beneficios3@interweg.com.br';
        require_once __DIR__ . '/fat_email_lib.php';
        $erro = ''; $info = [];
        $ok = fatBackupEnviar($dest, $erro, $info, $tabelaE);
        if ($ok) json(['ok' => true, 'enviado_para' => $dest, 'linhas' => $info['linhas'] ?? 0]);
        json(['ok' => false, 'msg' => $erro ?: 'Falha no envio']);
        break;

    case 'listar_faturamento':
        // Garante a coluna valor_a_pagar (migração automática, idempotente).
        try { db()->exec("ALTER TABLE faturamento ADD COLUMN valor_a_pagar DECIMAL(15,2) NULL"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE faturamento ADD COLUMN competencia_real VARCHAR(20) NULL"); } catch (Throwable $e) {}
        // Datas do cadastro (empresas) como fallback quando o faturamento
        // estiver com data_emissao/data_vencimento NULL.
        // IMPORTANTE: usa SUBCONSULTAS (nao LEFT JOIN). Um LEFT JOIN duplicava
        // a linha do faturamento quando existiam empresas cadastradas em
        // duplicidade (mesmo cnpj/contrato/empresa/seguradora, ativas) — o que
        // fazia um unico faturamento aparecer 2x na tela.
        $sql    = "SELECT f.*,
                          COALESCE(f.data_emissao, (
                              SELECT e.data_emissao FROM empresas e
                               WHERE e.cnpj <=> f.cnpj AND e.numero_contrato <=> f.numero_contrato
                                 AND e.nome = f.empresa AND e.seguradora <=> f.seguradora
                                 AND e.ativo = 1 ORDER BY e.id LIMIT 1
                          )) AS data_emissao,
                          COALESCE(f.data_vencimento, (
                              SELECT e.data_vencimento FROM empresas e
                               WHERE e.cnpj <=> f.cnpj AND e.numero_contrato <=> f.numero_contrato
                                 AND e.nome = f.empresa AND e.seguradora <=> f.seguradora
                                 AND e.ativo = 1 ORDER BY e.id LIMIT 1
                          )) AS data_vencimento
                   FROM faturamento f
                   WHERE 1=1";
        $params = [];

        // Se não for admin, financeiro ou visualizador, força filtro pela carteira do analista logado.
        // Financeiro vê tudo (todas as carteiras) pra poder lançar comissões.
        // Visualizador vê tudo (sem editar nada).
        if (!isAdmin() && !isFinanceiro() && !isVisualizador()) {
            // Operadores das operacoes veem a carteira da sua operacao (+ o proprio nome).
            //   Operacao 1: login operacao1 + Nivea, Gabriela, Nayara
            //   Operacao 2: login operacao2 + Angela, Welberth
            $mapaOp = [
                'operacao 1'=>'Operacao 1','operação 1'=>'Operacao 1',
                'nivea'=>'Operacao 1','gabriela'=>'Operacao 1','nayara'=>'Operacao 1',
                'operacao 2'=>'Operacao 2','operação 2'=>'Operacao 2',
                'angela'=>'Operacao 2','welberth'=>'Operacao 2','welbert'=>'Operacao 2',
            ];
            $unome = trim($_SESSION['user_nome'] ?? '');
            $alvoOp = $mapaOp[mb_strtolower($unome)] ?? null;
            if ($alvoOp !== null) {
                $sql .= " AND f.analista_nome IN (?, ?)";
                $params[] = $alvoOp;
                $params[] = $unome;
            } else {
                $sql .= " AND f.analista_nome = ?";
                $params[] = $unome;
            }
        }

        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $like = "%{$search}%";
            $sql .= " AND (f.empresa LIKE ? OR f.grupo LIKE ? OR f.cnpj LIKE ? OR f.numero_contrato LIKE ? OR f.seguradora LIKE ?)";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $origem = trim($_GET['origem'] ?? '');
        if ($origem) { $sql .= " AND f.origem = ?"; $params[] = $origem; }

        $ramo = trim($_GET['ramo'] ?? '');
        if ($ramo) { $sql .= " AND f.ramo = ?"; $params[] = $ramo; }

        $seguradora = trim($_GET['seguradora'] ?? '');
        if ($seguradora) { $sql .= " AND f.seguradora = ?"; $params[] = $seguradora; }

        // Filtro adicional por analista (admin pode usar pra ver carteira de outro)
        $analista = trim($_GET['analista'] ?? '');
        if ($analista && isAdmin()) { $sql .= " AND f.analista_nome = ?"; $params[] = $analista; }

        $status    = trim($_GET['status']    ?? '');
        $statusIn  = trim($_GET['status_in'] ?? '');
        if ($status === '__vazio__') {
            // Filtro especial: pega faturamentos sem status (NULL ou string vazia)
            $sql .= " AND (f.status_envio IS NULL OR f.status_envio = '')";
        } elseif ($statusIn !== '') {
            // Filtro por múltiplos status (CSV) — usado pelo card "Pendentes de envio"
            $lista = array_filter(array_map('trim', explode(',', $statusIn)));
            if (count($lista)) {
                $placeholders = implode(',', array_fill(0, count($lista), '?'));
                $sql .= " AND f.status_envio IN ($placeholders)";
                foreach ($lista as $s) $params[] = $s;
            }
        } elseif ($status) {
            $sql .= " AND f.status_envio = ?"; $params[] = $status;
        }

        $grupo = trim($_GET['grupo'] ?? '');
        if ($grupo) { $sql .= " AND f.grupo = ?"; $params[] = $grupo; }

        $competencia = trim($_GET['competencia'] ?? '');
        if ($competencia) {
            // Aceita "YYYY-MM" (do <input type="month">) e converte pra "Mes/AA" como está no banco
            if (preg_match('/^(\d{4})-(\d{2})$/', $competencia, $m)) {
                $meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
                $idx = intval($m[2]) - 1;
                if ($idx >= 0 && $idx < 12) {
                    $competencia = $meses[$idx] . '/' . substr($m[1], -2);
                }
            }
            $sql .= " AND f.competencia = ?"; $params[] = $competencia;
        }

        // Filtro por data de emissão (2 calendários: "de" e "até"):
        //  - Só "de" preenchido          → filtra dia exato (=)
        //  - Só "até" preenchido         → filtra todos até essa data (<=)
        //  - Ambos preenchidos           → faixa fechada (BETWEEN, ex: 01 a 10)
        //  - Ambos vazios                → ignora filtro
        $emissaoDe  = trim($_GET['emissao_de']  ?? '');
        $emissaoAte = trim($_GET['emissao_ate'] ?? '');
        if ($emissaoDe !== '' && $emissaoAte !== '') {
            $sql .= " AND f.data_emissao BETWEEN ? AND ?";
            $params[] = $emissaoDe;
            $params[] = $emissaoAte;
        } elseif ($emissaoDe !== '') {
            $sql .= " AND f.data_emissao = ?";
            $params[] = $emissaoDe;
        } elseif ($emissaoAte !== '') {
            $sql .= " AND f.data_emissao <= ?";
            $params[] = $emissaoAte;
        }

        $sql .= " ORDER BY f.data_emissao ASC, f.id DESC";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json(['ok' => true, 'data' => $rows]);
        break;

    case 'buscar_faturamento':
        $id   = intval($_GET['id'] ?? 0);
        // Mesmo fallback do listar_faturamento: pega data do cadastro se NULL
        $stmt = db()->prepare("
            SELECT f.*,
                   COALESCE(f.data_emissao, (
                       SELECT e.data_emissao FROM empresas e
                        WHERE e.cnpj <=> f.cnpj AND e.numero_contrato <=> f.numero_contrato
                          AND e.nome = f.empresa AND e.seguradora <=> f.seguradora
                          AND e.ativo = 1 ORDER BY e.id LIMIT 1
                   )) AS data_emissao,
                   COALESCE(f.data_vencimento, (
                       SELECT e.data_vencimento FROM empresas e
                        WHERE e.cnpj <=> f.cnpj AND e.numero_contrato <=> f.numero_contrato
                          AND e.nome = f.empresa AND e.seguradora <=> f.seguradora
                          AND e.ativo = 1 ORDER BY e.id LIMIT 1
                   )) AS data_vencimento
            FROM faturamento f
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) json(['ok' => false, 'msg' => 'Faturamento não encontrado']);
        json(['ok' => true, 'data' => $row]);
        break;

    case 'criar_faturamento':
        if (isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado: financeiro não pode criar faturamento']);
        $d = jsonBody();

        $origem       = trim($d['origem']          ?? '');
        $ramo         = trim($d['ramo']            ?? '');
        $seguradora   = trim($d['seguradora']      ?? '');
        $num_contrato = trim($d['numero_contrato'] ?? '');
        $cnpj         = trim($d['cnpj']            ?? '');
        $grupo        = trim($d['grupo']           ?? '');
        $empresa      = trim($d['empresa']         ?? '');
        $razao_social = trim($d['razao_social']    ?? '');
        $analista_id  = intval($d['analista_id']   ?? 0) ?: null;
        $analista_nm  = trim($d['analista_nome']   ?? '');
        $competencia  = trim($d['competencia']     ?? '');
        $competenciaReal = trim($d['competencia_real'] ?? '');
        $data_emissao = $d['data_emissao'] ?? null;
        $data_venc    = $d['data_vencimento'] ?? null;
        $status       = trim($d['status_envio']    ?? 'Pendente emissão');
        $data_envio   = $d['data_envio'] ?: null;
        $emails       = trim($d['emails_envio']    ?? '');
        $vidas        = intval($d['numero_vidas']  ?? 0);
        $premio       = floatval(str_replace(',', '.', $d['premio_liquido'] ?? 0));
        $perc         = floatval(str_replace(',', '.', $d['percentual_comissao'] ?? 0));
        $valor_prev   = round($premio * $perc / 100, 2);
        $com_rec_val  = $d['comissao_recebida'] ?? '';
        $com_rec      = ($com_rec_val === '' || $com_rec_val === null) ? null : floatval(str_replace(',', '.', $com_rec_val));
        $vp_val       = $d['valor_a_pagar'] ?? '';
        $vpag         = ($vp_val === '' || $vp_val === null) ? null : floatval(str_replace(',', '.', $vp_val));
        $obs          = trim($d['observacoes']     ?? '');

        // Validação dos campos obrigatórios (1-18)
        $missing = [];
        if (!$origem)       $missing[] = 'Origem';
        if (!$ramo)         $missing[] = 'Ramo';
        if (!$seguradora)   $missing[] = 'Seguradora';
        if (!$num_contrato) $missing[] = 'Número do contrato';
        if (!$cnpj)         $missing[] = 'CNPJ';
        if (!$grupo)        $missing[] = 'Grupo';
        if (!$empresa)      $missing[] = 'Empresa';
        if (!$analista_nm)  $missing[] = 'Analista';
        if (!$competencia)  $missing[] = 'Competência';
        if (!$data_emissao) $missing[] = 'Data de emissão';
        if (!$data_venc)    $missing[] = 'Data de vencimento';
        if (!$status)       $missing[] = 'Status de envio';
        if (!$emails)       $missing[] = 'E-mails de envio';
        if ($vidas <= 0)    $missing[] = 'Número de vidas';
        if ($premio <= 0)   $missing[] = 'Prêmio líquido';
        if ($perc < 0)      $missing[] = '% de comissão';
        if (count($missing)) json(['ok' => false, 'msg' => 'Campos obrigatórios: '.implode(', ', $missing)]);

        // CNPJ válido
        if (!validarCNPJ($cnpj)) json(['ok' => false, 'msg' => 'CNPJ inválido']);
        $cnpj = formatarCNPJ($cnpj);

        try {
            db()->exec("SET FOREIGN_KEY_CHECKS = 0");
            try { db()->exec("ALTER TABLE faturamento ADD COLUMN valor_a_pagar DECIMAL(15,2) NULL"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE faturamento ADD COLUMN competencia_real VARCHAR(20) NULL"); } catch (Throwable $e) {}
            $competenciaReal = trim($d['competencia_real'] ?? '');
            $stmt = db()->prepare("
                INSERT INTO faturamento
                    (origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa, razao_social,
                     analista_id, analista_nome, competencia, competencia_real, data_emissao, data_vencimento,
                     status_envio, data_envio, emails_envio, numero_vidas, premio_liquido,
                     percentual_comissao, valor_previsto, comissao_recebida, valor_a_pagar, observacoes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $origem, $ramo, $seguradora, $num_contrato, $cnpj, $grupo, $empresa, $razao_social,
                $analista_id, $analista_nm, $competencia, $competenciaReal, $data_emissao, $data_venc,
                $status, $data_envio, $emails, $vidas, $premio,
                $perc, $valor_prev, $com_rec, $vpag, $obs
            ]);
            $newId = db()->lastInsertId();
            db()->exec("SET FOREIGN_KEY_CHECKS = 1");
        } catch (PDOException $e) {
            db()->exec("SET FOREIGN_KEY_CHECKS = 1");
            json(['ok' => false, 'msg' => 'Erro ao criar faturamento: ' . $e->getMessage()]);
        }

        json(['ok' => true, 'id' => $newId]);
        break;

    case 'atualizar_status_faturamento_lote':
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody();
        $ids = $d['ids'] ?? [];
        $status = trim($d['status'] ?? '');
        $statusValidos = ['Pendente emissão','Aguardando envio','Enviado','Sem emissão','Cancelado','Inadimplente'];
        if (!is_array($ids) || !count($ids)) json(['ok' => false, 'msg' => 'Nenhum faturamento selecionado']);
        if (!in_array($status, $statusValidos, true)) json(['ok' => false, 'msg' => 'Status inválido']);
        $ids = array_values(array_filter(array_map('intval', $ids), function($x){ return $x > 0; }));
        if (!count($ids)) json(['ok' => false, 'msg' => 'IDs inválidos']);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        try {
            if ($status === 'Enviado') {
                $stmt = db()->prepare("UPDATE faturamento SET status_envio = ?, data_envio = COALESCE(data_envio, CURDATE()) WHERE id IN ($ph)");
            } else {
                $stmt = db()->prepare("UPDATE faturamento SET status_envio = ? WHERE id IN ($ph)");
            }
            $stmt->execute(array_merge([$status], $ids));
            json(['ok' => true, 'afetados' => $stmt->rowCount()]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao atualizar em lote: ' . $e->getMessage()]);
        }
        break;

    case 'atualizar_competencia_faturamento_lote':
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d    = jsonBody();
        $ids  = $d['ids'] ?? [];
        $comp = trim($d['competencia'] ?? '');
        if (!is_array($ids) || !count($ids)) json(['ok' => false, 'msg' => 'Nenhum faturamento selecionado']);
        if ($comp === '') json(['ok' => false, 'msg' => 'Informe a competência']);
        $ids = array_values(array_filter(array_map('intval', $ids), function($x){ return $x > 0; }));
        if (!count($ids)) json(['ok' => false, 'msg' => 'IDs inválidos']);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        try {
            // Altera SOMENTE a competência (competencia_real). O "Mês de envio" (competencia) permanece inalterado.
            $stmt = db()->prepare("UPDATE faturamento SET competencia_real = ? WHERE id IN ($ph)");
            $stmt->execute(array_merge([$comp], $ids));
            json(['ok' => true, 'afetados' => $stmt->rowCount()]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao atualizar competência em lote: ' . $e->getMessage()]);
        }
        break;

    case 'importar_fechamento_automacao':
        // Atualiza vidas/premio (e recalcula valor_previsto, marca Enviado) na faturamento_automacao.
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody(); $ups = $d['updates'] ?? []; $modo = trim($d['modo'] ?? 'sobrescrever');
        if (!is_array($ups) || !count($ups)) json(['ok' => false, 'msg' => 'Nada para atualizar']);
        $n = 0;
        try {
            $sel = db()->prepare("SELECT numero_vidas, premio_liquido, percentual_comissao FROM faturamento_automacao WHERE id=?");
            foreach ($ups as $u) {
                $id = intval($u['id'] ?? 0); if ($id<=0) continue;
                $sel->execute([$id]); $cur = $sel->fetch(PDO::FETCH_ASSOC); if (!$cur) continue;
                $vidas = $u['vidas']; $premio = $u['premio'];
                if ($modo === 'completar') {
                    if ($cur['numero_vidas'] !== null && $cur['numero_vidas'] !== '') $vidas = $cur['numero_vidas'];
                    if ($cur['premio_liquido'] !== null && $cur['premio_liquido'] !== '') $premio = $cur['premio_liquido'];
                }
                $perc = floatval($cur['percentual_comissao']);
                $prev = ($premio!==null && $premio!=='') ? round(floatval($premio)*$perc, 2) : null;
                $stmt = db()->prepare("UPDATE faturamento_automacao
                    SET numero_vidas=?, premio_liquido=?, valor_previsto=COALESCE(?,valor_previsto),
                        status_envio='Enviado', data_envio=?
                    WHERE id=?");
                $stmt->execute([($vidas===''?null:$vidas), ($premio===''?null:$premio), $prev, ($u['data_envio'] ?: null), $id]);
                $n++;
            }
            json(['ok' => true, 'afetados' => $n]);
        } catch (PDOException $e) { json(['ok' => false, 'msg' => 'Erro: '.$e->getMessage()]); }
        break;

    case 'atualizar_caminho_automacao':
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody(); $id = intval($d['id'] ?? 0);
        if ($id <= 0) json(['ok' => false, 'msg' => 'ID inválido']);
        $cam = trim($d['caminho_pasta'] ?? '');
        try {
            db()->prepare("UPDATE faturamento_automacao SET caminho_pasta = ? WHERE id = ?")
                ->execute([($cam === '' ? null : $cam), $id]);
            json(['ok' => true]);
        } catch (PDOException $e) { json(['ok' => false, 'msg' => 'Erro: '.$e->getMessage()]); }
        break;

    case 'importar_fechamento_backup':
        // Atualiza no backup vidas/premio/data_envio/status=Enviado das linhas casadas.
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody(); $ups = $d['updates'] ?? []; $modo = trim($d['modo'] ?? 'sobrescrever'); $tab = fb_tabela($d, true);
        $mesEnvio = trim($d['mes_envio'] ?? '');        // grava em competencia (opcional)
        $compReal = trim($d['competencia_real'] ?? ''); // grava em competencia_real (opcional)
        if (!is_array($ups) || !count($ups)) json(['ok' => false, 'msg' => 'Nada para atualizar']);
        $n = 0;
        try {
            $sel = db()->prepare("SELECT numero_vidas, premio_liquido, percentual_comissao FROM $tab WHERE id=?");
            foreach ($ups as $u) {
                $id = intval($u['id'] ?? 0); if ($id<=0) continue;
                $sel->execute([$id]); $cur = $sel->fetch(PDO::FETCH_ASSOC); if (!$cur) continue;
                $vidas  = $u['vidas'];  $premio = $u['premio'];
                if ($modo === 'completar') {
                    if ($cur['numero_vidas'] !== null && $cur['numero_vidas'] !== '') $vidas = $cur['numero_vidas'];
                    if ($cur['premio_liquido'] !== null && $cur['premio_liquido'] !== '') $premio = $cur['premio_liquido'];
                }
                $perc = floatval($cur['percentual_comissao']); // fração (ex 0.05)
                $prevCalc = ($premio !== null && $premio !== '') ? round(floatval($premio) * $perc, 2) : null;
                $sets = "numero_vidas=?, premio_liquido=?, valor_previsto=COALESCE(?,valor_previsto), status_envio='Enviado', data_envio=?";
                $par  = [($vidas===''?null:$vidas), ($premio===''?null:$premio), $prevCalc, ($u['data_envio'] ?: null)];
                if ($mesEnvio !== '') { $sets .= ", competencia=?";      $par[] = $mesEnvio; }
                if ($compReal !== '') { $sets .= ", competencia_real=?"; $par[] = $compReal; }
                $par[] = $id;
                $stmt = db()->prepare("UPDATE $tab SET $sets WHERE id=?");
                $stmt->execute($par);
                $n++;
            }
            json(['ok' => true, 'afetados' => $n]);
        } catch (PDOException $e) { json(['ok' => false, 'msg' => 'Erro: '.$e->getMessage()]); }
        break;

    case 'incluir_competencia_backup':
        // Copia do faturamento ao vivo para o backup todas as linhas de uma competência
        // que ainda NAO estao no backup (por id). Nao sobrescreve o que ja existe.
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody(); $comp = trim($d['competencia'] ?? '');
        if ($comp === '') json(['ok' => false, 'msg' => 'Informe a competência (mês de envio), ex.: Set/26']);
        try {
            $stmt = db()->prepare("
                INSERT INTO faturamento_backup
                 (id, origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa, razao_social,
                  analista_nome, competencia, competencia_real, data_emissao, data_vencimento,
                  status_envio, data_envio, numero_vidas, premio_liquido, percentual_comissao,
                  valor_previsto, comissao_recebida, valor_a_pagar, emails_envio, observacoes)
                SELECT f.id, f.origem, f.ramo, f.seguradora, f.numero_contrato, f.cnpj, f.grupo, f.empresa, f.razao_social,
                  f.analista_nome, f.competencia, f.competencia_real, f.data_emissao, f.data_vencimento,
                  f.status_envio, f.data_envio, f.numero_vidas, f.premio_liquido, f.percentual_comissao,
                  f.valor_previsto, f.comissao_recebida, f.valor_a_pagar, f.emails_envio, f.observacoes
                FROM faturamento f
                WHERE f.competencia = ?
                  AND NOT EXISTS (SELECT 1 FROM faturamento_backup b WHERE b.id = f.id)
            ");
            $stmt->execute([$comp]);
            json(['ok' => true, 'inseridos' => $stmt->rowCount()]);
        } catch (PDOException $e) { json(['ok' => false, 'msg' => 'Erro: '.$e->getMessage()]); }
        break;

    case 'lancar_comissao_backup':
        // FINANCEIRO: lança SÓ comissão recebida + valor a pagar (não mexe em mais nada).
        if (isVisualizador()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody(); $id = intval($d['id'] ?? 0); $tab = fb_tabela($d, true);
        if ($id <= 0) json(['ok' => false, 'msg' => 'ID inválido']);
        $cr = ($d['comissao_recebida'] ?? '') === '' || ($d['comissao_recebida'] ?? null) === null ? null : floatval(str_replace(',', '.', $d['comissao_recebida']));
        $vp = ($d['valor_a_pagar'] ?? '') === '' || ($d['valor_a_pagar'] ?? null) === null ? null : floatval(str_replace(',', '.', $d['valor_a_pagar']));
        try {
            db()->prepare("UPDATE $tab SET comissao_recebida=?, valor_a_pagar=? WHERE id=?")->execute([$cr, $vp, $id]);
            json(['ok' => true]);
        } catch (PDOException $e) { json(['ok' => false, 'msg' => 'Erro: '.$e->getMessage()]); }
        break;

    case 'excluir_faturamento_backup':
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody(); $id = intval($d['id'] ?? 0); $tab = fb_tabela($d);
        if ($id <= 0) json(['ok' => false, 'msg' => 'ID inválido']);
        try {
            db()->prepare("DELETE FROM $tab WHERE id = ?")->execute([$id]);
            json(['ok' => true]);
        } catch (PDOException $e) { json(['ok' => false, 'msg' => 'Erro: '.$e->getMessage()]); }
        break;

    case 'excluir_faturamento_backup_lote':
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody(); $ids = $d['ids'] ?? []; $tab = fb_tabela($d, true);
        $ids = array_values(array_filter(array_map('intval', $ids), function($x){ return $x > 0; }));
        if (!count($ids)) json(['ok' => false, 'msg' => 'Nenhuma linha selecionada']);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = db()->prepare("DELETE FROM $tab WHERE id IN ($ph)");
            $stmt->execute($ids);
            json(['ok' => true, 'afetados' => $stmt->rowCount()]);
        } catch (PDOException $e) { json(['ok' => false, 'msg' => 'Erro: '.$e->getMessage()]); }
        break;

    case 'inserir_faturamento_backup':
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody();
        $campos = ['origem','ramo','seguradora','numero_contrato','cnpj','grupo','empresa','razao_social',
                   'analista_nome','competencia','competencia_real','data_emissao','data_vencimento',
                   'status_envio','data_envio','numero_vidas','premio_liquido','percentual_comissao',
                   'valor_previsto','comissao_recebida','valor_a_pagar','emails_envio','observacoes'];
        // Criação liberada para todos os analistas também nos meses novos (igual à edição).
        $tab = fb_tabela($d, true);
        $ph = []; $vals = [];
        foreach ($campos as $c) { $v = $d[$c] ?? null; if ($v === '' || $v === '—') $v = null; $ph[] = '?'; $vals[] = $v; }
        try {
            // novo id = max+1 (a tabela backup nao tem auto_increment)
            $novoId = (int) db()->query("SELECT COALESCE(MAX(id),0)+1 FROM $tab")->fetchColumn();
            $stmt = db()->prepare("INSERT INTO $tab (id,".implode(',', $campos).") VALUES (?,".implode(',', $ph).")");
            $stmt->execute(array_merge([$novoId], $vals));
            json(['ok' => true, 'id' => $novoId]);
        } catch (PDOException $e) { json(['ok' => false, 'msg' => 'Erro ao criar: '.$e->getMessage()]); }
        break;

    case 'atualizar_faturamento_backup':
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d  = jsonBody();
        // Ajuste/edição: liberado para todos os analistas também nos meses novos.
        $id = intval($d['id'] ?? 0); $tab = fb_tabela($d, true);
        if ($id <= 0) json(['ok' => false, 'msg' => 'ID inválido']);
        $campos = ['origem','ramo','seguradora','numero_contrato','cnpj','grupo','empresa','razao_social',
                   'analista_nome','competencia','competencia_real','data_emissao','data_vencimento',
                   'status_envio','data_envio','numero_vidas','premio_liquido','percentual_comissao',
                   'valor_previsto','comissao_recebida','valor_a_pagar','emails_envio','observacoes'];
        $set = []; $params = [];
        foreach ($campos as $c) {
            if (!array_key_exists($c, $d)) continue;
            $v = $d[$c];
            if ($v === '' || $v === '—') $v = null;
            // datas em branco viram NULL; numeros aceitam ponto decimal
            $set[] = "$c = ?";
            $params[] = $v;
        }
        if (!count($set)) json(['ok' => false, 'msg' => 'Nada para atualizar']);
        $params[] = $id;
        try {
            $stmt = db()->prepare("UPDATE $tab SET ".implode(', ', $set)." WHERE id = ?");
            $stmt->execute($params);
            json(['ok' => true, 'afetados' => $stmt->rowCount()]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao salvar backup: ' . $e->getMessage()]);
        }
        break;

    case 'atualizar_competencia_backup_lote':
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody(); $ids = $d['ids'] ?? []; $comp = trim($d['competencia'] ?? ''); $tab = fb_tabela($d, true);
        $ids = array_values(array_filter(array_map('intval', $ids), function($x){ return $x > 0; }));
        if (!count($ids)) json(['ok' => false, 'msg' => 'Nenhuma linha selecionada']);
        if ($comp === '') json(['ok' => false, 'msg' => 'Informe a competência']);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = db()->prepare("UPDATE $tab SET competencia_real = ? WHERE id IN ($ph)");
            $stmt->execute(array_merge([$comp], $ids));
            json(['ok' => true, 'afetados' => $stmt->rowCount()]);
        } catch (PDOException $e) { json(['ok' => false, 'msg' => 'Erro: '.$e->getMessage()]); }
        break;

    case 'atualizar_vencimento_backup_lote':
        // Altera o MÊS do vencimento das linhas selecionadas, mantendo o dia
        // (limitado ao último dia do mês destino). Linhas sem vencimento ficam como estão.
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody(); $ids = $d['ids'] ?? []; $am = trim($d['ano_mes'] ?? ''); $tab = fb_tabela($d, true);
        $ids = array_values(array_filter(array_map('intval', $ids), function($x){ return $x > 0; }));
        if (!count($ids)) json(['ok' => false, 'msg' => 'Nenhuma linha selecionada']);
        if (!preg_match('/^\d{4}-\d{2}$/', $am)) json(['ok' => false, 'msg' => 'Informe o mês do vencimento (AAAA-MM)']);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $primeiro = $am . '-01';
        try {
            $stmt = db()->prepare("UPDATE $tab
                SET data_vencimento = LEAST(
                        DATE_ADD(?, INTERVAL DAY(data_vencimento)-1 DAY),
                        LAST_DAY(?))
                WHERE id IN ($ph) AND data_vencimento IS NOT NULL");
            $stmt->execute(array_merge([$primeiro, $primeiro], $ids));
            json(['ok' => true, 'afetados' => $stmt->rowCount()]);
        } catch (PDOException $e) { json(['ok' => false, 'msg' => 'Erro: '.$e->getMessage()]); }
        break;

    case 'atualizar_status_backup_lote':
    case 'atualizar_analista_backup_lote':
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d   = jsonBody();
        $ids = $d['ids'] ?? []; $tab = fb_tabela($d, true);
        $ids = array_values(array_filter(array_map('intval', $ids), function($x){ return $x > 0; }));
        if (!count($ids)) json(['ok' => false, 'msg' => 'Nenhuma linha selecionada']);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'atualizar_status_backup_lote') {
            $status = trim($d['status'] ?? '');
            if ($status === '') json(['ok' => false, 'msg' => 'Escolha o status']);
            try {
                if ($status === 'Enviado') {
                    $stmt = db()->prepare("UPDATE $tab SET status_envio=?, data_envio=COALESCE(data_envio,CURDATE()) WHERE id IN ($ph)");
                } else {
                    $stmt = db()->prepare("UPDATE $tab SET status_envio=? WHERE id IN ($ph)");
                }
                $stmt->execute(array_merge([$status], $ids));
                json(['ok' => true, 'afetados' => $stmt->rowCount()]);
            } catch (PDOException $e) { json(['ok' => false, 'msg' => 'Erro: '.$e->getMessage()]); }
        } else {
            $novo = trim($d['analista_nome'] ?? '');
            if ($novo === '') json(['ok' => false, 'msg' => 'Escolha o analista']);
            try {
                $stmt = db()->prepare("UPDATE $tab SET analista_nome=? WHERE id IN ($ph)");
                $stmt->execute(array_merge([$novo], $ids));
                json(['ok' => true, 'afetados' => $stmt->rowCount()]);
            } catch (PDOException $e) { json(['ok' => false, 'msg' => 'Erro: '.$e->getMessage()]); }
        }
        break;

    case 'atualizar_analista_faturamento_lote':
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d    = jsonBody();
        $ids  = $d['ids'] ?? [];
        $novo = trim($d['analista_nome'] ?? '');
        if (!is_array($ids) || !count($ids)) json(['ok' => false, 'msg' => 'Nenhum faturamento selecionado']);
        if ($novo === '') json(['ok' => false, 'msg' => 'Informe o analista']);
        $ids = array_values(array_filter(array_map('intval', $ids), function($x){ return $x > 0; }));
        if (!count($ids)) json(['ok' => false, 'msg' => 'IDs inválidos']);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        try {
            // Só troca o analista (nome). analista_id fica NULL — a listagem usa o nome
            // e a FK aponta pra outra tabela (evita erro de chave estrangeira).
            $stmt = db()->prepare("UPDATE faturamento SET analista_nome = ?, analista_id = NULL WHERE id IN ($ph)");
            $stmt->execute(array_merge([$novo], $ids));
            json(['ok' => true, 'afetados' => $stmt->rowCount()]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao trocar analista em lote: ' . $e->getMessage()]);
        }
        break;

    case 'gerar_faturamento_carteira_backup':
        // Igual ao gerar_faturamento_carteira, mas grava no FATURAMENTO ATUAL (faturamento_backup).
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d       = jsonBody();
        $tab     = fb_tabela($d);
        $comp    = trim($d['competencia'] ?? '');
        $emissao = trim($d['data_emissao'] ?? '') ?: null;
        $venc    = trim($d['data_vencimento'] ?? '') ?: null;
        $grupoF  = trim($d['grupo'] ?? '');
        if ($comp === '') json(['ok' => false, 'msg' => 'Informe a competência']);
        try {
            $sqlE = "SELECT origem, ramo, seguradora, numero_contrato, cnpj, grupo, nome AS empresa,
                            analista_nome, percentual_comissao, emails_faturamento, data_emissao, data_vencimento
                     FROM empresas WHERE ativo = 1";
            $parE = [];
            if ($grupoF !== '') { $sqlE .= " AND (grupo LIKE ? OR nome LIKE ?)"; $parE[] = "%$grupoF%"; $parE[] = "%$grupoF%"; }
            $stE = db()->prepare($sqlE); $stE->execute($parE);
            $contratos = $stE->fetchAll(PDO::FETCH_ASSOC);

            $chk = db()->prepare("SELECT 1 FROM $tab
                WHERE cnpj <=> ? AND numero_contrato <=> ? AND empresa = ? AND competencia = ? LIMIT 1");
            $base = (int) db()->query("SELECT COALESCE(MAX(id),0) FROM $tab")->fetchColumn();
            $ins = db()->prepare("INSERT INTO $tab
                (id, origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa,
                 analista_nome, competencia, data_emissao, data_vencimento,
                 percentual_comissao, emails_envio, status_envio)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'Pendente emissão')");

            $criadas = 0; $puladas = 0;
            foreach ($contratos as $c) {
                $chk->execute([$c['cnpj'] ?? null, $c['numero_contrato'] ?? null, $c['empresa'], $comp]);
                if ($chk->fetchColumn()) { $puladas++; continue; }
                $base++;
                $ins->execute([
                    $base, $c['origem'] ?? null, $c['ramo'] ?? null, $c['seguradora'] ?? null,
                    $c['numero_contrato'] ?? null, $c['cnpj'] ?? null, $c['grupo'] ?? null, $c['empresa'],
                    $c['analista_nome'] ?? null, $comp,
                    $emissao ?: ($c['data_emissao'] ?? null), $venc ?: ($c['data_vencimento'] ?? null),
                    $c['percentual_comissao'] ?? null, $c['emails_faturamento'] ?? null
                ]);
                $criadas++;
            }
            json(['ok' => true, 'criadas' => $criadas, 'puladas' => $puladas, 'total' => count($contratos)]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao gerar faturamentos: ' . $e->getMessage()]);
        }
        break;

    case 'gerar_faturamento_carteira':
        // Cria faturamentos PENDENTES para uma competência a partir da carteira
        // (tabela empresas, ativas). Pula os contratos que já existem no mês.
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d       = jsonBody();
        $comp    = trim($d['competencia'] ?? '');
        $emissao = trim($d['data_emissao'] ?? '') ?: null;
        $venc    = trim($d['data_vencimento'] ?? '') ?: null;
        $grupoF  = trim($d['grupo'] ?? '');
        if ($comp === '') json(['ok' => false, 'msg' => 'Informe a competência']);
        try {
            $sqlE = "SELECT origem, ramo, seguradora, numero_contrato, cnpj, grupo, nome AS empresa,
                            analista_nome, percentual_comissao, emails_faturamento
                     FROM empresas WHERE ativo = 1";
            $parE = [];
            if ($grupoF !== '') { $sqlE .= " AND (grupo LIKE ? OR nome LIKE ?)"; $parE[] = "%$grupoF%"; $parE[] = "%$grupoF%"; }
            $stE = db()->prepare($sqlE);
            $stE->execute($parE);
            $contratos = $stE->fetchAll(PDO::FETCH_ASSOC);

            $chk = db()->prepare("SELECT 1 FROM faturamento
                WHERE cnpj <=> ? AND numero_contrato <=> ? AND empresa = ? AND competencia = ? LIMIT 1");
            $ins = db()->prepare("INSERT INTO faturamento
                (origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa,
                 analista_id, analista_nome, competencia, data_emissao, data_vencimento,
                 percentual_comissao, emails_envio, status_envio)
                VALUES (?,?,?,?,?,?,?, NULL, ?, ?, ?, ?, ?, ?, 'Pendente emissão')");

            $criadas = 0; $puladas = 0;
            foreach ($contratos as $c) {
                $chk->execute([$c['cnpj'] ?? null, $c['numero_contrato'] ?? null, $c['empresa'], $comp]);
                if ($chk->fetchColumn()) { $puladas++; continue; }
                $ins->execute([
                    $c['origem'] ?? null, $c['ramo'] ?? null, $c['seguradora'] ?? null,
                    $c['numero_contrato'] ?? null, $c['cnpj'] ?? null, $c['grupo'] ?? null, $c['empresa'],
                    $c['analista_nome'] ?? null, $comp, $emissao, $venc,
                    $c['percentual_comissao'] ?? null, $c['emails_faturamento'] ?? null
                ]);
                $criadas++;
            }
            json(['ok' => true, 'criadas' => $criadas, 'puladas' => $puladas, 'total' => count($contratos)]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao gerar faturamentos: ' . $e->getMessage()]);
        }
        break;

    case 'listar_carteira_faturamento':
        // Prévia (somente leitura): lista os contratos da carteira que seriam
        // gerados para a competência, marcando quais já existem no mês.
        if (isVisualizador()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $comp   = trim($_GET['competencia'] ?? '');
        $grupoF = trim($_GET['grupo'] ?? '');
        try {
            $sqlE = "SELECT origem, ramo, seguradora, numero_contrato, cnpj, grupo, nome AS empresa,
                            analista_nome, percentual_comissao
                     FROM empresas WHERE ativo = 1";
            $parE = [];
            if ($grupoF !== '') { $sqlE .= " AND (grupo LIKE ? OR nome LIKE ?)"; $parE[] = "%$grupoF%"; $parE[] = "%$grupoF%"; }
            $sqlE .= " ORDER BY grupo, nome";
            $stE = db()->prepare($sqlE);
            $stE->execute($parE);
            $contratos = $stE->fetchAll(PDO::FETCH_ASSOC);

            if ($comp !== '') {
                $chk = db()->prepare("SELECT 1 FROM faturamento
                    WHERE cnpj <=> ? AND numero_contrato <=> ? AND empresa = ? AND competencia = ? LIMIT 1");
                foreach ($contratos as &$c) {
                    $chk->execute([$c['cnpj'] ?? null, $c['numero_contrato'] ?? null, $c['empresa'], $comp]);
                    $c['ja_existe'] = $chk->fetchColumn() ? 1 : 0;
                }
                unset($c);
            }
            json(['ok' => true, 'data' => $contratos, 'total' => count($contratos)]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao listar carteira: ' . $e->getMessage()]);
        }
        break;

    case 'editar_faturamento':
        $d  = jsonBody();
        $id = intval($d['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);

        // Garante a coluna valor_a_pagar (migração automática, idempotente).
        try { db()->exec("ALTER TABLE faturamento ADD COLUMN valor_a_pagar DECIMAL(15,2) NULL"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE faturamento ADD COLUMN competencia_real VARCHAR(20) NULL"); } catch (Throwable $e) {}

        // Financeiro só pode atualizar `comissao_recebida` e/ou `valor_a_pagar`. Trava o resto.
        if (isFinanceiro()) {
            try {
                $sets = []; $params = [];
                if (array_key_exists('comissao_recebida', $d)) {
                    $v = $d['comissao_recebida'];
                    $sets[] = 'comissao_recebida = ?';
                    $params[] = ($v === '' || $v === null) ? null : floatval(str_replace(',', '.', $v));
                }
                if (array_key_exists('valor_a_pagar', $d)) {
                    $v = $d['valor_a_pagar'];
                    $sets[] = 'valor_a_pagar = ?';
                    $params[] = ($v === '' || $v === null) ? null : floatval(str_replace(',', '.', $v));
                }
                if (!$sets) { json(['ok' => true, 'modo' => 'financeiro']); }
                $params[] = $id;
                $st = db()->prepare("UPDATE faturamento SET " . implode(', ', $sets) . " WHERE id = ?");
                $st->execute($params);
                json(['ok' => true, 'modo' => 'financeiro']);
            } catch (PDOException $e) {
                json(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
            }
            break;
        }

        $origem       = trim($d['origem']          ?? '');
        $ramo         = trim($d['ramo']            ?? '');
        $seguradora   = trim($d['seguradora']      ?? '');
        $num_contrato = trim($d['numero_contrato'] ?? '');
        $cnpj         = trim($d['cnpj']            ?? '');
        $grupo        = trim($d['grupo']           ?? '');
        $empresa      = trim($d['empresa']         ?? '');
        $razao_social = trim($d['razao_social']    ?? '');
        $analista_id  = intval($d['analista_id']   ?? 0) ?: null;
        $analista_nm  = trim($d['analista_nome']   ?? '');
        $competencia  = trim($d['competencia']     ?? '');
        $competenciaReal = trim($d['competencia_real'] ?? '');
        $data_emissao = $d['data_emissao'] ?? null;
        $data_venc    = $d['data_vencimento'] ?? null;
        $status       = trim($d['status_envio']    ?? 'Pendente emissão');
        $data_envio   = $d['data_envio'] ?: null;
        $emails       = trim($d['emails_envio']    ?? '');
        $vidas        = intval($d['numero_vidas']  ?? 0);
        $premio       = floatval(str_replace(',', '.', $d['premio_liquido'] ?? 0));
        $perc         = floatval(str_replace(',', '.', $d['percentual_comissao'] ?? 0));
        $valor_prev   = round($premio * $perc / 100, 2);
        $com_rec_val  = $d['comissao_recebida'] ?? '';
        $com_rec      = ($com_rec_val === '' || $com_rec_val === null) ? null : floatval(str_replace(',', '.', $com_rec_val));
        $obs          = trim($d['observacoes']     ?? '');

        // Valor a pagar (financeiro): só sobrescreve se veio no payload; senão preserva o atual.
        if (array_key_exists('valor_a_pagar', $d)) {
            $vp_val = $d['valor_a_pagar'];
            $vpag   = ($vp_val === '' || $vp_val === null) ? null : floatval(str_replace(',', '.', $vp_val));
        } else {
            $cur = db()->prepare("SELECT valor_a_pagar FROM faturamento WHERE id = ?");
            $cur->execute([$id]);
            $vpag = $cur->fetchColumn();
            if ($vpag === false) $vpag = null;
        }

        // Competência (campo novo): só sobrescreve se veio no payload; senão preserva o atual.
        if (!array_key_exists('competencia_real', $d)) {
            $curCR = db()->prepare("SELECT competencia_real FROM faturamento WHERE id = ?");
            $curCR->execute([$id]);
            $crCur = $curCR->fetchColumn();
            $competenciaReal = ($crCur === false || $crCur === null) ? '' : (string)$crCur;
        }

        if ($cnpj !== '') {
            if (!validarCNPJ($cnpj)) json(['ok' => false, 'msg' => 'CNPJ inválido']);
            $cnpj = formatarCNPJ($cnpj);
        }

        try {
            // Desliga FK_CHECKS pra evitar barramento por FK legado
            // (analista_id->usuarios) já que o cadastro real é em `analistas`
            db()->exec("SET FOREIGN_KEY_CHECKS = 0");
            $stmt = db()->prepare("
                UPDATE faturamento SET
                    origem = ?, ramo = ?, seguradora = ?, numero_contrato = ?, cnpj = ?,
                    grupo = ?, empresa = ?, razao_social = ?, analista_id = ?, analista_nome = ?,
                    competencia = ?, competencia_real = ?, data_emissao = ?, data_vencimento = ?,
                    status_envio = ?, data_envio = ?, emails_envio = ?, numero_vidas = ?,
                    premio_liquido = ?, percentual_comissao = ?, valor_previsto = ?,
                    comissao_recebida = ?, valor_a_pagar = ?, observacoes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $origem, $ramo, $seguradora, $num_contrato, $cnpj,
                $grupo, $empresa, $razao_social, $analista_id, $analista_nm,
                $competencia, $competenciaReal, $data_emissao, $data_venc,
                $status, $data_envio, $emails, $vidas,
                $premio, $perc, $valor_prev,
                $com_rec, $vpag, $obs,
                $id
            ]);
            db()->exec("SET FOREIGN_KEY_CHECKS = 1");
        } catch (PDOException $e) {
            db()->exec("SET FOREIGN_KEY_CHECKS = 1");
            json(['ok' => false, 'msg' => 'Erro ao salvar faturamento: ' . $e->getMessage()]);
        }

        json(['ok' => true]);
        break;

    case 'editar_faturamento_mensal':
        // Edição rápida mensal: apenas campos editáveis mensalmente + status + comissão recebida
        $d  = jsonBody();
        $id = intval($d['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);

        // Busca registro atual para recálculo
        $cur = db()->prepare("SELECT percentual_comissao FROM faturamento WHERE id = ?");
        $cur->execute([$id]);
        $curData = $cur->fetch();
        if (!$curData) json(['ok' => false, 'msg' => 'Registro não encontrado']);

        $perc   = floatval($curData['percentual_comissao']);
        $premio = floatval(str_replace(',', '.', $d['premio_liquido'] ?? 0));
        $vidas  = intval($d['numero_vidas'] ?? 0);
        $valor_prev = round($premio * $perc / 100, 2);
        $status     = trim($d['status_envio'] ?? 'Pendente emissão');
        $data_envio = $d['data_envio'] ?: null;
        $com_rec_val= $d['comissao_recebida'] ?? '';
        $com_rec    = ($com_rec_val === '' || $com_rec_val === null) ? null : floatval(str_replace(',', '.', $com_rec_val));

        $stmt = db()->prepare("
            UPDATE faturamento SET
                numero_vidas = ?, premio_liquido = ?, valor_previsto = ?,
                status_envio = ?, data_envio = ?, comissao_recebida = ?
            WHERE id = ?
        ");
        $stmt->execute([$vidas, $premio, $valor_prev, $status, $data_envio, $com_rec, $id]);

        json(['ok' => true]);
        break;

    // ═══════════════════════════════════════════════════
    //  IMPORTAR FECHAMENTO MENSAL — atualiza vidas/prêmio/status=Enviado
    //  em lote, restrito à competência alvo (proteção contra afetar outros meses).
    // ═══════════════════════════════════════════════════
    case 'importar_fechamento_mensal':
        if (isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody();
        $competencia = trim($d['competencia'] ?? '');
        $modo        = trim($d['modo'] ?? 'sobrescrever'); // 'sobrescrever' | 'completar'
        $updates     = $d['updates'] ?? [];

        if (!$competencia) json(['ok' => false, 'msg' => 'Competência alvo obrigatória']);
        if (!is_array($updates) || !count($updates)) json(['ok' => false, 'msg' => 'Sem linhas para aplicar']);

        // SQL: WHERE fixo na competência impede afetar outros meses, mesmo que id venha errado.
        $sql = $modo === 'completar'
            ? "UPDATE faturamento SET
                   numero_vidas    = COALESCE(numero_vidas, ?),
                   premio_liquido  = COALESCE(premio_liquido, ?),
                   data_envio      = COALESCE(data_envio, ?),
                   status_envio    = 'Enviado',
                   valor_previsto  = CASE
                                        WHEN premio_liquido IS NULL AND ? IS NOT NULL AND percentual_comissao IS NOT NULL
                                        THEN ROUND(? * percentual_comissao / 100, 2)
                                        ELSE valor_previsto
                                     END
               WHERE id = ? AND competencia = ?"
            : "UPDATE faturamento SET
                   numero_vidas    = ?,
                   premio_liquido  = ?,
                   data_envio      = ?,
                   status_envio    = 'Enviado',
                   valor_previsto  = CASE
                                        WHEN ? IS NOT NULL AND percentual_comissao IS NOT NULL
                                        THEN ROUND(? * percentual_comissao / 100, 2)
                                        ELSE valor_previsto
                                     END
               WHERE id = ? AND competencia = ?";

        $stmt = db()->prepare($sql);
        $atualizados = 0;
        $erros = [];
        foreach ($updates as $u) {
            $id = intval($u['id'] ?? 0);
            if (!$id) continue;
            $vidas  = isset($u['vidas'])  && $u['vidas']  !== '' && $u['vidas']  !== null ? intval($u['vidas'])      : null;
            $premio = isset($u['premio']) && $u['premio'] !== '' && $u['premio'] !== null ? floatval($u['premio']) : null;
            $dataEnv = !empty($u['data_envio']) ? $u['data_envio'] : null;

            try {
                $stmt->execute([$vidas, $premio, $dataEnv, $premio, $premio, $id, $competencia]);
                if ($stmt->rowCount() > 0) $atualizados++;
            } catch (PDOException $e) {
                $erros[] = "ID $id: " . $e->getMessage();
            }
        }

        json([
            'ok' => true,
            'atualizados' => $atualizados,
            'tentativas' => count($updates),
            'erros' => $erros
        ]);
        break;

    case 'excluir_faturamento':
        if (isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado: financeiro não pode excluir faturamento']);
        $id = intval($_GET['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);
        // Analista pode excluir, mas só o que está na carteira dele.
        // Admin exclui qualquer um.
        if (!isAdmin()) {
            $st = db()->prepare("SELECT analista_nome FROM faturamento WHERE id = ?");
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) json(['ok' => false, 'msg' => 'Faturamento não encontrado']);
            if (($row['analista_nome'] ?? '') !== ($_SESSION['user_nome'] ?? '')) {
                json(['ok' => false, 'msg' => 'Você só pode excluir faturamentos da sua carteira']);
            }
        }
        db()->prepare("DELETE FROM faturamento WHERE id = ?")->execute([$id]);
        json(['ok' => true]);
        break;

    // ═══════════════════════════════════════════════════
    //  VIRADA DE COMPETÊNCIA (duplicar mês)
    // ═══════════════════════════════════════════════════

    case 'virar_competencia':
        adminOnly();
        $d = jsonBody();
        $de   = trim($d['de']   ?? '');   // ex.: "Abr/26"
        $para = trim($d['para'] ?? '');   // ex.: "Mai/26"

        if (!$de || !$para) {
            json(['ok' => false, 'msg' => 'Informe a competência de origem e a de destino']);
        }
        if ($de === $para) {
            json(['ok' => false, 'msg' => 'Origem e destino não podem ser iguais']);
        }

        // Conta o que já existe no destino (pra avisar no fim — não bloqueia mais)
        $stChk = db()->prepare("SELECT COUNT(*) FROM faturamento WHERE competencia = ?");
        $stChk->execute([$para]);
        $jaExistentes = intval($stChk->fetchColumn());

        // Calcula diferença de meses entre origem e destino pra rotacionar as datas
        $mesesMap = ['Jan'=>1,'Fev'=>2,'Mar'=>3,'Abr'=>4,'Mai'=>5,'Jun'=>6,'Jul'=>7,'Ago'=>8,'Set'=>9,'Out'=>10,'Nov'=>11,'Dez'=>12];
        $monthsDiff = 1; // fallback default: 1 mês
        if (preg_match('/^(\w{3})\/(\d{2})$/', $de, $mDe) && preg_match('/^(\w{3})\/(\d{2})$/', $para, $mPara)) {
            $deMonth   = $mesesMap[$mDe[1]]   ?? null;
            $paraMonth = $mesesMap[$mPara[1]] ?? null;
            if ($deMonth && $paraMonth) {
                $deYear   = 2000 + intval($mDe[2]);
                $paraYear = 2000 + intval($mPara[2]);
                $monthsDiff = ($paraYear - $deYear) * 12 + ($paraMonth - $deMonth);
            }
        }

        // Duplica todas as linhas da competência de origem para a de destino.
        // data_emissao e data_vencimento são deslocadas pelo número de meses
        // entre as competências (preservando o dia). Os campos que mudam por
        // mês (vidas, prêmio, status, data_envio, comissão recebida) ficam NULL.
        // ── Detecta se a coluna `razao_social` existe (alguns bancos legado
        //  podem não ter — evita crash ao virar competência).
        $temRazao = false;
        try {
            $temRazao = (bool) db()->query("SHOW COLUMNS FROM faturamento LIKE 'razao_social'")->fetch();
        } catch (PDOException $e) { $temRazao = false; }

        try {
            db()->exec("SET FOREIGN_KEY_CHECKS = 0");

            // Datas: desloca data_emissao/vencimento da linha de ORIGEM pelo
            // número de meses entre as competências (preserva o dia).
            // Se a linha de origem estiver com data NULL, o destino fica NULL
            // ("Pendente emissão") em vez de inventar uma data errada a partir
            // do cadastro — isso evitava o bug que gravava emissão 07/07.
            // Sem LEFT JOIN empresas: além de não ser mais necessário, o join
            // podia casar mais de uma empresa e DUPLICAR linhas na virada.
            if ($temRazao) {
                $sql = "INSERT INTO faturamento
                    (origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa, razao_social,
                     analista_id, analista_nome, competencia,
                     data_emissao, data_vencimento, status_envio, data_envio, emails_envio,
                     numero_vidas, premio_liquido, percentual_comissao, valor_previsto,
                     comissao_recebida, observacoes)
                    SELECT f.origem, f.ramo, f.seguradora, f.numero_contrato, f.cnpj, f.grupo, f.empresa, f.razao_social,
                     f.analista_id, f.analista_nome, ?,
                     DATE_ADD(f.data_emissao,    INTERVAL ? MONTH),
                     DATE_ADD(f.data_vencimento, INTERVAL ? MONTH),
                     'Pendente emissão', NULL, f.emails_envio,
                     NULL, NULL, f.percentual_comissao, NULL,
                     NULL, f.observacoes
                    FROM faturamento f
                    WHERE f.competencia = ?
                      AND NOT EXISTS (
                        SELECT 1 FROM faturamento f2
                        WHERE f2.competencia = ?
                          AND IFNULL(f2.cnpj,'')             = IFNULL(f.cnpj,'')
                          AND IFNULL(f2.numero_contrato,'')  = IFNULL(f.numero_contrato,'')
                          AND IFNULL(f2.empresa,'')          = IFNULL(f.empresa,'')
                      )";
            } else {
                $sql = "INSERT INTO faturamento
                    (origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa,
                     analista_id, analista_nome, competencia,
                     data_emissao, data_vencimento, status_envio, data_envio, emails_envio,
                     numero_vidas, premio_liquido, percentual_comissao, valor_previsto,
                     comissao_recebida, observacoes)
                    SELECT f.origem, f.ramo, f.seguradora, f.numero_contrato, f.cnpj, f.grupo, f.empresa,
                     f.analista_id, f.analista_nome, ?,
                     DATE_ADD(f.data_emissao,    INTERVAL ? MONTH),
                     DATE_ADD(f.data_vencimento, INTERVAL ? MONTH),
                     'Pendente emissão', NULL, f.emails_envio,
                     NULL, NULL, f.percentual_comissao, NULL,
                     NULL, f.observacoes
                    FROM faturamento f
                    WHERE f.competencia = ?
                      AND NOT EXISTS (
                        SELECT 1 FROM faturamento f2
                        WHERE f2.competencia = ?
                          AND IFNULL(f2.cnpj,'')             = IFNULL(f.cnpj,'')
                          AND IFNULL(f2.numero_contrato,'')  = IFNULL(f.numero_contrato,'')
                          AND IFNULL(f2.empresa,'')          = IFNULL(f.empresa,'')
                      )";
            }

            $stmt = db()->prepare($sql);
            // Passa o $para 2x: 1) para o campo competencia do INSERT; 2) para o NOT EXISTS
            $stmt->execute([$para, $monthsDiff, $monthsDiff, $de, $para]);
            $inseridos = $stmt->rowCount();

            db()->exec("SET FOREIGN_KEY_CHECKS = 1");
        } catch (PDOException $e) {
            db()->exec("SET FOREIGN_KEY_CHECKS = 1");
            json(['ok' => false, 'msg' => 'Erro ao virar competência: ' . $e->getMessage()]);
        }

        // Monta a mensagem dependendo se havia (ou não) registros prévios no destino
        if ($jaExistentes > 0) {
            $msg = "Virada concluída: $inseridos novas linhas duplicadas de $de para $para. "
                 . "($jaExistentes já existiam no destino e foram preservadas — não duplicou.)";
        } else {
            $msg = "Virada concluída: $inseridos linhas duplicadas de $de para $para.";
        }
        json(['ok' => true, 'msg' => $msg, 'inseridos' => $inseridos, 'ja_existentes' => $jaExistentes]);
        break;

    case 'virar_competencia_backup':
        // Vira o mês no FATURAMENTO ATUAL (faturamento_backup): clona as linhas de
        // uma competência (mês de envio) para a próxima, deslocando as datas e
        // zerando vidas/prêmio/status/envio. Não duplica o que já existe no destino.
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody();
        $de   = trim($d['de']   ?? '');
        $para = trim($d['para'] ?? '');
        if (!$de || !$para) json(['ok' => false, 'msg' => 'Informe a competência de origem e a de destino']);
        if ($de === $para)  json(['ok' => false, 'msg' => 'Origem e destino não podem ser iguais']);

        $stChk = db()->prepare("SELECT COUNT(*) FROM faturamento_backup WHERE competencia = ?");
        $stChk->execute([$para]); $jaExistentes = intval($stChk->fetchColumn());

        $mesesMap = ['Jan'=>1,'Fev'=>2,'Mar'=>3,'Abr'=>4,'Mai'=>5,'Jun'=>6,'Jul'=>7,'Ago'=>8,'Set'=>9,'Out'=>10,'Nov'=>11,'Dez'=>12];
        $monthsDiff = 1;
        if (preg_match('/^(\w{3})\/(\d{2})$/', $de, $mDe) && preg_match('/^(\w{3})\/(\d{2})$/', $para, $mPara)) {
            $dm = $mesesMap[$mDe[1]] ?? null; $pm = $mesesMap[$mPara[1]] ?? null;
            if ($dm && $pm) { $monthsDiff = ((2000+intval($mPara[2])) - (2000+intval($mDe[2]))) * 12 + ($pm - $dm); }
        }
        try {
            db()->exec("SET @rn := (SELECT COALESCE(MAX(id),0) FROM faturamento_backup)");
            $sql = "INSERT INTO faturamento_backup
                (id, origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa, razao_social,
                 analista_nome, competencia, data_emissao, data_vencimento, status_envio, data_envio,
                 emails_envio, numero_vidas, premio_liquido, percentual_comissao, valor_previsto,
                 comissao_recebida, observacoes)
                SELECT (@rn := @rn + 1),
                 f.origem, f.ramo, f.seguradora, f.numero_contrato, f.cnpj, f.grupo, f.empresa, f.razao_social,
                 f.analista_nome, ?, DATE_ADD(f.data_emissao, INTERVAL ? MONTH), DATE_ADD(f.data_vencimento, INTERVAL ? MONTH),
                 'Pendente emissão', NULL,
                 f.emails_envio, NULL, NULL, f.percentual_comissao, NULL,
                 NULL, f.observacoes
                FROM faturamento_backup f
                WHERE f.competencia = ?
                  AND NOT EXISTS (
                    SELECT 1 FROM faturamento_backup f2
                    WHERE f2.competencia = ?
                      AND IFNULL(f2.cnpj,'')            = IFNULL(f.cnpj,'')
                      AND IFNULL(f2.numero_contrato,'') = IFNULL(f.numero_contrato,'')
                      AND IFNULL(f2.empresa,'')         = IFNULL(f.empresa,'')
                  )";
            $stmt = db()->prepare($sql);
            $stmt->execute([$para, $monthsDiff, $monthsDiff, $de, $para]);
            $inseridos = $stmt->rowCount();
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao virar competência: ' . $e->getMessage()]);
        }
        $msg = $jaExistentes > 0
            ? "Virada concluída: $inseridos novas linhas de $de para $para. ($jaExistentes já existiam no destino e foram preservadas.)"
            : "Virada concluída: $inseridos linhas de $de para $para.";
        json(['ok' => true, 'msg' => $msg, 'inseridos' => $inseridos, 'ja_existentes' => $jaExistentes]);
        break;

    case 'virar_para_outubro':
        // Vira o mês do FATURAMENTO ATUAL (faturamento_backup) para o FATURAMENTO
        // OUTUBRO (tabela espelho). Regra do "virar mês": mantém contrato/empresa/
        // CNPJ/analista/% comissão/observações e ZERA vidas, prêmio, valores e
        // comissão, com status "Pendente emissão" (começa em branco pra preencher).
        // Exige o destino VAZIO para não duplicar.
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        if (!fb_pode_alterar_outubro()) json(['ok' => false, 'msg' => 'Apenas a Letícia pode alterar o Faturamento Outubro.']);
        $d  = jsonBody();
        $de = trim($d['de'] ?? ''); // competência de origem (opcional; vazio = todas)
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS faturamento_outubro LIKE faturamento_backup");
            $jaTem = (int) db()->query("SELECT COUNT(*) FROM faturamento_outubro")->fetchColumn();
            if ($jaTem > 0) {
                json(['ok' => false, 'msg' => "O Faturamento Outubro já tem $jaTem linha(s). Esvazie-o antes de virar o mês (para não duplicar)."]);
            }
            $compOut = 'Out/' . date('Y'); // ex.: Out/2026 (mês de envio)
            db()->exec("SET @rn := (SELECT COALESCE(MAX(id),0) FROM faturamento_outubro)");
            // Carrega: analista, emissão, vencimento, vidas e % comissão.
            // Zera: prêmio, valor previsto (comissão) e comissão recebida. Status pendente.
            $sql = "INSERT INTO faturamento_outubro
                (id, origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa, razao_social,
                 analista_nome, competencia, competencia_real, data_emissao, data_vencimento,
                 status_envio, data_envio, emails_envio, numero_vidas, premio_liquido,
                 percentual_comissao, valor_previsto, comissao_recebida, observacoes)
                SELECT (@rn := @rn + 1),
                 f.origem, f.ramo, f.seguradora, f.numero_contrato, f.cnpj, f.grupo, f.empresa, f.razao_social,
                 f.analista_nome, ?,
                 NULL,
                 DATE_ADD(f.data_emissao,    INTERVAL 1 MONTH),
                 DATE_ADD(f.data_vencimento, INTERVAL 1 MONTH),
                 'Pendente emissão', NULL, f.emails_envio, NULL, NULL,
                 f.percentual_comissao, NULL, NULL, f.observacoes
                FROM faturamento_backup f";
            $params = [$compOut];
            if ($de !== '') { $sql .= " WHERE f.competencia = ?"; $params[] = $de; }
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $inseridos = $stmt->rowCount();
            json(['ok' => true, 'inseridos' => $inseridos, 'msg' => "Mês virado: $inseridos contrato(s) levados para o Faturamento Outubro (vidas, prêmio e valores em branco)."]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao virar o mês: ' . $e->getMessage()]);
        }
        break;

    case 'virar_para_novembro':
        // Vira o mês do FATURAMENTO OUTUBRO para o FATURAMENTO NOVEMBRO.
        // Mesma regra: mantém contrato/analista/% comissão, emissão/vencimento +1 mês,
        // vidas e valores em branco, status "Pendente emissão". Destino precisa estar vazio.
        if (isVisualizador() || isFinanceiro()) json(['ok' => false, 'msg' => 'Acesso negado']);
        if (!fb_pode_alterar_outubro()) json(['ok' => false, 'msg' => 'Apenas a Letícia pode alterar este faturamento.']);
        $d  = jsonBody();
        $de = trim($d['de'] ?? ''); // competência de origem (opcional; vazio = todas)
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS faturamento_novembro LIKE faturamento_backup");
            db()->exec("CREATE TABLE IF NOT EXISTS faturamento_outubro LIKE faturamento_backup");
            $jaTem = (int) db()->query("SELECT COUNT(*) FROM faturamento_novembro")->fetchColumn();
            if ($jaTem > 0) {
                json(['ok' => false, 'msg' => "O Faturamento Novembro já tem $jaTem linha(s). Esvazie-o antes de virar o mês (para não duplicar)."]);
            }
            $compNov = 'Nov/' . date('Y'); // ex.: Nov/2026
            db()->exec("SET @rn := (SELECT COALESCE(MAX(id),0) FROM faturamento_novembro)");
            $sql = "INSERT INTO faturamento_novembro
                (id, origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa, razao_social,
                 analista_nome, competencia, competencia_real, data_emissao, data_vencimento,
                 status_envio, data_envio, emails_envio, numero_vidas, premio_liquido,
                 percentual_comissao, valor_previsto, comissao_recebida, observacoes)
                SELECT (@rn := @rn + 1),
                 f.origem, f.ramo, f.seguradora, f.numero_contrato, f.cnpj, f.grupo, f.empresa, f.razao_social,
                 f.analista_nome, ?,
                 NULL,
                 DATE_ADD(f.data_emissao,    INTERVAL 1 MONTH),
                 DATE_ADD(f.data_vencimento, INTERVAL 1 MONTH),
                 'Pendente emissão', NULL, f.emails_envio, NULL, NULL,
                 f.percentual_comissao, NULL, NULL, f.observacoes
                FROM faturamento_outubro f";
            $params = [$compNov];
            if ($de !== '') { $sql .= " WHERE f.competencia = ?"; $params[] = $de; }
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $inseridos = $stmt->rowCount();
            json(['ok' => true, 'inseridos' => $inseridos, 'msg' => "Mês virado: $inseridos contrato(s) levados para o Faturamento Novembro (vidas, prêmio e valores em branco)."]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao virar o mês: ' . $e->getMessage()]);
        }
        break;


    // ═══════════════════════════════════════════════════
    //  STATS FATURAMENTO (cards + resumo grupo/seguradora)
    // ═══════════════════════════════════════════════════
    case 'stats_faturamento':
        $competencia = trim($_GET['competencia'] ?? '');
        $todos = isset($_GET['todos']) && $_GET['todos'] === '1';
        // Default: mês vigente (a não ser que o cliente peça explicitamente "todos")
        if (!$competencia && !$todos) {
            $competencia = date('Y-m');
        }
        if ($competencia && preg_match('/^(\d{4})-(\d{2})$/', $competencia, $m)) {
            $meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
            $idx = intval($m[2]) - 1;
            if ($idx >= 0 && $idx < 12) $competencia = $meses[$idx] . '/' . substr($m[1], -2);
        }

        $whereComp  = '';
        $paramsComp = [];
        $whereCarteira = '';
        // Admin, Financeiro e Visualizador veem tudo. Analista vê só a carteira dele.
        if (!isAdmin() && !isFinanceiro() && !isVisualizador()) {
            $whereCarteira = " AND analista_nome = ?";
            $paramsComp[] = $_SESSION['user_nome'] ?? '';
        }
        if ($competencia) {
            $whereComp = " AND competencia = ?";
            $paramsComp[] = $competencia;
        }
        $where = "WHERE 1=1" . $whereCarteira . $whereComp;

        try {
            $st = db()->prepare("SELECT COUNT(*) AS qtd,
                                        COALESCE(SUM(premio_liquido),0)        AS total_premio,
                                        COALESCE(SUM(valor_previsto),0)        AS total_previsto,
                                        COALESCE(SUM(comissao_recebida),0)     AS total_recebido,
                                        SUM(CASE WHEN data_emissao = CURDATE() AND status_envio != 'Enviado' THEN 1 ELSE 0 END) AS emitir_hoje,
                                        SUM(CASE WHEN status_envio IN ('Pendente emissão','Aguardando envio','Em análise') THEN 1 ELSE 0 END) AS aguardando,
                                        SUM(CASE WHEN status_envio = 'Enviado' THEN 1 ELSE 0 END) AS enviados
                                 FROM faturamento $where");
            $st->execute($paramsComp);
            $base = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            $stG = db()->prepare("SELECT grupo,
                                         COUNT(*) AS qtd,
                                         COALESCE(SUM(premio_liquido),0)    AS premio,
                                         COALESCE(SUM(valor_previsto),0)    AS previsto,
                                         COALESCE(SUM(comissao_recebida),0) AS recebido
                                  FROM faturamento $where
                                  GROUP BY grupo
                                  ORDER BY premio DESC");
            $stG->execute($paramsComp);
            $porGrupo = $stG->fetchAll(PDO::FETCH_ASSOC);

            $stS = db()->prepare("SELECT seguradora,
                                         COUNT(*) AS qtd,
                                         COALESCE(SUM(premio_liquido),0)    AS premio,
                                         COALESCE(SUM(valor_previsto),0)    AS previsto,
                                         COALESCE(SUM(comissao_recebida),0) AS recebido
                                  FROM faturamento $where
                                  GROUP BY seguradora
                                  ORDER BY premio DESC");
            $stS->execute($paramsComp);
            $porSeg = $stS->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $base = []; $porGrupo = []; $porSeg = [];
        }

        json(['ok' => true, 'data' => [
            'total'           => intval($base['qtd'] ?? 0),
            'emitir_hoje'     => intval($base['emitir_hoje'] ?? 0),
            'aguardando'      => intval($base['aguardando'] ?? 0),
            'enviados'        => intval($base['enviados'] ?? 0),
            'total_premio'    => floatval($base['total_premio'] ?? 0),
            'total_previsto'  => floatval($base['total_previsto'] ?? 0),
            'total_recebido'  => floatval($base['total_recebido'] ?? 0),
            'competencia'     => $competencia ?: null,  // "Mai/26" ou null se "todos"
            'por_grupo'       => $porGrupo,
            'por_seguradora'  => $porSeg
        ]]);
        break;

    // ═══════════════════════════════════════════════════
    //  LISTAR / STATS RENOVAÇÃO
    // ═══════════════════════════════════════════════════
    case 'listar_renovacao':
        // Schema novo: renovacao só guarda dados do ciclo + empresa_id (FK pra empresas).
        // Dados da empresa (origem, ramo, seguradora, cnpj, nome, etc.) vêm via JOIN.
        // Aliases mantidos pra compat com o frontend antigo (status_renovacao, empresa).
        $sql = "
            SELECT
                r.*,
                r.status AS status_renovacao,
                r.perc_proposto AS percentual_proposto,
                r.perc_finalizado AS percentual_final,
                r.premio_reajuste AS premio_reajustado,
                r.observacoes AS observacao,
                COALESCE(r.origem_ciclo, e.origem) AS origem,
                COALESCE(r.ramo, e.ramo) AS ramo,
                COALESCE(r.seguradora, e.seguradora) AS seguradora,
                e.ramo AS empresa_ramo,
                e.seguradora AS empresa_seguradora,
                -- Contrato: NULL no ciclo = herda da empresa; '' = vazio intencional
                CASE WHEN r.numero_contrato_ciclo IS NOT NULL
                     THEN r.numero_contrato_ciclo
                     ELSE e.numero_contrato END AS numero_contrato,
                e.cnpj,
                e.grupo, e.nome AS empresa, e.razao_social,
                COALESCE(NULLIF(r.porte_ciclo, ''), e.porte) AS porte_empresa,
                e.porte,
                COALESCE(r.numero_vidas_ciclo, e.numero_vidas) AS numero_vidas,
                -- % comissão: NULL no ciclo = herda da empresa; '' = vazio intencional
                CASE WHEN r.perc_comissao_ciclo IS NOT NULL
                     THEN r.perc_comissao_ciclo
                     ELSE e.percentual_comissao END AS percentual_comissao,
                COALESCE(r.analista_id_ciclo, e.analista_id) AS analista_id,
                CASE WHEN r.analista_nome_ciclo IS NOT NULL
                     THEN r.analista_nome_ciclo
                     ELSE e.analista_nome END AS analista_nome,
                CASE WHEN r.inicio_vigencia_ciclo IS NOT NULL
                     THEN r.inicio_vigencia_ciclo
                     ELSE e.inicio_vigencia END AS inicio_vigencia,
                e.resp_nome, e.resp_email, e.resp_telefone
            FROM renovacao r
            INNER JOIN empresas e ON e.id = r.empresa_id
            WHERE 1=1
        ";
        $params = [];

        if (!iw_ve_tudo()) {
            $sql .= " AND e.analista_nome = ?";
            $params[] = $_SESSION['user_nome'] ?? '';
        }

        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $like = "%{$search}%";
            $sql .= " AND (e.nome LIKE ? OR e.grupo LIKE ? OR e.cnpj LIKE ? OR e.numero_contrato LIKE ? OR e.resp_nome LIKE ?)";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        // Filtros que vão pra empresas (origem e grupo só existem na empresa)
        foreach (['origem','grupo'] as $f) {
            $v = trim($_GET[$f] ?? '');
            if ($v) { $sql .= " AND e.$f = ?"; $params[] = $v; }
        }
        // Filtros de ramo/seguradora consideram override em renovacao via COALESCE
        foreach (['ramo','seguradora'] as $f) {
            $v = trim($_GET[$f] ?? '');
            if ($v) { $sql .= " AND COALESCE(r.$f, e.$f) = ?"; $params[] = $v; }
        }
        // Filtros que vão pra renovacao
        foreach (['status','tipo','aditivo'] as $f) {
            $v = trim($_GET[$f] ?? '');
            if ($v) { $sql .= " AND r.$f = ?"; $params[] = $v; }
        }
        $analista = trim($_GET['analista'] ?? '');
        if ($analista && isAdmin()) { $sql .= " AND e.analista_nome = ?"; $params[] = $analista; }

        $mes = trim($_GET['mes'] ?? '');
        if ($mes && preg_match('/^(\d{4})-(\d{2})$/', $mes, $mm)) {
            $sql .= " AND YEAR(r.mes_renovacao) = ? AND MONTH(r.mes_renovacao) = ?";
            $params[] = intval($mm[1]); $params[] = intval($mm[2]);
        }

        $periodo = trim($_GET['periodo'] ?? '');
        if ($periodo === 'mes_atual') {
            // "A iniciar agora" — exclui status já encerrados
            $sql .= " AND YEAR(r.mes_renovacao) = YEAR(CURDATE())
                      AND MONTH(r.mes_renovacao) = MONTH(CURDATE())
                      AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')";
        } elseif ($periodo === 'proximos30') {
            // Próximos 30 dias — janela imediata de ação
            $sql .= " AND r.mes_renovacao BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                      AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')";
        } elseif ($periodo === 'proximos90') {
            // Exclui status já encerrados (não precisam mais de ação)
            $sql .= " AND r.mes_renovacao BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                      AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')";
        } elseif ($periodo === 'atrasado') {
            $sql .= " AND r.mes_renovacao < CURDATE()
                      AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')";
        } elseif ($periodo === 'em_tratativa') {
            // Carteira ativa (qualquer renovação ainda em andamento)
            $sql .= " AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')";
        }

        // Filtro de sinistralidade alta (>70%) e pendente — risco financeiro
        if (!empty($_GET['sinistralidade_alta'])) {
            $sql .= " AND r.sinistralidade > 70
                      AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')";
        }

        $sql .= " ORDER BY r.mes_renovacao ASC, r.id DESC";

        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $rows = []; }

        json(['ok' => true, 'data' => $rows]);
        break;

    case 'stats_renovacao':
        // Schema novo: renovacao tem mes_renovacao direto (não precisa COALESCE/calc),
        // status (não status_renovacao), e premio_reajuste (não premio_reajustado).
        // Aplica os MESMOS filtros da listagem, para os cards baterem com a
        // carteira que está sendo exibida (antes o stats ignorava os filtros e
        // ficava "estático" na carteira inteira).
        $whereCart = '';
        $paramsCart = [];
        if (!iw_ve_tudo()) { $whereCart .= " AND e.analista_nome = ?"; $paramsCart[] = $_SESSION['user_nome'] ?? ''; }

        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $like = "%{$search}%";
            $whereCart .= " AND (e.nome LIKE ? OR e.grupo LIKE ? OR e.cnpj LIKE ? OR e.numero_contrato LIKE ? OR e.resp_nome LIKE ?)";
            $paramsCart[] = $like; $paramsCart[] = $like; $paramsCart[] = $like; $paramsCart[] = $like; $paramsCart[] = $like;
        }
        foreach (['origem','grupo'] as $f) {
            $v = trim($_GET[$f] ?? '');
            if ($v) { $whereCart .= " AND e.$f = ?"; $paramsCart[] = $v; }
        }
        foreach (['ramo','seguradora'] as $f) {
            $v = trim($_GET[$f] ?? '');
            if ($v) { $whereCart .= " AND COALESCE(r.$f, e.$f) = ?"; $paramsCart[] = $v; }
        }
        foreach (['status','tipo','aditivo'] as $f) {
            $v = trim($_GET[$f] ?? '');
            if ($v) { $whereCart .= " AND r.$f = ?"; $paramsCart[] = $v; }
        }
        $analistaF = trim($_GET['analista'] ?? '');
        if ($analistaF && isAdmin()) { $whereCart .= " AND e.analista_nome = ?"; $paramsCart[] = $analistaF; }
        $mesF = trim($_GET['mes'] ?? '');
        if ($mesF && preg_match('/^(\d{4})-(\d{2})$/', $mesF, $mmF)) {
            $whereCart .= " AND YEAR(r.mes_renovacao) = ? AND MONTH(r.mes_renovacao) = ?";
            $paramsCart[] = intval($mmF[1]); $paramsCart[] = intval($mmF[2]);
        }
        $periodoF = trim($_GET['periodo'] ?? '');
        if ($periodoF === 'mes_atual') {
            $whereCart .= " AND YEAR(r.mes_renovacao) = YEAR(CURDATE()) AND MONTH(r.mes_renovacao) = MONTH(CURDATE()) AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')";
        } elseif ($periodoF === 'proximos30') {
            $whereCart .= " AND r.mes_renovacao BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')";
        } elseif ($periodoF === 'proximos90') {
            $whereCart .= " AND r.mes_renovacao BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')";
        } elseif ($periodoF === 'atrasado') {
            $whereCart .= " AND r.mes_renovacao < CURDATE() AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')";
        } elseif ($periodoF === 'em_tratativa') {
            $whereCart .= " AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')";
        }
        if (!empty($_GET['sinistralidade_alta'])) {
            $whereCart .= " AND r.sinistralidade > 70 AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')";
        }

        try {
            $stT = db()->prepare("
                SELECT COUNT(*) AS total,
                    -- Carteira ativa (todas que ainda não foram fechadas)
                    SUM(CASE WHEN r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')
                             THEN 1 ELSE 0 END) AS em_tratativa,
                    -- A vencer em 30 dias (urgente, ainda dá tempo de agir)
                    SUM(CASE WHEN r.mes_renovacao BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)
                              AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')
                             THEN 1 ELSE 0 END) AS prox30,
                    -- Sinistralidade alta (>70%) — risco financeiro, prioridade comercial
                    SUM(CASE WHEN r.sinistralidade > 70
                              AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')
                             THEN 1 ELSE 0 END) AS sin_alta,
                    -- Mantidos pra compat (algumas telas ainda usam)
                    SUM(CASE WHEN YEAR(r.mes_renovacao)=YEAR(CURDATE())
                              AND MONTH(r.mes_renovacao)=MONTH(CURDATE())
                              AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')
                             THEN 1 ELSE 0 END) AS mes,
                    SUM(CASE WHEN r.mes_renovacao BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 90 DAY)
                              AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')
                             THEN 1 ELSE 0 END) AS prox90,
                    SUM(CASE WHEN r.mes_renovacao < CURDATE()
                              AND r.status NOT IN ('Finalizada','Finalizado','Cancelada','Cancelado','Recusada','Migrada')
                             THEN 1 ELSE 0 END) AS atrasadas,
                    SUM(CASE WHEN r.status IN ('Finalizada','Finalizado') THEN 1 ELSE 0 END) AS finalizadas,
                    COALESCE(AVG(NULLIF(r.sinistralidade, 0)),0) AS media_sin
                FROM renovacao r
                INNER JOIN empresas e ON e.id = r.empresa_id
                WHERE 1=1 $whereCart");
            $stT->execute($paramsCart);
            $r = $stT->fetch(PDO::FETCH_ASSOC) ?: [];

            // Prêmio Anterior = total da carteira filtrada (todas com prêmio anterior).
            // Prêmio Reajustado = total do que já foi reajustado.
            // Receita a Mais = ganho só das renovações que têm os DOIS prêmios (comparáveis).
            $stP = db()->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN r.premio_anterior > 0 THEN r.premio_anterior ELSE 0 END),0) AS tot_ant,
                    COALESCE(SUM(CASE WHEN r.premio_reajuste > 0 THEN r.premio_reajuste ELSE 0 END),0) AS tot_reaj,
                    COALESCE(SUM(CASE WHEN r.premio_anterior > 0 AND r.premio_reajuste > 0
                                      THEN (r.premio_reajuste - r.premio_anterior) ELSE 0 END),0) AS tot_rec,
                    SUM(CASE WHEN r.premio_anterior > 0 AND r.premio_reajuste > 0 THEN 1 ELSE 0 END) AS qtd_validas
                FROM renovacao r
                INNER JOIN empresas e ON e.id = r.empresa_id
                WHERE 1=1 $whereCart");
            $stP->execute($paramsCart);
            $rp = $stP->fetch(PDO::FETCH_ASSOC) ?: [];
            $tot_ant  = floatval($rp['tot_ant']  ?? 0);
            $tot_reaj = floatval($rp['tot_reaj'] ?? 0);
            $tot_rec  = floatval($rp['tot_rec']  ?? 0);
            $qtd_val  = intval($rp['qtd_validas'] ?? 0);
        } catch (PDOException $e) {
            $r = []; $tot_ant = 0; $tot_reaj = 0; $tot_rec = 0; $qtd_val = 0;
        }

        json(['ok' => true, 'data' => [
            'total'         => intval($r['total'] ?? 0),
            'em_tratativa'  => intval($r['em_tratativa'] ?? 0),
            'prox30'        => intval($r['prox30'] ?? 0),
            'sin_alta'      => intval($r['sin_alta'] ?? 0),
            'mes'           => intval($r['mes'] ?? 0),
            'prox90'        => intval($r['prox90'] ?? 0),
            'atrasadas'     => intval($r['atrasadas'] ?? 0),
            'finalizadas'   => intval($r['finalizadas'] ?? 0),
            'tot_ant'       => $tot_ant,
            'tot_reaj'      => $tot_reaj,
            'tot_rec'       => $tot_rec,
            'qtd_validas'   => $qtd_val,
            'media_sin'     => floatval($r['media_sin'] ?? 0)
        ]]);
        break;


    // ═══════════════════════════════════════════════════
    //  CRUD RENOVAÇÃO
    // ═══════════════════════════════════════════════════
    case 'criar_renovacao':
    case 'editar_renovacao':
        if (!iw_edita_renovacao()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $isEdit = ($action === 'editar_renovacao');
        $d = jsonBody();
        $id = intval($d['id'] ?? 0);
        if ($isEdit && !$id) json(['ok' => false, 'msg' => 'ID inválido']);

        // 1) RESOLVE EMPRESA — fonte de verdade ao EDITAR é o empresa_id já
        //    salvo na própria renovação. Buscar por nome/seguradora pode
        //    casar com a empresa errada quando existem duplicatas (BUS2
        //    Saúde + Odonto, por exemplo: ambos teriam mesmo nome/seguradora).
        $empresaId = 0;
        if ($isEdit && $id) {
            try {
                $stEdit = db()->prepare("SELECT empresa_id FROM renovacao WHERE id = ?");
                $stEdit->execute([$id]);
                $empresaId = intval($stEdit->fetchColumn());
            } catch (PDOException $e) {}
        }
        // Permite override explícito via body (criar_renovacao ou re-link manual)
        if (!$empresaId) $empresaId = intval($d['empresa_id'] ?? 0);

        if (!$empresaId) {
            $cnpj       = trim($d['cnpj'] ?? '');
            $contrato   = trim($d['numero_contrato'] ?? '');
            $nome       = trim($d['empresa'] ?? '');
            $seguradora = trim($d['seguradora'] ?? '');

            // Buscar empresa em ordem de prioridade
            if ($cnpj && $contrato) {
                $stE = db()->prepare("SELECT id FROM empresas WHERE cnpj = ? AND numero_contrato = ? LIMIT 1");
                $stE->execute([$cnpj, $contrato]);
                $empresaId = intval($stE->fetchColumn());
            }
            if (!$empresaId && $nome && $seguradora) {
                $stE = db()->prepare("SELECT id FROM empresas WHERE nome = ? AND seguradora = ? LIMIT 1");
                $stE->execute([$nome, $seguradora]);
                $empresaId = intval($stE->fetchColumn());
            }
            if (!$empresaId && $nome) {
                $stE = db()->prepare("SELECT id FROM empresas WHERE nome = ? LIMIT 1");
                $stE->execute([$nome]);
                $empresaId = intval($stE->fetchColumn());
            }

            // Resolve analista_id pelo nome se vier só nome
            $analistaId = intval($d['analista_id'] ?? 0);
            if (!$analistaId && !empty($d['analista_nome'])) {
                try {
                    $stA = db()->prepare("SELECT id FROM analistas WHERE nome = ? AND ativo = 1 LIMIT 1");
                    $stA->execute([$d['analista_nome']]);
                    $analistaId = intval($stA->fetchColumn());
                } catch (PDOException $e) {}
            }

            // Se nem assim achou, CADASTRA a empresa
            if (!$empresaId && $nome) {
                try {
                    $stmtCreate = db()->prepare("INSERT INTO empresas
                        (origem, ramo, seguradora, numero_contrato, cnpj, grupo, nome, razao_social,
                         analista_id, analista_nome, inicio_vigencia, numero_vidas, porte,
                         percentual_comissao, resp_nome, resp_email, resp_telefone, ativo)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)");
                    // Regra: origem vazia sempre vira 'INTERWEG'
                    $origemNova = trim($d['origem'] ?? '');
                    if ($origemNova === '') $origemNova = 'INTERWEG';
                    $stmtCreate->execute([
                        $origemNova,
                        $d['ramo']               ?? null,
                        $seguradora              ?: null,
                        $contrato                ?: null,
                        $cnpj                    ?: null,
                        $d['grupo']              ?? null,
                        $nome,
                        $d['razao_social']       ?? null,
                        $analistaId              ?: null,
                        $d['analista_nome']      ?? null,
                        $d['inicio_vigencia']    ?: null,
                        intval($d['numero_vidas'] ?? 0) ?: null,
                        $d['porte']              ?? $d['porte_empresa'] ?? null,
                        $d['percentual_comissao']?: null,
                        $d['resp_nome']          ?? null,
                        $d['resp_email']         ?? null,
                        $d['resp_telefone']      ?? null
                    ]);
                    $empresaId = db()->lastInsertId();
                } catch (PDOException $e) {
                    json(['ok' => false, 'msg' => 'Erro ao criar empresa: ' . $e->getMessage()]);
                }
            }
        }

        if (!$empresaId) {
            json(['ok' => false, 'msg' => 'Não foi possível resolver/criar a empresa. Preencha pelo menos o nome da empresa.']);
        }

        // 1a) DETECTA TROCA DE EMPRESA — se o user mudou o nome da empresa
        //     no modal, antes de qualquer clone, tenta re-linkar pra uma
        //     empresa JÁ EXISTENTE com aquele nome. Evita criar empresa
        //     duplicada quando a intenção era trocar pra outra empresa.
        if ($isEdit && $id && $empresaId) {
            try {
                $stCur = db()->prepare("SELECT nome, grupo, seguradora FROM empresas WHERE id = ?");
                $stCur->execute([$empresaId]);
                $atual = $stCur->fetch(PDO::FETCH_ASSOC) ?: [];
                $nomeAtual = trim($atual['nome'] ?? '');
                $nomeNovo  = trim($d['empresa'] ?? $d['nome'] ?? '');
                $segNova   = trim($d['seguradora'] ?? '');

                // Troca de empresa = nome diferente E não vazio
                if ($nomeNovo !== '' && strcasecmp($nomeAtual, $nomeNovo) !== 0) {
                    // Procura empresa existente com esse nome (priorizando match por seguradora)
                    $novoId = 0;
                    if ($segNova !== '') {
                        $stFind = db()->prepare("SELECT id FROM empresas
                                                 WHERE nome = ? AND seguradora = ? AND ativo = 1
                                                 LIMIT 1");
                        $stFind->execute([$nomeNovo, $segNova]);
                        $novoId = intval($stFind->fetchColumn());
                    }
                    if (!$novoId) {
                        $stFind = db()->prepare("SELECT id FROM empresas
                                                 WHERE nome = ? AND ativo = 1
                                                 LIMIT 1");
                        $stFind->execute([$nomeNovo]);
                        $novoId = intval($stFind->fetchColumn());
                    }
                    if ($novoId && $novoId !== $empresaId) {
                        // Re-linka essa renovação pra empresa existente
                        db()->prepare("UPDATE renovacao SET empresa_id = ? WHERE id = ?")
                            ->execute([$novoId, $id]);
                        $empresaId = $novoId;
                        // Pula o auto-clone (já está apontando pra outra empresa única dela)
                        goto aposClone;
                    }
                }
            } catch (PDOException $e) {
                error_log("editar_renovacao re-link falhou: " . $e->getMessage());
            }
        }

        // 1b) AUTO-CLONE — DESATIVADO em 18/05/2026.
        //     Schema atual já separa naturalmente:
        //       - Dados do ciclo (sinistralidade, %, status, mês_renovacao, etc)
        //         vivem na tabela `renovacao` — cada ciclo é independente.
        //       - Dados cadastrais (CNPJ, contrato, nome, vigência) vivem em
        //         `empresas` — alterações aqui propagam pra TODAS as renovações
        //         da empresa (Saúde + Odonto, ciclos anteriores, etc).
        //     Clonar a empresa quebrava essa sincronia natural: editar uma
        //     renovação ACPL Odonto não refletia o novo contrato na ACPL Saúde.
        //
        //     A separação entre "trocar de empresa" e "editar dados da empresa"
        //     é resolvida no passo 1a (re-link pra empresa existente com novo nome).
        aposClone:

        // 1b) ATUALIZA dados cadastrais da empresa quando o modal de renovação
        //     também é usado pra editar campos que vivem em `empresas` (origem,
        //     ramo, seguradora, CNPJ, contrato, analista, vigência, comissão,
        //     responsável). Usa COALESCE(?, col) pra não zerar quem não veio
        //     no body (campo omitido = mantém o que estava no banco).
        try {
            // Resolve analista_id de novo pra cobrir o caso em que veio só nome
            $analistaIdEmp = isset($d['analista_id']) ? intval($d['analista_id']) : 0;
            if (!$analistaIdEmp && !empty($d['analista_nome'])) {
                try {
                    $stAE = db()->prepare("SELECT id FROM analistas WHERE nome = ? AND ativo = 1 LIMIT 1");
                    $stAE->execute([$d['analista_nome']]);
                    $analistaIdEmp = intval($stAE->fetchColumn());
                } catch (PDOException $e) {}
            }
            // empUpd = só campos que são REALMENTE da empresa (cadastro base
            // compartilhado). Origem, contrato, %comissão, vigência e analista
            // viraram override POR CICLO (salvos na tabela renovacao no passo 3)
            // — por isso saíram daqui: editar numa renovação não mexe na outra.
            // numero_vidas e porte também saíram daqui — viraram override
            // por ciclo (numero_vidas_ciclo / porte_ciclo). Editar numa renovação
            // não muda a outra da mesma empresa.
            $empUpd = array(
                'ramo'                => $d['ramo']                ?? null,
                'seguradora'          => $d['seguradora']          ?? null,
                'cnpj'                => $d['cnpj']                ?? null,
                'grupo'               => $d['grupo']               ?? null,
                'nome'                => $d['empresa']             ?? $d['nome'] ?? null,
                'resp_nome'           => $d['resp_nome']            ?? null,
                'resp_email'          => $d['resp_email']           ?? null,
                'resp_telefone'      => $d['resp_telefone']        ?? null,
            );
            // Normaliza string vazia em null (pra COALESCE funcionar)
            foreach ($empUpd as $k => $v) { if ($v === '') $empUpd[$k] = null; }

            // Campos que o usuário PODE limpar (apagar o valor) pelo modal.
            // Para esses, se veio vazio no payload, gravamos NULL direto
            // (sem COALESCE) — assim "apagar contrato" funciona.
            // Os demais campos mantêm comportamento COALESCE (valor vazio = manter antigo).
            $camposLimpaveis = [
                'cnpj',
                'resp_nome', 'resp_email', 'resp_telefone',
                // numero_contrato saiu da lista — agora vive em renovacao.numero_contrato_ciclo
            ];

            // Mapa do nome da coluna SQL pro nome da chave no payload
            // (ex: coluna "nome" vem como "empresa" no JSON)
            $payloadAlias = [
                'nome'  => 'empresa',
                'porte' => 'porte', // mesmo nome
            ];

            $setEmp = array();
            $paramsEmp = array();
            foreach ($empUpd as $c => $val) {
                $payloadKey = $payloadAlias[$c] ?? $c;
                $veioNoPayload = array_key_exists($payloadKey, $d);
                $veioVazio = $veioNoPayload && (
                    $d[$payloadKey] === '' || $d[$payloadKey] === null
                );

                if ($veioVazio && in_array($c, $camposLimpaveis, true)) {
                    // Veio explicitamente vazio + é campo limpável → grava NULL
                    $setEmp[] = "$c = NULL";
                } else {
                    // Comportamento padrão: COALESCE (mantém antigo se null)
                    $setEmp[] = "$c = COALESCE(?, $c)";
                    $paramsEmp[] = $val;
                }
            }
            $sqlEmp = "UPDATE empresas SET " . implode(', ', $setEmp) . " WHERE id = ?";
            $paramsEmp[] = $empresaId;
            $stmtEmp = db()->prepare($sqlEmp);
            $stmtEmp->execute($paramsEmp);
        } catch (PDOException $e) {
            // Não trava o salvamento da renovação — apenas loga o erro
            error_log("editar_renovacao: falha ao atualizar empresa $empresaId: " . $e->getMessage());
        }

        // 2) Determina ano_ciclo do mes_renovacao
        $mesRen = $d['mes_renovacao'] ?? null;
        if (!$mesRen) {
            json(['ok' => false, 'msg' => 'Data de renovação (mes_renovacao) é obrigatória']);
        }
        $anoCiclo = intval(date('Y', strtotime($mesRen)));

        // 3) Monta dados pra renovacao (só campos do ciclo, aceitando aliases do front antigo)
        // ramo/seguradora/contrato aqui são OVERRIDES por ciclo. Se forem iguais aos da
        // empresa, ainda assim gravamos no ciclo (não compara) — isso garante que ao
        // editar o contrato em uma renovação NÃO mude as outras renovações da mesma empresa.
        $ramoOverride       = $d['ramo']       ?? null;
        $seguradoraOverride = $d['seguradora'] ?? null;
        if ($ramoOverride === '')       $ramoOverride = null;
        if ($seguradoraOverride === '') $seguradoraOverride = null;
        // Contrato: '' (vazio) é gravado como '' no ciclo = vazio INTENCIONAL
        // (não herda da empresa). Só fica NULL se a chave nem veio no payload.
        $contratoOverride = array_key_exists('numero_contrato', $d)
            ? (string)$d['numero_contrato']   // pode ser '' (vazio intencional) ou valor
            : null;                            // não veio = mantém herança

        // % comissão: mesmo padrão do contrato (override por ciclo)
        $percComissaoOverride = array_key_exists('percentual_comissao', $d)
            ? (string)$d['percentual_comissao']
            : null;

        // Origem / Início vigência / Analista — também override por ciclo
        $origemOverride = array_key_exists('origem', $d) ? (string)$d['origem'] : null;
        if ($origemOverride === '') $origemOverride = null; // origem vazia = herda

        $iniVigOverride = array_key_exists('inicio_vigencia', $d)
            ? ($d['inicio_vigencia'] ?: null) : null;

        // Analista do ciclo: resolve id pelo nome quando só veio nome
        $analistaNomeCiclo = array_key_exists('analista_nome', $d) ? trim((string)$d['analista_nome']) : '';
        $analistaIdCiclo   = isset($d['analista_id']) ? (intval($d['analista_id']) ?: null) : null;
        if (!$analistaIdCiclo && $analistaNomeCiclo !== '') {
            try {
                $stAC = db()->prepare("SELECT id FROM analistas WHERE nome = ? AND ativo = 1 LIMIT 1");
                $stAC->execute([$analistaNomeCiclo]);
                $analistaIdCiclo = intval($stAC->fetchColumn()) ?: null;
            } catch (PDOException $e) {}
        }
        if ($analistaNomeCiclo === '') $analistaNomeCiclo = null;

        $renovData = [
            'empresa_id'              => $empresaId,
            'ramo'                    => $ramoOverride,
            'seguradora'              => $seguradoraOverride,
            'numero_contrato_ciclo'   => $contratoOverride,
            'perc_comissao_ciclo'     => $percComissaoOverride,
            'origem_ciclo'            => $origemOverride,
            'inicio_vigencia_ciclo'   => $iniVigOverride,
            'analista_id_ciclo'       => $analistaIdCiclo,
            'analista_nome_ciclo'     => $analistaNomeCiclo,
            'ano_ciclo'               => $anoCiclo,
            'mes_renovacao'      => $mesRen,
            'inicio_tratativa'   => $d['inicio_tratativa']   ?? null,
            'status'             => $d['status']             ?? $d['status_renovacao'] ?? 'A iniciar',
            'tipo'               => $d['tipo']               ?? 'Renovação',
            'aditivo'            => $d['aditivo']            ?? null,
            'sinistralidade'     => $d['sinistralidade']     ?? null,
            'perc_proposto'      => $d['perc_proposto']      ?? $d['percentual_proposto'] ?? null,
            'perc_finalizado'    => $d['perc_finalizado']    ?? $d['percentual_final']    ?? null,
            'premio_anterior'    => $d['premio_anterior']    ?? null,
            'premio_reajuste'    => $d['premio_reajuste']    ?? $d['premio_reajustado']   ?? null,
            'numero_vidas_ciclo' => $d['numero_vidas_ciclo'] ?? $d['numero_vidas']        ?? null,
            'porte_ciclo'        => $d['porte_ciclo']        ?? $d['porte_empresa'] ?? $d['porte'] ?? null,
            'analista_seguradora'=> $d['analista_seguradora']?? null,
            'observacoes'        => $d['observacoes']        ?? $d['observacao']          ?? null,
        ];
        // Normaliza '' para null — EXCETO os overrides por ciclo onde ''
        // significa "vazio intencional" (não herda da empresa).
        $preservaVazio = ['numero_contrato_ciclo', 'perc_comissao_ciclo'];
        foreach ($renovData as $k => $v) {
            if ($v === '' && !in_array($k, $preservaVazio, true)) $renovData[$k] = null;
        }

        try {
            if ($isEdit) {
                // Sem arrow functions (PHP 7.2 compat)
                $setParts = array();
                foreach (array_keys($renovData) as $c) { $setParts[] = "$c = ?"; }
                $set = implode(', ', $setParts);
                $stmt = db()->prepare("UPDATE renovacao SET $set WHERE id = ?");
                $stmt->execute(array_merge(array_values($renovData), [$id]));
            } else {
                $colList = implode(',', array_keys($renovData));
                $placeholders = implode(',', array_fill(0, count($renovData), '?'));
                $stmt = db()->prepare("INSERT INTO renovacao ($colList) VALUES ($placeholders)");
                $stmt->execute(array_values($renovData));
                $id = db()->lastInsertId();
            }
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao salvar renovação: ' . $e->getMessage()]);
        }
        json(['ok' => true, 'id' => $id, 'empresa_id' => $empresaId]);
        break;

    case 'excluir_renovacao':
        if (!iw_edita_renovacao()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $id = intval($_GET['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);
        try {
            db()->prepare("DELETE FROM renovacao WHERE id = ?")->execute([$id]);
            json(['ok' => true]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        }
        break;

    // ═══════════════════════════════════════════════════
    //  IMPORTAR XLSX DE RENOVAÇÕES
    //  Recebe um array de linhas (já lidas no front via SheetJS), cria
    //  empresas faltantes em `empresas` e insere renovações ligadas via
    //  empresa_id. UNIQUE KEY (empresa_id, ano_ciclo) previne duplicatas.
    // ═══════════════════════════════════════════════════
    case 'importar_renovacao':
        if (!iw_edita_renovacao()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody();
        $linhas = $d['linhas'] ?? [];
        if (!is_array($linhas) || !count($linhas)) {
            json(['ok' => false, 'msg' => 'Nenhuma linha recebida']);
        }

        $inseridos = 0; $atualizados = 0; $empresas_criadas = 0; $erros = [];
        $analistasCache = [];

        // Pré-carrega analistas ativos pra resolver nome → id rapidamente
        try {
            $stmtA = db()->query("SELECT id, nome FROM analistas WHERE ativo = 1");
            foreach ($stmtA->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $analistasCache[mb_strtolower(trim($a['nome']))] = intval($a['id']);
            }
        } catch (PDOException $e) {}

        foreach ($linhas as $idx => $l) {
            try {
                $origem      = trim($l['origem']      ?? '');
                $ramo        = trim($l['ramo']        ?? '');
                $seguradora  = trim($l['seguradora']  ?? '');
                $contrato    = trim($l['numero_contrato'] ?? '');
                $clienteNome = trim($l['empresa']     ?? '');  // CLIENTE da planilha = nome da empresa
                $grupo       = trim($l['grupo']       ?? $clienteNome);
                $analistaNm  = trim($l['analista_nome'] ?? '');
                $cnpj        = trim($l['cnpj']        ?? '');

                if (!$clienteNome) {
                    $erros[] = "Linha " . ($idx+1) . ": EMPRESA/CLIENTE vazio, ignorada";
                    continue;
                }

                $analistaId = $analistasCache[mb_strtolower($analistaNm)] ?? null;

                // 1) Resolve empresa_id
                $empresaId = 0;
                if ($cnpj && $contrato) {
                    $st = db()->prepare("SELECT id FROM empresas WHERE cnpj = ? AND numero_contrato = ? LIMIT 1");
                    $st->execute([$cnpj, $contrato]);
                    $empresaId = intval($st->fetchColumn());
                }
                if (!$empresaId && $clienteNome && $seguradora) {
                    $st = db()->prepare("SELECT id FROM empresas WHERE nome = ? AND seguradora = ? LIMIT 1");
                    $st->execute([$clienteNome, $seguradora]);
                    $empresaId = intval($st->fetchColumn());
                }
                if (!$empresaId && $clienteNome) {
                    $st = db()->prepare("SELECT id FROM empresas WHERE nome = ? LIMIT 1");
                    $st->execute([$clienteNome]);
                    $empresaId = intval($st->fetchColumn());
                }

                // 2a) Achou empresa existente → preenche campos vazios com dados da planilha
                //     (origem, ramo, porte etc. que podem estar NULL ou string vazia '')
                if ($empresaId) {
                    try {
                        $stUpd = db()->prepare("UPDATE empresas SET
                            origem      = COALESCE(NULLIF(origem, ''), ?),
                            ramo        = COALESCE(NULLIF(ramo, ''), ?),
                            seguradora  = COALESCE(NULLIF(seguradora, ''), ?),
                            numero_contrato = COALESCE(NULLIF(numero_contrato, ''), ?),
                            cnpj        = COALESCE(NULLIF(cnpj, ''), ?),
                            grupo       = COALESCE(NULLIF(grupo, ''), ?),
                            analista_id = COALESCE(analista_id, ?),
                            analista_nome = COALESCE(NULLIF(analista_nome, ''), ?),
                            inicio_vigencia = COALESCE(inicio_vigencia, ?),
                            numero_vidas = COALESCE(numero_vidas, ?),
                            porte       = COALESCE(NULLIF(porte, ''), ?),
                            percentual_comissao = COALESCE(percentual_comissao, ?)
                            WHERE id = ?");
                        $stUpd->execute([
                            $origem ?: null,
                            $ramo ?: null,
                            $seguradora ?: null,
                            $contrato ?: null,
                            $cnpj ?: null,
                            $grupo ?: $clienteNome,
                            $analistaId ?: null,
                            $analistaNm ?: null,
                            $l['inicio_vigencia'] ?? null,
                            intval($l['numero_vidas'] ?? 0) ?: null,
                            _mapearPorteParaEnum($l['porte_empresa'] ?? null),
                            $l['percentual_comissao'] ?? null,
                            $empresaId
                        ]);
                    } catch (PDOException $e) { /* silencioso, não trava o import */ }
                }

                // 2b) Não achou → cria a empresa em `empresas`
                if (!$empresaId) {
                    $stmtC = db()->prepare("INSERT INTO empresas
                        (origem, ramo, seguradora, numero_contrato, cnpj, grupo, nome,
                         analista_id, analista_nome, inicio_vigencia, numero_vidas, porte,
                         percentual_comissao, ativo)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)");
                    $stmtC->execute([
                        $origem ?: null,
                        $ramo ?: null,
                        $seguradora ?: null,
                        $contrato ?: null,
                        $cnpj ?: null,
                        $grupo ?: $clienteNome,
                        $clienteNome,
                        $analistaId ?: null,
                        $analistaNm ?: null,
                        $l['inicio_vigencia'] ?? null,
                        intval($l['numero_vidas'] ?? 0) ?: null,
                        // porte: a planilha já vem no formato novo (Até 30, Entre 30 e 100, etc.) —
                        // mas o enum de empresas só aceita 3 valores antigos. Mapeia.
                        _mapearPorteParaEnum($l['porte_empresa'] ?? null),
                        $l['percentual_comissao'] ?? null
                    ]);
                    $empresaId = db()->lastInsertId();
                    $empresas_criadas++;
                }

                // 3) Determina ano_ciclo
                $mesRen = $l['mes_renovacao'] ?? null;
                if (!$mesRen) {
                    $erros[] = "Linha " . ($idx+1) . " ($clienteNome): mes_renovacao vazio, ignorada";
                    continue;
                }
                $anoCiclo = intval(date('Y', strtotime($mesRen)));

                // 4) INSERT puro renovacao — sem UNIQUE KEY agora, aceita duplicatas
                //    da planilha (mesma empresa com várias entradas no mesmo ano)
                $stmtR = db()->prepare("INSERT INTO renovacao
                    (empresa_id, ano_ciclo, mes_renovacao, inicio_tratativa,
                     status, tipo, aditivo,
                     sinistralidade, perc_proposto, perc_finalizado,
                     premio_anterior, premio_reajuste,
                     numero_vidas_ciclo, porte_ciclo, observacoes)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                $stmtR->execute([
                    $empresaId,
                    $anoCiclo,
                    $mesRen,
                    $l['inicio_tratativa']    ?? null,
                    $l['status_renovacao']    ?? $l['status'] ?? 'A iniciar',
                    $l['tipo']                ?? 'Renovação',
                    $l['aditivo']             ?? 'Não',
                    $l['sinistralidade']      ?? null,
                    $l['percentual_proposto'] ?? $l['perc_proposto'] ?? null,
                    $l['percentual_final']    ?? $l['perc_finalizado'] ?? null,
                    $l['premio_anterior']     ?? null,
                    $l['premio_reajustado']   ?? $l['premio_reajuste'] ?? null,
                    intval($l['numero_vidas'] ?? 0) ?: null,
                    // porte_ciclo: snapshot do valor original da planilha. Converte
                    // string vazia em NULL pra COALESCE conseguir cair no e.porte.
                    (isset($l['porte_empresa']) && trim((string)$l['porte_empresa']) !== '')
                        ? $l['porte_empresa'] : null,
                    $l['observacao']          ?? null
                ]);
                if ($stmtR->rowCount() == 1) { $inseridos++; } else { $atualizados++; }
            } catch (PDOException $e) {
                $erros[] = "Linha " . ($idx+1) . ": " . $e->getMessage();
            }
        }

        json([
            'ok' => true,
            'inseridos' => $inseridos,
            'atualizados' => $atualizados,
            'empresas_criadas' => $empresas_criadas,
            'erros' => $erros,
            'msg' => "✓ $inseridos inseridas, $atualizados atualizadas, $empresas_criadas empresas novas cadastradas"
                   . (count($erros) ? " (" . count($erros) . " erro(s))" : "")
        ]);
        break;

    // ═══════════════════════════════════════════════════
    //  VIRAR ANO — duplica renovações de um ano pro próximo
    //  (igual o "Virar Mês" do faturamento, mas anual)
    // ═══════════════════════════════════════════════════
    case 'virar_ano_renovacao':
        if (!iw_edita_renovacao()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody();
        $de   = intval($d['de']   ?? 0);
        $para = intval($d['para'] ?? 0);
        if (!$de || !$para)  json(['ok' => false, 'msg' => 'Informe origem e destino']);
        if ($de === $para)   json(['ok' => false, 'msg' => 'Origem e destino não podem ser iguais']);

        // Confere se já tem renovação no ano destino
        $stChk = db()->prepare("SELECT COUNT(*) FROM renovacao WHERE ano_ciclo = ?");
        $stChk->execute([$para]);
        if (intval($stChk->fetchColumn()) > 0) {
            json(['ok' => false, 'msg' => "Já existem renovações no ano $para. Apague-as antes ou escolha outro ano."]);
        }

        try {
            $diff = $para - $de;
            $stmt = db()->prepare("
                INSERT INTO renovacao
                    (empresa_id, ano_ciclo, mes_renovacao,
                     status, tipo, aditivo,
                     premio_anterior,
                     numero_vidas_ciclo, porte_ciclo,
                     analista_seguradora, observacoes)
                SELECT empresa_id,
                       ?,
                       DATE_ADD(mes_renovacao, INTERVAL ? YEAR),
                       'A iniciar', tipo, NULL,
                       premio_reajuste,
                       numero_vidas_ciclo, porte_ciclo,
                       analista_seguradora, NULL
                FROM renovacao
                WHERE ano_ciclo = ?
            ");
            $stmt->execute([$para, $diff, $de]);
            $inseridos = $stmt->rowCount();
            json(['ok' => true, 'inseridos' => $inseridos]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao virar ano: ' . $e->getMessage()]);
        }
        break;

    // ═══════════════════════════════════════════════════
    //  CONTROLE DE COTAÇÃO — Acompanhamento de cotações
    // ═══════════════════════════════════════════════════

    case 'listar_cotacoes':
        $sql    = "SELECT * FROM controle_cotacao WHERE 1=1";
        $params = [];

        // Acesso: admin, visualizador e o time de NOVOS NEGÓCIOS veem TODAS as cotações
        // (mesma regra do Fechamento). Demais perfis veem só a própria carteira.
        $nomeSess = $_SESSION['user_nome'] ?? '';
        if (!canViewAll() && !isNovosNegocios() && !isComercialCotacao() && !isSilmara()) {
            $sql .= " AND analista = ?";
            $params[] = $nomeSess;
        }

        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $like = "%{$search}%";
            $sql .= " AND (razao_social LIKE ? OR operadora_atual LIKE ? OR players_cotados LIKE ? OR player_contratado LIKE ? OR observacao LIKE ?)";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        foreach (['status','entrada','tipo','ramo','analista'] as $f) {
            $v = trim($_GET[$f] ?? '');
            if ($v) { $sql .= " AND $f = ?"; $params[] = $v; }
        }
        $de  = trim($_GET['de']  ?? '');
        $ate = trim($_GET['ate'] ?? '');
        if ($de)  { $sql .= " AND data_recebimento >= ?"; $params[] = $de;  }
        if ($ate) { $sql .= " AND data_recebimento <= ?"; $params[] = $ate; }

        $sql .= " ORDER BY data_recebimento DESC, id DESC";

        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json(['ok' => true, 'data' => $rows]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao listar cotações: ' . $e->getMessage()]);
        }
        break;

    case 'criar_cotacao_controle':
    case 'editar_cotacao_controle':
        $isEdit = ($action === 'editar_cotacao_controle');
        $d  = jsonBody();
        $id = intval($d['id'] ?? 0);
        if ($isEdit && !$id) json(['ok' => false, 'msg' => 'ID inválido']);

        $razao    = trim($d['razao_social'] ?? '');
        $status   = trim($d['status']       ?? '');
        $entrada  = trim($d['entrada']      ?? '');
        $analista = trim($d['analista']     ?? '');
        $tipo     = trim($d['tipo']         ?? '');
        $ramo     = trim($d['ramo']         ?? '');
        $cnpj     = trim($d['cnpj']         ?? '') ?: null;
        // Analista é OPCIONAL (pode ficar em branco e ser preenchido depois)
        if (!$razao || !$status || !$entrada || !$tipo || !$ramo) {
            json(['ok' => false, 'msg' => 'Preencha os campos obrigatórios (Razão Social, Status, Entrada, Tipo, Ramo)']);
        }
        // Se vazio, salva NULL no banco
        if ($analista === '') $analista = null;
        $obs       = trim($d['observacao']         ?? '') ?: null;
        $dRec      = $d['data_recebimento']        ?: null;
        $vidas     = isset($d['numero_vidas']) && $d['numero_vidas'] !== '' ? intval($d['numero_vidas']) : null;
        $opAtual   = trim($d['operadora_atual']    ?? '') ?: null;
        $dSolic    = $d['data_solicitacao']        ?: null;
        $dEnvio    = $d['data_envio_estudo']       ?: null;
        $players   = trim($d['players_cotados']    ?? '') ?: null;
        $playersSaude  = trim($d['players_cotados_saude']  ?? '') ?: null;
        $playersOdonto = trim($d['players_cotados_odonto'] ?? '') ?: null;
        $playersVida   = trim($d['players_cotados_vida']   ?? '') ?: null;
        $dSinaliz  = $d['data_sinalizacao']        ?: null;
        $playerCt  = trim($d['player_contratado']  ?? '') ?: null;
        $premio    = isset($d['premio']) && $d['premio'] !== '' && $d['premio'] !== null ? floatval($d['premio']) : null;
        $dFinal    = $d['data_finalizacao']        ?: null;
        $motivo    = trim($d['motivo_declinio']    ?? '') ?: null;
        $dProx     = $d['data_proxima_acao']       ?: null;
        $notifEmail = !empty($d['notificar_email_passagem']) ? 1 : 0;
        $destEmail  = trim($d['destinatarios_email_passagem'] ?? '') ?: null;

        try {
            // Garante a coluna de controle anti-duplicação do fechamento automático
            try { db()->exec("ALTER TABLE controle_cotacao ADD COLUMN fechamento_gerado TINYINT NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
            try { db()->exec("ALTER TABLE controle_cotacao ADD COLUMN cnpj VARCHAR(20) NULL"); } catch (Throwable $e) {}
            try { db()->exec("ALTER TABLE controle_cotacao ADD COLUMN uf VARCHAR(2) NULL"); } catch (Throwable $e) {}
            try { db()->exec("ALTER TABLE controle_cotacao ADD COLUMN municipio VARCHAR(120) NULL"); } catch (Throwable $e) {}

            if ($isEdit) {
                $stmt = db()->prepare("UPDATE controle_cotacao SET
                    razao_social=?, cnpj=?, status=?, observacao=?, entrada=?, analista=?, tipo=?, ramo=?,
                    data_recebimento=?, numero_vidas=?, operadora_atual=?, data_solicitacao=?,
                    data_envio_estudo=?, players_cotados=?, players_cotados_saude=?, players_cotados_odonto=?, players_cotados_vida=?, data_sinalizacao=?, player_contratado=?,
                    premio=?, data_finalizacao=?, motivo_declinio=?, data_proxima_acao=?,
                    notificar_email_passagem=?, destinatarios_email_passagem=?
                    WHERE id=?");
                $stmt->execute([
                    $razao, $cnpj, $status, $obs, $entrada, $analista, $tipo, $ramo,
                    $dRec, $vidas, $opAtual, $dSolic, $dEnvio, $players, $playersSaude, $playersOdonto, $playersVida, $dSinaliz, $playerCt,
                    $premio, $dFinal, $motivo, $dProx,
                    $notifEmail, $destEmail, $id
                ]);
                $cotId = $id;
            } else {
                $stmt = db()->prepare("INSERT INTO controle_cotacao
                    (razao_social, cnpj, status, observacao, entrada, analista, tipo, ramo,
                     data_recebimento, numero_vidas, operadora_atual, data_solicitacao,
                     data_envio_estudo, players_cotados, players_cotados_saude, players_cotados_odonto, players_cotados_vida, data_sinalizacao, player_contratado,
                     premio, data_finalizacao, motivo_declinio, data_proxima_acao,
                     notificar_email_passagem, destinatarios_email_passagem)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $razao, $cnpj, $status, $obs, $entrada, $analista, $tipo, $ramo,
                    $dRec, $vidas, $opAtual, $dSolic, $dEnvio, $players, $playersSaude, $playersOdonto, $playersVida, $dSinaliz, $playerCt,
                    $premio, $dFinal, $motivo, $dProx,
                    $notifEmail, $destEmail
                ]);
                $cotId = db()->lastInsertId();
            }

            // ── Automação: ao virar "PASSAGEM DE BASTÃO", cria o registro no Fechamento (1x) ──
            $fechamentoCriado = false;
            if (mb_strtoupper(trim($status), 'UTF-8') === 'PASSAGEM DE BASTÃO') {
                $stF = db()->prepare("SELECT COALESCE(fechamento_gerado,0) FROM controle_cotacao WHERE id = ?");
                $stF->execute([$cotId]);
                if (!intval($stF->fetchColumn())) {
                    try {
                        $obsFech = null; // observação fica em branco (sem texto automático)
                        $insF = db()->prepare("INSERT INTO fechamento
                            (tipo, entrada, ramo, operadora, empresa, cnpj, analista, numero_vidas, fatura_1, valor_primeira_fatura, observacao)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                        $insF->execute([$tipo, $entrada, $ramo, $playerCt, $razao, $cnpj, $analista, $vidas, $premio, $premio, $obsFech]);
                        db()->prepare("UPDATE controle_cotacao SET fechamento_gerado = 1 WHERE id = ?")->execute([$cotId]);
                        $fechamentoCriado = true;
                    } catch (Throwable $e) { /* não bloqueia o salvamento da cotação */ }
                }
            }

            // ── UF pela Receita a partir do CNPJ (não bloqueia o save) ──
            $ufSalva = null;
            if ($cnpj) {
                try {
                    $loc = cc_uf_lookup($cnpj);
                    if ($loc && !empty($loc['uf'])) {
                        db()->prepare("UPDATE controle_cotacao SET uf=?, municipio=? WHERE id=?")
                            ->execute([$loc['uf'], ($loc['municipio'] ?: null), $cotId]);
                        $ufSalva = $loc['uf'];
                    }
                } catch (Throwable $e) { /* silencioso */ }
            }

            json(['ok' => true, 'id' => $cotId, 'fechamento_criado' => $fechamentoCriado, 'uf' => $ufSalva]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao salvar cotação: ' . $e->getMessage()]);
        }
        break;

    // ── Preenche a UF (Receita) das cotações que têm CNPJ e ainda não têm UF ──
    case 'backfill_uf_cotacoes':
        try {
            try { db()->exec("ALTER TABLE controle_cotacao ADD COLUMN uf VARCHAR(2) NULL"); } catch (Throwable $e) {}
            try { db()->exec("ALTER TABLE controle_cotacao ADD COLUMN municipio VARCHAR(120) NULL"); } catch (Throwable $e) {}
            $lote = max(1, min(60, intval($_GET['lote'] ?? 30)));
            $rows = db()->query("SELECT id, cnpj FROM controle_cotacao
                                 WHERE cnpj IS NOT NULL AND cnpj <> ''
                                   AND (uf IS NULL OR uf = '')
                                 ORDER BY id DESC LIMIT $lote")->fetchAll(PDO::FETCH_ASSOC);
            $ok = 0; $falhou = 0;
            $upd = db()->prepare("UPDATE controle_cotacao SET uf=?, municipio=? WHERE id=?");
            foreach ($rows as $r) {
                $loc = cc_uf_lookup($r['cnpj']);
                if ($loc && !empty($loc['uf'])) { $upd->execute([$loc['uf'], ($loc['municipio'] ?: null), $r['id']]); $ok++; }
                else { $falhou++; }
                usleep(250000); // 0,25s p/ respeitar limites da API
            }
            $rest = (int) db()->query("SELECT COUNT(*) FROM controle_cotacao
                                       WHERE cnpj IS NOT NULL AND cnpj <> '' AND (uf IS NULL OR uf = '')")->fetchColumn();
            json(['ok' => true, 'processadas' => count($rows), 'preenchidas' => $ok, 'falhou' => $falhou, 'restantes' => $rest]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro no backfill de UF: ' . $e->getMessage()]);
        }
        break;

    case 'excluir_cotacao_controle':
        $d  = jsonBody();
        $id = intval($d['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);
        try {
            db()->prepare("DELETE FROM controle_cotacao WHERE id = ?")->execute([$id]);
            json(['ok' => true]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao excluir: ' . $e->getMessage()]);
        }
        break;

    // ═══════════════════════════════════════════════════
    //  FECHAMENTO — contratos fechados
    //  Acesso: admin, visualizador, novos_negocios
    // ═══════════════════════════════════════════════════
    case 'listar_fechamentos':
        $sql    = "SELECT * FROM fechamento WHERE 1=1";
        $params = [];
        // Acesso: admin, visualizador e novos_negocios veem TUDO (controle de time).
        // Demais tipos (analista regular, etc.) veem só a carteira deles.
        $tipoSess = $_SESSION['user_tipo'] ?? '';
        if (!canViewAll() && !isFinanceiro() && $tipoSess !== 'novos_negocios' && $tipoSess !== 'comercial_cotacao' && !isSilmara()) {
            $sql .= " AND analista = ?";
            $params[] = $_SESSION['user_nome'] ?? '';
        }
        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $like = "%{$search}%";
            $sql .= " AND (empresa LIKE ? OR cnpj LIKE ? OR operadora LIKE ? OR observacao LIKE ?)";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        foreach (['tipo','ramo','operadora','mes','analista'] as $f) {
            $v = trim($_GET[$f] ?? '');
            if ($v) { $sql .= " AND $f = ?"; $params[] = $v; }
        }
        $de  = trim($_GET['de']  ?? '');
        $ate = trim($_GET['ate'] ?? '');
        if ($de)  { $sql .= " AND vigencia_operadora >= ?"; $params[] = $de;  }
        if ($ate) { $sql .= " AND vigencia_operadora <= ?"; $params[] = $ate; }
        $sql .= " ORDER BY vigencia_operadora DESC, id DESC";
        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao listar fechamentos: ' . $e->getMessage()]);
        }
        break;

    case 'criar_fechamento':
    case 'editar_fechamento':
        $isEdit = ($action === 'editar_fechamento');
        $d  = jsonBody();
        $id = intval($d['id'] ?? 0);
        if ($isEdit && !$id) json(['ok' => false, 'msg' => 'ID inválido']);

        // Financeiro só lança a COMISSÃO RECEBIDA (5 valores + 5 datas).
        // Não cria fechamento nem altera os demais campos.
        if (isFinanceiro()) {
            if (!$isEdit) json(['ok' => false, 'msg' => 'Financeiro não pode criar fechamento.']);
            $rec = []; $dat = [];
            for ($k = 1; $k <= 5; $k++) {
                $rv = $d['comissao_rec_' . $k] ?? null;
                $rec[$k] = ($rv !== '' && $rv !== null) ? floatval($rv) : null;
                $dv = $d['comissao_data_' . $k] ?? null;
                $dat[$k] = ($dv !== '' && $dv !== null) ? $dv : null;
            }
            try {
                $st = db()->prepare("UPDATE fechamento SET
                    comissao_rec_1=?, comissao_rec_2=?, comissao_rec_3=?, comissao_rec_4=?, comissao_rec_5=?,
                    comissao_data_1=?, comissao_data_2=?, comissao_data_3=?, comissao_data_4=?, comissao_data_5=?
                    WHERE id=?");
                $st->execute([
                    $rec[1], $rec[2], $rec[3], $rec[4], $rec[5],
                    $dat[1], $dat[2], $dat[3], $dat[4], $dat[5],
                    $id
                ]);
                json(['ok' => true, 'modo' => 'financeiro']);
            } catch (PDOException $e) {
                json(['ok' => false, 'msg' => 'Erro ao lançar comissão: ' . $e->getMessage()]);
            }
        }

        $tipo     = trim($d['tipo']     ?? '');
        $empresa  = trim($d['empresa']  ?? '');
        $analista = trim($d['analista'] ?? '');
        if (!$tipo || !$empresa) {
            json(['ok' => false, 'msg' => 'Preencha Tipo e Empresa']);
        }
        $entrada   = trim($d['entrada']   ?? '') ?: null;
        $ramo      = trim($d['ramo']      ?? '') ?: null;
        $operadora = trim($d['operadora'] ?? '') ?: null;
        $mes       = trim($d['mes']       ?? '') ?: null;
        $cnpj      = trim($d['cnpj']      ?? '') ?: null;
        $vig       = $d['vigencia_operadora'] ?: null;
        $vidas     = isset($d['numero_vidas']) && $d['numero_vidas'] !== '' ? intval($d['numero_vidas']) : null;
        // Agenciamento em 5 parcelas; pct_agenciamento guarda o TOTAL (soma).
        $ag = [];
        for ($k = 1; $k <= 5; $k++) {
            $v = $d['agenciamento_' . $k] ?? null;
            $ag[$k] = ($v !== '' && $v !== null) ? floatval($v) : null;
        }
        $somaAg = 0; $temAg = false;
        foreach ($ag as $v) { if ($v !== null) { $somaAg += $v; $temAg = true; } }
        $agenc     = $temAg ? $somaAg
                     : (isset($d['pct_agenciamento']) && $d['pct_agenciamento'] !== '' && $d['pct_agenciamento'] !== null ? floatval($d['pct_agenciamento']) : null);
        $comis     = isset($d['pct_comissao'])     && $d['pct_comissao']     !== '' && $d['pct_comissao']     !== null ? floatval($d['pct_comissao'])     : null;
        // 1ª Fatura em 5 parcelas; valor_primeira_fatura guarda o TOTAL (soma).
        $fat = [];
        for ($k = 1; $k <= 5; $k++) {
            $v = $d['fatura_' . $k] ?? null;
            $fat[$k] = ($v !== '' && $v !== null) ? floatval($v) : null;
        }
        $somaFat = 0; $temFat = false;
        foreach ($fat as $v) { if ($v !== null) { $somaFat += $v; $temFat = true; } }
        $fatura    = $temFat ? $somaFat
                     : (isset($d['valor_primeira_fatura']) && $d['valor_primeira_fatura'] !== '' && $d['valor_primeira_fatura'] !== null ? floatval($d['valor_primeira_fatura']) : null);
        $obs       = trim($d['observacao'] ?? '') ?: null;

        try {
            if ($isEdit) {
                $stmt = db()->prepare("UPDATE fechamento SET
                    tipo=?, entrada=?, ramo=?, operadora=?, mes=?, empresa=?, cnpj=?, analista=?,
                    vigencia_operadora=?, numero_vidas=?, pct_agenciamento=?,
                    agenciamento_1=?, agenciamento_2=?, agenciamento_3=?, agenciamento_4=?, agenciamento_5=?,
                    pct_comissao=?, valor_primeira_fatura=?,
                    fatura_1=?, fatura_2=?, fatura_3=?, fatura_4=?, fatura_5=?,
                    observacao=?
                    WHERE id=?");
                $stmt->execute([
                    $tipo, $entrada, $ramo, $operadora, $mes, $empresa, $cnpj, $analista,
                    $vig, $vidas, $agenc,
                    $ag[1], $ag[2], $ag[3], $ag[4], $ag[5],
                    $comis, $fatura,
                    $fat[1], $fat[2], $fat[3], $fat[4], $fat[5],
                    $obs, $id
                ]);
                json(['ok' => true]);
            } else {
                $stmt = db()->prepare("INSERT INTO fechamento
                    (tipo, entrada, ramo, operadora, mes, empresa, cnpj, analista,
                     vigencia_operadora, numero_vidas, pct_agenciamento,
                     agenciamento_1, agenciamento_2, agenciamento_3, agenciamento_4, agenciamento_5,
                     pct_comissao, valor_primeira_fatura,
                     fatura_1, fatura_2, fatura_3, fatura_4, fatura_5,
                     observacao)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $tipo, $entrada, $ramo, $operadora, $mes, $empresa, $cnpj, $analista,
                    $vig, $vidas, $agenc,
                    $ag[1], $ag[2], $ag[3], $ag[4], $ag[5],
                    $comis, $fatura,
                    $fat[1], $fat[2], $fat[3], $fat[4], $fat[5],
                    $obs
                ]);
                json(['ok' => true, 'id' => db()->lastInsertId()]);
            }
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao salvar fechamento: ' . $e->getMessage()]);
        }
        break;

    case 'excluir_fechamento':
        $d  = jsonBody();
        $id = intval($d['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);
        try {
            db()->prepare("DELETE FROM fechamento WHERE id = ?")->execute([$id]);
            json(['ok' => true]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao excluir: ' . $e->getMessage()]);
        }
        break;

    // ═══════════════════════════════════════════════════
    //  ATAS DE VISITAS (modelo TACOM)
    //  Acesso: admin (CRUD) + visualizador (read-only)
    // ═══════════════════════════════════════════════════
    case 'listar_atas':
        if (!iw_ve_tudo()) json(['ok' => false, 'msg' => 'Acesso negado']);
        iw_atas_softdel();
        $sql    = "SELECT a.*,
                          (SELECT COUNT(*) FROM atas_visitas_encaminhamentos e WHERE e.ata_id = a.id) AS qtd_encaminhamentos,
                          (SELECT COUNT(*) FROM atas_visitas_encaminhamentos e WHERE e.ata_id = a.id AND e.status='Pendente') AS qtd_pendentes
                   FROM atas_visitas a
                   WHERE a.excluida_em IS NULL";
        $params = [];

        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $like = "%{$search}%";
            $sql .= " AND (a.empresa LIKE ? OR a.grupo LIKE ? OR a.seguradora LIKE ? OR a.responsavel LIKE ? OR a.objetivo LIKE ?)";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        foreach (['formato','percepcao','status','seguradora','grupo'] as $f) {
            $v = trim($_GET[$f] ?? '');
            if ($v) { $sql .= " AND a.$f = ?"; $params[] = $v; }
        }
        $de  = trim($_GET['data_de']  ?? '');
        $ate = trim($_GET['data_ate'] ?? '');
        if ($de)  { $sql .= " AND a.data_visita >= ?"; $params[] = $de; }
        if ($ate) { $sql .= " AND a.data_visita <= ?"; $params[] = $ate; }

        $sql .= " ORDER BY a.data_visita DESC, a.id DESC";

        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        }
        break;

    case 'buscar_ata':
        if (!iw_ve_tudo()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $id = intval($_GET['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);
        $stmt = db()->prepare("SELECT * FROM atas_visitas WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) json(['ok' => false, 'msg' => 'Ata não encontrada']);

        // Carrega encaminhamentos
        $st2 = db()->prepare("SELECT * FROM atas_visitas_encaminhamentos WHERE ata_id = ? ORDER BY id ASC");
        $st2->execute([$id]);
        $row['encaminhamentos'] = $st2->fetchAll(PDO::FETCH_ASSOC);

        json(['ok' => true, 'data' => $row]);
        break;

    case 'stats_atas':
        if (!iw_ve_tudo()) json(['ok' => false, 'msg' => 'Acesso negado']);
        iw_atas_softdel();
        try {
            $r = db()->query("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN data_visita >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
                              AND data_visita <  DATE_ADD(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 1 MONTH)
                             THEN 1 ELSE 0 END) AS mes_atual,
                    SUM(CASE WHEN formato = 'Presencial' THEN 1 ELSE 0 END) AS presencial,
                    SUM(CASE WHEN formato = 'Online'     THEN 1 ELSE 0 END) AS online,
                    SUM(CASE WHEN status   = 'Rascunho'  THEN 1 ELSE 0 END) AS rascunho
                FROM atas_visitas
                WHERE excluida_em IS NULL
            ")->fetch(PDO::FETCH_ASSOC);
            $p = db()->query("
                SELECT COUNT(*) FROM atas_visitas_encaminhamentos e
                INNER JOIN atas_visitas a ON a.id = e.ata_id
                WHERE e.status = 'Pendente' AND a.excluida_em IS NULL
            ")->fetchColumn();
            $r['encaminhamentos_pendentes'] = intval($p);
            json(['ok' => true, 'data' => $r]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        }
        break;

    case 'agendar_visita':
        // Agendamento leve: cria a ata já como "Agendada" (aparece na Programação/kanban).
        if (!iw_ve_tudo()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody();
        $empresaAg = trim($d['empresa'] ?? '');
        $dataAg    = $d['data_visita'] ?? null;
        if ($empresaAg === '' || !$dataAg) json(['ok' => false, 'msg' => 'Informe a empresa e a data da visita.']);
        try {
            $st = db()->prepare("INSERT INTO atas_visitas
                (empresa_id, grupo, empresa, seguradora, data_visita, horario, formato,
                 objetivo, responsavel, analista_id, analista_nome, data_registro, status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'Agendada')");
            $st->execute([
                intval($d['empresa_id'] ?? 0) ?: null,
                trim($d['grupo'] ?? ''), $empresaAg, trim($d['seguradora'] ?? ''),
                $dataAg, (trim($d['horario'] ?? '') ?: null), (trim($d['formato'] ?? '') ?: null),
                trim($d['objetivo'] ?? ''), trim($d['responsavel'] ?? ''),
                intval($d['analista_id'] ?? 0) ?: null, trim($d['analista_nome'] ?? ''),
                date('Y-m-d')
            ]);
            json(['ok' => true, 'id' => db()->lastInsertId()]);
        } catch (PDOException $e) { json(['ok' => false, 'msg' => 'Erro ao agendar: ' . $e->getMessage()]); }
        break;

    case 'criar_ata':
        if (!iw_edita_renovacao()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d = jsonBody();

        try {
            db()->beginTransaction();

            $stmt = db()->prepare("
                INSERT INTO atas_visitas
                    (empresa_id, grupo, empresa, seguradora,
                     data_visita, horario, formato,
                     participantes_empresa, participantes_interweg, participantes_operadora,
                     objetivo, temas_abordados, observacoes,
                     percepcao, responsavel, analista_id, analista_nome, data_registro, status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                intval($d['empresa_id'] ?? 0) ?: null,
                trim($d['grupo']      ?? ''),
                trim($d['empresa']    ?? ''),
                trim($d['seguradora'] ?? ''),
                $d['data_visita'] ?: null,
                trim($d['horario']  ?? ''),
                trim($d['formato']  ?? 'Presencial'),
                trim($d['participantes_empresa']   ?? ''),
                trim($d['participantes_interweg']  ?? ''),
                trim($d['participantes_operadora'] ?? ''),
                trim($d['objetivo']         ?? ''),
                trim($d['temas_abordados']  ?? ''),
                trim($d['observacoes']      ?? ''),
                trim($d['percepcao']        ?? '') ?: null,
                trim($d['responsavel']      ?? ''),
                intval($d['analista_id']    ?? 0) ?: null,
                trim($d['analista_nome']    ?? ''),
                $d['data_registro'] ?: date('Y-m-d'),
                trim($d['status']           ?? 'Rascunho'),
            ]);
            $newId = db()->lastInsertId();

            // Encaminhamentos (array)
            $encs = $d['encaminhamentos'] ?? [];
            if (is_array($encs)) {
                $stE = db()->prepare("
                    INSERT INTO atas_visitas_encaminhamentos
                        (ata_id, descricao, responsavel, responsavel_interno, prazo, status)
                    VALUES (?,?,?,?,?,?)
                ");
                foreach ($encs as $e) {
                    $desc = trim($e['descricao'] ?? '');
                    if (!$desc) continue;
                    $stE->execute([
                        $newId,
                        $desc,
                        trim($e['responsavel'] ?? ''),
                        trim($e['responsavel_interno'] ?? '') ?: null,
                        $e['prazo'] ?: null,
                        trim($e['status'] ?? 'Pendente'),
                    ]);
                }
            }

            db()->commit();
            json(['ok' => true, 'id' => $newId]);
        } catch (PDOException $e) {
            db()->rollBack();
            json(['ok' => false, 'msg' => 'Erro ao criar ata: ' . $e->getMessage()]);
        }
        break;

    case 'editar_ata':
        if (!iw_edita_renovacao()) json(['ok' => false, 'msg' => 'Acesso negado']);
        $d  = jsonBody();
        $id = intval($d['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);

        try {
            db()->beginTransaction();

            $stmt = db()->prepare("
                UPDATE atas_visitas SET
                    empresa_id = ?, grupo = ?, empresa = ?, seguradora = ?,
                    data_visita = ?, horario = ?, formato = ?,
                    participantes_empresa = ?, participantes_interweg = ?, participantes_operadora = ?,
                    objetivo = ?, temas_abordados = ?, observacoes = ?,
                    percepcao = ?, responsavel = ?, analista_id = ?, analista_nome = ?,
                    data_registro = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                intval($d['empresa_id'] ?? 0) ?: null,
                trim($d['grupo']      ?? ''),
                trim($d['empresa']    ?? ''),
                trim($d['seguradora'] ?? ''),
                $d['data_visita'] ?: null,
                trim($d['horario']  ?? ''),
                trim($d['formato']  ?? 'Presencial'),
                trim($d['participantes_empresa']   ?? ''),
                trim($d['participantes_interweg']  ?? ''),
                trim($d['participantes_operadora'] ?? ''),
                trim($d['objetivo']         ?? ''),
                trim($d['temas_abordados']  ?? ''),
                trim($d['observacoes']      ?? ''),
                trim($d['percepcao']        ?? '') ?: null,
                trim($d['responsavel']      ?? ''),
                intval($d['analista_id']    ?? 0) ?: null,
                trim($d['analista_nome']    ?? ''),
                $d['data_registro'] ?: null,
                trim($d['status']           ?? 'Rascunho'),
                $id,
            ]);

            // Encaminhamentos: substituição completa (delete + insert)
            // Preserva demanda_id de itens que já existiam (match por id)
            $encs = $d['encaminhamentos'] ?? null;
            if (is_array($encs)) {
                // Pega demandas vinculadas existentes pra não perder
                $stExist = db()->prepare("SELECT id, demanda_id FROM atas_visitas_encaminhamentos WHERE ata_id = ?");
                $stExist->execute([$id]);
                $existMap = [];
                foreach ($stExist->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if ($row['demanda_id']) $existMap[intval($row['id'])] = intval($row['demanda_id']);
                }
                db()->prepare("DELETE FROM atas_visitas_encaminhamentos WHERE ata_id = ?")->execute([$id]);
                $insE = db()->prepare("INSERT INTO atas_visitas_encaminhamentos (ata_id, descricao, responsavel, responsavel_interno, prazo, status, demanda_id) VALUES (?,?,?,?,?,?,?)");
                foreach ($encs as $e) {
                    $oldId = intval($e['id'] ?? 0);
                    $insE->execute([
                        $id,
                        trim($e['descricao']  ?? ''),
                        trim($e['responsavel']?? ''),
                        trim($e['responsavel_interno'] ?? '') ?: null,
                        $e['prazo'] ?: null,
                        trim($e['status']     ?? 'Pendente'),
                        $oldId && isset($existMap[$oldId]) ? $existMap[$oldId] : (intval($e['demanda_id'] ?? 0) ?: null),
                    ]);
                }
            }
            // COMMIT obrigatório — sem isso o beginTransaction() acima é
            // descartado quando o script PHP termina, e nada é persistido.
            // (esse era o bug: ata "salvava" mas ao reabrir voltava ao estado antigo)
            db()->commit();
            json(['ok' => true]);
        } catch (PDOException $e) {
            if (db()->inTransaction()) db()->rollBack();
            json(['ok' => false, 'msg' => 'Erro ao salvar ata: ' . $e->getMessage()]);
        }
        break;

    case 'excluir_ata':
        // Soft-delete: marca a ata como excluída (recuperável). Os encaminhamentos e
        // as demandas já geradas são preservados — a ata some das listagens até ser restaurada.
        if (!iw_edita_renovacao()) json(['ok' => false, 'msg' => 'Acesso negado']);
        iw_atas_softdel();
        $id = intval($_GET['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);
        try {
            db()->prepare("UPDATE atas_visitas SET excluida_em = NOW() WHERE id = ?")->execute([$id]);
            json(['ok' => true]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao excluir ata: ' . $e->getMessage()]);
        }
        break;

    case 'restaurar_ata':
        // Desfaz a exclusão: traz a ata de volta às listagens.
        if (!iw_edita_renovacao()) json(['ok' => false, 'msg' => 'Acesso negado']);
        iw_atas_softdel();
        $id = intval($_GET['id'] ?? 0);
        if (!$id) json(['ok' => false, 'msg' => 'ID inválido']);
        try {
            db()->prepare("UPDATE atas_visitas SET excluida_em = NULL WHERE id = ?")->execute([$id]);
            json(['ok' => true]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao restaurar ata: ' . $e->getMessage()]);
        }
        break;

    case 'listar_atas_excluidas':
        // Lixeira: atas marcadas como excluídas, mais recentes primeiro.
        if (!iw_ve_tudo()) json(['ok' => false, 'msg' => 'Acesso negado']);
        iw_atas_softdel();
        try {
            $rows = db()->query("
                SELECT id, grupo, empresa, seguradora, data_visita, status, responsavel, analista_nome, excluida_em
                FROM atas_visitas
                WHERE excluida_em IS NOT NULL
                ORDER BY excluida_em DESC, id DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            json(['ok' => true, 'data' => $rows]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        }
        break;

    case 'enc_gerar_demanda':
        // Cria uma DEMANDA a partir de um encaminhamento da ata.
        // Vincula via atas_visitas_encaminhamentos.demanda_id pra rastreabilidade.
        if (!isAdmin()) json(['ok' => false, 'msg' => 'Apenas admin pode gerar demandas']);
        $d = jsonBody();
        $encId = intval($d['enc_id'] ?? 0);
        if (!$encId) json(['ok' => false, 'msg' => 'ID do encaminhamento inválido']);
        try {
            // Busca o encaminhamento + dados da ata
            $stE = db()->prepare("
                SELECT e.id, e.ata_id, e.descricao, e.responsavel, e.responsavel_interno,
                       e.prazo, e.status, e.demanda_id,
                       a.grupo, a.empresa, a.seguradora, a.empresa_id,
                       a.responsavel AS ata_responsavel, a.analista_id, a.analista_nome
                FROM atas_visitas_encaminhamentos e
                INNER JOIN atas_visitas a ON a.id = e.ata_id
                WHERE e.id = ? LIMIT 1
            ");
            $stE->execute([$encId]);
            $enc = $stE->fetch(PDO::FETCH_ASSOC);
            if (!$enc) json(['ok' => false, 'msg' => 'Encaminhamento não encontrado']);
            if ($enc['demanda_id']) json(['ok' => false, 'msg' => 'Esse encaminhamento já gerou a demanda #'.$enc['demanda_id']]);

            // Resolve ramo da empresa (se houver)
            $ramo = '';
            if ($enc['empresa_id']) {
                try {
                    $stR = db()->prepare("SELECT ramo FROM empresas WHERE id = ? LIMIT 1");
                    $stR->execute([$enc['empresa_id']]);
                    $ramo = trim((string)$stR->fetchColumn());
                } catch (PDOException $e) {}
            }

            // Insere a demanda
            $detalhe = "Origem: Ata de Visita #{$enc['ata_id']}\n";
            if ($enc['responsavel_interno']) $detalhe .= "Responsável interno: {$enc['responsavel_interno']}\n";
            if ($enc['responsavel'])         $detalhe .= "Responsável externo: {$enc['responsavel']}\n";

            $stIns = db()->prepare("
                INSERT INTO demandas
                    (origem, grupo, empresa, seguradora, ramo, tipo_demanda, situacao,
                     descricao_demanda, analista_id, analista_nome,
                     recebimento, inicio_tratativa, proxima_acao, detalhamento)
                VALUES ('Ata de Visita', ?, ?, ?, ?, 'Acompanhamento', 'Em análise',
                        ?, ?, ?, NOW(), NOW(), ?, ?)
            ");
            $stIns->execute([
                $enc['grupo'] ?? '',
                $enc['empresa'] ?? '',
                $enc['seguradora'] ?? '',
                $ramo,
                $enc['descricao'] ?? '',
                $enc['analista_id'] ?: null,
                $enc['analista_nome'] ?? '',
                $enc['prazo'] ?: null,
                $detalhe
            ]);
            $demandaId = db()->lastInsertId();

            // Vincula o encaminhamento à demanda criada e move pra "Em andamento"
            db()->prepare("UPDATE atas_visitas_encaminhamentos
                           SET demanda_id = ?, status = 'Em andamento'
                           WHERE id = ?")->execute([$demandaId, $encId]);

            json(['ok' => true, 'demanda_id' => intval($demandaId)]);
        } catch (PDOException $e) {
            json(['ok' => false, 'msg' => 'Erro ao gerar demanda: ' . $e->getMessage()]);
        }
        break;

    default:
        json(['ok' => false, 'msg' => 'Ação desconhecida: ' . $action]);
}
// =============================================================
// PADDING DEFENSIVO contra truncamento do File Manager Hostinger
// =============================================================
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// FIM REAL
