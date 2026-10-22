// ═══════════════════════════════════════════════════════════════
// FORMCOT.JS — Formulário de Cotação Interweg
// Form builder: campos editáveis + tabela de vidas + import/export XLSX
// Depende de assets/common.js (esc, toast, fmtBRL, brToNum, etc.)
// ═══════════════════════════════════════════════════════════════

// ── Listas reaproveitadas (LEGENDAS da planilha de cotação SAÚDE PME) ────
// Coberturas de SAÚDE (Placement não trata vida — itens de morte/invalidez/acidente removidos)
const FC_LEGENDA_COBERTURAS = [
  'DMHO – Despesas Médicas, Hospitalares e Odontológicas',
  'Doença Congênita de Filhos',
  'Outras (informar no campo Observações)'
];

// Assistências de SAÚDE (itens de funeral/morte/acidente de trabalho/rescisão removidos)
const FC_LEGENDA_ASSISTENCIAS = [
  'Despesas Médicas',
  'Despesas Médicas, Hospitalares e Odontológicas',
  'Orientação Médica por Telefone',
  'Pagamento por Diagnóstico de Câncer de Mama ou Próstata do Titular',
  'Amparo Oncológico',
  'Assistência Emergencial',
  'Assistência Fitness',
  'Assistência social, psicológica e nutricional',
  'Outras (informar no campo Observações)'
];

// Helper: cria um campo {label, valor, tipo, opcoes}
const _f = (label, opts = {}) => ({ label, valor: opts.valor || '', tipo: opts.tipo || 'text', opcoes: opts.opcoes || [] });

// Opções recorrentes de Sim/Não (com nuances específicas da planilha)
const _SIM_NAO              = ['Sim','Não'];
const _SIM_DOC_NAO          = ['Sim (envio de documento detalhado em anexo)','Não'];
const _SIM_DETALHE_OBS      = ['Sim (informar detalhe dos valores no campo Observação)','Não'];
const _SIM_AFASTADO_CID     = ['Sim (informar se afastado ou aposentado e CID na Observação)','Não'];
const _SIM_INATIVOS_NOMES   = ['Sim (enviar nomes na relação de vidas e data fim do benefício)','Não'];
const _SIM_PRESTADOR_CNPJ   = ['Sim (informar CNPJ e razão social no campo Observações)','Não'];

// ── TEMPLATES por tipo ──────────────────────────────────────────
// Cada campo é { label, valor, tipo: 'text'|'select'|'textarea', opcoes: [] }.
// O cliente (cotacao_cliente.php) já entende esse formato.
const FC_TEMPLATES = {
  // SAÚDE — PME (2 a 29 vidas) — espelha a planilha oficial Interweg
  saude_pme: {
    dados: [
      _f('Razão Social'),
      _f('CNPJ Principal'),
      _f('Subfaturas (CNPJs e razões sociais)', { tipo:'textarea' }),
      _f('Total de Vidas Assistidas'),
      _f('Possui CCT?',                                      { tipo:'select', opcoes: _SIM_NAO }),
      _f('Possui Prestadores de Serviço (PJ)?',              { tipo:'select', opcoes: _SIM_PRESTADOR_CNPJ }),
      _f('Observações',                                      { tipo:'textarea' })
    ],
    // Mantemos a seção "plano_atual" para o que o cliente paga hoje.
    plano_atual: [
      _f('Operadora Atual'),
      _f('Produto / Tipo de Plano'),
      _f('Valor Última Fatura (R$)'),
      _f('Início do Plano'),
      _f('Motivo da Cotação')
    ],
    contrato: [
      _f('Acomodação',                                       { tipo:'select', opcoes:['Enfermaria','Apartamento','Ambos'] }),
      _f('Coparticipação',                                   { tipo:'select', opcoes: _SIM_DETALHE_OBS }),
      _f('Reembolso'),
      _f('Modalidade',                                       { tipo:'select', opcoes:['Adesão','Compulsório'] }),
      _f('Grupo Segurado'),
      _f('Localidade das Vidas (Cidade/Estado)'),
      _f('Política de Benefícios',                           { tipo:'textarea' }),
      _f('Afastados / Aposentados?',                         { tipo:'select', opcoes: _SIM_AFASTADO_CID }),
      _f('Inativos?',                                        { tipo:'select', opcoes: _SIM_INATIVOS_NOMES }),
      _f('Deseja atendimento em algum hospital específico?', { tipo:'select', opcoes: _SIM_DOC_NAO }),
      _f('Observação',                                       { tipo:'textarea' })
    ],
    coberturas: [
      _f('Coberturas desejadas',  { tipo:'select', opcoes: FC_LEGENDA_COBERTURAS }),
      _f('Assistências desejadas',{ tipo:'select', opcoes: FC_LEGENDA_ASSISTENCIAS })
    ],
    vidas_cols: ['CNPJ','Operadora Atual','Produto Atual','Categoria','Acomodação','Tipo de Vínculo','Parentesco','Data de Nascimento','Idade','Faixa Etária']
  },

  // SAÚDE — Empresarial (30+ vidas) — mantém o template anterior mais expandido
  saude_emp: {
    dados: [
      _f('Razão Social'),
      _f('CNPJ Principal'),
      _f('Subfaturas (CNPJs e razões sociais)', { tipo:'textarea' }),
      _f('Total de Vidas Assistidas'),
      _f('Possui CCT?',                            { tipo:'select', opcoes: _SIM_NAO }),
      _f('Possui Prestadores de Serviço (PJ)?',    { tipo:'select', opcoes: _SIM_PRESTADOR_CNPJ }),
      _f('Filiais (qtde)'),
      _f('Estados de Atuação'),
      _f('RH Responsável'),
      _f('E-mail RH'),
      _f('Telefone'),
      _f('Observações',                            { tipo:'textarea' })
    ],
    plano_atual: [
      _f('Operadora Atual'),
      _f('Produto / Tipo de Plano'),
      _f('Valor Última Fatura (R$)'),
      _f('Início do Plano'),
      _f('Sinistralidade últimos 12m'),
      _f('Rede Credenciada Principal'),
      _f('Motivo da Cotação')
    ],
    contrato: [
      _f('Acomodação',                                       { tipo:'select', opcoes:['Enfermaria','Apartamento','Ambos'] }),
      _f('Coparticipação',                                   { tipo:'select', opcoes: _SIM_DETALHE_OBS }),
      _f('Reembolso'),
      _f('Modalidade',                                       { tipo:'select', opcoes:['Adesão','Compulsório'] }),
      _f('Grupo Segurado'),
      _f('Localidade das Vidas (Cidade/Estado)'),
      _f('Forma de Pagamento'),
      _f('Vigência Pretendida'),
      _f('Reajuste'),
      _f('Carência'),
      _f('Subsídio Empresa (%)'),
      _f('Subsídio Funcionário (%)'),
      _f('Política de Benefícios',                           { tipo:'textarea' }),
      _f('Afastados / Aposentados?',                         { tipo:'select', opcoes: _SIM_AFASTADO_CID }),
      _f('Inativos?',                                        { tipo:'select', opcoes: _SIM_INATIVOS_NOMES }),
      _f('Deseja atendimento em algum hospital específico?', { tipo:'select', opcoes: _SIM_DOC_NAO }),
      _f('Observação',                                       { tipo:'textarea' })
    ],
    coberturas: [
      _f('Coberturas desejadas',  { tipo:'select', opcoes: FC_LEGENDA_COBERTURAS }),
      _f('Assistências desejadas',{ tipo:'select', opcoes: FC_LEGENDA_ASSISTENCIAS })
    ],
    vidas_cols: ['CNPJ','Operadora Atual','Produto Atual','Categoria','Acomodação','Tipo de Vínculo','Parentesco','Data de Nascimento','Idade','Faixa Etária']
  }
};

// Default das colunas da Base de Vidas — alinhado ao template SAÚDE PME
const FC_VIDAS_COLS_DEFAULT = ['CNPJ','Operadora Atual','Produto Atual','Categoria','Tipo de Vínculo','Parentesco','Data de Nascimento','Idade','Faixa Etária'];

// ── Estado ──────────────────────────────────────────────────────
let _fcVidasCols = [...FC_VIDAS_COLS_DEFAULT];
let _fcVidasRows = [];

// ── INIT ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  fcSetTemplate();
  fcRenderVidas();
});

// ── TEMPLATE LOADING ────────────────────────────────────────────
function fcSetTemplate(){
  const tipo = document.getElementById('fc-tipo').value;
  const tpl = FC_TEMPLATES[tipo] || FC_TEMPLATES.saude_pme;
  ['dados','plano_atual','contrato','coberturas'].forEach(sec => {
    fcRenderSection(sec, tpl[sec] || []);
  });
  // Atualiza colunas da Base de Vidas se o template tiver definição própria
  if (Array.isArray(tpl.vidas_cols) && tpl.vidas_cols.length){
    _fcVidasCols = [...tpl.vidas_cols];
    // Mantém linhas se mesma quantidade de colunas; senão zera
    if (_fcVidasRows.length && _fcVidasRows[0].length !== _fcVidasCols.length){
      _fcVidasRows = [];
    }
    fcRenderVidas();
  }
}

function fcLimpar(){
  if (!confirm('Limpar todos os campos?')) return;
  fcSetTemplate();
  _fcVidasRows = [];
  fcRenderVidas();
  toast('Formulário limpo');
}

// Normaliza um campo para sempre ter {label, valor, tipo, opcoes}.
// Aceita tanto o formato antigo ([label, valor]) quanto o novo (objeto).
function _fcNormalizeField(f){
  if (Array.isArray(f)) return { label: f[0] ?? '', valor: f[1] ?? '', tipo: 'text', opcoes: [] };
  return {
    label:  f && f.label  || '',
    valor:  f && f.valor  || '',
    tipo:   (f && f.tipo) || 'text',
    opcoes: (f && Array.isArray(f.opcoes)) ? f.opcoes : []
  };
}

function fcRenderSection(section, fields){
  const cont = document.getElementById('fc-fields-' + section);
  if (!cont) return;
  cont.innerHTML = fields.map((f, idx) => {
    const n = _fcNormalizeField(f);
    return fcCampoHtml(section, idx, n.label, n.valor, n.tipo, n.opcoes);
  }).join('');
}

function fcCampoHtml(section, idx, label, value, tipo='text', opcoes=[]){
  // Renderiza o input/select/textarea conforme o tipo do campo
  let inputHtml = '';
  if (tipo === 'select' && Array.isArray(opcoes) && opcoes.length){
    const optsJson = encodeURIComponent(JSON.stringify(opcoes));
    inputHtml = `<select data-tipo="select" data-opcoes="${optsJson}">
      <option value="">Selecione...</option>
      ${opcoes.map(o => `<option value="${escAttr(o)}"${o === value ? ' selected' : ''}>${esc(o)}</option>`).join('')}
    </select>`;
  } else if (tipo === 'textarea'){
    inputHtml = `<textarea data-tipo="textarea" rows="2" placeholder="Preencha...">${esc(value || '')}</textarea>`;
  } else {
    inputHtml = `<input data-tipo="text" type="text" value="${escAttr(value || '')}" placeholder="Preencha..."/>`;
  }
  return `<div class="fc-field" data-section="${section}" data-idx="${idx}">
    <div class="fc-field-head">
      <span class="fc-field-label" contenteditable="true" spellcheck="false">${esc(label)}</span>
      <button class="fc-field-del" onclick="fcDelCampo(this)" title="Remover campo"><span class="material-symbols-rounded" style="font-size:16px">close</span></button>
    </div>
    ${inputHtml}
  </div>`;
}

function fcAddCampo(section){
  const cont = document.getElementById('fc-fields-' + section);
  if (!cont) return;
  const nextIdx = cont.querySelectorAll('.fc-field').length;
  const div = document.createElement('div');
  div.innerHTML = fcCampoHtml(section, nextIdx, 'Novo campo', '', 'text', []);
  cont.appendChild(div.firstChild);
}

function fcDelCampo(btn){
  const fld = btn.closest('.fc-field');
  if (fld) fld.remove();
}

// ── EXCLUIR / RESTAURAR SEÇÃO INTEIRA ───────────────────────────
// Usado no bloco "Plano Atual" — quando o cliente não tem plano vigente,
// o analista pode esconder o bloco inteiro. Os dados não são exportados
// enquanto a seção estiver escondida (ver fcColetarSecao / fcColetarSecaoObj).
function fcExcluirSecao(section){
  const sec = document.querySelector('.fc-section[data-section="' + section + '"]');
  const ph  = document.querySelector('.fc-section-excluida[data-section-excluida="' + section + '"]');
  if (!sec) return;
  if (sec.style.display === 'none') return; // já está excluído
  if (!confirm('Excluir o bloco "' + _secLabel(section) + '" desta cotação?')) return;
  sec.style.display = 'none';
  if (ph) ph.style.display = 'flex';
}

function fcRestaurarSecao(section){
  const sec = document.querySelector('.fc-section[data-section="' + section + '"]');
  const ph  = document.querySelector('.fc-section-excluida[data-section-excluida="' + section + '"]');
  if (sec) sec.style.display = '';
  if (ph)  ph.style.display  = 'none';
}

function _fcSecaoVisivel(section){
  const sec = document.querySelector('.fc-section[data-section="' + section + '"]');
  // Se não existe o wrapper (.fc-section), considera visível para manter
  // compat com chamadas antigas que só dependem de #fc-fields-<section>.
  if (!sec) return true;
  return sec.style.display !== 'none';
}

// ── BASE DE VIDAS ───────────────────────────────────────────────
function fcRenderVidas(){
  const head = document.getElementById('fc-vidas-head');
  const body = document.getElementById('fc-vidas-body');
  head.innerHTML = '<th style="width:40px">#</th>' +
    _fcVidasCols.map((c,i) => `<th>
      <span contenteditable="true" spellcheck="false" onblur="fcRenameCol(${i}, this.textContent)">${esc(c)}</span>
      <button class="fc-field-del" onclick="fcDelColuna(${i})" title="Remover coluna" style="position:absolute;right:4px;top:4px"><span class="material-symbols-rounded" style="font-size:14px">close</span></button>
    </th>`).join('') +
    '<th style="width:40px"></th>';

  body.innerHTML = _fcVidasRows.map((row, ri) => {
    return `<tr>
      <td style="text-align:center;color:var(--muted)">${ri+1}</td>` +
      _fcVidasCols.map((_, ci) =>
        `<td><input type="text" value="${escAttr(row[ci]||'')}" oninput="_fcVidasRows[${ri}][${ci}]=this.value"/></td>`
      ).join('') +
      `<td><button class="fc-field-del" onclick="fcDelVida(${ri})"><span class="material-symbols-rounded" style="font-size:16px">delete</span></button></td>
    </tr>`;
  }).join('') ||
    `<tr><td colspan="${_fcVidasCols.length+2}" style="text-align:center;padding:20px;color:var(--muted)">Nenhuma vida cadastrada — clique em "+ Vida" pra adicionar</td></tr>`;
}

function fcAddColuna(){
  const nome = prompt('Nome da nova coluna:');
  if (!nome) return;
  _fcVidasCols.push(nome.trim());
  _fcVidasRows.forEach(r => r.push(''));
  fcRenderVidas();
}
function fcDelColuna(i){
  if (_fcVidasCols.length <= 1) { toast('Mantenha pelo menos 1 coluna'); return; }
  _fcVidasCols.splice(i, 1);
  _fcVidasRows.forEach(r => r.splice(i, 1));
  fcRenderVidas();
}
function fcRenameCol(i, newName){
  if (newName && newName.trim()) _fcVidasCols[i] = newName.trim();
}
function fcAddVida(){
  _fcVidasRows.push(new Array(_fcVidasCols.length).fill(''));
  fcRenderVidas();
}
function fcDelVida(i){
  _fcVidasRows.splice(i, 1);
  fcRenderVidas();
}

// ── IMPORT/EXPORT XLSX ──────────────────────────────────────────
function fcExportXLSX(){
  if (typeof XLSX === 'undefined'){ toast('Biblioteca XLSX não carregada'); return; }
  const wb = XLSX.utils.book_new();

  ['dados','plano_atual','contrato','coberturas'].forEach(sec => {
    const fields = fcColetarSecao(sec);
    if (fields.length){
      const ws = XLSX.utils.aoa_to_sheet([['Campo','Valor'], ...fields]);
      XLSX.utils.book_append_sheet(wb, ws, _secLabel(sec));
    }
  });

  if (_fcVidasRows.length){
    const ws = XLSX.utils.aoa_to_sheet([_fcVidasCols, ..._fcVidasRows]);
    XLSX.utils.book_append_sheet(wb, ws, 'Base de Vidas');
  }

  const tipo = document.getElementById('fc-tipo').value;
  const fname = `cotacao_${tipo}_${new Date().toISOString().slice(0,10)}.xlsx`;
  XLSX.writeFile(wb, fname);
  toast('Exportado!');
}

function _secLabel(s){
  return ({dados:'Dados',plano_atual:'Plano Atual',contrato:'Contrato',coberturas:'Coberturas'})[s] || s;
}

// Coleta a seção como array de [label, valor] (compat com export XLSX antigo)
function fcColetarSecao(section){
  if (!_fcSecaoVisivel(section)) return []; // bloco excluído pelo usuário
  const cont = document.getElementById('fc-fields-' + section);
  if (!cont) return [];
  return Array.from(cont.querySelectorAll('.fc-field')).map(f => {
    const label = f.querySelector('.fc-field-label').textContent.trim();
    const valEl = f.querySelector('input,textarea,select');
    return [label, valEl ? valEl.value : ''];
  });
}

// Coleta a seção como array de objetos {label, valor, tipo, opcoes} —
// formato esperado por cotacao_cliente.php / api.php?action=gerar_link_cotacao.
function fcColetarSecaoObj(section){
  if (!_fcSecaoVisivel(section)) return []; // bloco excluído pelo usuário
  const cont = document.getElementById('fc-fields-' + section);
  if (!cont) return [];
  return Array.from(cont.querySelectorAll('.fc-field')).map(f => {
    const label = f.querySelector('.fc-field-label').textContent.trim();
    const valEl = f.querySelector('input,textarea,select');
    if (!valEl) return { label, valor: '', tipo: 'text', opcoes: [] };
    const tipo = valEl.getAttribute('data-tipo') || (valEl.tagName === 'SELECT' ? 'select' : valEl.tagName === 'TEXTAREA' ? 'textarea' : 'text');
    let opcoes = [];
    const optsAttr = valEl.getAttribute('data-opcoes');
    if (optsAttr){
      try { opcoes = JSON.parse(decodeURIComponent(optsAttr)); } catch(e){ opcoes = []; }
    }
    return { label, valor: valEl.value || '', tipo, opcoes };
  });
}

async function fcImportarXLSX(input){
  if (typeof XLSX === 'undefined'){ toast('Biblioteca XLSX não carregada'); return; }
  const file = input.files[0]; if (!file) return;
  const buf = await file.arrayBuffer();
  const wb = XLSX.read(buf, {type:'array'});

  const _read = name => {
    const ws = wb.Sheets[name]; if (!ws) return [];
    return XLSX.utils.sheet_to_json(ws, {header:1, defval:''});
  };

  const secs = {Dados:'dados','Plano Atual':'plano_atual',Contrato:'contrato',Coberturas:'coberturas'};
  Object.entries(secs).forEach(([sheetName, section]) => {
    const data = _read(sheetName);
    if (data.length > 1){
      // Pula header
      const fields = data.slice(1).filter(r => r[0]).map(r => [r[0], r[1] || '']);
      fcRenderSection(section, fields);
    }
  });

  const vidas = _read('Base de Vidas');
  if (vidas.length > 0){
    _fcVidasCols = vidas[0].map(c => String(c||'').trim()).filter(Boolean);
    _fcVidasRows = vidas.slice(1).map(r => _fcVidasCols.map((_,i) => String(r[i]||'')));
    fcRenderVidas();
  }

  input.value = '';
  toast('XLSX importado');
}

// ── Coleta de payload para o backend (cotacoes_envio) ───────────
// Monta o "template" no formato esperado por api.php?action=gerar_link_cotacao
// e por cotacao_cliente.php — preserva tipo (text/select/textarea) e opcoes.
function fcMontarTemplate(){
  return {
    dados:       fcColetarSecaoObj('dados'),
    plano_atual: fcColetarSecaoObj('plano_atual'),
    contrato:    fcColetarSecaoObj('contrato'),
    coberturas:  fcColetarSecaoObj('coberturas'),
    vidas_cols:  [..._fcVidasCols],
    vidas_rows:  _fcVidasRows.map(r => [...r])
  };
}

// ── VISUALIZAR COMO CLIENTE ─────────────────────────────────────
// Gera um link real e abre a página do cliente em nova aba — assim
// o que o analista vê é exatamente o que o cliente vai receber.
async function fcAbrirPreviewCliente(){
  try {
    const sel = document.getElementById('fc-tipo');
    const tipo = sel.value;
    const tipoNome = sel.selectedOptions[0]?.textContent || tipo;
    const template = fcMontarTemplate();

    toast('Gerando preview...');
    const r = await apiPost('gerar_link_cotacao', { tipo, tipo_nome: tipoNome, template });
    if (!r || !r.ok || !r.url){
      alert('Não consegui gerar o preview: ' + (r && r.msg ? r.msg : 'resposta inesperada do servidor'));
      return;
    }
    window.open(r.url, '_blank', 'noopener');
  } catch (err){
    console.error('[fcAbrirPreviewCliente] erro:', err);
    alert('Erro ao gerar preview: ' + (err.message || err));
  }
}

// ── GERAR LINK PARA O CLIENTE ───────────────────────────────────
// Cria o token + link e exibe num modal com botões pra copiar / abrir.
async function fcGerarLinkCliente(){
  try {
    const sel = document.getElementById('fc-tipo');
    const tipo = sel.value;
    const tipoNome = sel.selectedOptions[0]?.textContent || tipo;
    const template = fcMontarTemplate();

    toast('Gerando link...');
    const r = await apiPost('gerar_link_cotacao', { tipo, tipo_nome: tipoNome, template });
    if (!r || !r.ok || !r.url){
      alert('Não consegui gerar o link: ' + (r && r.msg ? r.msg : 'resposta inesperada do servidor'));
      return;
    }
    fcMostrarModalLink(r.url);
  } catch (err){
    console.error('[fcGerarLinkCliente] erro:', err);
    alert('Erro ao gerar link: ' + (err.message || err));
  }
}

// Modal simples (auto-injetado) com a URL e botões "Copiar" / "Abrir"
function fcMostrarModalLink(url){
  // Remove modal anterior se houver
  document.getElementById('fc-modal-link')?.remove();

  const ov = document.createElement('div');
  ov.id = 'fc-modal-link';
  ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px';
  ov.innerHTML = `
    <div style="background:var(--bg-card,#fff);color:var(--text,#111);border:1px solid var(--border,#d0d7de);border-radius:14px;padding:24px;width:560px;max-width:100%;box-shadow:0 12px 40px rgba(0,0,0,.4)">
      <h3 style="margin:0 0 6px;color:var(--accent,#00a589);display:flex;align-items:center;gap:8px"><span class="material-symbols-rounded">link</span> Link gerado</h3>
      <p style="margin:0 0 14px;font-size:13px;color:var(--muted,#656d76)">Envie este link para o cliente. Ele preenche e os dados voltam direto pra você.</p>
      <input id="fc-link-input" type="text" readonly value="${escAttr(url)}" style="width:100%;padding:10px 12px;border:1.5px solid var(--border,#d0d7de);border-radius:8px;font-size:13px;font-family:inherit;background:var(--bg,#f6f8fa);color:var(--text,#111);outline:none"/>
      <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;justify-content:flex-end">
        <button class="btn btn-outline" onclick="document.getElementById('fc-modal-link').remove()" style="height:36px">Fechar</button>
        <button class="btn btn-outline" onclick="window.open(document.getElementById('fc-link-input').value,'_blank','noopener')" style="border-color:#00bfb3;color:#00bfb3;height:36px"><span class="material-symbols-rounded" style="font-size:16px">open_in_new</span> Abrir</button>
        <button class="btn btn-primary" onclick="fcCopiarLink()" style="height:36px"><span class="material-symbols-rounded" style="font-size:16px">content_copy</span> Copiar</button>
      </div>
    </div>`;
  ov.addEventListener('click', e => { if (e.target === ov) ov.remove(); });
  document.body.appendChild(ov);
  document.getElementById('fc-link-input').select();
}

function fcCopiarLink(){
  const inp = document.getElementById('fc-link-input');
  if (!inp) return;
  inp.select();
  inp.setSelectionRange(0, 999);
  try {
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(inp.value).then(
        () => toast('Link copiado!'),
        () => { document.execCommand('copy'); toast('Link copiado!'); }
      );
    } else {
      document.execCommand('copy');
      toast('Link copiado!');
    }
  } catch (e){
    toast('Não consegui copiar — selecione manualmente');
  }
}
