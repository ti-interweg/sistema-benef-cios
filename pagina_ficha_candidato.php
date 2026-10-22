<?php
if (!defined('AD_SISTEMA')) { http_response_code(403); exit('Acesso negado.'); }
// =====================================================================
// Ficha de Registro do candidato aprovado (e-Social) - página pública.
// O candidato recebe o link por e-mail quando é aprovado, preenche os
// próprios dados e o RH aproveita tudo na contratação.
// =====================================================================

$fcId  = (int)($_GET['fichacand'] ?? ($_POST['id'] ?? 0));
$fcTok = (string)($_GET['t'] ?? ($_POST['t'] ?? ''));
$fcOkTela = ($_GET['ok'] ?? '') === '1';
$fcCand = null;
if ($fcId > 0 && hash_equals(ad_ficha_cand_token($fcId), $fcTok) && $pdo) {
    try { $st = $pdo->prepare("SELECT * FROM candidatos WHERE id=?"); $st->execute([$fcId]); $fcCand = $st->fetch() ?: null; } catch (Throwable $e) { $fcCand = null; }
}

// Campos que o candidato preenche (guardados em JSON na candidatura).
$fcCampos = [
    'sexo', 'nascimento', 'local_nascimento', 'estado_nascimento', 'pais_nascimento', 'estado_civil', 'cor_raca', 'grau_instrucao', 'deficiente', 'deficiencia_desc',
    'residencia', 'numero', 'bairro', 'cidade', 'estado', 'cep',
    'nome_pai', 'nome_mae',
    'cpf', 'identidade', 'identidade_estado', 'identidade_emissao', 'ctps_num', 'ctps_serie', 'ctps_estado', 'ctps_emissao', 'pis',
    'titulo_eleitor', 'titulo_zona', 'titulo_secao', 'doc_militar', 'doc_militar_categoria',
    'cnh_num', 'cnh_categoria', 'cnh_primeira', 'cnh_orgao', 'cnh_venc',
    'telefone', 'celular', 'contato1', 'contato2',
    'emergencia_nome', 'emergencia_parentesco', 'emergencia_tel',
    'vale_transporte', 'residencia_propria', 'contribuicao_sindical',
    'dep1_nome', 'dep1_nascimento', 'dep1_cpf', 'dep2_nome', 'dep2_nascimento', 'dep2_cpf',
    'dep3_nome', 'dep3_nascimento', 'dep3_cpf', 'dep4_nome', 'dep4_nascimento', 'dep4_cpf',
];
$fcOpSexo   = ['Masculino', 'Feminino'];
$fcOpCivil  = ['Solteiro(a)', 'Casado(a)', 'União estável', 'Divorciado(a)', 'Viúvo(a)'];
$fcOpRaca   = ['Branca', 'Preta', 'Parda', 'Amarela', 'Indígena', 'Prefiro não informar'];
$fcOpInstr  = ['Ensino fundamental incompleto', 'Ensino fundamental completo', 'Ensino médio incompleto', 'Ensino médio completo', 'Técnico', 'Superior incompleto', 'Superior completo', 'Pós-graduação', 'MBA', 'Mestrado', 'Doutorado'];
$fcOpSimNao = ['Sim', 'Não'];
// Tipos de documento da admissão (um botão de envio para cada).
$fcTiposDocs = [
    'atestado'      => 'Atestado médico admissional',
    'cpf_filhos'    => 'CPF dos filhos',
    'cpf_conjuge'   => 'CPF do cônjuge',
    'cert_casamento' => 'Certidão de casamento',
    'cert_nascimento' => 'Certidão de nascimento dos filhos (menores de 14 anos)',
    'cartao_vacina' => 'Cartão de vacina (filhos menores de 6 anos)',
    'escolaridade_filhos' => 'Comprovante de escolaridade (filhos de 6 a 14 anos)',
    'cnh'           => 'CNH (somente se a função envolver dirigir)',
    'outros'        => 'Outros documentos',
];

$fcErro = '';
$fcVals = [];
foreach ($fcCampos as $cF) { $fcVals[$cF] = ''; }

// Pré-preenche com o que o candidato já informou na Ficha de Cadastro do processo seletivo.
if ($fcCand) {
    $cV = function (string $k) use ($fcCand): string { return trim((string)($fcCand[$k] ?? '')); };
    $nrm = function (string $s): string { return mb_strtolower(trim($s)); };
    // Sexo a partir do gênero do cadastro.
    $gN = $nrm($cV('genero'));
    if (str_starts_with($gN, 'fem')) { $fcVals['sexo'] = 'Feminino'; }
    elseif (str_starts_with($gN, 'masc')) { $fcVals['sexo'] = 'Masculino'; }
    // Estado civil (texto livre no cadastro vira a opção equivalente).
    $ecN = $nrm($cV('estado_civil'));
    foreach (['solteir' => 'Solteiro(a)', 'casad' => 'Casado(a)', 'uni' => 'União estável', 'divorc' => 'Divorciado(a)', 'viuv' => 'Viúvo(a)', 'viúv' => 'Viúvo(a)'] as $pfx => $op) {
        if ($ecN !== '' && str_starts_with($ecN, $pfx)) { $fcVals['estado_civil'] = $op; break; }
    }
    // Grau de instrução a partir da escolaridade do cadastro.
    $esN = $nrm($cV('escolaridade'));
    if ($esN !== '') {
        if (mb_strpos($esN, 'doutor') !== false) { $fcVals['grau_instrucao'] = 'Doutorado'; }
        elseif (mb_strpos($esN, 'mestr') !== false) { $fcVals['grau_instrucao'] = 'Mestrado'; }
        elseif (mb_strpos($esN, 'mba') !== false) { $fcVals['grau_instrucao'] = 'MBA'; }
        elseif (mb_strpos($esN, 'pós') !== false || mb_strpos($esN, 'pos') !== false) { $fcVals['grau_instrucao'] = 'Pós-graduação'; }
        elseif (mb_strpos($esN, 'superior') !== false) { $fcVals['grau_instrucao'] = mb_strpos($esN, 'incomp') !== false ? 'Superior incompleto' : 'Superior completo'; }
        elseif (mb_strpos($esN, 'técnic') !== false || mb_strpos($esN, 'tecnic') !== false) { $fcVals['grau_instrucao'] = 'Técnico'; }
        elseif (mb_strpos($esN, 'médio') !== false || mb_strpos($esN, 'medio') !== false) { $fcVals['grau_instrucao'] = mb_strpos($esN, 'incomp') !== false ? 'Ensino médio incompleto' : 'Ensino médio completo'; }
        elseif (mb_strpos($esN, 'fundamental') !== false) { $fcVals['grau_instrucao'] = mb_strpos($esN, 'incomp') !== false ? 'Ensino fundamental incompleto' : 'Ensino fundamental completo'; }
    }
    // Cidade/UF do cadastro pode vir junta ("Belo Horizonte/MG").
    $cidC = $cV('cidade'); $ufC = $cV('estado');
    if ($ufC === '' && mb_strpos($cidC, '/') !== false) {
        $ptsC = array_map('trim', explode('/', $cidC, 2));
        $cidC = $ptsC[0]; $ufC = $ptsC[1] ?? '';
    }
    // Estado do RG a partir do órgão expedidor (ex.: SSP-MG).
    if (preg_match('/-\s*([A-Za-z]{2})$/', $cV('rg_orgao'), $mUf)) { $fcVals['identidade_estado'] = mb_strtoupper($mUf[1]); }
    $fcVals['nascimento']         = $cV('nascimento');
    $fcVals['local_nascimento']   = $cV('naturalidade');
    $fcVals['pais_nascimento']    = 'Brasil';
    $fcVals['residencia']         = $cV('endereco');
    $fcVals['cep']                = $cV('cep');
    $fcVals['cidade']             = $cidC;
    $fcVals['estado']             = $ufC;
    $fcVals['nome_pai']           = $cV('nome_pai');
    $fcVals['nome_mae']           = $cV('nome_mae');
    $fcVals['cpf']                = $cV('cpf');
    $fcVals['identidade']         = $cV('rg');
    $fcVals['identidade_emissao'] = $cV('rg_expedicao');
    $fcVals['telefone']           = $cV('telefone');
    $fcVals['celular']            = $cV('celular');
    $fcVals['emergencia_nome']       = $cV('emergencia_nome');
    $fcVals['emergencia_parentesco'] = $cV('emergencia_parentesco');
    $fcVals['emergencia_tel']        = $cV('emergencia_tel');
}
// O que já foi salvo na própria Ficha de Registro tem prioridade sobre o pré-preenchimento.
if ($fcCand && trim((string)($fcCand['ficha_json'] ?? '')) !== '') {
    $j = json_decode((string)$fcCand['ficha_json'], true);
    if (is_array($j)) {
        foreach ($fcCampos as $cF) {
            $jv = trim((string)($j[$cF] ?? ''));
            if ($jv !== '') { $fcVals[$cF] = $jv; }
        }
    }
}

// ---- Salvar a ficha ----
if ($fcCand && ($_POST['acao'] ?? '') === 'salvar_ficha_cand') {
    foreach ($fcCampos as $cF) { $fcVals[$cF] = mb_substr(trim((string)($_POST[$cF] ?? '')), 0, 300); }
    if (!in_array($fcVals['sexo'], $fcOpSexo, true)) { $fcVals['sexo'] = ''; }
    if (!in_array($fcVals['estado_civil'], $fcOpCivil, true)) { $fcVals['estado_civil'] = ''; }
    if (!in_array($fcVals['cor_raca'], $fcOpRaca, true)) { $fcVals['cor_raca'] = ''; }
    if (!in_array($fcVals['grau_instrucao'], $fcOpInstr, true)) { $fcVals['grau_instrucao'] = ''; }
    foreach (['deficiente', 'vale_transporte', 'residencia_propria', 'contribuicao_sindical'] as $cSN) {
        if (!in_array($fcVals[$cSN], $fcOpSimNao, true)) { $fcVals[$cSN] = ''; }
    }
    foreach (['nascimento', 'identidade_emissao', 'ctps_emissao', 'cnh_primeira', 'cnh_venc'] as $cDt) {
        if ($fcVals[$cDt] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fcVals[$cDt])) { $fcVals[$cDt] = ''; }
    }
    if ($fcVals['nascimento'] === '' || $fcVals['cpf'] === '' || $fcVals['residencia'] === '' || $fcVals['cidade'] === '' || $fcVals['celular'] === '' || $fcVals['nome_mae'] === '') {
        $fcErro = 'Preencha os campos obrigatórios: data de nascimento, CPF, endereço, cidade, celular e nome da mãe.';
    } else {
        try {
            $pdo->prepare("UPDATE candidatos SET ficha_json=?, ficha_preenchida_em=NOW(), atualizado_em=NOW() WHERE id=?")
                ->execute([json_encode($fcVals, JSON_UNESCAPED_UNICODE), $fcId]);
            ad_log($pdo, 'Alteração', 'Candidatos', (string)$fcCand['nome'], 'Ficha de registro preenchida pelo candidato (link externo)', 'Ficha do candidato', (string)($fcCand['email'] ?? ''));
            // Candidato já contratado: os dados vão direto para a ficha do funcionário.
            $fcFid = (int)($fcCand['funcionario_id'] ?? 0);
            if ($fcFid > 0) {
                ad_ficha_aplicar_funcionario($pdo, $fcVals, $fcFid);
                ad_log($pdo, 'Alteração', 'Funcionários', (string)$fcCand['nome'], 'Ficha do funcionário atualizada com os dados da Ficha de Registro preenchida pelo colaborador', 'Ficha do candidato', (string)($fcCand['email'] ?? ''));
            }
            // Documentos da admissão anexados pelo candidato (um envio por tipo,
            // arquivo renomeado com o nome do candidato, o documento e a data).
            $fcDocsOk = 0; $fcDocsErro = '';
            $extPermitidas = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'];
            $dirBase = __DIR__ . '/docs_candidatos';
            $dirCand = $dirBase . '/' . $fcId;
            $nomeLimpo = trim(preg_replace('/[^\p{L}\p{N} \-]/u', '', (string)$fcCand['nome']));
            foreach ($fcTiposDocs as $tpChave => $tpRotulo) {
                $campoArq = $_FILES['doc_' . $tpChave] ?? null;
                if (!$campoArq || (int)($campoArq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { continue; }
                $tam = (int)($campoArq['size'] ?? 0);
                $ext = mb_strtolower(pathinfo((string)($campoArq['name'] ?? ''), PATHINFO_EXTENSION));
                if (!in_array($ext, $extPermitidas, true)) { $fcDocsErro = 'Alguns arquivos foram recusados: envie somente PDF ou foto (JPG, PNG).'; continue; }
                if ($tam <= 0 || $tam > 10 * 1024 * 1024) { $fcDocsErro = 'Alguns arquivos foram recusados por passarem de 10 MB.'; continue; }
                if (!is_dir($dirCand)) { @mkdir($dirCand, 0755, true); }
                if (!is_file($dirBase . '/.htaccess')) {
                    @file_put_contents($dirBase . '/.htaccess', "Require all denied\nOrder allow,deny\nDeny from all\n");
                }
                $novoNome = bin2hex(random_bytes(10)) . '.' . $ext;
                $nomeExibicao = mb_substr($nomeLimpo . ' - ' . $tpRotulo . ' - ' . date('d-m-Y'), 0, 190) . '.' . $ext;
                if (@move_uploaded_file((string)$campoArq['tmp_name'], $dirCand . '/' . $novoNome)) {
                    try {
                        $pdo->prepare("INSERT INTO candidato_docs (candidato_id, arquivo, nome_original, tipo, tamanho, enviado_em) VALUES (?,?,?,?,?,NOW())")
                            ->execute([$fcId, $novoNome, $nomeExibicao, $tpRotulo, $tam]);
                        $fcDocsOk++;
                    } catch (Throwable $e) { @unlink($dirCand . '/' . $novoNome); }
                }
            }
            if ($fcDocsOk > 0) {
                ad_log($pdo, 'Alteração', 'Candidatos', (string)$fcCand['nome'], $fcDocsOk . ' documento(s) da admissão enviados pelo candidato', 'Ficha do candidato', (string)($fcCand['email'] ?? ''));
            }
            // Avisa o RH por e-mail que a ficha foi preenchida.
            try {
                require_once __DIR__ . '/email_helpers.php';
                $cfgN = (array)(ad_config()['email'] ?? []);
                $paraN = trim((string)($cfgN['responder_para'] ?? ($cfgN['remetente'] ?? '')));
                if ($paraN !== '' && filter_var($paraN, FILTER_VALIDATE_EMAIL)) {
                    $urlCand = 'https://interweg-financeiro.online/index.php?page=candidatos&editar=' . $fcId;
                    $cargoN = trim((string)($fcCand['cargo_desejado'] ?? ''));
                    $corpoN = '<p>O candidato <b>' . htmlspecialchars((string)$fcCand['nome']) . '</b>'
                        . ($cargoN !== '' ? ' (vaga: <b>' . htmlspecialchars($cargoN) . '</b>)' : '')
                        . ' acabou de preencher a Ficha de Registro.</p>'
                        . '<p>' . ($fcFid > 0 ? 'Como ele já está contratado, os dados foram aplicados na ficha de funcionário.' : 'Os dados já estão na ficha dele no menu Candidatos, prontos para a contratação.') . '</p>'
                        . ($fcDocsOk > 0 ? '<p>Ele também anexou <b>' . $fcDocsOk . ' documento(s)</b> da admissão, disponíveis para download na ficha do candidato.</p>' : '')
                        . '<p style="text-align:center;margin:22px 0"><a href="' . htmlspecialchars($urlCand) . '" style="background:#00C0B3;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:9px;font-weight:bold;display:inline-block">Abrir o candidato</a></p>';
                    ad_email_enviar($paraN, 'Ficha de Registro preenchida: ' . (string)$fcCand['nome'], ad_email_layout('Ficha de Registro preenchida', 'Interweg Seguros · Candidatos', $corpoN));
                }
            } catch (Throwable $e) { /* o preenchimento vale mesmo se o aviso falhar */ }
            header('Location: index.php?fichacand=' . $fcId . '&t=' . urlencode($fcTok) . '&ok=1'); exit;
        } catch (Throwable $e) {
            $fcErro = 'Não foi possível salvar agora. Tente novamente em instantes.';
        }
    }
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<!-- Ficha de Registro v2: pre-preenchida com o de/para da Ficha de Cadastro -->
<title>Ficha de Registro · Interweg Seguros</title>
<style>
  body{margin:0;font-family:'Segoe UI',system-ui,Arial,sans-serif;background:linear-gradient(135deg,#e6faf8,#f0f3f7);min-height:100vh;padding:30px 14px;box-sizing:border-box}
  .box{background:#fff;border:1px solid #e6eaf2;border-radius:18px;box-shadow:0 20px 50px rgba(15,23,42,.12);max-width:760px;margin:0 auto;padding:32px 30px}
  .marca{display:flex;flex-direction:column;align-items:center;gap:8px;margin:0 0 18px}
  .marca img{max-height:50px;max-width:250px;width:auto;height:auto}
  .marca small{font-size:10.5px;color:#5a6472;letter-spacing:.12em;font-weight:700}
  h1{font-size:21px;text-align:center;margin:0 0 4px;color:#0b1220}
  p.sub{text-align:center;color:#5a6472;font-size:13.5px;margin:0 0 22px;line-height:1.6}
  h2.sec{font-size:13px;color:#00857B;letter-spacing:.08em;text-transform:uppercase;border-bottom:2px solid #e6faf8;padding-bottom:7px;margin:26px 0 14px}
  .grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px 14px}
  .g2{grid-column:span 2}.g3{grid-column:1 / -1}
  label{display:block;font-size:12px;font-weight:700;color:#5a6472;margin:0 0 5px}
  input,select,textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #e6eaf2;border-radius:10px;font-size:14px;font-family:inherit;color:#0b1220;background:#fff}
  input:focus,select:focus{outline:none;border-color:#00C0B3;box-shadow:0 0 0 3px rgba(0,192,179,.15)}
  input[readonly]{background:#f6f8fb;color:#5a6472}
  button{width:100%;margin-top:24px;background:#00C0B3;color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer}
  button:hover{background:#00A99E}
  .err{background:#FEE2E2;color:#B91C1C;font-size:13px;padding:10px 13px;border-radius:9px;margin:0 0 16px;text-align:center}
  .obr{color:#B91C1C}
  .docs{background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:14px 16px;font-size:12.5px;color:#92400E;line-height:1.7;margin-top:22px}
  .btn-imprimir{display:inline-flex;align-items:center;gap:8px;width:auto;margin:0 auto 4px;background:#fff;color:#00857B;border:1.5px solid #00C0B3;border-radius:9px;padding:9px 18px;font-size:13px;font-weight:700;cursor:pointer}
  .btn-imprimir:hover{background:#e6faf8}
  .topo-acao{text-align:center;margin:0 0 18px}
  @media print{
    body{background:#fff;padding:0}
    .box{box-shadow:none;border:none;max-width:100%;padding:0}
    .topo-acao,button[type=submit],input[type=file],.aceite input{display:none}
    input,select,textarea{border:none;border-bottom:1px solid #9aa7b3;border-radius:0;padding:4px 2px}
    h2.sec{page-break-after:avoid}
    .grid > div{page-break-inside:avoid}
  }
  .okbox{text-align:center;padding:20px 0 6px}
  .okbox .ic{width:56px;height:56px;border-radius:99px;background:#D1FAE5;color:#065F46;font-size:24px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
  .okbox h2{font-size:19px;color:#0b1220;margin:0 0 8px}
  .okbox p{color:#5a6472;font-size:14px;line-height:1.7;margin:0 0 10px}
  @media (max-width:640px){.grid{grid-template-columns:1fr 1fr}.g2,.g3{grid-column:1 / -1}}
  @media (max-width:430px){.grid{grid-template-columns:1fr}}
</style></head><body>
  <div class="box">
    <div class="marca"><img src="https://interweg-financeiro.online/logo_cinza.png" alt="Interweg"></div>
    <?php if (!$fcCand): ?>
      <h1>Link inválido</h1>
      <p class="sub">Este link de ficha de registro não é válido ou expirou. Fale com o RH da Interweg para receber um novo link.</p>
    <?php elseif ($fcOkTela): ?>
      <div class="okbox">
        <div class="ic">OK</div>
        <h2>Ficha enviada com sucesso</h2>
        <p>Obrigado, <?= htmlspecialchars(ucwords(mb_strtolower((string)$fcCand['nome']))) ?>! Recebemos os seus dados e o RH vai dar andamento à sua admissão.</p>
        <p>Não esqueça de separar os documentos da admissão (atestado médico admissional, certidões e documentos dos dependentes). Se precisar corrigir algo, é só abrir este mesmo link de novo.</p>
      </div>
    <?php else: ?>
      <h1>Ficha de Registro</h1>
      <p class="sub">Parabéns pela aprovação, <?= htmlspecialchars(ucwords(mb_strtolower((string)$fcCand['nome']))) ?>!<br>Preencha os dados abaixo para a sua admissão. Todos os campos são obrigatórios.</p>
      <?php if ($fcErro): ?><div class="err"><?= htmlspecialchars($fcErro) ?></div><?php endif; ?>
      <div class="topo-acao">
        <button type="button" class="btn-imprimir" onclick="window.print()">
          <svg viewBox="0 0 24 24" fill="currentColor" style="width:15px;height:15px"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
          Imprimir / Salvar em PDF
        </button>
      </div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="acao" value="salvar_ficha_cand">
        <input type="hidden" name="id" value="<?= $fcId ?>">
        <input type="hidden" name="t" value="<?= htmlspecialchars($fcTok) ?>">

        <h2 class="sec">Dados pessoais</h2>
        <div class="grid">
          <div class="g2"><label>Nome completo</label><input value="<?= htmlspecialchars((string)$fcCand['nome']) ?>" readonly></div>
          <div><label>Sexo</label><select name="sexo" required><option value="">Selecione…</option><?php foreach ($fcOpSexo as $o): ?><option <?= $fcVals['sexo'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
          <div><label>Data de nascimento</label><input type="date" name="nascimento" required required value="<?= htmlspecialchars($fcVals['nascimento']) ?>"></div>
          <div><label>Cidade de nascimento</label><input name="local_nascimento" required value="<?= htmlspecialchars($fcVals['local_nascimento']) ?>"></div>
          <div><label>Estado</label><input name="estado_nascimento" required maxlength="30" value="<?= htmlspecialchars($fcVals['estado_nascimento']) ?>" placeholder="Ex.: MG"></div>
          <div><label>País</label><input name="pais_nascimento" required value="<?= htmlspecialchars($fcVals['pais_nascimento'] !== '' ? $fcVals['pais_nascimento'] : 'Brasil') ?>"></div>
          <div><label>Estado civil</label><select name="estado_civil" required><option value="">Selecione…</option><?php foreach ($fcOpCivil as $o): ?><option <?= $fcVals['estado_civil'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
          <div><label>Cor / Raça</label><select name="cor_raca" required><option value="">Selecione…</option><?php foreach ($fcOpRaca as $o): ?><option <?= $fcVals['cor_raca'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
          <div class="g2"><label>Grau de instrução</label><select name="grau_instrucao" required><option value="">Selecione…</option><?php foreach ($fcOpInstr as $o): ?><option <?= $fcVals['grau_instrucao'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
          <div><label>Pessoa com deficiência</label><select name="deficiente" required><option value="">Selecione…</option><?php foreach ($fcOpSimNao as $o): ?><option <?= $fcVals['deficiente'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
          <div class="g2"><label>Se sim, descrição</label><input name="deficiencia_desc" value="<?= htmlspecialchars($fcVals['deficiencia_desc']) ?>"></div>
        </div>

        <h2 class="sec">Endereço</h2>
        <div class="grid">
          <div class="g2"><label>Residência (rua, avenida)</label><input name="residencia" required required value="<?= htmlspecialchars($fcVals['residencia']) ?>"></div>
          <div><label>Número</label><input name="numero" required value="<?= htmlspecialchars($fcVals['numero']) ?>"></div>
          <div><label>Bairro</label><input name="bairro" required value="<?= htmlspecialchars($fcVals['bairro']) ?>"></div>
          <div><label>Cidade</label><input name="cidade" required required value="<?= htmlspecialchars($fcVals['cidade']) ?>"></div>
          <div><label>Estado</label><input name="estado" required maxlength="30" value="<?= htmlspecialchars($fcVals['estado']) ?>" placeholder="Ex.: MG"></div>
          <div><label>CEP</label><input name="cep" required maxlength="12" value="<?= htmlspecialchars($fcVals['cep']) ?>" placeholder="00000-000"></div>
          <div class="g2"><label>Residência própria</label><select name="residencia_propria" required><option value="">Selecione…</option><?php foreach ($fcOpSimNao as $o): ?><option <?= $fcVals['residencia_propria'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
        </div>

        <h2 class="sec">Filiação</h2>
        <div class="grid">
          <div class="g3"><label>Nome do pai</label><input name="nome_pai" value="<?= htmlspecialchars($fcVals['nome_pai']) ?>"></div>
          <div class="g3"><label>Nome da mãe</label><input name="nome_mae" required required value="<?= htmlspecialchars($fcVals['nome_mae']) ?>"></div>
        </div>

        <h2 class="sec">Documentos</h2>
        <div class="grid">
          <div><label>CPF</label><input name="cpf" required required maxlength="20" value="<?= htmlspecialchars($fcVals['cpf']) ?>" placeholder="000.000.000-00"></div>
          <div><label>Identidade (RG)</label><input name="identidade" required maxlength="20" value="<?= htmlspecialchars($fcVals['identidade']) ?>"></div>
          <div><label>Estado do RG</label><input name="identidade_estado" required maxlength="30" value="<?= htmlspecialchars($fcVals['identidade_estado']) ?>" placeholder="Ex.: MG"></div>
          <div><label>Emissão do RG</label><input type="date" name="identidade_emissao" required value="<?= htmlspecialchars($fcVals['identidade_emissao']) ?>"></div>
          <div><label>CTPS (número)</label><input name="ctps_num" required maxlength="20" value="<?= htmlspecialchars($fcVals['ctps_num']) ?>"></div>
          <div><label>CTPS (série)</label><input name="ctps_serie" required maxlength="20" value="<?= htmlspecialchars($fcVals['ctps_serie']) ?>"></div>
          <div><label>CTPS (estado)</label><input name="ctps_estado" required maxlength="30" value="<?= htmlspecialchars($fcVals['ctps_estado']) ?>"></div>
          <div><label>CTPS (emissão)</label><input type="date" name="ctps_emissao" required value="<?= htmlspecialchars($fcVals['ctps_emissao']) ?>"></div>
          <div><label>Nº PIS</label><input name="pis" required maxlength="20" value="<?= htmlspecialchars($fcVals['pis']) ?>"></div>
          <div><label>Título de eleitor</label><input name="titulo_eleitor" required maxlength="20" value="<?= htmlspecialchars($fcVals['titulo_eleitor']) ?>"></div>
          <div><label>Zona</label><input name="titulo_zona" required maxlength="10" value="<?= htmlspecialchars($fcVals['titulo_zona']) ?>"></div>
          <div><label>Seção</label><input name="titulo_secao" required maxlength="10" value="<?= htmlspecialchars($fcVals['titulo_secao']) ?>"></div>
          <div class="g2"><label>Documento militar (se tiver)</label><input name="doc_militar" maxlength="30" value="<?= htmlspecialchars($fcVals['doc_militar']) ?>"></div>
          <div><label>Categoria</label><input name="doc_militar_categoria" maxlength="20" value="<?= htmlspecialchars($fcVals['doc_militar_categoria']) ?>"></div>
        </div>

        <h2 class="sec">Habilitação (somente se dirigir pela empresa)</h2>
        <div class="grid">
          <div><label>Nº da CNH</label><input name="cnh_num" maxlength="20" value="<?= htmlspecialchars($fcVals['cnh_num']) ?>"></div>
          <div><label>Categoria</label><input name="cnh_categoria" maxlength="5" value="<?= htmlspecialchars($fcVals['cnh_categoria']) ?>"></div>
          <div><label>Órgão emissor</label><input name="cnh_orgao" maxlength="20" value="<?= htmlspecialchars($fcVals['cnh_orgao']) ?>"></div>
          <div><label>Primeira habilitação</label><input type="date" name="cnh_primeira" value="<?= htmlspecialchars($fcVals['cnh_primeira']) ?>"></div>
          <div><label>Vencimento</label><input type="date" name="cnh_venc" value="<?= htmlspecialchars($fcVals['cnh_venc']) ?>"></div>
        </div>

        <h2 class="sec">Contatos</h2>
        <div class="grid">
          <div><label>Celular</label><input name="celular" required required maxlength="30" value="<?= htmlspecialchars($fcVals['celular'] !== '' ? $fcVals['celular'] : (string)($fcCand['celular'] ?? '')) ?>"></div>
          <div><label>Telefone fixo</label><input name="telefone" maxlength="30" value="<?= htmlspecialchars($fcVals['telefone']) ?>"></div>
          <div><label>Contato alternativo</label><input name="contato1" maxlength="30" value="<?= htmlspecialchars($fcVals['contato1']) ?>"></div>
          <div class="g3"><label>Contato de emergência (nome)</label><input name="emergencia_nome" required value="<?= htmlspecialchars($fcVals['emergencia_nome']) ?>"></div>
          <div class="g2"><label>Parentesco / relação</label><input name="emergencia_parentesco" required maxlength="40" value="<?= htmlspecialchars($fcVals['emergencia_parentesco']) ?>" placeholder="Ex.: Mãe, cônjuge, amigo(a)"></div>
          <div><label>Telefone de emergência</label><input name="emergencia_tel" required maxlength="30" value="<?= htmlspecialchars($fcVals['emergencia_tel']) ?>"></div>
        </div>

        <h2 class="sec">Opções</h2>
        <div class="grid">
          <div><label>Optou pelo vale-transporte</label><select name="vale_transporte" required><option value="">Selecione…</option><?php foreach ($fcOpSimNao as $o): ?><option <?= $fcVals['vale_transporte'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
          <div class="g2"><label>Descontar contribuição sindical</label><select name="contribuicao_sindical" required><option value="">Selecione…</option><?php foreach ($fcOpSimNao as $o): ?><option <?= $fcVals['contribuicao_sindical'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
        </div>

        <h2 class="sec">Filhos, dependentes e cônjuge</h2>
        <?php for ($iD = 1; $iD <= 4; $iD++): ?>
          <div class="grid" style="margin-bottom:10px">
            <div><label>Nome<?= $iD > 1 ? '' : ' do dependente' ?></label><input name="dep<?= $iD ?>_nome" value="<?= htmlspecialchars($fcVals['dep' . $iD . '_nome']) ?>"></div>
            <div><label>Data de nascimento</label><input type="date" name="dep<?= $iD ?>_nascimento" value="<?= htmlspecialchars($fcVals['dep' . $iD . '_nascimento']) ?>"></div>
            <div><label>CPF</label><input name="dep<?= $iD ?>_cpf" maxlength="20" value="<?= htmlspecialchars($fcVals['dep' . $iD . '_cpf']) ?>"></div>
          </div>
        <?php endfor; ?>

        <h2 class="sec">Documentos da admissão</h2>
        <div class="docs" style="margin-top:0"><b>Anexe um arquivo para cada documento</b> (PDF ou foto, até 10 MB cada). Sem os documentos que se aplicam a você não é possível concluir o registro. O arquivo é renomeado automaticamente com o seu nome, o documento e a data.</div>
        <?php
          $fcDocsPorTipo = [];
          try {
              $stD = $pdo->prepare("SELECT tipo, nome_original, tamanho, enviado_em FROM candidato_docs WHERE candidato_id=? ORDER BY id");
              $stD->execute([$fcId]);
              foreach ($stD as $dL) { $fcDocsPorTipo[(string)$dL['tipo']][] = $dL; }
          } catch (Throwable $e) { $fcDocsPorTipo = []; }
        ?>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:14px">
          <?php foreach ($fcTiposDocs as $tpChave => $tpRotulo): $jaTem = $fcDocsPorTipo[$tpRotulo] ?? []; ?>
            <div style="border:1px solid #e6eaf2;border-radius:10px;padding:12px 14px;background:#fbfcfe">
              <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
                <label style="margin:0;font-size:12.5px"><?= htmlspecialchars($tpRotulo) ?></label>
                <?php if ($jaTem): ?><span style="background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0;border-radius:99px;font-size:11px;font-weight:700;padding:3px 10px">Enviado</span><?php endif; ?>
              </div>
              <?php foreach ($jaTem as $dL): ?>
                <div style="font-size:12px;color:#0F766E;margin-top:4px"><?= htmlspecialchars((string)$dL['nome_original']) ?> (<?= number_format((int)$dL['tamanho'] / 1024, 0, ',', '.') ?> KB)</div>
              <?php endforeach; ?>
              <input type="file" name="doc_<?= $tpChave ?>" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic" style="margin-top:8px;padding:8px;background:#fff;font-size:13px">
            </div>
          <?php endforeach; ?>
        </div>

        <button type="submit">Enviar ficha de registro</button>
      </form>
    <?php endif; ?>
  </div>
</body></html>
