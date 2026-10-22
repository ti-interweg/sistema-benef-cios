// ═══════════════════════════════════════════════════════════════
// DEMANDAS.JS — Página de Demandas (lista, cards, cadastro)
// Depende de assets/common.js (esc, toast, fmtBRL, apiGet, etc.)
// ═══════════════════════════════════════════════════════════════

// Constantes injetadas pelo PHP no _header.php (window.IS_ADMIN, USER_NOME, CAN_VIEW_ALL)
// Se não vierem, assume admin (segurança backend cobre o resto)
window.IS_ADMIN  = (typeof IS_ADMIN  !== 'undefined') ? IS_ADMIN  : true;
window.USER_NOME = (typeof USER_NOME !== 'undefined') ? USER_NOME : '';
// CAN_VIEW_ALL = admin OU visualizador (espelho admin sem editar).
// Analista comum NÃO vê tudo: fica restrito à própria carteira (ex.: Welbert → Operação 2).
window.CAN_VIEW_ALL = (typeof CAN_VIEW_ALL !== 'undefined') ? CAN_VIEW_ALL : window.IS_ADMIN;
// Fallback do filtro travado p/ analista comum (vazio = usa o próprio nome do login).
window.DM_OPERACAO_VER = '';

// Fallback de COLORS caso common.js seja antigo / não carregue ou seja outra versão
const _DM_COLORS = (typeof window.COLORS !== 'undefined' && Array.isArray(window.COLORS) && window.COLORS.length)
  ? window.COLORS
  : ['#00c9a7','#58a6ff','#bc8cff','#ffa657','#3fb950','#f85149','#d29922','#79c0ff','#ff7b72','#a5d6ff'];

// Fallbacks defensivos pra outros helpers que podem não estar carregados
if (typeof window.esc !== 'function')    window.esc    = s => { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; };
if (typeof window.escAttr !== 'function') window.escAttr = s => window.esc(s).replace(/"/g,'&quot;');
if (typeof window.toast !== 'function')  window.toast  = m => { try { alert(m); } catch(e){} };
if (typeof window.fmtDate !== 'function') window.fmtDate = s => { if(!s) return '—'; const m=String(s).match(/^(\d{4})-(\d{2})-(\d{2})/); return m?`${m[3]}/${m[2]}/${m[1]}`:String(s); };
if (typeof window.fmtDateTime !== 'function') window.fmtDateTime = s => { if(!s) return '—'; const dt=new Date(String(s).replace(' ','T')); return isNaN(dt.getTime())?String(s):dt.toLocaleDateString('pt-BR')+' '+dt.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}); };
if (typeof window.sc !== 'function')     window.sc     = st => { const k=String(st||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); if(['concluido','enviado','aceita','finalizada'].includes(k)) return 'b-concluido'; if(['em analise','aguardando envio'].includes(k)) return 'b-analise'; return 'b-pendencia'; };
if (typeof window.openModal !== 'function')  window.openModal  = id => { const el=document.getElementById(id); if(el) el.classList.add('show'); };
if (typeof window.closeModal !== 'function') window.closeModal = id => { const el=document.getElementById(id); if(el) el.classList.remove('show'); };
if (typeof window.apiGet !== 'function')  window.apiGet  = async (a,p={}) => (await fetch('api.php?'+new URLSearchParams({action:a,...p}))).json();
if (typeof window.apiPost !== 'function') window.apiPost = async (a,b={}) => (await fetch('api.php?action='+encodeURIComponent(a),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)})).json();

// ── Estado ──────────────────────────────────────────────────────
let dmData = [];
let DM_PAGE = 1;
const DM_PER = 15;

let _dmAnalistas = [];
// Admins que TAMBÉM cadastram demanda (aparecem no filtro e no seletor mesmo sendo admin).
const DM_ANALISTA_EXCECOES = ['letícia pacheco', 'leticia pacheco'];
// Nomes que NÃO devem aparecer na lista de analistas (nem filtro, nem seletor).
const DM_ANALISTA_REMOVER = ['frederico correa', 'fred correa'];
// Extras do filtro de analista: nomes que NÃO são tipo "analista" no cadastro,
// mas que devem aparecer no filtro mesmo assim (carteiras operacionais, etc.).
const DM_ANALISTAS_EXTRAS = ['Operacao 1', 'Operacao 2', 'Leticia Pacheco'];
let _dmSeguradoras = [];
let _dmEmpresas = [];

// ── INIT ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  // 1. Carrega caches em paralelo (cada um errors=>warning)
  await Promise.all([
    apiGet('analistas').then(r => _dmAnalistas = r.data || []).catch(e => console.warn('analistas:', e)),
    apiGet('seguradoras').then(r => _dmSeguradoras = r.data || []).catch(e => console.warn('seguradoras:', e)),
    apiGet('empresas').then(r => _dmEmpresas = r.data || []).catch(e => console.warn('empresas:', e))
  ]);

  // 2. Popula filtro de analistas (custom dropdown)
  dmFillAnalistasDropdown();

  // 3. Carrega demandas + stats
  await dmLoad();
  await dmLoadStats();
});

function dmFillAnalistasDropdown(){
  // Analista comum só vê a própria carteira → o filtro de analista fica travado
  // no próprio nome. Esconde o dropdown pra quem não pode filtrar por outros.
  if (!window.CAN_VIEW_ALL) {
    const ddWrap = document.getElementById('f-analista-dd');
    if (ddWrap) ddWrap.style.display = 'none';
    return;
  }
  const list = document.getElementById('f-analista-list');
  if (!list) return;
  list.innerHTML = '';
  const liAll = document.createElement('li');
  liAll.textContent = 'Todos os analistas';
  liAll.dataset.value = '';
  liAll.classList.add('selected');
  liAll.onclick = () => cddPick('f-analista-dd', '', 'Todos os analistas');
  list.appendChild(liAll);

  // Analistas do filtro:
  //  - TODOS os cadastrados como tipo "analista" e ATIVOS (entram sozinhos ao cadastrar)
  //  - MAIS os extras fixos (carteiras operacionais / nomes que não são tipo analista)
  // chave sem acento e em minúsculas, pra "Operação 2" e "Operacao 2" contarem como iguais
  const dmNorm = s => String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/\s+/g,' ').trim();
  const vistos = new Set();
  const nomes = [];
  (_dmAnalistas || []).forEach(a => {
    const nome = (a.nome || '').trim();
    const tipo = (a.tipo || '').toLowerCase().trim();
    const chave = dmNorm(nome);
    if (!nome) return;
    if (DM_ANALISTA_REMOVER.map(dmNorm).includes(chave)) return; // nunca mostrar
    if (tipo !== 'analista') return;                         // só tipo "analista"
    if (a.ativo != null && Number(a.ativo) === 0) return;    // só ativos
    if (vistos.has(chave)) return;                           // sem duplicados (acento-insensível)
    vistos.add(chave); nomes.push(nome);
  });
  nomes.sort((x, y) => x.localeCompare(y, 'pt-BR'));
  // Extras: aparecem mesmo não sendo tipo "analista" (não duplica os que já entraram)
  DM_ANALISTAS_EXTRAS.forEach(nome => {
    const k = dmNorm(nome);
    if (!vistos.has(k)) { vistos.add(k); nomes.push(nome); }
  });
  nomes.forEach(nome => {
    const li = document.createElement('li');
    li.textContent = nome;
    li.dataset.value = nome;
    li.onclick = () => cddPick('f-analista-dd', nome, nome);
    list.appendChild(li);
  });
}

// ── Custom dropdown helpers ─────────────────────────────────────
function cddToggle(ddId, e){
  if (e) e.stopPropagation();
  const dd = document.getElementById(ddId);
  // Fecha outros
  document.querySelectorAll('.cdd.open').forEach(o => { if (o !== dd) o.classList.remove('open'); });
  dd.classList.toggle('open');
}
function cddPick(ddId, value, label){
  const dd = document.getElementById(ddId);
  const inp = dd.querySelector('input[type=hidden]');
  const lbl = dd.querySelector('.cdd-label');
  if (inp) inp.value = value;
  if (lbl) lbl.textContent = label;
  dd.querySelectorAll('li').forEach(li => li.classList.remove('selected'));
  const sel = dd.querySelector(`li[data-value="${value.replace(/"/g,'\\"')}"]`);
  if (sel) sel.classList.add('selected');
  dd.classList.remove('open');
  dmLoad();
}
document.addEventListener('click', e => {
  if (!e.target.closest('.cdd')) {
    document.querySelectorAll('.cdd.open').forEach(o => o.classList.remove('open'));
  }
});

// ── BUSCA: debounce + clear + atalho Ctrl+K ─────────────────────
let _dmSearchTimer = null;
function dmSearchDebounced(){
  const wrap = document.getElementById('f-search-wrap');
  const inp  = document.getElementById('f-search');
  if (wrap && inp) wrap.classList.toggle('has-text', !!inp.value.trim());
  clearTimeout(_dmSearchTimer);
  _dmSearchTimer = setTimeout(() => dmLoad(), 300);
}
function dmClearSearch(){
  const inp  = document.getElementById('f-search');
  const wrap = document.getElementById('f-search-wrap');
  if (inp) inp.value = '';
  if (wrap) wrap.classList.remove('has-text');
  if (inp) inp.focus();
  clearTimeout(_dmSearchTimer);
  dmLoad();
}
function _dmInitSearch(){
  const inp = document.getElementById('f-search');
  if (inp && !inp.dataset.bound) {
    inp.dataset.bound = '1';
    inp.addEventListener('input', dmSearchDebounced);
    inp.addEventListener('keydown', e => {
      if (e.key === 'Escape' && inp.value) { e.preventDefault(); dmClearSearch(); }
      if (e.key === 'Enter')               { e.preventDefault(); clearTimeout(_dmSearchTimer); dmLoad(); }
    });
  }
  // Atalho global Ctrl+K / Cmd+K → focar busca (ignora Shift pra não conflitar com devtools)
  if (!window._dmKHotkey) {
    window._dmKHotkey = true;
    document.addEventListener('keydown', e => {
      if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === 'k') {
        const i = document.getElementById('f-search');
        if (i) { e.preventDefault(); i.focus(); i.select(); }
      }
    });
  }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', _dmInitSearch);
} else {
  _dmInitSearch();
}

// ── LOAD demandas ───────────────────────────────────────────────
async function dmLoad(){
  DM_PAGE = 1;
  const analistaFiltro = window.CAN_VIEW_ALL
    ? (document.getElementById('f-analista').value || '')
    : (window.DM_OPERACAO_VER || window.USER_NOME);

  // Monta params apenas com valores não-vazios pra evitar enviar lixo na URL
  const raw = {
    search:    val('f-search'),
    analista:  analistaFiltro,
    situacao:  val('f-situacao'),
    ramo:      val('f-ramo'),
    tipo:      val('f-tipo'),
    origem:    val('f-origem'),
    receb_de:  val('f-receb-de'),
    receb_ate: val('f-receb-ate'),
    prox_de:   val('f-prox-de'),
    prox_ate:  val('f-prox-ate'),
  };
  const params = {};
  Object.keys(raw).forEach(k => { if (raw[k] !== '' && raw[k] != null) params[k] = raw[k]; });

  // Se o usuário mudou o select de SITUAÇÃO manualmente pra algo específico,
  // desliga a flag de "atrasadas" (são filtros mutuamente exclusivos).
  if (raw.situacao) window._dmFiltroAtrasadas = false;

  // Flag especial do card "Atrasadas"
  if (window._dmFiltroAtrasadas) params.atrasado = '1';

  try {
    const r = await apiGet('listar', params);
    dmData = r.data || [];
  } catch(e){
    dmData = [];
    console.error('Erro ao listar demandas:', e);
    toast('Erro ao carregar demandas');
  }
  dmRenderTable();
}

function val(id){
  const el = document.getElementById(id);
  return el ? (el.value || '') : '';
}

// ── STATS (cards) ───────────────────────────────────────────────
async function dmLoadStats(){
  const params = window.CAN_VIEW_ALL ? {} : { analista: (window.DM_OPERACAO_VER || window.USER_NOME) };
  try {
    const r = await apiGet('stats', params);
    const d = r.data || {};
    setText('s-total',     d.total      || 0);
    setText('s-analise',   d.analise    || 0);
    setText('s-concluido', d.concluido  || 0);
    setText('s-atrasadas', d.atrasadas  || 0);
  } catch(e){
    console.error('Erro stats:', e);
  }
}
function setText(id, v){
  const el = document.getElementById(id);
  if (el) el.textContent = v;
}

// ── RENDER TABELA ───────────────────────────────────────────────
function dmRenderTable(){
  const tbody = document.getElementById('tbody');
  const total = dmData.length;
  const cnt = document.getElementById('tcount');
  if (cnt) cnt.textContent = `Lista de Demandas (${total})`;

  if (!total){
    tbody.innerHTML = `<tr><td colspan="16" style="text-align:center;padding:30px;color:var(--muted)">Nenhuma demanda encontrada</td></tr>`;
    document.getElementById('pag-info').textContent = '0 registros';
    document.getElementById('pag-btns').innerHTML = '';
    return;
  }

  const ini = (DM_PAGE - 1) * DM_PER;
  const slice = dmData.slice(ini, ini + DM_PER);

  tbody.innerHTML = slice.map((d, i) => {
    const ramoCls = d.ramo === 'Saúde' ? 'r-saude'
                  : d.ramo === 'Odonto' ? 'r-odonto'
                  : d.ramo === 'Saúde/Odonto' ? 'r-saude-odonto'
                  : d.ramo === 'Vida' ? 'r-vida'
                  : 'r-enfermagem';
    const ci = d.analista_nome ? d.analista_nome.charCodeAt(0) % _DM_COLORS.length : 0;
    const color = _DM_COLORS[ci];
    const init = (d.analista_nome || '??').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();

    // Próx. ação atrasada?
    const today = new Date().toISOString().slice(0, 10);
    const isLate = d.proxima_acao && d.proxima_acao < today && d.situacao !== 'Concluído';
    const lateClass = isLate ? 'late' : '';

    // Badge da coluna ATRASO (independente do badge da situação)
    const atrasoBadge = isLate
      ? `<span style="background:rgba(248,81,73,.12);color:#f85149;padding:3px 9px;border-radius:12px;font-size:11.5px;font-weight:600;white-space:nowrap">⚠ Em atraso</span>`
      : (d.situacao === 'Concluído'
          ? `<span style="color:var(--success);font-size:11.5px">—</span>`
          : `<span style="color:var(--muted);font-size:11.5px">Em dia</span>`);

    return `<tr>
      <td style="text-align:center"><input type="checkbox" class="dm-chk" value="${d.id}" onclick="dmUpdSel()"></td>
      <td style="color:#484f58;font-size:11px">#${String(d.id).padStart(4,'0')}</td>
      <td><strong>${esc(d.grupo||'—')}</strong><br><span style="color:var(--muted);font-size:11.5px">${esc(d.empresa||'')}</span></td>
      <td style="font-size:12px">${esc(d.origem||'—')}</td>
      <td style="font-size:12px">${esc(d.seguradora||'—')}</td>
      <td><span class="ramo ${ramoCls}">${esc(d.ramo||'—')}</span></td>
      <td style="font-size:12px">${esc(d.tipo_demanda||'—')}</td>
      <td><span class="badge ${sc(d.situacao)}">${esc(d.situacao||'—')}</span></td>
      <td>${atrasoBadge}</td>
      <td style="font-size:12px">${fmtDate(d.recebimento)}</td>
      <td style="font-size:12px" class="${lateClass}">${fmtDate(d.proxima_acao)}</td>
      <td><div style="display:flex;align-items:center"><div class="avatar" style="background:${color}">${init}</div><span style="font-size:12px">${esc(d.analista_nome||'—')}</span></div></td>
      <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px" title="${escAttr(d.dados_beneficiario||'')}">${esc(d.dados_beneficiario||'—')}</td>
      <td style="font-size:11.5px;color:var(--muted)">${esc(d.protocolo||'—')}</td>
      <td style="font-size:11.5px;color:var(--muted)">${fmtDateTime(d.criado_em)}</td>
      <td><button class="btn btn-outline btn-sm" onclick="dmEdit(${d.id})">Editar</button></td>
    </tr>`;
  }).join('');

  // Paginação
  const totalPages = Math.ceil(total / DM_PER);
  document.getElementById('pag-info').textContent = `${ini+1}-${Math.min(ini+DM_PER, total)} de ${total}`;
  let btns = '';
  if (totalPages > 1){
    const max = 12;
    for (let p = 1; p <= Math.min(totalPages, max); p++){
      btns += `<button class="pb${p===DM_PAGE?' active':''}" onclick="DM_PAGE=${p};dmRenderTable()">${p}</button>`;
    }
  }
  document.getElementById('pag-btns').innerHTML = btns;
  dmUpdSel();
}

// ── Conclusão em lote (selecionar várias → concluir com uma data) ──
function dmGetSelIds(){
  return [...document.querySelectorAll('.dm-chk:checked')].map(c => parseInt(c.value)).filter(Boolean);
}
function dmToggleAll(el){
  document.querySelectorAll('.dm-chk').forEach(c => { c.checked = el.checked; });
  dmUpdSel();
}
function dmUpdSel(){
  const ids  = dmGetSelIds();
  const info = document.getElementById('dm-selinfo');
  const btn  = document.getElementById('dm-btn-concluir');
  if (info) info.textContent = ids.length ? (ids.length + (ids.length === 1 ? ' selecionada' : ' selecionadas')) : '';
  if (btn)  btn.disabled = ids.length === 0;
  const all  = document.getElementById('dm-selall');
  const chks = document.querySelectorAll('.dm-chk');
  if (all) all.checked = chks.length > 0 && ids.length === chks.length;
}
async function dmConcluirLote(){
  const ids = dmGetSelIds();
  if (!ids.length){ alert('Selecione ao menos uma demanda.'); return; }
  const data = (document.getElementById('dm-lote-data') || {}).value || '';
  if (!data){ alert('Escolha a data de conclusão antes de concluir.'); return; }
  const dataBR = data.split('-').reverse().join('/');
  if (!confirm(`Concluir ${ids.length} demanda(s) com data de conclusão ${dataBR}?`)) return;
  const r = await apiPost('concluir_demandas_lote', { ids, data_conclusao: data });
  if (!r.ok){ alert('Erro: ' + (r.msg || 'falha ao concluir')); return; }
  if (typeof toast === 'function') toast(`${r.afetadas || ids.length} demanda(s) concluída(s)`);
  await dmLoad();
  if (typeof dmLoadStats === 'function') dmLoadStats();
}
window.dmToggleAll    = dmToggleAll;
window.dmUpdSel       = dmUpdSel;
window.dmConcluirLote = dmConcluirLote;

// ── FILTROS rápidos pelos cards ─────────────────────────────────
function dmFiltrarPor(tipo){
  const sel = document.getElementById('f-situacao');
  if (!sel) return;
  // Reseta flag de atrasadas (só permanece se for o card 'atrasadas')
  window._dmFiltroAtrasadas = false;
  if (tipo === 'total')          sel.value = '';
  else if (tipo === 'analise')   sel.value = 'Em análise'; // minúsculo (novo padrão)
  else if (tipo === 'concluido') sel.value = 'Concluído';
  else if (tipo === 'atrasadas') {
    sel.value = '';
    window._dmFiltroAtrasadas = true;
  }
  dmLoad();
}

// ── MODAL CADASTRO ──────────────────────────────────────────────
function openModalDemanda(){
  document.getElementById('md-id').value = '';
  document.getElementById('md-title').textContent = 'Nova Demanda';
  document.getElementById('md-btn-del').style.display = 'none';
  ['md-origem','md-ramo','md-protocolo','md-benef','md-assunto','md-obs',
   'md-recebimento','md-ini-trat','md-prox','md-conclusao']
    .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  document.getElementById('md-tipo').value = 'Inclusão';
  document.getElementById('md-situacao').value = 'Em Análise';

  // popular dropdowns
  fillSel('md-seguradora', 'Selecione...',
    _dmSeguradoras.map(s => typeof s === 'string' ? s : s.nome).filter(Boolean).sort());
  // Só ANALISTAS de verdade (sem admin/head/coordenador/financeiro/enfermeira/etc)
  const _dmAnalistasSomenteAnalistas = (_dmAnalistas || []).filter(a => {
    const tipo = (a.tipo || '').toLowerCase();
    const nome = (a.nome || '').toLowerCase().trim();
    if (DM_ANALISTA_REMOVER.includes(nome)) return false; // remover sempre
    if (DM_ANALISTA_EXCECOES.includes(nome)) return true; // exceção: sempre incluir
    if (['admin','head','coordenador','financeiro','enfermeira','enfermagem',
         'visualizador','novos_negocios'].includes(tipo)) return false;
    if (nome.startsWith('enf.') || nome.startsWith('enf ')) return false;
    if (nome === 'administrador' || nome === 'financeiro interweg') return false;
    return true;
  });
  fillSel('md-analista', 'Selecione...', _dmAnalistasSomenteAnalistas.map(a => a.nome));
  const grupos = [...new Set(_dmEmpresas.map(e => e.grupo))].filter(Boolean).sort();
  fillSel('md-grupo', 'Selecione...', grupos);

  document.getElementById('md-grupo').onchange = dmUpdateEmpresas;
  document.getElementById('md-empresa').innerHTML = '<option value="">Selecione um grupo primeiro</option>';

  // recebimento default = hoje
  document.getElementById('md-recebimento').value = new Date().toISOString().slice(0, 10);

  openModal('mod-demanda');
}

function fillSel(id, placeholder, items){
  const el = document.getElementById(id);
  if (!el) return;
  el.innerHTML = `<option value="">${placeholder}</option>` +
    items.map(s => `<option value="${escAttr(s)}">${esc(s)}</option>`).join('');
}

function dmUpdateEmpresas(){
  const grupo = document.getElementById('md-grupo').value;
  const sel = document.getElementById('md-empresa');
  const list = _dmEmpresas.filter(e => e.grupo === grupo);
  sel.innerHTML = '<option value="">Selecione...</option>' +
    list.map(e => `<option value="${escAttr(e.nome||e.empresa||'')}">${esc(e.nome||e.empresa||'')}</option>`).join('');
}

// Garante que o valor salvo na demanda apareça no <select> mesmo que não esteja
// entre as opções (ex.: empresa/seguradora que não está no cadastro atual).
function dmEnsureOpt(id, val){
  const sel = document.getElementById(id);
  if (!sel || val == null || String(val).trim() === '') return;
  const v = String(val);
  const existe = Array.from(sel.options).some(o => o.value === v);
  if (!existe){ const o = document.createElement('option'); o.value = v; o.textContent = v; sel.appendChild(o); }
}

async function dmEdit(id){
  const d = dmData.find(x => x.id == id);
  if (!d){ toast('Demanda não encontrada'); return; }

  openModalDemanda();
  document.getElementById('md-title').textContent = `Editar Demanda #${id}`;
  document.getElementById('md-btn-del').style.display = '';
  document.getElementById('md-id').value = id;

  setVal('md-origem',      d.origem);
  setVal('md-ramo',        d.ramo);
  dmEnsureOpt('md-seguradora', d.seguradora); setVal('md-seguradora',  d.seguradora);
  dmEnsureOpt('md-grupo',      d.grupo);       setVal('md-grupo',       d.grupo);
  dmUpdateEmpresas();
  dmEnsureOpt('md-empresa',    d.empresa);     setVal('md-empresa',     d.empresa);
  setVal('md-tipo',        d.tipo_demanda);
  dmEnsureOpt('md-situacao',   d.situacao);     setVal('md-situacao',    d.situacao);
  // Garante que o analista ATUAL da demanda apareça na lista (mesmo que não seja
  // um analista "ativo"), pra o campo não voltar pra "Selecione..." e apagar o analista.
  (function(){
    const selA = document.getElementById('md-analista');
    const nm = (d.analista_nome || '').trim();
    if (selA && nm && ![...selA.options].some(o => o.value === nm)) {
      const opt = document.createElement('option');
      opt.value = nm; opt.textContent = nm + ' (atual)';
      selA.appendChild(opt);
    }
  })();
  setVal('md-analista',    d.analista_nome);
  setVal('md-recebimento', d.recebimento);
  setVal('md-ini-trat',    d.inicio_tratativa);
  setVal('md-prox',        d.proxima_acao);
  setVal('md-conclusao',   d.data_conclusao);
  setVal('md-protocolo',   d.protocolo);
  setVal('md-benef',       d.dados_beneficiario);
  setVal('md-assunto',     d.assunto_email);
  setVal('md-obs',         d.detalhamento || d.observacoes);
}
function setVal(id, v){
  const el = document.getElementById(id);
  if (el) el.value = (v == null ? '' : v);
}

async function dmSave(){
  const id = document.getElementById('md-id').value;
  // resolve analista_id pelo nome
  const analistaNome = val('md-analista');
  const analista = _dmAnalistas.find(a => a.nome === analistaNome);
  const body = {
    id: id || null,
    origem:        val('md-origem'),
    ramo:          val('md-ramo'),
    seguradora:    val('md-seguradora'),
    grupo:         val('md-grupo'),
    empresa:       val('md-empresa'),
    tipo_demanda:  val('md-tipo'),
    situacao:      val('md-situacao'),
    analista_id:   analista ? analista.id : null,
    analista_nome: analistaNome,
    recebimento:      val('md-recebimento')   || null,
    inicio_tratativa: val('md-ini-trat')      || null,
    proxima_acao:     val('md-prox')          || null,
    data_conclusao:   val('md-conclusao')     || null,
    protocolo:           val('md-protocolo'),
    dados_beneficiario:  val('md-benef'),
    assunto_email:       val('md-assunto'),
    detalhamento:        val('md-obs')   // textarea Observações grava na coluna `detalhamento`
  };

  if (!body.empresa || !body.grupo){
    toast('Preencha Grupo e Empresa');
    return;
  }
  if (!body.tipo_demanda){ toast('Tipo de demanda obrigatório'); return; }

  const action = id ? 'editar' : 'criar';
  const r = await apiPost(action, body);
  if (r.ok){
    toast(id ? 'Demanda atualizada' : 'Demanda criada');
    closeModal('mod-demanda');
    dmLoad();
    dmLoadStats();
  } else {
    toast('Erro: ' + (r.msg || 'falha ao salvar'));
  }
}

async function dmDelete(){
  const id = document.getElementById('md-id').value;
  if (!id) return;
  if (!confirm(`Excluir demanda #${id}?`)) return;
  const r = await apiGet('excluir', { id });
  if (r.ok){
    toast('Demanda excluída');
    closeModal('mod-demanda');
    dmLoad();
    dmLoadStats();
  } else {
    toast('Erro: ' + (r.msg || 'falha ao excluir'));
  }
}

// ═══════════════════════════════════════════════════════════════
// LOTE — Cadastro em lote (até 50 demandas de uma vez)
// ═══════════════════════════════════════════════════════════════

const TIPOS_DEMANDA = ['Inclusão','Exclusão','Faturamento','Autorização','Cobrança Indevida','Busca por prestador','Outras demandas','Alteração Cadastral','Plano Extensão','Subfatura','Abertura de subfatura','Transferência de planos','Resumo mensal'];
const SITUACOES_DEMANDA = ['Em Análise','Concluído','PENDÊNCIA','Pendente documentação Empresa','Pendente retorno cliente','Aguardar processar'];

const LOTE_COLS = [
  {key:'grupo',              aliases:['grupo','grupo / empresa','grupo/empresa']},
  {key:'empresa',            aliases:['empresa','razao social','razão social','razao_social']},
  {key:'seguradora',         aliases:['seguradora','operadora','operadora atual']},
  {key:'ramo',               aliases:['ramo','tipo plano','tipo de plano']},
  {key:'analista_nome',      aliases:['analista','analista nome','analista_nome','responsavel','responsável']},
  {key:'tipo_demanda',       aliases:['tipo','tipo demanda','tipo_demanda','tipo de demanda']},
  {key:'situacao',           aliases:['situacao','situação','status']},
  {key:'dados_beneficiario', aliases:['beneficiario','beneficiário','dados beneficiario','dados_beneficiario','dados do beneficiario','dados do beneficiário']},
  {key:'descricao_demanda',  aliases:['descricao','descrição','descricao_demanda','descricao demanda','descrição da demanda']},
  {key:'assunto_email',      aliases:['assunto','assunto email','assunto e-mail','assunto do email','assunto do e-mail','assunto_email','assunto e mail']},
  {key:'protocolo',          aliases:['protocolo','nº protocolo','numero protocolo','número protocolo','no protocolo']},
  {key:'recebimento',        aliases:['recebimento','data recebimento','data de recebimento','dt recebimento']},
  {key:'inicio_tratativa',   aliases:['inicio_tratativa','início tratativa','inicio tratativa','início da tratativa','inicio da tratativa','ini tratativa','ini. tratativa']},
  {key:'proxima_acao',       aliases:['proxima_acao','próxima ação','proxima acao','prox acao','prox. ação','prox. acao']},
  {key:'data_conclusao',     aliases:['data_conclusao','conclusao','conclusão','data conclusao','data conclusão','data de conclusão']},
  {key:'detalhamento',       aliases:['detalhamento','detalhes','histórico','historico','acompanhamento','observações','observacoes']}
];

function loteBuildRow(i){
  const tr = document.createElement('tr');
  tr.className = 'ml-row';
  tr.innerHTML = `
    <td style="padding:6px 7px;color:var(--muted);font-weight:600">${i}</td>
    <td style="padding:3px"><input type="text" class="ml-grupo-row" placeholder="Grupo" style="padding:5px 7px;font-size:11.5px;width:100%" list="ml-grupos-dl"/></td>
    <td style="padding:3px"><input type="text" class="ml-empresa-row" placeholder="Empresa" style="padding:5px 7px;font-size:11.5px;width:100%" list="ml-empresas-dl"/></td>
    <td style="padding:3px"><input type="text" class="ml-seguradora-row" placeholder="Seguradora" style="padding:5px 7px;font-size:11.5px;width:100%" list="ml-segs-dl"/></td>
    <td style="padding:3px"><select class="ml-ramo-row" style="padding:5px 7px;font-size:11.5px;width:100%"><option value="">—</option><option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option><option>Vida</option><option>Enfermagem</option></select></td>
    <td style="padding:3px"><select class="ml-tipo" style="padding:5px 7px;font-size:11.5px"><option value="">—</option>${TIPOS_DEMANDA.map(t=>`<option>${t}</option>`).join('')}</select></td>
    <td style="padding:3px"><select class="ml-sit" style="padding:5px 7px;font-size:11.5px"><option value="">— (padrão)</option>${SITUACOES_DEMANDA.map(s=>`<option>${s}</option>`).join('')}</select></td>
    <td style="padding:3px"><input type="text" class="ml-benef" placeholder="Nome, CPF, nasc..." style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px"><input type="text" class="ml-desc" placeholder="Descrição..." style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px"><input type="text" class="ml-assunto" placeholder="RE: ..." style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px"><input type="text" class="ml-prot" placeholder="Nº protocolo" style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px"><input type="date" class="ml-receb" style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px"><input type="date" class="ml-ini" style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px"><input type="date" class="ml-prox" style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px"><input type="date" class="ml-conc" style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px"><input type="text" class="ml-anal-row" placeholder="Analista" style="padding:5px 7px;font-size:11.5px;width:100%" list="ml-analistas-dl"/></td>
    <td style="padding:3px"><input type="text" class="ml-det" placeholder="Histórico, obs..." style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px;text-align:center"><button class="btn btn-danger btn-sm" type="button" onclick="loteRemoveRow(this)" title="Remover"><span class="material-symbols-rounded" style="font-size:14px">close</span></button></td>`;
  tr.querySelectorAll('input,select').forEach(el => el.addEventListener('input', loteUpdateCount));
  return tr;
}

function loteAddRow(){
  const tb = document.getElementById('ml-tbody');
  if (tb.children.length >= 50) { alert('Máximo de 50 linhas por lote.'); return; }
  tb.appendChild(loteBuildRow(tb.children.length + 1));
  loteUpdateCount();
}

function loteRemoveRow(btn){
  btn.closest('tr').remove();
  document.querySelectorAll('#ml-tbody tr').forEach((r, idx) => { r.firstElementChild.textContent = (idx+1); });
  loteUpdateCount();
}

function loteClear(){
  if (!confirm('Limpar todas as linhas?')) return;
  const tb = document.getElementById('ml-tbody');
  tb.innerHTML = '';
  for (let i=1; i<=20; i++) tb.appendChild(loteBuildRow(i));
  loteUpdateCount();
}

function loteUpdateCount(){
  let n = 0;
  document.querySelectorAll('#ml-tbody tr').forEach(r => {
    const tipo = r.querySelector('.ml-tipo').value;
    const sit  = r.querySelector('.ml-sit').value;
    const benef= r.querySelector('.ml-benef').value.trim();
    const desc = r.querySelector('.ml-desc').value.trim();
    const assunto = r.querySelector('.ml-assunto').value.trim();
    const prot = r.querySelector('.ml-prot').value.trim();
    const det  = r.querySelector('.ml-det').value.trim();
    if (tipo||sit||benef||desc||assunto||prot||det) n++;
  });
  const el = document.getElementById('ml-count');
  if (el) el.textContent = n;
}

function openModalLote(){
  // Popular select de Analista padrão
  const selA = document.getElementById('ml-analista-sel');
  selA.innerHTML = '<option value="">Selecione...</option>' +
    (_dmAnalistas||[]).map(a => `<option value="${a.id}">${esc(a.nome)}</option>`).join('');

  // Popular datalists para autocomplete
  const dlG = document.getElementById('ml-grupos-dl');
  const dlE = document.getElementById('ml-empresas-dl');
  const dlS = document.getElementById('ml-segs-dl');
  const dlA = document.getElementById('ml-analistas-dl');
  if (dlG) dlG.innerHTML = [...new Set((_dmEmpresas||[]).map(e=>e.grupo))].sort().map(g=>`<option value="${escAttr(g)}">`).join('');
  if (dlE) dlE.innerHTML = [...new Set((_dmEmpresas||[]).map(e=>e.nome||e.empresa))].sort().map(n=>`<option value="${escAttr(n)}">`).join('');
  if (dlS) dlS.innerHTML = (_dmSeguradoras||[]).map(s=>`<option value="${escAttr(typeof s==='string'?s:s.nome)}">`).join('');
  if (dlA) dlA.innerHTML = (_dmAnalistas||[]).map(a=>`<option value="${escAttr(a.nome)}">`).join('');

  document.getElementById('ml-recebimento').value = new Date().toISOString().slice(0,10);

  const tb = document.getElementById('ml-tbody');
  tb.innerHTML = '';
  for (let i=1; i<=20; i++) tb.appendChild(loteBuildRow(i));
  loteUpdateCount();

  openModal('mod-lote');
}

// ── Importação de planilha ──────────────────────────────────────
function _normHeader(s){
  return String(s||'').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/\s+/g,' ');
}

function _parseDateCell(v){
  if (v==null || v==='') return '';
  if (typeof v==='number' && typeof XLSX!=='undefined' && XLSX.SSF){
    const o = XLSX.SSF.parse_date_code(v);
    if (o) return `${o.y}-${String(o.m).padStart(2,'0')}-${String(o.d).padStart(2,'0')}`;
  }
  const s = String(v).trim();
  let m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
  if (m) return `${m[1]}-${m[2].padStart(2,'0')}-${m[3].padStart(2,'0')}`;
  m = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
  if (m){
    let y = m[3]; if (y.length===2) y = '20'+y;
    return `${y}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`;
  }
  return '';
}

function loteDownloadTemplate(){
  if (typeof XLSX === 'undefined'){ alert('Biblioteca de planilhas ainda não carregou.'); return; }
  const header = ['Grupo','Empresa','Seguradora','Ramo','Tipo','Situação','Recebimento','Ini. Tratativa','Próx. Ação','Conclusão','Analista','Beneficiário','Protocolo','Assunto E-mail','Descrição','Detalhamento'];
  const exemplo = ['HINOVA','POOLTRACK','METLIFE','Odonto','Inclusão','Em Análise','2026-04-10','2026-04-11','2026-04-15','','Pâmela','João Silva - CPF 000.000.000-00','20863071','RE: Inclusão','Inclusão de titular','Aguardando retorno'];
  const vazia = header.map(()=>'');
  const linhas = [header, exemplo];
  for (let i=0; i<20; i++) linhas.push(vazia.slice());
  const ws = XLSX.utils.aoa_to_sheet(linhas);
  ws['!cols'] = [{wch:18},{wch:30},{wch:18},{wch:12},{wch:18},{wch:22},{wch:14},{wch:14},{wch:14},{wch:14},{wch:16},{wch:38},{wch:16},{wch:30},{wch:35},{wch:35}];
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Demandas');
  XLSX.writeFile(wb, 'modelo_demandas.xlsx');
}

function loteImportFile(input){
  const file = input.files && input.files[0];
  if (!file) return;
  if (typeof XLSX === 'undefined'){ alert('Biblioteca de planilhas ainda não carregou.'); input.value=''; return; }

  console.log('[Lote] Importando:', file.name, '|', (file.size/1024).toFixed(1), 'KB');

  const reader = new FileReader();
  reader.onload = e => {
    try {
      const data = new Uint8Array(e.target.result);
      const wb = XLSX.read(data, {type:'array'});
      const ws = wb.Sheets[wb.SheetNames[0]];
      const rows = XLSX.utils.sheet_to_json(ws, {header:1, defval:'', raw:true});
      if (!rows.length){ alert('Planilha vazia.'); input.value=''; return; }
      console.log('[Lote] Aba:', wb.SheetNames[0], '| Linhas no Excel:', rows.length, '| Cabeçalho:', rows[0]);

      const header = rows[0].map(_normHeader);
      const colIdx = {};
      LOTE_COLS.forEach(c => {
        const i = header.findIndex(h => c.aliases.includes(h));
        colIdx[c.key] = i;
      });

      const parsed = [];
      for (let i=1; i<rows.length; i++){
        const r = rows[i]; if (!r) continue;
        const getStr = k => colIdx[k]>=0 ? String(r[colIdx[k]]||'').trim() : '';
        const getDate = k => colIdx[k]>=0 ? _parseDateCell(r[colIdx[k]]) : '';
        let _g = getStr('grupo'), _e = getStr('empresa');
        if (_g && !_e && _g.includes(' / ')) {
          const p = _g.split(' / '); _g = p[0].trim(); _e = p.slice(1).join(' / ').trim();
        }
        const obj = {
          grupo:_g, empresa:_e,
          seguradora:getStr('seguradora'), ramo:getStr('ramo'),
          analista_nome:getStr('analista_nome'), tipo_demanda:getStr('tipo_demanda'),
          situacao:getStr('situacao'), dados_beneficiario:getStr('dados_beneficiario'),
          descricao_demanda:getStr('descricao_demanda'), assunto_email:getStr('assunto_email'),
          protocolo:getStr('protocolo'), recebimento:getDate('recebimento'),
          inicio_tratativa:getDate('inicio_tratativa'), proxima_acao:getDate('proxima_acao'),
          data_conclusao:getDate('data_conclusao'), detalhamento:getStr('detalhamento')
        };
        // Pula linha totalmente vazia
        if (!Object.values(obj).some(v => v)) continue;
        // Pula linha que só tem campos auxiliares (sem grupo+empresa+tipo, geralmente é cabeçalho descritivo ou legenda)
        if (!obj.grupo && !obj.empresa && !obj.tipo_demanda && !obj.dados_beneficiario && !obj.descricao_demanda) continue;
        parsed.push(obj);
      }

      if (!parsed.length){ alert('Nenhuma linha com dados encontrada.'); input.value=''; return; }

      // Normaliza tipos/situações para casar com as opções dos selects
      parsed.forEach(p => {
        if (p.tipo_demanda) {
          const m = TIPOS_DEMANDA.find(t => _normHeader(t) === _normHeader(p.tipo_demanda));
          if (m) p.tipo_demanda = m;
        }
        if (p.situacao) {
          const m = SITUACOES_DEMANDA.find(s => _normHeader(s) === _normHeader(p.situacao));
          if (m) p.situacao = m;
        }
      });

      const tb = document.getElementById('ml-tbody');
      tb.innerHTML = '';
      parsed.forEach((p, i) => {
        const tr = loteBuildRow(i+1);
        tr.querySelector('.ml-grupo-row').value      = p.grupo || '';
        tr.querySelector('.ml-empresa-row').value    = p.empresa || '';
        tr.querySelector('.ml-seguradora-row').value = p.seguradora || '';
        tr.querySelector('.ml-ramo-row').value       = p.ramo || '';
        tr.querySelector('.ml-anal-row').value       = p.analista_nome || '';
        tr.querySelector('.ml-tipo').value    = p.tipo_demanda || '';
        tr.querySelector('.ml-sit').value     = p.situacao || '';
        tr.querySelector('.ml-benef').value   = p.dados_beneficiario || '';
        tr.querySelector('.ml-desc').value    = p.descricao_demanda || '';
        tr.querySelector('.ml-assunto').value = p.assunto_email || '';
        tr.querySelector('.ml-prot').value    = p.protocolo || '';
        tr.querySelector('.ml-receb').value   = p.recebimento || '';
        tr.querySelector('.ml-ini').value     = p.inicio_tratativa || '';
        tr.querySelector('.ml-prox').value    = p.proxima_acao || '';
        tr.querySelector('.ml-conc').value    = p.data_conclusao || '';
        tr.querySelector('.ml-det').value     = p.detalhamento || '';
        tb.appendChild(tr);
      });
      loteUpdateCount();
      toast(`${parsed.length} linha(s) importada(s) de "${file.name}"`, 5000);
    } catch(err){
      console.error(err);
      alert('Erro ao ler a planilha: ' + err.message);
    } finally {
      input.value = '';
    }
  };
  reader.onerror = () => { alert('Erro ao ler arquivo.'); input.value=''; };
  reader.readAsArrayBuffer(file);
}

// ── Salvar lote no banco ────────────────────────────────────────
async function saveLote(btn){
  // Analista padrão: já não vem do modal (cada linha tem o seu)
  const analista_id_padrao = null;
  const analista_nome_padrao = '';
  const recebimento_padrao = document.getElementById('ml-recebimento').value || null;

  const linhas = [];
  document.querySelectorAll('#ml-tbody tr').forEach(r => {
    const grupoR  = r.querySelector('.ml-grupo-row').value.trim();
    const empresaR= r.querySelector('.ml-empresa-row').value.trim();
    const segR    = r.querySelector('.ml-seguradora-row').value.trim();
    const ramoR   = r.querySelector('.ml-ramo-row').value.trim();
    const tipo   = r.querySelector('.ml-tipo').value;
    const sit    = r.querySelector('.ml-sit').value;
    const benef  = r.querySelector('.ml-benef').value.trim();
    const desc   = r.querySelector('.ml-desc').value.trim();
    const assunto= r.querySelector('.ml-assunto').value.trim();
    const prot   = r.querySelector('.ml-prot').value.trim();
    const receb  = r.querySelector('.ml-receb').value || null;
    const ini    = r.querySelector('.ml-ini').value || null;
    const prox   = r.querySelector('.ml-prox').value || null;
    const conc   = r.querySelector('.ml-conc').value || null;
    const analR  = r.querySelector('.ml-anal-row').value.trim();
    const det    = r.querySelector('.ml-det').value.trim();

    if (!grupoR && !empresaR && !segR && !ramoR && !tipo && !sit && !benef && !desc && !assunto && !prot && !receb && !ini && !prox && !conc && !analR && !det) return;
    if (!tipo){ linhas.push({_invalid:true}); return; }

    let analista_id_linha = null, analista_nome_linha = '';
    if (analR){
      // Match insensível a acento+caixa: "Pâmela" === "PAMELA" === "pamela"
      const _norm = s => String(s||'').normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().trim();
      const alvo = _norm(analR);
      const aMatch = (_dmAnalistas||[]).find(a => _norm(a.nome) === alvo);
      if (aMatch){ analista_id_linha = aMatch.id; analista_nome_linha = aMatch.nome; }
      else { analista_nome_linha = analR; } // não bateu — salva o que o usuário digitou
    }

    linhas.push({
      grupo: grupoR, empresa: empresaR, seguradora: segR, ramo: ramoR,
      analista_id_linha, analista_nome_linha,
      tipo_demanda: tipo, situacao: sit,
      dados_beneficiario: benef, descricao_demanda: desc,
      assunto_email: assunto, protocolo: prot,
      recebimento: receb, inicio_tratativa: ini,
      proxima_acao: prox, data_conclusao: conc,
      detalhamento: det
    });
  });

  if (!linhas.length){ alert('Preencha pelo menos uma linha.'); return; }
  if (linhas.some(l => l._invalid)){ alert('Toda linha preenchida precisa de um Tipo de Demanda.'); return; }

  const body = {
    comuns: {
      grupo:'', empresa:'', seguradora:'', ramo:'',
      analista_id: analista_id_padrao, analista_nome: analista_nome_padrao,
      situacao: 'Em Análise', recebimento: recebimento_padrao
    },
    linhas
  };

  if (btn) btn.disabled = true;
  try {
    console.log('[Lote] Enviando', linhas.length, 'linha(s) para criar_lote...');
    let r;
    try {
      const resp = await fetch('api.php?action=criar_lote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const txt = await resp.text();
      console.log('[Lote] HTTP', resp.status, '| corpo bruto:', txt.slice(0, 500));
      try { r = JSON.parse(txt); }
      catch (parseErr){
        alert('Servidor não retornou JSON (status ' + resp.status + '). Veja o console (aba Network → Response) para detalhes. Provável erro de PHP, sessão expirada ou limite de upload do servidor.');
        return;
      }
    } catch (netErr){
      console.error('[Lote] Erro de rede:', netErr);
      alert('Falha de rede ao salvar o lote: ' + netErr.message);
      return;
    }

    console.log('[Lote] Resposta JSON:', r);

    if (r && r.ok){
      const inseridos = (typeof r.inseridos === 'number') ? r.inseridos : null;
      // Aviso explícito se o servidor disse "ok" mas não inseriu nada
      if (inseridos === 0){
        alert('O servidor respondeu OK mas inseriu 0 demandas. ' +
              'Provável causa: tipo_demanda chegou vazio em todas as linhas. ' +
              'Confira o console para o payload e a resposta.');
        console.warn('[Lote] Payload enviado:', body);
        return;
      }
      closeModal('mod-lote');
      await dmLoad();
      await dmLoadStats();
      const msg = inseridos !== null
        ? `${inseridos} demanda(s) criada(s) no lote!`
        : `Lote enviado (servidor não confirmou contagem)`;
      toast(msg);
    } else {
      alert('Erro do servidor: ' + (r && r.msg ? r.msg : 'resposta inesperada — veja o console'));
    }
  } finally {
    if (btn) btn.disabled = false;
  }
}

// ── EXPORT XLSX (com seletor de data + todas as colunas) ────────
function dmOpenExportModal(){
  // Default: TUDO (sem limitar por mês). O usuário pode escolher um período.
  dmExportPreset('tudo');
  if (typeof openModal === 'function') openModal('mod-export');
  else document.getElementById('mod-export').classList.add('show');
}

function dmExportPreset(p){
  const ini = document.getElementById('ex-data-ini');
  const fim = document.getElementById('ex-data-fim');
  if (!ini || !fim) return;
  const hoje = new Date();
  const ymd  = d => d.toISOString().slice(0,10);
  if (p === 'mes'){
    ini.value = ymd(new Date(hoje.getFullYear(), hoje.getMonth(), 1));
    fim.value = ymd(new Date(hoje.getFullYear(), hoje.getMonth()+1, 0));
  } else if (p === 'mes-ant'){
    ini.value = ymd(new Date(hoje.getFullYear(), hoje.getMonth()-1, 1));
    fim.value = ymd(new Date(hoje.getFullYear(), hoje.getMonth(), 0));
  } else if (p === '30d'){
    const a = new Date(hoje); a.setDate(a.getDate()-30);
    ini.value = ymd(a); fim.value = ymd(hoje);
  } else if (p === 'ano'){
    ini.value = ymd(new Date(hoje.getFullYear(), 0, 1));
    fim.value = ymd(new Date(hoje.getFullYear(), 11, 31));
  } else if (p === 'tudo'){
    ini.value = ''; fim.value = '';
  }
}

// Carrega xlsx-js-style sob demanda (fallback se _header.php ainda tiver lib antiga)
function _ensureXlsxStyle(){
  if (window._xlsxStyleLoaded) return Promise.resolve();
  return new Promise((resolve) => {
    const urls = [
      'https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js',
      'https://unpkg.com/xlsx-js-style@1.2.0/dist/xlsx.bundle.js'
    ];
    let i = 0;
    const tryNext = () => {
      if (i >= urls.length) { console.warn('xlsx-js-style: todas as CDNs falharam, exportando sem estilo'); resolve(); return; }
      const s = document.createElement('script');
      s.src = urls[i++];
      s.onload  = () => { window._xlsxStyleLoaded = true; resolve(); };
      s.onerror = () => { s.remove(); tryNext(); };
      document.head.appendChild(s);
    };
    tryNext();
  });
}

// Busca TODAS as demandas em blocos (offset), sem teto de linhas
async function dmFetchAllPages(baseParams){
  const PAGE = 5000; let offset = 0, all = [];
  while (true){
    const r = await apiGet('listar', Object.assign({}, baseParams, { limit: PAGE, offset: offset }));
    const chunk = (r && r.data) || [];
    all = all.concat(chunk);
    if (chunk.length < PAGE) break;   // última página
    offset += PAGE;
    if (offset > 500000) break;       // trava de segurança
  }
  return all;
}

async function dmDoExport(btn){
  if (typeof XLSX === 'undefined'){ toast('SheetJS não carregou — recarregue a página'); return; }
  const ini = (document.getElementById('ex-data-ini')||{}).value || '';
  const fim = (document.getElementById('ex-data-fim')||{}).value || '';
  if (ini && fim && ini > fim){ toast('Data inicial maior que a final'); return; }
  const respeitar = (document.getElementById('ex-respeita-filtros')||{}).checked !== false;

  if (btn) { btn.disabled = true; btn.dataset.lbl = btn.innerHTML; btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:17px">hourglass_top</span> Gerando...'; }

  try {
    // Força carregamento do xlsx-js-style pra ter suporte a estilos de célula
    await _ensureXlsxStyle();
    // Buscar dados: se respeitar filtros, usa os filtros da tela; senão, busca tudo do backend
    let dados;
    if (respeitar) {
      const analistaFiltro = window.CAN_VIEW_ALL
        ? (document.getElementById('f-analista').value || '')
        : window.USER_NOME;
      const params = {
        search:   val('f-search'),
        analista: analistaFiltro,
        situacao: val('f-situacao'),
        ramo:     val('f-ramo'),
        tipo:     val('f-tipo')
      };
      if (ini) params.data_ini = ini;
      if (fim) params.data_fim = fim;
      dados = await dmFetchAllPages(params);
    } else {
      const params = {};
      if (!window.CAN_VIEW_ALL) params.analista = window.USER_NOME;
      if (ini) params.data_ini = ini;
      if (fim) params.data_fim = fim;
      dados = await dmFetchAllPages(params);
    }

    // Fallback no cliente caso o backend ainda não filtre por data
    if (ini || fim) {
      dados = dados.filter(d => {
        const r = (d.recebimento || '').slice(0,10);
        if (!r) return false;
        if (ini && r < ini) return false;
        if (fim && r > fim) return false;
        return true;
      });
    }

    if (!dados.length){ toast('Nenhuma demanda no período selecionado'); return; }

    // TODAS as colunas da tabela (mesma ordem visível)
    const cab = [
      'ID','Grupo','Empresa','Seguradora','Ramo','Tipo Demanda','Situação',
      'Recebimento','Próxima Ação','Analista','Beneficiário','Protocolo','Criado em',
      'Observações','Status (último)','Última atualização'
    ];
    const linhas = [cab];
    dados.forEach(d => {
      linhas.push([
        d.id || '',
        d.grupo || '',
        d.empresa || '',
        d.seguradora || '',
        d.ramo || '',
        d.tipo_demanda || '',
        d.situacao || '',
        _exFmtData(d.recebimento),
        _exFmtData(d.proxima_acao),
        d.analista_nome || '',
        d.dados_beneficiario || '',
        d.protocolo || '',
        _exFmtDataHora(d.criado_em),
        d.observacoes || d.obs || '',
        d.status_ultimo || d.ultimo_status || '',
        _exFmtDataHora(d.atualizado_em || d.updated_at || d.modificado_em)
      ]);
    });

    const ws = XLSX.utils.aoa_to_sheet(linhas);
    // Larguras automáticas (aprox.) baseadas no maior valor de cada coluna
    ws['!cols'] = cab.map((_, ci) => {
      let max = String(cab[ci]).length;
      for (let r = 1; r < linhas.length; r++) {
        const v = linhas[r][ci]; if (v == null) continue;
        const len = String(v).length;
        if (len > max) max = len;
      }
      return { wch: Math.min(Math.max(max + 2, 10), 50) };
    });

    // ── Estilo do cabeçalho (precisa do xlsx-js-style) ──────────────
    const headerStyle = {
      font:      { bold: true, color: { rgb: 'FFFFFF' }, sz: 11, name: 'Calibri' },
      fill:      { patternType: 'solid', fgColor: { rgb: '00C9A7' } },
      alignment: { horizontal: 'left', vertical: 'center', wrapText: false },
      border: {
        top:    { style: 'thin', color: { rgb: '009880' } },
        bottom: { style: 'thin', color: { rgb: '009880' } },
        left:   { style: 'thin', color: { rgb: '009880' } },
        right:  { style: 'thin', color: { rgb: '009880' } }
      }
    };
    const cellBorder = {
      top:    { style: 'thin', color: { rgb: 'E5E7EB' } },
      bottom: { style: 'thin', color: { rgb: 'E5E7EB' } },
      left:   { style: 'thin', color: { rgb: 'E5E7EB' } },
      right:  { style: 'thin', color: { rgb: 'E5E7EB' } }
    };
    // Aplica estilo no cabeçalho (linha 0)
    for (let c = 0; c < cab.length; c++) {
      const addr = XLSX.utils.encode_cell({ r: 0, c });
      if (ws[addr]) ws[addr].s = headerStyle;
    }
    // Aplica zebra striping + bordas sutis nas linhas de dados
    for (let r = 1; r < linhas.length; r++) {
      const isOdd = (r % 2) === 0; // r=2 (linha 3 do excel) → zebrada
      for (let c = 0; c < cab.length; c++) {
        const addr = XLSX.utils.encode_cell({ r, c });
        if (!ws[addr]) continue;
        ws[addr].s = {
          font:      { sz: 10, name: 'Calibri', color: { rgb: '24292F' } },
          alignment: { vertical: 'center', wrapText: false },
          border:    cellBorder,
          fill:      isOdd ? { patternType: 'solid', fgColor: { rgb: 'F6F8FA' } } : undefined
        };
      }
    }
    ws['!rows'] = [{ hpt: 24 }];

    ws['!freeze'] = { xSplit: 0, ySplit: 1 };
    ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { c: 0, r: 0 }, e: { c: cab.length - 1, r: linhas.length - 1 } }) };

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Demandas');

    const periodo = (ini || fim) ? `_${ini || 'inicio'}_a_${fim || 'fim'}` : '';
    XLSX.writeFile(wb, `demandas${periodo}.xlsx`);

    if (typeof closeModal === 'function') closeModal('mod-export');
    else document.getElementById('mod-export').classList.remove('show');
    toast(`${dados.length} demanda(s) exportada(s)`);
  } catch(e){
    console.error('Erro export XLSX:', e);
    toast('Erro ao gerar Excel: ' + (e.message || e));
  } finally {
    if (btn){ btn.disabled = false; if (btn.dataset.lbl) btn.innerHTML = btn.dataset.lbl; }
  }
}

function _exFmtData(s){
  if (!s) return '';
  s = String(s).slice(0,10);
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
}
function _exFmtDataHora(s){
  if (!s) return '';
  s = String(s);
  const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/.exec(s);
  if (m) return `${m[3]}/${m[2]}/${m[1]} ${m[4]}`;
  return _exFmtData(s);
}

function dmExportXML(){ dmOpenExportModal(); }
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding
// FIM REAL
