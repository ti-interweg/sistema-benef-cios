<?php
/* ============================================================
   Apresentação Teste — lê o orçamento (PDF/planilha) com IA
   (Claude) e devolve os dados organizados.
   - contexto 'conta_nova'/'renovacao': devolve o ESQUEMA do layout
     de custos (seguradoras, produtos, faixas, vidas, valores...).
   - outros contextos: devolve {tabelas:[...]} genérico.
   Recebe JSON: { contexto, kind:'pdf'|'image'|'text', data, mime }
     pdf/image: data = base64 ; text: data = CSV
   ============================================================ */
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['erro'=>'Não autenticado']); exit; }

$ANTHROPIC_API_KEY = '';
if (is_file(__DIR__.'/chave_ia.php')) { $ANTHROPIC_API_KEY = trim((string) require __DIR__.'/chave_ia.php'); }
if ($ANTHROPIC_API_KEY === '') { $ANTHROPIC_API_KEY = getenv('ANTHROPIC_API_KEY') ?: ''; }
$MODEL = 'claude-sonnet-5';
if ($ANTHROPIC_API_KEY === '') { http_response_code(500); echo json_encode(['erro'=>'Chave da API não configurada (chave_ia.php).']); exit; }

$in   = json_decode(file_get_contents('php://input'), true) ?: [];
$ctx  = $in['contexto'] ?? '';
$kind = $in['kind'] ?? 'text';
$data = (string)($in['data'] ?? '');
$mime = $in['mime'] ?? '';
if (trim($data) === '') { http_response_code(400); echo json_encode(['erro'=>'Arquivo vazio.']); exit; }

$isLayout = ($ctx === 'conta_nova' || $ctx === 'renovacao');

if ($isLayout) {
  $instr =
    "Você recebe uma cotação de plano de saúde de uma seguradora (PDF gerado pelo site da operadora). ".
    "O arquivo pode conter MAIS DE UM plano/perfil (ex.: 'Perfil 1: Funcionários' e 'Perfil 2: Sócios', ou vários produtos). ".
    "Gere UM objeto para CADA plano/perfil encontrado. Cada plano tem uma distribuição de VIDAS por faixa etária e o VALOR POR VIDA (valor unitário) por faixa. ".
    "Responda SÓ com um JSON válido, sem texto extra, neste formato EXATO:\n".
    "{ \"cotacoes\": [\n".
    "  {\n".
    "    \"seguradora\": \"<AMIL / BRADESCO / SULAMÉRICA / etc>\",\n".
    "    \"produto\": \"<nome do plano/perfil, ex.: PRATA QP COPART, NACIONAL PLUS x6, 'Funcionários - TPN6'...>\",\n".
    "    \"abrangencia\": \"<Nacional / Regional...>\",\n".
    "    \"coparticipacao\": \"<SIM / NÃO / Parcial>\",\n".
    "    \"reembolso\": <valor do reembolso de consulta médica em número, ou null>,\n".
    "    \"iof_pct\": <percentual de IOF se aparecer, senão null>,\n".
    "    \"faixas\": [\"até 18\",\"19 - 23\",\"24 - 28\",\"29 - 33\",\"34 - 38\",\"39 - 43\",\"44 - 48\",\"49 - 53\",\"54 - 58\",\"59 ou mais\"],\n".
    "    \"vidas\":   [<vidas na faixa1>, <faixa2>, ...],  (a QUANTIDADE de vidas por faixa; 0 quando vazio)\n".
    "    \"valores\": [<valor POR VIDA na faixa1>, <faixa2>, ...]  (o 'valor por vida'/valor unitário de saúde de cada faixa, na MESMA ordem)\n".
    "  }\n".
    "] }\n".
    "Regras: números com ponto decimal, SEM 'R\$' e SEM separador de milhar (ex.: 209.78). ".
    "Use o 'Valor por vida' / valor unitário de Saúde (NÃO o subtotal nem o valor conjugado com dental). Mantenha as 10 faixas na ordem. ".
    "Se a coparticipação estiver no nome ou no plano (ex.: 'Com Copart', 'COPART 30%') use SIM. NÃO invente valores. ".
    "Se houver só um plano, devolva um array com um único objeto.";
} else {
  $instr =
    "Você recebe uma planilha de coparticipação de plano de saúde. Organize e devolva SÓ um JSON, sem texto extra: ".
    "{\"tabelas\": [ {\"titulo\": \"...\", \"colunas\": [\"...\"], \"linhas\": [[\"...\"]] } ] }. ".
    "Mantenha valores em R\$ como aparecem. Uma tabela por bloco. Não invente.";
}

// Monta o conteúdo conforme o tipo do arquivo
$content = [];
if ($kind === 'pdf') {
  $content[] = ['type'=>'document','source'=>['type'=>'base64','media_type'=>'application/pdf','data'=>$data]];
  $content[] = ['type'=>'text','text'=>$instr];
} elseif ($kind === 'image') {
  $content[] = ['type'=>'image','source'=>['type'=>'base64','media_type'=>($mime ?: 'image/png'),'data'=>$data]];
  $content[] = ['type'=>'text','text'=>$instr];
} else {
  $csv = strlen($data) > 200000 ? substr($data, 0, 200000) : $data;
  $content[] = ['type'=>'text','text'=>$instr."\n\nCONTEÚDO (CSV):\n".$csv];
}

$payload = ['model'=>$MODEL,'max_tokens'=>8000,'messages'=>[['role'=>'user','content'=>$content]]];
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
  CURLOPT_HTTPHEADER=>['content-type: application/json','x-api-key: '.$ANTHROPIC_API_KEY,'anthropic-version: 2023-06-01'],
  CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_TIMEOUT=>180,
]);
$resp=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $cerr=curl_error($ch); curl_close($ch);
if ($resp===false){ http_response_code(502); echo json_encode(['erro'=>'Falha de conexão: '.$cerr]); exit; }
if ($code!==200){ http_response_code(502); echo json_encode(['erro'=>'API HTTP '.$code,'detalhe'=>substr($resp,0,600)]); exit; }

$d=json_decode($resp,true); $txt='';
if (!empty($d['content'])) foreach ($d['content'] as $b){ if (($b['type']??'')==='text') $txt.=$b['text']; }
$txt=preg_replace('/```[a-z]*|```/i','',$txt);
if (preg_match('/\{[\s\S]*\}/',$txt,$m)) $txt=$m[0];
$ext=json_decode($txt,true);
if (!is_array($ext)){ http_response_code(500); echo json_encode(['erro'=>'Não interpretei o orçamento','bruto'=>substr($txt,0,600)]); exit; }

if ($isLayout) {
  // Normaliza: aceita {cotacoes:[...]}, {cotacao:{...}} ou o próprio objeto único.
  $cots = [];
  if (isset($ext['cotacoes']) && is_array($ext['cotacoes'])) { $cots = $ext['cotacoes']; }
  elseif (isset($ext['cotacao']) && is_array($ext['cotacao'])) { $cots = [$ext['cotacao']]; }
  elseif (isset($ext['faixas']) || isset($ext['valores'])) { $cots = [$ext]; }
  echo json_encode(['ok'=>true,'cotacoes'=>array_values($cots)]);
}
else { echo json_encode(['ok'=>true,'tabelas'=>$ext['tabelas'] ?? []]); }
