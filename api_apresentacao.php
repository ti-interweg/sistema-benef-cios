<?php
/**
 * API - ESTUDOS DA APRESENTAÇÃO (comparativo de custos)
 * Salva/abre estudos nomeados do comparativo (Conta Nova / Renovação / Coparticipação).
 */
require_once __DIR__ . '/config.php';
auth();
header('Content-Type: application/json; charset=utf-8');

function apj($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function apbody(){ return json_decode(file_get_contents('php://input'), true) ?: []; }

try {
    db()->exec("CREATE TABLE IF NOT EXISTS apresentacao_estudos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(180) NOT NULL,
        dados MEDIUMTEXT NULL,
        usuario VARCHAR(120) NULL,
        usuario_login VARCHAR(120) NULL,
        atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

$usr   = $_SESSION['user_nome'] ?? '';
$login = (string)($_SESSION['user_login'] ?? $_SESSION['user_id'] ?? '');
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'estudo_listar': {
        $rows = db()->query("SELECT id, nome, usuario, atualizado_em
                             FROM apresentacao_estudos ORDER BY atualizado_em DESC")->fetchAll(PDO::FETCH_ASSOC);
        apj(['ok'=>true, 'data'=>$rows]);
        break;
    }

    case 'estudo_salvar': {
        if (isVisualizador()) apj(['ok'=>false,'msg'=>'Acesso negado']);
        $d = apbody();
        $nome = trim($d['nome'] ?? '');
        $id   = intval($d['id'] ?? 0);
        $dados = isset($d['dados']) ? (is_string($d['dados']) ? $d['dados'] : json_encode($d['dados'], JSON_UNESCAPED_UNICODE)) : '';
        if ($nome === '') apj(['ok'=>false,'msg'=>'Dê um nome ao projeto.']);
        try {
            if ($id > 0) {
                db()->prepare("UPDATE apresentacao_estudos SET nome=?, dados=?, usuario=?, usuario_login=? WHERE id=?")
                   ->execute([$nome, $dados, $usr, $login, $id]);
            } else {
                // Se já existe um com o mesmo nome, sobrescreve (evita duplicar).
                $ex = db()->prepare("SELECT id FROM apresentacao_estudos WHERE nome=? LIMIT 1");
                $ex->execute([$nome]); $exId = (int)$ex->fetchColumn();
                if ($exId > 0) {
                    db()->prepare("UPDATE apresentacao_estudos SET dados=?, usuario=?, usuario_login=? WHERE id=?")
                       ->execute([$dados, $usr, $login, $exId]);
                    $id = $exId;
                } else {
                    db()->prepare("INSERT INTO apresentacao_estudos (nome, dados, usuario, usuario_login) VALUES (?,?,?,?)")
                       ->execute([$nome, $dados, $usr, $login]);
                    $id = (int)db()->lastInsertId();
                }
            }
            apj(['ok'=>true, 'id'=>$id]);
        } catch (PDOException $e) { apj(['ok'=>false,'msg'=>'Erro ao salvar: '.$e->getMessage()]); }
        break;
    }

    case 'estudo_carregar': {
        $id = intval($_GET['id'] ?? 0);
        $st = db()->prepare("SELECT id, nome, dados FROM apresentacao_estudos WHERE id=?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) apj(['ok'=>false,'msg'=>'Projeto não encontrado.']);
        apj(['ok'=>true, 'id'=>(int)$row['id'], 'nome'=>$row['nome'], 'dados'=>$row['dados']]);
        break;
    }

    case 'estudo_excluir': {
        if (isVisualizador()) apj(['ok'=>false,'msg'=>'Acesso negado']);
        $d = apbody(); $id = intval($d['id'] ?? 0);
        db()->prepare("DELETE FROM apresentacao_estudos WHERE id=?")->execute([$id]);
        apj(['ok'=>true]);
        break;
    }

    default:
        apj(['ok'=>false,'msg'=>'Ação desconhecida: '.$action]);
}
