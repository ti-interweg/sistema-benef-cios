<?php
/**
 * API - PROGRAMAS DE SAÚDE (Interbaby / Intercuidado)
 * Acompanhamento de participantes + evolução mês a mês + Kit (enviado/pendente).
 */
require_once __DIR__ . '/config.php';
auth();
header('Content-Type: application/json; charset=utf-8');

function pgj($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function pgbody(){ return json_decode(file_get_contents('php://input'), true) ?: []; }

// ── Tabelas (idempotente) ───────────────────────────────────────────
function pg_tabelas(){
    db()->exec("CREATE TABLE IF NOT EXISTS programas_participantes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        programa VARCHAR(20) NOT NULL DEFAULT 'Interbaby',
        grupo VARCHAR(90) NULL,
        nome VARCHAR(160) NOT NULL,
        empresa VARCHAR(120) NULL,
        telefone VARCHAR(50) NULL,
        dn DATE NULL,
        id_gestacional VARCHAR(60) NULL,
        dpp DATE NULL,
        nome_bebe VARCHAR(120) NULL,
        historico VARCHAR(60) NULL,
        ativ_fisica VARCHAR(140) NULL,
        alimentacao VARCHAR(140) NULL,
        medicacoes TEXT NULL,
        maternidade VARCHAR(180) NULL,
        kit VARCHAR(20) NULL,
        alta VARCHAR(30) NULL,
        endereco TEXT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY k_prog (programa, ativo),
        KEY k_nome (nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS programas_evolucao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participante_id INT NOT NULL,
        competencia VARCHAR(7) NOT NULL,
        nota TEXT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY k_part_comp (participante_id, competencia), KEY k_criado (criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Migração p/ histórico acumulado: remove a chave única antiga e garante criado_em.
    try { db()->exec("ALTER TABLE programas_evolucao DROP INDEX uq_part_comp"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE programas_evolucao ADD COLUMN IF NOT EXISTS criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) {}
    // Data do próximo contato (idempotente)
    try { db()->exec("ALTER TABLE programas_participantes ADD COLUMN IF NOT EXISTS proximo_contato DATE NULL"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE programas_participantes ADD COLUMN IF NOT EXISTS comorbidade TEXT NULL"); } catch (Throwable $e) {}
    // Termos de consentimento (LGPD) anexados — o arquivo fica no DISCO; aqui guardamos só a referência.
    db()->exec("CREATE TABLE IF NOT EXISTS programas_termos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participante_id INT NOT NULL,
        arquivo VARCHAR(190) NOT NULL,
        nome_original VARCHAR(190) NULL,
        mime VARCHAR(90) NULL,
        tamanho INT NULL,
        enviado_por VARCHAR(120) NULL,
        enviado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY k_part (participante_id, enviado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Pasta de armazenamento dos termos (fora de acesso web direto via .htaccess).
function pg_termosDir(){
    $dir = __DIR__ . '/uploads/termos';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    // Bloqueia acesso direto pela web: o download só sai pelo endpoint autenticado.
    $ht = __DIR__ . '/uploads/termos/.htaccess';
    if (!file_exists($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    return $dir;
}

function pg_bloqueiaLeitura(){ /* todos logados podem ler */ }
function pg_bloqueiaEscrita(){ if (isVisualizador()) pgj(['ok'=>false,'msg'=>'Acesso negado']); }

$CAMPOS = ['programa','grupo','nome','empresa','telefone','dn','id_gestacional','dpp','nome_bebe',
           'historico','ativ_fisica','alimentacao','medicacoes','maternidade','kit','alta','endereco','proximo_contato','comorbidade'];

try { pg_tabelas(); } catch (Throwable $e) {}

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // Lista participantes de um programa (+ a nota da competência, se informada).
    case 'prog_listar': {
        $programa = trim($_GET['programa'] ?? 'Interbaby');
        $comp = trim($_GET['competencia'] ?? '');
        try {
            if (preg_match('/^\d{4}-\d{2}$/', $comp)) {
                $st = db()->prepare("SELECT p.*,
                                       (SELECT e.nota FROM programas_evolucao e
                                        WHERE e.participante_id=p.id AND e.competencia=?
                                        ORDER BY e.criado_em DESC, e.id DESC LIMIT 1) AS nota_mes,
                                       (SELECT COUNT(*) FROM programas_termos t WHERE t.participante_id=p.id) AS termos_qtd
                                     FROM programas_participantes p
                                     WHERE p.programa=? AND p.ativo=1
                                     ORDER BY p.nome");
                $st->execute([$comp, $programa]);
            } else {
                $st = db()->prepare("SELECT p.*, NULL AS nota_mes,
                                       (SELECT COUNT(*) FROM programas_termos t WHERE t.participante_id=p.id) AS termos_qtd
                                     FROM programas_participantes p
                                     WHERE p.programa=? AND p.ativo=1 ORDER BY p.nome");
                $st->execute([$programa]);
            }
            pgj(['ok'=>true, 'data'=>$st->fetchAll(PDO::FETCH_ASSOC), 'competencia'=>$comp]);
        } catch (Throwable $e) { pgj(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // Cria/edita um participante.
    case 'prog_salvar': {
        pg_bloqueiaEscrita();
        $d = pgbody();
        $id = intval($d['id'] ?? 0);
        $nome = trim($d['nome'] ?? '');
        if ($nome === '') pgj(['ok'=>false,'msg'=>'Nome é obrigatório']);
        $vals = [];
        foreach ($CAMPOS as $c) {
            $v = $d[$c] ?? null;
            if ($c === 'programa') $v = ($v === 'Intercuidado') ? 'Intercuidado' : 'Interbaby';
            if (($c === 'dn' || $c === 'dpp' || $c === 'proximo_contato')) $v = (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v)) ? $v : null;
            if ($v === '' ) $v = null;
            $vals[$c] = $v;
        }
        try {
            if ($id > 0) {
                $set = implode(', ', array_map(fn($c)=>"$c=?", $CAMPOS));
                $st = db()->prepare("UPDATE programas_participantes SET $set WHERE id=?");
                $st->execute(array_merge(array_values($vals), [$id]));
                pgj(['ok'=>true, 'id'=>$id]);
            } else {
                $cols = implode(',', $CAMPOS);
                $ph = implode(',', array_fill(0, count($CAMPOS), '?'));
                $st = db()->prepare("INSERT INTO programas_participantes ($cols) VALUES ($ph)");
                $st->execute(array_values($vals));
                pgj(['ok'=>true, 'id'=>intval(db()->lastInsertId())]);
            }
        } catch (Throwable $e) { pgj(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // Exclui (soft) um participante.
    case 'prog_excluir': {
        pg_bloqueiaEscrita();
        $d = pgbody(); $id = intval($d['id'] ?? 0);
        if ($id <= 0) pgj(['ok'=>false,'msg'=>'ID inválido']);
        try { db()->prepare("UPDATE programas_participantes SET ativo=0 WHERE id=?")->execute([$id]); pgj(['ok'=>true]); }
        catch (Throwable $e) { pgj(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // Lista a evolução (todas as competências) de um participante.
    case 'prog_evolucao_listar': {
        $pid = intval($_GET['participante_id'] ?? 0);
        if ($pid <= 0) pgj(['ok'=>false,'msg'=>'Participante inválido']);
        try {
            $st = db()->prepare("SELECT id, competencia, nota, criado_em FROM programas_evolucao
                                 WHERE participante_id=? ORDER BY criado_em DESC, id DESC");
            $st->execute([$pid]);
            pgj(['ok'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Throwable $e) { pgj(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // Salva (upsert) a nota de evolução de um mês.
    case 'prog_evolucao_salvar': {
        pg_bloqueiaEscrita();
        $d = pgbody();
        $pid = intval($d['participante_id'] ?? 0);
        $comp = trim($d['competencia'] ?? '');
        $nota = trim($d['nota'] ?? '');
        if ($pid <= 0 || !preg_match('/^\d{4}-\d{2}$/', $comp)) pgj(['ok'=>false,'msg'=>'Dados inválidos']);
        if ($nota === '') pgj(['ok'=>false,'msg'=>'Nota vazia']);
        try {
            // Sempre insere um NOVO registro (histórico acumulado — nunca sobrescreve).
            $st = db()->prepare("INSERT INTO programas_evolucao (participante_id,competencia,nota) VALUES (?,?,?)");
            $st->execute([$pid,$comp,$nota]);
            pgj(['ok'=>true]);
        } catch (Throwable $e) { pgj(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // Edita uma nota específica do histórico.
    case 'prog_evolucao_editar': {
        pg_bloqueiaEscrita();
        $d = pgbody(); $id = intval($d['id'] ?? 0); $nota = trim($d['nota'] ?? '');
        if ($id <= 0) pgj(['ok'=>false,'msg'=>'ID inválido']);
        if ($nota === '') pgj(['ok'=>false,'msg'=>'Nota vazia']);
        try { db()->prepare("UPDATE programas_evolucao SET nota=? WHERE id=?")->execute([$nota,$id]); pgj(['ok'=>true]); }
        catch (Throwable $e) { pgj(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // Exclui uma nota específica do histórico.
    case 'prog_evolucao_excluir': {
        pg_bloqueiaEscrita();
        $d = pgbody(); $id = intval($d['id'] ?? 0);
        if ($id <= 0) pgj(['ok'=>false,'msg'=>'ID inválido']);
        try { db()->prepare("DELETE FROM programas_evolucao WHERE id=?")->execute([$id]); pgj(['ok'=>true]); }
        catch (Throwable $e) { pgj(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // ── TERMOS DE CONSENTIMENTO (LGPD) ──────────────────────────────
    // Lista os termos anexados de um participante (histórico).
    case 'prog_termo_listar': {
        $pid = intval($_GET['participante_id'] ?? 0);
        if ($pid <= 0) pgj(['ok'=>false,'msg'=>'Participante inválido']);
        try {
            $st = db()->prepare("SELECT id, nome_original, mime, tamanho, enviado_por, enviado_em
                                 FROM programas_termos WHERE participante_id=? ORDER BY enviado_em DESC, id DESC");
            $st->execute([$pid]);
            pgj(['ok'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Throwable $e) { pgj(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // Recebe o upload (multipart) e grava o arquivo no DISCO; no banco só a referência.
    case 'prog_termo_upload': {
        pg_bloqueiaEscrita();
        $pid = intval($_POST['participante_id'] ?? 0);
        if ($pid <= 0) pgj(['ok'=>false,'msg'=>'Salve o participante antes de anexar o termo.']);
        if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK)
            pgj(['ok'=>false,'msg'=>'Falha no envio do arquivo.']);

        $f = $_FILES['arquivo'];
        $max = 5 * 1024 * 1024; // 5 MB
        if ($f['size'] > $max) pgj(['ok'=>false,'msg'=>'Arquivo acima de 5 MB.']);

        $permitidos = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
        ];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!isset($permitidos[$ext])) pgj(['ok'=>false,'msg'=>'Formato não permitido. Use PDF, JPG ou PNG.']);
        // Confere o tipo real do conteúdo (não confia só na extensão).
        $mimeReal = @finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']);
        $okMime = in_array($mimeReal, ['application/pdf','image/jpeg','image/png'], true);
        if (!$okMime) pgj(['ok'=>false,'msg'=>'O conteúdo do arquivo não confere com PDF/JPG/PNG.']);

        $dir = pg_termosDir();
        $nomeDisco = 'termo_' . $pid . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $dir . '/' . $nomeDisco;
        if (!@move_uploaded_file($f['tmp_name'], $destino)) pgj(['ok'=>false,'msg'=>'Não foi possível salvar o arquivo.']);

        $usuario = $_SESSION['usuario_nome'] ?? ($_SESSION['nome'] ?? ($_SESSION['user']['nome'] ?? null));
        $nomeOrig = mb_substr(preg_replace('/[\r\n\t]/', ' ', $f['name']), 0, 185);
        try {
            $st = db()->prepare("INSERT INTO programas_termos (participante_id,arquivo,nome_original,mime,tamanho,enviado_por)
                                 VALUES (?,?,?,?,?,?)");
            $st->execute([$pid, $nomeDisco, $nomeOrig, $permitidos[$ext], intval($f['size']), $usuario]);
            pgj(['ok'=>true,'id'=>intval(db()->lastInsertId())]);
        } catch (Throwable $e) {
            @unlink($destino);
            pgj(['ok'=>false,'msg'=>$e->getMessage()]);
        }
        break;
    }

    // Baixa um termo (stream do arquivo do disco). Sobrescreve o header JSON.
    case 'prog_termo_baixar': {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); exit('ID inválido'); }
        $st = db()->prepare("SELECT arquivo, nome_original, mime FROM programas_termos WHERE id=?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) { http_response_code(404); exit('Termo não encontrado'); }
        $caminho = pg_termosDir() . '/' . basename($row['arquivo']);
        if (!is_file($caminho)) { http_response_code(404); exit('Arquivo ausente no servidor'); }
        $nomeDown = $row['nome_original'] ?: basename($row['arquivo']);
        header('Content-Type: ' . ($row['mime'] ?: 'application/octet-stream'), true);
        $inline = isset($_GET['inline']) && $_GET['inline'] == '1';
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $nomeDown) . '"', true);
        header('Content-Length: ' . filesize($caminho));
        header('X-Content-Type-Options: nosniff');
        readfile($caminho);
        exit;
    }

    // Exclui um termo (arquivo do disco + registro).
    case 'prog_termo_excluir': {
        pg_bloqueiaEscrita();
        $d = pgbody(); $id = intval($d['id'] ?? 0);
        if ($id <= 0) pgj(['ok'=>false,'msg'=>'ID inválido']);
        try {
            $st = db()->prepare("SELECT arquivo FROM programas_termos WHERE id=?");
            $st->execute([$id]); $arq = $st->fetchColumn();
            if ($arq) @unlink(pg_termosDir() . '/' . basename($arq));
            db()->prepare("DELETE FROM programas_termos WHERE id=?")->execute([$id]);
            pgj(['ok'=>true]);
        } catch (Throwable $e) { pgj(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // Vira o mês: cria as fichas de evolução EM BRANCO do próximo mês para todos os ativos do programa.
    case 'prog_virar_mes': {
        pg_bloqueiaEscrita();
        $d = pgbody();
        $programa = trim($d['programa'] ?? 'Interbaby');
        $para = trim($d['competencia'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $para)) pgj(['ok'=>false,'msg'=>'Competência inválida (AAAA-MM)']);
        // Histórico acumulado: nada a criar — as notas do novo mês entram ao serem escritas.
        pgj(['ok'=>true,'competencia'=>$para]);
        break;
    }

    // Importa em lote (participantes + evolução).
    case 'prog_importar': {
        pg_bloqueiaEscrita();
        $d = pgbody();
        $programa = (($d['programa'] ?? '') === 'Intercuidado') ? 'Intercuidado' : 'Interbaby';
        $linhas = $d['linhas'] ?? [];
        if (!is_array($linhas) || !count($linhas)) pgj(['ok'=>false,'msg'=>'Nada para importar']);
        $np = 0; $nev = 0;
        try {
            // Busca existentes do programa para casar por nome (evita duplicar).
            $ex = []; $q = db()->prepare("SELECT id, nome FROM programas_participantes WHERE programa=? AND ativo=1");
            $q->execute([$programa]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) { $ex[mb_strtolower(trim($r['nome']))] = intval($r['id']); }

            $camposIns = ['programa','grupo','nome','empresa','telefone','dn','id_gestacional','dpp','nome_bebe',
                          'historico','ativ_fisica','alimentacao','medicacoes','maternidade','kit','alta','endereco'];
            $cols = implode(',', $camposIns);
            $ph   = implode(',', array_fill(0, count($camposIns), '?'));
            $stIns = db()->prepare("INSERT INTO programas_participantes ($cols) VALUES ($ph)");
            $setU  = implode(', ', array_map(fn($c)=>"$c=?", array_slice($camposIns,1))); // sem 'programa'
            $stUpd = db()->prepare("UPDATE programas_participantes SET $setU WHERE id=?");
            $stEv  = db()->prepare("INSERT INTO programas_evolucao (participante_id,competencia,nota)
                                    VALUES (?,?,?) ON DUPLICATE KEY UPDATE nota=VALUES(nota)");
            $norm = fn($v)=>($v===''||$v===null)?null:$v;
            $dta  = function($v){ return (preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$v)) ? $v : null; };

            foreach ($linhas as $l) {
                $nome = trim($l['nome'] ?? ''); if ($nome === '') continue;
                $row = [
                    'grupo'=>$norm(trim($l['grupo'] ?? '')),
                    'nome'=>$nome,
                    'empresa'=>$norm(trim($l['empresa'] ?? '')),
                    'telefone'=>$norm(trim($l['telefone'] ?? '')),
                    'dn'=>$dta($l['dn'] ?? ''),
                    'id_gestacional'=>$norm(trim($l['id_gestacional'] ?? '')),
                    'dpp'=>$dta($l['dpp'] ?? ''),
                    'nome_bebe'=>$norm(trim($l['nome_bebe'] ?? '')),
                    'historico'=>$norm(trim($l['historico'] ?? '')),
                    'ativ_fisica'=>$norm(trim($l['ativ_fisica'] ?? '')),
                    'alimentacao'=>$norm(trim($l['alimentacao'] ?? '')),
                    'medicacoes'=>$norm(trim($l['medicacoes'] ?? '')),
                    'maternidade'=>$norm(trim($l['maternidade'] ?? '')),
                    'kit'=>$norm(trim($l['kit'] ?? '')),
                    'alta'=>$norm(trim($l['alta'] ?? '')),
                    'endereco'=>$norm(trim($l['endereco'] ?? '')),
                ];
                $key = mb_strtolower($nome);
                if (isset($ex[$key])) {
                    $pid = $ex[$key];
                    $stUpd->execute(array_merge(array_values($row), [$pid]));
                } else {
                    $stIns->execute(array_merge([$programa], array_values($row)));
                    $pid = intval(db()->lastInsertId());
                    $ex[$key] = $pid;
                }
                $np++;
                $comp = trim($l['competencia'] ?? '');
                $nota = trim($l['nota'] ?? '');
                if (preg_match('/^\d{4}-\d{2}$/', $comp) && $nota !== '') {
                    $stEv->execute([$pid, $comp, $nota]); $nev++;
                }
            }
            pgj(['ok'=>true, 'participantes'=>$np, 'evolucoes'=>$nev]);
        } catch (Throwable $e) { pgj(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    default:
        pgj(['ok'=>false,'msg'=>'Ação desconhecida: '.$action]);
}
