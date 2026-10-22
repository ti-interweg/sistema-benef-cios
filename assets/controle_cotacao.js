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
window.ccToggleNotifEmail = window.ccToggleNotifEmail || function(){};
setTimeout(function(){
  if (typeof ccOpenModal  === 'function') window.ccOpenModal  = ccOpenModal;
  if (typeof ccLoad       === 'function') window.ccLoad       = ccLoad;
  if (typeof ccEdit       === 'function') window.ccEdit       = ccEdit;
  if (typeof ccSave       === 'function') window.ccSave       = ccSave;
  if (typeof ccDelete     === 'function') window.ccDelete     = ccDelete;
  if (typeof ccExportXLSX === 'function') window.ccExportXLSX = ccExportXLSX;
  if (typeof ccToggleNotifEmail === 'function') window.ccToggleNotifEmail = ccToggleNotifEmail;
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
    'AGUARDANDO LIBERAÇÃO DE COTAÇÃO': 'ccs-aguardando-liberacao',
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

    const tiposExcluir = ['admin','head','financeiro','enfermeira','enfermagem','visualizador'];
    candidatos = candidatos.filter(a => {
      const t = (a.tipo||'').toLowerCase();
      if (tiposExcluir.includes(t)) {
        return (ccData||[]).some(c => _ccPrimeiroNome(c.analista) === _ccPrimeiroNome(a.nome));
      }
      return true;
    });

    const porPrimeiro = new Map();
    candidatos.forEach(a => {
      const chave = _ccPrimeiroNome(a.nome);
      if (!chave) return;
      const atual = porPrimeiro.get(chave);
      if (!atual || (a.nome||'').length < (atual.nome||'').length) {
        porPrimeiro.set(chave, a);
      }
    });

    let lista = [...porPrimeiro.values()].sort((a,b) => (a.nome||'').localeCompare(b.nome||'', 'pt'));

    // WHITELIST do Controle de Cotacao - so Mariana e Ingridh
    const PERMITIDOS = ['mariana', 'ingridh', 'ingrid'];
    lista = lista.filter(a => PERMITIDOS.includes(_ccPrimeiroNome(a.nome)));

    // Garante as duas opcoes mesmo sem cotacoes existentes
    const presentes = new Set(lista.map(a => _ccPrimeiroNome(a.nome)));
    if (!presentes.has('mariana')) lista.push({ nome: 'Mariana', tipo: 'analista' });
    if (!presentes.has('ingridh') && !presentes.has('ingrid')) lista.push({ nome: 'Ingridh', tipo: 'analista' });
    lista.sort((a,b) => (a.nome||'').localeCompare(b.nome||'', 'pt'));
    _ccAnalistas = lista;

    const sel  = document.getElementById('mc-analista');
    const filt = document.getElementById('cf-analista');
    if (sel)  sel.innerHTML  = '<option value="">Selecione...</option>' + lista.map(a => `<option value="${esc(a.nome)}">${esc(a.nome)}</option>`).join('');
    if (filt) filt.innerHTML = '<option value="">Todos os analistas</option>' + lista.map(a => `<option value="${esc(a.nome)}">${esc(a.nome)}</option>`).join('');
  } catch (e) { console.warn('ccCarregarAnalistas:', e); }
}

async function ccLoad(){
  const params = {
    search:    val('cf-search'),
    status:    val('cf-status'),
    entrada:   val('cf-entrada'),
    tipo:      val('cf-tipo'),
    ramo:      val('cf-ramo'),
    analista:  val('cf-analista'),
    de:        val('cf-de'),
    ate:       val('cf-ate')
  };
  Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
  const r = await apiGet('listar_cotacoes', params);
  if (!r.ok) { toast('Erro ao carregar cotações: ' + (r.msg || '')); return; }
  ccData = r.data || [];
  ccRenderTable();
  ccRenderStats();
}

function ccRenderStats(){
  const total = ccData.length;
  const stUp = d => String(d.status||'').toUpperCase().trim();
  const emEstudo = ccData.filter(d => {
    const s = stUp(d);
    return s==='AGUARDANDO DADOS' || s==='AGUARDANDO LIBERAÇÃO DE COTAÇÃO'
        || s==='INICIAR COTAÇÃO' || s==='EM COTAÇÃO'
        || s==='ESTUDO APRESENTADO' || s==='AGUARDANDO DOCUMENTAÇÃO'
        || s==='EM NEGOCIAÇÃO COM A OPERADORA' || s==='PASSAGEM DE BASTÃO'
        || s==='INICIAR NOMEAÇÃO' || s==='EM NOMEAÇÃO';
  }).length;
  const finalizadas = ccData.filter(d => {
    const s = stUp(d);
    return s==='IMPLANTAÇÃO FINALIZADA' || s==='NOMEAÇÃO CONCLUÍDA'
        || s==='INICIAR IMPLANTAÇÃO' || s==='EM IMPLANTAÇÃO';
  }).length;
  const declinadas = ccData.filter(d => stUp(d)==='DECLINADA').length;
  setText('cc-total',  total);
  setText('cc-estudo', emEstudo);
  setText('cc-impl',   finalizadas);
  setText('cc-dec',    declinadas);
}

const _CC_GRUPOS = {
  estudo: ['AGUARDANDO DADOS','AGUARDANDO LIBERAÇÃO DE COTAÇÃO','INICIAR COTAÇÃO','EM COTAÇÃO','ESTUDO APRESENTADO',
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
}

function ccRenderTable(){
  const tbody = document.getElementById('cc-tbody');
  let dados = ccData;
  if (_ccCardFilter && _CC_GRUPOS[_ccCardFilter]) {
    const grupo = _CC_GRUPOS[_ccCardFilter];
    dados = ccData.filter(d => grupo.includes(String(d.status||'').toUpperCase().trim()));
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
    <td><button class="btn btn-outline btn-sm" onclick="ccEdit(${d.id})">Editar</button></td>
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
  ['mc-id','mc-razao','mc-obs','mc-recebimento','mc-prox-acao','mc-vidas','mc-operadora-atual',
   'mc-data-solic','mc-data-envio','mc-players','mc-data-sinaliz','mc-player-contratado',
   'mc-premio','mc-data-final','mc-motivo','mc-destinatarios-email'].forEach(id => setVal(id, ''));
  const cb = document.getElementById('mc-notificar-email');
  if (cb) cb.checked = false;
  ccToggleNotifEmail();
  setVal('mc-status', 'Recebida');
  setVal('mc-entrada', '');
  setVal('mc-analista', '');
  setVal('mc-tipo', '');
  setVal('mc-ramo', '');
  setVal('mc-recebimento', new Date().toISOString().slice(0,10));
  openModal('mod-cc');
}

function ccEdit(id){
  const d = ccData.find(x => x.id == id);
  if (!d) { toast('Cotação não encontrada'); return; }
  openModal('mod-cc');
  document.getElementById('mc-title').innerHTML = `<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">request_quote</span> Editar Cotação #${id}`;
  document.getElementById('mc-btn-del').style.display = '';
  setVal('mc-id', id);
  setVal('mc-razao',             d.razao_social);
  setVal('mc-status',            d.status);
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
  setVal('mc-players',           d.players_cotados);
  setVal('mc-data-sinaliz',      d.data_sinalizacao);
  setVal('mc-player-contratado', d.player_contratado);
  setVal('mc-premio',            d.premio != null ? numToBR(d.premio) : '');
  setVal('mc-data-final',        d.data_finalizacao);
  setVal('mc-motivo',            d.motivo_declinio);
  const cb = document.getElementById('mc-notificar-email');
  if (cb) cb.checked = (d.notificar_email_passagem == 1 || d.notificar_email_passagem === '1' || d.notificar_email_passagem === true);
  setVal('mc-destinatarios-email', d.destinatarios_email_passagem || '');
  ccToggleNotifEmail();
}

function ccToggleNotifEmail(){
  const cb = document.getElementById('mc-notificar-email');
  const box = document.getElementById('mc-notif-email-box');
  if (!cb || !box) return;
  box.style.display = cb.checked ? '' : 'none';
}

async function ccSave(){
  const id = document.getElementById('mc-id').value;
  const body = {
    id: id || null,
    razao_social:        val('mc-razao').trim(),
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
    players_cotados:     val('mc-players').trim(),
    data_sinalizacao:    val('mc-data-sinaliz') || null,
    player_contratado:   val('mc-player-contratado').trim(),
    premio:              val('mc-premio').trim() === '' ? null : brToNum(val('mc-premio')),
    data_finalizacao:    val('mc-data-final') || null,
    motivo_declinio:     val('mc-motivo').trim(),
    notificar_email_passagem:     (document.getElementById('mc-notificar-email') && document.getElementById('mc-notificar-email').checked) ? 1 : 0,
    destinatarios_email_passagem: val('mc-destinatarios-email').trim()
  };

  if (!body.razao_social) { toast('Preencha a Razão Social'); return; }
  if (!body.status)       { toast('Selecione o Status'); return; }
  if (!body.entrada)      { toast('Selecione a Entrada'); return; }
  if (!body.tipo)         { toast('Selecione o Tipo'); return; }
  if (!body.ramo)         { toast('Selecione o Ramo'); return; }

  const action = id ? 'editar_cotacao_controle' : 'criar_cotacao_controle';
  const r = await apiPost(action, body);
  if (r.ok) {
    toast(id ? 'Cotação atualizada' : 'Cotação criada');
    closeModal('mod-cc');
    ccLoad();
  } else {
    toast('Erro: ' + (r.msg || 'falha ao salvar'));
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

// ── Export XLSX ──
function ccExportXLSX(){
  if (!ccData.length) { toast('Nada para exportar'); return; }
  const headers = ['RAZÃO SOCIAL','STATUS','OBSERVAÇÃO/ÚLTIMO STATUS','ENTRADA','ANALISTA','TIPO','RAMO',
                   'DATA RECEBIMENTO','PRÓXIMA AÇÃO','OPERADORA ATUAL','VIDAS','DATA SOLICITAÇÃO',
                   'PLAYERS COTADOS','DATA ENVIO','DATA SINALIZAÇÃO',
                   'PLAYER CONTRATADO','PRÊMIO','DATA FINALIZAÇÃO','MOTIVO DECLÍNIO'];
  const rows = ccData.map(d => [
    d.razao_social, d.status, d.observacao, d.entrada, d.analista, d.tipo, d.ramo,
    d.data_recebimento, d.data_proxima_acao, d.operadora_atual, d.numero_vidas, d.data_solicitacao,
    d.players_cotados, d.data_envio_estudo, d.data_sinalizacao,
    d.player_contratado, d.premio, d.data_finalizacao, d.motivo_declinio
  ]);
  const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
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
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// FIM
