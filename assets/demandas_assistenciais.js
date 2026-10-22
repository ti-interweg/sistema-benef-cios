// ═══════════════════════════════════════════════════════════════
// DEMANDAS ASSISTENCIAIS — Enfermagem
// ═══════════════════════════════════════════════════════════════

window.USER_NOME = (typeof USER_NOME !== 'undefined') ? USER_NOME : '';

// Helpers defensivos
if (typeof window.toast !== 'function') window.toast = m => { try { alert(m); } catch(e){} };
if (typeof window.esc !== 'function')   window.esc   = s => { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; };
if (typeof window.openModal !== 'function')  window.openModal  = id => { const el=document.getElementById(id); if(el) el.classList.add('show'); };
if (typeof window.closeModal !== 'function') window.closeModal = id => { const el=document.getElementById(id); if(el) el.classList.remove('show'); };
if (typeof window.escAttr !== 'function') window.escAttr = s => String(s==null?'':s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
if (typeof window.setVal !== 'function')  window.setVal  = (id,v) => { const el=document.getElementById(id); if(el) el.value = (v==null?'':v); };

const API_ENF = 'api_enfermagem.php';

async function apiEnfGet(action, params={}){
  const url = API_ENF + '?action=' + encodeURIComponent(action) +
              (Object.keys(params).length ? '&' + new URLSearchParams(params).toString() : '');
  const r = await fetch(url);
  return r.json();
}
async function apiEnfPost(action, body={}){
  const r = await fetch(API_ENF + '?action=' + encodeURIComponent(action), {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)
  });
  return r.json();
}

let _daData = [];
let _daEmpresas = [];
let _daSoAtrasadas = false;

// Clique nos cards filtra a tabela (igual às outras telas)
window.daFiltrarCard = function(tipo, el){
  document.querySelectorAll('.cards .card').forEach(c => { c.style.outline=''; c.style.outlineOffset=''; });
  if (el){ el.style.outline = '2px solid var(--accent)'; el.style.outlineOffset = '-1px'; }
  const sel = document.getElementById('da-situacao');
  _daSoAtrasadas = false;
  if      (tipo === 'total')     { sel.value = ''; }
  else if (tipo === 'iniciar')   { sel.value = 'A iniciar'; }
  else if (tipo === 'andamento') { sel.value = 'Em andamento'; }
  else if (tipo === 'concluido') { sel.value = 'Concluído'; }
  else if (tipo === 'atrasadas') { sel.value = ''; _daSoAtrasadas = true; }
  daLoad();
};

document.addEventListener('DOMContentLoaded', () => {
  daLoadEnfermeiras();
  daLoad();
  daLoadStats();
  daLoadEmpresas();
});

// ─── Mapeamento canônico das enfermeiras (nome único → variantes normalizadas) ───
// Tudo em minúsculo e sem acento; daNorm() abaixo cuida da normalização das strings vindas do banco.
const DA_ENF_CANONICAL = {
  'Isabella': ['isabela','isabella','enfermeira isabela','enfermeira isabella','enf isabela','enf isabella','enf. isabela','enf. isabella'],
  'Talita':   ['talita','enfermeira talita','enf talita','enf. talita'],
  'Jéssica':  ['jessica','enfermeira jessica','enf jessica','enf. jessica'],
  'Lorena':   ['lorena','enfermeira lorena','enf lorena','enf. lorena'],
  'Carolina': ['carolina','enfermeira carolina','enf carolina','enf. carolina']
};
function daNorm(s){ return (s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').trim(); }
function daCanonicalEnf(nome){
  if (!nome) return '';
  const n = daNorm(nome);
  for (const canon in DA_ENF_CANONICAL){
    if (DA_ENF_CANONICAL[canon].includes(n)) return canon;
  }
  return nome; // não é enfermeira conhecida → devolve original
}
function daIsEnf(nome){
  if (!nome) return false;
  const n = daNorm(nome);
  for (const canon in DA_ENF_CANONICAL){
    if (DA_ENF_CANONICAL[canon].includes(n)) return true;
  }
  return false;
}

// ─── Popula o filtro de enfermeiras (no topo da página) ───
// Mostra um nome único por enfermeira (canônico), agrupando todas as variantes.
function daLoadEnfermeiras(){
  const sel = document.getElementById('da-enfermeira');
  if (!sel) return;
  const enfs = Object.keys(DA_ENF_CANONICAL).sort((a,b) => a.localeCompare(b, 'pt'));
  const cur = sel.value;
  sel.innerHTML = '<option value="">Todas as enfermeiras</option>' +
    enfs.map(n => `<option value="${escAttr(n)}">${esc(n)}</option>`).join('');
  if (cur && [...sel.options].some(o => o.value === cur)) sel.value = cur;
}

// ─── BASE DE EMPRESAS (dropdowns Grupo → Empresa, igual Demandas) ───
async function daLoadEmpresas(){
  try {
    const r = await fetch('api.php?action=empresas&somente_clientes=1').then(x => x.json());
    _daEmpresas = (r && r.data) ? r.data : [];
  } catch(e){ _daEmpresas = []; }
  // Grupo: lista distinta
  const grupos = [...new Set(_daEmpresas.map(e => e.grupo))].filter(Boolean).sort((a,b)=>a.localeCompare(b,'pt'));
  const gsel = document.getElementById('mda-grupo');
  if (gsel) gsel.innerHTML = '<option value="">Selecione...</option>' +
    grupos.map(g => `<option value="${escAttr(g)}">${esc(g)}</option>`).join('');
  const esel = document.getElementById('mda-empresa');
  if (esel) esel.innerHTML = '<option value="">Selecione um grupo primeiro</option>';
  daPopulaSeguradoras();
}

// Empresas do grupo selecionado
function daUpdateEmpresas(){
  const grupo = document.getElementById('mda-grupo').value;
  const sel = document.getElementById('mda-empresa');
  if (!sel) return;
  const list = _daEmpresas.filter(e => e.grupo === grupo);
  sel.innerHTML = '<option value="">Selecione...</option>' +
    list.map(e => `<option value="${escAttr(e.nome||'')}">${esc(e.nome||'')}</option>`).join('');
}

// Ao escolher a empresa, preenche a Seguradora (e Ramo) se estiverem vazios
function daEmpresaChange(){
  const grupo = document.getElementById('mda-grupo').value;
  const nome  = document.getElementById('mda-empresa').value;
  const rec = _daEmpresas.find(e => e.grupo === grupo && (e.nome||'') === nome)
           || _daEmpresas.find(e => (e.nome||'') === nome);
  if (!rec) return;
  const segEl = document.getElementById('mda-seguradora');
  if (segEl && !segEl.value && rec.seguradora) daSetSeguradora(rec.seguradora);
  const ramoEl = document.getElementById('mda-ramo');
  if (ramoEl && !ramoEl.value && rec.ramo && [...ramoEl.options].some(o => o.value === rec.ramo)) {
    ramoEl.value = rec.ramo;
  }
}

// Monta o dropdown de Seguradora com os valores distintos da base
function daPopulaSeguradoras(){
  const sel = document.getElementById('mda-seguradora');
  if (!sel) return;
  const set = new Set();
  _daEmpresas.forEach(e => { const s = (e.seguradora || '').trim(); if (s) set.add(s); });
  const arr = [...set].sort((a, b) => a.localeCompare(b, 'pt'));
  sel.innerHTML = '<option value="">— Selecione —</option>' + arr.map(s => `<option value="${escAttr(s)}">${esc(s)}</option>`).join('');
}

// Seleciona uma seguradora; se não estiver na lista (ex: demanda antiga), cria opção
function daSetSeguradora(val){
  const sel = document.getElementById('mda-seguradora');
  if (!sel) return;
  val = (val || '').trim();
  if (!val){ sel.value = ''; return; }
  if (![...sel.options].some(o => o.value === val)){
    const o = document.createElement('option');
    o.value = val; o.textContent = val; o.dataset.legacy = '1';
    sel.appendChild(o);
  }
  sel.value = val;
}

// Seleciona Grupo + Empresa (com fallback para dados antigos fora da base)
function daSetGrupoEmpresa(grupo, empresa){
  const gsel = document.getElementById('mda-grupo');
  if (gsel){
    if (grupo && ![...gsel.options].some(o => o.value === grupo)){
      const o = document.createElement('option'); o.value = grupo; o.textContent = grupo; o.dataset.legacy = '1'; gsel.appendChild(o);
    }
    gsel.value = grupo || '';
  }
  daUpdateEmpresas();
  const esel = document.getElementById('mda-empresa');
  if (esel){
    if (empresa && ![...esel.options].some(o => o.value === empresa)){
      const o = document.createElement('option'); o.value = empresa; o.textContent = empresa; o.dataset.legacy = '1'; esel.appendChild(o);
    }
    esel.value = empresa || '';
  }
}

// Remove opções "legacy" dos selects
function daLimpaLegacy(){
  ['mda-grupo','mda-empresa','mda-seguradora'].forEach(id => {
    const sel = document.getElementById(id);
    if (!sel) return;
    [...sel.options].forEach(o => { if (o.dataset && o.dataset.legacy) o.remove(); });
  });
}

// ─── LOAD ────────────────────────────────────────────────────
async function daLoad(){
  const enfEl = document.getElementById('da-enfermeira');
  const enfFiltro = enfEl ? enfEl.value : ''; // nome canônico ('' = todas)
  const params = {
    search:      document.getElementById('da-search').value.trim(),
    situacao:    document.getElementById('da-situacao').value,
    tipo_demanda:document.getElementById('da-tipo').value,
    ramo:        document.getElementById('da-ramo').value,
    // analista NÃO vai no backend — filtro de enfermeira é client-side (matching canônico)
  };
  try {
    const r = await apiEnfGet('listar_assistenciais', params);
    _daData = r.data || [];
  } catch(e){
    _daData = [];
    toast('Erro ao listar demandas assistenciais');
  }
  // Filtro client-side de enfermeira (canônico bate com qualquer variante de nome)
  if (enfFiltro){
    _daData = _daData.filter(d => daCanonicalEnf(d.analista_nome) === enfFiltro);
  }
  // Card "Atrasadas": próxima ação vencida e ainda não concluída
  if (_daSoAtrasadas){
    const hoje = new Date().toISOString().slice(0,10);
    _daData = _daData.filter(d => d.proxima_acao && d.proxima_acao < hoje && d.situacao !== 'Concluído');
  }
  daRender();
  daLoadStats();
}

function daLoadStats(){
  apiEnfGet('stats_assistenciais').then(r => {
    const d = r.data || {};
    setText('da-tot',       d.total     || 0);
    setText('da-iniciar',   d.iniciar   || 0);
    setText('da-andamento', d.andamento || 0);
    setText('da-concluido', d.concluido || 0);
    setText('da-atrasadas', d.atrasadas || 0);
  }).catch(()=>{});
}

function setText(id, v){ const el=document.getElementById(id); if (el) el.textContent = v; }

// ─── RENDER ──────────────────────────────────────────────────
function daRender(){
  setText('da-count', _daData.length);
  const tbody = document.getElementById('da-tbody');
  if (!_daData.length){
    tbody.innerHTML = '<tr><td colspan="13" style="text-align:center;padding:30px;color:var(--muted)">Nenhuma demanda encontrada</td></tr>';
    return;
  }
  tbody.innerHTML = _daData.map(d => {
    const enfDisp = daCanonicalEnf(d.analista_nome);
    const enfCss  = daIsEnf(d.analista_nome) ? 'background:rgba(0,201,167,.12);color:#0a8a8d;padding:3px 9px;border-radius:12px;font-size:11.5px;font-weight:600' : 'color:var(--muted)';
    return `
    <tr>
      <td style="font-size:11.5px;color:var(--muted)">#${d.id}</td>
      <td><strong>${esc(d.grupo||'—')}</strong><br><span style="color:var(--muted);font-size:11.5px">${esc(d.empresa||'—')}</span></td>
      <td>${esc(d.seguradora||'—')}</td>
      <td>${badgeRamo(d.ramo)}</td>
      <td>${esc(d.tipo_demanda||'—')}</td>
      <td>${badgeSit(d.situacao)}</td>
      <td>${fmtDate(d.recebimento)}</td>
      <td>${fmtDate(d.proxima_acao)}</td>
      <td style="${d.data_conclusao ? 'color:var(--success);font-weight:600' : 'color:var(--muted)'}">${fmtDate(d.data_conclusao)}</td>
      <td><span style="${enfCss}">${esc(enfDisp || '—')}</span></td>
      <td style="font-size:11.5px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(d.dados_beneficiario||'')}">${esc((d.dados_beneficiario||'').slice(0, 60))}</td>
      <td style="font-size:11.5px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(d.descricao_demanda||'')}">${esc((d.descricao_demanda||'').slice(0, 80))}</td>
      <td><button class="btn btn-outline btn-sm" onclick="daEdit(${d.id})">Editar</button></td>
    </tr>`;
  }).join('');
}

function badgeSit(s){
  const map = {
    'A iniciar':    'background:rgba(88,166,255,.12);color:#58a6ff',
    'Em andamento': 'background:rgba(210,153,34,.12);color:#d29922',
    'Concluído':    'background:rgba(63,185,80,.12);color:#3fb950'
  };
  const css = map[s] || 'background:var(--bg);color:var(--muted)';
  return `<span style="${css};padding:3px 9px;border-radius:12px;font-size:11.5px;font-weight:600">${esc(s||'—')}</span>`;
}
function badgeRamo(r){
  if (!r) return '<span style="color:var(--muted)">—</span>';
  const cor = r.toLowerCase().startsWith('saúde') ? '#00c9a7' : '#bc8cff';
  return `<span style="background:${cor}22;color:${cor};padding:3px 9px;border-radius:12px;font-size:11.5px;font-weight:600">${esc(r)}</span>`;
}
function fmtDate(s){
  if (!s) return '—';
  const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})/);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
}

// ─── MODAL ───────────────────────────────────────────────────
function daOpenModal(){
  document.getElementById('mda-id').value = '';
  document.getElementById('mda-title').innerHTML = '<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">medical_services</span> Nova Demanda Assistencial';
  document.getElementById('mda-btn-del').style.display = 'none';
  daLimpaLegacy();
  ['mda-grupo','mda-empresa','mda-seguradora','mda-recebimento','mda-proxima_acao','mda-data_conclusao','mda-dados','mda-descricao']
    .forEach(id => { const el=document.getElementById(id); if (el) el.value = ''; });
  const _es = document.getElementById('mda-empresa');
  if (_es) _es.innerHTML = '<option value="">Selecione um grupo primeiro</option>';
  document.getElementById('mda-ramo').value = '';
  document.getElementById('mda-tipo').value = 'Outros';
  document.getElementById('mda-situacao').value = 'A iniciar';
  openModal('mod-da');
}

function daEdit(id){
  const d = _daData.find(x => x.id == id);
  if (!d) return toast('Demanda não encontrada');
  document.getElementById('mda-id').value = id;
  document.getElementById('mda-title').innerHTML = `<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">edit</span> Editar Demanda #${id}`;
  document.getElementById('mda-btn-del').style.display = '';
  // Grupo → Empresa em cascata (igual Demandas), com fallback para dados antigos
  daLimpaLegacy();
  daSetGrupoEmpresa(d.grupo || '', d.empresa || '');
  daSetSeguradora(d.seguradora || '');
  document.getElementById('mda-ramo').value        = d.ramo || '';
  document.getElementById('mda-tipo').value        = d.tipo_demanda || 'Outros';
  document.getElementById('mda-situacao').value    = d.situacao || 'A iniciar';
  document.getElementById('mda-recebimento').value = (d.recebimento || '').slice(0,10);
  document.getElementById('mda-proxima_acao').value= (d.proxima_acao || '').slice(0,10);
  document.getElementById('mda-data_conclusao').value = (d.data_conclusao || '').slice(0,10);
  document.getElementById('mda-dados').value       = d.dados_beneficiario || '';
  document.getElementById('mda-descricao').value   = d.descricao_demanda || '';
  openModal('mod-da');
}

async function daSave(){
  const id = document.getElementById('mda-id').value;
  const body = {
    grupo:              document.getElementById('mda-grupo').value.trim(),
    empresa:            document.getElementById('mda-empresa').value.trim(),
    seguradora:         document.getElementById('mda-seguradora').value.trim(),
    ramo:               document.getElementById('mda-ramo').value,
    tipo_demanda:       document.getElementById('mda-tipo').value,
    situacao:           document.getElementById('mda-situacao').value,
    recebimento:        document.getElementById('mda-recebimento').value || null,
    proxima_acao:       document.getElementById('mda-proxima_acao').value || null,
    data_conclusao:     document.getElementById('mda-data_conclusao').value || null,
    dados_beneficiario: document.getElementById('mda-dados').value.trim(),
    descricao_demanda:  document.getElementById('mda-descricao').value.trim(),
    analista_nome:      window.USER_NOME,
  };
  if (id) body.id = id;

  const r = await apiEnfPost(id ? 'editar_assistencial' : 'criar_assistencial', body);
  if (r.ok){
    toast('Demanda salva!');
    closeModal('mod-da');
    daLoad();
  } else {
    toast(r.msg || 'Erro ao salvar');
  }
}

async function daDelete(){
  const id = document.getElementById('mda-id').value;
  if (!id || !confirm('Excluir essa demanda?')) return;
  const r = await apiEnfGet('excluir_assistencial', { id });
  if (r.ok){
    toast('Demanda excluída');
    closeModal('mod-da');
    daLoad();
  } else {
    toast(r.msg || 'Erro ao excluir');
  }
}

// ─── EXPORTAR ────────────────────────────────────────────────
function daExport(){
  if (!_daData.length) return toast('Nada a exportar');
  const headers = ['#','Grupo','Empresa','Seguradora','Ramo','Tipo','Situação','Recebimento','Próxima ação','Conclusão','Enfermeira','Beneficiário','Descrição'];
  const rows = _daData.map(d => [
    d.id, d.grupo||'', d.empresa||'', d.seguradora||'', d.ramo||'',
    d.tipo_demanda||'', d.situacao||'',
    fmtDate(d.recebimento), fmtDate(d.proxima_acao), fmtDate(d.data_conclusao),
    daCanonicalEnf(d.analista_nome) || '',
    d.dados_beneficiario||'', d.descricao_demanda||''
  ]);
  if (typeof XLSX === 'undefined') return toast('Biblioteca XLSX não carregada');
  const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Demandas Assistenciais');
  XLSX.writeFile(wb, `demandas_assistenciais_${new Date().toISOString().slice(0,10)}.xlsx`);
}

// ═══════════════════════════════════════════════════════════════
// CADASTRO EM LOTE
// ═══════════════════════════════════════════════════════════════
const DA_TIPOS = ['Autorização','Busca de Rede','Reembolso','Agendamento Psicologia','Agendamento','Orientação','Transferência Hospitalar','Transcrição de Guias','Check up','Veracidade de atestado','Cobrança indevida','Outros'];
const DA_SITUACOES = ['A iniciar','Em andamento','Concluído'];
let _daAnalistas = [];

const DAL_COLS = [
  {key:'grupo',              aliases:['grupo','grupo / empresa','grupo/empresa']},
  {key:'empresa',            aliases:['empresa','razao social','razão social','razao_social']},
  {key:'seguradora',         aliases:['seguradora','operadora','operadora atual']},
  {key:'ramo',               aliases:['ramo','tipo plano','tipo de plano']},
  {key:'tipo_demanda',       aliases:['tipo','tipo demanda','tipo_demanda','tipo de demanda']},
  {key:'situacao',           aliases:['situacao','situação','status']},
  {key:'recebimento',        aliases:['recebimento','data recebimento','data de recebimento','dt recebimento']},
  {key:'proxima_acao',       aliases:['proxima_acao','próxima ação','proxima acao','prox acao','prox. ação','prox. acao']},
  {key:'data_conclusao',     aliases:['data_conclusao','conclusao','conclusão','data conclusao','data conclusão','data de conclusão']},
  {key:'dados_beneficiario', aliases:['beneficiario','beneficiário','dados beneficiario','dados_beneficiario','dados do beneficiario','dados do beneficiário']},
  {key:'descricao_demanda',  aliases:['descricao','descrição','descricao_demanda','descricao demanda','descrição da demanda']},
  {key:'analista_nome',      aliases:['analista','analista nome','analista_nome','responsavel','responsável']},
];

function daNormHeader(s){
  return String(s||'').trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/\s+/g,' ');
}
function daParseDate(v){
  if (v==null || v==='') return '';
  if (typeof v==='number' && typeof XLSX!=='undefined' && XLSX.SSF){
    const o = XLSX.SSF.parse_date_code(v);
    if (o) return `${o.y}-${String(o.m).padStart(2,'0')}-${String(o.d).padStart(2,'0')}`;
  }
  const s = String(v).trim();
  let m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
  if (m) return `${m[1]}-${m[2].padStart(2,'0')}-${m[3].padStart(2,'0')}`;
  m = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
  if (m){ let y=m[3]; if (y.length===2) y='20'+y; return `${y}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`; }
  return '';
}

function daLoteBuildRow(i){
  const tr = document.createElement('tr');
  tr.className = 'dal-row';
  tr.innerHTML = `
    <td style="padding:6px 7px;color:var(--muted);font-weight:600">${i}</td>
    <td style="padding:3px"><input type="text" class="dal-grupo" placeholder="Grupo" style="padding:5px 7px;font-size:11.5px;width:100%" list="dal-grupos-dl"/></td>
    <td style="padding:3px"><input type="text" class="dal-empresa" placeholder="Empresa" style="padding:5px 7px;font-size:11.5px;width:100%" list="dal-empresas-dl"/></td>
    <td style="padding:3px"><input type="text" class="dal-seg" placeholder="Seguradora" style="padding:5px 7px;font-size:11.5px;width:100%" list="dal-segs-dl"/></td>
    <td style="padding:3px"><select class="dal-ramo" style="padding:5px 7px;font-size:11.5px;width:100%"><option value="">—</option><option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option><option>Vida</option><option>Enfermagem</option></select></td>
    <td style="padding:3px"><select class="dal-tipo" style="padding:5px 7px;font-size:11.5px"><option value="">—</option>${DA_TIPOS.map(t=>`<option>${t}</option>`).join('')}</select></td>
    <td style="padding:3px"><select class="dal-sit" style="padding:5px 7px;font-size:11.5px"><option value="">— (padrão)</option>${DA_SITUACOES.map(s=>`<option>${s}</option>`).join('')}</select></td>
    <td style="padding:3px"><input type="date" class="dal-receb" style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px"><input type="date" class="dal-prox" style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px"><input type="date" class="dal-conc" style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px"><input type="text" class="dal-benef" placeholder="Nome, CPF, nasc..." style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px"><input type="text" class="dal-desc" placeholder="Descrição..." style="padding:5px 7px;font-size:11.5px"/></td>
    <td style="padding:3px"><input type="text" class="dal-anal" placeholder="Analista" style="padding:5px 7px;font-size:11.5px;width:100%" list="dal-analistas-dl"/></td>
    <td style="padding:3px;text-align:center"><button class="btn btn-danger btn-sm" type="button" onclick="daLoteRemoveRow(this)" title="Remover"><span class="material-symbols-rounded" style="font-size:14px">close</span></button></td>`;
  tr.querySelectorAll('input,select').forEach(el => el.addEventListener('input', daLoteUpdateCount));
  return tr;
}

function daLoteAddRow(){
  const tb = document.getElementById('dal-tbody');
  if (tb.children.length >= 50) { alert('Máximo de 50 linhas por lote.'); return; }
  tb.appendChild(daLoteBuildRow(tb.children.length + 1));
  daLoteUpdateCount();
}
function daLoteRemoveRow(btn){
  btn.closest('tr').remove();
  document.querySelectorAll('#dal-tbody tr').forEach((r, idx) => { r.firstElementChild.textContent = (idx+1); });
  daLoteUpdateCount();
}
function daLoteClear(){
  if (!confirm('Limpar todas as linhas?')) return;
  const tb = document.getElementById('dal-tbody');
  tb.innerHTML = '';
  for (let i=1; i<=20; i++) tb.appendChild(daLoteBuildRow(i));
  daLoteUpdateCount();
}
function daLoteUpdateCount(){
  let n = 0;
  document.querySelectorAll('#dal-tbody tr').forEach(r => {
    const tipo = r.querySelector('.dal-tipo').value;
    const benef= r.querySelector('.dal-benef').value.trim();
    const desc = r.querySelector('.dal-desc').value.trim();
    const grupo= r.querySelector('.dal-grupo').value.trim();
    const emp  = r.querySelector('.dal-empresa').value.trim();
    if (tipo||benef||desc||grupo||emp) n++;
  });
  const el = document.getElementById('dal-count');
  if (el) el.textContent = n;
}

async function daOpenModalLote(){
  // garante base de empresas + analistas
  if (!_daEmpresas.length) await daLoadEmpresas();
  if (!_daAnalistas.length){
    try { const r = await fetch('api.php?action=analistas').then(x=>x.json()); _daAnalistas = (r&&r.data)?r.data:[]; }
    catch(e){ _daAnalistas = []; }
  }
  const dlG = document.getElementById('dal-grupos-dl');
  const dlE = document.getElementById('dal-empresas-dl');
  const dlS = document.getElementById('dal-segs-dl');
  const dlA = document.getElementById('dal-analistas-dl');
  if (dlG) dlG.innerHTML = [...new Set(_daEmpresas.map(e=>e.grupo))].filter(Boolean).sort().map(g=>`<option value="${escAttr(g)}">`).join('');
  if (dlE) dlE.innerHTML = [...new Set(_daEmpresas.map(e=>e.nome))].filter(Boolean).sort().map(n=>`<option value="${escAttr(n)}">`).join('');
  if (dlS) dlS.innerHTML = [...new Set(_daEmpresas.map(e=>e.seguradora))].filter(Boolean).sort().map(s=>`<option value="${escAttr(s)}">`).join('');
  if (dlA) dlA.innerHTML = (_daAnalistas||[]).map(a=>`<option value="${escAttr(a.nome)}">`).join('');

  const tb = document.getElementById('dal-tbody');
  tb.innerHTML = '';
  for (let i=1; i<=20; i++) tb.appendChild(daLoteBuildRow(i));
  daLoteUpdateCount();
  openModal('mod-da-lote');
}

function daLoteDownloadTemplate(){
  if (typeof XLSX === 'undefined'){ alert('Biblioteca de planilhas ainda não carregou.'); return; }
  const header = ['Grupo','Empresa','Seguradora','Ramo','Tipo','Situação','Recebimento','Próx. Ação','Conclusão','Beneficiário','Descrição','Analista'];
  const exemplo = ['HINOVA','POOLTRACK','UNIMED','Saúde','Autorização','A iniciar','2026-05-21','2026-05-25','','João Silva - CPF 000.000.000-00','Autorização de exame','Talita'];
  const vazia = header.map(()=>'');
  const linhas = [header, exemplo];
  for (let i=0; i<20; i++) linhas.push(vazia.slice());
  const ws = XLSX.utils.aoa_to_sheet(linhas);
  ws['!cols'] = [{wch:18},{wch:30},{wch:16},{wch:12},{wch:20},{wch:14},{wch:14},{wch:14},{wch:14},{wch:38},{wch:35},{wch:16}];
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Demandas Assistenciais');
  XLSX.writeFile(wb, 'modelo_demandas_assistenciais.xlsx');
}

function daLoteImportFile(input){
  const file = input.files && input.files[0];
  if (!file) return;
  if (typeof XLSX === 'undefined'){ alert('Biblioteca de planilhas ainda não carregou.'); input.value=''; return; }
  const reader = new FileReader();
  reader.onload = e => {
    try {
      const data = new Uint8Array(e.target.result);
      const wb = XLSX.read(data, {type:'array'});
      const ws = wb.Sheets[wb.SheetNames[0]];
      const rows = XLSX.utils.sheet_to_json(ws, {header:1, defval:'', raw:true});
      if (!rows.length){ alert('Planilha vazia.'); input.value=''; return; }
      const header = rows[0].map(daNormHeader);
      const colIdx = {};
      DAL_COLS.forEach(c => { colIdx[c.key] = header.findIndex(h => c.aliases.includes(h)); });
      const parsed = [];
      for (let i=1; i<rows.length; i++){
        const r = rows[i]; if (!r) continue;
        const getStr = k => colIdx[k]>=0 ? String(r[colIdx[k]]||'').trim() : '';
        const getDate = k => colIdx[k]>=0 ? daParseDate(r[colIdx[k]]) : '';
        let _g = getStr('grupo'), _e = getStr('empresa');
        if (_g && !_e && _g.includes(' / ')){ const p=_g.split(' / '); _g=p[0].trim(); _e=p.slice(1).join(' / ').trim(); }
        const obj = {
          grupo:_g, empresa:_e, seguradora:getStr('seguradora'), ramo:getStr('ramo'),
          tipo_demanda:getStr('tipo_demanda'), situacao:getStr('situacao'),
          recebimento:getDate('recebimento'), proxima_acao:getDate('proxima_acao'), data_conclusao:getDate('data_conclusao'),
          dados_beneficiario:getStr('dados_beneficiario'), descricao_demanda:getStr('descricao_demanda'),
          analista_nome:getStr('analista_nome')
        };
        if (!Object.values(obj).some(v => v)) continue;
        if (!obj.grupo && !obj.empresa && !obj.tipo_demanda && !obj.dados_beneficiario && !obj.descricao_demanda) continue;
        parsed.push(obj);
      }
      if (!parsed.length){ alert('Nenhuma linha com dados encontrada.'); input.value=''; return; }
      parsed.forEach(p => {
        if (p.tipo_demanda){ const m = DA_TIPOS.find(t => daNormHeader(t)===daNormHeader(p.tipo_demanda)); if (m) p.tipo_demanda=m; }
        if (p.situacao){ const m = DA_SITUACOES.find(s => daNormHeader(s)===daNormHeader(p.situacao)); if (m) p.situacao=m; }
      });
      const tb = document.getElementById('dal-tbody');
      tb.innerHTML = '';
      parsed.forEach((p, i) => {
        const tr = daLoteBuildRow(i+1);
        tr.querySelector('.dal-grupo').value   = p.grupo || '';
        tr.querySelector('.dal-empresa').value = p.empresa || '';
        tr.querySelector('.dal-seg').value     = p.seguradora || '';
        tr.querySelector('.dal-ramo').value    = p.ramo || '';
        tr.querySelector('.dal-tipo').value    = p.tipo_demanda || '';
        tr.querySelector('.dal-sit').value     = p.situacao || '';
        tr.querySelector('.dal-receb').value   = p.recebimento || '';
        tr.querySelector('.dal-prox').value    = p.proxima_acao || '';
        tr.querySelector('.dal-conc').value    = p.data_conclusao || '';
        tr.querySelector('.dal-benef').value   = p.dados_beneficiario || '';
        tr.querySelector('.dal-desc').value    = p.descricao_demanda || '';
        tr.querySelector('.dal-anal').value    = p.analista_nome || '';
        tb.appendChild(tr);
      });
      daLoteUpdateCount();
      toast(`${parsed.length} linha(s) importada(s) de "${file.name}"`);
    } catch(err){ console.error(err); alert('Erro ao ler a planilha: ' + err.message); }
    finally { input.value=''; }
  };
  reader.onerror = () => { alert('Erro ao ler arquivo.'); input.value=''; };
  reader.readAsArrayBuffer(file);
}

async function daSaveLote(btn){
  const linhas = [];
  let invalida = false;
  document.querySelectorAll('#dal-tbody tr').forEach(r => {
    const grupo = r.querySelector('.dal-grupo').value.trim();
    const empresa= r.querySelector('.dal-empresa').value.trim();
    const seg   = r.querySelector('.dal-seg').value.trim();
    const ramo  = r.querySelector('.dal-ramo').value;
    const tipo  = r.querySelector('.dal-tipo').value;
    const sit   = r.querySelector('.dal-sit').value;
    const receb = r.querySelector('.dal-receb').value || null;
    const prox  = r.querySelector('.dal-prox').value || null;
    const conc  = r.querySelector('.dal-conc').value || null;
    const benef = r.querySelector('.dal-benef').value.trim();
    const desc  = r.querySelector('.dal-desc').value.trim();
    const anal  = r.querySelector('.dal-anal').value.trim();
    const vazia = !grupo && !empresa && !seg && !ramo && !tipo && !sit && !receb && !prox && !conc && !benef && !desc && !anal;
    if (vazia) return;
    if (!tipo){ invalida = true; return; }
    linhas.push({
      grupo, empresa, seguradora: seg, ramo,
      tipo_demanda: tipo, situacao: sit || 'A iniciar',
      recebimento: receb, proxima_acao: prox, data_conclusao: conc,
      dados_beneficiario: benef, descricao_demanda: desc,
      analista_nome: anal || window.USER_NOME
    });
  });
  if (!linhas.length){ alert('Preencha pelo menos uma linha.'); return; }
  if (invalida){ alert('Toda linha preenchida precisa de um Tipo de demanda.'); return; }

  if (btn) btn.disabled = true;
  try {
    const r = await apiEnfPost('criar_lote_assistencial', { linhas });
    if (r && r.ok){
      toast(`${r.inserted || linhas.length} demanda(s) criada(s)!`);
      closeModal('mod-da-lote');
      daLoad();
    } else {
      alert((r && r.msg) || 'Erro ao salvar o lote.');
    }
  } catch(e){
    alert('Falha ao salvar o lote: ' + e.message);
  } finally {
    if (btn) btn.disabled = false;
  }
}