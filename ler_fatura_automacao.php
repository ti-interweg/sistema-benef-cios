<?php
/* ============================================================
   Ler fatura (Faturamento Mensalidade) com IA (Claude) e
   preencher Vidas / Prêmio Líquido de uma linha do
   Faturamento Automação.
   Recebe JSON: { id, kind: 'pdf'|'image'|'text', data, mime }
   - pdf/image: data = base64 do arquivo
   - text: data = texto (ex.: xlsx convertido em CSV no navegador)
   ============================================================ */
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['erro'=>'Não autenticado']); exit; }

$ANTHROPIC_API_KEY = '';
if (is_file(__DIR__.'/chave_ia.php')) { $ANTHROPIC_API_KEY = trim((string) require __DIR__.'/chave_ia.php'); }
if ($ANTHROPIC_API_KEY === '') { $ANTHROPIC_API_KEY = getenv('ANTHROPIC_API_KEY') ?: ''; }
$MODEL = 'claude-sonnet-5';
if ($ANTHROPIC_API_KEY === '') { http_response_code(500); echo json_encode(['erro'=>'Chave da API não configurada (chave_ia.php).']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$id   = intval($in['id'] ?? 0);
$kind = $in['kind'] ?? '';
$data = $in['data'] ?? '';
$mime = $in['mime'] ?? '';
// Tabela alvo (whitelist): automacao (padrao) ou o Faturamento Atual (backup)
$tabela = in_array(($in['tabela'] ?? ''), ['faturamento_backup', 'faturamento_outubro', 'faturamento_novembro'], true)
    ? $in['tabela'] : 'faturamento_automacao';
// Ler fatura (preencher vidas/prêmio) é liberado para todos os analistas,
// inclusive nos meses novos. Só o visualizador (somente leitura) é bloqueado.
if (function_exists('isVisualizador') && isVisualizador()) {
    echo json_encode(['ok' => false, 'erro' => 'Acesso negado (somente leitura).']); exit;
}
if ($id <= 0 || $data === '') { http_response_code(400); echo json_encode(['erro'=>'Dados incompletos.']); exit; }

$instr =
 "Você recebe um Demonstrativo Analítico de Faturamento (fatura de mensalidade de plano de saúde/odonto). ".
 "Extraia EXATAMENTE estes campos e responda SÓ com um JSON, sem texto extra:\n".
 "{\"vidas\": <numero de vidas = o valor de 'Total Contrato' (a quantidade total de beneficiarios do contrato)>, ".
 "\"premio\": <PRÊMIO LÍQUIDO = o valor da linha 'Total' da tabela 'Total de Beneficiarios por Plano' (a soma dos 'Valor Total' dos planos, as mensalidades)>, ".
 "\"contrato\": \"<numero do contrato>\"}\n".
 "IMPORTANTE sobre o premio: existem DOIS totais no documento. NAO use o 'Valor' que aparece ao lado de 'Total Contrato' (esse e o total geral, que inclui movimentacoes, retroativos, descontos, devolucoes e coparticipacao). USE o 'Total' consolidado da tabela 'Total de Beneficiarios por Plano' (soma das mensalidades por plano). Ex.: se 'Total Contrato' mostra Valor 5.252,00 e a tabela por plano mostra Total 2.995,47, o premio e 2995.47.\n".
 "Regras: numeros com ponto decimal, sem R\$ e sem separador de milhar (ex.: 2995.47). ".
 "Se não achar um campo, use null.";

// Monta o conteúdo conforme o tipo do arquivo
$content = [];
if ($kind === 'pdf') {
    $content[] = ['type'=>'document','source'=>['type'=>'base64','media_type'=>'application/pdf','data'=>$data]];
    $content[] = ['type'=>'text','text'=>$instr];
} elseif ($kind === 'image') {
    $content[] = ['type'=>'image','source'=>['type'=>'base64','media_type'=>($mime ?: 'image/png'),'data'=>$data]];
    $content[] = ['type'=>'text','text'=>$instr];
} else { // text (xlsx/csv convertido no navegador)
    $content[] = ['type'=>'text','text'=>$instr."\n\nCONTEÚDO DA PLANILHA:\n".substr($data,0,60000)];
}

$payload = ['model'=>$MODEL,'max_tokens'=>500,'messages'=>[['role'=>'user','content'=>$content]]];
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
  CURLOPT_HTTPHEADER=>['content-type: application/json','x-api-key: '.$ANTHROPIC_API_KEY,'anthropic-version: 2023-06-01'],
  CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_TIMEOUT=>120,
]);
$resp=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $cerr=curl_error($ch); curl_close($ch);
if ($resp===false){ http_response_code(502); echo json_encode(['erro'=>'Falha de conexão: '.$cerr]); exit; }
if ($code!==200){ http_response_code(502); echo json_encode(['erro'=>'API HTTP '.$code,'detalhe'=>substr($resp,0,600)]); exit; }

$d=json_decode($resp,true); $txt='';
if (!empty($d['content'])) foreach ($d['content'] as $b){ if (($b['type']??'')==='text') $txt.=$b['text']; }
$txt=preg_replace('/```[a-z]*|```/i','',$txt);
if (preg_match('/\{[\s\S]*\}/',$txt,$m)) $txt=$m[0];
$ext=json_decode($txt,true);
if (!is_array($ext)){ http_response_code(500); echo json_encode(['erro'=>'Não interpretei a fatura','bruto'=>substr($txt,0,600)]); exit; }

$vidas  = isset($ext['vidas'])  && $ext['vidas']!==null  ? intval($ext['vidas']) : null;
$premio = isset($ext['premio']) && $ext['premio']!==null ? floatval($ext['premio']) : null;

// Atualiza a linha: vidas, prêmio, recalcula valor_previsto, marca Enviado
try {
    $cur = db()->prepare("SELECT percentual_comissao FROM {$tabela} WHERE id=?");
    $cur->execute([$id]); $perc = floatval($cur->fetchColumn());
    $prev = ($premio!==null) ? round($premio*$perc,2) : null;
    db()->prepare("UPDATE {$tabela}
        SET numero_vidas=COALESCE(?,numero_vidas), premio_liquido=COALESCE(?,premio_liquido),
            valor_previsto=COALESCE(?,valor_previsto), status_envio='Enviado', data_envio=COALESCE(data_envio,CURDATE())
        WHERE id=?")->execute([$vidas,$premio,$prev,$id]);
} catch (Throwable $e) { http_response_code(500); echo json_encode(['erro'=>'Erro ao salvar: '.$e->getMessage()]); exit; }

echo json_encode(['ok'=>true,'vidas'=>$vidas,'premio'=>$premio,'previsto'=>$prev,'contrato'=>$ext['contrato']??null]);
