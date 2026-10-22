<?php
/**
 * =====================================================
 * API ANAMNESE — Formulário público + campanhas + resultados
 * =====================================================
 * Ações PÚBLICAS (sem login): info_campanha, salvar_resposta, resultados (por token)
 * Ações PROTEGIDAS (equipe): criar_campanha, listar_campanhas, excluir_campanha,
 *                            resultados (por id)
 * As tabelas são criadas automaticamente na primeira chamada.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function anaBody(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function anaOut($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

function anaEnsureTables(){
    db()->exec("CREATE TABLE IF NOT EXISTS anamnese_campanhas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente VARCHAR(255) NOT NULL,
        slug VARCHAR(90) NOT NULL UNIQUE,
        token_result VARCHAR(90) NOT NULL UNIQUE,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        criado_por INT NULL,
        criado_por_nome VARCHAR(150) NULL,
        analises LONGTEXT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Garante a coluna 'analises' em bancos onde a tabela já existia sem ela
    try { db()->exec("ALTER TABLE anamnese_campanhas ADD COLUMN analises LONGTEXT NULL"); } catch (Throwable $e) {}
    db()->exec("CREATE TABLE IF NOT EXISTS anamnese_respostas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campanha_id INT NOT NULL,
        dados LONGTEXT NOT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_camp (campanha_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Tabela dedicada das análises (uma linha por campanha + pergunta)
    db()->exec("CREATE TABLE IF NOT EXISTS anamnese_analises (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campanha_id INT NOT NULL,
        q_idx INT NOT NULL,
        titulo VARCHAR(500) NULL,
        texto LONGTEXT NULL,
        atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_camp_q (campanha_id, q_idx)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Carrega as análises de uma campanha como mapa { q_idx: texto }
function anaCarregarAnalises($campanhaId){
    try {
        $st = db()->prepare("SELECT q_idx, texto FROM anamnese_analises WHERE campanha_id = ?");
        $st->execute([intval($campanhaId)]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[(string)$r['q_idx']] = $r['texto']; }
        return $out;
    } catch (Throwable $e) { return []; }
}

function anaSlug($s){
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = preg_replace('/[áàâãä]/u','a',$s); $s = preg_replace('/[éèêë]/u','e',$s);
    $s = preg_replace('/[íìîï]/u','i',$s); $s = preg_replace('/[óòôõö]/u','o',$s);
    $s = preg_replace('/[úùûü]/u','u',$s); $s = preg_replace('/[ç]/u','c',$s);
    $s = preg_replace('/[^a-z0-9]+/','-', $s);
    $s = trim($s, '-');
    return $s ?: 'cliente';
}

try { anaEnsureTables(); } catch (Throwable $e) { anaOut(['ok'=>false,'msg'=>'Erro ao preparar tabelas: '.$e->getMessage()]); }

$action = $_GET['action'] ?? '';
$publicas = ['info_campanha','salvar_resposta','resultados_token'];

// Ações protegidas exigem login + equipe
if (!in_array($action, $publicas, true)) {
    auth();
    $ehRenov = (strtolower(trim($_SESSION['user_tipo'] ?? '')) === 'renovacao');
    if (!(isAdmin() || isEnfermeira() || $ehRenov)) anaOut(['ok'=>false,'msg'=>'Acesso negado']);
}

switch ($action) {

    // ── PÚBLICO: dados da campanha pelo slug (nome do cliente) ──
    case 'info_campanha': {
        $slug = trim($_GET['c'] ?? '');
        if ($slug === '') anaOut(['ok'=>false,'msg'=>'Link inválido']);
        $st = db()->prepare("SELECT id, cliente, ativo FROM anamnese_campanhas WHERE slug = ? LIMIT 1");
        $st->execute([$slug]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) anaOut(['ok'=>false,'msg'=>'Formulário não encontrado']);
        if (!intval($c['ativo'])) anaOut(['ok'=>false,'msg'=>'Este formulário está encerrado.']);
        anaOut(['ok'=>true,'cliente'=>$c['cliente']]);
        break;
    }

    // ── PÚBLICO: salva uma resposta ──
    case 'salvar_resposta': {
        $d = anaBody();
        $slug = trim($d['slug'] ?? '');
        $dados = $d['dados'] ?? null;
        if ($slug === '' || !is_array($dados)) anaOut(['ok'=>false,'msg'=>'Dados inválidos']);
        $st = db()->prepare("SELECT id, ativo FROM anamnese_campanhas WHERE slug = ? LIMIT 1");
        $st->execute([$slug]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) anaOut(['ok'=>false,'msg'=>'Formulário não encontrado']);
        if (!intval($c['ativo'])) anaOut(['ok'=>false,'msg'=>'Este formulário está encerrado.']);
        $ins = db()->prepare("INSERT INTO anamnese_respostas (campanha_id, dados) VALUES (?, ?)");
        $ins->execute([intval($c['id']), json_encode($dados, JSON_UNESCAPED_UNICODE)]);
        anaOut(['ok'=>true]);
        break;
    }

    // ── PÚBLICO: resultados por token (link de leitura do cliente) ──
    case 'resultados_token': {
        $tok = trim($_GET['t'] ?? '');
        if ($tok === '') anaOut(['ok'=>false,'msg'=>'Link inválido']);
        $st = db()->prepare("SELECT id, cliente, analises FROM anamnese_campanhas WHERE token_result = ? LIMIT 1");
        $st->execute([$tok]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) anaOut(['ok'=>false,'msg'=>'Resultados não encontrados']);
        anaOut(['ok'=>true,'cliente'=>$c['cliente'],'analises'=>anaCarregarAnalises(intval($c['id'])),'respostas'=>anaCarregarRespostas(intval($c['id']))]);
        break;
    }

    // ── EQUIPE: cria campanha (gera slug + token) ──
    case 'criar_campanha': {
        $d = anaBody();
        $cliente = trim($d['cliente'] ?? '');
        if ($cliente === '') anaOut(['ok'=>false,'msg'=>'Informe o nome do cliente']);
        $base = anaSlug($cliente);
        $slug = $base . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
        $token = bin2hex(random_bytes(10));
        $ins = db()->prepare("INSERT INTO anamnese_campanhas (cliente, slug, token_result, criado_por, criado_por_nome) VALUES (?,?,?,?,?)");
        $ins->execute([$cliente, $slug, $token, $_SESSION['user_id'] ?? null, $_SESSION['user_nome'] ?? '']);
        anaOut(['ok'=>true,'id'=>db()->lastInsertId(),'slug'=>$slug,'token'=>$token]);
        break;
    }

    // ── EQUIPE: lista campanhas com contagem de respostas ──
    case 'listar_campanhas': {
        $rows = db()->query("SELECT c.*, (SELECT COUNT(*) FROM anamnese_respostas r WHERE r.campanha_id = c.id) AS n_respostas
                             FROM anamnese_campanhas c ORDER BY c.criado_em DESC")->fetchAll(PDO::FETCH_ASSOC);
        anaOut(['ok'=>true,'data'=>$rows]);
        break;
    }

    // ── EQUIPE: ativa/desativa ou exclui campanha ──
    case 'toggle_campanha': {
        $d = anaBody(); $id = intval($d['id'] ?? 0); $ativo = intval($d['ativo'] ?? 1);
        if (!$id) anaOut(['ok'=>false,'msg'=>'ID inválido']);
        db()->prepare("UPDATE anamnese_campanhas SET ativo = ? WHERE id = ?")->execute([$ativo, $id]);
        anaOut(['ok'=>true]);
        break;
    }
    case 'excluir_campanha': {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) anaOut(['ok'=>false,'msg'=>'ID inválido']);
        db()->prepare("DELETE FROM anamnese_respostas WHERE campanha_id = ?")->execute([$id]);
        db()->prepare("DELETE FROM anamnese_campanhas WHERE id = ?")->execute([$id]);
        anaOut(['ok'=>true]);
        break;
    }

    // ── EQUIPE: resultados por id da campanha ──
    case 'resultados': {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) anaOut(['ok'=>false,'msg'=>'ID inválido']);
        $st = db()->prepare("SELECT cliente, analises FROM anamnese_campanhas WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) anaOut(['ok'=>false,'msg'=>'Campanha não encontrada']);
        anaOut(['ok'=>true,'cliente'=>$c['cliente'],'analises'=>anaCarregarAnalises($id),'respostas'=>anaCarregarRespostas($id)]);
        break;
    }

    // ── EQUIPE: gera a análise com IA (Claude) a partir dos dados agregados da pergunta ──
    case 'gerar_analise_ia': {
        $d = anaBody();
        $pergunta = trim((string)($d['pergunta'] ?? ''));
        $resumo   = trim((string)($d['resumo'] ?? ''));
        $cliente  = trim((string)($d['cliente'] ?? ''));
        if ($pergunta === '' || $resumo === '') anaOut(['ok'=>false,'msg'=>'Dados insuficientes para gerar']);

        $apiKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : (getenv('CLAUDE_API_KEY') ?: '');
        if (!$apiKey) anaOut(['ok'=>false,'msg'=>'Chave da IA não configurada. Adicione define(\'CLAUDE_API_KEY\', \'sua-chave\') no config.php.']);
        $model = defined('CLAUDE_MODEL') ? trim((string)CLAUDE_MODEL) : '';
        // Segurança: nunca usar a chave como modelo. Se vier vazio ou parecer uma chave, usa o padrão.
        if ($model === '' || stripos($model, 'sk-ant') !== false) $model = 'claude-haiku-4-5-20251001';

        $sys = 'Você é uma enfermeira experiente da Interweg elaborando a devolutiva de saúde de uma turma/empresa. '
             . 'Escreva análises objetivas, empáticas e profissionais em português do Brasil, com base nos dados AGREGADOS das respostas. '
             . 'Nunca diagnostique indivíduos e não dê orientações nutricionais/de dieta. Interprete o resultado sob a ótica de saúde e bem-estar geral e traga orientações gerais de cuidado.';
        $usr = "Pergunta do questionário: " . $pergunta . "\n\n"
             . "Empresa/turma: " . ($cliente !== '' ? $cliente : '(não informado)') . "\n"
             . "Resultado agregado das respostas:\n" . $resumo . "\n\n"
             . "Escreva a análise/orientação da enfermeira para essa pergunta em 2 a 4 frases, tom acolhedor e profissional, com foco em saúde e bem-estar (sem viés nutricional/de dieta). "
             . "Responda apenas com o parágrafo de análise, sem listas, títulos ou aspas.";

        $payload = json_encode([
            'model' => $model,
            'max_tokens' => 400,
            'system' => $sys,
            'messages' => [['role' => 'user', 'content' => $usr]]
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 45
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) anaOut(['ok'=>false,'msg'=>'Não foi possível falar com a IA: ' . $err]);
        $j = json_decode($resp, true);
        if ($code !== 200) anaOut(['ok'=>false,'msg'=>'IA retornou erro ' . $code . ': ' . ($j['error']['message'] ?? substr((string)$resp, 0, 200))]);
        $texto = isset($j['content'][0]['text']) ? trim($j['content'][0]['text']) : '';
        if ($texto === '') anaOut(['ok'=>false,'msg'=>'A IA não retornou texto.']);
        anaOut(['ok'=>true, 'texto'=>$texto]);
        break;
    }

    // ── EQUIPE: salva UMA análise (um campo/pergunta) — jeito tradicional, 1 linha por pergunta ──
    case 'salvar_analise': {
        $d = anaBody();
        $cid  = intval($d['campanha_id'] ?? 0);
        $qidx = intval($d['q_idx'] ?? -1);
        $tit  = trim((string)($d['titulo'] ?? ''));
        $txt  = (string)($d['texto'] ?? '');
        if (!$cid || $qidx < 0) anaOut(['ok'=>false,'msg'=>'Dados inválidos (campanha/pergunta)']);
        $st = db()->prepare("INSERT INTO anamnese_analises (campanha_id, q_idx, titulo, texto)
                             VALUES (?,?,?,?)
                             ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), texto = VALUES(texto)");
        $st->execute([$cid, $qidx, ($tit !== '' ? $tit : null), $txt]);
        anaOut(['ok'=>true, 'campanha_id'=>$cid, 'q_idx'=>$qidx, 'bytes'=>strlen($txt)]);
        break;
    }

    // ── EQUIPE: salva as análises (textos da nutricionista) da campanha ──
    case 'salvar_analises': {
        $d = anaBody();
        $id = intval($d['id'] ?? 0);
        $analises = $d['analises'] ?? null;
        if (!$id || !is_array($analises)) anaOut(['ok'=>false,'msg'=>'Dados inválidos']);
        // conta quantas análises têm texto de verdade
        $comTexto = 0; foreach ($analises as $v) { if (trim((string)$v) !== '') $comTexto++; }
        $st = db()->prepare("UPDATE anamnese_campanhas SET analises = ? WHERE id = ?");
        $st->execute([json_encode($analises, JSON_UNESCAPED_UNICODE), $id]);
        // relê pra confirmar que gravou de fato nessa campanha
        $chk = db()->prepare("SELECT LENGTH(analises) FROM anamnese_campanhas WHERE id = ?");
        $chk->execute([$id]);
        $bytes = intval($chk->fetchColumn());
        anaOut(['ok'=>true, 'campos'=>$comTexto, 'gravado_bytes'=>$bytes, 'id'=>$id]);
        break;
    }

    default:
        anaOut(['ok'=>false,'msg'=>'Ação desconhecida: '.$action]);
}

function anaCarregarRespostas($campanhaId){
    $st = db()->prepare("SELECT dados FROM anamnese_respostas WHERE campanha_id = ? ORDER BY id");
    $st->execute([$campanhaId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $j) {
        $obj = json_decode($j, true);
        if (is_array($obj)) $out[] = $obj;
    }
    return $out;
}
