// ═══════════════════════════════════════════════════════════════
// CADASTROS.JS — Gestão de Seguradoras, Empresas e Analistas
// (porte do legado index.php: loadSeguradoras/loadEmpresas/loadAnalistasAll)
// ═══════════════════════════════════════════════════════════════

window.IS_ADMIN  = (typeof IS_ADMIN  !== 'undefined') ? IS_ADMIN  : true;
window.IS_VISUALIZADOR = (typeof IS_VISUALIZADOR !== 'undefined') ? IS_VISUALIZADOR : false;
window.CAN_VIEW_ALL = (typeof CAN_VIEW_ALL !== 'undefined') ? CAN_VIEW_ALL : window.IS_ADMIN;

// ─── STUBS WINDOW.* (carregam IMEDIATO, blindados contra truncamento) ────────
// Se o arquivo for cortado no meio, essas atribuições garantem que TODOS os onclick="..."
// inline da página vão encontrar a função (mesmo que vazia). As reais sobrescrevem depois.
window.openModalEmp     = window.openModalEmp     || function(){ alert('Carregando módulo... Tente novamente em 1 segundo.'); };
window.openModalEmpById = window.openModalEmpById || function(){ alert('Carregando módulo... Tente novamente em 1 segundo.'); };
window.editAnalista     = window.editAnalista     || function(){ alert('Carregando módulo... Tente novamente em 1 segundo.'); };
window.saveEmpresa      = window.saveEmpresa      || function(){ alert('Carregando módulo... Tente novamente em 1 segundo.'); };
window.cadAdd           = window.cadAdd           || function(){ alert('Carregando módulo... Tente novamente em 1 segundo.'); };
window.cadDel           = window.cadDel           || function(){ alert('Carregando módulo... Tente novamente em 1 segundo.'); };
window.filtrarSeguradoras    = window.filtrarSeguradoras    || function(){};
window.filtrarEmpresas       = window.filtrarEmpresas       || function(){};
window.filtrarSeguradorasTbl = window.filtrarSeguradorasTbl || function(){};
window.filtrarEmpresasTbl    = window.filtrarEmpresasTbl    || function(){};
window.filtrarAnalistasTbl   = window.filtrarAnalistasTbl   || function(){};
window.cadCalcMesRen         = window.cadCalcMesRen         || function(){};
// ─── Re-atribui as versões REAIS depois que tudo carregou ────────────────
if (typeof openModalEmp     === 'function') window.openModalEmp     = openModalEmp;
if (typeof openModalEmpById === 'function') window.openModalEmpById = openModalEmpById;
if (typeof editAnalista     === 'function') window.editAnalista     = editAnalista;
if (typeof saveEmpresa      === 'function') window.saveEmpresa      = saveEmpresa;
if (typeof cadAdd           === 'function') window.cadAdd           = cadAdd;
if (typeof cadDel           === 'function') window.cadDel           = cadDel;
if (typeof filtrarSeguradoras    === 'function') window.filtrarSeguradoras    = filtrarSeguradoras;
if (typeof filtrarEmpresas       === 'function') window.filtrarEmpresas       = filtrarEmpresas;
if (typeof filtrarSeguradorasTbl === 'function') window.filtrarSeguradorasTbl = filtrarSeguradorasTbl;
if (typeof filtrarEmpresasTbl    === 'function') window.filtrarEmpresasTbl    = filtrarEmpresasTbl;
if (typeof filtrarAnalistasTbl   === 'function') window.filtrarAnalistasTbl   = filtrarAnalistasTbl;
if (typeof cadCalcMesRen         === 'function') window.cadCalcMesRen         = cadCalcMesRen;


// Normaliza valores antigos de "Porte da empresa" para o formato novo do select.
// Os valores antigos ("3 a 29 vidas", "30 a 99 vidas", "acima de 100 vidas")
// continuam no banco — esta função só mapeia em memória pra que o select
// reconheça a opção e o badge da lista mostre a versão atualizada.
function _cadNormalizePorte(v){
  if (v == null || v === '') return '';
  const s = String(v).trim().toLowerCase();
  if (!s) return '';
  if (s.includes('até 30')   || s.startsWith('3 a 29') || s.includes('até 29')) return 'Até 30 vidas';
  if (s.includes('30 e 100') || s.startsWith('30 a 99') || s.includes('30 a 100')) return 'Entre 30 e 100 vidas';
  if (s.includes('100 e 900') || s.includes('100 a 900') || s.includes('acima de 100')) return 'Entre 100 e 900';
  if (s.includes('1000')     || s.includes('acima de 1000')) return 'Acima de 1000';
  return String(v).trim();
}

// Auto-preenche a "Data de renovação" a partir da "Início de vigência".
// Pega o dia/mês da vigência e aplica no ano corrente — mesma lógica usada
// na tela de Renovação (`_proxRenovacao`). Só preenche se o campo de data
// estiver vazio, pra não sobrescrever ajuste manual do usuário.
function cadCalcMesRen(){
  const vig = document.getElementById('ee-ini-vig');
  const dt  = document.getElementById('ee-mes-ren');
  if (!vig || !dt || !vig.value) return;
  if (dt.value) return; // já preenchido manualmente — não sobrescreve
  const m = vig.value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!m) return;
  const anoAtual = new Date().getFullYear();
  // Input type=date aceita YYYY-MM-DD (preserva mês e dia da vigência, ano corrente)
  dt.value = `${anoAtual}-${m[2]}-${m[3]}`;
}

if (typeof window.esc !== 'function')    window.esc    = s => { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; };
if (typeof window.escAttr !== 'function') window.escAttr = s => window.esc(s).replace(/"/g,'&quot;');
if (typeof window.toast !== 'function')  window.toast  = m => alert(m);
if (typeof window.numToBR !== 'function') window.numToBR = v => { const n=parseFloat(v); return isNaN(n)?'0':n.toLocaleString('pt-BR',{minimumFractionDigits:0,maximumFractionDigits:2}); };
if (typeof window.brToNum !== 'function') window.brToNum = v => { if(v==null) return 0; const s=String(v).trim().replace(/[R$\s.]/g,'').replace(',','.'); const n=parseFloat(s); return isNaN(n)?0:n; };
if (typeof window.openModal !== 'function')  window.openModal  = id => { const el=document.getElementById(id); if(el) el.classList.add('show'); };
if (typeof window.closeModal !== 'function') window.closeModal = id => { const el=document.getElementById(id); if(el) el.classList.remove('show'); };
if (typeof window.apiGet !== 'function')  window.apiGet  = async (a,p={}) => (await fetch('api.php?'+new URLSearchParams({action:a,...p}))).json();
if (typeof window.apiPost !== 'function') window.apiPost = async (a,b={}) => (await fetch('api.php?action='+encodeURIComponent(a),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)})).json();

// Função auxiliar
function setVal(id, v){ const el=document.getElementById(id); if(el) el.value = (v==null?'':v); }

// editAnalista: definida no TOPO pra garantir que existe mesmo se o arquivo for cortado
function editAnalista(id){
  const a = (cachedAnalistasAll||[]).find(x => x.id == id);
  if (!a) { toast('Analista não encontrado'); return; }
  setVal('ma-id', a.id);
  setVal('ma-nome', a.nome);
  setVal('ma-login', a.login);
  setVal('ma-email', a.email);
  setVal('ma-tipo', a.tipo || 'analista');
  setVal('ma-grupo', a.grupo || '');
  var _grRow = document.getElementById('ma-grupo-row'); if (_grRow) _grRow.style.display = ((a.tipo||'')==='cliente' ? '' : 'none');
  setVal('ma-papel', a.papel);
  setVal('ma-tarefas', a.tarefas);
  setVal('ma-senha', '');
  setVal('ma-senha2', '');
  const btnDel = document.getElementById('ma-btn-del');
  if (btnDel) btnDel.style.display = '';
  const title = document.getElementById('ma-title');
  if (title) title.innerHTML = '<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">edit</span> Editar Integrante';
  openModal('mod-anal');
}

let cachedSeguradoras = [], cachedEmpresas = [], cachedAnalistasAll = [];

document.addEventListener('DOMContentLoaded', async () => {
  await Promise.all([
    loadSeguradoras(),
    loadEmpresas(),
    loadAnalistasAll()
  ]);
});

// ─── SEGURADORAS ────────────────────────────────────────────────
async function loadSeguradoras(){
  try { const r = await apiGet('seguradoras'); cachedSeguradoras = r.data || []; }
  catch(e){ console.warn('seguradoras:', e); return; }
  renderListaSeguradoras(cachedSeguradoras);
  try { renderTabelaSeguradoras(cachedSeguradoras); } catch(e){ console.warn('renderTabelaSeguradoras:', e); }
}

function renderListaSeguradoras(lista){
  const list = document.getElementById('cad-seg-list');
  if (!list) return;
  list.innerHTML = lista.map(s => `
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-bottom:1px solid var(--border)">
      <span style="font-size:13px">${esc(s.nome)}</span>
      ${window.IS_VISUALIZADOR ? '' : `<button class="btn btn-danger btn-sm" onclick="cadDel('seguradora',${s.id})" title="Excluir"><span class="material-symbols-rounded" style="font-size:14px">close</span></button>`}
    </div>`).join('') || '<p style="color:var(--muted);font-size:13px;padding:10px">Nenhuma seguradora encontrada.</p>';
}

function filtrarSeguradoras(){
  const q = (document.getElementById('cad-seg-filter').value || '').toLowerCase().trim();
  if (!q) { renderListaSeguradoras(cachedSeguradoras); return; }
  const norm = s => (s||'').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
  const qn = norm(q);
  renderListaSeguradoras(cachedSeguradoras.filter(s => norm(s.nome).includes(qn)));
}

// ─── EMPRESAS ───────────────────────────────────────────────────
async function loadEmpresas(){
  try { const r = await apiGet('empresas'); cachedEmpresas = r.data || []; }
  catch(e){ console.warn('empresas:', e); return; }
  renderListaEmpresas(cachedEmpresas);
  try { renderTabelaEmpresas(cachedEmpresas); } catch(e){ console.warn('renderTabelaEmpresas:', e); }
}

function renderListaEmpresas(lista){
  const list = document.getElementById('cad-emp-list');
  if (!list) return;
  list.innerHTML = lista.map(e => {
    const badges = [];
    if (e.origem)      badges.push(`<span style="background:rgba(0,201,167,.1);color:var(--accent);padding:1px 7px;border-radius:10px;font-size:10px;font-weight:600">${esc(e.origem)}</span>`);
    if (e.ramo)        badges.push(`<span class="ramo ${e.ramo==='Saúde'?'r-saude':'r-odonto'}" style="font-size:10px;padding:1px 7px">${esc(e.ramo)}</span>`);
    if (e.seguradora)  badges.push(`<span style="background:rgba(88,166,255,.08);color:var(--info);padding:1px 7px;border-radius:10px;font-size:10px;font-weight:600">${esc(e.seguradora)}</span>`);
    if (e.porte)       badges.push(`<span style="background:rgba(210,153,34,.08);color:var(--warn);padding:1px 7px;border-radius:10px;font-size:10px;font-weight:600">${esc(_cadNormalizePorte(e.porte))}</span>`);
    if (e.tipo_estipulacao) badges.push(`<span style="background:rgba(139,148,158,.1);color:var(--muted);padding:1px 7px;border-radius:10px;font-size:10px;font-weight:600">${esc(e.tipo_estipulacao)}</span>`);
    const badgesHtml = badges.length ? `<div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:4px">${badges.join('')}</div>` : '';

    const details = [];
    if (e.cnpj) details.push(esc(e.cnpj));
    if (e.numero_contrato) details.push(`Contrato ${esc(e.numero_contrato)}`);
    if (e.percentual_comissao) details.push(`${numToBR(e.percentual_comissao * 100)}% com.`);
    if (e.analista_nome) details.push(esc(e.analista_nome));
    const detailsHtml = details.length ? `<div style="font-size:11px;color:var(--muted);margin-top:2px">${details.join(' · ')}</div>` : '';

    // Esconde JSON do empresa pra passar em onclick (escape de aspas)
    const empJson = JSON.stringify(e).replace(/'/g, "\\'").replace(/"/g, '&quot;');

    return `
    <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:10px;border-bottom:1px solid var(--border)">
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:700">${esc(e.grupo)}</div>
        <div style="font-size:11.5px;color:var(--muted)">${esc(e.nome)}</div>
        ${detailsHtml}
        ${badgesHtml}
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0;margin-left:8px">
        ${window.IS_VISUALIZADOR ? '' : `
        <button class="btn btn-outline btn-sm" onclick="openModalEmpById(${e.id})" title="Editar"><span class="material-symbols-rounded" style="font-size:14px">edit</span></button>
        <button class="btn btn-danger btn-sm" onclick="cadDel('empresa',${e.id})" title="Excluir"><span class="material-symbols-rounded" style="font-size:14px">close</span></button>
        `}
      </div>
    </div>`;
  }).join('') || '<p style="color:var(--muted);font-size:13px;padding:10px">Nenhuma empresa cadastrada.</p>';
}

function filtrarEmpresas(){
  const q = (document.getElementById('cad-emp-filter').value || '').toLowerCase().trim();
  if (!q) { renderListaEmpresas(cachedEmpresas); return; }
  const filt = cachedEmpresas.filter(e => (
    (e.grupo||'').toLowerCase().includes(q) ||
    (e.nome||'').toLowerCase().includes(q) ||
    (e.cnpj||'').toLowerCase().includes(q) ||
    (e.numero_contrato||'').toLowerCase().includes(q) ||
    (e.seguradora||'').toLowerCase().includes(q)
  ));
  renderListaEmpresas(filt);
}

// ─── LISTAGEM EM TABELA — EMPRESAS ─────────────────────────────
function renderTabelaEmpresas(lista){
  const tbody = document.getElementById('cad-emp-tbl-body');
  const count = document.getElementById('cad-emp-tbl-count');
  if (!tbody) return;
  if (count) count.textContent = `${lista.length} de ${cachedEmpresas.length}`;
  if (!lista.length){
    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:20px;color:var(--muted)">Nenhuma empresa encontrada</td></tr>`;
    return;
  }
  const isVis = window.IS_VISUALIZADOR;
  tbody.innerHTML = lista.map(e => {
    let ramoCls = 'ramo-default';
    if (/saúde|saude/i.test(e.ramo||'')) ramoCls = 'ramo-saude';
    else if (/odonto/i.test(e.ramo||'')) ramoCls = 'ramo-odonto';
    else if (/vida/i.test(e.ramo||'')) ramoCls = 'ramo-vida';
    const ramoHtml = e.ramo ? `<span class="ramo-tag ${ramoCls}">${esc(e.ramo)}</span>` : '—';
    const acoes = isVis ? '' : `<td class="acoes">
      <button class="btn btn-outline btn-sm" onclick="openModalEmpById(${e.id})" title="Editar"><span class="material-symbols-rounded" style="font-size:13px">edit</span></button>
      <button class="btn btn-danger btn-sm" onclick="cadDel('empresa',${e.id})" title="Excluir"><span class="material-symbols-rounded" style="font-size:13px">close</span></button>
    </td>`;
    return `<tr>
      <td style="color:var(--muted)">${e.id}</td>
      <td style="font-weight:700;color:var(--info)">${esc(e.grupo||'—')}</td>
      <td>${esc(e.nome||'—')}</td>
      <td style="font-family:Consolas,monospace;font-size:11.5px">${esc(e.cnpj||'—')}</td>
      <td>${esc(e.numero_contrato||'—')}</td>
      <td>${esc(e.seguradora||'—')}</td>
      <td>${ramoHtml}</td>
      <td>${esc(e.analista_nome||'—')}</td>
      ${acoes}
    </tr>`;
  }).join('');
}

function filtrarEmpresasTbl(){
  const q = (document.getElementById('cad-emp-tbl-filter').value || '').toLowerCase().trim();
  if (!q){ renderTabelaEmpresas(cachedEmpresas); return; }
  const filt = cachedEmpresas.filter(e => (
    (e.grupo||'').toLowerCase().includes(q) ||
    (e.nome||'').toLowerCase().includes(q) ||
    (e.cnpj||'').toLowerCase().includes(q) ||
    (e.numero_contrato||'').toLowerCase().includes(q) ||
    (e.seguradora||'').toLowerCase().includes(q) ||
    (e.analista_nome||'').toLowerCase().includes(q) ||
    (e.ramo||'').toLowerCase().includes(q)
  ));
  renderTabelaEmpresas(filt);
}

// ─── ANALISTAS ──────────────────────────────────────────────────
async function loadAnalistasAll(){
  try { const r = await apiGet('analistas'); cachedAnalistasAll = r.data || []; }
  catch(e){ console.warn('analistas:', e); return; }
  try { renderTabelaAnalistas(cachedAnalistasAll); } catch(e){ console.warn('renderTabelaAnalistas:', e); }
  const list = document.getElementById('cad-anal-list');
  if (!list) return;
  list.innerHTML = cachedAnalistasAll.map(a => {
    const init = (a.nome||'').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
    const fotoHtml = a.foto
      ? `<img src="${escAttr(a.foto)}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0">`
      : `<div style="width:36px;height:36px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0">${init}</div>`;
    return `
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:10px">
        ${fotoHtml}
        <div>
          <span style="font-size:13px;font-weight:600">${esc(a.nome)}</span>
          <br><span style="font-size:11.5px;color:var(--muted)">${esc(a.login||'')} · <span style="color:${a.tipo==='admin'?'var(--accent)':'var(--muted)'}">${esc(a.tipo||'analista')}</span></span>
        </div>
      </div>
      <div style="display:flex;gap:6px">
        ${window.IS_VISUALIZADOR ? '' : `
        <button class="btn btn-outline btn-sm" onclick="editAnalista(${a.id})" title="Editar"><span class="material-symbols-rounded" style="font-size:14px">edit</span></button>
        <button class="btn btn-danger btn-sm" onclick="cadDel('analista',${a.id})" title="Excluir"><span class="material-symbols-rounded" style="font-size:14px">close</span></button>
        `}
      </div>
    </div>`;
  }).join('') || '<p style="color:var(--muted);font-size:13px;padding:10px">Nenhum analista cadastrado.</p>';
}

// ─── ADD / DELETE GENÉRICOS ─────────────────────────────────────
async function cadAdd(tipo){
  let body = {};
  if (tipo === 'seguradora'){
    const nome = document.getElementById('cad-seg-nome').value.trim();
    if (!nome){ toast('Informe o nome da seguradora'); return; }
    body = { nome };
    document.getElementById('cad-seg-nome').value = '';
  } else if (tipo === 'empresa'){
    openModalEmp();
    return;
  } else if (tipo === 'analista'){
    const nome  = document.getElementById('cad-anal-nome').value.trim();
    const login = document.getElementById('cad-anal-login').value.trim();
    const senha = document.getElementById('cad-anal-senha').value.trim();
    const tipoUser = document.getElementById('cad-anal-tipo').value;
    const grupoEl = document.getElementById('cad-anal-grupo');
    const grupo = grupoEl ? grupoEl.value.trim() : '';
    if (!nome || !login || !senha){ toast('Preencha nome, login e senha'); return; }
    if (tipoUser === 'cliente' && !grupo){ toast('Para tipo Cliente, informe o Grupo (ex.: MERCANTIL)'); return; }
    body = { nome, login, senha, tipo: tipoUser, grupo };
    ['cad-anal-nome','cad-anal-login','cad-anal-senha'].forEach(id => document.getElementById(id).value = '');
    if (grupoEl) grupoEl.value = '';
  }
  const r = await apiPost('criar_' + tipo, body);
  if (r.ok){
    toast(tipo.charAt(0).toUpperCase() + tipo.slice(1) + ' cadastrada!');
    if (tipo === 'seguradora') await loadSeguradoras();
    else if (tipo === 'empresa') await loadEmpresas();
    else if (tipo === 'analista') await loadAnalistasAll();
    if (tipo === 'analista' && body.tipo === 'cliente' && body.grupo) {
      const slug = body.grupo.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
      const url  = location.origin + '/portal' + (slug ? ('?e=' + slug) : '');
      const msg  = 'Acesso do cliente pronto!\n\nURL do portal:\n' + url + '\n\nLogin: ' + body.login + '\n(envie este link e o login/senha ao cliente)';
      if (typeof uiAlert === 'function') uiAlert(msg, 'Portal do cliente — ' + body.grupo); else alert(msg);
    }
  } else {
    toast(r.msg || 'Erro ao cadastrar');
  }
}

async function cadDel(tipo, id){
  if (!confirm(`Excluir este registro de ${tipo}?`)) return;
  const r = await apiGet('excluir_' + tipo, { id });
  if (r.ok){
    toast(tipo.charAt(0).toUpperCase() + tipo.slice(1) + ' excluída.');
    if (tipo === 'seguradora') await loadSeguradoras();
    else if (tipo === 'empresa') await loadEmpresas();
    else if (tipo === 'analista') await loadAnalistasAll();
  } else {
    toast(r.msg || 'Erro ao excluir');
  }
}

// ─── Abre a tela completa de Passagem de Bastão da empresa atual ───
// Pega o id da empresa no botão e redireciona pra passagem_bastao.php com
// um parâmetro ?edit=ID, que vai abrir o form completo já pré-preenchido.
window.empAbrirPassagemBastao = function(){
  const btn = document.getElementById('ee-btn-pb');
  const id = btn ? btn.dataset.empId : '';
  if (!id) { alert('Selecione uma empresa primeiro.'); return; }
  // Abre em nova aba pra não perder o que estiver preenchendo no cadastro
  window.open('passagem_bastao.php?edit=' + encodeURIComponent(id), '_blank');
};

window.empEnviarFaturamento = async function(btn){
  const id = (document.getElementById('ee-id')||{}).value;
  if (!id) { alert('Salve a empresa primeiro.'); return; }
  if (!confirm('Enviar esta empresa para o Faturamento da competência atual?')) return;
  if (btn) btn.disabled = true;
  try {
    const r = await apiPost('enviar_faturamento_empresa', { empresa_id: parseInt(id, 10) });
    if (r.ok) {
      toast(r.msg || (r.ja_existia ? 'Já estava no faturamento.' : 'Enviada para o faturamento.'));
    } else {
      toast(r.msg || 'Erro ao enviar para faturamento');
    }
  } catch(e) {
    toast('Erro de conexão ao enviar para faturamento');
  } finally {
    if (btn) btn.disabled = false;
  }
};

// ─── MODAL EMPRESA / CONTRATO (cadastro rico) ───────────────────
function openModalEmpById(id){
  const e = cachedEmpresas.find(x => x.id == id);
  openModalEmp(e);
}

function openModalEmp(e){
  e = e || {};
  // Popular dropdowns
  const selS = document.getElementById('ee-seguradora');
  selS.innerHTML = '<option value="">—</option>' +
    cachedSeguradoras.map(s => `<option value="${escAttr(s.nome)}">${esc(s.nome)}</option>`).join('');
  const selA = document.getElementById('ee-analista-sel');
  selA.innerHTML = '<option value="">—</option>' +
    cachedAnalistasAll.map(a => `<option value="${a.id}">${esc(a.nome)}</option>`).join('');

  // Botão "Visualizar / Editar Passagem de Bastão" — sempre visível ao
  // EDITAR uma empresa existente. Some quando é criação de empresa nova
  // (sem id ainda, precisa salvar primeiro).
  const btnPB = document.getElementById('ee-btn-pb');
  if (btnPB) {
    if (!e.id) {
      btnPB.style.display = 'none';
    } else {
      btnPB.style.display = 'inline-flex';
      btnPB.dataset.empId = e.id;
      btnPB.innerHTML = '<span class="material-symbols-rounded" style="font-size:17px">swap_horiz</span> Visualizar / Editar Passagem de Bastão';
    }
  }
  // Botão "Enviar para Faturamento" — só ao editar empresa existente
  const btnFat = document.getElementById('ee-btn-fat');
  if (btnFat) btnFat.style.display = e.id ? 'inline-flex' : 'none';

  // Limpa e preenche
  document.getElementById('ee-id').value         = e.id || '';
  document.getElementById('ee-origem').value     = e.origem           || '';
  document.getElementById('ee-ramo').value       = e.ramo             || '';
  document.getElementById('ee-seguradora').value = e.seguradora       || '';
  document.getElementById('ee-contrato').value   = e.numero_contrato  || '';
  document.getElementById('ee-cnpj').value       = e.cnpj             || '';
  document.getElementById('ee-tipo').value       = e.tipo_estipulacao || '';
  document.getElementById('ee-grupo').value      = e.grupo            || '';
  document.getElementById('ee-nome').value       = e.nome             || '';
  document.getElementById('ee-porte').value      = _cadNormalizePorte(e.porte || '');
  // Analista pelo ID, fallback por nome
  if (e.analista_id) {
    selA.value = e.analista_id;
  } else if (e.analista_nome) {
    const norm = s => String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').trim();
    const alvo = norm(e.analista_nome);
    const m = (cachedAnalistasAll||[]).find(a => norm(a.nome) === alvo);
    selA.value = m ? m.id : '';
  } else {
    selA.value = '';
  }
  document.getElementById('ee-data-emi').value   = e.data_emissao    || '';
  document.getElementById('ee-data-venc').value  = e.data_vencimento || '';
  document.getElementById('ee-ini-vig').value    = e.inicio_vigencia || '';
  // mes_renovacao no banco vem como YYYY-MM-DD; input type=date aceita o mesmo formato
  document.getElementById('ee-mes-ren').value    = (e.mes_renovacao || '').slice(0,10);
  // Se a data de renovação está vazia mas tem vigência, auto-calcula a partir
  // do dia/mês da vigência no ano corrente (mesma regra da tela de Renovação).
  if (typeof cadCalcMesRen === 'function') cadCalcMesRen();
  document.getElementById('ee-vidas').value      = e.numero_vidas != null ? e.numero_vidas : '';
  document.getElementById('ee-perc-com').value   = e.percentual_comissao ? numToBR(e.percentual_comissao * 100) : '';
  document.getElementById('ee-emails-fat').value = e.emails_faturamento || '';
  document.getElementById('ee-resp-nome').value  = e.resp_nome      || '';
  document.getElementById('ee-resp-email').value = e.resp_email     || '';
  document.getElementById('ee-resp-tel').value   = e.resp_telefone  || '';
  document.getElementById('ee-obs').value        = e.observacao     || '';

  document.getElementById('me-title').innerHTML = (e.id
    ? '<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--info)">edit</span> Editar Empresa / Contrato'
    : '<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--info)">apartment</span> Novo Cadastro de Empresa / Contrato');

  openModal('modal-emp-ov');
}

// Consulta o CNPJ na Receita e preenche a razão social (Empresa).
async function eeConsultarCnpj(btn){
  const info = document.getElementById('ee-cnpj-info');
  const cnpj = (document.getElementById('ee-cnpj').value || '').replace(/\D/g,'');
  if (cnpj.length !== 14){ info.style.color='#c0392b'; info.textContent='Digite um CNPJ com 14 dígitos.'; return; }
  if (btn) btn.disabled = true;
  info.style.color='var(--muted)'; info.textContent='Consultando…';
  try{
    const r = await apiGet('consultar_cnpj', { cnpj });
    if (r.ok && r.data){
      const d = r.data;
      const nome = document.getElementById('ee-nome');
      if (!nome.value.trim()) nome.value = d.razao_social || '';
      const partes = [d.razao_social];
      if (d.municipio || d.uf) partes.push((d.municipio||'')+(d.uf?('/'+d.uf):''));
      if (d.situacao) partes.push('situação: '+d.situacao);
      info.style.color='var(--success,#28a745)';
      info.textContent = '✓ '+partes.filter(Boolean).join(' · ')+(nome.value.trim()?'':' (razão social preenchida)');
      if (window.toast) toast('CNPJ encontrado');
    } else {
      info.style.color='#c0392b'; info.textContent = r.msg || 'CNPJ não encontrado.';
    }
  }catch(e){ info.style.color='#c0392b'; info.textContent='Erro na consulta.'; }
  if (btn) btn.disabled = false;
}

async function saveEmpresa(){
  const id   = document.getElementById('ee-id').value;
  const aSel = document.getElementById('ee-analista-sel');
  const body = {
    id: id || undefined,
    // Regra: origem vazia sempre vira 'INTERWEG' (defesa no front)
    origem:           document.getElementById('ee-origem').value.trim() || 'INTERWEG',
    ramo:             document.getElementById('ee-ramo').value,
    seguradora:       document.getElementById('ee-seguradora').value,
    numero_contrato:  document.getElementById('ee-contrato').value.trim(),
    cnpj:             document.getElementById('ee-cnpj').value.trim(),
    tipo_estipulacao: document.getElementById('ee-tipo').value,
    grupo:            document.getElementById('ee-grupo').value.trim(),
    nome:             document.getElementById('ee-nome').value.trim(),
    porte:            document.getElementById('ee-porte').value,
    analista_id:      aSel.value,
    analista_nome:    aSel.options[aSel.selectedIndex]?.text || '',
    data_emissao:     document.getElementById('ee-data-emi').value || null,
    data_vencimento:  document.getElementById('ee-data-venc').value || null,
    inicio_vigencia:  document.getElementById('ee-ini-vig').value || null,
    // input type=date dá YYYY-MM-DD (data completa); salva direto
    mes_renovacao:    document.getElementById('ee-mes-ren').value || null,
    numero_vidas:     (() => {
      const v = document.getElementById('ee-vidas').value.trim();
      return v === '' ? null : parseInt(v);
    })(),
    percentual_comissao: document.getElementById('ee-perc-com').value.trim() === '' ? '' : (brToNum(document.getElementById('ee-perc-com').value) / 100),
    emails_faturamento:  document.getElementById('ee-emails-fat').value.trim(),
    resp_nome:        document.getElementById('ee-resp-nome').value.trim(),
    resp_email:       document.getElementById('ee-resp-email').value.trim(),
    resp_telefone:    document.getElementById('ee-resp-tel').value.trim(),
    observacao:       document.getElementById('ee-obs').value.trim()
  };
  const faltando = [];
  if (!body.grupo)             faltando.push('Grupo');
  if (!body.nome)              faltando.push('Empresa');
  if (!body.numero_contrato)   faltando.push('Número do contrato');
  if (!body.cnpj)              faltando.push('CNPJ');
  if (!body.tipo_estipulacao)  faltando.push('Estipulante / Subestipulante');
  if (!body.porte)             faltando.push('Porte da empresa');
  if (body.percentual_comissao === '' || body.percentual_comissao == null) faltando.push('% de comissão');
  if (faltando.length){ toast('Preencha os campos obrigatórios: ' + faltando.join(', ')); return; }

  const r = await apiPost(id ? 'editar_empresa' : 'criar_empresa', body);
  if (r.ok){
    closeModal('modal-emp-ov');
    let msg;
    if (r.reativado) {
      msg = 'Empresa estava inativa e foi reativada com os dados atualizados';
    } else {
      msg = id ? 'Empresa atualizada!' : 'Empresa cadastrada!';
    }
    if (id && (r.fat_atualizados > 0 || r.ren_atualizados > 0)){
      const partes = [];
      if (r.fat_atualizados > 0) partes.push(`${r.fat_atualizados} faturamento${r.fat_atualizados>1?'s':''}`);
      if (r.ren_atualizados > 0) partes.push(`${r.ren_atualizados} renovação${r.ren_atualizados>1?'ões':''}`);
      msg += ' (também atualizados: ' + partes.join(' + ') + ')';
    }
    toast(msg);
    await loadEmpresas();
  } else {
    toast(r.msg || 'Erro ao salvar');
  }
}

// ─── LISTAGEM EM TABELA — EQUIPE / ANALISTAS ─────────────────────
function renderTabelaAnalistas(lista){
  const tbody = document.getElementById('cad-anal-tbl-body');
  const count = document.getElementById('cad-anal-tbl-count');
  if (!tbody) return;
  if (count) count.textContent = `${lista.length} de ${(window.cachedAnalistasAll||cachedAnalistasAll||[]).length}`;
  if (!lista.length){
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--muted)">Nenhum integrante encontrado</td></tr>`;
    return;
  }
  const isVis = window.IS_VISUALIZADOR;
  tbody.innerHTML = lista.map(a => {
    const tipoLabel = {
      admin:'Admin', head:'Head', coordenador:'Coordenador',
      enfermeira:'Enfermeira', enfermagem:'Enfermeira',
      analista:'Analista', financeiro:'Financeiro',
      visualizador:'Visualizador', novos_negocios:'Novos Negócios',
      comercial_cotacao:'Comercial (Cotação)'
    }[(a.tipo||'').toLowerCase()] || (a.tipo||'analista');
    const acoes = isVis ? '' : `<td class="acoes">
      <button class="btn btn-outline btn-sm" onclick="editAnalista(${a.id})" title="Editar"><span class="material-symbols-rounded" style="font-size:13px">edit</span></button>
      <button class="btn btn-danger btn-sm" onclick="cadDel('analista',${a.id})" title="Excluir"><span class="material-symbols-rounded" style="font-size:13px">close</span></button>
    </td>`;
    return `<tr>
      <td style="color:var(--muted)">${a.id}</td>
      <td style="font-weight:600">${esc(a.nome||'—')}</td>
      <td style="font-family:Consolas,monospace;font-size:11.5px">${esc(a.login||'—')}</td>
      <td><span class="tag-tipo">${esc(tipoLabel)}</span></td>
      <td style="font-size:11.5px">${esc(a.papel||'—')}</td>
      ${acoes}
    </tr>`;
  }).join('');
}

function filtrarAnalistasTbl(){
  const q = (document.getElementById('cad-anal-tbl-filter').value || '').toLowerCase().trim();
  if (!q){ renderTabelaAnalistas(cachedAnalistasAll); return; }
  const filt = cachedAnalistasAll.filter(a => (
    (a.nome||'').toLowerCase().includes(q) ||
    (a.login||'').toLowerCase().includes(q) ||
    (a.tipo||'').toLowerCase().includes(q) ||
    (a.papel||'').toLowerCase().includes(q)
  ));
  renderTabelaAnalistas(filt);
}

// ─── LISTAGEM EM TABELA — SEGURADORAS ────────────────────────────
function renderTabelaSeguradoras(lista){
  const tbody = document.getElementById('cad-seg-tbl-body');
  const count = document.getElementById('cad-seg-tbl-count');
  if (!tbody) return;
  const total = (window.cachedSeguradoras || cachedSeguradoras || []).length;
  if (count) count.textContent = `${lista.length} de ${total}`;
  if (!lista.length){
    tbody.innerHTML = `<tr><td colspan="3" style="text-align:center;padding:20px;color:var(--muted)">Nenhuma seguradora encontrada</td></tr>`;
    return;
  }
  const isVis = window.IS_VISUALIZADOR;
  tbody.innerHTML = lista.map(s => {
    const acoes = isVis ? '' : `<td class="acoes">
      <button class="btn btn-danger btn-sm" onclick="cadDel('seguradora',${s.id})" title="Excluir"><span class="material-symbols-rounded" style="font-size:13px">close</span></button>
    </td>`;
    return `<tr>
      <td style="color:var(--muted)">${s.id}</td>
      <td style="font-weight:600">${esc(s.nome||'—')}</td>
      ${acoes}
    </tr>`;
  }).join('');
}

function filtrarSeguradorasTbl(){
  const q = (document.getElementById('cad-seg-tbl-filter').value || '').toLowerCase().trim();
  if (!q){ renderTabelaSeguradoras(cachedSeguradoras); return; }
  const norm = s => (s||'').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
  const qn = norm(q);
  renderTabelaSeguradoras(cachedSeguradoras.filter(s => norm(s.nome).includes(qn)));
}

// ═════════════════════════════════════════════════════════════════════════════
// PADDING DEFENSIVO — IGNORE
// O servidor (File Manager Hostinger) está cortando os últimos bytes do arquivo.
// Para proteger o código real acima, adicionei estas linhas que podem ser
// truncadas sem afetar nada. Se for editar o arquivo, NÃO remova este bloco.
// ═════════════════════════════════════════════════════════════════════════════
// padding linha 01
// padding linha 02
// padding linha 03
// padding linha 04
// padding linha 05
// padding linha 06
// padding linha 07
// padding linha 08
// padding linha 09
// padding linha 10
// padding linha 11
// padding linha 12
// padding linha 13
// padding linha 14
// padding linha 15
// padding linha 16
// padding linha 17
// padding linha 18
// padding linha 19
// padding linha 20
// padding linha 21
// padding linha 22
// padding linha 23
// padding linha 24
// padding linha 25
// padding linha 26
// padding linha 27
// padding linha 28
// padding linha 29
// padding linha 30
// padding linha 31
// padding linha 32
// padding linha 33
// padding linha 34
// padding linha 35
// padding linha 36
// padding linha 37
// padding linha 38
// padding linha 39
// padding linha 40
// padding linha 41
// padding linha 42
// padding linha 43
// padding linha 44
// padding linha 45
// padding linha 46
// padding linha 47
// padding linha 48
// padding linha 49
// padding linha 50
// fim do padding — qualquer truncamento abaixo desta linha NÃO afeta o código
