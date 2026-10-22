// ═══════════════════════════════════════════════════════════════
// RENOVACAO.JS — lógica da página de Renovação
// Depende de assets/common.js (fmtBRL, esc, toast, apiGet, etc.)
// ═══════════════════════════════════════════════════════════════

window.IS_ADMIN  = (typeof IS_ADMIN  !== 'undefined') ? IS_ADMIN  : true;
window.CAN_VIEW_ALL = (typeof CAN_VIEW_ALL !== 'undefined') ? CAN_VIEW_ALL : window.IS_ADMIN;
window.USER_NOME = (typeof USER_NOME !== 'undefined') ? USER_NOME : '';

// Fallbacks defensivos pra helpers (caso common.js seja antigo/falhe)
const _REN_COLORS = (typeof window.COLORS !== 'undefined' && Array.isArray(window.COLORS) && window.COLORS.length)
  ? window.COLORS
  : ['#00c9a7','#58a6ff','#bc8cff','#ffa657','#3fb950','#f85149','#d29922','#79c0ff','#ff7b72','#a5d6ff'];

if (typeof window.esc !== 'function')    window.esc    = s => { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; };
if (typeof window.escAttr !== 'function') window.escAttr = s => window.esc(s).replace(/"/g,'&quot;');
if (typeof window.toast !== 'function')  window.toast  = m => alert(m);
if (typeof window.fmtBRL !== 'function') window.fmtBRL = v => { const n=parseFloat(v); return isNaN(n)?'R$ 0,00':'R$ '+n.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); };
if (typeof window.numToBR !== 'function') window.numToBR = v => { const n=parseFloat(v); return isNaN(n)?'0':n.toLocaleString('pt-BR',{minimumFractionDigits:0,maximumFractionDigits:2}); };
if (typeof window.brToNum !== 'function') window.brToNum = v => { if(v==null) return 0; const s=String(v).trim().replace(/[R$\s.]/g,'').replace(',','.'); const n=parseFloat(s); return isNaN(n)?0:n; };
if (typeof window.fmtDate !== 'function') window.fmtDate = s => { if(!s) return '—'; const m=String(s).match(/^(\d{4})-(\d{2})-(\d{2})/); return m?`${m[3]}/${m[2]}/${m[1]}`:String(s); };
if (typeof window.fmtPerc !== 'function') window.fmtPerc = v => { const n=parseFloat(v); return isNaN(n)?'—':n.toLocaleString('pt-BR',{minimumFractionDigits:0,maximumFractionDigits:2})+'%'; };
if (typeof window.openModal !== 'function')  window.openModal  = id => { const el=document.getElementById(id); if(el) el.classList.add('show'); };
if (typeof window.closeModal !== 'function') window.closeModal = id => { const el=document.getElementById(id); if(el) el.classList.remove('show'); };
if (typeof window.apiGet !== 'function')  window.apiGet  = async (a,p={}) => (await fetch('api.php?'+new URLSearchParams({action:a,...p}))).json();
if (typeof window.apiPost !== 'function') window.apiPost = async (a,b={}) => (await fetch('api.php?action='+encodeURIComponent(a),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)})).json();

let renData = [];
let renFiltered = [];
let REN_PAGE = 1;
const REN_PER = 50;

let _renSeguradoras = [];
let _renEmpresas    = [];
let _renAnalistas   = [];

// ── INIT ────────────────────────────────────────────────────────
async function renInit(){
  try{
    const [seg, emp, ana] = await Promise.all([
      apiGet('seguradoras'),
      apiGet('empresas'),
      apiGet('analistas')
    ]);
    _renSeguradoras = seg.data || [];
    _renEmpresas    = emp.data || [];
    _renAnalistas   = ana.data || [];
  }catch(e){ console.warn('Falha ao carregar caches:', e); }

  // popular dropdowns dos filtros
  fillSelect('rf-seguradora', 'Todas as seguradoras',
    _renSeguradoras.map(s => typeof s === 'string' ? s : s.nome).filter(Boolean).sort());

  // Filtro de grupo removido — só Empresa interessa na visão de Renovação

  // Filtra: só ANALISTAS reais (sem admin/enfermeiras/financeiro/etc)
  const analistasReais = (_renAnalistas || []).filter(a => {
    const nome = (a.nome || '').toLowerCase().trim();
    const tipo = (a.tipo || '').toLowerCase();
    // Mantém SÓ analista de fato — exclui todas as lideranças e áreas-suporte
    if (['admin','head','coordenador','financeiro','enfermeira','enfermagem',
         'visualizador','novos_negocios'].includes(tipo)) return false;
    if (nome.startsWith('enf.') || nome.startsWith('enf '))    return false;
    if (nome === 'administrador' || nome === 'financeiro interweg') return false;
    return true;
  });
  fillSelect('rf-analista', 'Todos os analistas',
    analistasReais.map(a => a.nome).filter(Boolean));

  await renLoad();
  await renLoadStats();
}

function fillSelect(id, placeholder, items){
  const el = document.getElementById(id);
  if (!el) return;
  el.innerHTML = `<option value="">${placeholder}</option>` +
    items.map(s => `<option value="${escAttr(s)}">${esc(s)}</option>`).join('');
}

// ── LISTAGEM ────────────────────────────────────────────────────
function renFilterParams(){
  const params = {
    search:     val('rf-search'),
    origem:     val('rf-origem'),
    ramo:       val('rf-ramo'),
    seguradora: val('rf-seguradora'),
    analista:   val('rf-analista'),
    status:     val('rf-status'),
    tipo:       val('rf-tipo'),
    aditivo:    val('rf-aditivo'),
    periodo:    val('rf-periodo'),
    mes:        val('rf-mes')
  };
  // Flag do card "Sinistralidade Alta" (>70%)
  if (window._renSinAlta) params.sinistralidade_alta = '1';
  return params;
}

async function renLoad(){
  REN_PAGE = 1;
  const params = renFilterParams();
  try{
    const r = await apiGet('listar_renovacao', params);
    renData = r.data || [];
  }catch(e){
    renData = [];
    toast('Erro ao carregar renovações');
  }
  renRenderTable();
  renCalcCardsAnuais();
  renLoadStats(); // cards de cima também refletem os filtros atuais
}

function val(id){
  const el = document.getElementById(id);
  return el ? (el.value || '') : '';
}

function renRenderTable(){
  const tbody = document.getElementById('ren-tbody');
  const total = renData.length;
  const cnt = document.getElementById('ren-count');
  if (cnt) cnt.textContent = `Lista de Renovações (${total})`;

  if (!total){
    tbody.innerHTML = `<tr><td colspan="25" style="text-align:center;padding:30px;color:var(--muted)">Nenhuma renovação encontrada</td></tr>`;
    document.getElementById('ren-pag-info').textContent = '0 registros';
    document.getElementById('ren-pag-btns').innerHTML = '';
    return;
  }

  const ini = (REN_PAGE-1)*REN_PER;
  const slice = renData.slice(ini, ini+REN_PER);

  tbody.innerHTML = slice.map((r, i) => {
    const ant  = parseFloat(r.premio_anterior)   || 0;
    const reaj = parseFloat(r.premio_reajustado) || 0;
    const perc = parseFloat(r.percentual_comissao) || 0;
    const percNorm = perc > 0 && perc < 1 ? perc * 100 : perc; // banco pode ter 0.05 ou 5
    // Só calcula receita/comissão/aumento quando AMBOS estão preenchidos.
    // Se reaj=0, a renovação ainda não foi feita — mostra "—" em vez de
    // virar -100% / -ant*12 (que sumia como prejuízo falso).
    const calc = ant > 0 && reaj > 0;
    const recMais  = calc ? (reaj - ant)            : null;
    const recAnual = calc ? (reaj - ant) * 12       : null;
    const comAnual = calc ? recAnual * percNorm / 100 : null;
    const aumentoReal = calc ? ((reaj/ant - 1) * 100) : null;
    const sinPct = _sinPct(r.sinistralidade);
    // Data de renovação = valor do banco (mes_renovacao) é fonte de verdade,
    // porque é por ele que o filtro de mês filtra. Só cai no aniversário da
    // vigência se mes_renovacao estiver vazio — assim o que aparece na coluna
    // sempre bate com o que o filtro busca.
    const mesRenISO = (r.mes_renovacao ? String(r.mes_renovacao).slice(0,10) : null)
                   || _proxRenovacao(r.inicio_vigencia)
                   || null;
    const mesCorObj = _mesCor(mesRenISO);
    const mesRen  = mesRenISO
      ? new Date(mesRenISO+'T00:00:00').toLocaleDateString('pt-BR') // dd/mm/yyyy
      : '—';
    const ramoCls = r.ramo === 'Saúde' ? 'r-saude'
                  : r.ramo === 'Odonto' ? 'r-odonto'
                  : r.ramo === 'Saúde/Odonto' ? 'r-saude-odonto'
                  : r.ramo === 'Vida' ? 'r-vida' : '';
    return `<tr>
      <td>${ini+i+1}</td>
      <td style="font-size:12px">${esc(r.origem||'—')}</td>
      <td><span class="ramo ${ramoCls}">${esc(r.ramo||'—')}</span></td>
      <td style="font-size:12px">${esc(r.seguradora||'—')}</td>
      <td style="font-size:11.5px">${esc(r.numero_contrato||'—')}</td>
      <td><strong>${esc(r.empresa||r.grupo||'—')}</strong></td>
      <td style="font-size:12px">${esc(r.analista_nome||'—')}</td>
      <td style="font-size:12px">${fmtDate(r.inicio_vigencia)}</td>
      <td style="font-size:12px;background:${mesCorObj.bg};color:${mesCorObj.fg};font-weight:${mesCorObj.bg?'700':'400'}" title="${mesCorObj.label}">${mesRen}</td>
      <td style="text-align:right">${r.numero_vidas||'—'}</td>
      <td style="font-size:12px">${esc(_renNormalizePorte(r.porte_empresa||r.porte||'')||'—')}</td>
      <td style="text-align:right">${r.percentual_comissao?fmtPerc(_pct(r.percentual_comissao)):'—'}</td>
      <td style="font-size:12px">${fmtDate(r.inicio_tratativa)}</td>
      <td><span class="badge b-analise">${esc(r.status_renovacao||'—')}</span></td>
      <td style="text-align:right;color:${_sinColor(r.sinistralidade)};font-weight:700">${sinPct!=null?fmtPerc(sinPct):'—'}</td>
      <td style="text-align:right">${r.percentual_proposto?fmtPerc(_pct(r.percentual_proposto)):'—'}</td>
      <td style="text-align:right">${r.percentual_final?fmtPerc(_pct(r.percentual_final)):'—'}</td>
      <td style="font-size:12px">${esc(r.aditivo||'—')}</td>
      <td style="text-align:right">${fmtBRL(r.premio_anterior)}</td>
      <td style="text-align:right">${fmtBRL(r.premio_reajustado)}</td>
      <td style="text-align:right;color:${recMais==null?'var(--muted)':(recMais>=0?'var(--success)':'var(--danger)')};font-weight:600">${recMais!=null?fmtBRL(recMais):'—'}</td>
      <td style="text-align:right;color:${recAnual==null?'var(--muted)':(recAnual>=0?'var(--success)':'var(--danger)')};font-weight:700">${recAnual!=null?fmtBRL(recAnual):'—'}</td>
      <td style="text-align:right;color:${comAnual==null?'var(--muted)':'var(--accent)'};font-weight:700">${comAnual!=null?fmtBRL(comAnual):'—'}</td>
      <td style="font-size:11.5px;max-width:200px;white-space:normal;color:var(--muted)" title="${escAttr(r.observacao||'')}">${esc(r.observacao||'—')}</td>
      <td><button class="btn btn-outline btn-sm" onclick="renEdit(${r.id})">Editar</button></td>
    </tr>`;
  }).join('');

  // Paginação
  const totalPages = Math.ceil(total/REN_PER);
  document.getElementById('ren-pag-info').textContent =
    `${ini+1}-${Math.min(ini+REN_PER, total)} de ${total}`;
  let btns = '';
  if (totalPages > 1){
    for (let p=1; p<=Math.min(totalPages, 12); p++){
      btns += `<button class="pb${p===REN_PAGE?' active':''}" onclick="REN_PAGE=${p};renRenderTable()">${p}</button>`;
    }
  }
  document.getElementById('ren-pag-btns').innerHTML = btns;
}

async function renLoadStats(){
  try{
    const r = await apiGet('stats_renovacao', renFilterParams());
    const d = r.data || {};
    // Cards básicos: usa stats da API se vier, senão calcula direto da renData
    // 4 cards operacionais (nova versão)
    setText('ren-em-trat',       d.em_tratativa || 0);
    setText('ren-prox30',        d.prox30 || 0);
    setText('ren-atrasadas',     d.atrasadas || 0);
    setText('ren-sin-alta-card', d.sin_alta || 0);

    // Mantidos pra compat (algumas telas podem usar)
    setText('ren-total',     d.total || (renData?renData.length:0) || 0);
    setText('ren-mes',       d.mes || 0);
    setText('ren-prox90',    d.prox90 || 0);
    setText('ren-fim',       d.finalizadas || 0);
    setText('ren-tot-ant', fmtBRL(d.tot_ant));
    setText('ren-tot-reaj',fmtBRL(d.tot_reaj));
    setText('ren-tot-rec', fmtBRL(d.tot_rec));
    setText('ren-media-sin', d.media_sin ? fmtPerc(_pct(d.media_sin)) : '—');
  }catch(e){}

  // Cards novos: calculados sobre renData (carteira filtrada visível)
  renCalcCardsAnuais();
}

function renCalcCardsAnuais(){
  if (!renData || !renData.length){
    setText('ren-receita-anual',     'R$ 0,00');
    setText('ren-comis-anual',       'R$ 0,00');
    setText('ren-receita-anual-est', 'R$ 0,00');
    setText('ren-comis-anual-est',   'R$ 0,00');
    setText('ren-sin-alta',          '0');
    return;
  }
  let totReceitaAno = 0, totComisAno = 0, sinAlta = 0, contadas = 0;
  let estReceitaAno = 0, estComisAno = 0, estimadas = 0;
  renData.forEach(r => {
    const ant  = parseFloat(r.premio_anterior)   || 0;
    const reaj = parseFloat(r.premio_reajustado) || 0;
    const perc = _pct(r.percentual_comissao);
    // ── REAL: só conta quando AMBOS os prêmios estão preenchidos (>0). ──
    if (ant > 0 && reaj > 0){
      const recAno = (reaj - ant) * 12;
      totReceitaAno += recAno;
      totComisAno   += recAno * perc / 100;
      contadas++;
    }
    // ── PROJEÇÃO: usa o valor real quando existe; senão estima pelo % de
    // reajuste proposto/final aplicado sobre o prêmio anterior. ──
    let ganhoMensal = 0;
    if (ant > 0 && reaj > 0){
      ganhoMensal = reaj - ant;
    } else if (ant > 0){
      const pctReaj = _pct(r.percentual_final) || _pct(r.percentual_proposto) || 0;
      if (pctReaj > 0){ ganhoMensal = ant * pctReaj / 100; estimadas++; }
    }
    if (ganhoMensal){
      const recAnoEst = ganhoMensal * 12;
      estReceitaAno += recAnoEst;
      estComisAno   += recAnoEst * perc / 100;
    }
    const sin = _sinPct(r.sinistralidade);
    if (sin != null && sin > 70) sinAlta++;
  });
  setText('ren-receita-anual',     fmtBRL(totReceitaAno));
  setText('ren-comis-anual',       fmtBRL(totComisAno));
  setText('ren-receita-anual-est', fmtBRL(estReceitaAno));
  setText('ren-comis-anual-est',   fmtBRL(estComisAno));
  setText('ren-sin-alta',          String(sinAlta));

  // Atualiza sub-legendas pra indicar quantas renovações entraram no cálculo
  const totalCarteira = renData.length;
  const semDados = totalCarteira - contadas;
  const setSub = (id, txt) => {
    const card = document.getElementById(id)?.closest('.card');
    const sub = card?.querySelector('.card-sub');
    if (sub) sub.textContent = txt;
  };
  const projTxt = estimadas > 0 ? ` · projeção soma +${estimadas} estimada(s) pelo %` : '';
  if (semDados > 0){
    setSub('ren-receita-anual', `real: ${contadas} de ${totalCarteira} reajustadas (${semDados} sem prêmio)${projTxt}`);
    setSub('ren-comis-anual',   `real: ${contadas} de ${totalCarteira} reajustadas${projTxt}`);
  } else {
    setSub('ren-receita-anual', `real: ${contadas} renovações da carteira${projTxt}`);
    setSub('ren-comis-anual',   `real: ${contadas} renovações da carteira${projTxt}`);
  }
}
function setText(id, v){ const el = document.getElementById(id); if (el) el.textContent = v; }

function renFiltroMesAtual(){
  const sel = document.getElementById('rf-periodo');
  if (sel){ sel.value = 'mes_atual'; renLoad(); }
}
function renFiltroProx90(){
  const sel = document.getElementById('rf-periodo');
  if (sel){ sel.value = 'proximos90'; renLoad(); }
}

// ── FILTROS rápidos pelos cards ─────────────────────────────────
//   em_tratativa → carteira ativa (não finalizadas/canceladas/etc)
//   prox30       → vence em 30 dias e pendente
//   atrasadas    → data passou e pendente
//   sin_alta     → sinistralidade > 70% e pendente
//   (mantidos: total / finalizadas — usados em algumas chamadas legadas)
function renFiltroPor(tipo){
  const selStatus = document.getElementById('rf-status');
  const selPer    = document.getElementById('rf-periodo');
  // Limpa filtros que possam atrapalhar
  if (selStatus) selStatus.value = '';
  if (selPer)    selPer.value    = '';
  // Reseta flag de sinistralidade alta
  window._renSinAlta = false;

  if (tipo === 'em_tratativa') {
    if (selPer) selPer.value = 'em_tratativa';
  } else if (tipo === 'prox30') {
    if (selPer) selPer.value = 'proximos30';
  } else if (tipo === 'atrasadas') {
    if (selPer) selPer.value = 'atrasado';
  } else if (tipo === 'sin_alta') {
    // Backend aceita sinistralidade_alta=1
    window._renSinAlta = true;
  } else if (tipo === 'finalizadas') {
    if (selStatus) selStatus.value = 'Finalizado';
  }
  // 'total' = sem filtros (já limpou tudo)
  renLoad();
}

// ── Auto-calc do Prêmio Reajuste a partir do Prêmio Anterior + % Finalizado ──
// Fórmula: reaj = ant × (1 + percFim/100). Só preenche quando ambos > 0,
// permitindo edição manual do reaj se o usuário não usar o % Finalizado.
// Atenção: cada alteração no Anterior ou no % Finalizado vai sobrescrever o
// Reajuste — se quiser valor manual, edite depois.
function renCalcReajuste(){
  const ant     = brToNum(val('mr-prem-ant'));
  const percFim = brToNum(val('mr-perc-fim'));
  if (ant > 0 && percFim !== 0){
    const reaj = ant * (1 + percFim/100);
    const el = document.getElementById('mr-prem-reaj');
    if (el) el.value = numToBR(reaj);
  }
}

// ── Calculadoras do modal: Receita mensal, % Aumento Real, Receita Anual, Comissão Anual ──
function renCalc(){
  const ant  = brToNum(val('mr-prem-ant'));
  const reaj = brToNum(val('mr-prem-reaj'));
  const perc = brToNum(val('mr-perc-com'));   // % comissão
  const diff = reaj - ant;

  // Receita a mais (mensal)
  const elRec = document.getElementById('mr-receita');
  if (elRec){
    if (ant === 0 && reaj === 0){ elRec.value = ''; elRec.style.color=''; }
    else {
      elRec.value = numToBR(diff);
      elRec.style.color = diff >= 0 ? 'var(--success)' : 'var(--danger)';
    }
  }

  // (% Aumento Real removido — usuária pediu pra retirar o campo)

  // Receita Adicional ANUAL = (Reajustado - Anterior) × 12
  const elRecAno = document.getElementById('mr-receita-anual');
  if (elRecAno){
    if (ant === 0 && reaj === 0){ elRecAno.value = ''; elRecAno.style.color=''; }
    else {
      const recAno = diff * 12;
      elRecAno.value = numToBR(recAno);
      elRecAno.style.color = recAno >= 0 ? 'var(--success)' : 'var(--danger)';
    }
  }

  // Comissão Adicional ANUAL = Receita Anual × % Comissão / 100
  const elComAno = document.getElementById('mr-comis-anual');
  if (elComAno){
    if ((ant === 0 && reaj === 0) || perc === 0){ elComAno.value = ''; elComAno.style.color=''; }
    else {
      const comAno = (diff * 12) * perc / 100;
      elComAno.value = numToBR(comAno);
      elComAno.style.color = comAno >= 0 ? 'var(--success)' : 'var(--danger)';
    }
  }
}

// ── Helper: cor da sinistralidade (semáforo) ────────────────────
function _sinColor(v){
  const n = parseFloat(v);
  if (isNaN(n) || n === 0) return 'var(--muted)';
  if (n > 0.7 || n > 70) return 'var(--danger)';   // > 70% (aceita 0.7 ou 70)
  if (n > 0.5 || n > 50) return 'var(--warn)';     // 50-70%
  return 'var(--success)';                          // < 50%
}

// ── Próxima renovação: aniversário NO ANO CORRENTE ──────────────
// Pega mês/dia da vigência e aplica no ano atual, mesmo se a data já passou.
// A virada para o próximo ano é manual via botão "Virar Ano" (igual o "Virar Mês"
// do faturamento) — assim o controle de quando trocar o ano fica com o usuário.
function _proxRenovacao(inicioVigenciaISO){
  if (!inicioVigenciaISO) return null;
  const dv = new Date(inicioVigenciaISO + 'T00:00:00');
  if (isNaN(dv.getTime())) return null;
  const hoje = new Date();
  // Sempre no ano corrente — não avança automaticamente
  const cand = new Date(hoje.getFullYear(), dv.getMonth(), dv.getDate());
  return cand.toISOString().slice(0, 10);
}

// ── Auto-calc no modal: data de renovação = próximo aniversário ──
function renCalcRenovacao(){
  const vig = val('mr-ini-vig');
  if (!vig) return;
  const elMes = document.getElementById('mr-mes-ren');
  if (!elMes || elMes.value) return; // não sobrescreve se já preencheu
  const prox = _proxRenovacao(vig);
  if (prox) elMes.value = prox;
}

// ── Auto-preenchimento de PORTE baseado no número de vidas ──
// Regra: vidas >= 1000 → "Acima de 1000"
// (extensível pra outros ranges se quiser depois)
function renAjustaPorte(){
  const elVidas = document.getElementById('mr-vidas');
  const elPorte = document.getElementById('mr-porte');
  if (!elVidas || !elPorte) return;
  const v = parseInt(elVidas.value, 10);
  if (isNaN(v) || v <= 0) return;

  let porte = '';
  if      (v >= 1000) porte = 'Acima de 1000';
  else if (v >= 100)  porte = 'Entre 100 e 900';
  else if (v >= 30)   porte = 'Entre 30 e 100 vidas';
  else                porte = 'Até 30 vidas';

  // Setter tolerante (mesma função usada em outros lugares do form)
  if (typeof setSelectVal === 'function') {
    setSelectVal('mr-porte', porte);
  } else {
    elPorte.value = porte;
  }
}

// ── Helper: cor do mês de renovação (vencido / atual / próx 30 / >60) ──
function _mesCor(mesISO){
  if (!mesISO) return { bg: '', fg: 'var(--muted)', label: '' };
  const hoje = new Date(); hoje.setHours(0,0,0,0);
  const mes = new Date(mesISO + 'T00:00:00');
  const diff = Math.floor((mes - hoje) / 86400000);
  if (diff < 0) return { bg: 'rgba(248,81,73,.15)', fg: 'var(--danger)', label: 'vencido' };
  if (diff < 30) return { bg: 'rgba(210,153,34,.18)', fg: 'var(--warn)', label: 'mês atual' };
  if (diff < 60) return { bg: 'rgba(255,166,87,.15)', fg: '#ffa657', label: 'próx 30 dias' };
  return { bg: '', fg: 'var(--success)', label: '' };
}

// ── Helper: normaliza sinistralidade (banco pode vir 0.85 ou 85) ──
function _sinPct(v){
  const n = parseFloat(v);
  if (isNaN(n) || n === 0) return null;
  return n <= 1 ? n * 100 : n;
}

// ── Helper: normaliza percentual genérico (0.05 → 5, mas 5 fica 5) ──
function _pct(v){
  const n = parseFloat(v);
  if (isNaN(n)) return 0;
  return n > 0 && n < 1 ? n * 100 : n;
}

// ── Helper: formata percentual pra exibição no input (sem zero à esquerda,
// inteiro sempre que possível, até 2 decimais).
// Exemplos:
//   0.05   → "5"
//   0.0517 → "5,17"
//   5      → "5"
//   10     → "10"
//   12.5   → "12,5"
//   null/0 → ""
function _fmtPctEdit(v){
  if (v == null || v === '') return '';
  const n = _pct(v);
  if (n === 0) return '';
  return n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

// ── MODAL CADASTRO/EDIÇÃO ───────────────────────────────────────
function openModalRen(){
  // Limpa form
  document.getElementById('mr-id').value = '';
  document.getElementById('mr-title').textContent = 'Nova Renovação';
  document.getElementById('mr-btn-del').style.display = 'none';
  ['mr-origem','mr-ramo','mr-contrato','mr-cnpj','mr-vidas','mr-perc-com','mr-sinistr',
   'mr-perc-prop','mr-perc-fim','mr-prem-ant','mr-prem-reaj','mr-receita','mr-resp-nome','mr-resp-email',
   'mr-resp-tel','mr-obs','mr-ini-vig','mr-mes-ren','mr-ini-trat','mr-aditivo','mr-porte']
    .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  document.getElementById('mr-status').value = 'A iniciar';

  // popular dropdowns
  fillSelect('mr-seguradora', 'Selecione...',
    _renSeguradoras.map(s => typeof s === 'string' ? s : s.nome).filter(Boolean).sort());
  // Só ANALISTAS de verdade (sem admin/head/coordenador/financeiro/enfermeira/etc)
  const _renAnalistasSomenteAnalistas = (_renAnalistas || []).filter(a => {
    const tipo = (a.tipo || '').toLowerCase();
    const nome = (a.nome || '').toLowerCase().trim();
    if (['admin','head','coordenador','financeiro','enfermeira','enfermagem',
         'visualizador','novos_negocios'].includes(tipo)) return false;
    if (nome.startsWith('enf.') || nome.startsWith('enf ')) return false;
    if (nome === 'administrador' || nome === 'financeiro interweg') return false;
    return true;
  });
  fillSelect('mr-analista-sel', 'Selecione...',
    _renAnalistasSomenteAnalistas.map(a => a.nome));

  // Empresa: autocomplete com busca (não mais select gigante).
  // Limpa o campo e os hidden.
  const inpBusca = document.getElementById('mr-empresa-search');
  if (inpBusca) inpBusca.value = '';
  document.getElementById('mr-empresa').value    = '';
  document.getElementById('mr-empresa-id').value = '';
  document.getElementById('mr-grupo').value      = '';
  const results = document.getElementById('mr-empresa-results');
  if (results) results.style.display = 'none';

  // Esconde resultados ao clicar fora
  document.addEventListener('click', renEmpresaClickFora, { once: true });

  openModal('mod-ren');
}

// ── AUTOCOMPLETE DE EMPRESA ────────────────────────────────────
// Filtra _renEmpresas conforme o usuário digita. Sem duplicatas visuais:
// agrupa por nome+seguradora (mesma empresa duplicada vira 1 entrada,
// mas mantemos o ID da primeira pra resolver no save).
let _renEmpresaIdx = -1; // índice do item destacado (navegação por teclado)

function _renNormaliza(s){
  // ̀-ͯ = combining diacritical marks (forma robusta, sem depender
  // de chars literais no arquivo que podem corromper no salvamento/upload)
  return (s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').trim();
}

// Destaca o texto buscado no resultado (deixa em negrito + cor de destaque)
function _renHighlight(texto, query){
  if (!query) return esc(texto);
  const qNorm  = _renNormaliza(query);
  const tNorm  = _renNormaliza(texto);
  const idx = tNorm.indexOf(qNorm);
  if (idx < 0) return esc(texto);
  const antes = texto.substring(0, idx);
  const match = texto.substring(idx, idx + query.length);
  const depois = texto.substring(idx + query.length);
  return esc(antes) + `<mark style="background:rgba(0,191,179,.25);color:var(--accent);font-weight:700;padding:0 1px;border-radius:2px">${esc(match)}</mark>` + esc(depois);
}

function renEmpresaBuscar(query){
  const results = document.getElementById('mr-empresa-results');
  if (!results) return;
  const q = (query || '').trim();
  _renEmpresaIdx = -1; // reset destaque

  // TEXTO LIVRE: sincroniza o hidden mr-empresa com o que foi digitado.
  // Assim, mesmo que a empresa NÃO esteja na lista (empresa nova), o nome
  // digitado é salvo — o backend cria a empresa se não existir.
  // Se a usuária clicar numa opção depois, renEmpresaSelecionar sobrescreve
  // com o nome exato + id + grupo.
  document.getElementById('mr-empresa').value    = q;
  document.getElementById('mr-empresa-id').value = '';   // texto livre = sem id
  document.getElementById('mr-grupo').value      = '';   // sem grupo até selecionar

  // Sempre lista — vazio mostra as 50 primeiras alfabéticas
  let lista;
  if (q.length === 0) {
    lista = [..._renEmpresas].sort((a,b) =>
      (a.nome || a.empresa || '').localeCompare(b.nome || b.empresa || ''));
  } else {
    const qN = _renNormaliza(q);
    lista = _renEmpresas.filter(e => {
      const n = _renNormaliza(e.nome || e.empresa || '');
      const g = _renNormaliza(e.grupo || '');
      const s = _renNormaliza(e.seguradora || '');
      return n.includes(qN) || g.includes(qN) || s.includes(qN);
    });
    // Prioriza: empresas que COMEÇAM com o termo aparecem antes
    lista.sort((a,b) => {
      const aN = _renNormaliza(a.nome || a.empresa || '');
      const bN = _renNormaliza(b.nome || b.empresa || '');
      const aStarts = aN.startsWith(qN) ? 0 : 1;
      const bStarts = bN.startsWith(qN) ? 0 : 1;
      return aStarts - bStarts || aN.localeCompare(bN);
    });
  }

  // Dedup: chave = nome + "|" + seguradora
  const vistas = new Set();
  const unicas = [];
  for (const e of lista) {
    const key = ((e.nome || e.empresa || '') + '|' + (e.seguradora || '')).toLowerCase();
    if (vistas.has(key)) continue;
    vistas.add(key);
    unicas.push(e);
    if (unicas.length >= 50) break;
  }

  // Opção "usar como empresa nova" (texto livre) — aparece quando há texto digitado
  const opcaoNova = q.length >= 2 ? `
    <div class="ren-emp-opt-nova" onclick="renEmpresaUsarTexto()"
         style="padding:10px 14px;cursor:pointer;border-top:2px solid var(--accent);background:rgba(0,191,179,.06);font-size:12.5px;display:flex;align-items:center;gap:8px"
         onmouseover="this.style.background='rgba(0,191,179,.14)'"
         onmouseout="this.style.background='rgba(0,191,179,.06)'">
      <span class="material-symbols-rounded" style="font-size:18px;color:var(--accent)">add_circle</span>
      <span>Usar "<strong>${esc(q)}</strong>" como empresa nova</span>
    </div>` : '';

  if (!unicas.length) {
    results.innerHTML = '<div style="padding:14px;color:var(--muted);font-size:13px;text-align:center">Nenhuma empresa cadastrada com "<strong>'+esc(q)+'</strong>"</div>' + opcaoNova;
    results.style.display = 'block';
    return;
  }

  results.innerHTML = unicas.map((e, i) => {
    const nome  = e.nome || e.empresa || '';
    const grupo = e.grupo || '';
    const seg   = e.seguradora || '';
    const contrato = e.numero_contrato || '';
    const sub = [grupo && grupo !== nome ? grupo : '', seg, contrato].filter(Boolean).join(' · ');
    return `<div class="ren-emp-opt" data-idx="${i}" data-id="${e.id||''}" data-nome="${escAttr(nome)}" data-grupo="${escAttr(grupo)}"
              onclick="renEmpresaSelecionar(this)"
              onmouseenter="renEmpresaHover(${i})"
              style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border);font-size:13px;transition:background .1s">
              <div style="font-weight:600;color:var(--text);line-height:1.3">${_renHighlight(nome, q)}</div>
              ${sub ? `<div style="font-size:11.5px;color:var(--muted);margin-top:3px">${_renHighlight(sub, q)}</div>` : ''}
            </div>`;
  }).join('');
  // Contador no rodapé
  if (unicas.length >= 50) {
    results.innerHTML += '<div style="padding:8px;text-align:center;font-size:11px;color:var(--muted);background:rgba(0,0,0,.04);border-top:1px solid var(--border)">Mostrando 50 primeiras — refine a busca pra ver mais</div>';
  }
  // Opção de criar nova sempre disponível no rodapé
  results.innerHTML += opcaoNova;
  results.style.display = 'block';
}

// Usa o texto digitado como nome de empresa nova (não veio da lista)
function renEmpresaUsarTexto(){
  const txt = (document.getElementById('mr-empresa-search').value || '').trim();
  document.getElementById('mr-empresa').value    = txt;
  document.getElementById('mr-empresa-id').value = '';   // nova = sem id
  document.getElementById('mr-grupo').value      = '';   // sem grupo
  document.getElementById('mr-empresa-results').style.display = 'none';
}

function renEmpresaHover(idx){ _renEmpresaIdx = idx; _renEmpresaAtualizaDestaque(); }

function _renEmpresaAtualizaDestaque(){
  const items = document.querySelectorAll('#mr-empresa-results .ren-emp-opt');
  items.forEach((el, i) => {
    el.style.background = (i === _renEmpresaIdx) ? 'rgba(0,191,179,.18)' : 'transparent';
  });
  // Auto-scroll pra trazer o item destacado pra vista
  if (_renEmpresaIdx >= 0 && items[_renEmpresaIdx]) {
    items[_renEmpresaIdx].scrollIntoView({ block: 'nearest' });
  }
}

// Tecla pressionada no input — ESC fecha, Enter seleciona, setas navegam
function renEmpresaKey(ev){
  const results = document.getElementById('mr-empresa-results');
  const items = results ? results.querySelectorAll('.ren-emp-opt') : [];
  if (!items.length) return;

  if (ev.key === 'ArrowDown') {
    ev.preventDefault();
    _renEmpresaIdx = Math.min(_renEmpresaIdx + 1, items.length - 1);
    _renEmpresaAtualizaDestaque();
  } else if (ev.key === 'ArrowUp') {
    ev.preventDefault();
    _renEmpresaIdx = Math.max(_renEmpresaIdx - 1, 0);
    _renEmpresaAtualizaDestaque();
  } else if (ev.key === 'Enter') {
    ev.preventDefault();
    const sel = _renEmpresaIdx >= 0 ? items[_renEmpresaIdx] : items[0];
    if (sel) renEmpresaSelecionar(sel);
  } else if (ev.key === 'Escape') {
    if (results) results.style.display = 'none';
  }
}

// Quando clica em uma empresa do resultado: preenche os hidden e fecha
function renEmpresaSelecionar(el){
  const nome  = el.dataset.nome || '';
  const grupo = el.dataset.grupo || '';
  const id    = el.dataset.id || '';
  document.getElementById('mr-empresa-search').value = nome;
  document.getElementById('mr-empresa').value        = nome;
  document.getElementById('mr-empresa-id').value     = id;
  document.getElementById('mr-grupo').value          = grupo;
  document.getElementById('mr-empresa-results').style.display = 'none';
  _renEmpresaIdx = -1;
}

// Fecha o dropdown se clica fora dele
function renEmpresaClickFora(e){
  const wrap   = document.getElementById('mr-empresa-search');
  const result = document.getElementById('mr-empresa-results');
  if (!wrap || !result) return;
  if (e.target !== wrap && !result.contains(e.target)) {
    result.style.display = 'none';
  }
  document.addEventListener('click', renEmpresaClickFora, { once: true });
}

// Mantida pra compat com chamadas antigas
function renUpdateEmpresas(){ /* obsoleta: o autocomplete substitui */ }
function renOnEmpresaChange(){ /* idem */ }

async function renEdit(id){
  const r = renData.find(x => x.id == id);
  if (!r){ toast('Renovação não encontrada'); return; }

  openModalRen();
  document.getElementById('mr-title').textContent = `Editar Renovação #${id}`;
  document.getElementById('mr-btn-del').style.display = '';
  document.getElementById('mr-id').value = id;

  setSelectVal('mr-origem',     r.origem);
  setSelectVal('mr-ramo',       r.ramo);
  setSelectVal('mr-seguradora', r.seguradora);
  setVal      ('mr-contrato',   r.numero_contrato);
  setVal      ('mr-cnpj',       r.cnpj);
  setSelectVal('mr-analista-sel', r.analista_nome);
  setVal      ('mr-anal-seg',   r.analista_seguradora);
  // Empresa: pré-preenche o autocomplete com a empresa atual
  const empNome = r.empresa || '';
  document.getElementById('mr-empresa-search').value = empNome;
  document.getElementById('mr-empresa').value        = empNome;
  document.getElementById('mr-empresa-id').value     = r.empresa_id || '';
  document.getElementById('mr-grupo').value          = r.grupo || '';
  setSelectVal('mr-status',     r.status_renovacao || 'A iniciar');
  setSelectVal('mr-aditivo',    r.aditivo);
  // Coluna real do banco é `porte_empresa`. Aceita fallback no nome legado `porte`
  // pra não quebrar registros antigos exportados antes da padronização.
  setSelectVal('mr-porte',      _renNormalizePorte(r.porte_empresa || r.porte || ''));
  setVal('mr-ini-vig',  r.inicio_vigencia);
  // Banco grava como DATE (YYYY-MM-DD); input type=date aceita esse formato direto
  setVal('mr-mes-ren',    r.mes_renovacao ? String(r.mes_renovacao).slice(0,10) : '');
  setVal('mr-ini-trat',   r.inicio_tratativa);
  setVal('mr-vidas',      r.numero_vidas);
  setVal('mr-perc-com',   _fmtPctEdit(r.percentual_comissao));
  setVal('mr-sinistr',    _fmtPctEdit(r.sinistralidade));
  setVal('mr-perc-prop',  _fmtPctEdit(r.percentual_proposto));
  setVal('mr-perc-fim',   _fmtPctEdit(r.percentual_final));
  setVal('mr-prem-ant',   numToBR(r.premio_anterior));
  setVal('mr-prem-reaj',  numToBR(r.premio_reajustado));
  renCalc(); // recalcula Receita a Mais
  setVal('mr-resp-nome',  r.resp_nome);
  setVal('mr-resp-email', r.resp_email);
  setVal('mr-resp-tel',   r.resp_telefone);
  setVal('mr-obs',        r.observacao);
}
function setVal(id, v){
  const el = document.getElementById(id);
  if (el) el.value = (v == null ? '' : v);
}

// ── Setter de SELECT tolerante a caixa/acento/espaços ─────────────
// `el.value = 'PAMELA'` só seleciona se existir uma option exata; se o cadastro
// tiver 'Pâmela' (acento) ou 'Pamela' (caixa diferente), o select fica vazio.
// Esta versão faz fallback: tenta match exato, depois normalizado (lower + sem
// acento), em value e text, e usa o valor real da option pra preservar o que o
// servidor espera no submit. Não cria option nova — se não houver match,
// deixa o select no estado anterior (limpo se foi recém-aberto).
function _renNormCmp(s){
  return String(s == null ? '' : s).trim().toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g,'');
}
function setSelectVal(id, v){
  const el = document.getElementById(id);
  if (!el) return;
  if (v == null || v === ''){ el.value = ''; return; }
  // 1) match exato
  for (let i = 0; i < el.options.length; i++){
    if (el.options[i].value === v){ el.value = v; return; }
  }
  // 2) match normalizado por value
  const t = _renNormCmp(v);
  for (let i = 0; i < el.options.length; i++){
    if (_renNormCmp(el.options[i].value) === t){
      el.value = el.options[i].value; return;
    }
  }
  // 3) match normalizado por text (caso value seja diferente do text)
  for (let i = 0; i < el.options.length; i++){
    if (_renNormCmp(el.options[i].text) === t){
      el.value = el.options[i].value; return;
    }
  }
  // 4) Sem match — deixa como está (com placeholder vazio)
}

async function renSave(){
  const id = document.getElementById('mr-id').value;
  // Data de renovação já vem como YYYY-MM-DD do input type=date
  const mesRen = val('mr-mes-ren');

  // Resolve analista_id pelo nome
  const analistaNome = val('mr-analista-sel');
  const analista = (_renAnalistas||[]).find(a => a.nome === analistaNome);

  const body = {
    id: id || null,
    // Regra: origem vazia sempre vira 'INTERWEG' (defesa no front)
    origem:           (val('mr-origem') || '').trim() || 'INTERWEG',
    ramo:             val('mr-ramo'),
    seguradora:       val('mr-seguradora'),
    numero_contrato:  val('mr-contrato'),
    cnpj:             val('mr-cnpj'),
    analista_id:      analista ? analista.id : null,
    analista_nome:    analistaNome,
    analista_seguradora: val('mr-anal-seg'),
    grupo:            val('mr-grupo'),
    empresa:          val('mr-empresa'),
    empresa_id:       parseInt(val('mr-empresa-id'), 10) || null,
    status_renovacao: val('mr-status'),
    aditivo:          val('mr-aditivo'),
    porte_empresa:    val('mr-porte'),
    inicio_vigencia:  val('mr-ini-vig')   || null,
    mes_renovacao:    mesRen              || null,
    inicio_tratativa: val('mr-ini-trat')  || null,
    numero_vidas:        val('mr-vidas')                 || null,
    percentual_comissao: brToNum(val('mr-perc-com'))    || null,
    sinistralidade:      brToNum(val('mr-sinistr'))     || null,
    percentual_proposto: brToNum(val('mr-perc-prop'))   || null,
    percentual_final:    brToNum(val('mr-perc-fim'))    || null,
    premio_anterior:     brToNum(val('mr-prem-ant'))    || null,
    premio_reajustado:   brToNum(val('mr-prem-reaj'))   || null,
    resp_nome:     val('mr-resp-nome'),
    resp_email:    val('mr-resp-email'),
    resp_telefone: val('mr-resp-tel'),
    observacao:    val('mr-obs')
  };

  // Só Empresa é obrigatória (Grupo foi removido do modal — é deduzido
  // da empresa selecionada, ou fica vazio quando é empresa nova/texto livre).
  if (!body.empresa){
    toast('Preencha a Empresa');
    return;
  }

  const action = id ? 'editar_renovacao' : 'criar_renovacao';
  const r = await apiPost(action, body);
  if (r.ok){
    toast(id ? 'Renovação atualizada' : 'Renovação criada');
    closeModal('mod-ren');
    renLoad();
    renLoadStats();
  } else {
    toast('Erro: ' + (r.msg || 'falha ao salvar'));
  }
}

async function renDelete(){
  const id = document.getElementById('mr-id').value;
  if (!id) return;
  if (!confirm(`Excluir renovação #${id}?`)) return;
  const r = await apiGet('excluir_renovacao', {id});
  if (r.ok){
    toast('Renovação excluída');
    closeModal('mod-ren');
    renLoad();
    renLoadStats();
  } else {
    toast('Erro: ' + (r.msg || 'falha ao excluir'));
  }
}

function renExportXLSX(){
  if (!renData || !renData.length){ toast('Nenhuma renovação para exportar'); return; }
  if (typeof XLSX === 'undefined'){ toast('Biblioteca XLSX não carregada'); return; }

  // Formata uma data YYYY-MM-DD pra DD/MM/AAAA
  const fmtBr = s => {
    if (!s) return '';
    const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
  };
  // Calcula MÊS no formato pedido (DD/MM/ano-atual baseado no dia/mês da vigência)
  const anoAtual = new Date().getFullYear();
  const fmtMes = (vig) => {
    if (!vig) return '';
    const m = String(vig).match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${anoAtual}` : '';
  };
  const numBr = v => {
    if (v == null || v === '') return '';
    const n = parseFloat(v);
    return isNaN(n) ? v : n;
  };
  const moedaBr = v => {
    const n = parseFloat(v);
    return isNaN(n) ? '' : n;
  };

  // Cabeçalho final (igual o que combinamos)
  const headers = [
    'EMPRESA','RAMO','SEGURADORA','CONTRATO','CLIENTE','Analista',
    'MÊS','Nº de vidas','PORTE','VIGÊNCIA','% COMISSÃO','Início Tratativa',
    'STATUS','Sinistralidade','% Proposto','% Finalizado','Tipo','Aditivo',
    '$ Prêmio Anterior','$ Prêmio Reajustado','$ Receita','Obs.'
  ];

  // Monta as linhas
  const rows = renData.map(d => {
    // Coalesce: ramo/seguradora vêm do JOIN com COALESCE(r.x, e.x)
    const ramo       = d.ramo       || '';
    const seguradora = d.seguradora || '';
    const cliente    = d.empresa    || '';
    const contrato   = d.numero_contrato || '';
    const vigencia   = d.inicio_vigencia || '';
    const percCom    = numBr(d.percentual_comissao);
    const sin        = numBr(d.sinistralidade);
    const pProp      = numBr(d.percentual_proposto || d.perc_proposto);
    const pFim       = numBr(d.percentual_final    || d.perc_finalizado);
    const pAnt       = moedaBr(d.premio_anterior);
    const pReaj_raw  = d.premio_reajustado || d.premio_reajuste;
    let pReaj        = moedaBr(pReaj_raw);
    // Se reajustado vazio mas tiver anterior + %final, calcula
    if ((!pReaj && pReaj !== 0) && pAnt && pFim) {
      pReaj = pAnt * (1 + pFim/100);
    }
    // Receita = Reajustado × % Comissão / 100
    const receita = (pReaj && percCom) ? (pReaj * percCom / 100) : '';

    return [
      d.origem      || '',
      ramo,
      seguradora,
      contrato,
      cliente,
      d.analista_nome || '',
      fmtMes(vigencia),
      numBr(d.numero_vidas),
      d.porte_empresa || d.porte || '',
      fmtBr(vigencia),
      percCom,
      fmtBr(d.inicio_tratativa),
      d.status_renovacao || d.status || '',
      sin,
      pProp,
      pFim,
      d.tipo    || '',
      d.aditivo || '',
      pAnt,
      pReaj,
      receita,
      d.observacao || d.observacoes || ''
    ];
  });

  // Cria worksheet
  const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);

  // ── Estilos (xlsx-js-style) ───────────────────────────────
  const HEADER_STYLE = {
    fill:      { fgColor:{ rgb:'00C9A7' } },
    font:      { name:'Arial', sz:11, bold:true, color:{ rgb:'FFFFFF' } },
    alignment: { horizontal:'center', vertical:'center', wrapText:true },
    border:    {
      top:    { style:'thin', color:{ rgb:'B8E6DC' } },
      bottom: { style:'thin', color:{ rgb:'B8E6DC' } },
      left:   { style:'thin', color:{ rgb:'B8E6DC' } },
      right:  { style:'thin', color:{ rgb:'B8E6DC' } }
    }
  };
  const DATA_STYLE = {
    font:      { name:'Arial', sz:10 },
    alignment: { vertical:'center' },
    border:    {
      top:    { style:'thin', color:{ rgb:'E0E0E0' } },
      bottom: { style:'thin', color:{ rgb:'E0E0E0' } },
      left:   { style:'thin', color:{ rgb:'E0E0E0' } },
      right:  { style:'thin', color:{ rgb:'E0E0E0' } }
    }
  };
  const ALT_FILL = { fill:{ fgColor:{ rgb:'F8F9FA' } } };

  // Aplica estilos no header
  for (let c = 0; c < headers.length; c++){
    const ref = XLSX.utils.encode_cell({ r:0, c });
    if (ws[ref]) ws[ref].s = HEADER_STYLE;
  }
  // Aplica estilos nos dados
  const moedaCols = [18, 19, 20]; // Prêmio Ant, Prêmio Reaj, Receita
  const numCols   = [7, 10, 13, 14, 15]; // Vidas, %Com, Sinistr, %Prop, %Fim
  for (let r = 1; r <= rows.length; r++){
    const altStyle = (r % 2 === 0) ? { ...DATA_STYLE, fill:ALT_FILL.fill } : DATA_STYLE;
    for (let c = 0; c < headers.length; c++){
      const ref = XLSX.utils.encode_cell({ r, c });
      if (!ws[ref]) continue;
      let s = { ...altStyle };
      if (moedaCols.includes(c)){
        s = { ...s, numFmt:'R$ #,##0.00' };
      } else if (numCols.includes(c)){
        s = { ...s, numFmt:'0.00' };
      }
      ws[ref].s = s;
    }
  }

  // Largura das colunas
  ws['!cols'] = [
    {wch:14},{wch:11},{wch:18},{wch:14},{wch:32},{wch:14},  // EMPRESA-Analista
    {wch:12},{wch:11},{wch:22},{wch:12},{wch:11},{wch:14},  // MÊS-Início Trat
    {wch:18},{wch:13},{wch:11},{wch:12},{wch:14},{wch:14},  // STATUS-Aditivo
    {wch:18},{wch:18},{wch:16},{wch:40}                      // Prêmios + Receita + Obs
  ];
  // Altura do header
  ws['!rows'] = [{ hpt:32 }];
  // Freeze pane (linha 1) + autofilter
  ws['!freeze'] = { xSplit:0, ySplit:1 };
  ws['!autofilter'] = { ref: 'A1:' + XLSX.utils.encode_col(headers.length-1) + (rows.length+1) };

  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Renovações');
  XLSX.writeFile(wb, 'renovacoes_' + new Date().toISOString().slice(0,10) + '.xlsx');
}

// ═══════════════════════════════════════════════════════════════
// IMPORTAR XLSX — atualiza renovações em massa do modelo Interweg
// Aceita planilha com aba "RENOVAÇÕES" e header na linha 11.
// Faz upsert por (numero_contrato + cnpj) ou (cliente + seguradora).
// ═══════════════════════════════════════════════════════════════

const REN_COLS = [
  { keys:['empresa'],                                                    map:'origem' },        // EMPRESA = origem (INTERWEG/FINLÂNDIA)
  { keys:['ramo'],                                                       map:'ramo' },
  { keys:['seguradora'],                                                 map:'seguradora' },
  { keys:['contrato','numero contrato','nº contrato','contratoº'],       map:'numero_contrato' },
  { keys:['cliente'],                                                    map:'empresa' },       // CLIENTE = empresa real
  { keys:['analista','analista responsavel','responsavel'],              map:'analista_nome' },
  { keys:['nº de vidas','n de vidas','vidas','numero de vidas'],         map:'numero_vidas' },
  { keys:['porte','porte_empresa','porte da empresa'],                    map:'porte_empresa' },
  { keys:['vigencia','vigência','inicio vigencia','início vigência'],    map:'inicio_vigencia' },
  { keys:['% comissao','% comissão','comissao','comissão'],              map:'percentual_comissao' },
  { keys:['mes','mês','mes renovacao','mês renovação','mes de renovacao'], map:'mes_renovacao' },
  { keys:['inicio tratativa','início tratativa','inicio tratativa2'],    map:'inicio_tratativa' },
  { keys:['status'],                                                     map:'status_renovacao' },
  { keys:['sinistralidade'],                                             map:'sinistralidade' },
  { keys:['% proposto','proposto'],                                      map:'percentual_proposto' },
  { keys:['% finalizado','finalizado','finalizado %'],                   map:'percentual_final' },
  { keys:['tipo'],                                                       map:'tipo' },
  { keys:['aditivo'],                                                    map:'aditivo' },
  { keys:['$ premio anterior','$ prêmio anterior','premio anterior','prêmio anterior'], map:'premio_anterior' },
  { keys:['$ premio reajustado','$ prêmio reajustado','premio reajustado','prêmio reajustado','premio reajuste','prêmio reajuste'], map:'premio_reajustado' },
  { keys:['obs','obs.','observacao','observação','observacoes','observações'], map:'observacao' },
  { keys:['cnpj'],                                                       map:'cnpj' }
];

function _renNormHeader(s){
  return String(s||'').trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/\s+/g,' ');
}

function _renParseDate(v){
  if (v == null || v === '') return null;
  if (v instanceof Date) return v.toISOString().slice(0,10);
  if (typeof v === 'number' && typeof XLSX !== 'undefined' && XLSX.SSF){
    const o = XLSX.SSF.parse_date_code(v);
    if (o) return `${o.y}-${String(o.m).padStart(2,'0')}-${String(o.d).padStart(2,'0')}`;
  }
  const s = String(v).trim();
  let m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
  if (m) return `${m[1]}-${m[2].padStart(2,'0')}-${m[3].padStart(2,'0')}`;
  m = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
  if (m){
    let y = m[3]; if (y.length === 2) y = '20' + y;
    return `${y}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`;
  }
  return null;
}

function _renParseNum(v){
  if (v == null || v === '' || v === '-') return null;
  if (typeof v === 'number') return v;
  const s = String(v).trim().replace(/[R$\s%]/g,'').replace(/\./g,'').replace(',','.');
  const n = parseFloat(s);
  return isNaN(n) ? null : n;
}

// Normaliza ORIGEM da planilha (nomes de marca) pros valores do enum em empresas
// ("Interweg MG", "Interweg JF", "Interweg SP"). FINLÂNDIA e demais não-mapeáveis
// retornam null pra evitar erro de enum.
function _renNormalizeOrigem(v){
  if (v == null || v === '') return null;
  const s = String(v).trim().toUpperCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
  if (!s) return null;
  if (s === 'INTERWEG' || s === 'INTERWEG MG') return 'Interweg MG';
  if (s === 'INTERWEG SP') return 'Interweg SP';
  if (s === 'INTERWEG JF') return 'Interweg JF';
  // INTERWEG | FINLANDIA é ambíguo — default pra MG
  if (s.includes('INTERWEG') && s.includes('FINLANDIA')) return 'Interweg MG';
  // FINLÂNDIA sozinho ou outras marcas (VALENTE JUNIOR) ficam null
  return null;
}

// Normaliza o campo "Porte da empresa" para os valores oficiais do dropdown.
// Aceita variações comuns da planilha original ("Entre 30 e 100" sem "vidas",
// "3 a 29 vidas", "30 a 99 vidas", "acima de 100 vidas" — formatos legados).
function _renNormalizePorte(v){
  if (v == null || v === '') return '';
  const s = String(v).trim().toLowerCase();
  if (!s) return '';
  // IMPORTANTE: checar "1000" ANTES de "100", senão "acima de 1000"
  // casa por substring com "acima de 100" e vira "Entre 100 e 900" por engano.
  if (s.includes('1000')     || s.includes('acima de 1000')) return 'Acima de 1000';
  if (s.includes('até 30')   || s.startsWith('3 a 29') || s.includes('até 29')) return 'Até 30 vidas';
  if (s.includes('30 e 100') || s.startsWith('30 a 99') || s.includes('30 a 100')) return 'Entre 30 e 100 vidas';
  if (s.includes('100 e 900') || s.includes('100 a 900') || s.includes('acima de 100')) return 'Entre 100 e 900';
  // Caso não bata com nenhum padrão, devolve o valor original limpo
  return String(v).trim();
}

// Mostra modal explicativo antes de abrir o seletor de arquivo
function renShowImportInfo(){
  const cols = ['EMPRESA (origem: INTERWEG/FINLÂNDIA)', 'RAMO (Saúde/Odonto/Vida)', 'SEGURADORA',
    'CONTRATO', 'CLIENTE (nome real)', 'Analista', 'Nº de vidas', 'PORTE',
    'VIGÊNCIA (data)', '% COMISSÃO', 'Início Tratativa', 'STATUS',
    'Sinistralidade (%)', '% Proposto', '% Finalizado', 'Tipo (Renovação/Migração)',
    'Aditivo', '$ Prêmio Anterior', '$ Prêmio Reajustado', 'Obs', 'CNPJ (opcional)'];
  const msg = `📋 IMPORTAR PLANILHA DE RENOVAÇÕES

✓ A planilha pode ter o cabeçalho na linha 1 OU na linha 11 (modelo Interweg).
✓ A aba pode chamar "RENOVAÇÕES" ou ser a primeira aba.
✓ Cada linha vira/atualiza uma renovação (chave: CONTRATO + CLIENTE + SEGURADORA).

📑 Colunas reconhecidas (em qualquer ordem):
${cols.map((c,i) => `   ${i+1}. ${c}`).join('\n')}

⚠️ COMO FUNCIONA:
• Se já existe renovação com mesmo CONTRATO + CLIENTE + SEGURADORA → ATUALIZA
• Se não existe → INSERE nova
• Linhas sem CLIENTE são ignoradas

Clique OK pra escolher o arquivo XLSX.`;
  if (confirm(msg)) {
    document.getElementById('ren-import-file').click();
  }
}

// Baixa um modelo XLSX em branco com 2 linhas de exemplo
function renDownloadModelo(){
  if (typeof XLSX === 'undefined'){ alert('Biblioteca XLSX não carregou. Aguarde 2 segundos.'); return; }
  const header = ['EMPRESA','RAMO','SEGURADORA','CONTRATO','CLIENTE','Analista','Nº de vidas','PORTE',
    'VIGÊNCIA','% COMISSÃO','Início Tratativa','STATUS','Sinistralidade','% Proposto','% Finalizado',
    'Tipo','Aditivo','$ Prêmio Anterior','$ Prêmio Reajustado','Obs','CNPJ'];
  const ex1 = ['INTERWEG','SAÚDE','UNIMED BH','142789','TECNOFINK','PAMELA',150,'30 a 99 vidas',
    '2025-08-15',5,'2026-04-10','Em tratativa',0.68,15,12,'Renovação','Não',12500,14000,'Cliente em negociação','12.345.678/0001-90'];
  const ex2 = ['FINLÂNDIA','ODONTO','METLIFE','08832','ITAMINAS','BRUNA',80,'30 a 99 vidas',
    '2025-09-20',2,'','A iniciar','','','','Renovação','Não',8500,9200,'','98.765.432/0001-21'];
  const linhas = [header, ex1, ex2];
  for (let i = 0; i < 20; i++) linhas.push(header.map(()=>''));
  const ws = XLSX.utils.aoa_to_sheet(linhas);
  ws['!cols'] = header.map(() => ({ wch: 18 }));
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'RENOVAÇÕES');
  XLSX.writeFile(wb, 'modelo_renovacoes_interweg.xlsx');
  if (typeof toast === 'function') toast('Modelo baixado: modelo_renovacoes_interweg.xlsx');
}

async function renImportXLSX(input){
  const file = input.files && input.files[0];
  if (!file) return;
  if (typeof XLSX === 'undefined'){ alert('Biblioteca XLSX não carregou. Aguarde 2 segundos e tente de novo.'); input.value=''; return; }

  console.log('[Renovação] Importando:', file.name, '|', (file.size/1024).toFixed(1), 'KB');

  const reader = new FileReader();
  reader.onload = async e => {
    try {
      const data = new Uint8Array(e.target.result);
      // cellDates:false → datas vêm como NÚMEROS (serial date do Excel).
      // _renParseDate sabe converter via XLSX.SSF.parse_date_code. Isso evita
      // a confusão da coluna MÊS (1-12) ser convertida em Date(1900-01-XX).
      const wb = XLSX.read(data, { type:'array', cellDates:false });

      // Procura aba "RENOVAÇÕES" ou usa a 1ª
      let sheetName = wb.SheetNames.find(n => /renova/i.test(n)) || wb.SheetNames[0];
      const ws = wb.Sheets[sheetName];
      const rows = XLSX.utils.sheet_to_json(ws, { header:1, defval:'', raw:true });

      // Detecta linha do header (pode ser linha 1 ou linha 11 do modelo Interweg)
      let headerIdx = -1;
      for (let i = 0; i < Math.min(15, rows.length); i++){
        const cells = (rows[i]||[]).map(_renNormHeader);
        if (cells.some(c => c === 'empresa') && cells.some(c => /vidas/.test(c))){
          headerIdx = i; break;
        }
      }
      if (headerIdx < 0){ alert('Não achei o cabeçalho na planilha. Esperado: EMPRESA, RAMO, SEGURADORA, CONTRATO, CLIENTE, etc.'); input.value=''; return; }

      const header = rows[headerIdx].map(_renNormHeader);

      // Mapeia colunas pelo nome
      const colIdx = {};
      REN_COLS.forEach(c => {
        const i = header.findIndex(h => c.keys.includes(h));
        colIdx[c.map] = i;
      });

      // Parse das linhas
      const linhas = [];
      for (let i = headerIdx + 1; i < rows.length; i++){
        const r = rows[i]; if (!r) continue;
        const get = (key) => colIdx[key] >= 0 ? r[colIdx[key]] : '';

        const empresa = String(get('empresa')||'').trim();
        if (!empresa) continue;  // pula linha sem cliente

        // DEBUG: log dos 5 primeiros pra ver o tipo do MÊS após mudar cellDates
        if (linhas.length < 5) {
          const _raw = get('mes_renovacao');
          console.log('[Renovação DEBUG] Linha', i, empresa, '| MÊS raw:', _raw, '| typeof:', typeof _raw, '| instanceof Date:', _raw instanceof Date);
        }

        // mes_renovacao é obrigatório. Na planilha de origem, a coluna MÊS é só
        // um NÚMERO do mês (1-12), não uma data completa. Combinamos:
        //   ano  = ano corrente
        //   mês  = valor da coluna MÊS (1-12)
        //   dia  = dia da VIGÊNCIA original
        // Se MÊS for vazio/inválido, cai pra mês+dia da vigência (ano corrente).
        let mesRen = null;
        const mesRaw = get('mes_renovacao');
        const vig    = _renParseDate(get('inicio_vigencia'));
        const hoje   = new Date();
        const anoAtual = hoje.getFullYear();

        // Tenta interpretar MÊS como número (1-12). O Excel formata a coluna
        // como "data", então o SheetJS converte o valor 10 em Date(~1900-01-10).
        // Reconvertemos pra serial number do Excel (dias desde 1899-12-30) pra
        // recuperar o valor original, contornando o bug do ano bissexto 1900.
        let mesNum = null;
        if (mesRaw != null && mesRaw !== '') {
          if (typeof mesRaw === 'number' && mesRaw >= 1 && mesRaw <= 12) {
            mesNum = Math.round(mesRaw);
          } else if (mesRaw instanceof Date) {
            // Excel base: 1899-12-30. Serial 1 = 1899-12-31, Serial 2 = 1900-01-01, etc.
            // (Tradicionalmente seria 1900-01-01, mas o bug do ano bissexto faz shift)
            const excelEpoch = Date.UTC(1899, 11, 30);
            const serial = Math.round((mesRaw.getTime() - excelEpoch) / 86400000);
            if (serial >= 1 && serial <= 12) {
              mesNum = serial;
            } else if (mesRaw.getFullYear() >= 2000) {
              // Data real (ano corrente), usa como mes_renovacao direto
              mesRen = mesRaw.toISOString().slice(0,10);
            }
          } else {
            // Pode ser string "1", "10" ou data formatada
            const asNum = parseFloat(String(mesRaw).replace(',', '.'));
            if (!isNaN(asNum) && asNum >= 1 && asNum <= 12 && Number.isInteger(asNum)) {
              mesNum = asNum;
            } else {
              const parsed = _renParseDate(mesRaw);
              if (parsed && parseInt(parsed.slice(0,4),10) >= 2000) {
                mesRen = parsed;
              }
            }
          }
        }

        // Compõe a data se temos mês + vigência
        if (!mesRen && mesNum && vig) {
          const dia = vig.slice(8, 10); // dd da vigência
          mesRen = `${anoAtual}-${String(mesNum).padStart(2,'0')}-${dia}`;
        }
        // Fallback: sem MÊS válido mas com vigência → usa mês/dia da vigência
        if (!mesRen && vig) {
          mesRen = vig.replace(/^\d{4}/, String(anoAtual));
        }
        // Se ainda assim não tem data válida, pula a linha
        if (!mesRen) {
          console.warn('[Renovação] Linha sem MÊS válido nem VIGÊNCIA, pulada:', empresa);
          continue;
        }

        const obj = {
          origem:        _renNormalizeOrigem(get('origem')),  // INTERWEG → Interweg MG
          ramo:          String(get('ramo')||'').trim(),
          seguradora:    String(get('seguradora')||'').trim(),
          numero_contrato: String(get('numero_contrato')||'').trim(),
          empresa:       empresa,
          cnpj:          String(get('cnpj')||'').trim(),
          analista_nome: String(get('analista_nome')||'').trim(),
          numero_vidas:  _renParseNum(get('numero_vidas')),
          porte_empresa: _renNormalizePorte(get('porte_empresa')),
          inicio_vigencia: vig,
          mes_renovacao: mesRen,
          percentual_comissao: _renParseNum(get('percentual_comissao')),
          inicio_tratativa: _renParseDate(get('inicio_tratativa')),
          status_renovacao: String(get('status_renovacao')||'').trim(),
          sinistralidade: _renParseNum(get('sinistralidade')),
          percentual_proposto: _renParseNum(get('percentual_proposto')),
          percentual_final:    _renParseNum(get('percentual_final')),
          tipo:          String(get('tipo')||'').trim(),
          aditivo:       String(get('aditivo')||'').trim(),
          premio_anterior:   _renParseNum(get('premio_anterior')),
          premio_reajustado: _renParseNum(get('premio_reajustado')),
          observacao:    String(get('observacao')||'').trim()
        };
        linhas.push(obj);
      }

      if (!linhas.length){ alert('Nenhuma linha válida encontrada na planilha.'); input.value=''; return; }

      console.log('[Renovação] Linhas válidas:', linhas.length);

      if (!confirm(`Importar ${linhas.length} renovações?\n\nO sistema vai:\n• ATUALIZAR registros existentes (mesmo CONTRATO + CLIENTE)\n• INSERIR os novos\n\nContinuar?`)) {
        input.value = '';
        return;
      }

      // Chama API direto pra capturar response cru (em caso de PHP fatal)
      let r;
      try {
        const resp = await fetch('api.php?action=importar_renovacao', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ linhas })
        });
        const txt = await resp.text();
        if (!txt) {
          alert('Erro: API retornou resposta vazia (provável erro PHP no servidor).\n\nVerifique:\n1. api.php está atualizado em /public_html/?\n2. PHP error log no Hostinger pode ter mais detalhes.');
          return;
        }
        try { r = JSON.parse(txt); }
        catch (parseErr) {
          alert('Erro: API retornou resposta inválida (não-JSON):\n\n' + txt.substring(0, 500));
          return;
        }
      } catch (fetchErr) {
        alert('Erro de rede: ' + fetchErr.message);
        return;
      }

      if (r.ok){
        const partes = [];
        if (r.atualizados) partes.push(`${r.atualizados} atualizado(s)`);
        if (r.inseridos)   partes.push(`${r.inseridos} inserido(s)`);
        if (r.ignorados)   partes.push(`${r.ignorados} ignorado(s)`);
        toast(`✓ Importação concluída — ${partes.join(' · ') || 'nenhuma alteração'}`, 6000);
        if (r.erros && r.erros.length) {
          // Erros viram log no console (não polui a UI)
          console.warn('[Renovação] Erros na importação:', r.erros);
        }
        renLoad();
        renLoadStats();
      } else {
        toast('Erro na importação: ' + (r.msg || 'falha'), 8000);
        console.error('[Renovação] Falha import:', r);
      }
    } catch (err){
      console.error('[Renovação] Erro import:', err);
      alert('Erro ao ler planilha: ' + err.message);
    } finally {
      input.value = '';
    }
  };
  reader.onerror = () => { alert('Erro ao ler arquivo.'); input.value=''; };
  reader.readAsArrayBuffer(file);
}

// ═══════════════════════════════════════════════════════════════
//  VIRAR ANO (admin) — duplica todas as renovações de um ano para
//  o próximo, igual o "Virar Mês" do faturamento. Status volta para
//  "A iniciar" e campos do ciclo (sinistralidade, % proposto, %
//  finalizado, prêmio reajustado) ficam em branco.
// ═══════════════════════════════════════════════════════════════
function openModalVirarAno(){
  // Popula o select de "Ano de origem" com os anos distintos das renovações carregadas
  const anos = [...new Set((renData||[])
    .map(r => r.mes_renovacao ? String(r.mes_renovacao).slice(0,4) : null)
    .filter(Boolean)
  )].sort();
  const sel = document.getElementById('va-de');
  if (sel){
    if (!anos.length){
      const anoAtual = new Date().getFullYear();
      sel.innerHTML = `<option value="${anoAtual}">${anoAtual}</option>`;
    } else {
      sel.innerHTML = anos.map(a => `<option value="${a}">${a}</option>`).join('');
      // Default: último ano (mais recente)
      sel.value = anos[anos.length - 1];
    }
  }
  // Default destino: ano de origem + 1
  const ipPara = document.getElementById('va-para');
  if (ipPara){
    const deAtual = parseInt(sel?.value, 10) || new Date().getFullYear();
    ipPara.value = deAtual + 1;
  }
  // Atualiza destino quando origem muda
  if (sel) sel.onchange = () => {
    const d = parseInt(sel.value, 10);
    if (!isNaN(d) && ipPara) ipPara.value = d + 1;
  };
  // Usa a função padrão do app pra abrir o modal (adiciona classe `show`)
  openModal('mod-virar-ano');
}

async function renVirarAno(btn){
  const deStr   = document.getElementById('va-de')?.value;
  const paraStr = document.getElementById('va-para')?.value;
  const de   = parseInt(deStr,   10);
  const para = parseInt(paraStr, 10);
  if (!de || !para) { toast('Informe os anos de origem e destino'); return; }
  if (de === para)  { toast('Origem e destino não podem ser iguais'); return; }
  if (!confirm(`Confirma duplicar todas as renovações de ${de} para ${para}?\n\nAs novas entradas vêm com status "A iniciar" e os campos do ciclo (sinistralidade, % proposto/finalizado, prêmio reajustado) ficam em branco.`)) return;

  if (btn) btn.disabled = true;
  try {
    const r = await apiPost('virar_ano_renovacao', { de, para });
    if (r.ok) {
      toast(`✓ ${r.inseridos || 0} renovação(ões) duplicada(s) para ${para}`);
      closeModal('mod-virar-ano');
      renLoad();
    } else {
      alert('Erro: ' + (r.msg || 'tente novamente'));
    }
  } finally {
    if (btn) btn.disabled = false;
  }
}

// ── BOOT ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', renInit);