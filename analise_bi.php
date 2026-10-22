<?php
/* ============================================================
   Análise de BI (uso e tendências) com IA (Claude / Anthropic)
   Recebe a base bruta de utilização (CSV) e devolve os textos
   dos campos do BI já resumidos. {high, consultas, ...}
   ============================================================ */
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['erro'=>'Não autenticado']); exit; }

$ANTHROPIC_API_KEY = '';
if (is_file(__DIR__.'/chave_ia.php')) { $ANTHROPIC_API_KEY = trim((string) require __DIR__.'/chave_ia.php'); }
if ($ANTHROPIC_API_KEY === '') { $ANTHROPIC_API_KEY = getenv('ANTHROPIC_API_KEY') ?: ''; }
$MODEL = 'claude-sonnet-5';

if ($ANTHROPIC_API_KEY === '') { http_response_code(500); echo json_encode(['erro'=>'Chave da API não configurada (crie chave_ia.php).']); exit; }

$dados = $_POST['dados'] ?? '';
if (trim($dados) === '') { http_response_code(400); echo json_encode(['erro'=>'Nenhum dado recebido.']); exit; }
// Limita o tamanho enviado pra IA (evita custo/limite excessivo)
if (strlen($dados) > 600000) $dados = substr($dados, 0, 600000);

$prompt =
"Você é analista de BI de planos de saúde/odontológicos. Recebe um RESUMO JÁ CALCULADO da base de utilização do período (custo total, % dos maiores usuários, contagens e custos por tipo de atendimento, top prestadores, reembolso, custo por sexo e por titular/dependente, idade média).\n".
"REGRA PRINCIPAL: só escreva o que estiver CLARO e com CERTEZA no resumo. Na dúvida, ou se faltar o dado, deixe a string VAZIA (\"\"). NUNCA invente, infira ou generalize. Use EXATAMENTE os números do resumo (não recalcule). É melhor deixar um campo vazio do que escrever algo incerto.\n\n".
"Escreva EXATAMENTE no estilo destes exemplos (mesmo tom, mesma estrutura, com os números reais da base; cite o mês de referência):\n".
"- high: \"Os 10 maiores usuários do mês de março concentraram 41,63% do custo total da utilização. A sinistralidade está relacionada a intervenções cirúrgicas, bem como a sessões de terapia além de atendimentos eletivos, como consultas e exames.\"\n".
"- consultas: \"Foram registradas 136 consultas, com custo assistencial total de R\$ 41.626,95. Deste total, 93 consultas eletivas corresponderam a um custo de R\$ 25.613,08. E 43 consultas em pronto-socorro representaram um custo de R\$ 16.013,87. As consultas foram responsáveis por 41,90% da utilização total do mês, evidenciando sua relevância no perfil assistencial. Adicionalmente, as solicitações de reembolso relacionadas a consultas somaram R\$ 12.275,31 no período.\"\n".
"- exames: \"No período analisado, foram realizados 585 exames laboratoriais e de imagem, totalizando R\$ 31.924,10, com custo médio de R\$ 54,57 por exame.\"\n".
"- internacoes: \"Em março, foi registrada 4 internações, com custo total de R\$ 3.167,43. Dessas, 1 refere-se a titular e 3 a dependentes. Em relação à porta de entrada, 3 internações ocorreram em caráter de urgência e 1 foi eletiva. As internações cirúrgicas abrangeram as especialidades de gastroenterologia e dermatologia.\"\n".
"- terapias: \"Foram registradas 131 sessões de terapia, com custo total de R\$ 20.349,27. Observa-se maior concentração de sessões em psicoterapia, fonoaudiologia e fisioterapia. As solicitações de reembolso relacionadas a terapias somaram R\$ 15.414,42.\"\n".
"- prestadores: \"Os prestadores Fleury e Hospital Mater Dei concentraram o maior impacto financeiro, destacando-se como os principais responsáveis pelo custo assistencial do mês.\"\n".
"- demografico: \"A idade média dos beneficiários com utilização no contrato é de 31 anos. Os titulares concentraram R\$ 55.710,28 em custos, o que representa 56,08% da utilização total no período. Adicionalmente, observa-se maior concentração de custos entre beneficiários do sexo feminino, que corresponderam a 64,64% da utilização mensal.\"\n\n".
"Responda SOMENTE com JSON válido (sem texto antes/depois) com as chaves: high, consultas, exames, internacoes, terapias, prestadores, produtos, ranking, demografico.\n".
"- produtos: use a linha 'Utilização por produto/plano' do resumo (distribuição de custo por plano). ranking: use a linha 'Ranking de grupos por custo'. Se não houver essas linhas, deixe vazio.\n".
"- internacoes: só preencha se o resumo tiver dados de internação; na maioria das bases ambulatoriais não há, então deixe vazio.\n".
"- NÃO cite o mês de referência (ex.: 'março') a menos que ele apareça no resumo; caso contrário escreva 'no período'.\n".
"- No high users, a frase interpretativa (sobre cirurgias/terapias/consultas) só entra se os grupos de maior custo no resumo a sustentarem claramente; senão, escreva apenas a frase com o percentual.\n".
"- Não cite especialidades, CIDs, porta de entrada ou divisão titular/dependente das internações se não estiverem no resumo.\n".
"- Se um tópico não tiver dados na base (ex.: nenhuma internação), deixe a string vazia.\n".
"- Valores monetários em R\$ no formato brasileiro (R\$ 41.626,95). Não invente números.\n\n".
"CSV:\n".$dados;

$payload = [
  'model'=>$MODEL, 'max_tokens'=>4000,
  'messages'=>[ [ 'role'=>'user', 'content'=>$prompt ] ],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
  CURLOPT_HTTPHEADER=>['content-type: application/json','x-api-key: '.$ANTHROPIC_API_KEY,'anthropic-version: 2023-06-01'],
  CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_TIMEOUT=>120,
]);
$resp=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $cerr=curl_error($ch); curl_close($ch);
if ($resp===false){ http_response_code(502); echo json_encode(['erro'=>'Falha de conexão: '.$cerr]); exit; }
if ($code!==200){ http_response_code(502); echo json_encode(['erro'=>'API HTTP '.$code,'detalhe'=>substr($resp,0,800)]); exit; }

$data=json_decode($resp,true);
// Junta todos os blocos de texto (modelos novos podem trazer "thinking" antes).
$texto='';
if (!empty($data['content']) && is_array($data['content'])) {
    foreach ($data['content'] as $blk) {
        if (is_array($blk) && ($blk['type'] ?? '') === 'text' && isset($blk['text'])) $texto .= $blk['text'];
    }
}
if ($texto === '' && isset($data['content'][0]['text'])) $texto=$data['content'][0]['text'];
$texto=preg_replace('/```[a-z]*|```/i','',$texto);    // tira cercas de markdown se houver
if (preg_match('/\{[\s\S]*\}/',$texto,$m)) $texto=$m[0];  // isola o objeto JSON
$bi=json_decode($texto,true);
if (!is_array($bi)){ http_response_code(500); echo json_encode(['erro'=>'Não interpretei a resposta','bruto'=>substr($texto,0,1200)]); exit; }
echo json_encode($bi);
