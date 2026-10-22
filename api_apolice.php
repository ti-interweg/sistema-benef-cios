<?php
/**
 * API - ASSISTENTE DE APÓLICE
 * Anexa a apólice (PDF) e pergunta em português. A resposta sai do documento,
 * citando a cláusula de origem. Usa a API do Claude (chave em chave_ia.php).
 * O PDF é enviado direto à API (document base64), sem extrair texto no servidor.
 */
require_once __DIR__ . '/config.php';
auth();

define('AP_LIMITE_DIA', 40);                  // perguntas por usuário/dia
$AP_MODEL = 'claude-sonnet-5-5';              // modelo usado
$AP_DIR   = __DIR__ . '/uploads/apolices';   // onde os PDFs ficam

function apj($d){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function apbody(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ap_pode_editar(){ return !isVisualizador(); }
function ap_login(){ return (string)($_SESSION['user_login'] ?? $_SESSION['user_id'] ?? ''); }
function ap_nome(){ return (string)($_SESSION['user_nome'] ?? ap_login()); }

function ap_tabelas(){
    db()->exec("CREATE TABLE IF NOT EXISTS apolice_docs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grupo VARCHAR(160) NULL,
        tipo VARCHAR(30) NOT NULL DEFAULT 'Apólice',
        nome_arquivo VARCHAR(255) NOT NULL,
        arquivo VARCHAR(255) NOT NULL,
        tamanho INT NOT NULL DEFAULT 0,
        usuario VARCHAR(120) NULL,
        usuario_login VARCHAR(120) NULL,
        perguntas INT NOT NULL DEFAULT 0,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS apolice_perguntas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doc_id INT NOT NULL,
        doc_id2 INT NULL,
        usuario VARCHAR(120) NULL,
        usuario_login VARCHAR(120) NULL,
        pergunta TEXT NOT NULL,
        resposta MEDIUMTEXT NULL,
        modelo VARCHAR(60) NULL,
        tokens_in INT NULL,
        tokens_out INT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
try { ap_tabelas(); } catch (Throwable $e) {}

$action = $_REQUEST['action'] ?? '';

/* ─────────────── Ver o PDF (stream; não é JSON) ─────────────── */
if ($action === 'ap_ver') {
    $id = intval($_GET['id'] ?? 0);
    $row = null;
    try { $st = db()->prepare("SELECT * FROM apolice_docs WHERE id=? AND ativo=1"); $st->execute([$id]); $row = $st->fetch(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
    if (!$row) { http_response_code(404); echo 'Documento não encontrado'; exit; }
    $path = $AP_DIR . '/' . basename($row['arquivo']);
    if (!is_file($path)) { http_response_code(404); echo 'Arquivo não encontrado'; exit; }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^\w\.\- ]/', '_', $row['nome_arquivo']) . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

switch ($action) {

    case 'ap_grupos': {
        $gs = [];
        try { $gs = db()->query("SELECT DISTINCT grupo FROM empresas WHERE grupo IS NOT NULL AND grupo<>'' ORDER BY grupo")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}
        apj(['ok'=>true, 'grupos'=>$gs]);
        break;
    }

    case 'ap_listar': {
        $grupo = trim($_GET['grupo'] ?? '');
        $sql = "SELECT id, grupo, tipo, nome_arquivo, tamanho, usuario, perguntas, criado_em
                FROM apolice_docs WHERE ativo=1";
        $par = [];
        if ($grupo !== '') { $sql .= " AND grupo <=> ?"; $par[] = $grupo; }
        $sql .= " ORDER BY criado_em DESC";
        $st = db()->prepare($sql); $st->execute($par);
        $docs = $st->fetchAll(PDO::FETCH_ASSOC);
        // Perguntas restantes hoje
        $usadas = 0;
        try { $q = db()->prepare("SELECT COUNT(*) FROM apolice_perguntas WHERE usuario_login=? AND DATE(criado_em)=CURDATE()"); $q->execute([ap_login()]); $usadas = (int)$q->fetchColumn(); } catch (Throwable $e) {}
        apj(['ok'=>true, 'data'=>$docs, 'limite'=>AP_LIMITE_DIA, 'usadas'=>$usadas, 'restantes'=>max(0, AP_LIMITE_DIA-$usadas), 'pode_editar'=>ap_pode_editar()]);
        break;
    }

    case 'ap_perguntas': { // histórico de um documento
        $docId = intval($_GET['doc_id'] ?? 0);
        $st = db()->prepare("SELECT id, doc_id2, usuario, pergunta, resposta, modelo, tokens_in, tokens_out, criado_em
                             FROM apolice_perguntas WHERE doc_id=? ORDER BY criado_em ASC");
        $st->execute([$docId]);
        apj(['ok'=>true, 'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
        break;
    }

    case 'ap_upload': {
        if (!ap_pode_editar()) apj(['ok'=>false,'msg'=>'Acesso negado']);
        if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            apj(['ok'=>false,'msg'=>'Falha no upload do arquivo.']);
        }
        $f = $_FILES['arquivo'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') apj(['ok'=>false,'msg'=>'Envie um arquivo PDF.']);
        if ($f['size'] > 25*1024*1024) apj(['ok'=>false,'msg'=>'Arquivo muito grande (máx. 25 MB).']);
        if (!is_dir($AP_DIR)) @mkdir($AP_DIR, 0775, true);
        // Protege a pasta de acesso direto por URL.
        $ht = $AP_DIR . '/.htaccess';
        if (!is_file($ht)) @file_put_contents($ht, "Require all denied\n");
        $grupo = trim($_POST['grupo'] ?? '');
        $tipo  = (trim($_POST['tipo'] ?? '') === 'Especificação') ? 'Especificação' : 'Apólice';
        $safe  = bin2hex(random_bytes(8)) . '.pdf';
        $dest  = $AP_DIR . '/' . $safe;
        if (!move_uploaded_file($f['tmp_name'], $dest)) apj(['ok'=>false,'msg'=>'Não consegui salvar o arquivo.']);
        $st = db()->prepare("INSERT INTO apolice_docs (grupo, tipo, nome_arquivo, arquivo, tamanho, usuario, usuario_login)
                             VALUES (?,?,?,?,?,?,?)");
        $st->execute([($grupo ?: null), $tipo, $f['name'], $safe, (int)$f['size'], ap_nome(), ap_login()]);
        apj(['ok'=>true, 'id'=>db()->lastInsertId()]);
        break;
    }

    case 'ap_excluir': {
        if (!ap_pode_editar()) apj(['ok'=>false,'msg'=>'Acesso negado']);
        $d = apbody(); $id = intval($d['id'] ?? 0);
        $st = db()->prepare("SELECT arquivo FROM apolice_docs WHERE id=?"); $st->execute([$id]);
        $arq = $st->fetchColumn();
        db()->prepare("UPDATE apolice_docs SET ativo=0 WHERE id=?")->execute([$id]);
        if ($arq) { $p = $AP_DIR . '/' . basename($arq); if (is_file($p)) @unlink($p); }
        apj(['ok'=>true]);
        break;
    }

    case 'ap_perguntar': {
        global $AP_MODEL, $AP_DIR;
        if (!ap_pode_editar()) apj(['ok'=>false,'msg'=>'Acesso negado']);
        $d = apbody();
        $docId  = intval($d['doc_id'] ?? 0);
        $docId2 = intval($d['doc_id2'] ?? 0);
        $pergunta = trim($d['pergunta'] ?? '');
        if ($docId <= 0 || $pergunta === '') apj(['ok'=>false,'msg'=>'Selecione o documento e escreva a pergunta.']);

        // Limite diário por usuário
        $usadas = 0;
        try { $q = db()->prepare("SELECT COUNT(*) FROM apolice_perguntas WHERE usuario_login=? AND DATE(criado_em)=CURDATE()"); $q->execute([ap_login()]); $usadas = (int)$q->fetchColumn(); } catch (Throwable $e) {}
        if ($usadas >= AP_LIMITE_DIA) apj(['ok'=>false,'msg'=>'Você atingiu o limite de '.AP_LIMITE_DIA.' perguntas hoje.']);

        // Chave da API
        $key = '';
        if (is_file(__DIR__.'/chave_ia.php')) { $key = trim((string) require __DIR__.'/chave_ia.php'); }
        if ($key === '') apj(['ok'=>false,'msg'=>'Chave da API não configurada (chave_ia.php).']);

        // Carrega o(s) PDF(s)
        $loadDoc = function($id) use ($AP_DIR) {
            $st = db()->prepare("SELECT * FROM apolice_docs WHERE id=? AND ativo=1"); $st->execute([$id]);
            $r = $st->fetch(PDO::FETCH_ASSOC); if (!$r) return null;
            $p = $AP_DIR . '/' . basename($r['arquivo']); if (!is_file($p)) return null;
            $r['_b64'] = base64_encode(file_get_contents($p)); return $r;
        };
        $doc1 = $loadDoc($docId);
        if (!$doc1) apj(['ok'=>false,'msg'=>'Documento não encontrado.']);
        $doc2 = $docId2 > 0 ? $loadDoc($docId2) : null;

        $content = [];
        $content[] = ['type'=>'text','text'=>'DOCUMENTO 1 ('.$doc1['tipo'].' — '.$doc1['nome_arquivo'].'):'];
        $content[] = ['type'=>'document','source'=>['type'=>'base64','media_type'=>'application/pdf','data'=>$doc1['_b64']]];
        if ($doc2) {
            $content[] = ['type'=>'text','text'=>'DOCUMENTO 2 ('.$doc2['tipo'].' — '.$doc2['nome_arquivo'].'):'];
            $content[] = ['type'=>'document','source'=>['type'=>'base64','media_type'=>'application/pdf','data'=>$doc2['_b64']]];
        }
        $content[] = ['type'=>'text','text'=>"PERGUNTA: ".$pergunta];

        $sys = "Você é um assistente que responde SOMENTE com base no(s) documento(s) de apólice de seguro anexado(s), em português do Brasil. "
             . "Regras: 1) Responda de forma objetiva e cite a CLÁUSULA/ITEM DE ORIGEM de cada afirmação (ex.: \"Cláusula 7ª, item 7.2\"). "
             . "2) Se a informação não estiver no documento, diga claramente que não consta. 3) Não invente valores nem coberturas. "
             . "4) Deixe explícito quando algo for interpretação sua e não estiver escrito. "
             . ($doc2 ? "5) Quando fizer sentido, compare os dois documentos indicando diferenças e de qual documento vem cada ponto." : "");

        $payload = ['model'=>$AP_MODEL,'max_tokens'=>2000,'system'=>$sys,
                    'messages'=>[['role'=>'user','content'=>$content]]];
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>['content-type: application/json','x-api-key: '.$key,'anthropic-version: 2023-06-01'],
            CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_TIMEOUT=>180,
        ]);
        $t0 = microtime(true);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
        $secs = round(microtime(true)-$t0, 1);
        if ($resp === false) apj(['ok'=>false,'msg'=>'Falha de conexão com a IA: '.$cerr]);
        if ($code !== 200)   apj(['ok'=>false,'msg'=>'IA retornou HTTP '.$code, 'detalhe'=>substr($resp,0,400)]);

        $dj = json_decode($resp, true); $txt = '';
        if (!empty($dj['content'])) foreach ($dj['content'] as $b) { if (($b['type']??'')==='text') $txt .= $b['text']; }
        $txt = trim($txt);
        if ($txt === '') apj(['ok'=>false,'msg'=>'A IA não retornou resposta.']);
        $tin  = (int)($dj['usage']['input_tokens'] ?? 0);
        $tout = (int)($dj['usage']['output_tokens'] ?? 0);

        try {
            db()->prepare("INSERT INTO apolice_perguntas (doc_id, doc_id2, usuario, usuario_login, pergunta, resposta, modelo, tokens_in, tokens_out)
                           VALUES (?,?,?,?,?,?,?,?,?)")
              ->execute([$docId, ($docId2>0?$docId2:null), ap_nome(), ap_login(), $pergunta, $txt, $AP_MODEL, $tin, $tout]);
            db()->prepare("UPDATE apolice_docs SET perguntas = perguntas + 1 WHERE id=?")->execute([$docId]);
        } catch (Throwable $e) {}

        apj(['ok'=>true, 'resposta'=>$txt, 'modelo'=>$AP_MODEL, 'segundos'=>$secs,
             'tokens_in'=>$tin, 'tokens_out'=>$tout,
             'restantes'=>max(0, AP_LIMITE_DIA-($usadas+1))]);
        break;
    }

    default:
        apj(['ok'=>false,'msg'=>'Ação desconhecida: '.$action]);
}
