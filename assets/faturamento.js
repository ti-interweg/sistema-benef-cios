// ═══════════════════════════════════════════════════════════════
// FATURAMENTO.JS — Página de Faturamento (lista, cards, cadastro)
// Depende de assets/common.js (fmtBRL, esc, toast, apiGet, etc.)
// ═══════════════════════════════════════════════════════════════

window.IS_ADMIN  = (typeof IS_ADMIN  !== 'undefined') ? IS_ADMIN  : true;
window.IS_VISUALIZADOR = (typeof IS_VISUALIZADOR !== 'undefined') ? IS_VISUALIZADOR : false;
window.IS_FINANCEIRO = (typeof IS_FINANCEIRO !== 'undefined') ? IS_FINANCEIRO : false;
window.CAN_VIEW_ALL = (typeof CAN_VIEW_ALL !== 'undefined') ? CAN_VIEW_ALL : window.IS_ADMIN;
window.USER_NOME = (typeof USER_NOME !== 'undefined') ? USER_NOME : '';

// Fallbacks defensivos pra helpers que podem não estar no common.js antigo
const _FAT_COLORS = (typeof window.COLORS !== 'undefined' && Array.isArray(window.COLORS) && window.COLORS.length)
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
if (typeof window.fmtComp !== 'function') window.fmtComp = c => c||'—';
if (typeof window.openModal !== 'function')  window.openModal  = id => { const el=document.getElementById(id); if(el) el.classList.add('show'); };
if (typeof window.closeModal !== 'function') window.closeModal = id => { const el=document.getElementById(id); if(el) el.classList.remove('show'); };
if (typeof window.apiGet !== 'function')  window.apiGet  = async (a,p={}) => (await fetch('api.php?'+new URLSearchParams({action:a,...p}))).json();
if (typeof window.apiPost !== 'function') window.apiPost = async (a,b={}) => (await fetch('api.php?action='+encodeURIComponent(a),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)})).json();

// ── Status badge color ─────────────────────────────────────────
function _fatSc(status){
  const k = String(status||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
  if (['enviado','concluido'].includes(k)) return 'b-concluido';
  if (['aguardando envio','em analise'].includes(k)) return 'b-analise';
  return 'b-pendencia';
}

// ── Estado ──────────────────────────────────────────────────────
let fatData = [];
let FAT_PAGE = 1;
const FAT_PER = 15;

let _fatAnalistas = [];
let _fatSeguradoras = [];
let _fatEmpresas = [];

// ── INIT ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  await Promise.all([
    apiGet('analistas').then(r => _fatAnalistas = r.data || []).catch(e => console.warn('analistas:', e)),
    apiGet('seguradoras').then(r => _fatSeguradoras = r.data || []).catch(e => console.warn('seguradoras:', e)),
    apiGet('empresas').then(r => _fatEmpresas = r.data || []).catch(e => console.warn('empresas:', e))
  ]);

  // Popula filtros
  const segs = (_fatSeguradoras||[]).map(s => typeof s === 'string' ? s : s.nome).filter(Boolean).sort();
  fillSelect('ff-seguradora', 'Todas as seguradoras', segs);
  const grupos = [...new Set((_fatEmpresas||[]).map(e => e.grupo))].filter(Boolean).sort();
  fillSelect('ff-grupo', 'Todos os grupos', grupos);
  // Filtra: só ANALISTAS reais (sem admin/enfermeiras/financeiro/etc)
  const analistasReais = (_fatAnalistas || []).filter(a => {
    const nome = (a.nome || '').toLowerCase().trim();
    const tipo = (a.tipo || '').toLowerCase();
    // Mantém SÓ analista de fato — exclui todas as lideranças e áreas-suporte
    if (['admin','head','coordenador','gerente','financeiro','enfermeira','enfermagem',
         'visualizador','novos_negocios'].includes(tipo)) return false;
    if (nome.startsWith('enf.') || nome.startsWith('enf '))    return false;
    if (nome === 'administrador' || nome === 'financeiro interweg') return false;
    return true;
  });
  fillSelect('ff-analista', 'Todos os analistas', analistasReais.map(a => a.nome));
  // Dropdown de troca de analista em lote (mesmos nomes reais)
  fillSelect('fat-bulk-analista', 'Trocar analista para…', analistasReais.map(a => a.nome));

  await fatLoad();
  await fatLoadStats();
});

function fillSelect(id, placeholder, items){
  const el = document.getElementById(id);
  if (!el) return;
  el.innerHTML = `<option value="">${placeholder}</option>` +
    items.map(s => `<option value="${escAttr(s)}">${esc(s)}</option>`).join('');
}

function val(id){ const el = document.getElementById(id); return el ? (el.value || '') : ''; }
function setText(id, v){ const el = document.getElementById(id); if (el) el.textContent = v; }
function setVal(id, v){ const el = document.getElementById(id); if (el) el.value = (v == null ? '' : v); }

// ── LOAD ────────────────────────────────────────────────────────
async function fatLoad(){
  FAT_PAGE = 1;
  const params = {
    search:       val('ff-search'),
    origem:       val('ff-origem'),
    ramo:         val('ff-ramo'),
    seguradora:   val('ff-seguradora'),
    grupo:        val('ff-grupo'),
    analista:     val('ff-analista'),
    status:       val('ff-status'),
    emissao_de:   val('ff-emissao-de'),
    emissao_ate:  val('ff-emissao-ate'),
    competencia:  val('ff-competencia')
  };
  // Flag do card "Pendentes de envio": pega os 3 status juntos.
  // Backend aceita status_in CSV (sobrescreve filtro de status único).
  if (window._fatStatusIn) {
    params.status_in = window._fatStatusIn;
    params.status    = ''; // garante que o status único não interfira
  }
  try {
    const r = await apiGet('listar_faturamento', params);
    fatData = r.data || [];
  } catch(e) {
    fatData = [];
    console.error('Erro ao listar faturamento:', e);
    toast('Erro ao carregar faturamento');
  }
  fatRenderTable();
  fatLoadStats();
}

async function fatLoadStats(){
  const comp = val('ff-competencia');
  const params = comp ? { competencia: comp } : {};
  try {
    const r = await apiGet('stats_faturamento', params);
    const d = r.data || {};
    setText('fat-total',        d.total || 0);
    setText('fat-hoje',         d.emitir_hoje || 0);
    setText('fat-aguard',       d.aguardando || 0);
    setText('fat-env',          d.enviados || 0);
    setText('fat-tot-premio',   fmtBRL(d.total_premio));
    setText('fat-tot-previsto', fmtBRL(d.total_previsto));
    setText('fat-tot-recebido', fmtBRL(d.total_recebido));

    // Atualiza sub-legendas dos cards com a competência (mês vigente por padrão)
    const compTxt = d.competencia ? `mês ${d.competencia}` : 'todos os meses';
    const setSub = (id, txt) => {
      const card = document.getElementById(id)?.closest('.card');
      const sub = card?.querySelector('.card-sub');
      if (sub) sub.textContent = txt;
    };
    setSub('fat-total',        `registros · ${compTxt}`);
    setSub('fat-aguard',       `pendente emissão + aguardando + em análise · ${compTxt}`);
    setSub('fat-env',          `concluídos · ${compTxt}`);
    setSub('fat-tot-premio',   `soma · ${compTxt}`);
    setSub('fat-tot-previsto', `cálculo automático · ${compTxt}`);
    setSub('fat-tot-recebido', `financeiro · ${compTxt}`);

    // Resumo por grupo / seguradora
    const g = (d.por_grupo || []);
    document.getElementById('fat-resumo-grupo').innerHTML = g.length ? g.map(x => {
      const diff = parseFloat(x.recebido) - parseFloat(x.previsto);
      const cor = Math.abs(diff) < 0.01 ? 'var(--success)' : (diff < 0 ? 'var(--warn)' : 'var(--info)');
      return `<div style="display:grid;grid-template-columns:1.2fr 40px repeat(3,1fr);gap:6px;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:12px">
        <div style="font-weight:600">${esc(x.grupo||'—')}</div>
        <div style="text-align:center;color:var(--muted)">${x.qtd}</div>
        <div style="text-align:right">${fmtBRL(x.premio)}</div>
        <div style="text-align:right;color:var(--accent)">${fmtBRL(x.previsto)}</div>
        <div style="text-align:right;color:${cor}">${fmtBRL(x.recebido)}</div>
      </div>`;
    }).join('') : '<div style="color:var(--muted);font-size:12px">Sem dados</div>';

    const s = (d.por_seguradora || []);
    document.getElementById('fat-resumo-seg').innerHTML = s.length ? s.map(x => {
      const diff = parseFloat(x.recebido) - parseFloat(x.previsto);
      const cor = Math.abs(diff) < 0.01 ? 'var(--success)' : (diff < 0 ? 'var(--warn)' : 'var(--info)');
      return `<div style="display:grid;grid-template-columns:1.2fr 40px repeat(3,1fr);gap:6px;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:12px">
        <div style="font-weight:600">${esc(x.seguradora||'—')}</div>
        <div style="text-align:center;color:var(--muted)">${x.qtd}</div>
        <div style="text-align:right">${fmtBRL(x.premio)}</div>
        <div style="text-align:right;color:var(--accent)">${fmtBRL(x.previsto)}</div>
        <div style="text-align:right;color:${cor}">${fmtBRL(x.recebido)}</div>
      </div>`;
    }).join('') : '<div style="color:var(--muted);font-size:12px">Sem dados</div>';
  } catch(e) {
    console.error('Erro stats faturamento:', e);
  }
}

// ── RENDER TABELA ───────────────────────────────────────────────
function fatRenderTable(){
  const tbody = document.getElementById('fat-tbody');
  const total = fatData.length;
  setText('fat-count', `Lista de Faturamentos (${total})`);

  if (!total) {
    tbody.innerHTML = `<tr><td colspan="20" style="text-align:center;padding:30px;color:var(--muted)">Nenhum faturamento encontrado</td></tr>`;
    setText('fat-pag-info', '0 registros');
    document.getElementById('fat-pag-btns').innerHTML = '';
    return;
  }

  const ini = (FAT_PAGE - 1) * FAT_PER;
  const slice = fatData.slice(ini, ini + FAT_PER);
  const today = new Date().toISOString().slice(0, 10);

  tbody.innerHTML = slice.map(d => {
    const ramoCls = d.ramo === 'Saúde' ? 'r-saude'
                  : d.ramo === 'Odonto' ? 'r-odonto'
                  : d.ramo === 'Saúde/Odonto' ? 'r-saude-odonto'
                  : d.ramo === 'Vida' ? 'r-vida' : '';
    const ci = d.analista_nome ? d.analista_nome.charCodeAt(0) % _FAT_COLORS.length : 0;
    const color = _FAT_COLORS[ci];
    const init = (d.analista_nome || '??').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
    const isISODate = s => typeof s === 'string' && /^\d{4}-\d{2}-\d{2}/.test(s);
    const emitirHoje    = isISODate(d.data_emissao) && d.data_emissao === today && d.status_envio !== 'Enviado';
    const emitirAtrasado = isISODate(d.data_emissao) && d.data_emissao < today && d.status_envio !== 'Enviado';

    let flag = '';
    if (emitirAtrasado) flag = `<span title="Emissão atrasada!" style="color:var(--danger);font-weight:700"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle">warning</span></span> `;
    else if (emitirHoje) flag = `<span title="A emitir hoje!" style="color:var(--warn);font-weight:700"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle">today</span></span> `;

    return `<tr>
      <td style="text-align:center"><input type="checkbox" class="fat-chk" value="${d.id}" onclick="fatRowCheck()"></td>
      <td style="color:#484f58;font-size:11px">#${String(d.id).padStart(4,'0')}</td>
      <td style="font-size:11.5px"><span class="badge b-analise" style="font-size:10.5px">${esc(d.origem||'—')}</span></td>
      <td><span class="ramo ${ramoCls}">${esc(d.ramo||'—')}</span></td>
      <td style="font-size:12px">${esc(d.seguradora||'—')}</td>
      <td style="font-size:11.5px"><strong>${esc(d.numero_contrato||'—')}</strong><br><span style="color:var(--muted);font-size:10.5px">${esc(d.cnpj||'')}</span></td>
      <td style="font-size:12px"><strong>${esc(d.grupo||'—')}</strong><br><span style="color:var(--muted);font-size:11px">${esc(d.empresa||'')}</span></td>
      <td><div style="display:flex;align-items:center"><div class="avatar" style="background:${color}">${init}</div><span style="font-size:11.5px">${esc(d.analista_nome||'—')}</span></div></td>
      <td style="font-size:11.5px">${fmtComp(d.competencia)}</td>
      <td style="font-size:11.5px">${d.competencia_real ? fmtComp(d.competencia_real) : '—'}</td>
      <td style="font-size:12px">${flag}${isISODate(d.data_emissao)?fmtDate(d.data_emissao):(d.data_emissao?esc(d.data_emissao):'—')}</td>
      <td style="font-size:12px">${isISODate(d.data_vencimento)?fmtDate(d.data_vencimento):(d.data_vencimento?esc(d.data_vencimento):'—')}</td>
      <td><span class="badge ${_fatSc(d.status_envio)}" style="font-size:10.5px">${esc(d.status_envio||'—')}</span></td>
      <td style="font-size:11.5px;color:var(--muted)">${isISODate(d.data_envio)?fmtDate(d.data_envio):(d.data_envio?esc(d.data_envio):'—')}</td>
      <td style="text-align:center;font-weight:600">${d.numero_vidas != null ? d.numero_vidas : '—'}</td>
      <td style="font-size:12px;text-align:right;white-space:nowrap">${d.premio_liquido != null ? fmtBRL(d.premio_liquido) : '—'}</td>
      <td style="font-size:12px;text-align:center">${d.percentual_comissao != null ? fmtPerc(d.percentual_comissao) : '—'}</td>
      <td style="font-size:12px;text-align:right;white-space:nowrap;color:var(--accent);font-weight:600">${d.valor_previsto != null ? fmtBRL(d.valor_previsto) : '—'}</td>
      <td style="font-size:12px;text-align:right;white-space:nowrap;color:${d.comissao_recebida!=null?'var(--success)':'var(--muted)'};font-weight:600">${d.comissao_recebida != null ? fmtBRL(d.comissao_recebida) : '—'}</td>
      <td style="font-size:12px;text-align:right;white-space:nowrap;color:${d.valor_a_pagar!=null?'var(--info)':'var(--muted)'};font-weight:600">${d.valor_a_pagar != null ? fmtBRL(d.valor_a_pagar) : '—'}</td>
      <td style="font-size:11px;color:var(--muted);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escAttr(d.emails_envio||'')}">${esc(d.emails_envio||'—')}</td>
      <td style="white-space:nowrap">
        ${window.IS_VISUALIZADOR
          ? '—'
          : (window.IS_FINANCEIRO
            ? `<button class="btn btn-outline btn-sm" onclick="fatLancarComRec(${d.id})" title="Lançar comissão recebida" style="padding:4px 8px"><span class="material-symbols-rounded" style="font-size:14px">payments</span> Lançar comissão</button>`
            : `<button class="btn btn-outline btn-sm" onclick="fatAjusteRapido(${d.id})" title="Ajuste rápido (vidas, prêmio, status, envio)" style="padding:4px 8px"><span class="material-symbols-rounded" style="font-size:14px">bolt</span></button>
               <button class="btn btn-outline btn-sm" onclick="fatEdit(${d.id})" style="padding:4px 8px;margin-left:4px">Editar</button>`)
        }
      </td>
    </tr>`;
  }).join('');

  // Paginação
  const totalPages = Math.ceil(total / FAT_PER);
  setText('fat-pag-info', `${ini+1}-${Math.min(ini+FAT_PER, total)} de ${total}`);
  let btns = '';
  if (totalPages > 1) {
    for (let p = 1; p <= Math.min(totalPages, 12); p++) {
      btns += `<button class="pb${p===FAT_PAGE?' active':''}" onclick="FAT_PAGE=${p};fatRenderTable()">${p}</button>`;
    }
  }
  document.getElementById('fat-pag-btns').innerHTML = btns;
  fatAtualizarBarra();
}

// ── Seleção em massa + status em lote ──────────────────────────
function fatChecks(){ return [...document.querySelectorAll('#fat-tbody .fat-chk')]; }
function fatSelectedIds(){ return fatChecks().filter(c => c.checked).map(c => parseInt(c.value,10)).filter(Boolean); }
function fatAtualizarBarra(){
  const ids = fatSelectedIds();
  const bar = document.getElementById('fat-bulk-bar');
  const cnt = document.getElementById('fat-bulk-count');
  if (cnt) cnt.textContent = ids.length;
  if (bar) bar.style.display = ids.length ? 'flex' : 'none';
  const all  = document.getElementById('fat-check-all');
  const chks = fatChecks();
  if (all) all.checked = chks.length > 0 && chks.every(c => c.checked);
}
function fatRowCheck(){ fatAtualizarBarra(); }
function fatToggleAll(cb){ fatChecks().forEach(c => { c.checked = cb.checked; }); fatAtualizarBarra(); }
function fatBulkLimpar(){ const all=document.getElementById('fat-check-all'); if(all) all.checked=false; fatChecks().forEach(c=>c.checked=false); fatAtualizarBarra(); }
async function fatBulkStatus(status){
  const ids = fatSelectedIds();
  if (!ids.length){ toast('Selecione ao menos um faturamento'); return; }
  if (!status){ toast('Escolha o status no seletor'); return; }
  if (!confirm('Aplicar o status "'+status+'" em '+ids.length+' faturamento(s)?')) return;
  try {
    const r = await apiPost('atualizar_status_faturamento_lote', { ids, status });
    if (!r.ok){ toast(r.msg || 'Erro ao atualizar'); return; }
    toast((r.afetados!=null?r.afetados:ids.length) + ' faturamento(s) atualizado(s).');
    fatLoad();
  } catch(e){ toast('Erro de conexão'); }
}
function fatBulkAplicar(){ fatBulkStatus((document.getElementById('fat-bulk-status')||{}).value || ''); }
function fatBulkConcluir(){ fatBulkStatus('Enviado'); }

// ── Gerar faturamentos do mês a partir da carteira (base) ──────
function fatOpenGerarCarteira(){
  const hoje = new Date();
  const ini = document.getElementById('gc-comp'); if (ini) ini.value = hoje.getFullYear()+'-'+String(hoje.getMonth()+1).padStart(2,'0');
  ['gc-emissao','gc-venc'].forEach(id=>{ const el=document.getElementById(id); if(el) el.value=''; });
  const g=document.getElementById('gc-grupo'); if(g) g.value='';
  const info=document.getElementById('gc-info'); if(info) info.textContent='Escolha o mês, as datas e (se quiser) filtre por grupo.';
  if (typeof openModal==='function') openModal('mod-gerar-carteira'); else document.getElementById('mod-gerar-carteira').classList.add('show');
}
async function fatGerarCarteira(btn){
  const iso = (document.getElementById('gc-comp')||{}).value || '';
  if (!/^\d{4}-\d{2}$/.test(iso)){ toast('Escolha a competência (mês)'); return; }
  const meses=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
  const [y,m]=iso.split('-'); const comp=meses[parseInt(m,10)-1]+'/'+y.slice(-2);
  const emissao=(document.getElementById('gc-emissao')||{}).value||'';
  const venc=(document.getElementById('gc-venc')||{}).value||'';
  const grupo=((document.getElementById('gc-grupo')||{}).value||'').trim();
  const ok = await fatConfirmModal({
    titulo:'Gerar faturamentos',
    corpo:'Vou criar os faturamentos pendentes de <b>'+comp+'</b>'+(grupo?(' do grupo <b>'+grupo+'</b>'):' de <b>toda a carteira</b>')+'. Contratos que já existem no mês não são duplicados.',
    okTxt:'Gerar'
  });
  if (!ok) return;
  if (btn){ btn.disabled=true; btn.dataset.l=btn.innerHTML; btn.innerHTML='Gerando...'; }
  try{
    const r = await apiPost('gerar_faturamento_carteira', { competencia:comp, data_emissao:emissao, data_vencimento:venc, grupo:grupo });
    if (!r.ok){ toast(r.msg||'Erro ao gerar'); return; }
    toast(r.criadas+' faturamento(s) criado(s) em '+comp+(r.puladas?(' · '+r.puladas+' já existiam'):''));
    closeModal('mod-gerar-carteira');
    fatLoad();
  }catch(e){ toast('Erro de conexão'); }
  finally{ if(btn){ btn.disabled=false; btn.innerHTML=btn.dataset.l||'Gerar faturamentos'; } }
}

// ── Baixar prévia da carteira (revisar antes de gerar) ─────────
async function fatBaixarPreviaCarteira(btn){
  if (typeof XLSX === 'undefined'){ toast('Planilha ainda carregando, tente de novo'); return; }
  const iso = (document.getElementById('gc-comp')||{}).value || '';
  const meses=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
  let comp = '';
  if (/^\d{4}-\d{2}$/.test(iso)){ const [y,m]=iso.split('-'); comp=meses[parseInt(m,10)-1]+'/'+y.slice(-2); }
  const grupo=((document.getElementById('gc-grupo')||{}).value||'').trim();
  if (btn){ btn.disabled=true; btn.dataset.l=btn.innerHTML; btn.innerHTML='Baixando...'; }
  try{
    const params = {}; if (comp) params.competencia = comp; if (grupo) params.grupo = grupo;
    const r = await apiGet('listar_carteira_faturamento', params);
    const dados = (r && r.data) || [];
    if (!dados.length){ toast('Nenhum contrato na carteira'+(grupo?(' para "'+grupo+'"'):'')); return; }
    const temComp = comp !== '';
    const cab = ['Grupo','Empresa','CNPJ','Contrato','Seguradora','Ramo','Analista','% Comissão'];
    if (temComp) cab.push('Já existe em '+comp+'?');
    cab.push('Vidas','Prêmio Líquido');
    const linhas = [cab];
    dados.forEach(d=>{
      const row = [ d.grupo||'', d.empresa||'', d.cnpj||'', d.numero_contrato||'', d.seguradora||'', d.ramo||'', d.analista_nome||'',
        (d.percentual_comissao!=null&&d.percentual_comissao!==''?String(+(Number(d.percentual_comissao)*100).toFixed(2)).replace('.',',')+'%':'') ];
      if (temComp) row.push(d.ja_existe ? 'Sim' : 'Não');
      row.push('',''); // Vidas e Prêmio em branco pra preencher
      linhas.push(row);
    });
    const ws = XLSX.utils.aoa_to_sheet(linhas);
    ws['!cols'] = cab.map((c,i)=>({ wch: Math.min(Math.max(String(c).length+2, 12), 45) }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Carteira');
    const nome = 'carteira'+(grupo?('_'+grupo.replace(/[^A-Za-z0-9]+/g,'_')):'')+(comp?('_'+comp.replace('/','-')):'')+'.xlsx';
    XLSX.writeFile(wb, nome);
    toast(dados.length+' contrato(s) na prévia');
  }catch(e){ toast('Erro ao baixar prévia'); }
  finally{ if(btn){ btn.disabled=false; btn.innerHTML=btn.dataset.l||'Baixar prévia'; } }
}

// ── Alterar COMPETÊNCIA em lote (calendário de mês) ────────────
async function fatBulkCompetencia(){
  const ids = fatSelectedIds();
  if (!ids.length){ toast('Selecione ao menos um faturamento'); return; }
  const iso = (document.getElementById('fat-bulk-comp')||{}).value || ''; // YYYY-MM
  if (!/^\d{4}-\d{2}$/.test(iso)){ toast('Escolha a competência no calendário'); return; }
  const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
  const [y,m] = iso.split('-');
  const comp = meses[parseInt(m,10)-1] + '/' + y.slice(-2); // ex.: Set/26
  const ok = await fatConfirmModal({
    titulo: 'Alterar competência',
    corpo: 'A competência de <b>' + ids.length + ' faturamento(s)</b> será alterada para <b style="color:var(--accent)">' + comp + '</b>.',
    okTxt: 'Alterar competência'
  });
  if (!ok) return;
  try {
    const r = await apiPost('atualizar_competencia_faturamento_lote', { ids, competencia: comp });
    if (!r.ok){ toast(r.msg || 'Erro ao atualizar competência'); return; }
    toast((r.afetados!=null?r.afetados:ids.length) + ' faturamento(s) com competência ' + comp + '.');
    fatLoad();
  } catch(e){ toast('Erro de conexão'); }
}

async function fatBulkAnalista(){
  const ids = fatSelectedIds();
  if (!ids.length){ toast('Selecione ao menos um faturamento'); return; }
  const novo = (document.getElementById('fat-bulk-analista')||{}).value || '';
  if (!novo){ toast('Escolha o analista no seletor'); return; }
  const ok = await fatConfirmModal({
    titulo: 'Trocar analista',
    corpo: 'O analista de <b>' + ids.length + ' faturamento(s)</b> será alterado para <b style="color:var(--accent)">' + novo + '</b>.',
    okTxt: 'Trocar analista'
  });
  if (!ok) return;
  try {
    const r = await apiPost('atualizar_analista_faturamento_lote', { ids, analista_nome: novo });
    if (!r.ok){ toast(r.msg || 'Erro ao trocar analista'); return; }
    toast((r.afetados!=null?r.afetados:ids.length) + ' faturamento(s) para ' + novo + '.');
    fatLoad();
  } catch(e){ toast('Erro de conexão'); }
}

function fatToggleResumo(){
  const el = document.getElementById('fat-resumo');
  el.style.display = el.style.display === 'none' ? '' : 'none';
}

function fatFiltrarHoje(){
  // Filtra emissão = hoje preenchendo o campo "de" com a data de hoje
  // (e deixando "até" vazio, pra cair no caso de "dia exato").
  const hoje = new Date().toISOString().slice(0,10);
  document.getElementById('ff-emissao-de').value  = hoje;
  document.getElementById('ff-emissao-ate').value = '';
  fatLoad();
}

// ── FILTROS rápidos pelos cards (Total / Pendentes de envio / Enviados) ─
function fatFiltrarPor(tipo){
  const sel = document.getElementById('ff-status');
  if (!sel) return;
  // Limpa filtro de data por padrão pra não atrapalhar
  const elDe = document.getElementById('ff-emissao-de');
  const elAte = document.getElementById('ff-emissao-ate');
  if (elDe)  elDe.value = '';
  if (elAte) elAte.value = '';

  // Reseta flag de status_in por padrão (só o card "aguardando" usa)
  window._fatStatusIn = null;

  if (tipo === 'total') {
    sel.value = '';
  } else if (tipo === 'aguardando') {
    // Card "Pendentes de envio" → pega os 3 status juntos via status_in
    sel.value = '';
    window._fatStatusIn = 'Pendente emissão,Aguardando envio,Em análise';
  } else if (tipo === 'enviado') {
    sel.value = 'Enviado';
  }
  fatLoad();
}

// Quando o usuário mexe no select de status manualmente, limpa a flag do card
document.addEventListener('DOMContentLoaded', () => {
  const sel = document.getElementById('ff-status');
  if (sel) sel.addEventListener('change', () => { window._fatStatusIn = null; });
});

// ── Calculadora: Prêmio × % = Valor Previsto ───────────────────
function fatCalc(){
  const premio = brToNum(val('mf-premio'));
  const perc   = brToNum(val('mf-perc-com'));
  const v = premio * perc / 100;
  setVal('mf-valor-prev', isNaN(v) || v === 0 ? '' : numToBR(v));
}

// ── AJUSTE RÁPIDO MENSAL ────────────────────────────────────────
// Modal compacto pra atualizar só o que muda mensalmente:
// Nº de vidas, prêmio, status, data de envio, comissão recebida.
// (% de comissão é fixo do contrato, mostrado readonly).

function fatMensalCalc(){
  const premio = brToNum(val('mfm-premio'));
  const percRaw = String(val('mfm-perc')).replace(/[^\d.,]/g, '');
  const perc = brToNum(percRaw);
  const v = (premio || 0) * (perc || 0) / 100;
  setVal('mfm-valor-prev', isNaN(v) || v === 0 ? '' : numToBR(v));
}

function fatAjusteRapido(id){
  const d = fatData.find(x => x.id == id);
  if (!d) { toast('Faturamento não encontrado'); return; }

  setVal('mfm-id', id);
  document.getElementById('mfm-ctx').textContent =
    `${d.grupo || ''} · ${d.empresa || ''} · ${d.seguradora || ''} · Contrato ${d.numero_contrato || '—'} · Competência ${fmtComp(d.competencia)}`;

  setVal('mfm-vidas',      d.numero_vidas != null ? d.numero_vidas : '');
  setVal('mfm-premio',     d.premio_liquido != null ? numToBR(d.premio_liquido) : '');
  setVal('mfm-status',     d.status_envio || 'Pendente emissão');
  setVal('mfm-data-envio', d.data_envio || '');
  setVal('mfm-perc',       d.percentual_comissao != null ? numToBR(d.percentual_comissao) + ' %' : '');
  setVal('mfm-valor-prev', d.valor_previsto != null ? numToBR(d.valor_previsto) : '');
  setVal('mfm-com-rec',    d.comissao_recebida != null ? numToBR(d.comissao_recebida) : '');
  setVal('mfm-competencia', _compToISO(d.competencia));
  setVal('mfm-competencia-real', _compToISO(d.competencia_real));
  fatMensalCalc();

  openModal('mod-fat-mensal');
}

// Atalho: muda status pra "Enviado" e preenche data de envio com hoje
function fatMensalMarcarEnviado(){
  setVal('mfm-status', 'Enviado');
  if (!val('mfm-data-envio')) setVal('mfm-data-envio', new Date().toISOString().slice(0,10));
  toast('Marcado como Enviado — clique em Salvar pra gravar');
}

async function fatMensalSave(){
  const id = val('mfm-id');
  if (!id) return;
  const d = fatData.find(x => x.id == id);
  if (!d) { toast('Registro não encontrado'); return; }

  // Mantém o restante dos dados originais e atualiza só os campos do ajuste rápido.
  const body = {
    id: id,
    origem:           d.origem,
    ramo:             d.ramo,
    seguradora:       d.seguradora,
    numero_contrato:  d.numero_contrato,
    cnpj:             d.cnpj,
    grupo:            d.grupo,
    empresa:          d.empresa,
    razao_social:     d.razao_social,
    analista_id:      d.analista_id,
    analista_nome:    d.analista_nome,
    competencia:      _isoToComp(val('mfm-competencia')) || d.competencia,
    competencia_real: _isoToComp(val('mfm-competencia-real')),
    data_emissao:     d.data_emissao,
    data_vencimento:  d.data_vencimento,
    emails_envio:     d.emails_envio,
    observacoes:      d.observacoes,
    percentual_comissao: d.percentual_comissao,
    // ── Campos editados pelo ajuste rápido ──
    numero_vidas:     val('mfm-vidas')        || null,
    premio_liquido:   brToNum(val('mfm-premio'))    || null,
    status_envio:     val('mfm-status'),
    data_envio:       val('mfm-data-envio')   || null,
    comissao_recebida: val('mfm-com-rec').trim() === '' ? null : brToNum(val('mfm-com-rec'))
  };

  const r = await apiPost('editar_faturamento', body);
  if (r.ok) {
    toast('Ajuste salvo');
    closeModal('mod-fat-mensal');
    fatLoad();
  } else {
    toast('Erro: ' + (r.msg || 'falha ao salvar'));
  }
}

// ── MODAL CADASTRO/EDIÇÃO ───────────────────────────────────────
function openModalFat(){
  setVal('mf-id','');
  document.getElementById('mf-title').textContent = 'Novo Faturamento';
  document.getElementById('mf-btn-del').style.display = 'none';

  ['mf-origem','mf-ramo','mf-contrato','mf-cnpj','mf-vidas','mf-premio','mf-perc-com',
   'mf-valor-prev','mf-com-rec','mf-valor-pagar','mf-emails','mf-obs','mf-emissao','mf-vencimento','mf-data-envio','mf-competencia','mf-competencia-real',
   'mf-razao-social']
    .forEach(id => setVal(id, ''));
  setVal('mf-status', 'Pendente emissão');

  fillSelect('mf-seguradora', 'Selecione...', _fatSeguradoras.map(s => typeof s === 'string' ? s : s.nome).filter(Boolean).sort());
  // Só ANALISTAS de verdade (sem admin/head/coordenador/financeiro/enfermeira/etc)
  const _fatAnalistasSomenteAnalistas = (_fatAnalistas || []).filter(a => {
    const tipo = (a.tipo || '').toLowerCase();
    const nome = (a.nome || '').toLowerCase().trim();
    if (['admin','head','coordenador','gerente','financeiro','enfermeira','enfermagem',
         'visualizador','novos_negocios'].includes(tipo)) return false;
    if (nome.startsWith('enf.') || nome.startsWith('enf ')) return false;
    if (nome === 'administrador' || nome === 'financeiro interweg') return false;
    return true;
  });
  fillSelect('mf-analista-sel', 'Selecione...', _fatAnalistasSomenteAnalistas.map(a => a.nome));
  const grupos = [...new Set(_fatEmpresas.map(e => e.grupo))].filter(Boolean).sort();
  fillSelect('mf-grupo', 'Selecione...', grupos);

  document.getElementById('mf-grupo').onchange = fatUpdateEmpresas;
  document.getElementById('mf-empresa').innerHTML = '<option value="">Selecione um grupo primeiro</option>';

  openModal('mod-fat');
}

function fatUpdateEmpresas(){
  const grupo = document.getElementById('mf-grupo').value;
  const sel = document.getElementById('mf-empresa');
  const list = _fatEmpresas.filter(e => e.grupo === grupo);
  sel.innerHTML = '<option value="">Selecione...</option>' +
    list.map(e => `<option value="${escAttr(e.nome||e.empresa||'')}">${esc(e.nome||e.empresa||'')}</option>`).join('');
  // Auto-preenche Razão Social ao escolher Empresa (se existir no cadastro de empresas)
  sel.onchange = () => {
    const nome = sel.value;
    const emp = list.find(e => (e.nome||e.empresa||'') === nome);
    const rs = emp && (emp.razao_social || emp.razaosocial);
    const cur = (document.getElementById('mf-razao-social')||{}).value || '';
    if (rs && !cur) setVal('mf-razao-social', rs);
  };
}

// Helper: define o value de um <select>. Se a opção não existir
// (seguradora deletada do cadastro, mudança de acento, etc.), ADICIONA
// a opção dinamicamente pra preservar o valor já gravado no faturamento
// e evitar que ele suma ao salvar de novo.
function setSelectValueOrAdd(id, value) {
  const sel = document.getElementById(id);
  if (!sel || value == null || value === '') { setVal(id, value); return; }
  const v = String(value);
  const exists = Array.from(sel.options).some(o => o.value === v);
  if (!exists) {
    const opt = document.createElement('option');
    opt.value = v;
    opt.textContent = v;
    sel.appendChild(opt);
  }
  sel.value = v;
}

async function fatEdit(id){
  const d = fatData.find(x => x.id == id);
  if (!d) { toast('Faturamento não encontrado'); return; }

  openModalFat();
  document.getElementById('mf-title').textContent = `Editar Faturamento #${id}`;
  document.getElementById('mf-btn-del').style.display = '';
  setVal('mf-id', id);

  setVal('mf-origem',     d.origem);
  setVal('mf-ramo',       d.ramo);
  setSelectValueOrAdd('mf-seguradora',   d.seguradora);
  setVal('mf-contrato',   d.numero_contrato);
  setVal('mf-cnpj',       d.cnpj);
  setSelectValueOrAdd('mf-analista-sel', d.analista_nome);
  setSelectValueOrAdd('mf-grupo', d.grupo);
  fatUpdateEmpresas();
  setSelectValueOrAdd('mf-empresa', d.empresa);
  setVal('mf-razao-social', d.razao_social);
  // Competência "Mai/26" -> "2026-05" (campo renomeado para "Mês de envio")
  setVal('mf-competencia', _compToISO(d.competencia));
  setVal('mf-competencia-real', _compToISO(d.competencia_real));
  setVal('mf-emissao',    d.data_emissao);
  setVal('mf-vencimento', d.data_vencimento);
  setVal('mf-status',     d.status_envio);
  setVal('mf-data-envio', d.data_envio);
  setVal('mf-vidas',      d.numero_vidas);
  setVal('mf-premio',     numToBR(d.premio_liquido));
  setVal('mf-perc-com',   numToBR(d.percentual_comissao));
  setVal('mf-valor-prev', d.valor_previsto != null ? numToBR(d.valor_previsto) : '');
  setVal('mf-com-rec',    d.comissao_recebida != null ? numToBR(d.comissao_recebida) : '');
  setVal('mf-valor-pagar', d.valor_a_pagar != null ? numToBR(d.valor_a_pagar) : '');
  fatCalc(); // recalcula caso valor_previsto não esteja salvo no banco
  setVal('mf-emails',     d.emails_envio);
  setVal('mf-obs',        d.observacoes);
}

function _compToISO(c){
  if (!c) return '';
  const map = {Jan:'01',Fev:'02',Mar:'03',Abr:'04',Mai:'05',Jun:'06',Jul:'07',Ago:'08',Set:'09',Out:'10',Nov:'11',Dez:'12'};
  const m = String(c).match(/^(\w{3})\/(\d{2})$/);
  if (!m) return '';
  return '20' + m[2] + '-' + (map[m[1]] || '01');
}

// Converte o valor do seletor de mês (YYYY-MM) para "Mes/AA" (ex.: "Jul/26").
function _isoToComp(v){
  if (!v || !/^\d{4}-\d{2}$/.test(v)) return v || '';
  const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
  const [y,m] = v.split('-');
  return meses[parseInt(m)-1] + '/' + y.slice(-2);
}

// Exibe uma data (YYYY-MM-DD ou YYYY-MM) como mês/ano: "07/2026".
function fmtMesAno(s){
  if (!s) return '—';
  const m = String(s).match(/^(\d{4})-(\d{2})/);
  return m ? `${m[2]}/${m[1]}` : String(s);
}

async function fatSave(){
  const id = document.getElementById('mf-id').value;
  const analistaNome = document.getElementById('mf-analista-sel').value;
  const analista = _fatAnalistas.find(a => a.nome === analistaNome);

  // Competência ISO -> "Mes/AA"
  let competencia = val('mf-competencia');
  if (competencia && /^\d{4}-\d{2}$/.test(competencia)) {
    const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    const [y,m] = competencia.split('-');
    competencia = meses[parseInt(m)-1] + '/' + y.slice(-2);
  }

  const body = {
    id: id || null,
    origem:           val('mf-origem'),
    ramo:             val('mf-ramo'),
    seguradora:       val('mf-seguradora'),
    numero_contrato:  val('mf-contrato'),
    cnpj:             val('mf-cnpj'),
    grupo:            val('mf-grupo'),
    empresa:          val('mf-empresa'),
    razao_social:     val('mf-razao-social'),
    analista_id:      analista ? analista.id : null,
    analista_nome:    analistaNome,
    competencia:      competencia,
    competencia_real: _isoToComp(val('mf-competencia-real')),
    data_emissao:     val('mf-emissao')    || null,
    data_vencimento:  val('mf-vencimento') || null,
    status_envio:     val('mf-status'),
    data_envio:       val('mf-data-envio') || null,
    emails_envio:     val('mf-emails'),
    numero_vidas:     val('mf-vidas')      || null,
    premio_liquido:   brToNum(val('mf-premio'))  || null,
    percentual_comissao: brToNum(val('mf-perc-com')) || null,
    comissao_recebida: val('mf-com-rec').trim()==='' ? null : brToNum(val('mf-com-rec')),
    valor_a_pagar:    val('mf-valor-pagar').trim()==='' ? null : brToNum(val('mf-valor-pagar')),
    observacoes:      val('mf-obs')
  };

  if (!body.empresa || !body.grupo) { toast('Preencha Grupo e Empresa'); return; }
  if (!body.seguradora) { toast('Selecione a seguradora'); return; }

  const action = id ? 'editar_faturamento' : 'criar_faturamento';
  // Trava anti-duplo-clique: evita enviar o mesmo faturamento 2x
  if (window._fatSaving) return;
  window._fatSaving = true;
  try {
    const r = await apiPost(action, body);
    if (r.ok) {
      toast(id ? 'Faturamento atualizado' : 'Faturamento criado');
      closeModal('mod-fat');
      fatLoad();
    } else {
      toast('Erro: ' + (r.msg || 'falha ao salvar'));
    }
  } finally {
    window._fatSaving = false;
  }
}

async function fatDelete(){
  const id = document.getElementById('mf-id').value;
  if (!id) return;
  if (!confirm(`Excluir faturamento #${id}?`)) return;
  const r = await apiGet('excluir_faturamento', { id });
  if (r.ok) {
    toast('Faturamento excluído');
    closeModal('mod-fat');
    fatLoad();
  } else {
    toast('Erro: ' + (r.msg || 'falha ao excluir'));
  }
}

// ── EXPORT XML ──────────────────────────────────────────────────
// ── EXPORT XLSX (modal com data + presets + estilo) ─────────────
function fatOpenExportModal(){
  fatExportPreset('mes');
  if (typeof openModal === 'function') openModal('mod-fat-export');
  else document.getElementById('mod-fat-export').classList.add('show');
}

function fatExportPreset(p){
  const ini = document.getElementById('fex-data-ini');
  const fim = document.getElementById('fex-data-fim');
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

// Carrega xlsx-js-style sob demanda (pra suporte a estilo de célula)
function _ensureXlsxStyleFat(){
  if (window._xlsxStyleLoaded) return Promise.resolve();
  return new Promise((resolve) => {
    const urls = [
      'https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js',
      'https://unpkg.com/xlsx-js-style@1.2.0/dist/xlsx.bundle.js'
    ];
    let i = 0;
    const tryNext = () => {
      if (i >= urls.length) { console.warn('xlsx-js-style: CDNs falharam, exportando sem estilo'); resolve(); return; }
      const s = document.createElement('script');
      s.src = urls[i++];
      s.onload  = () => { window._xlsxStyleLoaded = true; resolve(); };
      s.onerror = () => { s.remove(); tryNext(); };
      document.head.appendChild(s);
    };
    tryNext();
  });
}

async function fatDoExport(btn){
  if (typeof XLSX === 'undefined'){ toast('SheetJS não carregou — recarregue a página'); return; }
  const ini = (document.getElementById('fex-data-ini')||{}).value || '';
  const fim = (document.getElementById('fex-data-fim')||{}).value || '';
  if (ini && fim && ini > fim){ toast('Data inicial maior que a final'); return; }
  const respeitar = (document.getElementById('fex-respeita-filtros')||{}).checked !== false;

  if (btn) { btn.disabled = true; btn.dataset.lbl = btn.innerHTML; btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:17px">hourglass_top</span> Gerando...'; }

  try {
    await _ensureXlsxStyleFat();

    // Buscar dados: respeita filtros da tela ou pega tudo
    let dados;
    if (respeitar) {
      const params = {
        search:      val('ff-search'),
        origem:       val('ff-origem'),
        ramo:         val('ff-ramo'),
        seguradora:   val('ff-seguradora'),
        grupo:        val('ff-grupo'),
        analista:     val('ff-analista'),
        status:       val('ff-status'),
        emissao_de:   val('ff-emissao-de'),
        emissao_ate:  val('ff-emissao-ate'),
        competencia:  val('ff-competencia')
      };
      const r = await apiGet('listar_faturamento', params);
      dados = r.data || [];
    } else {
      const r = await apiGet('listar_faturamento');
      dados = r.data || [];
    }

    // Filtro local de data por data_emissao
    if (ini || fim) {
      dados = dados.filter(d => {
        const r = (d.data_emissao || '').slice(0,10);
        if (!r) return false;
        if (ini && r < ini) return false;
        if (fim && r > fim) return false;
        return true;
      });
    }

    if (!dados.length){ toast('Nenhum faturamento no período'); return; }

    // TODAS as colunas da tabela (mesma ordem visível)
    const cab = [
      'ID','Origem','Ramo','Seguradora','Contrato','CNPJ','Empresa','Razão Social','Grupo',
      'Analista','Mês de envio','Competência','Data Emissão','Data Vencimento','Status Envio','Data Envio',
      'Vidas','Prêmio Líquido','% Comissão','Valor Previsto','Comissão Recebida','Valor a Pagar','E-mails de Envio',
      'Observações','Criado em','Atualizado em'
    ];
    const linhas = [cab];
    const _fd  = s => { if (!s) return ''; s = String(s).slice(0,10); const m=/^(\d{4})-(\d{2})-(\d{2})$/.exec(s); return m?`${m[3]}/${m[2]}/${m[1]}`:s; };
    const _fdh = s => { if (!s) return ''; const x=String(s); const m=/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/.exec(x); if(m) return `${m[3]}/${m[2]}/${m[1]} ${m[4]}`; return _fd(x); };
    const _toNum = v => { if (v == null || v === '') return ''; const n = Number(v); return isNaN(n) ? v : n; };
    dados.forEach(d => {
      linhas.push([
        d.id || '',
        d.origem || '',
        d.ramo || '',
        d.seguradora || '',
        d.numero_contrato || '',
        d.cnpj || '',
        d.empresa || '',
        d.razao_social || '',
        d.grupo || '',
        d.analista_nome || '',
        d.competencia || '',
        d.competencia_real || '',
        _fd(d.data_emissao),
        _fd(d.data_vencimento),
        d.status_envio || '',
        _fd(d.data_envio),
        _toNum(d.numero_vidas),
        _toNum(d.premio_liquido),
        // % como TEXTO "5,00%" (fração 0.05 -> 5,00%) — não depende do formato do Excel
        (d.percentual_comissao != null && d.percentual_comissao !== '') ? String(+(Number(d.percentual_comissao)*100).toFixed(2)).replace('.',',')+'%' : '',
        _toNum(d.valor_previsto),
        _toNum(d.comissao_recebida),
        _toNum(d.valor_a_pagar),
        d.emails_envio || '',
        d.observacoes || '',
        _fdh(d.criado_em),
        _fdh(d.atualizado_em)
      ]);
    });

    const ws = XLSX.utils.aoa_to_sheet(linhas);

    // Larguras automáticas
    ws['!cols'] = cab.map((_, ci) => {
      let max = String(cab[ci]).length;
      for (let r = 1; r < linhas.length; r++) {
        const v = linhas[r][ci]; if (v == null) continue;
        const len = String(v).length;
        if (len > max) max = len;
      }
      return { wch: Math.min(Math.max(max + 2, 10), 50) };
    });

    // ── Estilo do cabeçalho (verde Interweg) ───────────────────────
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
    // Colunas que devem ser numéricas/moeda/%: Vidas(16), Prêmio(17), % Comissão(18),
    // Valor Previsto(19), Comissão Recebida(20), Valor a Pagar(21)
    const numCols  = new Set([16]);
    const moneyCols = new Set([17, 19, 20, 21]);
    const pctCols   = new Set([18]);

    for (let c = 0; c < cab.length; c++) {
      const addr = XLSX.utils.encode_cell({ r: 0, c });
      if (ws[addr]) ws[addr].s = headerStyle;
    }
    for (let r = 1; r < linhas.length; r++) {
      const isOdd = (r % 2) === 0;
      for (let c = 0; c < cab.length; c++) {
        const addr = XLSX.utils.encode_cell({ r, c });
        if (!ws[addr]) continue;
        let nf = undefined;
        if (moneyCols.has(c)) nf = 'R$ #,##0.00';
        else if (pctCols.has(c)) nf = '0.00%';
        else if (numCols.has(c)) nf = '0';
        if (nf && typeof ws[addr].v === 'number') ws[addr].z = nf;
        ws[addr].s = {
          font:      { sz: 10, name: 'Calibri', color: { rgb: '24292F' } },
          alignment: { vertical: 'center', wrapText: false, horizontal: (moneyCols.has(c)||numCols.has(c)||pctCols.has(c))?'right':'left' },
          border:    cellBorder,
          fill:      isOdd ? { patternType: 'solid', fgColor: { rgb: 'F6F8FA' } } : undefined,
          numFmt:    nf
        };
      }
    }

    ws['!rows']       = [{ hpt: 24 }];
    ws['!freeze']     = { xSplit: 0, ySplit: 1 };
    ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { c: 0, r: 0 }, e: { c: cab.length - 1, r: linhas.length - 1 } }) };

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Faturamentos');

    const periodo = (ini || fim) ? `_${ini || 'inicio'}_a_${fim || 'fim'}` : '';
    XLSX.writeFile(wb, `faturamentos${periodo}.xlsx`);

    if (typeof closeModal === 'function') closeModal('mod-fat-export');
    else document.getElementById('mod-fat-export').classList.remove('show');
    toast(`${dados.length} faturamento(s) exportado(s)`);
  } catch(e){
    console.error('Erro export Faturamento:', e);
    toast('Erro ao gerar Excel: ' + (e.message || e));
  } finally {
    if (btn){ btn.disabled = false; if (btn.dataset.lbl) btn.innerHTML = btn.dataset.lbl; }
  }
}

// Compat: link antigo XML → abre modal novo de Excel
function fatExportXML(){ fatOpenExportModal(); }


// ── VIRAR MÊS (admin) ───────────────────────────────────────────
function openModalVirarMes(){
  // Popular competências distintas dos faturamentos carregados
  const comps = [...new Set((fatData||[]).map(d => d.competencia).filter(Boolean))]
    .sort((a,b) => _compKey(b).localeCompare(_compKey(a)));
  const sel = document.getElementById('vm-de');
  sel.innerHTML = '<option value="">Selecione...</option>' + comps.map(c => `<option>${esc(c)}</option>`).join('');
  setVal('vm-para', '');
  document.getElementById('vm-info').textContent = 'Selecione a competência de origem.';
  openModal('mod-virar');
}

function _compKey(c){
  if (!c) return '0000-00';
  const map = {Jan:'01',Fev:'02',Mar:'03',Abr:'04',Mai:'05',Jun:'06',Jul:'07',Ago:'08',Set:'09',Out:'10',Nov:'11',Dez:'12'};
  const m = String(c).match(/^(\w{3})\/(\d{2})$/);
  if (!m) return c;
  return '20' + m[2] + '-' + (map[m[1]] || '00');
}

// ── FINANCEIRO: lançar comissão recebida (modal compacto, só esse campo) ──
async function fatLancarComRec(id){
  const d = fatData.find(x => x.id == id);
  if (!d) { toast('Faturamento não encontrado'); return; }
  const atual = d.comissao_recebida != null ? numToBR(d.comissao_recebida) : '';
  const ctx = `${d.grupo || '?'} · ${d.empresa || '?'} · ${d.seguradora || '?'} · ${fmtComp(d.competencia)}`;

  // 1) Comissão recebida (modal estilizado)
  const valor = await uiPrompt(`${ctx}\n\nPrêmio líquido: ${d.premio_liquido != null ? fmtBRL(d.premio_liquido) : '—'}\nValor previsto: ${d.valor_previsto != null ? fmtBRL(d.valor_previsto) : '—'}\n\nDigite o valor recebido (vazio = limpar):`, {title:'Lançar comissão recebida', value:atual, placeholder:'Ex: 1.234,56'});
  if (valor === null) return; // cancelou tudo

  // 2) Valor a pagar
  const atualPag = d.valor_a_pagar != null ? numToBR(d.valor_a_pagar) : '';
  const valorPag = await uiPrompt(`${ctx}\n\nComissão recebida: ${valor.trim() === '' ? '—' : 'R$ ' + valor.trim()}\n\nDigite o valor a pagar (vazio = limpar):`, {title:'Valor a pagar', value:atualPag, placeholder:'Ex: 1.234,56'});
  if (valorPag === null) return; // cancelou

  const payload = {
    id,
    comissao_recebida: valor.trim()    === '' ? null : brToNum(valor),
    valor_a_pagar:     valorPag.trim() === '' ? null : brToNum(valorPag)
  };
  apiPost('editar_faturamento', payload).then(r => {
    if (r.ok) { toast('Comissão recebida e valor a pagar atualizados'); fatLoad(); }
    else toast('Erro: ' + (r.msg || 'falha ao salvar'));
  });
}

async function fatVirarMes(btn){
  const de = val('vm-de');
  const paraInput = val('vm-para');
  if (!de || !paraInput) { toast('Preencha origem e destino'); return; }
  const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
  const [y,m] = paraInput.split('-');
  const para = meses[parseInt(m)-1] + '/' + y.slice(-2);
  if (!confirm(`Confirma duplicar todas as linhas de ${de} para ${para}?`)) return;

  if (btn) btn.disabled = true;
  try {
    const r = await apiPost('virar_competencia', { de, para });
    if (r.ok) {
      toast(`✓ ${r.inseridos || 0} linha(s) duplicadas`);
      closeModal('mod-virar');
      fatLoad();
    } else {
      alert('Erro: ' + (r.msg || 'tente novamente'));
    }
  } finally {
    if (btn) btn.disabled = false;
  }
}

// ═══════════════════════════════════════════════════════════════
//  IMPORTAR FECHAMENTO MENSAL
//  Lê planilha XLSX da Letícia, casa por CNPJ+seguradora dentro da
//  competência alvo e atualiza vidas/prêmio/status=Enviado de uma vez.
//  WHERE fixo na competência impede afetar outros meses.
// ═══════════════════════════════════════════════════════════════

let _fatFechRows = []; // { numero_contrato, cnpj, seguradora, vidas, premio, dataEnvio, raw }

function fatOpenImportFechamento(){
  // Pré-preenche competência com a do mês anterior (caso comum: fechar abril em maio)
  const hoje = new Date();
  const mAnt = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
  setVal('mfe-comp', mAnt.toISOString().slice(0,7));
  setVal('mfe-data-envio', new Date().toISOString().slice(0,10));
  setVal('mfe-modo', 'sobrescrever');
  document.getElementById('mfe-file').value = '';
  document.getElementById('mfe-preview').style.display = 'none';
  document.getElementById('mfe-preview').innerHTML = '';
  document.getElementById('mfe-btn-aplicar').disabled = true;
  _fatFechRows = [];
  openModal('mod-fat-fech');
}

function _fatFechNorm(s){
  return String(s||'').trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/\s+/g,' ');
}
function _fatFechCleanCnpj(s){
  return String(s||'').replace(/\D/g,'').slice(-14);
}
// Normaliza nº de contrato pra comparação:
// uppercase, mantém só letras e dígitos (some hífen, ponto, barra, espaço).
// MANTÉM zeros à esquerda — eles podem ser significativos (ex: 0BAMY, 0231965).
// Ex.: "06-FRX" e "06FRX" → ambos "06FRX"; "0302828-2" → "03028282".
function _fatFechCleanContrato(s){
  return String(s||'').toUpperCase().replace(/[^A-Z0-9]/g,'');
}
// Variante "sem zero à esquerda" — usada SÓ como fallback quando o match exato
// falhou (caso típico: Excel salvou "0231965" como número, virou "231965").
function _fatFechStripZ(s){
  const t = String(s||'').replace(/^0+/, '');
  return t || String(s||''); // se era tudo zero, mantém
}
function _fatFechParseDate(v){
  if (v == null || v === '') return null;
  if (typeof v === 'number' && typeof XLSX !== 'undefined' && XLSX.SSF){
    const o = XLSX.SSF.parse_date_code(v);
    if (o) return `${o.y}-${String(o.m).padStart(2,'0')}-${String(o.d).padStart(2,'0')}`;
  }
  const s = String(v).trim();
  let m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
  if (m) return `${m[1]}-${m[2].padStart(2,'0')}-${m[3].padStart(2,'0')}`;
  m = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
  if (m){ let y=m[3]; if(y.length===2) y='20'+y; return `${y}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`; }
  return null;
}
function _fatFechParseNum(v){
  if (v == null || v === '') return null;
  if (typeof v === 'number') return v;
  const s = String(v).replace(/[R$\s]/g,'').replace(/\./g,'').replace(',', '.');
  const n = parseFloat(s);
  return isNaN(n) ? null : n;
}
// ISO YYYY-MM → "Mes/AA" (ex: "2026-04" → "Abr/26")
function _fatFechCompIso2BR(iso){
  const m = String(iso||'').match(/^(\d{4})-(\d{2})$/);
  if (!m) return '';
  const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
  return meses[parseInt(m[2])-1] + '/' + m[1].slice(-2);
}

async function fatFechamentoPreview(){
  const file = document.getElementById('mfe-file').files[0];
  if (!file) return;
  const compIso = val('mfe-comp');
  if (!compIso){ alert('Selecione a competência alvo primeiro.'); document.getElementById('mfe-file').value=''; return; }
  const compBR = _fatFechCompIso2BR(compIso);

  if (typeof XLSX === 'undefined'){ alert('Biblioteca de planilhas ainda não carregou. Recarregue a página.'); return; }

  const reader = new FileReader();
  reader.onload = e => {
    try {
      const data = new Uint8Array(e.target.result);
      const wb = XLSX.read(data, { type:'array' });
      const ws = wb.Sheets[wb.SheetNames[0]];
      const rows = XLSX.utils.sheet_to_json(ws, { header:1, defval:'', raw:true });
      if (!rows.length){ alert('Planilha vazia.'); return; }

      const header = rows[0].map(_fatFechNorm);
      const idxOf = (...aliases) => {
        for (const a of aliases){ const i = header.indexOf(_fatFechNorm(a)); if (i>=0) return i; }
        return -1;
      };
      const cols = {
        contrato:   idxOf('apolice/contrato','apólice/contrato','apolice / contrato','apólice / contrato','numero do contrato','número do contrato','numero contrato','número contrato','n contrato','nº contrato','contrato','apolice','apólice'),
        cnpj:       idxOf('cnpj'),
        seguradora: idxOf('seguradora'),
        vidas:      idxOf('vidas','numero vidas','número vidas','nº vidas','n vidas'),
        premio:     idxOf('premio','prêmio','premio liquido','prêmio líquido','premio líquido','prêmio liquido','prêmio liq.','premio liq.'),
        dataEnvio:  idxOf('data envio','data de envio','envio')
      };
      if (cols.contrato < 0 || cols.vidas < 0 || cols.premio < 0){
        alert('A planilha precisa ter as colunas: Apólice/Contrato (ou Número do Contrato), Vidas e Prêmio Líquido.\nColunas detectadas: ' + header.join(', '));
        return;
      }

      // Parse linhas — chave de match agora é o numero_contrato
      _fatFechRows = [];
      for (let i=1; i<rows.length; i++){
        const r = rows[i]; if (!r) continue;
        const contrato = _fatFechCleanContrato(r[cols.contrato]);
        if (!contrato) continue;
        _fatFechRows.push({
          numero_contrato: contrato,
          cnpj:        cols.cnpj       >= 0 ? _fatFechCleanCnpj(r[cols.cnpj]) : '',
          seguradora:  cols.seguradora >= 0 ? String(r[cols.seguradora]||'').trim() : '',
          vidas:       cols.vidas      >= 0 ? _fatFechParseNum(r[cols.vidas])  : null,
          premio:      cols.premio     >= 0 ? _fatFechParseNum(r[cols.premio]) : null,
          dataEnvio:   cols.dataEnvio  >= 0 ? _fatFechParseDate(r[cols.dataEnvio]) : null
        });
      }

      // Casa contra fatData (já filtrado pela competência alvo)
      const previewBox = document.getElementById('mfe-preview');
      const params = { competencia: compIso };
      apiGet('listar_faturamento', params).then(resp => {
        const candidatos = (resp.data || []).filter(d => d.competencia === compBR);
        // Mapa principal: chave exata (alfanum, com zeros)
        const matchByKey = new Map();
        // Mapa fallback: chave sem zeros à esquerda (pra cobrir Excel que comeu o "0")
        const matchByKeyStripped = new Map();
        candidatos.forEach(d => {
          const k = _fatFechCleanContrato(d.numero_contrato);
          if (!k) return;
          if (!matchByKey.has(k)) matchByKey.set(k, []);
          matchByKey.get(k).push(d);
          const ks = _fatFechStripZ(k);
          if (ks !== k){
            if (!matchByKeyStripped.has(ks)) matchByKeyStripped.set(ks, []);
            matchByKeyStripped.get(ks).push(d);
          }
        });

        let casadas = 0, orfas = 0, dupla = 0, casadasFallback = 0;
        const orfasList = [];
        const dupladasList = [];
        const casadasList = [];
        _fatFechRows.forEach(r => {
          const k = r.numero_contrato;
          let matches = matchByKey.get(k) || [];
          let viaFallback = false;
          if (matches.length === 0){
            // Tenta sem zero à esquerda — útil quando Excel converteu "0231965" em 231965
            const ks = _fatFechStripZ(k);
            if (ks !== k){
              const cand = matchByKeyStripped.get(ks) || [];
              if (cand.length > 0){ matches = cand; viaFallback = true; }
            }
          }
          if (matches.length === 0){ orfas++; orfasList.push(r); r._match = null; }
          else if (matches.length > 1){ dupla++; dupladasList.push(r); r._match = matches[0]; r._viaFallback = viaFallback; if (viaFallback) casadasFallback++; }
          else { casadas++; casadasList.push(r); r._match = matches[0]; r._viaFallback = viaFallback; if (viaFallback) casadasFallback++; }
        });

        let html = `<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:10px">
          <div style="padding:8px;border-radius:6px;background:rgba(63,185,80,.12);color:#3fb950"><strong>${casadas}</strong> linha(s) vão ser atualizadas</div>
          <div style="padding:8px;border-radius:6px;background:rgba(212,61,61,.12);color:#d43d3d"><strong>${orfas}</strong> contrato(s) não encontrado(s) em ${compBR}</div>
          <div style="padding:8px;border-radius:6px;background:rgba(240,136,62,.12);color:#f0883e"><strong>${dupla}</strong> contrato(s) duplicado(s)</div>
        </div>`;

        if (casadasFallback > 0){
          html += `<div style="padding:7px 10px;border-radius:6px;background:rgba(88,166,255,.10);color:#58a6ff;font-size:12px;margin-bottom:8px">
            <strong>${casadasFallback}</strong> casaram só depois de ignorar zero à esquerda — provavelmente o Excel converteu o contrato em número e perdeu o "0". Revise antes de aplicar.
          </div>`;
        }

        if (orfas > 0) {
          html += `<details style="margin-bottom:8px"><summary style="cursor:pointer;color:#d43d3d;font-weight:600">Ver ${orfas} contrato(s) órfão(s) — não vão ser tocados</summary><div style="margin-top:6px;font-size:11.5px">` +
            orfasList.slice(0, 50).map(r => `Contrato <strong>${esc(r.numero_contrato)}</strong> · ${esc(r.seguradora)||'—'} · CNPJ ${r.cnpj||'—'}`).join('<br>') +
            (orfas > 50 ? `<br>... e mais ${orfas - 50}` : '') +
            '</div></details>';
        }
        if (dupla > 0) {
          html += `<details style="margin-bottom:8px"><summary style="cursor:pointer;color:#f0883e;font-weight:600">Ver ${dupla} duplicata(s) — só a primeira será atualizada</summary><div style="margin-top:6px;font-size:11.5px">` +
            dupladasList.slice(0, 30).map(r => `Contrato <strong>${esc(r.numero_contrato)}</strong> · ${esc(r.seguradora)||'—'}`).join('<br>') +
            '</div></details>';
        }
        if (casadasList.length > 0) {
          html += `<details><summary style="cursor:pointer;color:#3fb950;font-weight:600">Ver primeiras ${Math.min(15, casadasList.length)} que vão ser atualizadas</summary><div style="margin-top:6px;font-size:11.5px">` +
            casadasList.slice(0, 15).map(r => `<strong>${esc(r._match.empresa||'?')}</strong> · contrato ${esc(r.numero_contrato)} · vidas: ${r.vidas||'—'} · prêmio: ${r.premio!=null?fmtBRL(r.premio):'—'}`).join('<br>') +
            '</div></details>';
        }

        previewBox.innerHTML = html;
        previewBox.style.display = '';
        document.getElementById('mfe-btn-aplicar').disabled = (casadas === 0);
      });

    } catch(err){
      console.error(err);
      alert('Erro ao ler planilha: ' + err.message);
    }
  };
  reader.readAsArrayBuffer(file);
}

async function fatFechamentoAplicar(btn){
  const compIso = val('mfe-comp');
  const compBR  = _fatFechCompIso2BR(compIso);
  const dataEnvioPadrao = val('mfe-data-envio') || new Date().toISOString().slice(0,10);
  const modo    = val('mfe-modo'); // 'sobrescrever' | 'completar'

  const updates = _fatFechRows
    .filter(r => r._match)
    .map(r => ({
      id: r._match.id,
      vidas:  r.vidas,
      premio: r.premio,
      data_envio: r.dataEnvio || dataEnvioPadrao
    }));

  if (!updates.length){ alert('Nada a aplicar.'); return; }
  if (!confirm(`Confirma atualizar ${updates.length} faturamento(s) da competência ${compBR}?`)) return;

  btn.disabled = true;
  const prev = btn.innerHTML;
  btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px">hourglass_top</span> Aplicando...';

  try {
    const r = await apiPost('importar_fechamento_mensal', {
      competencia: compBR,
      modo,
      updates
    });
    if (r.ok){
      toast(`✓ ${r.atualizados || 0} faturamento(s) atualizado(s)`);
      closeModal('mod-fat-fech');
      fatLoad();
    } else {
      alert('Erro: ' + (r.msg || 'falha ao aplicar'));
    }
  } catch(e){
    console.error(e);
    alert('Erro de rede: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = prev;
  }
}

// ── Modal de confirmação estilizado (substitui o confirm() do navegador) ──
function fatConfirmModal(opt){
  opt = opt || {};
  return new Promise((resolve) => {
    const ov = document.createElement('div');
    ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:12000;display:flex;align-items:center;justify-content:center;padding:16px';
    ov.innerHTML =
      '<div style="background:var(--bg-card,#fff);color:var(--text);border:1px solid var(--border);border-radius:16px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden">' +
        '<div style="display:flex;align-items:center;gap:10px;padding:18px 22px 6px">' +
          '<span class="material-symbols-rounded" style="color:var(--accent);font-size:26px">event_available</span>' +
          '<h3 style="margin:0;font-size:17px">' + (opt.titulo || 'Confirmar') + '</h3>' +
        '</div>' +
        '<div style="padding:6px 22px 4px;font-size:14px;color:var(--muted);line-height:1.55">' + (opt.corpo || '') + '</div>' +
        '<div style="display:flex;justify-content:flex-end;gap:10px;padding:16px 22px 20px">' +
          '<button type="button" class="btn btn-outline" data-act="no">Cancelar</button>' +
          '<button type="button" class="btn btn-primary" data-act="yes"><span class="material-symbols-rounded" style="font-size:17px">check</span> ' + (opt.okTxt || 'Confirmar') + '</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(ov);
    const fim = (v) => { ov.remove(); document.removeEventListener('keydown', onKey); resolve(v); };
    const onKey = (e) => { if (e.key === 'Escape') fim(false); if (e.key === 'Enter') fim(true); };
    document.addEventListener('keydown', onKey);
    ov.addEventListener('click', (e) => { if (e.target === ov) fim(false); });
    ov.querySelector('[data-act="no"]').addEventListener('click', () => fim(false));
    ov.querySelector('[data-act="yes"]').addEventListener('click', () => fim(true));
    const y = ov.querySelector('[data-act="yes"]'); if (y) y.focus();
  });
}
