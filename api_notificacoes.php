<?php
/* ============================================================
   API - Notificações internas (sino)
   Notificações globais (todos os usuários veem) com estado de
   leitura por usuário.
   ============================================================ */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Não autenticado']); exit; }

$uid = intval($_SESSION['user_id']);

try {
  db()->exec("CREATE TABLE IF NOT EXISTS notificacoes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      tipo VARCHAR(40) NOT NULL DEFAULT 'passagem',
      titulo VARCHAR(200) NOT NULL,
      mensagem VARCHAR(500) NULL,
      url VARCHAR(300) NULL,
      empresa_id INT NULL,
      criado_por INT NULL,
      criado_por_nome VARCHAR(120) NULL,
      criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Destinatário: NULL = todos; preenchido = só esse usuário (por nome).
  try { db()->exec("ALTER TABLE notificacoes ADD COLUMN destino_nome VARCHAR(190) NULL"); } catch (Throwable $e) {}
  db()->exec("CREATE TABLE IF NOT EXISTS notificacoes_lidas (
      notificacao_id INT NOT NULL,
      usuario_id INT NOT NULL,
      lida_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (notificacao_id, usuario_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $action = $_GET['action'] ?? 'listar';

  if ($action === 'listar') {
    // Nome do usuário logado, para filtrar notificações direcionadas a ele.
    $meuNome = trim($_SESSION['user_nome'] ?? '');
    // Notificações de PASSAGEM DE BASTÃO só para quem trabalha com isso
    // (admin/gerência/pós-venda). Analistas e enfermeiros NÃO recebem.
    $tipoU = strtolower(trim($_SESSION['user_tipo'] ?? ''));
    $verPassagem = in_array($tipoU, ['analista','enfermeira','enfermagem'], true) ? 0 : 1;
    // Últimas 30 notificações VISÍVEIS: globais (destino NULL) OU direcionadas a mim,
    // respeitando quem pode ver as de passagem.
    $st = db()->prepare(
      "SELECT n.id, n.tipo, n.titulo, n.mensagem, n.url, n.empresa_id, n.criado_por_nome, n.criado_em,
              (SELECT COUNT(*) FROM notificacoes_lidas l WHERE l.notificacao_id = n.id AND l.usuario_id = ?) AS lida
       FROM notificacoes n
       WHERE (n.destino_nome IS NULL OR n.destino_nome = '' OR n.destino_nome = ?)
         AND (n.tipo <> 'passagem' OR ? = 1)
       ORDER BY n.id DESC
       LIMIT 30");
    $st->execute([$uid, $meuNome, $verPassagem]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $naoLidas = 0;
    foreach ($rows as &$r) { $r['lida'] = intval($r['lida']) > 0; if (!$r['lida']) $naoLidas++; }
    unset($r);
    echo json_encode(['ok' => true, 'data' => $rows, 'nao_lidas' => $naoLidas]);
    exit;
  }

  if ($action === 'marcar_lida') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $nid = intval($body['id'] ?? ($_POST['id'] ?? 0));
    if ($nid > 0) {
      db()->prepare("INSERT IGNORE INTO notificacoes_lidas (notificacao_id, usuario_id) VALUES (?, ?)")
          ->execute([$nid, $uid]);
    }
    echo json_encode(['ok' => true]);
    exit;
  }

  if ($action === 'marcar_todas') {
    // Marca como lidas todas as que ainda não foram lidas por este usuário
    db()->prepare(
      "INSERT IGNORE INTO notificacoes_lidas (notificacao_id, usuario_id)
       SELECT n.id, ? FROM notificacoes n
       WHERE NOT EXISTS (SELECT 1 FROM notificacoes_lidas l WHERE l.notificacao_id = n.id AND l.usuario_id = ?)")
      ->execute([$uid, $uid]);
    echo json_encode(['ok' => true]);
    exit;
  }

  echo json_encode(['ok' => false, 'msg' => 'Ação inválida']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
}
