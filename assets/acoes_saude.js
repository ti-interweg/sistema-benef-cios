// ═══════════════════════════════════════════════════════════════
// AÇÕES DE SAÚDE — Enfermagem
// ═══════════════════════════════════════════════════════════════

window.USER_NOME = (typeof USER_NOME !== 'undefined') ? USER_NOME : '';

if (typeof window.toast !== 'function') window.toast = m => { try { alert(m); } catch(e){} };
if (typeof window.esc !== 'function')   window.esc   = s => { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; };
if (typeof window.openModal !== 'function')  window.openModal  = id => { const el=document.getElementById(id); if(el) el.classList.add('show'); };
if (typeof window.closeModal !== 'function') window.closeModal = id => { const el=document.getElementById(id); if(el) el.classList.remove('show'); };
if (typeof window.fmtBRL !== 'function') window.fmtBRL = v => { const n=parseFloat(v)||0; return 'R$ '+n.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); };

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

let _asData = [];
let _asEmpresas = []; // empresas (grupo, nome, porte) puxadas igual ao módulo de demandas

function _asAttr(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// Carrega as empresas (mesma fonte do módulo de Demandas)
async function asCarregarEmpresas(){
  try {
    const r = await fetch('api.php?action=empresas&somente_clientes=1').then(x => x.json());
    _asEmpresas = r.data || [];
  } catch(e){ _asEmpresas = []; }
  asFillGrupos();
  // Liga os eventos de cascata uma única vez
  const g = document.getElementById('mas-grupo');
  const e = document.getElementById('mas-empresa');
  if (g) g.onchange = () => { asUpdateEmpresas(); document.getElementById('mas-empresa').value=''; document.getElementById('mas-porte').value=''; asAplicarMargem(null); };
  if (e) e.onchange = asAutoPorte;
}

function asFillGrupos(){
  const sel = document.getElementById('mas-grupo');
  if (!sel) return;
  const atual = sel.value;
  const grupos = [...new Set(_asEmpresas.map(x => x.grupo))].filter(Boolean).sort();
  sel.innerHTML = '<option value="">Selecione...</option>' +
    grupos.map(g => `<option value="${_asAttr(g)}">${esc(g)}</option>`).join('');
  if (atual) sel.value = atual;
}

function asUpdateEmpresas(){
  const grupo = document.getElementById('mas-grupo').value;
  const sel = document.getElementById('mas-empresa');
  if (!sel) return;
  const list = _asEmpresas.filter(x => x.grupo === grupo);
  sel.innerHTML = '<option value="">Selecione...</option>' +
    list.map(x => `<option value="${_asAttr(x.nome||'')}">${esc(x.nome||'')}</option>`).join('');
}

let _asMargemAtual = null; // margem para ação (R$) do grupo/empresa selecionado

// Preenche o porte e a margem automaticamente a partir da empresa selecionada
function asAutoPorte(){
  const grupo = document.getElementById('mas-grupo').value;
  const nome  = document.getElementById('mas-empresa').value;
  const emp = _asEmpresas.find(x => x.grupo === grupo && (x.nome||'') === nome);
  if (emp && emp.porte) document.getElementById('mas-porte').value = emp.porte;
  asAplicarMargem(grupo);
}

// Mostra a margem para ação do grupo (pela tabela embutida) e atualiza o alerta
function asAplicarMargem(grupo){
  _asMargemAtual = asMargemGrupo(grupo);
  const campo = document.getElementById('mas-margem_acao');
  if (campo) campo.value = (_asMargemAtual != null) ? fmtBRL(_asMargemAtual) : '—';
  _asAtualizaTotal();
}

// Garante que um valor (legado/texto livre) apareça no select mesmo se não estiver na lista
function asEnsureOption(id, val){
  if (!val) return;
  const sel = document.getElementById(id);
  if (!sel) return;
  if (![...sel.options].some(o => o.value === val)){
    const o = document.createElement('option'); o.value = val; o.textContent = val; sel.appendChild(o);
  }
}

// Clique nos cards filtra a tabela pelo status (igual às outras telas)
window.asFiltrarCard = function(status, el){
  document.querySelectorAll('.cards .card').forEach(c => { c.style.outline=''; c.style.outlineOffset=''; });
  if (el){ el.style.outline = '2px solid var(--accent)'; el.style.outlineOffset = '-1px'; }
  const sel = document.getElementById('as-status');
  if (sel) sel.value = status;
  asLoad();
};

document.addEventListener('DOMContentLoaded', () => {
  asLoad();
  asLoadStats();
  asCarregarEmpresas();

  // Atualiza custo total automático no modal
  ['mas-custo_previsto','mas-transporte'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', _asAtualizaTotal);
  });
});

function _asAtualizaTotal(){
  const cp = parseFloat(document.getElementById('mas-custo_previsto').value) || 0;
  const tr = parseFloat(document.getElementById('mas-transporte').value) || 0;
  const total = cp + tr;
  document.getElementById('mas-custo_total_calc').value = fmtBRL(total);

  // Alerta de margem para ação
  const aviso = document.getElementById('mas-margem-aviso');
  if (!aviso) return;
  if (_asMargemAtual == null){ aviso.style.display = 'none'; return; }
  aviso.style.display = 'block';
  if (total > _asMargemAtual){
    const excedente = total - _asMargemAtual;
    aviso.style.background = 'rgba(231,76,60,.12)';
    aviso.style.color = '#c0392b';
    aviso.innerHTML = '⚠ Custo total (' + fmtBRL(total) + ') ultrapassa a margem para ação (' + fmtBRL(_asMargemAtual) + '). Excedente: ' + fmtBRL(excedente) + '.';
  } else {
    const saldo = _asMargemAtual - total;
    aviso.style.background = 'rgba(76,175,80,.12)';
    aviso.style.color = '#2e7d32';
    aviso.innerHTML = '✓ Dentro da margem. Saldo disponível: ' + fmtBRL(saldo) + ' de ' + fmtBRL(_asMargemAtual) + '.';
  }
}

// ─── LOAD ────────────────────────────────────────────────────
async function asLoad(){
  const params = {
    search:    document.getElementById('as-search').value.trim(),
    status:    document.getElementById('as-status').value,
    porte:     document.getElementById('as-porte').value,
    data_de:   document.getElementById('as-data_de').value,
    data_ate:  document.getElementById('as-data_ate').value,
  };
  try {
    const r = await apiEnfGet('listar_acoes_saude', params);
    _asData = r.data || [];
  } catch(e){
    _asData = [];
    toast('Erro ao listar ações de saúde');
  }
  asRender();
  asLoadStats();
}

function asLoadStats(){
  apiEnfGet('stats_acoes_saude').then(r => {
    const d = r.data || {};
    setText('as-tot',          d.total          || 0);
    setText('as-planejamento', d.planejamento   || 0);
    setText('as-agendado',     d.agendado       || 0);
    setText('as-realizado',    d.realizado      || 0);
    setText('as-custo',        fmtBRL(d.custo_total_geral || 0));
  }).catch(()=>{});
}

function setText(id, v){ const el=document.getElementById(id); if (el) el.textContent = v; }

// ─── RENDER ──────────────────────────────────────────────────
// Normaliza nome do grupo: maiúsculas, sem acento, e / - . viram espaço
function asNormGrupo(s){
  return String(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'')
    .toUpperCase().replace(/[\/\-.]/g,' ').replace(/\s+/g,' ').trim();
}

// Margem para ação do grupo: 1º da tabela embutida (planilha), 2º da empresa cadastrada
function asMargemGrupo(grupo){
  if (!grupo) return null;
  const ng = asNormGrupo(grupo);
  if (window.MARGEM_GRUPOS && window.MARGEM_GRUPOS[ng] && window.MARGEM_GRUPOS[ng].margem != null){
    return window.MARGEM_GRUPOS[ng].margem;
  }
  const emp = _asEmpresas.find(x => asNormGrupo(x.grupo) === ng && x.margem_para_acao != null && x.margem_para_acao !== '');
  if (emp){ const m = parseFloat(emp.margem_para_acao); return isNaN(m) ? null : m; }
  return null;
}

function asRender(){
  setText('as-count', _asData.length);
  const tbody = document.getElementById('as-tbody');
  if (!_asData.length){
    tbody.innerHTML = '<tr><td colspan="19" style="text-align:center;padding:30px;color:var(--muted)">Nenhuma ação encontrada</td></tr>';
    return;
  }
  // Soma o custo total por grupo (com base no que está carregado na lista)
  const gastoGrupo = {};
  _asData.forEach(d => { const k = asNormGrupo(d.grupo); gastoGrupo[k] = (gastoGrupo[k]||0) + (parseFloat(d.custo_total)||0); });

  tbody.innerHTML = _asData.map(d => {
    const margem   = asMargemGrupo(d.grupo);
    const restante = (margem != null) ? (margem - (gastoGrupo[asNormGrupo(d.grupo)]||0)) : null;
    const over     = (restante != null && restante < 0);
    return `
    <tr style="${over ? 'background:rgba(231,76,60,.07)' : ''}">
      <td style="font-size:11.5px;color:var(--muted)">#${d.id}</td>
      <td><strong>${esc(d.grupo||'—')}</strong></td>
      <td>${esc(d.empresa||'—')}</td>
      <td style="font-size:11.5px">${esc(d.porte||'—')}</td>
      <td>${esc(d.tipo_servico||'—')}</td>
      <td style="font-size:12px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(d.tema||'')}">${esc(d.tema||'—')}</td>
      <td style="white-space:nowrap">${fmtDate(d.data)}</td>
      <td style="white-space:nowrap;color:var(--muted);font-size:11.5px">${d.horario ? esc(String(d.horario).slice(0,5)) : '—'}</td>
      <td style="font-size:11.5px">${esc(d.responsavel||'—')}</td>
      <td style="font-size:11.5px">${esc(d.local||'—')}</td>
      <td style="font-size:11.5px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(d.endereco||'')}">${esc(d.endereco||'—')}</td>
      <td>${badgeStatus(d.status)}${d.motivo ? ` <span class="material-symbols-rounded" title="${esc(d.motivo)}" style="font-size:14px;vertical-align:middle;color:var(--muted);cursor:help">info</span>` : ''}</td>
      <td style="text-align:right;font-size:12px">${d.custo_previsto != null ? fmtBRL(d.custo_previsto) : '—'}</td>
      <td style="text-align:right;font-size:12px">${d.custo_realizado != null ? fmtBRL(d.custo_realizado) : '—'}</td>
      <td style="text-align:right;font-size:12px">${d.transporte != null ? fmtBRL(d.transporte) : '—'}</td>
      <td style="text-align:right;font-weight:600;${over ? 'color:#c0392b' : 'color:var(--accent)'}">${fmtBRL(d.custo_total)}</td>
      <td style="text-align:right;font-size:12px">${margem != null ? fmtBRL(margem) : '—'}</td>
      <td style="text-align:right;font-weight:700;${restante != null ? (restante < 0 ? 'color:#c0392b' : 'color:#2e7d32') : 'color:var(--muted)'}">${restante != null ? fmtBRL(restante) : '—'}</td>
      <td><button class="btn btn-outline btn-sm" onclick="asEdit(${d.id})">Editar</button></td>
    </tr>`;
  }).join('');
}

function badgeStatus(s){
  const map = {
    'Agendada':   'background:rgba(210,153,34,.12);color:#d29922',
    'Realizada':  'background:rgba(63,185,80,.12);color:#3fb950',
    'Desmarcada': 'background:rgba(150,150,150,.14);color:#888;text-decoration:line-through'
  };
  const css = map[s] || 'background:var(--bg);color:var(--muted)';
  return `<span style="${css};padding:3px 9px;border-radius:12px;font-size:11.5px;font-weight:600">${esc(s||'—')}</span>`;
}

function fmtDate(s){
  if (!s) return '—';
  const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})/);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
}

// ─── MODAL: Nova / Editar ────────────────────────────────────
function asOpenModal(){
  document.getElementById('mas-id').value = '';
  document.getElementById('mas-title').innerHTML = '<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">favorite</span> Nova Ação de Saúde';
  document.getElementById('mas-btn-del').style.display = 'none';
  ['mas-grupo','mas-empresa','mas-tipo_servico','mas-tema','mas-data','mas-horario',
   'mas-responsavel','mas-local','mas-endereco','mas-motivo','mas-custo_previsto','mas-custo_realizado','mas-transporte']
    .forEach(id => { const el=document.getElementById(id); if (el) el.value = ''; });
  document.getElementById('mas-porte').value = '';
  asFillGrupos();
  document.getElementById('mas-empresa').innerHTML = '<option value="">Selecione um grupo primeiro</option>';
  _asMargemAtual = null;
  const cm = document.getElementById('mas-margem_acao'); if (cm) cm.value = '—';
  document.getElementById('mas-status').value = 'Agendada';
  _asAtualizaTotal();
  openModal('mod-as');
}

function asEdit(id){
  const d = _asData.find(x => x.id == id);
  if (!d) return toast('Ação não encontrada');
  document.getElementById('mas-id').value = id;
  document.getElementById('mas-title').innerHTML = `<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">edit</span> Editar Ação #${id}`;
  document.getElementById('mas-btn-del').style.display = '';
  asEnsureOption('mas-grupo', d.grupo);
  document.getElementById('mas-grupo').value         = d.grupo || '';
  asUpdateEmpresas();
  asEnsureOption('mas-empresa', d.empresa);
  document.getElementById('mas-empresa').value       = d.empresa || '';
  document.getElementById('mas-porte').value         = d.porte || '';
  asAplicarMargem(d.grupo || '');
  document.getElementById('mas-tipo_servico').value  = d.tipo_servico || '';
  document.getElementById('mas-tema').value          = d.tema || '';
  document.getElementById('mas-data').value          = (d.data || '').slice(0,10);
  document.getElementById('mas-horario').value       = (d.horario || '').slice(0,5);
  document.getElementById('mas-responsavel').value   = d.responsavel || '';
  _sv('mas-local', d.local || '');
  document.getElementById('mas-endereco').value      = d.endereco || '';
  _sv('mas-motivo', d.motivo || '');
  document.getElementById('mas-status').value        = d.status || 'Agendada';
  document.getElementById('mas-custo_previsto').value= d.custo_previsto != null ? d.custo_previsto : '';
  _sv('mas-custo_realizado', d.custo_realizado != null ? d.custo_realizado : '');
  document.getElementById('mas-transporte').value    = d.transporte != null ? d.transporte : '';
  _asAtualizaTotal();
  openModal('mod-as');
}

// Leitura segura de campos (não quebra se algum id não existir na página)
function _gv(id){ const el = document.getElementById(id); return el ? el.value : ''; }
function _sv(id, val){ const el = document.getElementById(id); if (el) el.value = val; }

async function asSave(){
  const id = _gv('mas-id');
  const body = {
    grupo:          _gv('mas-grupo').trim(),
    empresa:        _gv('mas-empresa').trim(),
    porte:          _gv('mas-porte'),
    tipo_servico:   _gv('mas-tipo_servico').trim(),
    tema:           _gv('mas-tema').trim(),
    data:           _gv('mas-data') || null,
    horario:        _gv('mas-horario') || null,
    responsavel:    _gv('mas-responsavel').trim(),
    local:          _gv('mas-local').trim(),
    endereco:       _gv('mas-endereco').trim(),
    motivo:         _gv('mas-motivo').trim(),
    status:         _gv('mas-status'),
    custo_previsto: _gv('mas-custo_previsto') || null,
    custo_realizado:_gv('mas-custo_realizado') || null,
    transporte:     _gv('mas-transporte') || null,
  };
  if (!body.tipo_servico){ toast('Informe o tipo de serviço'); return; }
  if (!body.data){ toast('Informe a data da ação (obrigatório para aparecer no calendário)'); const dt=document.getElementById('mas-data'); if(dt) dt.focus(); return; }
  if (id) body.id = id;
  try {
    const r = await apiEnfPost(id ? 'editar_acao_saude' : 'criar_acao_saude', body);
    if (!r.ok){ toast(r.msg || 'Erro ao salvar'); return; }
    toast('Ação salva.');
    closeModal('mod-as');
    asLoad();
    asRefreshCal(body.data);
  } catch(e){ toast('Erro de conexão: ' + (e.message||e)); }
}

// Re-renderiza o calendário se a visão Calendário estiver aberta (sem precisar de reload).
// Se receber uma data, pula o calendário para o mês dessa data (assim a ação recém-criada fica visível).
function asRefreshCal(dateStr){
  const cal = document.getElementById('as-view-cal');
  if (!cal || cal.style.display === 'none') return;
  const m = dateStr ? String(dateStr).match(/^(\d{4})-(\d{2})/) : null;
  if (m && typeof window.asCalGoTo === 'function') { window.asCalGoTo(+m[1], +m[2]-1); return; }
  if (typeof window.asCalRender === 'function') window.asCalRender();
}

async function asDelete(){
  const id = _gv('mas-id');
  if (!id) return;
  const ok = await uiConfirm('Esta ação de saúde será removida permanentemente. Deseja continuar?', 'Excluir ação de saúde');
  if (!ok) return;
  try {
    const r = await apiEnfGet('excluir_acao_saude', { id });
    if (!r.ok){ toast(r.msg || 'Erro ao excluir'); return; }
    toast('Ação excluída.');
    closeModal('mod-as');
    asLoad();
    asRefreshCal();
  } catch(e){ toast('Erro de conexão: ' + (e.message||e)); }
}

// ─── Exportar Excel ──────────────────────────────────────────
function asExport(){
  if (!window.XLSX){ toast('Biblioteca XLSX não carregada — recarregue a página'); return; }
  const headers = ['#','Grupo','Empresa','Porte','Tipo de serviço','Tema','Data','Horário','Responsável','Endereço','Status','Custo previsto','Transporte','Custo total','Margem para ação','Margem restante','Analista'];
  const gastoGrupo = {};
  _asData.forEach(d => { const k = asNormGrupo(d.grupo); gastoGrupo[k] = (gastoGrupo[k]||0) + (parseFloat(d.custo_total)||0); });
  const rows = _asData.map(d => {
    const margem = asMargemGrupo(d.grupo);
    const restante = (margem != null) ? (margem - (gastoGrupo[asNormGrupo(d.grupo)]||0)) : null;
    return [
      d.id, d.grupo||'', d.empresa||'', d.porte||'', d.tipo_servico||'', d.tema||'',
      fmtDate(d.data), d.horario ? String(d.horario).slice(0,5) : '', d.responsavel||'', d.endereco||'', d.status||'',
      parseFloat(d.custo_previsto||0), parseFloat(d.transporte||0), parseFloat(d.custo_total||0),
      margem != null ? margem : '', restante != null ? restante : '', d.analista_nome||''
    ];
  });
  const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Ações de Saúde');
  XLSX.writeFile(wb, 'acoes_saude_' + new Date().toISOString().slice(0,10) + '.xlsx');
}

// ─── Cadastro em Lote ────────────────────────────────────────
function asLoteRowHtml(){
  return `<tr>
    <td style="padding:4px;color:var(--muted);font-size:11px" class="asl-num"></td>
    <td style="padding:3px"><input class="asl-grupo" list="asl-grupos-dl" style="width:100%;padding:5px;font-size:11.5px"/></td>
    <td style="padding:3px"><input class="asl-empresa" list="asl-empresas-dl" style="width:100%;padding:5px;font-size:11.5px"/></td>
    <td style="padding:3px"><input class="asl-porte" style="width:100%;padding:5px;font-size:11.5px"/></td>
    <td style="padding:3px"><input class="asl-tipo_servico" style="width:100%;padding:5px;font-size:11.5px"/></td>
    <td style="padding:3px"><input class="asl-tema" style="width:100%;padding:5px;font-size:11.5px"/></td>
    <td style="padding:3px"><input class="asl-data" type="date" style="width:100%;padding:5px;font-size:11.5px"/></td>
    <td style="padding:3px"><input class="asl-horario" type="time" style="width:100%;padding:5px;font-size:11.5px"/></td>
    <td style="padding:3px"><input class="asl-responsavel" style="width:100%;padding:5px;font-size:11.5px"/></td>
    <td style="padding:3px"><input class="asl-endereco" style="width:100%;padding:5px;font-size:11.5px"/></td>
    <td style="padding:3px"><select class="asl-status" style="width:100%;padding:5px;font-size:11.5px"><option>Agendada</option><option>Realizada</option><option>Desmarcada</option></select></td>
    <td style="padding:3px"><input class="asl-custo_previsto" type="number" step="0.01" min="0" style="width:100%;padding:5px;font-size:11.5px"/></td>
    <td style="padding:3px"><input class="asl-transporte" type="number" step="0.01" min="0" style="width:100%;padding:5px;font-size:11.5px"/></td>
    <td style="padding:3px;text-align:center"><button type="button" class="btn btn-outline btn-sm" title="Remover" onclick="this.closest('tr').remove();asLoteRenum()">✕</button></td>
  </tr>`;
}
function asLoteRenum(){
  const rows = document.querySelectorAll('#asl-tbody tr');
  rows.forEach((tr,i)=>{ const n=tr.querySelector('.asl-num'); if(n) n.textContent=(i+1); });
  const c=document.getElementById('asl-count'); if(c) c.textContent=rows.length;
}
function asLoteAddRow(data){
  const tb=document.getElementById('asl-tbody'); if(!tb) return;
  tb.insertAdjacentHTML('beforeend', asLoteRowHtml());
  if (data){
    const tr=tb.lastElementChild;
    const set=(cls,v)=>{ const el=tr.querySelector('.'+cls); if(el && v!=null && v!=='') el.value=v; };
    set('asl-grupo',data.grupo); set('asl-empresa',data.empresa); set('asl-porte',data.porte);
    set('asl-tipo_servico',data.tipo_servico); set('asl-tema',data.tema); set('asl-data',data.data);
    set('asl-horario',data.horario); set('asl-responsavel',data.responsavel); set('asl-endereco',data.endereco);
    set('asl-status',data.status); set('asl-custo_previsto',data.custo_previsto); set('asl-transporte',data.transporte);
  }
  asLoteRenum();
}
function asLoteClear(){ const tb=document.getElementById('asl-tbody'); if(tb) tb.innerHTML=''; asLoteRenum(); }
function asOpenModalLote(){
  asLoteClear();
  for (let i=0;i<5;i++) asLoteAddRow();
  const dlg=document.getElementById('asl-grupos-dl'), dle=document.getElementById('asl-empresas-dl');
  if (dlg) dlg.innerHTML=[...new Set((_asEmpresas||[]).map(e=>e.grupo))].filter(Boolean).sort().map(g=>`<option value="${_asAttr(g)}">`).join('');
  if (dle) dle.innerHTML=[...new Set((_asEmpresas||[]).map(e=>e.nome))].filter(Boolean).sort().map(n=>`<option value="${_asAttr(n)}">`).join('');
  openModal('mod-as-lote');
}
function asLoteParseData(s){
  s=String(s||'').trim(); if(!s) return '';
  let m=s.match(/^(\d{2})\/(\d{2})\/(\d{4})$/); if(m) return `${m[3]}-${m[2]}-${m[1]}`;
  m=s.match(/^(\d{4})-(\d{2})-(\d{2})/); if(m) return s.slice(0,10);
  return s;
}
function asLoteImportFile(input){
  const file=input.files && input.files[0]; if(!file) return;
  if(!window.XLSX){ toast('Biblioteca XLSX não carregada'); input.value=''; return; }
  const reader=new FileReader();
  reader.onload=ev=>{
    try{
      const wb=XLSX.read(new Uint8Array(ev.target.result),{type:'array'});
      const ws=wb.Sheets[wb.SheetNames[0]];
      const rows=XLSX.utils.sheet_to_json(ws,{header:1,defval:''});
      if(!rows.length){ toast('Planilha vazia'); return; }
      const head=rows[0].map(h=>String(h||'').toLowerCase().trim());
      const idx=(...names)=>{ for(const n of names){ const i=head.indexOf(n); if(i>=0) return i; } return -1; };
      const ci={grupo:idx('grupo'),empresa:idx('empresa'),porte:idx('porte'),
        tipo_servico:idx('tipo de serviço','tipo de servico','tipo_servico','tipo'),
        tema:idx('tema'),data:idx('data'),horario:idx('horário','horario'),
        responsavel:idx('responsável','responsavel'),endereco:idx('endereço','endereco'),
        status:idx('status'),custo_previsto:idx('custo previsto','custo_previsto','custo'),transporte:idx('transporte')};
      let added=0;
      for(let i=1;i<rows.length;i++){
        const r=rows[i]; if(!r) continue;
        const g=k=>ci[k]>=0?String(r[ci[k]]||'').trim():'';
        if(!g('tipo_servico') && !g('grupo') && !g('empresa')) continue;
        asLoteAddRow({grupo:g('grupo'),empresa:g('empresa'),porte:g('porte'),tipo_servico:g('tipo_servico'),
          tema:g('tema'),data:asLoteParseData(g('data')),horario:g('horario'),responsavel:g('responsavel'),
          endereco:g('endereco'),status:g('status'),custo_previsto:g('custo_previsto'),transporte:g('transporte')});
        added++;
      }
      toast(added+' linha(s) importada(s).');
    }catch(err){ toast('Erro ao ler planilha: '+err.message); }
    finally{ input.value=''; }
  };
  reader.readAsArrayBuffer(file);
}
function asLoteDownloadTemplate(){
  if(!window.XLSX){ toast('Biblioteca XLSX não carregada'); return; }
  const headers=['Grupo','Empresa','Porte','Tipo de serviço','Tema','Data','Horário','Responsável','Endereço','Status','Custo previsto','Transporte'];
  const ws=XLSX.utils.aoa_to_sheet([headers]);
  const wb=XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb,ws,'Modelo');
  XLSX.writeFile(wb,'modelo_acoes_saude.xlsx');
}
async function asSaveLote(btn){
  const linhas=[];
  document.querySelectorAll('#asl-tbody tr').forEach(tr=>{
    const g=cls=>{ const el=tr.querySelector('.'+cls); return el?String(el.value||'').trim():''; };
    const tipo=g('asl-tipo_servico');
    if(!tipo) return;
    linhas.push({ grupo:g('asl-grupo'), empresa:g('asl-empresa'), porte:g('asl-porte'), tipo_servico:tipo,
      tema:g('asl-tema'), data:g('asl-data')||null, horario:g('asl-horario')||null,
      responsavel:g('asl-responsavel'), endereco:g('asl-endereco'), status:g('asl-status')||'Agendada',
      custo_previsto:g('asl-custo_previsto')||null, transporte:g('asl-transporte')||null });
  });
  if(!linhas.length){ toast('Preencha ao menos uma linha com Tipo de serviço'); return; }
  if(btn) btn.disabled=true;
  try{
    const r=await apiEnfPost('criar_lote_acao_saude',{linhas});
    if(!r.ok){ toast(r.msg||'Erro ao salvar lote'); return; }
    toast((r.inserted!=null?r.inserted:linhas.length)+' ação(ões) cadastrada(s).');
    closeModal('mod-as-lote');
    asLoad();
    asRefreshCal();
  }catch(e){ toast('Erro de conexão: '+(e.message||e)); }
  finally{ if(btn) btn.disabled=false; }
}
