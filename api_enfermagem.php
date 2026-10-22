<?php
/**
 * =====================================================
 * API ENFERMAGEM - INTERWEG Benefícios
 * =====================================================
 * Endpoints separados pra área de Enfermagem:
 * - demandas_assistenciais (CRUD + stats)
 * - acoes_saude            (CRUD + stats)
 *
 * Acesso (por enquanto): apenas admin.
 * Quando liberar para enfermeiras, ajustar `canAccessEnfermagem()`.
 */

require_once 'config.php';
auth();

header('Content-Type: application/json; charset=utf-8');

// ── Migração (modelo da planilha padronizada): colunas novas + status ──
// Idempotente e silenciosa. Roda em qualquer requisição da API de enfermagem.
try {
    db()->exec("ALTER TABLE acoes_saude ADD COLUMN IF NOT EXISTS local VARCHAR(255) NULL");
    db()->exec("ALTER TABLE acoes_saude ADD COLUMN IF NOT EXISTS motivo TEXT NULL");
    db()->exec("ALTER TABLE acoes_saude ADD COLUMN IF NOT EXISTS custo_realizado DECIMAL(10,2) NULL");
    // Status no formato da planilha: Agendada / Realizada / Desmarcada
    db()->exec("UPDATE acoes_saude SET status='Agendada'  WHERE status IN ('Agendado','Em planejamento','Em Planejamento')");
    db()->exec("UPDATE acoes_saude SET status='Realizada'  WHERE status='Realizado'");
    db()->exec("UPDATE acoes_saude SET status='Desmarcada' WHERE status IN ('Cancelado','Cancelada')");
} catch (Throwable $e) { /* ignora */ }

// Helpers — jsonOut() já é fornecido pelo config.php (não redeclarar!)
if (!function_exists('jsonBody')) {
    function jsonBody() {
        return json_decode(file_get_contents('php://input'), true) ?: [];
    }
}

// Garante que QUALQUER erro (inclusive fatais que não são PDOException)
// volte como JSON legível, em vez de um 500 com corpo vazio.
set_exception_handler(function ($e) {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Erro fatal: ' . $err['message']], JSON_UNESCAPED_UNICODE);
    }
});

// Quem pode acessar a área de Enfermagem
function canAccessEnfermagem(): bool {
    // Admin e Enfermeiras têm acesso à área de Enfermagem.
    return isAdmin() || isEnfermeira();
}

function enfermagemOnly() {
    if (!canAccessEnfermagem()) {
        jsonOut(['ok' => false, 'msg' => 'Acesso negado']);
    }
}

// Visualizador e o escopo Renovação também podem VER (mas não editar)
function canViewEnfermagem(): bool {
    return canAccessEnfermagem() || isVisualizador()
        || (strtolower(trim($_SESSION['user_tipo'] ?? '')) === 'renovacao');
}

$action = $_GET['action'] ?? '';

// Bloqueia tudo que não seja visualização para quem não pode editar
$readOnlyActions = ['listar_assistenciais', 'buscar_assistencial', 'stats_assistenciais',
                     'listar_acoes_saude', 'buscar_acao_saude', 'stats_acoes_saude',
                     'listar_catalogo_servicos'];
// Escopo Renovação pode ESCREVER nas Ações de Saúde e no Catálogo (não nas Demandas Assistenciais).
$acoesSaudeWrite = ['criar_acao_saude','editar_acao_saude','excluir_acao_saude','criar_lote_acao_saude',
                    'salvar_catalogo_servico','excluir_catalogo_servico','importar_catalogo_do_historico'];
$ehRenovEnf   = (strtolower(trim($_SESSION['user_tipo'] ?? '')) === 'renovacao');
$podeEscrever = canAccessEnfermagem() || ($ehRenovEnf && in_array($action, $acoesSaudeWrite, true));
if (!in_array($action, $readOnlyActions, true) && !$podeEscrever) {
    jsonOut(['ok' => false, 'msg' => 'Acesso negado']);
}
if (in_array($action, $readOnlyActions, true) && !canViewEnfermagem()) {
    jsonOut(['ok' => false, 'msg' => 'Acesso negado']);
}

switch ($action) {

    // ═══════════════════════════════════════════════════
    //  DEMANDAS ASSISTENCIAIS
    // ═══════════════════════════════════════════════════
    case 'listar_assistenciais':
        $sql    = "SELECT d.*, COALESCE(a.nome, d.analista_nome) AS analista_nome
                   FROM demandas_assistenciais d
                   LEFT JOIN analistas a ON d.analista_id = a.id
                   WHERE 1=1";
        $params = [];

        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $like = "%{$search}%";
            $sql .= " AND (d.dados_beneficiario LIKE ? OR d.empresa LIKE ? OR d.grupo LIKE ? OR d.descricao_demanda LIKE ?)";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        foreach (['situacao','tipo_demanda','ramo','seguradora'] as $f) {
            $v = trim($_GET[$f] ?? '');
            if ($v) { $sql .= " AND d.$f = ?"; $params[] = $v; }
        }
        $analista = trim($_GET['analista'] ?? '');
        if ($analista) {
            $sql .= " AND COALESCE(a.nome, d.analista_nome) = ?";
            $params[] = $analista;
        }

        $sql .= " ORDER BY d.recebimento DESC, d.id DESC";

        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            jsonOut(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            jsonOut(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        }
        break;

    case 'buscar_assistencial':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonOut(['ok' => false, 'msg' => 'ID inválido']);
        $stmt = db()->prepare("SELECT d.*, COALESCE(a.nome, d.analista_nome) AS analista_nome
                                FROM demandas_assistenciais d
                                LEFT JOIN analistas a ON d.analista_id = a.id
                                WHERE d.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) jsonOut(['ok' => false, 'msg' => 'Demanda não encontrada']);
        jsonOut(['ok' => true, 'data' => $row]);
        break;

    case 'criar_assistencial':
    case 'editar_assistencial':
        $isEdit = ($action === 'editar_assistencial');
        $d = jsonBody();
        $id = intval($d['id'] ?? 0);
        if ($isEdit && !$id) jsonOut(['ok' => false, 'msg' => 'ID inválido']);

        $analistaId = isset($d['analista_id']) ? intval($d['analista_id']) : null;
        if (!$analistaId && !empty($d['analista_nome'])) {
            try {
                $stA = db()->prepare("SELECT id FROM analistas WHERE nome = ? AND ativo = 1 LIMIT 1");
                $stA->execute([$d['analista_nome']]);
                $analistaId = intval($stA->fetchColumn()) ?: null;
            } catch (PDOException $e) {}
        }

        $fields = [
            'grupo'              => $d['grupo']              ?? null,
            'empresa'            => $d['empresa']            ?? null,
            'seguradora'         => $d['seguradora']         ?? null,
            'ramo'               => $d['ramo']               ?? null,
            'tipo_demanda'       => $d['tipo_demanda']       ?? 'Outros',
            'situacao'           => $d['situacao']           ?? 'A iniciar',
            'recebimento'        => $d['recebimento']        ?? null,
            'proxima_acao'       => $d['proxima_acao']       ?? null,
            'data_conclusao'     => $d['data_conclusao']     ?? null,
            'dados_beneficiario' => $d['dados_beneficiario'] ?? null,
            'descricao_demanda'  => $d['descricao_demanda']  ?? null,
            'analista_id'        => $analistaId,
            'analista_nome'      => $d['analista_nome']      ?? null,
        ];
        // Normaliza string vazia em null
        foreach ($fields as $k => $v) { if ($v === '') $fields[$k] = null; }

        try {
            if ($isEdit) {
                $sets = array_map(function($c){ return "$c = ?"; }, array_keys($fields));
                $stmt = db()->prepare("UPDATE demandas_assistenciais SET " . implode(', ', $sets) . " WHERE id = ?");
                $stmt->execute(array_merge(array_values($fields), [$id]));
            } else {
                $cols = implode(',', array_keys($fields));
                $ph   = implode(',', array_fill(0, count($fields), '?'));
                $stmt = db()->prepare("INSERT INTO demandas_assistenciais ($cols) VALUES ($ph)");
                $stmt->execute(array_values($fields));
                $id = db()->lastInsertId();
            }
            jsonOut(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            jsonOut(['ok' => false, 'msg' => 'Erro ao salvar: ' . $e->getMessage()]);
        }
        break;

    case 'criar_lote_assistencial':
        $d = jsonBody();
        $linhas = $d['linhas'] ?? [];
        if (!is_array($linhas) || !count($linhas)) {
            jsonOut(['ok' => false, 'msg' => 'Nenhuma linha enviada']);
        }
        if (count($linhas) > 50) {
            jsonOut(['ok' => false, 'msg' => 'Máximo de 50 linhas por lote']);
        }
        // cache de analista por nome (insensível a acento/caixa)
        $normalize = function ($s) {
            $s = mb_strtolower(trim((string)$s), 'UTF-8');
            $s = preg_replace('/\s+/u', ' ', $s);
            return $s;
        };
        $analistasCache = null;
        $resolveAnalista = function ($nome) use (&$analistasCache, $normalize) {
            if (!$nome) return null;
            if ($analistasCache === null) {
                $analistasCache = [];
                try {
                    foreach (db()->query("SELECT id, nome FROM analistas WHERE ativo = 1")->fetchAll(PDO::FETCH_ASSOC) as $a) {
                        $analistasCache[$normalize($a['nome'])] = (int)$a['id'];
                    }
                } catch (\Throwable $e) {}
            }
            return $analistasCache[$normalize($nome)] ?? null;
        };

        $cols = ['grupo','empresa','seguradora','ramo','tipo_demanda','situacao',
                 'recebimento','proxima_acao','data_conclusao','dados_beneficiario',
                 'descricao_demanda','analista_id','analista_nome'];
        $ph   = implode(',', array_fill(0, count($cols), '?'));
        $inserted = 0;
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO demandas_assistenciais (" . implode(',', $cols) . ") VALUES ($ph)");
            foreach ($linhas as $ln) {
                $tipo = trim((string)($ln['tipo_demanda'] ?? ''));
                if ($tipo === '') continue; // tipo é obrigatório
                $analistaNome = trim((string)($ln['analista_nome'] ?? ''));
                $vals = [
                    ($ln['grupo']              ?? '') !== '' ? $ln['grupo']              : null,
                    ($ln['empresa']            ?? '') !== '' ? $ln['empresa']            : null,
                    ($ln['seguradora']         ?? '') !== '' ? $ln['seguradora']         : null,
                    ($ln['ramo']               ?? '') !== '' ? $ln['ramo']               : null,
                    $tipo,
                    ($ln['situacao']           ?? '') !== '' ? $ln['situacao']           : 'A iniciar',
                    ($ln['recebimento']        ?? '') !== '' ? $ln['recebimento']        : null,
                    ($ln['proxima_acao']       ?? '') !== '' ? $ln['proxima_acao']       : null,
                    ($ln['data_conclusao']     ?? '') !== '' ? $ln['data_conclusao']     : null,
                    ($ln['dados_beneficiario'] ?? '') !== '' ? $ln['dados_beneficiario'] : null,
                    ($ln['descricao_demanda']  ?? '') !== '' ? $ln['descricao_demanda']  : null,
                    $resolveAnalista($analistaNome),
                    $analistaNome !== '' ? $analistaNome : null,
                ];
                $stmt->execute($vals);
                $inserted++;
            }
            $pdo->commit();
            jsonOut(['ok' => true, 'inserted' => $inserted]);
        } catch (\Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            jsonOut(['ok' => false, 'msg' => 'Erro ao salvar o lote: ' . $e->getMessage()]);
        }
        break;

    case 'excluir_assistencial':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonOut(['ok' => false, 'msg' => 'ID inválido']);
        try {
            db()->prepare("DELETE FROM demandas_assistenciais WHERE id = ?")->execute([$id]);
            jsonOut(['ok' => true]);
        } catch (PDOException $e) {
            jsonOut(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        }
        break;

    case 'stats_assistenciais':
        try {
            $st = db()->prepare("SELECT COUNT(*) AS total,
                                        SUM(CASE WHEN situacao = 'Em andamento' THEN 1 ELSE 0 END) AS andamento,
                                        SUM(CASE WHEN situacao = 'Concluído' THEN 1 ELSE 0 END) AS concluido,
                                        SUM(CASE WHEN situacao = 'A iniciar' THEN 1 ELSE 0 END) AS iniciar,
                                        SUM(CASE WHEN proxima_acao < CURDATE() AND situacao != 'Concluído' THEN 1 ELSE 0 END) AS atrasadas
                                 FROM demandas_assistenciais");
            $st->execute();
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            jsonOut(['ok' => true, 'data' => [
                'total'     => intval($r['total']     ?? 0),
                'andamento' => intval($r['andamento'] ?? 0),
                'concluido' => intval($r['concluido'] ?? 0),
                'iniciar'   => intval($r['iniciar']   ?? 0),
                'atrasadas' => intval($r['atrasadas'] ?? 0),
            ]]);
        } catch (PDOException $e) {
            jsonOut(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        }
        break;

    // ═══════════════════════════════════════════════════
    //  AÇÕES DE SAÚDE
    // ═══════════════════════════════════════════════════
    case 'listar_acoes_saude':
        $sql    = "SELECT a.*, COALESCE(an.nome, a.analista_nome) AS analista_nome
                   FROM acoes_saude a
                   LEFT JOIN analistas an ON a.analista_id = an.id
                   WHERE 1=1";
        $params = [];

        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $like = "%{$search}%";
            $sql .= " AND (a.empresa LIKE ? OR a.grupo LIKE ? OR a.tema LIKE ? OR a.responsavel LIKE ?)";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        foreach (['status','tipo_servico','porte'] as $f) {
            $v = trim($_GET[$f] ?? '');
            if ($v) { $sql .= " AND a.$f = ?"; $params[] = $v; }
        }
        $grupo = trim($_GET['grupo'] ?? '');
        if ($grupo) { $sql .= " AND a.grupo = ?"; $params[] = $grupo; }

        $de  = trim($_GET['data_de']  ?? '');
        $ate = trim($_GET['data_ate'] ?? '');
        if ($de)  { $sql .= " AND a.data >= ?"; $params[] = $de;  }
        if ($ate) { $sql .= " AND a.data <= ?"; $params[] = $ate; }

        $sql .= " ORDER BY a.data DESC, a.horario DESC, a.id DESC";

        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            jsonOut(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            jsonOut(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        }
        break;

    case 'buscar_acao_saude':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonOut(['ok' => false, 'msg' => 'ID inválido']);
        $stmt = db()->prepare("SELECT a.*, COALESCE(an.nome, a.analista_nome) AS analista_nome
                                FROM acoes_saude a
                                LEFT JOIN analistas an ON a.analista_id = an.id
                                WHERE a.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) jsonOut(['ok' => false, 'msg' => 'Ação não encontrada']);
        jsonOut(['ok' => true, 'data' => $row]);
        break;

    case 'criar_acao_saude':
    case 'editar_acao_saude':
        $isEdit = ($action === 'editar_acao_saude');
        $d = jsonBody();
        $id = intval($d['id'] ?? 0);
        if ($isEdit && !$id) jsonOut(['ok' => false, 'msg' => 'ID inválido']);

        $analistaId = isset($d['analista_id']) ? intval($d['analista_id']) : null;
        if (!$analistaId && !empty($d['analista_nome'])) {
            try {
                $stA = db()->prepare("SELECT id FROM analistas WHERE nome = ? AND ativo = 1 LIMIT 1");
                $stA->execute([$d['analista_nome']]);
                $analistaId = intval($stA->fetchColumn()) ?: null;
            } catch (PDOException $e) {}
        }

        $fields = [
            'grupo'           => $d['grupo']           ?? null,
            'empresa'         => $d['empresa']         ?? null,
            'porte'           => $d['porte']           ?? null,
            'tipo_servico'    => $d['tipo_servico']    ?? null,
            'tema'            => $d['tema']            ?? null,
            'data'            => $d['data']            ?? null,
            'horario'         => $d['horario']         ?? null,
            'responsavel'     => $d['responsavel']     ?? null,
            'local'           => $d['local']           ?? null,
            'endereco'        => $d['endereco']        ?? null,
            'motivo'          => $d['motivo']          ?? null,
            'status'          => $d['status']          ?? 'Agendada',
            'custo_previsto'  => isset($d['custo_previsto']) && $d['custo_previsto'] !== '' ? $d['custo_previsto'] : null,
            'custo_realizado' => isset($d['custo_realizado']) && $d['custo_realizado'] !== '' ? $d['custo_realizado'] : null,
            'transporte'      => isset($d['transporte']) && $d['transporte'] !== '' ? $d['transporte'] : null,
            'analista_id'     => $analistaId,
            'analista_nome'   => $d['analista_nome']   ?? null,
        ];
        foreach ($fields as $k => $v) { if ($v === '') $fields[$k] = null; }

        try {
            if ($isEdit) {
                $sets = array_map(function($c){ return "$c = ?"; }, array_keys($fields));
                $stmt = db()->prepare("UPDATE acoes_saude SET " . implode(', ', $sets) . " WHERE id = ?");
                $stmt->execute(array_merge(array_values($fields), [$id]));
            } else {
                $cols = implode(',', array_keys($fields));
                $ph   = implode(',', array_fill(0, count($fields), '?'));
                $stmt = db()->prepare("INSERT INTO acoes_saude ($cols) VALUES ($ph)");
                $stmt->execute(array_values($fields));
                $id = db()->lastInsertId();
            }
            jsonOut(['ok' => true, 'id' => $id]);
        } catch (PDOException $e) {
            jsonOut(['ok' => false, 'msg' => 'Erro ao salvar: ' . $e->getMessage()]);
        }
        break;

    case 'excluir_acao_saude':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonOut(['ok' => false, 'msg' => 'ID inválido']);
        try {
            db()->prepare("DELETE FROM acoes_saude WHERE id = ?")->execute([$id]);
            jsonOut(['ok' => true]);
        } catch (PDOException $e) {
            jsonOut(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        }
        break;

    case 'stats_acoes_saude':
        try {
            $st = db()->prepare("SELECT COUNT(*) AS total,
                                        SUM(CASE WHEN status = 'Agendado' THEN 1 ELSE 0 END) AS agendado,
                                        SUM(CASE WHEN status = 'Realizado' THEN 1 ELSE 0 END) AS realizado,
                                        SUM(CASE WHEN status = 'Em planejamento' THEN 1 ELSE 0 END) AS planejamento,
                                        SUM(CASE WHEN status = 'Cancelado' THEN 1 ELSE 0 END) AS cancelado,
                                        COALESCE(SUM(custo_total), 0) AS custo_total_geral
                                 FROM acoes_saude");
            $st->execute();
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            jsonOut(['ok' => true, 'data' => [
                'total'             => intval($r['total']             ?? 0),
                'agendado'          => intval($r['agendado']          ?? 0),
                'realizado'         => intval($r['realizado']         ?? 0),
                'planejamento'      => intval($r['planejamento']      ?? 0),
                'cancelado'         => intval($r['cancelado']         ?? 0),
                'custo_total_geral' => floatval($r['custo_total_geral'] ?? 0),
            ]]);
        } catch (PDOException $e) {
            jsonOut(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        }
        break;

    case 'criar_lote_acao_saude':
        $d = jsonBody();
        $linhas = $d['linhas'] ?? [];
        if (!is_array($linhas) || !count($linhas)) {
            jsonOut(['ok' => false, 'msg' => 'Nenhuma linha enviada']);
        }
        if (count($linhas) > 50) {
            jsonOut(['ok' => false, 'msg' => 'Máximo de 50 linhas por lote']);
        }
        // resolve analista_id a partir do analista_nome (cache local)
        $normalize = function ($s) {
            $s = mb_strtolower(trim((string)$s), 'UTF-8');
            return preg_replace('/\s+/u', ' ', $s);
        };
        $analistasCache = null;
        $resolveAnalista = function ($nome) use (&$analistasCache, $normalize) {
            if (!$nome) return null;
            if ($analistasCache === null) {
                $analistasCache = [];
                try {
                    foreach (db()->query("SELECT id, nome FROM analistas WHERE ativo = 1")->fetchAll(PDO::FETCH_ASSOC) as $a) {
                        $analistasCache[$normalize($a['nome'])] = (int)$a['id'];
                    }
                } catch (\Throwable $e) {}
            }
            return $analistasCache[$normalize($nome)] ?? null;
        };

        $cols = ['grupo','empresa','porte','tipo_servico','tema','data','horario',
                 'responsavel','endereco','status','custo_previsto','transporte',
                 'analista_id','analista_nome'];
        $ph   = implode(',', array_fill(0, count($cols), '?'));
        $inserted = 0;
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO acoes_saude (" . implode(',', $cols) . ") VALUES ($ph)");
            foreach ($linhas as $ln) {
                $tipo = trim((string)($ln['tipo_servico'] ?? ''));
                if ($tipo === '') continue; // tipo de serviço é obrigatório
                $analistaNome = trim((string)($ln['analista_nome'] ?? ''));
                $vals = [
                    ($ln['grupo']        ?? '') !== '' ? $ln['grupo']        : null,
                    ($ln['empresa']      ?? '') !== '' ? $ln['empresa']      : null,
                    ($ln['porte']        ?? '') !== '' ? $ln['porte']        : null,
                    $tipo,
                    ($ln['tema']         ?? '') !== '' ? $ln['tema']         : null,
                    ($ln['data']         ?? '') !== '' ? $ln['data']         : null,
                    ($ln['horario']      ?? '') !== '' ? $ln['horario']      : null,
                    ($ln['responsavel']  ?? '') !== '' ? $ln['responsavel']  : null,
                    ($ln['endereco']     ?? '') !== '' ? $ln['endereco']     : null,
                    ($ln['status']       ?? '') !== '' ? $ln['status']       : 'Agendada',
                    (isset($ln['custo_previsto']) && $ln['custo_previsto'] !== '' && $ln['custo_previsto'] !== null) ? $ln['custo_previsto'] : null,
                    (isset($ln['transporte'])     && $ln['transporte']     !== '' && $ln['transporte']     !== null) ? $ln['transporte']     : null,
                    $analistaNome ? $resolveAnalista($analistaNome) : null,
                    $analistaNome !== '' ? $analistaNome : null,
                ];
                $stmt->execute($vals);
                $inserted++;
            }
            $pdo->commit();
            jsonOut(['ok' => true, 'inserted' => $inserted]);
        } catch (\Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            jsonOut(['ok' => false, 'msg' => 'Erro ao salvar lote: ' . $e->getMessage()]);
        }
        break;

    // ═══════════ CATÁLOGO DE SERVIÇOS (checklist por porte) ═══════════
    case 'listar_catalogo_servicos':
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS catalogo_servicos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(120) NOT NULL,
                portes VARCHAR(255) NULL,
                ativo TINYINT NOT NULL DEFAULT 1,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $rows = db()->query("SELECT id, nome, portes, ativo FROM catalogo_servicos WHERE ativo=1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
            jsonOut(['ok' => true, 'data' => $rows]);
        } catch (Throwable $e) { jsonOut(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'data' => []]); }
        break;

    case 'salvar_catalogo_servico':
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $id    = intval($d['id'] ?? 0);
        $nome  = trim($d['nome'] ?? '');
        $portes= is_array($d['portes'] ?? null) ? implode('|', array_map('trim', $d['portes'])) : trim($d['portes'] ?? '');
        if ($nome === '') jsonOut(['ok' => false, 'msg' => 'Informe o nome do serviço.']);
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS catalogo_servicos (
                id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(120) NOT NULL, portes VARCHAR(255) NULL,
                ativo TINYINT NOT NULL DEFAULT 1, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            if ($id) {
                db()->prepare("UPDATE catalogo_servicos SET nome=?, portes=? WHERE id=?")->execute([$nome, $portes, $id]);
            } else {
                db()->prepare("INSERT INTO catalogo_servicos (nome, portes) VALUES (?,?)")->execute([$nome, $portes]);
                $id = db()->lastInsertId();
            }
            jsonOut(['ok' => true, 'id' => $id]);
        } catch (Throwable $e) { jsonOut(['ok' => false, 'msg' => 'Erro ao salvar: ' . $e->getMessage()]); }
        break;

    case 'excluir_catalogo_servico':
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = intval($d['id'] ?? 0);
        if (!$id) jsonOut(['ok' => false, 'msg' => 'ID inválido']);
        try {
            db()->prepare("DELETE FROM catalogo_servicos WHERE id=?")->execute([$id]);
            jsonOut(['ok' => true]);
        } catch (Throwable $e) { jsonOut(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]); }
        break;

    case 'importar_catalogo_do_historico':
        // Popula o catálogo com os serviços que já existem na coluna tipo_servico (sem duplicar).
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS catalogo_servicos (
                id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(120) NOT NULL, portes VARCHAR(255) NULL,
                ativo TINYINT NOT NULL DEFAULT 1, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $tipos = db()->query("SELECT DISTINCT TRIM(tipo_servico) AS t FROM acoes_saude
                                  WHERE tipo_servico IS NOT NULL AND TRIM(tipo_servico) <> ''")->fetchAll(PDO::FETCH_COLUMN);
            $exist = db()->query("SELECT LOWER(nome) FROM catalogo_servicos")->fetchAll(PDO::FETCH_COLUMN);
            $existSet = array_flip(array_map('strval', $exist));
            $ins = db()->prepare("INSERT INTO catalogo_servicos (nome, portes) VALUES (?, NULL)");
            $add = 0;
            foreach ($tipos as $t) {
                $t = trim($t); if ($t === '') continue;
                $k = mb_strtolower($t);
                if (isset($existSet[$k])) continue;
                $ins->execute([$t]); $existSet[$k] = 1; $add++;
            }
            jsonOut(['ok' => true, 'importados' => $add, 'total_distintos' => count($tipos)]);
        } catch (Throwable $e) { jsonOut(['ok' => false, 'msg' => 'Erro ao importar: ' . $e->getMessage()]); }
        break;

    default:
        jsonOut(['ok' => false, 'msg' => 'Ação inválida: ' . $action]);
}
