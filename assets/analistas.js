// ═══════════════════════════════════════════════════════════════
// ANALISTAS.JS — Equipe de Benefícios
// Cards de cada analista com sua carteira + CRUD completo
// ═══════════════════════════════════════════════════════════════

window.IS_ADMIN  = (typeof IS_ADMIN  !== 'undefined') ? IS_ADMIN  : true;
window.CAN_VIEW_ALL = (typeof CAN_VIEW_ALL !== 'undefined') ? CAN_VIEW_ALL : window.IS_ADMIN;

const _AN_COLORS = (typeof window.COLORS !== 'undefined' && Array.isArray(window.COLORS) && window.COLORS.length)
  ? window.COLORS
  : ['#00c9a7','#58a6ff','#bc8cff','#ffa657','#3fb950','#f85149','#d29922','#79c0ff','#ff7b72','#a5d6ff'];

if (typeof window.esc !== 'function')    window.esc    = s => { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; };
if (typeof window.escAttr !== 'function') window.escAttr = s => window.esc(s).replace(/"/g,'&quot;');
if (typeof window.toast !== 'function')  window.toast  = m => alert(m);
if (typeof window.openModal !== 'function')  window.openModal  = id => { const el=document.getElementById(id); if(el) el.classList.add('show'); };
if (typeof window.closeModal !== 'function') window.closeModal = id => { const el=document.getElementById(id); if(el) el.classList.remove('show'); };
if (typeof window.apiGet !== 'function')  window.apiGet  = async (a,p={}) => (await fetch('api.php?'+new URLSearchParams({action:a,...p}))).json();
if (typeof window.apiPost !== 'function') window.apiPost = async (a,b={}) => (await fetch('api.php?action='+encodeURIComponent(a),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)})).json();

let _analData = [];
let _demandasMap = {};
const _tarefasExpanded = {};

// Label amigável pra cada tipo de usuário (igual ao getRoleLabel do PHP)
function getRoleLabelJS(tipo){
  switch(String(tipo||'')){
    case 'admin':          return 'Admin';
    case 'head':           return 'Head';
    case 'coordenador':    return 'Coordenador';
    case 'enfermeira':
    case 'enfermagem':     return 'Enfermeira';
    case 'financeiro':     return 'Financeiro';
    case 'novos_negocios': return 'Novos Negócios';
    case 'comercial_cotacao': return 'Comercial (Cotação)';
    case 'visualizador':   return 'Visualizador';
    case 'analista':       return 'Analista';
    default:               return tipo || 'Analista';
  }
}

// Tipos que NÃO mostram contador de demandas (lideranças e áreas-suporte)
function tipoMostraDemandas(tipo){
  return ['analista','enfermeira','enfermagem'].includes(String(tipo||'').toLowerCase());
}

// Monta o texto que vai abaixo do nome no card.
//   - Analista/Enfermeira: "Analista · 12 demandas"
//   - Coordenador:        "Coordenador · Pós-Venda"  (usa o papel como área)
//   - Outros:             só o papel/título (sem demandas)
function getCardSubtitle(tipo, demandas, papel){
  const role = getRoleLabelJS(tipo);
  if (tipoMostraDemandas(tipo)) {
    return `${role} · ${demandas} demanda${demandas !== 1 ? 's' : ''}`;
  }
  // Coordenador: se tiver papel, mostra como área (Novos Negócios, Pós-Venda etc)
  if (String(tipo||'').toLowerCase() === 'coordenador' && papel) {
    return `${role} · ${papel}`;
  }
  return role;
}

// Só dispara o load automático se a página tiver o grid (analistas.php).
// Em outras telas (ex: cadastros.php) o modal é reusado, mas a listagem não.
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('anal-grid')) loadAnalistas();
  // Garante as opções de Tipo no modal (Equipe e Cadastros), mesmo que o HTML
  // do select esteja desatualizado em alguma página.
  const _mt = document.getElementById('ma-tipo');
  if (_mt) {
    [['novos_negocios','Novos Negócios'], ['comercial_cotacao','Comercial (Cotação)'], ['visualizador','Visualizador']].forEach(([v, l]) => {
      if (!_mt.querySelector('option[value="' + v + '"]')) {
        const o = document.createElement('option'); o.value = v; o.textContent = l; _mt.appendChild(o);
      }
    });
  }
});

async function loadAnalistas(){
  console.log('[Analistas] Carregando equipe...');
  document.getElementById('anal-grid').innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:30px;color:var(--muted)">Carregando equipe...</div>`;

  let lastError = null;
  try {
    const [ra, rd] = await Promise.all([
      apiGet('analistas'),
      apiGet('listar')
    ]);
    console.log('[Analistas] Recebidos:', (ra.data||[]).length, 'analistas |', (rd.data||[]).length, 'demandas');
    _analData = ra.data || [];
    const demands = rd.data || [];

    _demandasMap = {};
    demands.forEach(d => {
      if (!d.analista_nome || d.analista_nome === 'null') return;
      _demandasMap[d.analista_nome] = (_demandasMap[d.analista_nome] || 0) + 1;
    });
  } catch(e){
    _analData = [];
    lastError = e;
    console.error('[Analistas] Erro fetch:', e);
  }

  if (lastError) {
    document.getElementById('anal-grid').innerHTML = `<div style="grid-column:1/-1;background:rgba(248,81,73,.1);border:1px solid var(--danger);border-radius:8px;padding:20px;text-align:center">
      <div style="font-weight:700;color:var(--danger);margin-bottom:6px">⚠️ Falha ao carregar a equipe</div>
      <div style="font-size:12px;color:var(--muted)">${esc(lastError.message || 'erro desconhecido')}</div>
      <div style="font-size:12px;color:var(--muted);margin-top:6px">Verifique o Console (F12) e se o api.php está atualizado.</div>
    </div>`;
    return;
  }

  // Filtro por tipo (vem do select #anal-filtro-tipo)
  const filtroTipo = (document.getElementById('anal-filtro-tipo')?.value || '').toLowerCase();
  let _vistos = _analData;
  if (filtroTipo) {
    // 'enfermeira' aceita 'enfermagem' (alias antigo) também
    if (filtroTipo === 'enfermeira') {
      _vistos = _analData.filter(a => ['enfermeira','enfermagem'].includes(String(a.tipo||'').toLowerCase()));
    } else {
      _vistos = _analData.filter(a => String(a.tipo||'').toLowerCase() === filtroTipo);
    }
  }

  // Único card de resumo: Total da Equipe (filtrado pelo tipo selecionado)
  setText('anal-tot', _vistos.length);

  // Esconde o card inteiro quando filtra por Financeiro ou Visualizador
  // (áreas de suporte — não precisam de métrica de equipe)
  const tiposSemCards = ['financeiro', 'visualizador'];
  const cardsBox = document.getElementById('anal-cards');
  if (cardsBox) cardsBox.style.display = tiposSemCards.includes(filtroTipo) ? 'none' : '';

  // Grid
  document.getElementById('anal-grid').innerHTML = _vistos.map((a, i) => {
    const color = _AN_COLORS[i % _AN_COLORS.length];
    const init = (a.nome || '??').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
    const n = _demandasMap[a.nome] || 0;
    const papeis = (a.papel || '').split(',').map(p => p.trim()).filter(Boolean);
    const tarefaList = (a.tarefas || '').split(/\\n|\n/).map(t => t.trim()).filter(Boolean);

    const avatar = a.foto
      ? `<img src="${escAttr(a.foto)}" style="width:48px;height:48px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid ${color}">`
      : `<div style="width:48px;height:48px;border-radius:50%;background:${color};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;flex-shrink:0">${init}</div>`;

    const exp = _tarefasExpanded[i];
    const tarefasShow = exp ? tarefaList : tarefaList.slice(0, 3);
    const moreLabel = exp ? '— Mostrar menos' : `+ ${tarefaList.length - 3} mais tarefas`;

    return `<div class="card anal-card" style="padding:22px 20px;text-align:left;border-top:3px solid ${color}" onclick="editAnalista(${a.id})">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        ${avatar}
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;color:var(--text);font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(a.nome || '—')}</div>
          <div style="font-size:11px;color:var(--muted)">${getCardSubtitle(a.tipo, n, a.papel)}</div>
        </div>
      </div>

      ${papeis.length ? `<div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px">
        ${papeis.map(p => `<span style="background:rgba(0,201,167,.1);color:var(--accent);padding:2px 8px;border-radius:12px;font-size:10.5px;font-weight:600;border:1px solid rgba(0,201,167,.2)">${esc(p)}</span>`).join('')}
      </div>` : ''}

      ${tarefaList.length ? `<div style="max-height:${exp?'none':'160px'};overflow-y:auto;padding-right:4px">
        ${tarefasShow.map(t => `<div style="font-size:11.5px;color:var(--muted);padding:3px 0;border-bottom:1px solid var(--border);display:flex;gap:6px;align-items:flex-start">
          <span style="color:var(--accent);flex-shrink:0;margin-top:1px">•</span>${esc(t)}
        </div>`).join('')}
        ${tarefaList.length > 3 ? `<div onclick="event.stopPropagation();toggleTarefas(${i})" style="font-size:11px;color:var(--accent);margin-top:6px;cursor:pointer;font-weight:600">${moreLabel}</div>` : ''}
      </div>` : ''}

      ${a.email ? `<div style="margin-top:10px;font-size:11px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escAttr(a.email)}"><span class="material-symbols-rounded" style="font-size:12px;vertical-align:middle">mail</span> ${esc(a.email)}</div>` : ''}
    </div>`;
  }).join('') || '<p style="grid-column:1/-1;color:var(--muted);text-align:center;padding:30px">Nenhum analista cadastrado. Clique em "Novo Analista" pra começar.</p>';
}

function setText(id, v){ const el = document.getElementById(id); if (el) el.textContent = v; }

function toggleTarefas(idx){
  _tarefasExpanded[idx] = !_tarefasExpanded[idx];
  loadAnalistas(); // re-render simples
}

// ── MODAL CADASTRO/EDIÇÃO ───────────────────────────────────────
function openModalAnalista(){
  document.getElementById('ma-id').value = '';
  document.getElementById('ma-title').innerHTML = '<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">person_add</span> Novo Analista';
  document.getElementById('ma-btn-del').style.display = 'none';
  ['ma-nome','ma-login','ma-email','ma-papel','ma-tarefas','ma-senha','ma-senha2','ma-grupo'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  document.getElementById('ma-tipo').value = 'analista';
  var _gr = document.getElementById('ma-grupo-row'); if (_gr) _gr.style.display = 'none';
  document.getElementById('ma-foto-preview').textContent = 'Nenhuma foto carregada';
  document.getElementById('ma-foto').value = '';
  openModal('mod-anal');
}

async function editAnalista(id){
  // Procura primeiro em qualquer cache disponível (analistas.php OU cadastros.php)
  let a = (_analData || []).find(x => x.id == id);
  if (!a && typeof cachedAnalistasAll !== 'undefined') {
    a = (cachedAnalistasAll || []).find(x => x.id == id);
  }
  // Se ainda não achou (ex: aberto fora dessas páginas), busca via API
  if (!a) {
    try {
      const r = await apiGet('analistas');
      a = (r.data || []).find(x => x.id == id);
    } catch (e) { /* ignora */ }
  }
  if (!a) { toast('Analista não encontrado'); return; }

  openModalAnalista();
  document.getElementById('ma-id').value = id;
  document.getElementById('ma-title').innerHTML = `<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">edit</span> Editar Analista #${id}`;
  document.getElementById('ma-btn-del').style.display = '';

  document.getElementById('ma-nome').value    = a.nome || '';
  document.getElementById('ma-login').value   = a.login || '';
  document.getElementById('ma-email').value   = a.email || '';
  document.getElementById('ma-tipo').value    = a.tipo || 'analista';
  document.getElementById('ma-papel').value   = a.papel || '';
  document.getElementById('ma-tarefas').value = a.tarefas || '';
  var _grEl = document.getElementById('ma-grupo'); if (_grEl) _grEl.value = a.grupo || '';
  var _grRow = document.getElementById('ma-grupo-row'); if (_grRow) _grRow.style.display = ((a.tipo||'')==='cliente' ? '' : 'none');

  const previewEl = document.getElementById('ma-foto-preview');
  if (a.foto) {
    previewEl.innerHTML = `<img src="${escAttr(a.foto)}" style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid var(--accent)">
      <button type="button" class="btn btn-danger btn-sm" onclick="removerFotoAnalista(${id})" style="margin-left:10px;vertical-align:middle">Remover</button>`;
  } else {
    previewEl.textContent = 'Sem foto';
  }
}

async function saveAnalista(){
  const id = document.getElementById('ma-id').value;
  const nome    = document.getElementById('ma-nome').value.trim();
  const login   = document.getElementById('ma-login').value.trim();
  const email   = document.getElementById('ma-email').value.trim();
  const tipo    = document.getElementById('ma-tipo').value;
  const papel   = document.getElementById('ma-papel').value.trim();
  const tarefas = document.getElementById('ma-tarefas').value.trim();
  const senha   = document.getElementById('ma-senha').value;
  const senha2  = document.getElementById('ma-senha2').value;
  const grupoEl = document.getElementById('ma-grupo');
  const grupo   = grupoEl ? grupoEl.value.trim() : '';

  if (!nome) { toast('Nome obrigatório'); return; }
  if (senha && senha !== senha2) { toast('As senhas não coincidem'); return; }
  if (tipo === 'cliente' && !grupo) { toast('Para tipo Cliente, informe o Grupo (ex.: MERCANTIL)'); return; }

  const body = { id: id || null, nome, login, email, tipo, papel, tarefas, grupo };
  if (senha) body.senha = senha;

  const action = id ? 'editar_analista' : 'criar_analista';
  const r = await apiPost(action, body);
  if (!r.ok) { toast('Erro: ' + (r.msg || 'falha ao salvar')); return; }

  // Upload de foto se selecionou
  const fotoInput = document.getElementById('ma-foto');
  if (fotoInput.files && fotoInput.files[0]) {
    const newId = id || r.id;
    const fd = new FormData();
    fd.append('id', newId);
    fd.append('foto', fotoInput.files[0]);
    try {
      const rf = await fetch('api.php?action=upload_foto_analista', { method:'POST', body: fd }).then(x => x.json());
      if (!rf.ok) toast('Analista salvo, mas falha ao subir foto: ' + (rf.msg || ''));
    } catch(e){ toast('Analista salvo, mas erro no upload de foto'); }
  }

  toast(id ? 'Integrante atualizado' : 'Integrante criado');
  if (tipo === 'cliente' && grupo) {
    const slug = grupo.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
    const url  = location.origin + '/demandas-mercantil' + (slug ? ('?e=' + slug) : '');
    const msg  = 'Acesso do cliente pronto!\n\nURL do portal:\n' + url + '\n\nLogin: ' + login + '\n(envie este link e o login/senha ao cliente)';
    if (typeof uiAlert === 'function') uiAlert(msg, 'Portal do cliente — ' + grupo); else alert(msg);
  }
  closeModal('mod-anal');
  if (document.getElementById('anal-grid')) loadAnalistas();          // página Equipe
  if (typeof loadAnalistasAll === 'function') loadAnalistasAll();     // página Cadastros
}

async function deleteAnalista(){
  const id = document.getElementById('ma-id').value;
  if (!id) return;
  if (!confirm('Excluir esse analista? As demandas atribuídas a ele NÃO serão apagadas.')) return;
  const r = await apiGet('excluir_analista', { id });
  if (r.ok) {
    toast('Analista excluído');
    closeModal('mod-anal');
    if (document.getElementById('anal-grid')) loadAnalistas();
    if (typeof loadAnalistasAll === 'function') loadAnalistasAll();
  } else {
    toast('Erro: ' + (r.msg || 'falha ao excluir'));
  }
}

async function removerFotoAnalista(id){
  if (!confirm('Remover a foto do analista?')) return;
  const r = await apiGet('excluir_foto_analista', { id });
  if (r.ok) {
    toast('Foto removida');
    loadAnalistas();
    closeModal('mod-anal');
  } else {
    toast('Erro: ' + (r.msg || 'falha ao remover'));
  }
}
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// padding padding padding padding padding padding
// FIM REAL
