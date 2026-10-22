<?php
/* ============================================================
   API - Base de Clientes
   Lista as empresas CONCLUÍDAS na Passagem de Bastão
   (etapa_cadastro = 'cliente'). É o cadastro oficial.
   ============================================================ */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Não autenticado']); exit; }

try {
  // Garante a coluna (caso a Passagem de Bastão ainda não tenha rodado a migração)
  try { db()->exec("ALTER TABLE empresas ADD COLUMN etapa_cadastro VARCHAR(20) NOT NULL DEFAULT 'cliente'"); } catch (Throwable $e) {}

  $action = $_GET['action'] ?? 'listar';

  if ($action === 'listar') {
    $search = trim($_GET['search'] ?? '');
    $sql = "SELECT id, grupo, nome, cnpj, seguradora, numero_contrato, analista_nome, ramo,
                   numero_vidas, inicio_vigencia, etapa_cadastro
            FROM empresas
            WHERE ativo = 1 AND etapa_cadastro = 'cliente'";
    $params = [];
    if ($search !== '') {
      $like = "%$search%";
      $sql .= " AND (nome LIKE ? OR grupo LIKE ? OR cnpj LIKE ? OR numero_contrato LIKE ? OR seguradora LIKE ? OR analista_nome LIKE ?)";
      for ($i = 0; $i < 6; $i++) $params[] = $like;
    }
    $sql .= " ORDER BY grupo, nome LIMIT 3000";
    $st = db()->prepare($sql);
    $st->execute($params);
    echo json_encode(['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
  }

  echo json_encode(['ok' => false, 'msg' => 'Ação inválida']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
}
