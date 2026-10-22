<?php
if (!defined('AD_SISTEMA')) { http_response_code(403); exit('Acesso negado.'); }
// =====================================================================
// Menu Candidatos: candidaturas recebidas pelo formulário público,
// com filtros, edição de status e o botão Contratar (vira funcionário).
// Variáveis do index.php: $pdo, $user, ini2().
// A página tem duas abas: Candidatos (padrão) e Profiler.
// =====================================================================

// A aba Profiler fica oculta na tela (sem link visível); continua funcionando
// por endereço direto: index.php?page=candidatos&aba=profiler
$abaCand = ($_GET['aba'] ?? '') === 'profiler' ? 'profiler' : 'candidatos';
if ($abaCand === 'profiler') { include __DIR__ . '/pagina_profiler.php'; return; }

$okcMsg = [
    '1' => 'Candidato atualizado com sucesso.',
    '2' => 'Candidato excluído.',
    '3' => 'Candidato contratado e incluído em Funcionários. Complete a ficha dele no menu Funcionários.',
    '4' => 'E-mail com o link da Ficha de Registro enviado ao candidato.',
    '5' => 'Candidato aprovado. O e-mail com o link da Ficha de Registro foi enviado automaticamente.',
][(string)($_GET['okc'] ?? '')] ?? '';
$errcMsg = [
    '1' => 'Não foi possível contratar: já existe um funcionário com esse nome no cadastro.',
    '2' => 'Candidato salvo, mas o e-mail da Ficha de Registro não foi enviado: ele está sem e-mail válido no cadastro. Preencha o e-mail e use o botão Enviar ficha.',
    '3' => 'Candidato salvo, mas o servidor não conseguiu enviar o e-mail da Ficha de Registro. Tente de novo pelo botão Enviar ficha.',
][(string)($_GET['errc'] ?? '')] ?? '';

$candStatus = ['Novo', 'Em análise', 'Entrevista', 'Aprovado', 'Reprovado', 'Banco de talentos', 'Contratado'];
$candStatusCor = [
    'Novo'              => 'background:#DBEAFE;color:#1E40AF;border-color:#BFDBFE',
    'Em análise'        => 'background:#FEF3C7;color:#B45309;border-color:#FDE68A',
    'Entrevista'        => 'background:#EDE9FE;color:#6D28D9;border-color:#DDD6FE',
    'Aprovado'          => 'background:#D1FAE5;color:#065F46;border-color:#A7F3D0',
    'Reprovado'         => 'background:#FEE2E2;color:#B91C1C;border-color:#FECACA',
    'Banco de talentos' => 'background:#E5E7EB;color:#374151;border-color:#D1D5DB',
    'Contratado'        => 'background:#CCFBF1;color:#0F766E;border-color:#99F6E4',
];

// Candidatos do banco.
$candidatos = [];
if ($pdo) {
    try { $candidatos = $pdo->query("SELECT * FROM candidatos ORDER BY criado_em DESC, id DESC")->fetchAll(); } catch (Throwable $e) { $candidatos = []; }
}

// ---- Filtros ----
$candQ  = trim((string)($_GET['q'] ?? ''));
$candSt = (string)($_GET['st'] ?? '');
if (!in_array($candSt, $candStatus, true)) { $candSt = ''; }
$candCg = trim((string)($_GET['cg'] ?? ''));
$cargosDesejados = [];
foreach ($candidatos as $cT) { $cgT = trim((string)($cT['cargo_desejado'] ?? '')); if ($cgT !== '') { $cargosDesejados[$cgT] = true; } }
$cargosDesejados = array_keys($cargosDesejados); sort($cargosDesejados);

$candFiltrados = [];
foreach ($candidatos as $cF) {
    if ($candSt !== '' && (string)($cF['status'] ?? '') !== $candSt) { continue; }
    if ($candCg !== '' && trim((string)($cF['cargo_desejado'] ?? '')) !== $candCg) { continue; }
    if ($candQ !== '') {
        $hayC = mb_strtolower(($cF['nome'] ?? '') . ' ' . ($cF['email'] ?? '') . ' ' . ($cF['cargo_desejado'] ?? '') . ' ' . ($cF['cidade'] ?? '') . ' ' . ($cF['origem'] ?? ''));
        if (mb_strpos($hayC, mb_strtolower($candQ)) === false) { continue; }
    }
    $candFiltrados[] = $cF;
}

// KPIs.
$candTotal = count($candidatos); $candNovos = 0; $candProcesso = 0; $candContratados = 0;
foreach ($candidatos as $cK) {
    $stK = (string)($cK['status'] ?? '');
    if ($stK === 'Novo') { $candNovos++; }
    if (in_array($stK, ['Em análise', 'Entrevista', 'Aprovado'], true)) { $candProcesso++; }
    if ($stK === 'Contratado') { $candContratados++; }
}

// Candidato aberto para edição (?editar=ID).
$candEd = null;
$candEdId = (int)($_GET['editar'] ?? 0);
if ($candEdId > 0) { foreach ($candidatos as $cE) { if ((int)($cE['id'] ?? 0) === $candEdId) { $candEd = $cE; break; } } }

$linkCandidatura = 'https://interweg-financeiro.online/?candidatura=1';
?>
<div class="page-head">
  <h2>Candidatos</h2>
  <div class="sub">Candidaturas recebidas pelo formulário público. O candidato só vira funcionário quando você clica em Contratar</div>
</div>
<?php if ($okcMsg): ?><div class="ok-msg"><?= htmlspecialchars($okcMsg) ?></div><?php endif; ?>
<?php if ($errcMsg): ?><div class="err-msg"><?= htmlspecialchars($errcMsg) ?></div><?php endif; ?>
<?php if (!$pdo): ?><div class="err-msg">Banco de dados não configurado (config.php). Não é possível receber nem gerenciar candidaturas.</div><?php endif; ?>

<?php if ($candEd !== null): ?>
  <?php // ===================== FICHA DO CANDIDATO ===================== ?>
  <form method="post" class="card func-form" style="border-left:4px solid var(--brand);margin:16px 0 14px">
    <input type="hidden" name="acao" value="salvar_candidato">
    <input type="hidden" name="id" value="<?= (int)$candEd['id'] ?>">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
      <span style="width:38px;height:38px;border-radius:99px;background:var(--brand-soft);color:var(--brand-700);font-weight:800;font-size:16px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0"><?= htmlspecialchars(ini2((string)$candEd['nome'])) ?></span>
      <div>
        <h3 style="margin:0;font-size:16px"><?= htmlspecialchars((string)$candEd['nome']) ?></h3>
        <div class="sub" style="font-size:13px;color:var(--ink-2)">Candidatura em <?= date('d/m/Y H:i', strtotime((string)$candEd['criado_em'])) ?><?= trim((string)($candEd['origem'] ?? '')) !== '' ? ' · Origem: ' . htmlspecialchars((string)$candEd['origem']) : '' ?></div>
      </div>
    </div>
    <div class="func-grid">
      <div><label>Status</label>
        <select name="status"><?php foreach ($candStatus as $oS): ?><option value="<?= $oS ?>" <?= (string)($candEd['status'] ?? '') === $oS ? 'selected' : '' ?>><?= $oS ?></option><?php endforeach; ?></select>
      </div>
      <div><label>Cargo de interesse</label>
        <?php
          $catalogoCargos = [];
          if ($pdo) { try { $catalogoCargos = $pdo->query("SELECT nome FROM cargos ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) { $catalogoCargos = []; } }
          $cargoAtualCand = trim((string)($candEd['cargo_desejado'] ?? ''));
        ?>
        <?php if ($catalogoCargos): ?>
          <select name="cargo_desejado">
            <option value="">Selecione…</option>
            <?php foreach ($catalogoCargos as $oCg): ?><option value="<?= htmlspecialchars($oCg) ?>" <?= $cargoAtualCand === $oCg ? 'selected' : '' ?>><?= htmlspecialchars($oCg) ?></option><?php endforeach; ?>
            <?php if ($cargoAtualCand !== '' && !in_array($cargoAtualCand, $catalogoCargos, true)): ?><option value="<?= htmlspecialchars($cargoAtualCand) ?>" selected><?= htmlspecialchars($cargoAtualCand) ?></option><?php endif; ?>
          </select>
        <?php else: ?>
          <input name="cargo_desejado" maxlength="150" value="<?= htmlspecialchars($cargoAtualCand) ?>">
        <?php endif; ?>
      </div>
      <div><label>Origem (como soube da vaga)</label>
        <?php $opOrigens = ['Indicação', 'LinkedIn', 'Instagram', 'Site da Interweg', 'Portal de vagas', 'Outros']; $orAtual = trim((string)($candEd['origem'] ?? '')); ?>
        <select name="origem">
          <option value="">Selecione…</option>
          <?php foreach ($opOrigens as $oOr): ?><option value="<?= $oOr ?>" <?= $orAtual === $oOr ? 'selected' : '' ?>><?= $oOr ?></option><?php endforeach; ?>
          <?php if ($orAtual !== '' && !in_array($orAtual, $opOrigens, true)): ?><option value="<?= htmlspecialchars($orAtual) ?>" selected><?= htmlspecialchars($orAtual) ?></option><?php endif; ?>
        </select>
      </div>
      <div><label>E-mail</label><input name="email" maxlength="150" value="<?= htmlspecialchars((string)($candEd['email'] ?? '')) ?>"></div>
      <div><label>Celular</label><input name="celular" maxlength="30" value="<?= htmlspecialchars((string)($candEd['celular'] ?? '')) ?>"></div>
      <div><label>Cidade</label><input name="cidade" maxlength="80" value="<?= htmlspecialchars((string)($candEd['cidade'] ?? '')) ?>"></div>
      <div><label>Estado</label><input name="estado" maxlength="40" value="<?= htmlspecialchars((string)($candEd['estado'] ?? '')) ?>"></div>
      <div><label>Pretensão salarial</label><input name="pretensao" id="ced-pretensao" inputmode="numeric" maxlength="20" value="<?= htmlspecialchars((string)($candEd['pretensao'] ?? '')) ?>" placeholder="R$ 0,00"></div>
      <div><label>Nascimento</label><input type="date" name="nascimento" value="<?= htmlspecialchars((string)($candEd['nascimento'] ?? '')) ?>"></div>
      <div><label>Gênero</label><input name="genero" maxlength="60" value="<?= htmlspecialchars((string)($candEd['genero'] ?? '')) ?>" placeholder="Feminino, Masculino..."></div>
      <div><label>Estado civil</label><input name="estado_civil" maxlength="40" value="<?= htmlspecialchars((string)($candEd['estado_civil'] ?? '')) ?>"></div>
      <div><label>Escolaridade</label><input name="escolaridade" maxlength="40" value="<?= htmlspecialchars((string)($candEd['escolaridade'] ?? '')) ?>"></div>
      <div><label>Nacionalidade</label><input name="nacionalidade" maxlength="60" value="<?= htmlspecialchars((string)($candEd['nacionalidade'] ?? '')) ?>"></div>
      <div><label>Naturalidade</label><input name="naturalidade" maxlength="80" value="<?= htmlspecialchars((string)($candEd['naturalidade'] ?? '')) ?>"></div>
      <div class="full"><label>Endereço</label><input name="endereco" maxlength="200" value="<?= htmlspecialchars((string)($candEd['endereco'] ?? '')) ?>"></div>
      <div><label>CEP</label><input name="cep" maxlength="12" value="<?= htmlspecialchars((string)($candEd['cep'] ?? '')) ?>"></div>
      <div><label>Nome do pai</label><input name="nome_pai" maxlength="150" value="<?= htmlspecialchars((string)($candEd['nome_pai'] ?? '')) ?>"></div>
      <div><label>Nome da mãe</label><input name="nome_mae" maxlength="150" value="<?= htmlspecialchars((string)($candEd['nome_mae'] ?? '')) ?>"></div>
      <div><label>Telefone</label><input name="telefone" maxlength="30" value="<?= htmlspecialchars((string)($candEd['telefone'] ?? '')) ?>"></div>
      <div><label>Contato de emergência</label><input name="emergencia_nome" maxlength="150" value="<?= htmlspecialchars((string)($candEd['emergencia_nome'] ?? '')) ?>"></div>
      <div><label>Parentesco</label><input name="emergencia_parentesco" maxlength="40" value="<?= htmlspecialchars((string)($candEd['emergencia_parentesco'] ?? '')) ?>"></div>
      <div><label>Telefone de emergência</label><input name="emergencia_tel" maxlength="30" value="<?= htmlspecialchars((string)($candEd['emergencia_tel'] ?? '')) ?>"></div>
      <div><label>RG</label><input name="rg" maxlength="20" value="<?= htmlspecialchars((string)($candEd['rg'] ?? '')) ?>"></div>
      <div><label>Órgão expedidor</label><input name="rg_orgao" maxlength="20" value="<?= htmlspecialchars((string)($candEd['rg_orgao'] ?? '')) ?>"></div>
      <div><label>Data de expedição</label><input type="date" name="rg_expedicao" value="<?= htmlspecialchars((string)($candEd['rg_expedicao'] ?? '')) ?>"></div>
      <div><label>CPF</label><input name="cpf" maxlength="20" value="<?= htmlspecialchars((string)($candEd['cpf'] ?? '')) ?>"></div>
      <div class="full"><label>Link do currículo / LinkedIn</label><input name="curriculo_link" maxlength="300" value="<?= htmlspecialchars((string)($candEd['curriculo_link'] ?? '')) ?>">
        <?php if (trim((string)($candEd['curriculo_link'] ?? '')) !== ''): ?><div style="margin-top:6px"><a href="<?= htmlspecialchars((string)$candEd['curriculo_link']) ?>" target="_blank" rel="noopener" class="btn-mini">Abrir currículo</a></div><?php endif; ?>
      </div>
      <?php if (trim((string)($candEd['experiencia'] ?? '')) !== ''): ?>
        <div class="full"><label>Experiência informada pelo candidato</label>
          <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:9px;padding:12px 14px;font-size:13.5px;color:var(--ink);line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars((string)$candEd['experiencia']) ?></div>
        </div>
      <?php endif; ?>
      <div class="full"><label>Observações do RH</label>
        <textarea name="observacoes" rows="4" maxlength="4000" placeholder="Anotações internas: retorno da entrevista, testes, pareceres." style="width:100%;box-sizing:border-box;padding:10px 13px;border:1px solid var(--border);border-radius:9px;font-size:14px;font-family:inherit;color:var(--ink);resize:vertical"><?= htmlspecialchars((string)($candEd['observacoes'] ?? '')) ?></textarea>
      </div>
    </div>
    <?php if (!empty($candEd['lgpd_aceite_em'])): ?>
      <div class="func-hint" style="margin-top:14px"><b>LGPD:</b> consentimento registrado pelo candidato em <?= date('d/m/Y H:i', strtotime((string)$candEd['lgpd_aceite_em'])) ?>, no envio da Ficha de Cadastro (vale como assinatura eletrônica).</div>
    <?php endif; ?>
    <?php
      $fichaUrl = 'https://interweg-financeiro.online/' . ad_ficha_cand_url((int)$candEd['id']);
      $fichaJ = json_decode((string)($candEd['ficha_json'] ?? ''), true);
      $fichaJ = is_array($fichaJ) ? $fichaJ : [];
    ?>
    <div class="func-hint" style="margin-top:14px">
      <b>Ficha de Registro:</b>
      <?php if (!empty($candEd['ficha_preenchida_em'])): ?>
        preenchida pelo candidato em <?= date('d/m/Y H:i', strtotime((string)$candEd['ficha_preenchida_em'])) ?>. Ao contratar, os dados dela já entram na ficha do funcionário.
      <?php elseif (!empty($candEd['ficha_enviada_em'])): ?>
        link enviado por e-mail em <?= date('d/m/Y H:i', strtotime((string)$candEd['ficha_enviada_em'])) ?>, aguardando o candidato preencher.
      <?php else: ?>
        ainda não enviada. Ao marcar o status Aprovado, o e-mail com o link sai automaticamente; ou use o botão Enviar ficha abaixo.
      <?php endif; ?>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:9px">
        <input type="text" readonly value="<?= htmlspecialchars($fichaUrl) ?>" id="link-ficha" style="flex:1;min-width:240px;padding:8px 11px;border:1px solid var(--border);border-radius:9px;font-size:12px;color:var(--ink-2);background:#fff">
        <button type="button" class="btn-mini" onclick="var i=document.getElementById('link-ficha');i.select();if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(i.value);}else{document.execCommand('copy');}this.textContent='Copiado';var b=this;setTimeout(function(){b.textContent='Copiar link';},1600);">Copiar link</button>
      </div>
      <?php if ($fichaJ): ?>
        <details style="margin-top:10px">
          <summary style="cursor:pointer;color:var(--brand-700);font-weight:600;font-size:12.5px">Ver o que o candidato preencheu</summary>
          <div style="margin-top:8px;background:#fff;border:1px solid var(--border);border-radius:9px;padding:12px 14px;font-size:12.5px;line-height:1.8;color:var(--ink)">
            <?php
              $fichaRotulos = [
                'sexo' => 'Sexo', 'nascimento' => 'Nascimento', 'local_nascimento' => 'Cidade de nascimento', 'estado_nascimento' => 'Estado de nascimento', 'pais_nascimento' => 'País',
                'estado_civil' => 'Estado civil', 'cor_raca' => 'Cor/Raça', 'grau_instrucao' => 'Grau de instrução', 'deficiente' => 'PCD', 'deficiencia_desc' => 'Descrição da deficiência',
                'residencia' => 'Residência', 'numero' => 'Número', 'bairro' => 'Bairro', 'cidade' => 'Cidade', 'estado' => 'Estado', 'cep' => 'CEP', 'residencia_propria' => 'Residência própria',
                'nome_pai' => 'Pai', 'nome_mae' => 'Mãe',
                'cpf' => 'CPF', 'identidade' => 'RG', 'identidade_estado' => 'Estado do RG', 'identidade_emissao' => 'Emissão do RG',
                'ctps_num' => 'CTPS', 'ctps_serie' => 'CTPS série', 'ctps_estado' => 'CTPS estado', 'ctps_emissao' => 'CTPS emissão', 'pis' => 'PIS',
                'titulo_eleitor' => 'Título de eleitor', 'titulo_zona' => 'Zona', 'titulo_secao' => 'Seção', 'doc_militar' => 'Doc. militar', 'doc_militar_categoria' => 'Categoria militar',
                'cnh_num' => 'CNH', 'cnh_categoria' => 'CNH categoria', 'cnh_orgao' => 'CNH órgão', 'cnh_primeira' => 'Primeira habilitação', 'cnh_venc' => 'CNH vencimento',
                'celular' => 'Celular', 'telefone' => 'Telefone', 'contato1' => 'Contato alternativo',
                'emergencia_nome' => 'Emergência (nome)', 'emergencia_parentesco' => 'Emergência (relação)', 'emergencia_tel' => 'Emergência (telefone)',
                'vale_transporte' => 'Vale-transporte', 'contribuicao_sindical' => 'Contribuição sindical',
                'dep1_nome' => 'Dependente 1', 'dep1_nascimento' => 'Nascimento dep. 1', 'dep1_cpf' => 'CPF dep. 1',
                'dep2_nome' => 'Dependente 2', 'dep2_nascimento' => 'Nascimento dep. 2', 'dep2_cpf' => 'CPF dep. 2',
                'dep3_nome' => 'Dependente 3', 'dep3_nascimento' => 'Nascimento dep. 3', 'dep3_cpf' => 'CPF dep. 3',
                'dep4_nome' => 'Dependente 4', 'dep4_nascimento' => 'Nascimento dep. 4', 'dep4_cpf' => 'CPF dep. 4',
              ];
              foreach ($fichaRotulos as $kR => $rot) {
                  $vR = trim((string)($fichaJ[$kR] ?? ''));
                  if ($vR === '') { continue; }
                  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $vR)) { $vR = date('d/m/Y', strtotime($vR)); }
                  echo '<b>' . htmlspecialchars($rot) . ':</b> ' . htmlspecialchars($vR) . '<br>';
              }
            ?>
          </div>
        </details>
      <?php endif; ?>
    </div>
    <?php
      $docsCand = [];
      if ($pdo) { try { $stD = $pdo->prepare("SELECT * FROM candidato_docs WHERE candidato_id=? ORDER BY id"); $stD->execute([(int)$candEd['id']]); $docsCand = $stD->fetchAll(); } catch (Throwable $e) { $docsCand = []; } }
    ?>
    <?php if ($docsCand): ?>
      <div class="func-hint" style="margin-top:14px">
        <b>Documentos da admissão enviados pelo candidato (<?= count($docsCand) ?>):</b>
        <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px">
          <?php foreach ($docsCand as $dC): ?>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
              <a href="?baixardoc=<?= (int)$dC['id'] ?>" class="btn-mini">Baixar</a>
              <span style="font-size:13px;color:var(--ink)"><?= htmlspecialchars((string)$dC['nome_original']) ?></span>
              <span style="font-size:12px;color:var(--ink-2)"><?= number_format((int)$dC['tamanho'] / 1024, 0, ',', '.') ?> KB · <?= date('d/m/Y H:i', strtotime((string)$dC['enviado_em'])) ?></span>
              <form method="post" style="display:inline" data-mtitulo="Excluir o documento <?= htmlspecialchars((string)$dC['nome_original']) ?>?" data-mtexto="O arquivo é apagado do servidor de vez." data-mbotao="Excluir" data-mtipo="perigo">
                <input type="hidden" name="acao" value="excluir_doc_cand">
                <input type="hidden" name="doc_id" value="<?= (int)$dC['id'] ?>">
                <button type="submit" class="btn-mini btn-del">Excluir</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php if ((string)($candEd['status'] ?? '') !== 'Contratado'): ?>
      <div class="func-hint"><b>Contratar:</b> ao contratar, o candidato é incluído no menu Funcionários e, se a Ficha de Registro estiver preenchida, os dados dela já entram no cadastro. O registro dele aqui em Candidatos permanece como histórico do processo seletivo.</div>
    <?php endif; ?>
    <div class="func-actions">
      <a href="?page=candidatos" class="btn-ghost">Voltar</a>
      <button type="submit" class="btn-primary">Salvar candidato</button>
    </div>
  </form>
  <div style="display:flex;gap:8px;margin:0 0 14px;flex-wrap:wrap">
    <?php if ((string)($candEd['status'] ?? '') !== 'Contratado'): ?>
      <form method="post" data-mtitulo="Contratar <?= htmlspecialchars((string)$candEd['nome']) ?>?" data-mtexto="O candidato é incluído no cadastro de Funcionários<?= !empty($candEd['ficha_preenchida_em']) ? ' já com os dados da Ficha de Registro que ele preencheu' : ' com os dados básicos' ?>, e a ficha completa abre para você revisar. A candidatura fica guardada aqui como histórico do processo seletivo." data-mbotao="Contratar">
        <input type="hidden" name="acao" value="contratar_candidato">
        <input type="hidden" name="id" value="<?= (int)$candEd['id'] ?>">
        <button type="submit" class="btn-primary" style="padding:9px 18px;font-size:13px">Contratar candidato</button>
      </form>
      <form method="post" data-mtitulo="Enviar a Ficha de Registro por e-mail?" data-mtexto="O candidato recebe em <?= htmlspecialchars((string)($candEd['email'] ?? 'sem e-mail')) ?> um e-mail com o link seguro para preencher a própria Ficha de Registro." data-mbotao="Enviar e-mail">
        <input type="hidden" name="acao" value="enviar_ficha_cand">
        <input type="hidden" name="id" value="<?= (int)$candEd['id'] ?>">
        <button type="submit" class="btn-mini" style="padding:9px 18px;color:var(--brand-700);border-color:var(--brand)"><?= !empty($candEd['ficha_enviada_em']) ? 'Reenviar ficha por e-mail' : 'Enviar ficha por e-mail' ?></button>
      </form>
    <?php endif; ?>
    <form method="post" data-mtitulo="Excluir <?= htmlspecialchars((string)$candEd['nome']) ?>?" data-mtexto="A candidatura sai da lista de vez, junto com as observações do RH. Se preferir manter o histórico, mude o status para Reprovado ou Banco de talentos." data-mbotao="Excluir" data-mtipo="perigo">
      <input type="hidden" name="acao" value="excluir_candidato">
      <input type="hidden" name="id" value="<?= (int)$candEd['id'] ?>">
      <button type="submit" class="btn-mini btn-del" style="padding:9px 18px">Excluir candidato</button>
    </form>
  </div>
<?php else: ?>

  <?php // ===================== LINK PÚBLICO ===================== ?>
  <div class="card" style="margin:16px 0 14px;padding:16px 18px">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:space-between">
      <div>
        <h3 style="margin:0 0 3px;font-size:15px">Formulário público de candidatura</h3>
        <div class="sub" style="font-size:12.5px;color:var(--ink-2)">Divulgue este link nas vagas (site, LinkedIn, WhatsApp). Quem preencher cai aqui como candidato, sem acesso ao sistema.</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <input type="text" readonly value="<?= htmlspecialchars($linkCandidatura) ?>" id="link-cand" style="width:320px;max-width:70vw;padding:9px 12px;border:1px solid var(--border);border-radius:9px;font-size:12.5px;color:var(--ink-2);background:var(--surface-2)">
        <button type="button" class="btn-mini" onclick="var i=document.getElementById('link-cand');i.select();if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(i.value);}else{document.execCommand('copy');}this.textContent='Copiado';var b=this;setTimeout(function(){b.textContent='Copiar link';},1600);">Copiar link</button>
        <a href="<?= htmlspecialchars($linkCandidatura) ?>" target="_blank" rel="noopener" class="btn-mini">Abrir</a>
      </div>
    </div>
  </div>

  <?php // ===================== KPIS E FILTROS ===================== ?>
  <div class="kpis">
    <div class="kpi"><h3>Candidaturas</h3><div class="val"><?= $candTotal ?></div><div class="d">recebidas no total</div></div>
    <div class="kpi"><h3>Novos</h3><div class="val" style="color:#1E40AF"><?= $candNovos ?></div><div class="d">aguardando análise</div></div>
    <div class="kpi"><h3>Em processo</h3><div class="val" style="color:#B45309"><?= $candProcesso ?></div><div class="d">análise, entrevista ou aprovados</div></div>
    <div class="kpi"><h3>Contratados</h3><div class="val" style="color:var(--brand-700)"><?= $candContratados ?></div><div class="d">viraram funcionários</div></div>
  </div>

  <div class="card">
    <div class="toolbar">
      <?php
        $fltCampo  = 'box-sizing:border-box;padding:9px 12px 9px 34px;border:1px solid var(--border);border-radius:9px;font-size:13px;font-family:inherit;color:var(--ink);background:#fff;height:37px';
        $fltIcone  = 'position:absolute;left:11px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--ink-2);pointer-events:none';
      ?>
      <form method="get" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <input type="hidden" name="page" value="candidatos">
        <div style="position:relative">
          <svg viewBox="0 0 24 24" fill="currentColor" style="<?= $fltIcone ?>"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
          <input type="search" name="q" value="<?= htmlspecialchars($candQ) ?>" placeholder="Buscar por nome, e-mail, cargo ou cidade…" style="<?= $fltCampo ?>;min-width:260px">
        </div>
        <div style="position:relative">
          <svg viewBox="0 0 24 24" fill="currentColor" style="<?= $fltIcone ?>"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
          <select name="st" onchange="this.form.submit()" style="<?= $fltCampo ?>;padding-right:28px;appearance:auto;cursor:pointer">
            <option value="">Todos os status</option>
            <?php foreach ($candStatus as $oS): ?><option value="<?= $oS ?>" <?= $candSt === $oS ? 'selected' : '' ?>><?= $oS ?></option><?php endforeach; ?>
          </select>
        </div>
        <div style="position:relative">
          <svg viewBox="0 0 24 24" fill="currentColor" style="<?= $fltIcone ?>"><path d="M20 6h-4V4c0-1.1-.9-2-2-2h-4c-1.1 0-2 .9-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg>
          <select name="cg" onchange="this.form.submit()" style="<?= $fltCampo ?>;padding-right:28px;appearance:auto;cursor:pointer">
            <option value="">Todos os cargos</option>
            <?php foreach ($cargosDesejados as $oC): ?><option value="<?= htmlspecialchars($oC) ?>" <?= $candCg === $oC ? 'selected' : '' ?>><?= htmlspecialchars($oC) ?></option><?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn-primary" style="display:inline-flex;align-items:center;gap:7px;padding:0 16px;height:37px;font-size:13px;border-radius:9px">
          <svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
          Buscar
        </button>
        <?php if ($candQ !== '' || $candSt !== '' || $candCg !== ''): ?>
          <a href="?page=candidatos" class="btn-mini" style="display:inline-flex;align-items:center;gap:6px;height:37px;box-sizing:border-box;padding:0 14px;border-radius:9px">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:13px;height:13px"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            Limpar filtros
          </a>
        <?php endif; ?>
      </form>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Nome</th><th>Contato</th><th>Cidade</th><th>Cargo de interesse</th><th>Pretensão</th><th>Origem</th><th>Recebida em</th><th>Status</th><th>Profiler</th><th style="text-align:right">Ações</th></tr></thead>
        <tbody>
          <?php foreach ($candFiltrados as $cL): $stL = (string)($cL['status'] ?? 'Novo'); ?>
            <tr>
              <td><div class="nm"><span class="av"><?= htmlspecialchars(ini2((string)$cL['nome'])) ?></span><b><?= htmlspecialchars((string)$cL['nome']) ?></b></div></td>
              <td style="color:var(--ink-2);font-size:12.5px"><?= htmlspecialchars((string)($cL['email'] ?? '') ?: '-') ?><br><?= htmlspecialchars((string)($cL['celular'] ?? '') ?: '-') ?></td>
              <td><?= htmlspecialchars(trim(((string)($cL['cidade'] ?? '')) . (trim((string)($cL['estado'] ?? '')) !== '' ? ' / ' . (string)$cL['estado'] : '')) ?: '-') ?></td>
              <td><?= htmlspecialchars((string)($cL['cargo_desejado'] ?? '') ?: '-') ?></td>
              <td><?= htmlspecialchars((string)($cL['pretensao'] ?? '') ?: '-') ?></td>
              <td><?= htmlspecialchars((string)($cL['origem'] ?? '') ?: '-') ?></td>
              <td><?= date('d/m/Y', strtotime((string)$cL['criado_em'])) ?></td>
              <td>
                <span class="chip" style="<?= $candStatusCor[$stL] ?? '' ?>"><?= htmlspecialchars($stL) ?></span>
                <?php if (!empty($cL['ficha_preenchida_em'])): ?>
                  <br><span class="chip" style="background:#D1FAE5;color:#065F46;border-color:#A7F3D0;margin-top:5px;display:inline-block">Ficha de Registro preenchida</span>
                <?php elseif (!empty($cL['ficha_enviada_em'])): ?>
                  <br><span class="chip" style="background:#FEF3C7;color:#B45309;border-color:#FDE68A;margin-top:5px;display:inline-block">Ficha de Registro aguardando</span>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap">
                <button type="button" class="btn-mini" onclick="adCopiarProfiler(this)" title="Copia o link do teste comportamental para enviar ao candidato">Copiar link</button>
              </td>
              <td style="text-align:right;white-space:nowrap">
                <a href="?page=candidatos&editar=<?= (int)$cL['id'] ?>" class="btn-mini">Abrir</a>
                <?php if ($stL !== 'Contratado'): ?>
                  <form method="post" style="display:inline;margin-left:6px" data-mtitulo="Contratar <?= htmlspecialchars((string)$cL['nome']) ?>?" data-mtexto="O candidato é incluído no cadastro de Funcionários com os dados básicos e a ficha completa abre para você preencher. A candidatura fica guardada aqui como histórico do processo seletivo." data-mbotao="Contratar">
                    <input type="hidden" name="acao" value="contratar_candidato">
                    <input type="hidden" name="id" value="<?= (int)$cL['id'] ?>">
                    <button type="submit" class="btn-mini" style="color:var(--brand-700);border-color:var(--brand)">Contratar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$candFiltrados): ?><tr><td colspan="10" style="text-align:center;color:var(--ink-2);padding:30px"><?= $candTotal === 0 ? 'Nenhuma candidatura recebida ainda. Divulgue o link do formulário público acima.' : 'Nenhum candidato encontrado com os filtros aplicados.' ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php // O modal de confirmação agora é global (fica no index.php) e atende os forms com data-mtitulo. ?>
<script>
// Máscara de dinheiro na pretensão salarial (R$ 0,00).
(function () {
  var c = document.getElementById('ced-pretensao');
  if (!c) { return; }
  function aplica() {
    var d = c.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 9);
    if (!d) { c.value = ''; return; }
    var v = (parseInt(d, 10) / 100).toFixed(2).split('.');
    c.value = 'R$ ' + v[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + v[1];
  }
  c.addEventListener('input', aplica);
})();

// Copia o link do Profiler (teste comportamental) para enviar ao candidato.
function adCopiarProfiler(btn) {
  var url = 'https://interweg-financeiro.online/index.php?profiler=1';
  function feito() {
    var t = btn.textContent;
    btn.textContent = 'Copiado!';
    setTimeout(function () { btn.textContent = t; }, 1800);
  }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(feito, function () { fallback(); });
  } else { fallback(); }
  function fallback() {
    var i = document.createElement('input');
    i.value = url; document.body.appendChild(i); i.select();
    document.execCommand('copy'); document.body.removeChild(i); feito();
  }
}
</script>
