<?php
/* ============================================================
   OCR de Sinistralidade com IA de visão (Claude / Anthropic)
   Recebe a imagem da tabela e devolve {cols, rows} estruturado.
   ============================================================ */

// Sessão/auth do sistema (config.php define o nome de sessão e os helpers)
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['erro'=>'Não autenticado']); exit; }

$ANTHROPIC_API_KEY = '';
if (is_file(__DIR__.'/chave_ia.php')) { $ANTHROPIC_API_KEY = trim((string) require __DIR__.'/chave_ia.php'); }
if ($ANTHROPIC_API_KEY === '') { $ANTHROPIC_API_KEY = getenv('ANTHROPIC_API_KEY') ?: ''; }

$MODEL = 'claude-sonnet-5';   // atual. Mais preciso: claude-opus-5 | mais barato: claude-haiku-4-5-20251001

if ($ANTHROPIC_API_KEY === '') { http_response_code(500); echo json_encode(['erro'=>'Chave da API não configurada (crie chave_ia.php).']); exit; }
if (empty($_FILES['imagem']['tmp_name'])) { http_response_code(400); echo json_encode(['erro'=>'Nenhuma imagem enviada.']); exit; }

$tmp  = $_FILES['imagem']['tmp_name'];
$mime = @mime_content_type($tmp) ?: 'image/png';
if (!preg_match('#^image/(png|jpe?g|webp|gif)$#', $mime)) $mime = 'image/png';
$b64  = base64_encode(file_get_contents($tmp));

$prompt =
"Você extrai tabelas de RELATÓRIOS DE SINISTRALIDADE de planos de saúde/odontológicos a partir de uma imagem.\n".
"Responda SOMENTE com um JSON válido (nada antes ou depois), no formato:\n".
"{\"cols\":[\"...\"], \"rows\":[[\"...\"]]}\n\n".
"Regras:\n".
"- A 1ª coluna é sempre \"Data de Competência\". Use meses abreviados pt-BR: mai/25, jun/25, ... abr/26.\n".
"- Inclua TODAS as colunas de valores do relatório, mantendo os nomes originais (ex.: Faturamento, Faturamento Líquido, Repasse, Materiais Dentários, Sinistro Apurado, Evento, Custo Rede, Nº de Vidas, Per Capita...).\n".
"- A coluna de PERCENTUAL de sinistralidade deve se chamar exatamente \"Sinistralidade\".\n".
"- Se a tabela estiver TRANSPOSTA (meses nas colunas e indicadores nas linhas), transponha para meses nas linhas.\n".
"- Valores monetários no formato brasileiro (ex.: 1.366.199,08). Percentuais com % (ex.: 72,02%).\n".
"- Se existir linha de total (TOTAL, 12 Meses, Geral), inclua como ÚLTIMA linha com a 1ª célula = \"Total\".\n".
"- NÃO invente valores. Célula ilegível = string vazia. Não inclua comentários.";

$payload = [
  'model'      => $MODEL,
  'max_tokens' => 8000,
  'messages'   => [[
    'role'    => 'user',
    'content' => [
      ['type'=>'image','source'=>['type'=>'base64','media_type'=>$mime,'data'=>$b64]],
      ['type'=>'text','text'=>$prompt],
    ],
  ]],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => [
    'content-type: application/json',
    'x-api-key: '.$ANTHROPIC_API_KEY,
    'anthropic-version: 2023-06-01',
  ],
  CURLOPT_POSTFIELDS     => json_encode($payload),
  CURLOPT_TIMEOUT        => 90,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

if ($resp === false) { http_response_code(502); echo json_encode(['erro'=>'Falha de conexão com a API: '.$cerr]); exit; }
if ($code !== 200)   { http_response_code(502); echo json_encode(['erro'=>'A API retornou HTTP '.$code, 'detalhe'=>substr($resp,0,800)]); exit; }

$data  = json_decode($resp, true);
// Junta TODOS os blocos de texto da resposta. Modelos novos podem trazer um
// bloco de "thinking" antes do texto, então não dá pra usar só content[0].
$texto = '';
if (!empty($data['content']) && is_array($data['content'])) {
    foreach ($data['content'] as $blk) {
        if (is_array($blk) && ($blk['type'] ?? '') === 'text' && isset($blk['text'])) {
            $texto .= $blk['text'];
        }
    }
}
if ($texto === '' && isset($data['content'][0]['text'])) $texto = $data['content'][0]['text'];
if (preg_match('/\{.*\}/s', $texto, $m)) $texto = $m[0];   // isola o JSON
$tabela = json_decode($texto, true);

if (!$tabela || empty($tabela['cols']) || !isset($tabela['rows'])) {
  http_response_code(500);
  echo json_encode(['erro'=>'Não consegui interpretar a resposta da IA.', 'bruto'=>substr($texto,0,800)]);
  exit;
}
echo json_encode($tabela);
