<?php
/**
 * Dados compartilhados dos Indicadores de Uso (página interna + link público).
 * Requer config.php (db()). NÃO faz autenticação — cada página cuida do acesso.
 *
 * Controle da geração da análise por IA: defina $AUD_GERAR antes de incluir:
 *   'no'        (padrão) = apenas lê o cache do dia
 *   'ifmissing' = gera se ainda não houver análise para hoje
 *   'force'     = regenera a análise de hoje
 */
if (!function_exists('db')) require_once __DIR__ . '/config.php';
if (!isset($AUD_GERAR)) $AUD_GERAR = 'no';

/* ── Rótulos amigáveis dos menus/páginas/módulos ── */
$PAGINAS = [
  'demandas' => 'Demandas', 'resumo_movimentacoes' => 'Resumo Mensal',
  'faturamento' => 'Faturamento', 'renovacao' => 'Renovação',
  'atas_visitas' => 'Atas de Visitas', 'dashboard' => 'Dashboard',
  'analistas' => 'Equipe', 'cadastros' => 'Cadastros', 'config' => 'Configurações',
  'mfa_admin' => 'Verificação 2 etapas', 'auditoria_admin' => 'Histórico e Auditoria',
  'auditoria_indicadores' => 'Indicadores de Uso',
  'demandas_assistenciais' => 'Demandas Assistenciais', 'acoes_saude' => 'Ações de Saúde',
  'sinistralidade' => 'Sinistralidade', 'devolutiva_nutricional' => 'Devolutiva de Saúde',
  'controle_cotacao' => 'Controle de Cotação', 'fechamento' => 'Fechamento',
  'passagem_bastao' => 'Passagem de Bastão', 'formcot' => 'Formulário Cotação',
  'apresentacao' => 'Apresentação', 'perfil' => 'Meu Perfil', 'index' => 'Início',
  'demanda' => 'Demandas', 'empresa' => 'Empresa / Cadastro', 'cotacao_controle' => 'Controle de Cotação',
  'fechamento_mensal' => 'Fechamento (mensal)', 'status_faturamento_lote' => 'Faturamento (lote)',
  'demandas_lote' => 'Demandas (lote)', 'ata' => 'Ata de Visita', 'analise' => 'Devolutiva (análise)',
];
if (!function_exists('ai_esc')) { function ai_esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('ai_lbl')) { function ai_lbl($k){ global $PAGINAS; return $PAGINAS[$k] ?? ucwords(str_replace('_',' ',(string)$k)); } }

// Gera a análise diária via Claude. Retorna '' se a chave não existir ou a IA falhar.
if (!function_exists('ai_gerar_analise')) {
function ai_gerar_analise($resumo){
    $apiKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : (getenv('CLAUDE_API_KEY') ?: '');
    if (!$apiKey) return '';
    $model = defined('CLAUDE_MODEL') ? trim((string)CLAUDE_MODEL) : '';
    if ($model === '' || stripos($model, 'sk-ant') !== false) $model = 'claude-haiku-4-5-20251001';
    $sys = 'Você é um analista de dados da Interweg escrevendo um resumo executivo diário sobre o uso do sistema interno de benefícios, para a gestão. '
         . 'Escreva em português do Brasil, tom objetivo e prático. Aponte o que é mais e menos usado, quem usa mais, onde se concentram as alterações e os menus sem uso, '
         . 'e termine com uma recomendação curta. De 3 a 5 frases, em um único parágrafo, sem listas, títulos ou aspas. '
         . 'Não use travessões nem hífens longos (—); use vírgulas, parênteses ou ponto final.';
    $usr = "Dados de uso do sistema (do histórico de auditoria):\n" . $resumo;
    $payload = json_encode([
        'model' => $model, 'max_tokens' => 500, 'system' => $sys,
        'messages' => [['role' => 'user', 'content' => $usr]]
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['content-type: application/json', 'x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 30
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) return '';
    $j = json_decode($resp, true);
    if ($code !== 200) return '';
    return isset($j['content'][0]['text']) ? trim($j['content'][0]['text']) : '';
}
}

/* ── Consultas (tudo agregado no banco) ── */
$totGeral = 0; $dtMin = null; $dtMax = null;
try {
  $r = db()->query("SELECT COUNT(*) c, MIN(criado_em) mn, MAX(criado_em) mx FROM log_auditoria")->fetch();
  $totGeral = intval($r['c']); $dtMin = $r['mn']; $dtMax = $r['mx'];
} catch (Throwable $e) {}

$porEvento = ['acesso'=>0,'alteracao'=>0,'exclusao'=>0,'login'=>0,'logout'=>0];
try { foreach (db()->query("SELECT evento, COUNT(*) c FROM log_auditoria GROUP BY evento") as $x){ $porEvento[$x['evento']] = intval($x['c']); } } catch (Throwable $e) {}

$accPag = [];
try { foreach (db()->query("SELECT acao, COUNT(*) c FROM log_auditoria WHERE evento='acesso' GROUP BY acao") as $x){ $accPag[$x['acao']] = intval($x['c']); } } catch (Throwable $e) {}

$rankPaginas = [];
foreach ($PAGINAS as $k => $lbl) {
  if (in_array($k, ['index','auditoria_indicadores'], true)) continue;
  if (in_array($k, ['demanda','empresa','cotacao_controle','fechamento_mensal','status_faturamento_lote','demandas_lote','ata','analise'], true)) continue; // são módulos, não páginas
  $rankPaginas[] = ['k'=>$k, 'lbl'=>$lbl, 'c'=>($accPag[$k] ?? 0)];
}
foreach ($accPag as $k => $c){ if (!isset($PAGINAS[$k])) $rankPaginas[] = ['k'=>$k,'lbl'=>ai_lbl($k),'c'=>$c]; }
usort($rankPaginas, function($a,$b){ return $b['c'] - $a['c']; });
$maxPag = 0; foreach ($rankPaginas as $p){ if ($p['c'] > $maxPag) $maxPag = $p['c']; }
$maxPag = $maxPag ?: 1;

$porUsuario = [];
try { foreach (db()->query("SELECT COALESCE(NULLIF(user_nome,''),'—') nome, user_tipo tipo, COUNT(*) c FROM log_auditoria WHERE evento='acesso' GROUP BY nome, tipo ORDER BY c DESC LIMIT 15") as $x){ $porUsuario[] = $x; } } catch (Throwable $e) {}
$maxUser = 0; foreach ($porUsuario as $u){ if ($u['c'] > $maxUser) $maxUser = intval($u['c']); } $maxUser = $maxUser ?: 1;

$porModulo = [];
try { foreach (db()->query("SELECT COALESCE(NULLIF(entidade,''),'demanda') ent, COUNT(*) c FROM log_auditoria WHERE evento IN('alteracao','exclusao') GROUP BY ent ORDER BY c DESC LIMIT 15") as $x){ $porModulo[] = $x; } } catch (Throwable $e) {}
$maxMod = 0; foreach ($porModulo as $m){ if ($m['c'] > $maxMod) $maxMod = intval($m['c']); } $maxMod = $maxMod ?: 1;

$porDia = [];
try { foreach (db()->query("SELECT DATE(criado_em) d, COUNT(*) c FROM log_auditoria WHERE criado_em >= (CURDATE() - INTERVAL 13 DAY) GROUP BY d ORDER BY d") as $x){ $porDia[$x['d']] = intval($x['c']); } } catch (Throwable $e) {}
$maxDia = 0; foreach ($porDia as $c){ if ($c > $maxDia) $maxDia = $c; } $maxDia = $maxDia ?: 1;

$usados = array_values(array_filter($rankPaginas, function($p){ return $p['c'] > 0; }));
$naoUsados = array_values(array_filter($rankPaginas, function($p){ return $p['c'] === 0; }));

/* ── Análise diária por IA (cache 1x por dia) ── */
try { db()->exec("CREATE TABLE IF NOT EXISTS analise_uso_diaria (dia DATE PRIMARY KEY, texto TEXT, gerado_em DATETIME)"); } catch (Throwable $e) {}
$periodoIA = ($dtMin && $dtMax) ? (date('d/m/Y', strtotime($dtMin)) . ' a ' . date('d/m/Y', strtotime($dtMax))) : '';
$analiseIA = ''; $analiseEm = '';
$hojeIA = date('Y-m-d');
try {
    if ($AUD_GERAR !== 'force') {
        $q = db()->prepare("SELECT texto, gerado_em FROM analise_uso_diaria WHERE dia = ?");
        $q->execute([$hojeIA]);
        $row = $q->fetch();
        if ($row) { $analiseIA = (string)$row['texto']; $analiseEm = (string)$row['gerado_em']; }
    }
    $precisaGerar = ($analiseIA === '' && in_array($AUD_GERAR, ['ifmissing','force'], true));
    if ($precisaGerar) {
        $topTxt = []; foreach (array_slice($usados, 0, 6) as $p) { $topTxt[] = $p['lbl'] . ' (' . $p['c'] . ')'; }
        $usrTxt = []; foreach (array_slice($porUsuario, 0, 5) as $u) { $usrTxt[] = $u['nome'] . ' (' . $u['c'] . ')'; }
        $modTxt = []; foreach (array_slice($porModulo, 0, 6) as $m) { $modTxt[] = ai_lbl($m['ent']) . ' (' . $m['c'] . ')'; }
        $semTxt = []; foreach ($naoUsados as $p) { $semTxt[] = $p['lbl']; }
        $resumoIA = "Periodo: " . ($periodoIA ?: 'n/d') . ".\n"
            . "Total de registros: {$totGeral}. Acessos a paginas: {$porEvento['acesso']}. Alteracoes: " . ($porEvento['alteracao'] + $porEvento['exclusao']) . ". Logins: {$porEvento['login']}.\n"
            . "Paginas mais usadas (acessos): " . (count($topTxt) ? implode(', ', $topTxt) : 'sem dados') . ".\n"
            . "Menus sem nenhum acesso: " . (count($semTxt) ? implode(', ', $semTxt) : 'nenhum') . ".\n"
            . "Usuarios mais ativos: " . (count($usrTxt) ? implode(', ', $usrTxt) : 'sem dados') . ".\n"
            . "Alteracoes por modulo: " . (count($modTxt) ? implode(', ', $modTxt) : 'sem dados') . ".";
        $t = ai_gerar_analise($resumoIA);
        if ($t !== '') {
            try { db()->prepare("INSERT INTO analise_uso_diaria (dia, texto, gerado_em) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE texto = VALUES(texto), gerado_em = NOW()")->execute([$hojeIA, $t]); } catch (Throwable $e) {}
            $analiseIA = $t; $analiseEm = date('Y-m-d H:i:s');
        }
    }
} catch (Throwable $e) {}
