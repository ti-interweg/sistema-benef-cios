/**
 * Controle de Cotação — frontend.
 * Lista, cria, edita e exclui cotações em acompanhamento (do recebimento até o fechamento ou declínio).
 */

let CC_PAGE = 1;
const CC_PER = 50;
let ccData = [];
let _ccAnalistas = [];
let _ccCardFilter = null; // null = todos, ou "estudo" / "impl" / "declinada"

// ─── STUBS WINDOW.* (blindados contra truncamento) ───
window.ccOpenModal    = window.ccOpenModal    || function(){ alert('Carregando...'); };
window.ccLoad         = window.ccLoad         || function(){};
window.ccEdit         = window.ccEdit         || function(){ alert('Carregando...'); };
window.ccSave         = window.ccSave         || function(){};
window.ccDelete       = window.ccDelete       || function(){};
window.ccExportXLSX   = window.ccExportXLSX   || function(){ alert('Carregando...'); };
setTimeout(function(){
  if (typeof ccOpenModal  === 'function') window.ccOpenModal  = ccOpenModal;
  if (typeof ccLoad       === 'function') window.ccLoad       = ccLoad;
  if (typeof ccEdit       === 'function') window.ccEdit       = ccEdit;
  if (typeof ccSave       === 'function') window.ccSave       = ccSave;
  if (typeof ccDelete     === 'function') window.ccDelete     = ccDelete;
  if (typeof ccExportXLSX === 'function') window.ccExportXLSX = ccExportXLSX;
}, 0);

document.addEventListener('DOMContentLoaded', async () => {
  await ccLoad();
  await ccCarregarAnalistas();
});

// ── Helpers ──────
if (typeof window.fmtDate !== 'function') window.fmtDate = s => { if(!s) return '—'; const m=String(s).match(/^(\d{4})-(\d{2})-(\d{2})/); return m?`${m[3]}/${m[2]}/${m[1]}`:String(s); };
if (typeof window.fmtBRL  !== 'function') window.fmtBRL  = v => { const n=Number(String(v).replace(',','.'))||0; return 'R$ '+n.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); };
function setVal(id, v) { const el=document.getElementById(id); if(el) el.value = v == null ? '' : v; }
function val(id)        { const el=document.getElementById(id); return el ? el.value : ''; }
function setText(id, t) { const el=document.getElementById(id); if(el) el.textContent = t; }
function brToNum(s) {
  if (s == null || s === '') return null;
  // Limpa: remove R$, espacos, pontos de milhar; aceita virgula OU ponto como decimal
  let str = String(s).trim().replace(/R\$\s*/i, '').replace(/\s/g, '');
  if (!str) return null;
  // Se tem virgula, ela e o decimal: remove pontos (sao milhares), troca virgula por ponto
  if (str.includes(',')) {
    str = str.replace(/\./g, '').replace(',', '.');
  }
  // Senao, ponto pode ser decimal OU milhar — heuristica: se tem 2 digitos depois do ultimo ponto = decimal
  const n = Number(str);
  return isNaN(n) ? null : n;
}
function numToBR(n) {
  if (n == null || n === '') return '';
  return Number(n).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
}

function _ccStatusClass(s) {
  const m = {
    'AGUARDANDO DADOS':              'ccs-aguardando-dados',
    'INICIAR COTAÇÃO':               'ccs-iniciar-cotacao',
    'EM COTAÇÃO':                    'ccs-em-cotacao',
    'ESTUDO APRESENTADO':            'ccs-estudo-apresentado',
    'AGUARDANDO DOCUMENTAÇÃO':       'ccs-aguardando-documentacao',
    'INICIAR IMPLANTAÇÃO':           'ccs-iniciar-implantacao',
    'EM IMPLANTAÇÃO':                'ccs-em-implantacao',
    'EM NEGOCIAÇÃO COM A OPERADORA': 'ccs-em-negociacao',
    'PASSAGEM DE BASTÃO':            'ccs-passagem-bastao',
    'IMPLANTAÇÃO FINALIZADA':        'ccs-implantacao-finalizada',
    'DECLINADA':                     'ccs-declinada',
    'INICIAR NOMEAÇÃO':              'ccs-iniciar-nomeacao',
    'EM NOMEAÇÃO':                   'ccs-em-nomeacao',
    'NOMEAÇÃO CONCLUÍDA':            'ccs-nomeacao-concluida'
  };
  const key = String(s||'').toUpperCase().trim();
  return m[key] || 'ccs-default';
}

function _ccPrimeiroNome(nome){
  const n = String(nome||'').trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
  return n.split(/\s+/)[0] || '';
}

// Badge colorido para Próxima Ação
// Verde: 4-5 dias | Laranja: 2-3 dias | Vermelho: <=1 dia ou atrasado
function _ccProxBadge(dataISO){
  if (!dataISO) return '<span style="color:var(--muted)">—</span>';
  const m = String(dataISO).match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!m) return '<span style="color:var(--muted)">—</span>';
  const alvo = new Date(+m[1], +m[2]-1, +m[3]);
  const hoje = new Date(); hoje.setHours(0,0,0,0);
  const diff = Math.round((alvo - hoje) / 86400000);
  const txt = `${m[3]}/${m[2]}/${m[1]}`;
  let bg, fg, label;
  if (diff < 0)       { bg='#dc3545'; fg='#fff'; label=`${txt} (${Math.abs(diff)}d atrasado)`; }
  else if (diff <= 1) { bg='#dc3545'; fg='#fff'; label=`${txt} (hoje/amanha)`; }
  else if (diff <= 3) { bg='#fd7e14'; fg='#fff'; label=`${txt} (${diff}d)`; }
  else if (diff <= 5) { bg='#28a745'; fg='#fff'; label=`${txt} (${diff}d)`; }
  else                { bg='#e9ecef'; fg='#495057'; label=`${txt} (${diff}d)`; }
  return `<span style="padding:3px 8px;border-radius:10px;font-size:11px;font-weight:600;background:${bg};color:${fg};white-space:nowrap">${label}</span>`;
}

async function ccCarregarAnalistas(){
  try {
    let candidatos = [];
    try {
      const r = await apiGet('analistas');
      candidatos = (r && r.data) ? r.data.map(a => ({ nome: a.nome, tipo: a.tipo })) : [];
    } catch(_){ candidatos = []; }

    (ccData||[]).forEach(c => {
      const n = (c.analista||'').trim();
      if (n) candidatos.push({ nome: n, tipo: 'cotacao' });
    });

    // Designada = lista fixa definida pela operação (5 pessoas).
    // (primeiro nome, minúsculo; 'ingrid'/'ingridh' = mesma pessoa)
    const PERMITIDOS = ['ingrid','ingridh','mariana','marina','silmara','luiza','amanda','lorena','rhudson','alessandra'];
    const _norm = n => { const p = _ccPrimeiroNome(n); return p === 'ingrid' ? 'ingridh' : p; };

    const porPrimeiro = new Map();
    candidatos.forEach(a => {
      const chave = _ccPrimeiroNome(a.nome);
      if (!chave || !PERMITIDOS.includes(chave)) return;
      const key = _norm(a.nome);
      const atual = porPrimeiro.get(key);
      // prefere o registro do cadastro (tem tipo real) sobre o vindo da cotação
      const aCad = (a.tipo||'').toLowerCase() === 'novos_negocios';
      const atualCad = atual && (atual.tipo||'').toLowerCase() === 'novos_negocios';
      if (!atual || (aCad && !atualCad) || (aCad === atualCad && (a.nome||'').length < (atual.nome||'').length)) {
        porPrimeiro.set(key, a);
      }
    });

    let lista = [...porPrimeiro.values()];
    // Garante as 5 mesmo se faltarem no cadastro
    [['ingridh','Ingrid'],['mariana','Mariana'],['marina','Marina'],['silmara','Silmara'],['luiza','Luiza'],['amanda','Amanda'],['lorena','Lorena'],['rhudson','Rhudson Araújo'],['alessandra','Alessandra']]
      .forEach(([k,label]) => { if (!porPrimeiro.has(k)) lista.push({ nome: label, tipo: 'fixo' }); });
    lista.sort((a,b) => (a.nome||'').localeCompare(b.nome||'', 'pt'));
    _ccAnalistas = lista;

    // dropdown do modal (seleção única) — continua igual
    const sel = document.getElementById('mc-analista');
    if (sel) sel.innerHTML = '<option value="">Selecione...</option>' + lista.map(a => `<option value="${esc(a.nome)}">${esc(a.nome)}</option>`).join('');

    // filtro da tela (multi-seleção por checkbox)
    const apanel = document.getElementById('ms-analista-panel');
    if (apanel) {
      apanel.innerHTML = lista.map(a =>
        `<label class="ms-opt"><input type="checkbox" value="${escAttr(a.nome)}" onchange="ccMsChange('analista')"> ${esc(a.nome)}</label>`
      ).join('') + `<div class="ms-actions"><button type="button" onclick="ccMsClear('analista')">Limpar</button></div>`;
      // re-marca o que já estava filtrado
      (ccFiltros.analista || []).forEach(v => {
        const c = apanel.querySelector(`input[type=checkbox][value="${(window.CSS && CSS.escape) ? CSS.escape(v) : v}"]`);
        if (c) c.checked = true;
      });
    }
  } catch (e) { console.warn('ccCarregarAnalistas:', e); }
}

// ── Filtros multi-seleção (status, entrada, tipo, ramo, analista) ──
// Aplicados no front: marca várias opções e filtra a tabela na hora.
const ccFiltros = { status:[], entrada:[], tipo:[], ramo:[], analista:[] };
const _CC_MS_DEFAULT = {
  status:'Todos os status', entrada:'Todas as entradas', tipo:'Todos os tipos',
  ramo:'Todos os ramos', analista:'Todos os analistas'
};

function ccMsToggle(key, ev){
  if (ev) ev.stopPropagation();
  const el = document.getElementById('ms-' + key);
  if (!el) return;
  const abrir = !el.classList.contains('open');
  document.querySelectorAll('.ms.open').forEach(m => m.classList.remove('open'));
  if (abrir) el.classList.add('open');
}

function ccMsChange(key){
  const el = document.getElementById('ms-' + key);
  if (!el) return;
  const vals = [...el.querySelectorAll('input[type=checkbox]:checked')].map(c => c.value);
  ccFiltros[key] = vals;
  const lbl = document.getElementById('ms-' + key + '-label');
  if (lbl) lbl.textContent = vals.length === 0 ? _CC_MS_DEFAULT[key]
            : (vals.length === 1 ? vals[0] : (vals.length + ' selecionados'));
  CC_PAGE = 1;
  ccRenderStats();
  ccRenderTable();
}

function ccMsClear(key){
  const el = document.getElementById('ms-' + key);
  if (!el) return;
  el.querySelectorAll('input[type=checkbox]').forEach(c => { c.checked = false; });
  ccMsChange(key);
}

// fecha os painéis ao clicar fora; clique dentro do painel não fecha
document.addEventListener('click', () => document.querySelectorAll('.ms.open').forEach(m => m.classList.remove('open')));
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.ms-panel').forEach(p => p.addEventListener('click', e => e.stopPropagation()));
});

// Aplica os filtros de checkbox sobre o conjunto carregado.
function _ccNorm(s){ return String(s == null ? '' : s).trim().toUpperCase(); }
function ccGetFiltered(){
  const f = ccFiltros;
  const bate = (arr, v) => arr.length === 0 || arr.map(_ccNorm).includes(_ccNorm(v));
  return (ccData || []).filter(d =>
       bate(f.status,   d.status)
    && bate(f.entrada,  d.entrada)
    && bate(f.tipo,     d.tipo)
    && bate(f.ramo,     d.ramo)
    && bate(f.analista, d.analista)
  );
}

async function ccLoad(){
  // Busca e datas continuam no servidor; status/entrada/tipo/ramo/analista
  // são multi-seleção e filtram no front (ccGetFiltered).
  const params = {
    search: val('cf-search'),
    de:     val('cf-de'),
    ate:    val('cf-ate')
  };
  Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
  const r = await apiGet('listar_cotacoes', params);
  if (!r.ok) { toast('Erro ao carregar cotações: ' + (r.msg || '')); return; }
  ccData = r.data || [];
  ccRenderStats();
  ccRenderTable();
}

function ccRenderStats(){
  const base = ccGetFiltered();
  const total = base.length;
  const stUp = d => String(d.status||'').toUpperCase().trim();
  const emEstudo = base.filter(d => {
    const s = stUp(d);
    return s==='AGUARDANDO DADOS' || s==='INICIAR COTAÇÃO' || s==='EM COTAÇÃO'
        || s==='ESTUDO APRESENTADO' || s==='AGUARDANDO DOCUMENTAÇÃO'
        || s==='EM NEGOCIAÇÃO COM A OPERADORA' || s==='PASSAGEM DE BASTÃO'
        || s==='INICIAR NOMEAÇÃO' || s==='EM NOMEAÇÃO';
  }).length;
  const finalizadas = base.filter(d => {
    const s = stUp(d);
    return s==='IMPLANTAÇÃO FINALIZADA' || s==='NOMEAÇÃO CONCLUÍDA'
        || s==='INICIAR IMPLANTAÇÃO' || s==='EM IMPLANTAÇÃO';
  }).length;
  const declinadas = base.filter(d => stUp(d)==='DECLINADA').length;
  setText('cc-total',  total);
  setText('cc-estudo', emEstudo);
  setText('cc-impl',   finalizadas);
  setText('cc-dec',    declinadas);
  ccRenderBreakdown();
}

// ── Painel de resumo: por Entrada e por Tipo (quantidade + valor + %) ──
const CC_ENTRADAS = [
  ['Interweg MG','Belo Horizonte'], ['Interweg SP','São Paulo'], ['Interweg JF','Juiz de Fora'],
  ['Finlândia','Finlândia'], ['Fred','Fred'],
  ['Parceiro - Bernardo','Parceiro - Bernardo'], ['Parceiro - Elmo','Parceiro - Elmo']
];
const CC_TIPOS = ['Seguro Novo','Seguro Novo de Cliente Interweg','Renovação','Nomeação'];
const CC_PALETA = ['#00c9a7','#5b8def','#f5a623','#bd6bff','#ff6b8a','#2fb98a','#f77f3f','#4c6ef5','#e8467c','#12b886'];
// Situação = agrupamento de status (para o painel "Por situação")
const CC_SITUACOES = [
  ['Fechadas',  ['EM IMPLANTAÇÃO','EM NOMEAÇÃO','IMPLANTAÇÃO FINALIZADA','INICIAR IMPLANTAÇÃO','NOMEAÇÃO CONCLUÍDA','PASSAGEM DE BASTÃO']],
  ['Em aberto', ['ESTUDO APRESENTADO','AGUARDANDO DADOS','AGUARDANDO DOCUMENTAÇÃO','EM COTAÇÃO','EM NEGOCIAÇÃO COM A OPERADORA','INICIAR COTAÇÃO']],
  ['Perdidas',  ['DECLINADA']]
];
const CC_COLLAPSED = { situacao:true, entrada:true, tipo:true };  // começa fechado
function ccBreakToggle(btn){
  const blk = btn.closest('.cc-break-block'); if (!blk) return;
  const col = blk.classList.toggle('collapsed');
  if (blk.dataset.block) CC_COLLAPSED[blk.dataset.block] = col;
}

// Base do painel: respeita status/ramo/analista e o card de status, mas ignora
// entrada/tipo — assim TODAS as entradas e tipos continuam aparecendo pra clicar.
function ccBaseForBreakdown(){
  const f = ccFiltros;
  const bate = (arr, v) => arr.length === 0 || arr.map(_ccNorm).includes(_ccNorm(v));
  let base = (ccData || []).filter(d =>
       bate(f.status, d.status) && bate(f.ramo, d.ramo) && bate(f.analista, d.analista));
  if (_ccCardFilter && _CC_GRUPOS[_ccCardFilter]) {
    const setG = _CC_GRUPOS[_ccCardFilter].map(_ccNorm);
    base = base.filter(d => setG.includes(_ccNorm(d.status)));
  }
  return base;
}

function _ccAgg(base, campo){
  const map = {};
  base.forEach(d => {
    const k = String(d[campo] == null ? '' : d[campo]).trim() || '—';
    if (!map[k]) map[k] = { count:0, valor:0 };
    map[k].count++;
    const v = parseFloat(d.premio); if (!isNaN(v)) map[k].valor += v;
  });
  return map;
}

function ccRenderBreakdown(){
  const wrap = document.getElementById('cc-breakdown');
  if (!wrap) return;
  const base = ccBaseForBreakdown();
  const totalCount = base.length;
  const aggE = _ccAgg(base, 'entrada');
  const aggT = _ccAgg(base, 'tipo');
  const entLabels = {}; CC_ENTRADAS.forEach(([v,l]) => entLabels[v] = l);
  let entKeys = CC_ENTRADAS.map(e => e[0]);
  Object.keys(aggE).forEach(k => { if (k !== '—' && !entKeys.some(x => _ccNorm(x)===_ccNorm(k))) entKeys.push(k); });
  let tipoKeys = CC_TIPOS.slice();
  Object.keys(aggT).forEach(k => { if (k !== '—' && !tipoKeys.some(x => _ccNorm(x)===_ccNorm(k))) tipoKeys.push(k); });
  wrap.innerHTML =
    _ccSituBlock() +
    _ccBreakRow('Por entrada', 'entrada', entKeys, aggE, entLabels, totalCount) +
    _ccBreakRow('Por tipo',    'tipo',    tipoKeys, aggT, {},        totalCount);
}

// Base do bloco "Por situação": respeita entrada/tipo/ramo/analista, ignora status
// (pra que as 3 situações sempre apareçam).
function ccBaseForSituacao(){
  const f = ccFiltros;
  const bate = (arr, v) => arr.length === 0 || arr.map(_ccNorm).includes(_ccNorm(v));
  return (ccData || []).filter(d =>
       bate(f.entrada, d.entrada) && bate(f.tipo, d.tipo) && bate(f.ramo, d.ramo) && bate(f.analista, d.analista));
}
function _ccSituBlock(){
  const base = ccBaseForSituacao();
  const total = base.length;
  const items = CC_SITUACOES.map(([nome, sts]) => {
    const setN = sts.map(_ccNorm);
    const rows = base.filter(d => setN.includes(_ccNorm(d.status)));
    const valor = rows.reduce((s,d) => { const v = parseFloat(d.premio); return s + (isNaN(v) ? 0 : v); }, 0);
    return { nome, sts, count: rows.length, valor };
  }).sort((x,y) => y.count - x.count || y.valor - x.valor);
  const maxc = items.reduce((m,i) => Math.max(m, i.count), 0) || 1;
  const cur = ccFiltros.status.map(_ccNorm).slice().sort();
  const rows = items.map(it => {
    const pct = total ? Math.round(it.count / total * 100) : 0;
    const w = Math.round(it.count / maxc * 100);
    const grp = it.sts.map(_ccNorm).slice().sort();
    const ativo = cur.length === grp.length && cur.every((s,i) => s === grp[i]);
    return `<div class="cc-brk-row${ativo ? ' cc-brk-active' : ''}${it.count === 0 ? ' cc-brk-zero' : ''}" data-sit="${esc(it.nome)}" onclick="ccSituacaoClick(this)">
      <div class="cc-brk-lbl">${esc(it.nome)}</div>
      <div class="cc-brk-track"><div class="cc-brk-fill" style="width:${w}%"></div></div>
      <div class="cc-brk-val"><b>${it.count}</b> · ${_ccBRLc(it.valor)} · ${pct}%</div>
    </div>`;
  }).join('');
  return `<div class="cc-break-block${CC_COLLAPSED['situacao'] ? ' collapsed' : ''}" data-block="situacao"><button type="button" class="cc-break-title" onclick="ccBreakToggle(this)"><span>Por situação</span><span class="cc-brk-chev">▾</span></button><div class="cc-brk-list">${rows}</div></div>`;
}
function ccSituacaoClick(btn){
  const grp = CC_SITUACOES.find(g => g[0] === btn.dataset.sit);
  if (!grp) return;
  const alvo = grp[1].map(_ccNorm);
  const el = document.getElementById('ms-status');
  if (!el) return;
  const cur = ccFiltros.status.map(_ccNorm).slice().sort();
  const grpS = alvo.slice().sort();
  const jaAtivo = cur.length === grpS.length && cur.every((s,i) => s === grpS[i]);
  el.querySelectorAll('input[type=checkbox]').forEach(c => {
    c.checked = jaAtivo ? false : alvo.includes(_ccNorm(c.value));
  });
  ccMsChange('status');
}

function _ccBRLc(v){
  v = parseFloat(v) || 0;
  if (v >= 1e6) return 'R$ ' + (v/1e6).toFixed(1).replace('.', ',') + ' mi';
  if (v >= 1e3) return 'R$ ' + (v/1e3).toFixed(1).replace('.', ',') + ' mil';
  return 'R$ ' + Math.round(v);
}
function _ccBreakRow(titulo, key, keys, agg, labels, totalCount){
  const items = keys.map(v => {
    const rk = agg[v] ? v : Object.keys(agg).find(k => _ccNorm(k)===_ccNorm(v));
    const a = (rk ? agg[rk] : null) || { count:0, valor:0 };
    return { v, label: labels[v] || v, count:a.count, valor:a.valor };
  }).sort((x,y) => y.count - x.count || y.valor - x.valor);
  const maxc = items.reduce((m,i) => Math.max(m, i.count), 0) || 1;
  const rows = items.map(it => {
    const pct = totalCount ? Math.round(it.count / totalCount * 100) : 0;
    const w = Math.round(it.count / maxc * 100);
    const ativo = ccFiltros[key].map(_ccNorm).includes(_ccNorm(it.v));
    return `<div class="cc-brk-row${ativo ? ' cc-brk-active' : ''}${it.count === 0 ? ' cc-brk-zero' : ''}" data-k="${key}" data-v="${esc(it.v)}" onclick="ccBreakClick(this)">
      <div class="cc-brk-lbl">${esc(it.label)}</div>
      <div class="cc-brk-track"><div class="cc-brk-fill" style="width:${w}%"></div></div>
      <div class="cc-brk-val"><b>${it.count}</b> · ${_ccBRLc(it.valor)} · ${pct}%</div>
    </div>`;
  }).join('');
  return `<div class="cc-break-block${CC_COLLAPSED[key] ? ' collapsed' : ''}" data-block="${key}"><button type="button" class="cc-break-title" onclick="ccBreakToggle(this)"><span>${titulo}</span><span class="cc-brk-chev">▾</span></button><div class="cc-brk-list">${rows}</div></div>`;
}

function ccBreakClick(btn){
  const key = btn.dataset.k, value = btn.dataset.v;
  const el = document.getElementById('ms-' + key);
  const cb = el ? [...el.querySelectorAll('input[type=checkbox]')].find(c => _ccNorm(c.value) === _ccNorm(value)) : null;
  if (cb) { cb.checked = !cb.checked; ccMsChange(key); }
  else {
    const i = ccFiltros[key].map(_ccNorm).indexOf(_ccNorm(value));
    if (i >= 0) ccFiltros[key].splice(i, 1); else ccFiltros[key].push(value);
    CC_PAGE = 1; ccRenderStats(); ccRenderTable();
  }
}

const _CC_GRUPOS = {
  estudo: ['AGUARDANDO DADOS','INICIAR COTAÇÃO','EM COTAÇÃO','ESTUDO APRESENTADO',
           'AGUARDANDO DOCUMENTAÇÃO','EM NEGOCIAÇÃO COM A OPERADORA','PASSAGEM DE BASTÃO',
           'INICIAR NOMEAÇÃO','EM NOMEAÇÃO'],
  impl:   ['IMPLANTAÇÃO FINALIZADA','NOMEAÇÃO CONCLUÍDA','INICIAR IMPLANTAÇÃO','EM IMPLANTAÇÃO'],
  declinada: ['DECLINADA']
};

function ccFiltrarPorCard(grupo, cardEl){
  if (_ccCardFilter === grupo) {
    _ccCardFilter = null;
  } else {
    _ccCardFilter = (grupo === 'total') ? null : grupo;
  }
  document.querySelectorAll('[data-cc-card]').forEach(c => c.classList.remove('cc-active'));
  if (_ccCardFilter && cardEl) cardEl.classList.add('cc-active');
  CC_PAGE = 1;
  ccRenderTable();
  ccRenderBreakdown();
}

function ccRenderTable(){
  const tbody = document.getElementById('cc-tbody');
  let dados = ccGetFiltered();
  if (_ccCardFilter && _CC_GRUPOS[_ccCardFilter]) {
    const grupo = _CC_GRUPOS[_ccCardFilter];
    dados = dados.filter(d => grupo.includes(String(d.status||'').toUpperCase().trim()));
  }
  const total = dados.length;
  setText('cc-count', `Lista de Cotações (${total})`);

  if (!total) {
    tbody.innerHTML = `<tr><td colspan="21" style="text-align:center;padding:30px;color:var(--muted)">Nenhuma cotação encontrada</td></tr>`;
    setText('cc-pag-info', '0 registros');
    document.getElementById('cc-pag-btns').innerHTML = '';
    return;
  }

  const ini = (CC_PAGE - 1) * CC_PER;
  const slice = dados.slice(ini, ini + CC_PER);

  tbody.innerHTML = slice.map(d => `<tr>
    <td style="color:#484f58;font-size:11px">#${String(d.id).padStart(4,'0')}</td>
    <td style="font-size:12px;font-weight:600">${esc(d.razao_social||'—')}</td>
    <td><span class="ccs ${_ccStatusClass(d.status)}">${esc(d.status||'—')}</span></td>
    <td style="font-size:11.5px;color:var(--muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escAttr(d.observacao||'')}">${esc(d.observacao||'—')}</td>
    <td style="font-size:11.5px">${esc(d.entrada||'—')}</td>
    <td style="font-size:11.5px">${esc(d.analista||'—')}</td>
    <td style="font-size:11.5px">${esc(d.tipo||'—')}</td>
    <td style="font-size:11.5px">${esc(d.ramo||'—')}</td>
    <td style="font-size:11.5px">${fmtDate(d.data_recebimento)}</td>
    <td>${_ccProxBadge(d.data_proxima_acao)}</td>
    <td style="font-size:11.5px">${esc(d.operadora_atual||'—')}</td>
    <td style="text-align:center;font-weight:600">${d.numero_vidas != null ? d.numero_vidas : '—'}</td>
    <td style="font-size:11.5px">${fmtDate(d.data_solicitacao)}</td>
    <td style="font-size:11.5px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escAttr(d.players_cotados||'')}">${esc(d.players_cotados||'—')}</td>
    <td style="font-size:11.5px">${fmtDate(d.data_envio_estudo)}</td>
    <td style="font-size:11.5px">${fmtDate(d.data_sinalizacao)}</td>
    <td style="font-size:11.5px">${esc(d.player_contratado||'—')}</td>
    <td style="font-size:11.5px;text-align:right;white-space:nowrap">${d.premio != null && d.premio !== '' ? fmtBRL(d.premio) : '—'}</td>
    <td style="font-size:11.5px">${fmtDate(d.data_finalizacao)}</td>
    <td style="font-size:11px;color:var(--muted);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escAttr(d.motivo_declinio||'')}">${esc(d.motivo_declinio||'—')}</td>
    <td style="white-space:nowrap"><button class="btn btn-outline btn-sm" onclick="ccView(${d.id})" title="Visualizar"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:-3px">visibility</span></button> <button class="btn btn-outline btn-sm" onclick="ccEdit(${d.id})">Editar</button></td>
  </tr>`).join('');

  const totalPages = Math.ceil(total / CC_PER);
  setText('cc-pag-info', `${ini+1}-${Math.min(ini+CC_PER, total)} de ${total}`);
  let btns = '';
  if (totalPages > 1) {
    for (let p = 1; p <= Math.min(totalPages, 12); p++) {
      btns += `<button class="pb${p===CC_PAGE?' active':''}" onclick="CC_PAGE=${p};ccRenderTable()">${p}</button>`;
    }
  }
  document.getElementById('cc-pag-btns').innerHTML = btns;
}

// ── Modal ───────────────────────────────────────────────────────
function ccOpenModal(){
  document.getElementById('mc-title').innerHTML = '<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">request_quote</span> Nova Cotação';
  document.getElementById('mc-btn-del').style.display = 'none';
  ['mc-id','mc-razao','mc-cnpj','mc-obs','mc-recebimento','mc-prox-acao','mc-vidas','mc-operadora-atual',
   'mc-data-solic','mc-data-envio','mc-players-saude','mc-players-odonto','mc-players-vida','mc-data-sinaliz','mc-player-contratado',
   'mc-premio','mc-data-final','mc-motivo'].forEach(id => setVal(id, ''));
  setVal('mc-status', 'Recebida');
  ccTogglePassagemBtn();
  setVal('mc-entrada', '');
  setVal('mc-analista', '');
  setVal('mc-tipo', '');
  setVal('mc-ramo', '');
  setVal('mc-recebimento', new Date().toISOString().slice(0,10));
  ccSetReadOnly(false);
  openModal('mod-cc');
}

// Ativa/desativa modo somente leitura no modal de cotação
function ccSetReadOnly(ro){
  const m = document.getElementById('mod-cc');
  if (!m) return;
  m.querySelectorAll('input,select,textarea').forEach(el => { el.disabled = ro; });
  m.querySelectorAll('[data-edit-only]').forEach(el => { el.style.display = ro ? 'none' : ''; });
}

// Abre a cotação apenas para leitura
function ccView(id){
  ccEdit(id);
  const t = document.getElementById('mc-title');
  if (t) t.innerHTML = '<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">visibility</span> Visualizar Cotação #' + id;
  ccSetReadOnly(true);
  const del = document.getElementById('mc-btn-del'); if (del) del.style.display = 'none';
  const pas = document.getElementById('mc-btn-passagem'); if (pas) pas.style.display = 'none';
}

function ccEdit(id){
  const d = ccData.find(x => x.id == id);
  if (!d) { toast('Cotação não encontrada'); return; }
  openModal('mod-cc');
  ccSetReadOnly(false);
  document.getElementById('mc-title').innerHTML = `<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">request_quote</span> Editar Cotação #${id}`;
  document.getElementById('mc-btn-del').style.display = '';
  setVal('mc-id', id);
  setVal('mc-razao',             d.razao_social);
  setVal('mc-cnpj',              d.cnpj);
  setVal('mc-status',            d.status);
  ccTogglePassagemBtn();
  setVal('mc-obs',               d.observacao);
  setVal('mc-entrada',           d.entrada);
  setVal('mc-analista',          d.analista);
  setVal('mc-tipo',              d.tipo);
  setVal('mc-ramo',              d.ramo);
  setVal('mc-recebimento',       d.data_recebimento);
  setVal('mc-prox-acao',         d.data_proxima_acao);
  setVal('mc-vidas',             d.numero_vidas);
  setVal('mc-operadora-atual',   d.operadora_atual);
  setVal('mc-data-solic',        d.data_solicitacao);
  setVal('mc-data-envio',        d.data_envio_estudo);
  setVal('mc-players-saude',     d.players_cotados_saude);
  setVal('mc-players-odonto',    d.players_cotados_odonto);
  setVal('mc-players-vida',      d.players_cotados_vida);
  setVal('mc-data-sinaliz',      d.data_sinalizacao);
  setVal('mc-player-contratado', d.player_contratado);
  setVal('mc-premio',            d.premio != null ? numToBR(d.premio) : '');
  setVal('mc-data-final',        d.data_finalizacao);
  setVal('mc-motivo',            d.motivo_declinio);
}

async function ccSave(){
  const id = document.getElementById('mc-id').value;
  const body = {
    id: id || null,
    razao_social:        val('mc-razao').trim(),
    cnpj:                val('mc-cnpj').trim(),
    status:              val('mc-status'),
    observacao:          val('mc-obs').trim(),
    entrada:             val('mc-entrada'),
    analista:            val('mc-analista'),
    tipo:                val('mc-tipo'),
    ramo:                val('mc-ramo'),
    data_recebimento:    val('mc-recebimento') || null,
    data_proxima_acao:   val('mc-prox-acao') || null,
    numero_vidas:        val('mc-vidas').trim() === '' ? null : parseInt(val('mc-vidas')),
    operadora_atual:     val('mc-operadora-atual').trim(),
    data_solicitacao:    val('mc-data-solic') || null,
    data_envio_estudo:   val('mc-data-envio') || null,
    players_cotados:     [val('mc-players-saude'), val('mc-players-odonto'), val('mc-players-vida')].map(s => s.trim()).filter(Boolean).join(' | '),
    players_cotados_saude:  val('mc-players-saude').trim(),
    players_cotados_odonto: val('mc-players-odonto').trim(),
    players_cotados_vida:   val('mc-players-vida').trim(),
    data_sinalizacao:    val('mc-data-sinaliz') || null,
    player_contratado:   val('mc-player-contratado').trim(),
    premio:              val('mc-premio').trim() === '' ? null : brToNum(val('mc-premio')),
    data_finalizacao:    val('mc-data-final') || null,
    motivo_declinio:     val('mc-motivo').trim()
  };

  if (!body.razao_social) { toast('Preencha a Razão Social'); return false; }
  if (!body.status)       { toast('Selecione o Status'); return false; }
  if (!body.entrada)      { toast('Selecione a Entrada'); return false; }
  if (!body.tipo)         { toast('Selecione o Tipo'); return false; }
  if (!body.ramo)         { toast('Selecione o Ramo'); return false; }

  const action = id ? 'editar_cotacao_controle' : 'criar_cotacao_controle';
  const r = await apiPost(action, body);
  if (r.ok) {
    toast(id ? 'Cotação atualizada' : 'Cotação criada');
    if (r.fechamento_criado) toast('Enviado automaticamente para o Fechamento ✅');
    closeModal('mod-cc');
    ccLoad();
    return id || r.id || true;
  } else {
    toast('Erro: ' + (r.msg || 'falha ao salvar'));
    return false;
  }
}

async function ccDelete(){
  const id = document.getElementById('mc-id').value;
  if (!id) return;
  if (!confirm(`Excluir cotação #${id}?`)) return;
  const r = await apiPost('excluir_cotacao_controle', { id });
  if (r.ok) {
    toast('Cotação excluída');
    closeModal('mod-cc');
    ccLoad();
  } else {
    toast('Erro: ' + (r.msg || 'falha ao excluir'));
  }
}

// ── Ponte: gerar Passagem de Bastão a partir da cotação ──
function ccTogglePassagemBtn(){
  const b = document.getElementById('mc-btn-passagem');
  if (b) b.style.display = (val('mc-status') === 'PASSAGEM DE BASTÃO') ? '' : 'none';
}
async function ccGerarPassagem(){
  const nome = val('mc-razao').trim();
  if (!nome){ toast('Preencha a Razão Social antes de gerar a passagem.'); return; }

  // 1) Salva a cotação primeiro (mesma validação/gravação do botão Salvar)
  const btn = document.getElementById('mc-btn-passagem');
  if (btn) btn.disabled = true;
  try {
    const savedId = await ccSave();
    if (!savedId) return; // validação falhou ou erro ao salvar — não redireciona
    const cotId = (savedId === true) ? (val('mc-id') || '') : savedId;

    // 2) Monta os dados para pré-preencher a Passagem de Bastão
    const operadora = (val('mc-player-contratado') || '').trim() || (val('mc-operadora-atual') || '').trim();
    const notifChk = document.getElementById('mc-notificar-email');
    const pf = {
      cotacao_id: cotId,
      nome: nome,
      origem: val('mc-entrada'),
      ramo: val('mc-ramo'),
      seguradora: operadora,
      analista: val('mc-analista'),
      numero_vidas: val('mc-vidas').trim(),
      valor_fatura: val('mc-premio').trim(),
      obs_origem: 'Origem: Cotação' + (cotId ? (' #' + cotId) : ''),
      notificar_email_passagem: (notifChk && notifChk.checked) ? 1 : 0,
      destinatarios_email_passagem: val('mc-destinatarios-email').trim()
    };
    try { sessionStorage.setItem('pb_prefill', JSON.stringify(pf)); } catch(e){}

    // 3) Abre o formulário de Passagem de Bastão já pré-preenchido
    window.location.href = 'passagem_bastao.php';
  } finally {
    if (btn) btn.disabled = false;
  }
}
window.ccGerarPassagem = ccGerarPassagem;
window.ccTogglePassagemBtn = ccTogglePassagemBtn;

// ── Export XLSX ──
function ccExportXLSX(){
  const _exp = ccGetFiltered();
  if (!_exp.length) { toast('Nada para exportar'); return; }
  const headers = ['RAZÃO SOCIAL','STATUS','OBSERVAÇÃO/ÚLTIMO STATUS','ENTRADA','ANALISTA','TIPO','RAMO',
                   'DATA RECEBIMENTO','PRÓXIMA AÇÃO','OPERADORA ATUAL','VIDAS','DATA SOLICITAÇÃO',
                   'PLAYERS COTADOS','DATA ENVIO','DATA SINALIZAÇÃO',
                   'PLAYER CONTRATADO','PRÊMIO','DATA FINALIZAÇÃO','MOTIVO DECLÍNIO'];
  const rows = _exp.map(d => [
    d.razao_social, d.status, d.observacao, d.entrada, d.analista, d.tipo, d.ramo,
    d.data_recebimento, d.data_proxima_acao, d.operadora_atual, d.numero_vidas, d.data_solicitacao,
    d.players_cotados, d.data_envio_estudo, d.data_sinalizacao,
    d.player_contratado, d.premio, d.data_finalizacao, d.motivo_declinio
  ]);
  const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
  // Cabeçalho com a cor da Interweg (teal, texto branco em negrito)
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
  for (let c = 0; c < headers.length; c++) {
    const addr = XLSX.utils.encode_cell({ r: 0, c });
    if (ws[addr]) ws[addr].s = headerStyle;
  }
  ws['!rows'] = [{ hpt: 22 }];
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Controle de Cotação');
  XLSX.writeFile(wb, 'controle_cotacao_' + new Date().toISOString().slice(0,10) + '.xlsx');
}

window.ccExportXLSX = ccExportXLSX;

// =============================================================
// PADDING DEFENSIVO contra truncamento do File Manager Hostinger
// =============================================================
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// FIM
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding paddin