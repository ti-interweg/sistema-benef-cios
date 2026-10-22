// ═══════════════════════════════════════════════════════════════
// DEVOLUTIVA NUTRICIONAL — Enfermagem
// Lê respostas (do banco por campanha, ou CSV/XLSX) e gera, por pergunta,
// um gráfico (fechadas/múltiplas) ou lista (abertas) + análise editável.
// ═══════════════════════════════════════════════════════════════

if (typeof window.toast !== 'function') window.toast = m => { try { alert(m); } catch(e){} };

const DN_CHARTJS = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
const DN_CORES = ['#0a8a8d','#00bfb3','#26305a','#58a6ff','#d29922','#3fb950','#e07b39','#9b59b6','#e74c3c','#16a085','#f39c12','#7f8c8d'];

let DN_DATA = null;
let DN_QUESTOES = [];
const DN_CHARTS = {};
let DN_CAMPANHA_ID = null;
let DN_ANALISES = {};
let DN_READONLY = false;
let DN_SAVE_TIMER = null;

function dnEsc(s){ const d=document.createElement('div'); d.textContent = (s==null?'':s); return d.innerHTML; }
// Argumento seguro para uso dentro de onclick="..." (aspas viram &quot; pra não quebrar o atributo)
function dnAttrArg(v){ return JSON.stringify(v).replace(/&/g,'&amp;').replace(/"/g,'&quot;'); }

function dnLoadScript(src){
  return new Promise((res, rej) => {
    if (window.Chart) return res();
    const s = document.createElement('script');
    s.src = src; s.onload = res; s.onerror = () => rej(new Error('falha ao carregar '+src));
    document.head.appendChild(s);
  });
}

// Divide múltipla escolha por vírgula, sem quebrar dentro de parênteses
function dnSplitOpcoes(v){
  const out=[]; let cur=''; let depth=0;
  for (const ch of String(v)){
    if (ch==='(') depth++;
    if (ch===')') depth=Math.max(0,depth-1);
    if (ch===',' && depth===0){ if(cur.trim()) out.push(cur.trim()); cur=''; }
    else cur+=ch;
  }
  if (cur.trim()) out.push(cur.trim());
  return out;
}

// ─── Importação manual (CSV/XLSX) ────────────────────────────
function dnImportar(input){
  const file = input.files && input.files[0];
  if (!file) return;
  if (!window.XLSX){ toast('Biblioteca de leitura (XLSX) não carregada. Recarregue a página.'); input.value=''; return; }
  const info = document.getElementById('dn-file-info');
  if (info) info.textContent = 'Lendo ' + file.name + '…';
  const reader = new FileReader();
  reader.onload = e => {
    try {
      const wb = XLSX.read(new Uint8Array(e.target.result), { type:'array' });
      const ws = wb.Sheets[wb.SheetNames[0]];
      const aoa = XLSX.utils.sheet_to_json(ws, { header:1, defval:'', raw:false });
      if (!aoa.length){ toast('Arquivo vazio.'); return; }
      const header = aoa[0].map(h => String(h||'').trim());
      const rows = aoa.slice(1).filter(r => r.some(c => String(c||'').trim() !== ''));
      DN_CAMPANHA_ID = null; DN_ANALISES = {}; DN_READONLY = false;
      DN_DATA = { header, rows };
      if (info) info.textContent = file.name + ' — ' + rows.length + ' respostas, ' + header.length + ' colunas.';
      dnAnalisar(); dnRender();
    } catch(err){ console.error(err); toast('Erro ao ler o arquivo: ' + err.message); }
    finally { input.value=''; }
  };
  reader.readAsArrayBuffer(file);
}

// ─── Classificação das perguntas ─────────────────────────────
function dnAnalisar(){
  DN_QUESTOES = [];
  const { header, rows } = DN_DATA;
  header.forEach((titulo, i) => {
    const t = titulo.toLowerCase();
    if (!titulo || t.includes('carimbo') || t.includes('timestamp')) return;
    const valores = rows.map(r => String(r[i]==null?'':r[i]).trim()).filter(v => v !== '');
    if (!valores.length) return;
    let multi = false;
    const contagem = {};
    valores.forEach(v => {
      const ops = dnSplitOpcoes(v);
      if (ops.length > 1) multi = true;
      ops.forEach(o => { contagem[o] = (contagem[o]||0) + 1; });
    });
    const distintos = Object.keys(contagem);
    const tamMedio = distintos.reduce((s,o)=>s+o.length,0) / (distintos.length||1);
    const aberta = (distintos.length > 15) || (tamMedio > 60) || (distintos.length > valores.length*0.8 && tamMedio > 25);
    DN_QUESTOES.push({ idx:i, titulo, aberta, multi, nResp: valores.length, contagem, valores });
  });
}

// ─── Render ──────────────────────────────────────────────────
function dnRender(){
  const head = document.getElementById('dn-card-head');
  const cont = document.getElementById('dn-card');
  if (head) head.style.display = DN_READONLY ? 'none' : 'block';
  const bp = document.getElementById('dn-btn-print'); if (bp) bp.style.display = '';
  const bl = document.getElementById('dn-btn-limpar'); if (bl) bl.style.display = DN_READONLY ? 'none' : '';
  const bs = document.getElementById('dn-btn-salvar'); if (bs) bs.style.display = DN_READONLY ? 'none' : '';

  const empEl = document.getElementById('dn-empresa');
  const titEl = document.getElementById('dn-titulo');
  const nEl   = document.getElementById('dn-n');
  const meta = DN_CAMPANHA_ID ? {} : dnGetMeta();
  if (empEl && !empEl.value) empEl.value = meta.empresa || '';
  if (titEl && !titEl.value) titEl.value = meta.titulo || 'Devolutiva da Anamnese';
  if (nEl) nEl.value = DN_DATA.rows.length + ' pessoas';

  Object.values(DN_CHARTS).forEach(c => { try{ c.destroy(); }catch(e){} });

  cont.innerHTML = DN_QUESTOES.map(q => {
    const right = DN_READONLY
      ? '<div class="dn-analise"><label>Análise / orientação da enfermeira</label>'
        + '<div class="dn-analise-ro" style="white-space:pre-wrap;font-size:14px;line-height:1.55;color:var(--text);background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:12px 14px;min-height:60px">'
        + dnEsc(DN_ANALISES[String(q.idx)]||'—') + '</div></div>'
      : '<div class="dn-analise"><label style="display:flex;justify-content:space-between;align-items:center;gap:8px">Análise / orientação da enfermeira'
        + '<button type="button" class="dn-ia-btn" onclick="dnGerarIA(this)" style="border:1px solid var(--accent);background:rgba(0,191,179,.08);color:var(--accent);border-radius:8px;padding:3px 10px;font-size:11px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;text-transform:none;letter-spacing:0;font-family:inherit"><span class="material-symbols-rounded" style="font-size:15px">auto_awesome</span> Gerar com IA</button></label>'
        + '<textarea data-q="' + q.idx + '" placeholder="Escreva aqui os principais motivos, impactos e orientações com base nas respostas… (ou clique em Gerar com IA)" oninput="dnSalvarAnalise(this)" onblur="dnSalvarAnaliseAgora(this)"></textarea><div class="dn-analise-print"></div></div>';
    let left;
    if (q.aberta){
      const itens = q.valores.map(function(v, i){ return '<div class="dn-open-item"><span class="dn-open-num">' + (i+1) + '</span><span class="dn-open-txt">' + dnEsc(v) + '</span></div>'; }).join('');
      left = '<div><div class="dn-badge" style="margin-bottom:6px">' + q.nResp + ' resposta(s) abertas</div>'
           + '<div class="dn-open-list">' + (itens || '<div style="color:var(--muted);padding:8px">Sem respostas</div>') + '</div></div>';
    } else {
      left = '<div class="dn-chart-wrap"><canvas id="dn-chart-' + q.idx + '"></canvas></div>'
           + '<div class="dn-badge" style="margin-top:8px;text-align:center">' + q.nResp + ' de ' + DN_DATA.rows.length + ' responderam' + (q.multi ? ' · múltipla escolha' : '') + '</div>';
    }
    return '<div class="dn-q"><div class="dn-q-title"><span>' + dnEsc(q.titulo) + '</span></div>'
         + '<div class="dn-q-grid">' + left + right + '</div></div>';
  }).join('');

  dnLoadScript(DN_CHARTJS).then(() => {
    DN_QUESTOES.forEach(q => { if (!q.aberta) dnDesenharGrafico(q); });
  }).catch(e => toast('Não foi possível carregar a biblioteca de gráficos: ' + e.message));

  dnRestaurarAnalises();
  if (cont.scrollIntoView) cont.scrollIntoView({ behavior:'smooth', block:'start' });
}

function dnDesenharGrafico(q){
  const el = document.getElementById('dn-chart-' + q.idx);
  if (!el) return;
  const entradas = Object.entries(q.contagem).sort((a,b) => b[1]-a[1]);
  const labels = entradas.map(e => e[0]);
  const dados  = entradas.map(e => e[1]);
  const pequeno = labels.length <= 5 && !q.multi;
  const total = q.nResp;
  if (DN_CHARTS[q.idx]) { try{ DN_CHARTS[q.idx].destroy(); }catch(e){} }
  DN_CHARTS[q.idx] = new Chart(el, {
    type: pequeno ? 'doughnut' : 'bar',
    data: { labels, datasets: [{
      data: dados,
      backgroundColor: pequeno ? labels.map((_,i)=>DN_CORES[i%DN_CORES.length]) : '#0a8a8d',
      borderRadius: pequeno ? 0 : 6, borderWidth: pequeno ? 2 : 0, borderColor: '#fff'
    }]},
    options: {
      indexAxis: pequeno ? 'x' : 'y',
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: pequeno, position:'bottom', labels:{ font:{ size:12 } } },
        tooltip: { callbacks: { label: ctx => {
          const v = ctx.parsed[ pequeno ? 'y' : 'x' ] ?? ctx.parsed;
          const pct = total ? Math.round(v/total*100) : 0;
          return ' ' + v + ' (' + pct + '%)';
        } } }
      },
      scales: pequeno ? {} : { x: { beginAtZero:true, ticks:{ precision:0 } }, y: { ticks:{ font:{ size:12 }, autoSkip:false } } }
    }
  });
  if (!pequeno) el.parentElement.style.height = Math.max(200, labels.length*38 + 40) + 'px';
  else el.parentElement.style.height = '260px';
}

// ─── Análises ────────────────────────────────────────────────
function dnChaveMeta(){ return 'dn_meta'; }
function dnGetMeta(){ try { return JSON.parse(localStorage.getItem(dnChaveMeta())||'{}'); } catch(e){ return {}; } }
function dnSalvarMeta(){
  const e1=document.getElementById('dn-empresa'), e2=document.getElementById('dn-titulo');
  try { localStorage.setItem(dnChaveMeta(), JSON.stringify({ empresa: e1?e1.value:'', titulo: e2?e2.value:'' })); } catch(e){}
}
function dnTituloDe(ta){ const q = DN_QUESTOES.find(x => String(x.idx) === String(ta.dataset.q)); return q ? q.titulo : ta.dataset.q; }
function dnChaveAnalise(titulo){
  const e1=document.getElementById('dn-empresa');
  const emp = (e1 && e1.value ? e1.value : 'geral').trim().toLowerCase();
  return 'dn_analise::' + emp + '::' + titulo;
}
// Botão "Salvar análises": salva TODOS os campos, um a um (jeito tradicional)
function dnSalvarTudo(){
  const tas = document.querySelectorAll('textarea[data-q]');
  if (!tas.length){ toast('Nenhum campo de análise na tela.'); return; }
  if (!DN_CAMPANHA_ID){
    tas.forEach(ta => { try { localStorage.setItem(dnChaveAnalise(dnTituloDe(ta)), ta.value); } catch(e){} });
    dnStatusSalvar('Salvo só neste navegador — abra por "Ver resultados" p/ salvar no link', '#b26a00');
    toast('Salvo neste navegador (não vinculado ao link).');
    return;
  }
  clearTimeout(DN_SAVE_TIMER);
  let seq = Promise.resolve(); let okAll = true;
  tas.forEach(ta => { seq = seq.then(() => dnSalvarCampo(ta).then(r => { if (!r || !r.ok) okAll = false; })); });
  seq.then(() => { toast(okAll ? 'Análises salvas ✓' : 'Algumas análises falharam ao salvar'); });
}
function dnStatusSalvar(txt, cor){
  const el = document.getElementById('dn-save-status');
  if (!el) return;
  el.textContent = txt || '';
  el.style.color = cor || 'var(--muted)';
  el.style.display = txt ? '' : 'none';
}
// oninput: agenda salvar (debounce) o campo atual
function dnSalvarAnalise(ta){
  dnStatusSalvar('Editando…');
  clearTimeout(DN_SAVE_TIMER);
  DN_SAVE_TIMER = setTimeout(function(){ dnSalvarCampo(ta); }, 700);
}
// onblur: salva o campo na hora (ao sair do campo)
function dnSalvarAnaliseAgora(ta){ clearTimeout(DN_SAVE_TIMER); dnSalvarCampo(ta); }

// Gera a análise da pergunta com IA (Claude) a partir dos dados agregados
window.dnGerarIA = async function(btn){
  const wrap = btn.closest('.dn-analise');
  const ta = wrap ? wrap.querySelector('textarea[data-q]') : null;
  if (!ta){ toast('Campo não encontrado'); return; }
  const q = DN_QUESTOES.find(x => String(x.idx) === String(ta.dataset.q));
  if (!q){ toast('Pergunta não encontrada'); return; }
  let resumo;
  if (q.aberta){
    resumo = 'Respostas abertas (' + q.nResp + '):\n' + q.valores.slice(0, 60).map(v => '- ' + v).join('\n');
  } else {
    resumo = Object.entries(q.contagem).sort((a,b) => b[1]-a[1])
      .map(([k,v]) => '- ' + k + ': ' + v + ' (' + (q.nResp ? Math.round(v/q.nResp*100) : 0) + '%)').join('\n');
  }
  const cliente = (document.getElementById('dn-empresa') || {}).value || '';
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:15px">hourglass_top</span> Gerando…';
  try {
    const r = await fetch('api_anamnese?action=gerar_analise_ia', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ pergunta:q.titulo, resumo:resumo, cliente:cliente })
    }).then(x => x.json());
    if (!r.ok){ toast(r.msg || 'Erro ao gerar análise'); return; }
    ta.value = r.texto || '';
    dnSalvarCampo(ta, true);
    toast('Análise gerada e salva ✓');
  } catch(e){ toast('Erro de conexão com a IA'); }
  finally { btn.disabled = false; btn.innerHTML = orig; }
};

// Salva UM campo direto: o texto vem do próprio textarea; 1 linha por pergunta no banco.
function dnSalvarCampo(ta, mostrarToast){
  if (!DN_CAMPANHA_ID){
    try { localStorage.setItem(dnChaveAnalise(dnTituloDe(ta)), ta.value); } catch(e){}
    dnStatusSalvar('Salvo só neste navegador — abra por "Ver resultados" p/ salvar no link', '#b26a00');
    return Promise.resolve(null);
  }
  DN_ANALISES[String(ta.dataset.q)] = ta.value;
  dnStatusSalvar('Salvando…');
  return fetch('api_anamnese?action=salvar_analise', {
    method:'POST', headers:{'Content-Type':'application/json'}, cache:'no-store',
    body: JSON.stringify({
      campanha_id: DN_CAMPANHA_ID,
      q_idx: parseInt(ta.dataset.q, 10),
      titulo: dnTituloDe(ta),
      texto: ta.value
    })
  }).then(function(x){ return x.json(); }).then(function(r){
    if (r && r.ok){
      var h = new Date().toLocaleTimeString('pt-BR', { hour:'2-digit', minute:'2-digit' });
      dnStatusSalvar('Salvo ✓ ' + h + ' · ' + (r.bytes || 0) + ' bytes gravados', '#2e7d32');
      if (mostrarToast) toast('Análise salva ✓');
    } else {
      dnStatusSalvar('Falha ao salvar', '#c0392b');
      toast((r && r.msg) || 'Não foi possível salvar a análise');
    }
    return r;
  }).catch(function(){ dnStatusSalvar('Sem conexão — não salvou', '#c0392b'); toast('Sem conexão — a análise não foi salva'); return null; });
}
function dnRestaurarAnalises(){
  document.querySelectorAll('textarea[data-q]').forEach(ta => {
    const v = DN_CAMPANHA_ID ? DN_ANALISES[String(ta.dataset.q)] : localStorage.getItem(dnChaveAnalise(dnTituloDe(ta)));
    if (v != null) ta.value = v;
  });
}
function dnLimparAnalises(){
  if (!confirm('Limpar todas as análises digitadas desta devolutiva?')) return;
  const tas = document.querySelectorAll('textarea[data-q]');
  tas.forEach(ta => {
    if (!DN_CAMPANHA_ID){ try { localStorage.removeItem(dnChaveAnalise(dnTituloDe(ta))); } catch(e){} }
    ta.value = '';
  });
  if (DN_CAMPANHA_ID){ DN_ANALISES = {}; let seq = Promise.resolve(); tas.forEach(ta => { seq = seq.then(() => dnSalvarCampo(ta)); }); }
  toast('Análises limpas.');
}

// ─── Campanhas / formulários por cliente ─────────────────────
async function dnCarregarCampanhas(){
  const box = document.getElementById('dn-camp-lista');
  if (!box) return;
  try{
    const r = await fetch('api_anamnese?action=listar_campanhas').then(x=>x.json());
    if (!r.ok){ box.innerHTML = '<div style="color:#c0392b;font-size:13px">' + (r.msg||'Erro ao listar') + '</div>'; return; }
    if (!r.data.length){ box.innerHTML = '<div style="color:var(--muted);font-size:13px">Nenhum formulário criado ainda. Crie um acima para gerar o link do cliente.</div>'; return; }
    const base = window.DN_BASE_URL || '';
    box.innerHTML = r.data.map(c => {
      const formUrl = base + '/anamnese?c=' + encodeURIComponent(c.slug);
      const resUrl  = base + '/resultado_anamnese?t=' + encodeURIComponent(c.token_result);
      const ativo = String(c.ativo) === '1';
      return '<div class="dn-camp">'
        + '<div style="min-width:180px"><div style="font-weight:700;color:#26305a">' + dnEsc(c.cliente) + '</div>'
        + '<div class="dn-badge">' + c.n_respostas + ' resposta(s) · ' + (ativo?'<span style="color:#2e7d32">ativo</span>':'<span style="color:#c0392b">encerrado</span>') + '</div></div>'
        + '<div style="display:flex;flex-direction:column;gap:6px">'
        + '<div style="display:flex;gap:6px;align-items:center"><span class="dn-link" title="' + dnEsc(formUrl) + '"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:-3px;color:var(--accent)">link</span> Form: ' + dnEsc(formUrl) + '</span><button class="btn btn-outline btn-sm" onclick="dnCopiar(' + dnAttrArg(formUrl) + ',this)">Copiar</button></div>'
        + '<div style="display:flex;gap:6px;align-items:center"><span class="dn-link" title="' + dnEsc(resUrl) + '"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:-3px;color:var(--accent)">bar_chart</span> Resultados: ' + dnEsc(resUrl) + '</span><button class="btn btn-outline btn-sm" onclick="dnCopiar(' + dnAttrArg(resUrl) + ',this)">Copiar</button></div></div>'
        + '<div style="display:flex;gap:6px">'
        + '<button class="btn btn-primary btn-sm" onclick="dnVerResultados(' + c.id + ',' + dnAttrArg(c.cliente) + ')">Ver resultados</button>'
        + '<button class="btn btn-outline btn-sm" onclick="dnToggleCampanha(' + c.id + ',' + (ativo?0:1) + ')">' + (ativo?'Encerrar':'Reabrir') + '</button>'
        + '<button class="btn btn-outline btn-sm" style="color:#c0392b;border-color:#e0a0a0" onclick="dnExcluirCampanha(' + c.id + ',' + dnAttrArg(c.cliente) + ')">Excluir</button>'
        + '</div></div>';
    }).join('');
  }catch(e){ box.innerHTML = '<div style="color:#c0392b;font-size:13px">Erro de conexão</div>'; }
}

async function dnCriarCampanha(btn){
  const inp = document.getElementById('dn-novo-cliente');
  const cliente = inp.value.trim();
  if (!cliente){ toast('Informe o nome do cliente'); inp.focus(); return; }
  btn.disabled = true;
  try{
    const r = await fetch('api_anamnese?action=criar_campanha', {
      method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ cliente })
    }).then(x=>x.json());
    if (!r.ok){ toast(r.msg || 'Erro ao criar'); return; }
    inp.value=''; toast('Formulário criado! Link pronto para compartilhar.');
    dnCarregarCampanhas();
  }catch(e){ toast('Erro de conexão'); }
  finally{ btn.disabled=false; }
}

function dnCopiar(txt, btn){
  navigator.clipboard.writeText(txt).then(()=>{ if(btn){ const o=btn.textContent; btn.textContent='Copiado!'; setTimeout(()=>btn.textContent=o,1500); } })
    .catch(()=>{ prompt('Copie o link:', txt); });
}

async function dnToggleCampanha(id, ativo){
  try{
    const r = await fetch('api_anamnese?action=toggle_campanha', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id, ativo }) }).then(x=>x.json());
    if (!r.ok){ toast(r.msg||'Erro'); return; }
    dnCarregarCampanhas();
  }catch(e){ toast('Erro de conexão'); }
}

async function dnExcluirCampanha(id, cliente){
  if (!confirm('Excluir o formulário de "' + cliente + '" e TODAS as respostas? Esta ação não pode ser desfeita.')) return;
  try{
    const r = await fetch('api_anamnese?action=excluir_campanha&id=' + id).then(x=>x.json());
    if (!r.ok){ toast(r.msg||'Erro'); return; }
    toast('Formulário excluído.'); dnCarregarCampanhas();
  }catch(e){ toast('Erro de conexão'); }
}

async function dnVerResultados(id, cliente){
  try{
    const r = await fetch('api_anamnese?action=resultados&id=' + id + '&_=' + Date.now(), { cache:'no-store' }).then(x=>x.json());
    if (!r.ok){ toast(r.msg||'Erro ao carregar resultados'); return; }
    if (!r.respostas.length){ toast('Ainda não há respostas para este formulário.'); return; }
    const empEl = document.getElementById('dn-empresa'); if (empEl) empEl.value = cliente || r.cliente || '';
    dnFromRespostas(r.respostas, window.DN_PERGUNTAS_ORDEM, { campanhaId:id, analises:r.analises||{}, readOnly:false });
  }catch(e){ toast('Erro de conexão'); }
}

// Constrói DN_DATA a partir das respostas do banco e renderiza
function dnFromRespostas(respostas, ordem, opts){
  opts = opts || {};
  DN_CAMPANHA_ID = opts.campanhaId || null;
  DN_ANALISES = opts.analises || {};
  DN_READONLY = !!opts.readOnly;
  let header = (ordem && ordem.length) ? ordem.slice() : [];
  if (!header.length){
    const set = new Set(); respostas.forEach(o => Object.keys(o).forEach(k => set.add(k))); header = [...set];
  }
  const rows = respostas.map(o => header.map(h => (o[h] != null ? o[h] : '')));
  DN_DATA = { header, rows };
  dnAnalisar();
  dnRender();
  dnRestaurarAnalises();  // preenche os textareas com as análises já salvas
}

function dnInit(){
  const emp = document.getElementById('dn-empresa');
  if (emp) emp.addEventListener('change', () => { if (DN_DATA) dnRestaurarAnalises(); });
  if (document.getElementById('dn-camp-lista')) dnCarregarCampanhas();
}
// Impressão "limpa": abre uma janela só com o relatório (sem o layout do sistema),
// gráficos viram imagem e análises viram blocos de texto que quebram linha.
window.dnImprimirLimpo = function(){
  const card = document.getElementById('dn-card');
  if (!card){ window.print(); return; }
  const clone = card.cloneNode(true);
  // canvas (gráficos) → imagem
  const oc = card.querySelectorAll('canvas'), cc = clone.querySelectorAll('canvas');
  oc.forEach(function(c, i){
    try {
      const img = document.createElement('img');
      img.src = c.toDataURL('image/png');
      img.style.cssText = 'max-width:320px;width:100%;height:auto;display:block;margin:0 auto';
      if (cc[i] && cc[i].parentNode) cc[i].parentNode.replaceChild(img, cc[i]);
    } catch(e){}
  });
  // textarea (análise) → div com o texto
  const ot = card.querySelectorAll('textarea[data-q]'), ct = clone.querySelectorAll('textarea[data-q]');
  ot.forEach(function(t, i){
    const div = document.createElement('div');
    div.textContent = t.value || '—';
    div.style.cssText = 'white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px;line-height:1.55;color:#000;border:1px solid #ccc;border-radius:8px;padding:10px 12px';
    if (ct[i] && ct[i].parentNode) ct[i].parentNode.replaceChild(div, ct[i]);
  });
  // remove elementos que não vão pro PDF
  clone.querySelectorAll('.dn-ia-btn, .dn-analise-print').forEach(function(el){ el.remove(); });

  const empresa = (document.getElementById('dn-empresa') || {}).value || '';
  const titulo  = (document.getElementById('dn-titulo')  || {}).value || 'Devolutiva de Saúde';
  const w = window.open('', '_blank');
  if (!w){ toast('Permita pop-ups para gerar o PDF.'); return; }
  w.document.write(
    '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>' + dnEsc(titulo) + '</title>'
    + '<style>'
    + 'body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;padding:18px}'
    + 'h1{font-size:20px;color:#0a8a8d;margin:0 0 2px}.sub{color:#666;font-size:13px;margin:0 0 16px}'
    + '.dn-q{border:1px solid #e0e0e0;border-radius:12px;padding:16px 18px;margin-bottom:14px;page-break-inside:avoid}'
    + '.dn-q-title{font-size:16px;font-weight:700;color:#26305a;margin-bottom:10px}'
    + '.dn-q-grid{display:block}.dn-chart-wrap{margin-bottom:8px}'
    + '.dn-analise label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#0a8a8d;display:block;margin:8px 0 6px}'
    + '.dn-badge{font-size:11.5px;color:#666;font-weight:600}'
    + '.dn-open-list{border:1px solid #e0e0e0;border-radius:8px;padding:6px}'
    + '.dn-open-item{display:flex;gap:10px;align-items:flex-start;font-size:13px;padding:7px 8px;border-radius:6px;line-height:1.45}'
    + '.dn-open-item:nth-child(even){background:#f5f5f5}'
    + '.dn-open-num{flex:0 0 auto;min-width:19px;height:19px;border-radius:50%;background:#0a8a8d;color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center}'
    + '.dn-open-txt{flex:1;overflow-wrap:anywhere}'
    + '@page{margin:12mm}'
    + '</style></head><body>'
    + '<h1>' + dnEsc(titulo) + '</h1><div class="sub">' + dnEsc(empresa) + '</div>'
    + clone.innerHTML
    + '</body></html>'
  );
  w.document.close();
  setTimeout(function(){ try { w.focus(); w.print(); } catch(e){} }, 500);
};

// Antes de imprimir: joga o texto de cada campo de análise para um <div> que
// expande e imprime por inteiro (o <textarea> corta o texto na impressão).
window.addEventListener('beforeprint', function(){
  document.querySelectorAll('.dn-analise').forEach(function(box){
    var ta = box.querySelector('textarea[data-q]');
    var pr = box.querySelector('.dn-analise-print');
    if (ta && pr) pr.textContent = ta.value;
  });
});

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', dnInit);
else dnInit();
