// ═══════════════════════════════════════════════════════════════
// ATAS_VISITAS.JS — Atas de Visita de Relacionamento.
// Depende de assets/common.js
// ═══════════════════════════════════════════════════════════════

window.IS_ADMIN        = (typeof IS_ADMIN  !== 'undefined') ? IS_ADMIN  : true;
window.IS_VISUALIZADOR = (typeof IS_VISUALIZADOR !== 'undefined') ? IS_VISUALIZADOR : false;
window.CAN_VIEW_ALL    = (typeof CAN_VIEW_ALL !== 'undefined') ? CAN_VIEW_ALL : window.IS_ADMIN;
window.USER_NOME       = (typeof USER_NOME !== 'undefined') ? USER_NOME : '';

// Fallbacks defensivos
if (typeof window.esc !== 'function')      window.esc      = s => { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; };
if (typeof window.escAttr !== 'function')  window.escAttr  = s => window.esc(s).replace(/"/g,'&quot;');
if (typeof window.toast !== 'function')    window.toast    = m => alert(m);
if (typeof window.fmtDate !== 'function')  window.fmtDate  = s => { if(!s) return '—'; const m=String(s).match(/^(\d{4})-(\d{2})-(\d{2})/); return m?`${m[3]}/${m[2]}/${m[1]}`:String(s); };
if (typeof window.openModal !== 'function')  window.openModal  = id => { const el=document.getElementById(id); if(el) el.classList.add('show'); };
if (typeof window.closeModal !== 'function') window.closeModal = id => { const el=document.getElementById(id); if(el) el.classList.remove('show'); };
if (typeof window.apiGet !== 'function')  window.apiGet  = async (a,p={}) => (await fetch('api.php?'+new URLSearchParams({action:a,...p}))).json();
if (typeof window.apiPost !== 'function') window.apiPost = async (a,b={}) => (await fetch('api.php?action='+encodeURIComponent(a),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)})).json();

let ataData = [];
let _ataSeguradoras = [];
let _ataEmpresas    = [];
let _ataAnalistas   = [];

// ── INIT ───────────────────────────────────────────────────────
async function ataInit(){
  try{
    const [seg, emp, ana] = await Promise.all([
      apiGet('seguradoras'),
      apiGet('empresas', {somente_clientes:1}),
      apiGet('analistas')
    ]);
    _ataSeguradoras = (seg.data || []);
    _ataEmpresas    = (emp.data || []);
    _ataAnalistas   = (ana.data || []);
  }catch(e){ console.warn('Falha ao carregar caches:', e); }

  await ataLoad();
  await ataLoadStats();
}

function ataVal(id){ const el=document.getElementById(id); return el ? (el.value||'') : ''; }
function setVal(id,v){ const el=document.getElementById(id); if(el) el.value=(v==null?'':v); }
function setSelectVal(id,v){ const el=document.getElementById(id); if(!el) return; el.value=v||''; }
function fillSel(id, placeholder, items){
  const el = document.getElementById(id); if (!el) return;
  el.innerHTML = `<option value="">${placeholder}</option>` +
    items.map(s => `<option value="${escAttr(s)}">${esc(s)}</option>`).join('');
}

// ── LISTAGEM ───────────────────────────────────────────────────
async function ataLoad(){
  const params = {
    search:    ataVal('af-search'),
    formato:   ataVal('af-formato'),
    percepcao: ataVal('af-percepcao'),
    status:    ataVal('af-status'),
    data_de:   ataVal('af-de'),
    data_ate:  ataVal('af-ate'),
  };
  try{
    const r = await apiGet('listar_atas', params);
    ataData = r.data || [];
  }catch(e){
    ataData = [];
    toast('Erro ao carregar atas');
  }

  // filtros locais por cards (mes_atual / pendentes)
  let arr = ataData;
  if (window._ataFiltro === 'mes_atual') {
    const hoje = new Date();
    const yyyy = hoje.getFullYear();
    const mm   = String(hoje.getMonth()+1).padStart(2,'0');
    arr = arr.filter(a => (a.data_visita || '').startsWith(`${yyyy}-${mm}`));
  } else if (window._ataFiltro === 'pendentes') {
    arr = arr.filter(a => Number(a.qtd_pendentes) > 0);
  }

  ataRender(arr);
}

function ataRender(arr){
  const tbody = document.getElementById('ata-tbody');
  document.getElementById('ata-count').textContent = arr.length;

  if (!arr.length){
    tbody.innerHTML = `<tr><td colspan="12" style="text-align:center;padding:30px;color:var(--muted)">Nenhuma ata encontrada</td></tr>`;
    return;
  }

  tbody.innerHTML = arr.map(a => {
    const grupoEmp = `<strong>${esc(a.grupo||'')}</strong>${a.empresa ? `<br><span style="color:var(--muted);font-size:11px">${esc(a.empresa)}</span>` : ''}`;
    const percep   = a.percepcao ? `<span class="badge-percep ${_percepClass(a.percepcao)}">${esc(a.percepcao)}</span>` : '—';
    const stat     = `<span class="badge-status ${_statusClass(a.status)}">${esc(a.status||'Rascunho')}</span>`;
    const enc      = Number(a.qtd_encaminhamentos||0);
    const pend     = Number(a.qtd_pendentes||0);
    const encTxt   = enc ? `${enc}${pend?` <span style="color:#dc3545;font-weight:700">(${pend})</span>`:''}` : '—';
    const obj      = a.objetivo ? esc(a.objetivo.substring(0,80)) + (a.objetivo.length>80?'…':'') : '—';

    const acoes = IS_VISUALIZADOR
      ? `
        <button class="btn-acao btn-ver" onclick="ataVerCard(${a.id})" title="Visualizar"><span class="material-symbols-rounded" style="font-size:16px">visibility</span></button>
        <button class="btn-acao btn-ver" onclick="ataImprimirById(${a.id})" title="Imprimir"><span class="material-symbols-rounded" style="font-size:16px">print</span></button>
      `
      : `
        <button class="btn-acao btn-ver" onclick="ataVerCard(${a.id})" title="Visualizar"><span class="material-symbols-rounded" style="font-size:16px">visibility</span></button>
        <button class="btn-acao btn-ver" onclick="ataEdit(${a.id})" title="Editar"><span class="material-symbols-rounded" style="font-size:16px">edit</span></button>
        <button class="btn-acao btn-ver" onclick="ataImprimirById(${a.id})" title="Imprimir"><span class="material-symbols-rounded" style="font-size:16px">print</span></button>
        <button class="btn-acao btn-ver" onclick="ataEnviarCliente(${a.id})" title="Enviar pro cliente por e-mail"><span class="material-symbols-rounded" style="font-size:16px">send</span></button>
        <button class="btn-acao btn-del" onclick="ataDelete(${a.id})" title="Excluir"><span class="material-symbols-rounded" style="font-size:16px">delete</span></button>
      `;

    return `<tr>
      <td>${a.id}</td>
      <td>${fmtDate(a.data_visita)}</td>
      <td>${esc(a.horario||'—')}</td>
      <td>${esc(a.formato||'—')}</td>
      <td>${grupoEmp}</td>
      <td>${esc(a.seguradora||'—')}</td>
      <td title="${escAttr(a.objetivo||'')}">${obj}</td>
      <td>${percep}</td>
      <td style="text-align:center">${encTxt}</td>
      <td>${stat}</td>
      <td>${esc(a.responsavel||'—')}</td>
      <td style="white-space:nowrap">${acoes}</td>
    </tr>`;
  }).join('');
}

function _percepClass(p){
  if (p==='Satisfeito')   return 'bp-sat';
  if (p==='Neutro')       return 'bp-neu';
  if (p==='Insatisfeito') return 'bp-ins';
  if (p==='Preocupado com reajuste') return 'bp-pre';
  return '';
}
function _statusClass(s){
  if (s==='Rascunho')    return 'bs-rasc';
  if (s==='Finalizada')  return 'bs-fin';
  if (s==='Assinada')    return 'bs-ass';
  return '';
}

// ── STATS (cards) ──────────────────────────────────────────────
async function ataLoadStats(){
  try{
    const r = await apiGet('stats_atas');
    const d = r.data || {};
    document.getElementById('ata-tot').textContent  = d.total || 0;
    document.getElementById('ata-mes').textContent  = d.mes_atual || 0;
    document.getElementById('ata-pres').textContent = d.presencial || 0;
    document.getElementById('ata-onl').textContent  = d.online || 0;
    document.getElementById('ata-pend').textContent = d.encaminhamentos_pendentes || 0;
  }catch(e){ console.warn(e); }
}

function ataFiltroPor(tipo){
  window._ataFiltro = tipo;
  ataLoad();
}

// ═══════════════════════════════════════════════════════════════
// AUTO-SAVE DA ATA — protege contra perda acidental de digitação.
// O draft fica em localStorage e é restaurado se o usuário fechar
// a aba/modal sem salvar. Limpo ao salvar com sucesso.
// ═══════════════════════════════════════════════════════════════
const _ATA_DRAFT_KEY = 'interweg.ata.draft.v1';
const _ATA_FIELDS = ['ma-id','ma-grupo','ma-empresa','ma-seguradora',
  'ma-data','ma-horario','ma-formato','ma-status',
  'ma-part-empresa','ma-part-interweg','ma-part-operadora',
  'ma-objetivo','ma-temas','ma-percepcao','ma-obs',
  'ma-resp','ma-analista-sel','ma-data-reg'];

function _ataColetaEncs(){
  const rows = document.querySelectorAll('#ata-encs-list .enc-row');
  const out = [];
  rows.forEach(r => {
    out.push({
      descricao:    (r.querySelector('[name="desc"]')||{}).value || '',
      responsavel:  (r.querySelector('[name="resp"]')||{}).value || '',
      prazo:        (r.querySelector('[name="prazo"]')||{}).value || '',
      status:       (r.querySelector('[name="stat"]')||{}).value || '',
      vira_demanda: (r.querySelector('[name="dem"]')||{}).checked ? 1 : 0
    });
  });
  return out;
}

function _ataAutoSave(){
  try {
    const data = {};
    _ATA_FIELDS.forEach(id => {
      const el = document.getElementById(id);
      if (el) data[id] = el.value || '';
    });
    data._encs = _ataColetaEncs();
    data._savedAt = Date.now();
    localStorage.setItem(_ATA_DRAFT_KEY, JSON.stringify(data));
    const info = document.getElementById('ma-autosave-info');
    if (info) {
      const t = new Date().toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'});
      info.textContent = `✓ Rascunho salvo às ${t}`;
    }
  } catch(e) { /* ignora quota cheia, modo privado, etc. */ }
}

function _ataGetDraft(){
  try { return JSON.parse(localStorage.getItem(_ATA_DRAFT_KEY) || 'null'); }
  catch(e) { return null; }
}

function _ataClearDraft(){
  try { localStorage.removeItem(_ATA_DRAFT_KEY); } catch(e){}
}

function _ataRestoreDraft(draft){
  if (!draft) return;
  Object.keys(draft).forEach(id => {
    if (id.startsWith('_')) return;
    const el = document.getElementById(id);
    if (el) el.value = draft[id] || '';
  });
  document.getElementById('ata-encs-list').innerHTML = '';
  (draft._encs || []).forEach(e => ataAddEnc(e));
  if (!(draft._encs && draft._encs.length)) ataAddEnc();
  const v = draft['ma-percepcao'];
  if (v) document.querySelectorAll('.percep-opt').forEach(el => {
    if (el.dataset.v === v) el.classList.add('sel');
  });
  if (draft['ma-grupo']) ataUpdateEmpresas();
  setTimeout(() => { if (draft['ma-empresa']) setVal('ma-empresa', draft['ma-empresa']); }, 50);
}

function _ataInstallAutoSave(){
  const modal = document.getElementById('mod-ata');
  if (!modal) return;
  modal.addEventListener('input', _ataAutoSave);
  modal.addEventListener('change', _ataAutoSave);
  window.addEventListener('beforeunload', _ataAutoSave);
}
document.addEventListener('DOMContentLoaded', _ataInstallAutoSave);

// ═══════════════════════════════════════════════════════════════
// EXPANDIR — abre o textarea em tela cheia (overlay) pra digitar
// com mais espaço. As mudanças sincronizam de volta no campo original
// em tempo real, então fechar não perde nada.
// ═══════════════════════════════════════════════════════════════
let _expBoundField = null;
let _expSyncHandler = null;

function ataExpandir(fieldId, label){
  const field = document.getElementById(fieldId);
  const expArea = document.getElementById('exp-textarea');
  const expTitle = document.getElementById('exp-title');
  if (!field || !expArea || !expTitle) return;
  expTitle.textContent = label || 'Expandir campo';
  expArea.value = field.value || '';
  _expBoundField = field;
  // Sincronia bidirecional em tempo real
  _expSyncHandler = function(){
    if (_expBoundField) _expBoundField.value = expArea.value;
    // dispara input no original pra triggar auto-save
    if (_expBoundField) _expBoundField.dispatchEvent(new Event('input', {bubbles:true}));
  };
  expArea.addEventListener('input', _expSyncHandler);
  document.getElementById('mod-expand').classList.add('show');
  // foca e leva o cursor pro final
  setTimeout(()=>{ expArea.focus(); expArea.setSelectionRange(expArea.value.length, expArea.value.length); }, 50);
  // ESC fecha
  document.addEventListener('keydown', _ataExpandEsc);
}

function _ataExpandEsc(e){
  if (e.key === 'Escape') ataFecharExpand();
}

function ataFecharExpand(){
  const expArea = document.getElementById('exp-textarea');
  if (_expBoundField && expArea) {
    _expBoundField.value = expArea.value;
    _expBoundField.dispatchEvent(new Event('input', {bubbles:true}));
  }
  if (_expSyncHandler && expArea) expArea.removeEventListener('input', _expSyncHandler);
  document.removeEventListener('keydown', _ataExpandEsc);
  _expBoundField = null;
  _expSyncHandler = null;
  document.getElementById('mod-expand').classList.remove('show');
}

// Minimiza o modal mantendo o conteúdo (sem confirmação, sem limpar).
function ataMinimizar(){
  _ataAutoSave();
  closeModal('mod-ata');
  if (typeof toast === 'function') toast('Ata minimizada — clique em Nova Ata para retomar de onde parou');
}

// Cancelar — pergunta se quer descartar o rascunho preenchido.
function ataCancelar(){
  const draft = _ataGetDraft();
  const temConteudo = draft && (draft['ma-objetivo'] || draft['ma-temas'] ||
    draft['ma-obs'] || draft['ma-data'] || draft['ma-empresa'] ||
    (draft._encs && draft._encs.some(e => e.descricao)));
  if (temConteudo) {
    if (!confirm('Tem certeza que deseja descartar o rascunho?\n\nDica: clique em Minimizar pra fechar mantendo o conteúdo.')) return;
    _ataClearDraft();
  }
  closeModal('mod-ata');
}

// ── MODAL ──────────────────────────────────────────────────────
// Parâmetro `skipDraftCheck` evita o prompt de "recuperar rascunho" quando
// estamos abrindo o modal pra EDITAR uma ata existente (ataEdit) — nesse
// caso queremos carregar os dados do banco, não do localStorage.
function ataOpenModal(skipDraftCheck){
  document.getElementById('ma-title').innerHTML = '<span style="display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%"><span><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">edit_document</span> Nova Ata de Visita</span><span style="display:flex;align-items:center;gap:8px"><span id="ma-autosave-info" style="font-size:11px;color:var(--muted);font-weight:400">Rascunho salvo automaticamente</span><button type="button" class="btn btn-outline btn-sm" onclick="ataMinimizar()" title="Minimiza essa ata mantendo os dados"><span class="material-symbols-rounded" style="font-size:16px">minimize</span></button></span></span>';
  setVal('ma-id','');
  // limpa campos
  ['ma-data','ma-horario','ma-data-reg','ma-resp','ma-objetivo','ma-temas','ma-obs',
   'ma-part-empresa','ma-part-interweg','ma-part-operadora','ma-percepcao'].forEach(id=>setVal(id,''));
  setSelectVal('ma-formato','Presencial');
  setSelectVal('ma-status','Rascunho');
  setSelectVal('ma-seguradora','');
  setSelectVal('ma-analista-sel','');
  document.querySelectorAll('.percep-opt').forEach(el=>el.classList.remove('sel'));

  // populates
  fillSel('ma-seguradora', 'Selecione...',
    _ataSeguradoras.map(s => typeof s === 'string' ? s : s.nome).filter(Boolean).sort());
  fillSel('ma-analista-sel', 'Selecione...',
    _ataAnalistas.map(a => a.nome).filter(Boolean));
  const grupos = [...new Set(_ataEmpresas.map(e => e.grupo))].filter(Boolean).sort();
  fillSel('ma-grupo', 'Selecione...', grupos);

  document.getElementById('ma-grupo').onchange = ataUpdateEmpresas;
  document.getElementById('ma-empresa').innerHTML = '<option value="">Selecione um grupo primeiro</option>';

  // padrões inteligentes
  const hoje = new Date().toISOString().slice(0,10);
  setVal('ma-data-reg', hoje);
  if (USER_NOME) setVal('ma-resp', USER_NOME);

  // encaminhamentos zera + começa com 1 linha vazia
  document.getElementById('ata-encs-list').innerHTML = '';
  ataAddEnc();

  // Verifica se tem rascunho não-finalizado de uma ata nova ─────
  // Só pergunta se NÃO estiver no fluxo de editar uma ata existente.
  if (!skipDraftCheck) {
    const draft = _ataGetDraft();
    if (draft && !draft['ma-id']) {
      const ts = draft._savedAt ? new Date(draft._savedAt).toLocaleString('pt-BR') : '';
      if (confirm(`Há um rascunho de ata não finalizado (salvo em ${ts}).\n\nDeseja recuperá-lo?`)) {
        _ataRestoreDraft(draft);
      } else {
        _ataClearDraft();
      }
    }
  }

  openModal('mod-ata');
}

function ataUpdateEmpresas(){
  const grupo = document.getElementById('ma-grupo').value;
  const empSel = document.getElementById('ma-empresa');
  const list = _ataEmpresas.filter(e => e.grupo === grupo);
  empSel.innerHTML = '<option value="">Selecione...</option>' +
    list.map(e => `<option value="${escAttr(e.nome||e.empresa||'')}">${esc(e.nome||e.empresa||'')}</option>`).join('');
}

function ataSelPercep(el){
  document.querySelectorAll('.percep-opt').forEach(x=>x.classList.remove('sel'));
  el.classList.add('sel');
  setVal('ma-percepcao', el.dataset.v);
}

// ── ENCAMINHAMENTOS (sub-tabela) ───────────────────────────────
let _encTempIdx = 0;
function ataAddEnc(data){
  data = data || {};
  const list = document.getElementById('ata-encs-list');
  const idx = ++_encTempIdx;
  const wrap = document.createElement('div');
  wrap.className = 'enc-row';
  wrap.dataset.idx = idx;
  if (data.id)         wrap.dataset.id        = data.id;
  if (data.demanda_id) wrap.dataset.demandaId = data.demanda_id;

  const statusSel = ['Pendente','Em andamento','Concluído','Cancelado']
    .map(s => `<option ${data.status===s?'selected':''}>${s}</option>`).join('');

  // Dropdown de analistas Interweg (responsável interno)
  const analistasOpt = '<option value="">— Selecione —</option>' +
    (_ataAnalistas || []).map(a => {
      const n = a.nome || '';
      return `<option value="${escAttr(n)}" ${data.responsavel_interno===n?'selected':''}>${esc(n)}</option>`;
    }).join('');

  let gerarBtn;
  if (data.demanda_id) {
    gerarBtn = `<a class="enc-gerar done" href="demandas.php" target="_blank" title="Demanda #${data.demanda_id} já criada">✓ Dem. #${data.demanda_id}</a>`;
  } else if (data.id) {
    gerarBtn = `<button type="button" class="enc-gerar" onclick="ataGerarDemandaEnc(${data.id}, this)">Gerar demanda</button>`;
  } else {
    gerarBtn = `<button type="button" class="enc-gerar" disabled style="opacity:.5;cursor:not-allowed" title="Salve a ata primeiro">Salve antes</button>`;
  }

  // ID único por linha pra o botão "Expandir" poder encontrar esse textarea específico
  const descId = `enc-desc-${idx}`;
  wrap.innerHTML = `
    <div>
      <label class="enc-mini-lbl">Descrição</label>
      <textarea id="${descId}" name="desc" placeholder="Descrição do encaminhamento" rows="5">${esc(data.descricao||'')}</textarea>
      <button type="button" class="btn-expand" style="margin-top:4px" onclick="ataExpandir('${descId}','Descrição do encaminhamento')" title="Expandir em tela cheia"><span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle">open_in_full</span> Expandir</button>
    </div>
    <div class="enc-row-bottom">
      <div>
        <label class="enc-mini-lbl">Responsável (Interno / Externo)</label>
        <div class="enc-resp-stack">
          <select name="resp_int" title="Responsável da equipe Interweg">${analistasOpt}</select>
          <input type="text" name="resp" placeholder="Responsável externo (cliente/operadora)" value="${escAttr(data.responsavel||'')}"/>
        </div>
      </div>
      <div>
        <label class="enc-mini-lbl">Prazo</label>
        <input type="date" name="prazo" value="${escAttr(data.prazo||'')}"/>
      </div>
      <div>
        <label class="enc-mini-lbl">Status</label>
        <select name="status">${statusSel}</select>
      </div>
      <div>
        <label class="enc-mini-lbl">Demanda</label>
        <div class="enc-action">${gerarBtn}</div>
      </div>
      <button type="button" class="enc-rm" onclick="this.closest('.enc-row').remove()" title="Remover encaminhamento"><span class="material-symbols-rounded" style="font-size:16px">close</span></button>
    </div>
  `;
  list.appendChild(wrap);
}

function ataColetarEncs(){
  return [...document.querySelectorAll('#ata-encs-list .enc-row')].map(row => {
    const respInt = row.querySelector('[name=resp_int]');
    const r = {
      descricao:           row.querySelector('[name=desc]').value.trim(),
      responsavel:         row.querySelector('[name=resp]').value.trim(),
      responsavel_interno: respInt ? respInt.value.trim() : '',
      prazo:               row.querySelector('[name=prazo]').value || null,
      status:              row.querySelector('[name=status]').value,
    };
    if (row.dataset.id)        r.id         = parseInt(row.dataset.id,10);
    if (row.dataset.demandaId) r.demanda_id = parseInt(row.dataset.demandaId,10);
    return r;
  }).filter(r => r.descricao);
}

async function ataGerarDemandaEnc(encId, btn){
  if (!confirm('Gerar uma demanda do módulo Demandas a partir desse encaminhamento?')) return;
  btn.disabled = true; btn.textContent = '…';
  try{
    const r = await apiPost('enc_gerar_demanda', { enc_id: encId });
    if (!r.ok) { toast(r.msg || 'Erro'); btn.disabled=false; btn.textContent='Gerar demanda'; return; }
    toast(`Demanda #${r.demanda_id} criada com sucesso`);
    btn.outerHTML = `<a class="enc-gerar done" href="demandas.php" target="_blank">✓ Dem. #${r.demanda_id}</a>`;
    // atualiza status local da row pra "Em andamento"
    const row = btn.closest('.enc-row');
    if (row) {
      row.dataset.demandaId = r.demanda_id;
      const stSel = row.querySelector('[name=status]');
      if (stSel) stSel.value = 'Em andamento';
    }
  }catch(e){ toast('Erro ao gerar demanda'); btn.disabled=false; btn.textContent='Gerar demanda'; }
}

// ── EDITAR ─────────────────────────────────────────────────────
async function ataEdit(id){
  // garante caches carregados
  if (!_ataEmpresas.length) await ataInit();

  try{
    const r = await apiGet('buscar_ata', { id });
    if (!r.ok || !r.data) { toast(r.msg || 'Ata não encontrada'); return; }
    const a = r.data;

    // Passa true pra NÃO mostrar prompt de "recuperar rascunho" — aqui
    // estamos carregando uma ata existente, não criando nova.
    ataOpenModal(true);
    const title = IS_VISUALIZADOR ? `Visualizar Ata #${id}` : `Editar Ata #${id}`;
    // Mantém o botão de minimizar e indicador de auto-save mesmo na edição
    document.getElementById('ma-title').innerHTML = `<span style="display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%"><span><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">edit_document</span> ${esc(title)}</span><span style="display:flex;align-items:center;gap:8px"><span id="ma-autosave-info" style="font-size:11px;color:var(--muted);font-weight:400">Rascunho salvo automaticamente</span><button type="button" class="btn btn-outline btn-sm" onclick="ataMinimizar()" title="Minimiza essa ata mantendo os dados"><span class="material-symbols-rounded" style="font-size:16px">minimize</span></button></span></span>`;
    setVal('ma-id', id);

    setSelectVal('ma-grupo',      a.grupo);
    ataUpdateEmpresas();
    setSelectVal('ma-empresa',    a.empresa);
    setSelectVal('ma-seguradora', a.seguradora);

    setVal('ma-data',     a.data_visita);
    setVal('ma-horario',  a.horario);
    setSelectVal('ma-formato', a.formato);
    setSelectVal('ma-status',  a.status);

    setVal('ma-part-empresa',   a.participantes_empresa);
    setVal('ma-part-interweg',  a.participantes_interweg);
    setVal('ma-part-operadora', a.participantes_operadora);

    setVal('ma-objetivo', a.objetivo);
    setVal('ma-temas',    a.temas_abordados);
    setVal('ma-obs',      a.observacoes);

    setVal('ma-percepcao', a.percepcao || '');
    document.querySelectorAll('.percep-opt').forEach(el => {
      if (el.dataset.v === a.percepcao) el.classList.add('sel');
    });

    setVal('ma-resp',     a.responsavel);
    setSelectVal('ma-analista-sel', a.analista_nome);
    setVal('ma-data-reg', a.data_registro);

    // Encaminhamentos
    document.getElementById('ata-encs-list').innerHTML = '';
    (a.encaminhamentos || []).forEach(e => ataAddEnc(e));
    if (!(a.encaminhamentos||[]).length) ataAddEnc();

  }catch(e){ toast('Erro ao carregar ata'); }
}

// ── SALVAR ─────────────────────────────────────────────────────
async function ataSave(btn){
  const id = parseInt(ataVal('ma-id'),10) || 0;
  const data = {
    grupo:      ataVal('ma-grupo'),
    empresa:    ataVal('ma-empresa'),
    seguradora: ataVal('ma-seguradora'),
    data_visita: ataVal('ma-data') || null,
    horario:    ataVal('ma-horario'),
    formato:    ataVal('ma-formato'),
    participantes_empresa:   ataVal('ma-part-empresa'),
    participantes_interweg:  ataVal('ma-part-interweg'),
    participantes_operadora: ataVal('ma-part-operadora'),
    objetivo:        ataVal('ma-objetivo'),
    temas_abordados: ataVal('ma-temas'),
    observacoes:     ataVal('ma-obs'),
    percepcao:       ataVal('ma-percepcao'),
    responsavel:     ataVal('ma-resp'),
    analista_nome:   ataVal('ma-analista-sel'),
    data_registro:   ataVal('ma-data-reg') || null,
    status:          ataVal('ma-status') || 'Rascunho',
    encaminhamentos: ataColetarEncs(),
  };

  // resolve analista_id pelo nome
  const ana = _ataAnalistas.find(a => a.nome === data.analista_nome);
  if (ana) data.analista_id = ana.id;

  // empresa_id (resolve pelo nome + grupo)
  const emp = _ataEmpresas.find(e => e.grupo === data.grupo && (e.nome === data.empresa || e.empresa === data.empresa));
  if (emp) data.empresa_id = emp.id;

  // Validação mínima
  if (!data.grupo)       return toast('Selecione um grupo');
  if (!data.empresa)     return toast('Selecione uma empresa');
  if (!data.data_visita) return toast('Informe a data da visita');
  if (!data.responsavel) return toast('Informe o responsável pela visita');

  btn.disabled = true;
  try{
    const action = id ? 'editar_ata' : 'criar_ata';
    if (id) data.id = id;
    const r = await apiPost(action, data);
    if (!r.ok) { toast(r.msg || 'Erro ao salvar'); btn.disabled=false; return; }
    // Salvou com sucesso — limpa o rascunho do localStorage
    _ataClearDraft();
    toast(id ? 'Ata atualizada' : `Ata #${r.id} criada`);
    closeModal('mod-ata');
    await ataLoad();
    await ataLoadStats();
  }catch(e){ toast('Erro ao salvar'); }
  finally{ btn.disabled = false; }
}

// ── ENVIAR ATA PRO CLIENTE (Caminho 1: e-mail direto) ──────────
window.ataEnviarCliente = function(id){
  const a = ataData.find(x => x.id == id);
  if (!a){ toast('Ata não encontrada'); return; }
  // Prefill do e-mail: responsável cadastrado na empresa (editável).
  let email = '';
  const emp = _ataEmpresas.find(e =>
      (a.empresa_id && e.id == a.empresa_id) ||
      (e.grupo === a.grupo && (e.nome === a.empresa || e.empresa === a.empresa)));
  if (emp) email = (emp.resp_email || '').trim();
  const _d = d => { if(!d) return ''; const p=String(d).slice(0,10).split('-'); return p.length===3?`${p[2]}/${p[1]}/${p[0]}`:d; };
  document.getElementById('atae-id').value = id;
  document.getElementById('atae-email').value = email;
  const sub = document.getElementById('atae-sub');
  if (sub) sub.textContent = 'Ata de ' + (a.empresa || '') + (a.data_visita ? (' — ' + _d(a.data_visita)) : '');
  const msg = document.getElementById('atae-msg'); if (msg){ msg.textContent=''; msg.style.color=''; }
  openModal('mod-ata-email');
  setTimeout(()=>{ const el=document.getElementById('atae-email'); if(el){ el.focus(); el.select(); } }, 100);
};

window.ataConfirmarEnvio = async function(btn){
  const id   = parseInt(document.getElementById('atae-id').value, 10) || 0;
  const dest = (document.getElementById('atae-email').value || '').trim();
  const msg  = document.getElementById('atae-msg');
  if (!dest){ if(msg){ msg.textContent='Informe o e-mail do cliente.'; msg.style.color='#c0392b'; } return; }
  if (btn) btn.disabled = true;
  if (msg){ msg.textContent='Gerando PDF...'; msg.style.color='var(--muted)'; }
  // Gera o PDF da ata (best-effort: se falhar, envia sem anexo)
  let pdfB64 = '';
  try { pdfB64 = await ataGerarPDFBase64(id); } catch(e){ pdfB64 = ''; }
  if (msg){ msg.textContent='Enviando...'; msg.style.color='var(--muted)'; }
  try{
    const r = await fetch('api_ata_email.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ id: id, destinatarios: dest, pdf_base64: pdfB64 || '' })
    }).then(x => x.json());
    if (r.ok){ toast(r.msg || 'Ata enviada!'); closeModal('mod-ata-email'); }
    else { if(msg){ msg.textContent = r.msg || 'Falha no envio.'; msg.style.color='#c0392b'; } }
  }catch(e){ if(msg){ msg.textContent = 'Erro de conexão: ' + (e.message||e); msg.style.color='#c0392b'; } }
  finally{ if (btn) btn.disabled = false; }
};

// ── PDF da ata (jsPDF) para anexar no e-mail ───────────────────
function ataEnsureJsPDF(){
  if (window.jspdf && window.jspdf.jsPDF && window._ataAutoTable) return Promise.resolve();
  const load = src => new Promise((res, rej) => {
    const s = document.createElement('script'); s.src = src; s.onload = res; s.onerror = rej;
    document.head.appendChild(s);
  });
  let p = Promise.resolve();
  if (!(window.jspdf && window.jspdf.jsPDF)) p = p.then(() => load('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js'));
  p = p.then(() => load('https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js'));
  return p.then(() => { window._ataAutoTable = true; });
}
function ataLoadImg(src){
  return new Promise((res, rej) => { const img = new Image(); img.crossOrigin='anonymous'; img.onload=()=>res(img); img.onerror=rej; img.src=src; });
}
async function ataGerarPDFBase64(id){
  const r = await apiGet('buscar_ata', { id });
  if (!r.ok || !r.data) return '';
  const a = r.data;
  await ataEnsureJsPDF();
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ unit:'pt', format:'a4' });
  const W = doc.internal.pageSize.getWidth();
  let y = 28;
  try { const img = await ataLoadImg('logo_cinza.png'); const w=100, h=w*img.naturalHeight/img.naturalWidth; doc.addImage(img, 'PNG', (W-w)/2, y, w, h); y += h + 10; }
  catch(_){ y = 40; }
  doc.setFillColor(18,161,154); doc.rect(40, y, W-80, 28, 'F');
  doc.setTextColor(255,255,255); doc.setFont(undefined,'bold'); doc.setFontSize(13);
  doc.text('Ata de Visita de Relacionamento', W/2, y+18, { align:'center' });
  const startY = y + 28;
  const fmtD = s => { const m=String(s||'').match(/^(\d{4})-(\d{2})-(\d{2})/); return m?`${m[3]}/${m[2]}/${m[1]}`:(s||''); };
  const empresaLbl = (a.grupo && a.grupo!==a.empresa) ? (a.grupo+' / '+a.empresa) : (a.empresa||'');
  const dataLbl = fmtD(a.data_visita) + (a.horario ? ' às '+a.horario : '');
  const body = [
    ['Empresa', empresaLbl], ['Operadora', a.seguradora||''], ['Data da visita', dataLbl],
    ['Formato', a.formato||''], ['Participantes (empresa)', a.participantes_empresa||''],
    ['Participantes (Interweg)', a.participantes_interweg||''], ['Participantes (operadora)', a.participantes_operadora||''],
    ['Objetivo', a.objetivo||''], ['Temas abordados', a.temas_abordados||''],
    ['Observações', a.observacoes||''], ['Percepção geral do cliente', a.percepcao||''],
    ['Responsável pela visita', a.responsavel||'']
  ].filter(rw => String(rw[1]).trim() !== '');
  doc.autoTable({
    startY: startY, margin:{ left:40, right:40 },
    styles:{ fontSize:9, cellPadding:5, valign:'top', lineColor:[227,231,240], lineWidth:0.5 },
    columnStyles:{ 0:{ cellWidth:150, fontStyle:'bold', textColor:[18,161,154], fillColor:[238,247,246] } },
    body: body
  });
  const encs = a.encaminhamentos || a.encs || [];
  if (Array.isArray(encs) && encs.length){
    let yy = doc.lastAutoTable.finalY + 16;
    doc.setTextColor(18,161,154); doc.setFont(undefined,'bold'); doc.setFontSize(12);
    doc.text('Encaminhamentos', 40, yy);
    doc.autoTable({
      startY: yy + 6, margin:{ left:40, right:40 },
      head:[['Descrição','Responsável']],
      headStyles:{ fillColor:[244,246,251], textColor:[43,50,62], fontStyle:'bold' },
      styles:{ fontSize:9, cellPadding:5, valign:'top' },
      body: encs.map(en => [ en.descricao||'', en.responsavel||en.responsavel_interno||'' ])
    });
  }
  return doc.output('datauristring'); // data:application/pdf;base64,....
}

// ── EXCLUIR ────────────────────────────────────────────────────
async function ataDelete(id){
  if (!confirm(`Excluir a ata #${id} (e todos os encaminhamentos vinculados)?\n\nDemandas já criadas não serão excluídas.`)) return;
  try{
    const r = await fetch(`api.php?action=excluir_ata&id=${id}`).then(x=>x.json());
    if (!r.ok) return toast(r.msg || 'Erro');
    toast('Ata excluída');
    ataLoad(); ataLoadStats();
  }catch(e){ toast('Erro ao excluir'); }
}

// ── EXPORT XLSX ────────────────────────────────────────────────
function ataExportXLSX(){
  if (!window.XLSX){ toast('Excel indisponível'); return; }
  const rows = ataData.map(a => ({
    'ID':                 a.id,
    'Data':               fmtDate(a.data_visita),
    'Horário':            a.horario || '',
    'Formato':            a.formato || '',
    'Grupo':              a.grupo || '',
    'Empresa':            a.empresa || '',
    'Operadora':          a.seguradora || '',
    'Objetivo':           a.objetivo || '',
    'Temas abordados':    a.temas_abordados || '',
    'Percepção':          a.percepcao || '',
    'Encaminhamentos':    a.qtd_encaminhamentos || 0,
    'Enc. pendentes':     a.qtd_pendentes || 0,
    'Status':             a.status || '',
    'Responsável':        a.responsavel || '',
    'Data registro':      fmtDate(a.data_registro),
    'Observações':        a.observacoes || '',
  }));
  const wb = XLSX.utils.book_new();
  const ws = XLSX.utils.json_to_sheet(rows);
  // larguras
  ws['!cols'] = [
    {wch:5},{wch:11},{wch:8},{wch:11},{wch:24},{wch:32},{wch:18},
    {wch:36},{wch:36},{wch:14},{wch:8},{wch:8},{wch:12},{wch:22},{wch:11},{wch:36}
  ];
  // header verde
  const range = XLSX.utils.decode_range(ws['!ref']);
  for (let C=range.s.c; C<=range.e.c; ++C){
    const ref = XLSX.utils.encode_cell({r:0,c:C});
    if (ws[ref]) {
      ws[ref].s = {
        font:{bold:true,color:{rgb:'FFFFFF'},sz:11},
        fill:{fgColor:{rgb:'009688'}},
        alignment:{horizontal:'center',vertical:'center',wrapText:true},
        border:{top:{style:'thin'},bottom:{style:'thin'},left:{style:'thin'},right:{style:'thin'}}
      };
    }
  }
  XLSX.utils.book_append_sheet(wb, ws, 'Atas');
  const stamp = new Date().toISOString().slice(0,10);
  XLSX.writeFile(wb, `atas_visitas_${stamp}.xlsx`);
}

// ── IMPRIMIR (versão pra coletar assinatura física) ────────────
async function ataImprimirById(id){
  try{
    const r = await apiGet('buscar_ata', { id });
    if (!r.ok || !r.data) { toast(r.msg || 'Ata não encontrada'); return; }
    ataImprimirRender(r.data, true);
  }catch(e){ toast('Erro ao gerar impressão'); }
}

// Visualizar a ata na interface, no mesmo card do e-mail (navy Interweg)
window.ataVerCard = async function(id){
  let a;
  try { const r = await apiGet('buscar_ata', { id }); if (!r.ok || !r.data){ toast(r.msg||'Ata não encontrada'); return; } a = r.data; }
  catch(e){ toast('Erro ao carregar a ata'); return; }
  const esc = s => String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  const nl  = s => esc(s).replace(/\n/g,'<br>');
  const fmtD = s => { const m=String(s||'').match(/^(\d{4})-(\d{2})-(\d{2})/); return m?`${m[3]}/${m[2]}/${m[1]}`:esc(s||''); };
  const empresaLbl = (a.grupo && a.grupo!==a.empresa) ? (esc(a.grupo)+' / '+esc(a.empresa)) : esc(a.empresa||'—');
  const dataLbl = fmtD(a.data_visita) + (a.horario ? ' às '+esc(a.horario) : '');
  const TEAL = '#12a19a';
  const row = (label,val) => (val && String(val).trim()!=='') ? `<tr><td style="padding:9px 14px;border:1px solid #e3e7f0;background:#eef7f6;font-weight:600;color:${TEAL};width:210px;vertical-align:top;font-size:13px">${label}</td><td style="padding:9px 14px;border:1px solid #e3e7f0;color:#2b323e;font-size:13px">${nl(val)}</td></tr>` : '';
  const encs = a.encaminhamentos || a.encs || [];
  let encHtml = '';
  if (Array.isArray(encs) && encs.length){
    encHtml = `<div style="margin:16px 14px 0;font-weight:700;color:${TEAL};font-size:14px">Encaminhamentos</div>
      <table style="width:calc(100% - 28px);border-collapse:collapse;margin:6px 14px 0">
      <tr style="background:#f4f6fb"><td style="padding:7px 12px;border:1px solid #e3e7f0;font-weight:600;font-size:12px">Descrição</td><td style="padding:7px 12px;border:1px solid #e3e7f0;font-weight:600;font-size:12px">Responsável</td></tr>`
      + encs.map(en=>`<tr><td style="padding:7px 12px;border:1px solid #e3e7f0;font-size:12px">${nl(en.descricao||'')}</td><td style="padding:7px 12px;border:1px solid #e3e7f0;font-size:12px">${esc(en.responsavel||en.responsavel_interno||'')}</td></tr>`).join('')
      + `</table>`;
  }
  const card = `
    <div style="text-align:center;padding:18px 20px 8px;background:#fff">
      <img src="logo_cinza.png" alt="Interweg Seguros" style="height:56px" onerror="this.onerror=null;this.src='logo.png'">
    </div>
    <div style="background:${TEAL};color:#fff;padding:12px 20px;text-align:center">
      <div style="font-size:17px;font-weight:700">Ata de Visita de Relacionamento</div>
    </div>
    <table style="width:100%;border-collapse:collapse">
      ${row('Empresa', empresaLbl)}
      ${row('Operadora', a.seguradora)}
      ${row('Data da visita', dataLbl)}
      ${row('Formato', a.formato)}
      ${row('Participantes (empresa)', a.participantes_empresa)}
      ${row('Participantes (Interweg)', a.participantes_interweg)}
      ${row('Participantes (operadora)', a.participantes_operadora)}
      ${row('Objetivo', a.objetivo)}
      ${row('Temas abordados', a.temas_abordados)}
      ${row('Observações', a.observacoes)}
      ${row('Percepção geral do cliente', a.percepcao)}
      ${row('Responsável pela visita', a.responsavel)}
    </table>${encHtml}`;
  const ov = document.createElement('div');
  ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:12000;display:flex;align-items:flex-start;justify-content:center;padding:24px;overflow:auto';
  ov.innerHTML = `<div style="background:#fff;max-width:720px;width:100%;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.35);overflow:hidden">
      <div style="max-height:78vh;overflow:auto">${card}</div>
      <div style="display:flex;justify-content:flex-end;gap:10px;padding:14px 18px;border-top:1px solid #e3e7f0;background:#fafbfe">
        <button type="button" class="btn btn-outline" data-act="print"><span class="material-symbols-rounded" style="font-size:17px">print</span> Imprimir</button>
        <button type="button" class="btn btn-primary" data-act="close">Fechar</button>
      </div>
    </div>`;
  document.body.appendChild(ov);
  const fim = () => ov.remove();
  ov.addEventListener('click', e => { if (e.target === ov) fim(); });
  document.addEventListener('keydown', function onk(e){ if(e.key==='Escape'){ fim(); document.removeEventListener('keydown', onk); } });
  ov.querySelector('[data-act="close"]').addEventListener('click', fim);
  ov.querySelector('[data-act="print"]').addEventListener('click', () => { fim(); ataImprimirById(id); });
};

// Visualizar — abre a versão imprimível SEM auto-print, pra revisar antes
async function ataVisualizarById(id){
  try{
    const r = await apiGet('buscar_ata', { id });
    if (!r.ok || !r.data) { toast(r.msg || 'Ata não encontrada'); return; }
    ataImprimirRender(r.data, false);
  }catch(e){ toast('Erro ao gerar visualização'); }
}

// Coleta dados do modal (versão "viva", sem precisar salvar)
function _ataDadosDoModal(){
  return {
    id: ataVal('ma-id') || '—',
    grupo:        ataVal('ma-grupo'),
    empresa:      ataVal('ma-empresa'),
    seguradora:   ataVal('ma-seguradora'),
    data_visita:  ataVal('ma-data'),
    horario:      ataVal('ma-horario'),
    formato:      ataVal('ma-formato'),
    participantes_empresa:   ataVal('ma-part-empresa'),
    participantes_interweg:  ataVal('ma-part-interweg'),
    participantes_operadora: ataVal('ma-part-operadora'),
    objetivo:        ataVal('ma-objetivo'),
    temas_abordados: ataVal('ma-temas'),
    observacoes:     ataVal('ma-obs'),
    percepcao:       ataVal('ma-percepcao'),
    responsavel:     ataVal('ma-resp'),
    data_registro:   ataVal('ma-data-reg'),
    encaminhamentos: ataColetarEncs(),
  };
}

function ataImprimir(){
  // Imprime versão "viva" do modal (mesmo sem salvar)
  ataImprimirRender(_ataDadosDoModal(), true);
}

function ataVisualizar(){
  // Abre versão imprimível pra revisar antes de imprimir — sem auto-print
  ataImprimirRender(_ataDadosDoModal(), false);
}

function ataImprimirRender(a, autoPrint){
  if (autoPrint === undefined) autoPrint = true;
  const lista = (txt) => (txt||'').split(/\n/).filter(s=>s.trim()).map(s=>`<li>${esc(s)}</li>`).join('') || '<li style="color:#999">—</li>';
  const enc = (a.encaminhamentos||[]).map((e,i)=>`
    <tr>
      <td style="text-align:center">${i+1}</td>
      <td>${esc(e.descricao||'')}</td>
      <td>${esc(e.responsavel||'')}</td>
      <td style="text-align:center">${fmtDate(e.prazo)}</td>
      <td>${esc(e.status||'')}</td>
    </tr>
  `).join('') || `<tr><td colspan="5" style="text-align:center;color:#999;padding:14px">Nenhum encaminhamento registrado</td></tr>`;

  const percepBox = (v) => `<span style="display:inline-block;width:14px;height:14px;border:1.5px solid #333;text-align:center;line-height:11px;font-size:11px;margin-right:4px">${a.percepcao===v?'X':'&nbsp;'}</span>${v}`;

  const html = `<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8">
<title>${autoPrint?'Ata de Visita':'Visualizar Ata'} ${esc(String(a.id||''))} — ${esc(a.empresa||'')}</title>
<style>
  /* Margens da impressão — verticais 2cm, laterais 2.8cm pra respiro de leitura */
  @page { margin: 2cm 2.8cm; }
  body{font-family:'Calibri',Arial,sans-serif;color:#222;font-size:11pt;line-height:1.45;margin:0;padding:30px 50px}
  @media print { body{padding:0} }
  h1{font-size:14pt;text-align:center;margin:0 0 8px;letter-spacing:.4px}
  h2{font-size:11pt;margin:14px 0 6px;border-bottom:1.5px solid #009688;padding-bottom:3px;color:#009688;text-transform:uppercase;letter-spacing:.5px}
  .head-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px 24px;margin-bottom:6px}
  .head-grid div{padding:2px 0}
  .head-grid strong{color:#333}
  ul{margin:4px 0 6px 18px;padding:0}
  ul li{margin:2px 0}
  table{width:100%;border-collapse:collapse;margin:6px 0;font-size:10pt}
  th{background:#009688;color:#fff;padding:6px 8px;text-align:left;font-weight:700;font-size:10pt}
  td{padding:6px 8px;border:1px solid #ddd;vertical-align:top}
  .box{border:1px solid #ddd;padding:10px;border-radius:4px;background:#fafafa;min-height:50px;white-space:pre-wrap}
  .sign{margin-top:30px;display:grid;grid-template-columns:1fr 1fr;gap:60px}
  .sign div{text-align:center;border-top:1px solid #333;padding-top:5px;font-size:10pt}
  .footer{margin-top:18px;font-size:9pt;color:#888;text-align:center;border-top:1px solid #eee;padding-top:6px}
  @media print { body{margin:0} button, .no-print {display:none} }
</style></head>
<body>

<div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;padding:8px 12px;background:#e0f2f1;border-left:4px solid #009688;border-radius:4px">
  <span style="font-size:12px;color:#00695c">${autoPrint?'Diálogo de impressão abrirá em segundos…':'<strong>Modo visualização</strong> — revise o conteúdo antes de imprimir'}</span>
  <span style="display:flex;gap:8px">
    <button onclick="window.close()" style="background:#fff;color:#555;border:1px solid #ccc;padding:7px 14px;border-radius:4px;cursor:pointer;font-size:12px">Fechar</button>
    <button onclick="window.print()" style="background:#009688;color:#fff;border:none;padding:8px 18px;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600">🖨 Imprimir</button>
  </span>
</div>

<h1>ATA DE VISITA DE RELACIONAMENTO — INTERWEG</h1>

<div class="head-grid">
  <div><strong>Empresa:</strong> ${esc(a.grupo||'')}${a.empresa?' / '+esc(a.empresa):''}</div>
  <div><strong>Operadora:</strong> ${esc(a.seguradora||'—')}</div>
  <div><strong>Data:</strong> ${fmtDate(a.data_visita)}</div>
  <div><strong>Horário:</strong> ${esc(a.horario||'—')}</div>
  <div style="grid-column:1/-1"><strong>Formato:</strong>
    <span style="margin-left:8px">${a.formato==='Presencial'?'(X)':'( )'} Presencial</span>
    <span style="margin-left:18px">${a.formato==='Online'?'(X)':'( )'} Online</span>
  </div>
</div>

<h2>Participantes</h2>
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
  <div><strong>Empresa:</strong><ul>${lista(a.participantes_empresa)}</ul></div>
  <div><strong>Interweg:</strong><ul>${lista(a.participantes_interweg)}</ul></div>
  <div><strong>Operadora:</strong><ul>${lista(a.participantes_operadora)}</ul></div>
</div>

<h2>1. Objetivo da visita</h2>
<div class="box">${esc(a.objetivo||'—')}</div>

<h2>2. Temas abordados</h2>
<div class="box">${esc(a.temas_abordados||'—')}</div>

<h2>3. Encaminhamentos e ações acordadas</h2>
<table>
  <thead><tr>
    <th style="width:30px">#</th><th>Descrição</th><th style="width:160px">Responsável</th><th style="width:90px">Prazo</th><th style="width:110px">Status</th>
  </tr></thead>
  <tbody>${enc}</tbody>
</table>

<h2>4. Percepção geral do cliente</h2>
<div style="margin:6px 0;display:flex;flex-wrap:wrap;gap:18px">
  <span>${percepBox('Satisfeito')}</span>
  <span>${percepBox('Neutro')}</span>
  <span>${percepBox('Insatisfeito')}</span>
  <span>${percepBox('Preocupado com reajuste')}</span>
</div>

<h2>5. Observações finais</h2>
<div class="box">${esc(a.observacoes||'—')}</div>

<div style="margin-top:18px"><strong>Responsável pela visita:</strong> ${esc(a.responsavel||'—')}<br>
<strong>Data do registro da ata:</strong> ${fmtDate(a.data_registro)}</div>

<div class="sign">
  <div>${esc(a.responsavel||'Responsável Interweg')}<br><span style="font-size:9pt;color:#666">Interweg Benefícios</span></div>
  <div>Representante da Empresa<br><span style="font-size:9pt;color:#666">${esc(a.empresa||'')}</span></div>
</div>

<div class="footer">Ata gerada pelo sistema Interweg Benefícios — ${new Date().toLocaleString('pt-BR')}</div>

${autoPrint ? '<script>setTimeout(()=>window.print(), 600);<\/script>' : ''}
</body></html>`;

  const w = window.open('', '_blank');
  if (!w) { toast('Bloqueador de popup ativo'); return; }
  w.document.write(html);
  w.document.close();
}

// ── BOOT ───────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', ataInit);
