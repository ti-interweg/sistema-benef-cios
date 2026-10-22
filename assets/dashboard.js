// ═══════════════════════════════════════════════════════════════
// DASHBOARD.JS — Visão consolidada por analista
// Suporta 3 módulos: demandas, renovação, faturamento
// ═══════════════════════════════════════════════════════════════

window.IS_ADMIN  = (typeof IS_ADMIN  !== 'undefined') ? IS_ADMIN  : true;
window.CAN_VIEW_ALL = (typeof CAN_VIEW_ALL !== 'undefined') ? CAN_VIEW_ALL : window.IS_ADMIN;
window.USER_NOME = (typeof USER_NOME !== 'undefined') ? USER_NOME : '';

// Fallbacks defensivos
const _DASH_COLORS = (typeof window.COLORS !== 'undefined' && Array.isArray(window.COLORS) && window.COLORS.length)
  ? window.COLORS
  : ['#00c9a7','#58a6ff','#bc8cff','#ffa657','#3fb950','#f85149','#d29922','#79c0ff','#ff7b72','#a5d6ff'];

if (typeof window.esc !== 'function')    window.esc    = s => { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; };
if (typeof window.escAttr !== 'function') window.escAttr = s => window.esc(s).replace(/"/g,'&quot;');
if (typeof window.toast !== 'function')  window.toast  = m => alert(m);
if (typeof window.fmtBRL !== 'function') window.fmtBRL = v => { const n=parseFloat(v); return isNaN(n)?'R$ 0,00':'R$ '+n.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); };
if (typeof window.fmtDate !== 'function') window.fmtDate = s => { if(!s) return '—'; const m=String(s).match(/^(\d{4})-(\d{2})-(\d{2})/); return m?`${m[3]}/${m[2]}/${m[1]}`:String(s); };
if (typeof window.openModal !== 'function')  window.openModal  = id => { const el=document.getElementById(id); if(el) el.classList.add('show'); };
if (typeof window.closeModal !== 'function') window.closeModal = id => { const el=document.getElementById(id); if(el) el.classList.remove('show'); };
if (typeof window.apiGet !== 'function')  window.apiGet  = async (a,p={}) => (await fetch('api.php?'+new URLSearchParams({action:a,...p}))).json();

// Helpers de status pro módulo Controle de Cotação ─────────────────
function _ccNorm(s){ return String(s||'').trim().toUpperCase(); }

// Status que contam como "Finalizado" (concluído)
const _CC_STATUS_FINALIZADO = [
  'IMPLANTAÇÃO FINALIZADA','IMPLANTACAO FINALIZADA',
  'DECLINADA',
  'NOMEAÇÃO CONCLUÍDA','NOMEACAO CONCLUIDA'
];
// Status que contam como "A iniciar"
const _CC_STATUS_A_INICIAR = [
  'INICIAR COTAÇÃO','INICIAR COTACAO',
  'INICIAR IMPLANTAÇÃO','INICIAR IMPLANTACAO',
  'INICIAR NOMEAÇÃO','INICIAR NOMEACAO'
];
// Status que contam como "Em andamento" (todos os demais, exceto finalizado)
const _CC_STATUS_EM_ANDAMENTO = [
  'AGUARDANDO DADOS',
  'AGUARDANDO LIBERAÇÃO DE COTAÇÃO','AGUARDANDO LIBERACAO DE COTACAO',
  'EM COTAÇÃO','EM COTACAO',
  'ESTUDO APRESENTADO',
  'AGUARDANDO DOCUMENTAÇÃO','AGUARDANDO DOCUMENTACAO',
  'EM IMPLANTAÇÃO','EM IMPLANTACAO',
  'EM NEGOCIAÇÃO COM A OPERADORA','EM NEGOCIACAO COM A OPERADORA',
  'PASSAGEM DE BASTÃO','PASSAGEM DE BASTAO',
  'EM NOMEAÇÃO','EM NOMEACAO'
];

function _ccIsFinalizado(d){ return _CC_STATUS_FINALIZADO.includes(_ccNorm(d.status)); }
function _ccIsAIniciar(d){   return _CC_STATUS_A_INICIAR.includes(_ccNorm(d.status)); }
function _ccIsAtrasado(d){
  if (_ccIsFinalizado(d)) return false;
  if (!d.data_proxima_acao) return false;
  const m = String(d.data_proxima_acao).match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!m) return false;
  // Hoje 00:00
  const hoje = new Date(); hoje.setHours(0,0,0,0);
  const dx   = new Date(parseInt(m[1],10), parseInt(m[2],10)-1, parseInt(m[3],10));
  return dx.getTime() < hoje.getTime();
}
function _ccIsEmAndamento(d){
  if (_ccIsFinalizado(d) || _ccIsAIniciar(d)) return false;
  // Considera atrasado como subconjunto de "em andamento" para algumas vistas? Aqui mantemos exclusivo.
  if (_ccIsAtrasado(d)) return false;
  return _CC_STATUS_EM_ANDAMENTO.includes(_ccNorm(d.status));
}

// Normaliza situação/status (sem acento, minúsculo) para comparar com segurança.
const _dmNorm = s => String(s == null ? '' : s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();

// Configuração por módulo: cada um declara as categorias que aparecem no card.
// Cada categoria tem {key, label, color, fn}. O dashboard renderiza dinâmico.
const DASH_MODULOS = {
  demandas: {
    label: 'Demandas',
    action: 'listar',
    nomeField: 'analista_nome',
    categorias: [
      { key:'and',  label:'Em andamento', color:'info',    fn: d => _dmNorm(d.situacao) === 'em analise' },
      { key:'conc', label:'Concluído',    color:'success', fn: d => _dmNorm(d.situacao) === 'concluido' },
      { key:'pend', label:'Pendente',     color:'warn',    fn: d => { const n=_dmNorm(d.situacao); return n !== 'concluido' && n !== 'em analise'; } }
    ],
    statusLabel: d => d.situacao || '—'
  },
  renovacao: {
    label: 'Renovações',
    action: 'listar_renovacao',
    nomeField: 'analista_nome',
    categorias: [
      { key:'and',  label:'Em andamento', color:'info',    fn: d => ['Em tratativa','Proposta enviada'].includes(d.status_renovacao) },
      { key:'conc', label:'Concluído',    color:'success', fn: d => ['Finalizada','Finalizado','Aceita','Migrada'].includes(d.status_renovacao) },
      { key:'pend', label:'Pendente',     color:'warn',    fn: d => ['A iniciar','Aguardando cliente','Aguardando seguradora','Recusada','Cancelada','Cancelado'].includes(d.status_renovacao) }
    ],
    statusLabel: d => d.status_renovacao || '—'
  },
  faturamento_atual: {
    label: 'Faturamento Atual',
    action: 'listar_faturamento_backup',
    nomeField: 'analista_nome',
    categorias: [
      { key:'and',  label:'Em andamento', color:'info',    fn: d => ['Aguardando envio','Em análise'].includes(d.status_envio) },
      { key:'conc', label:'Concluído',    color:'success', fn: d => d.status_envio === 'Enviado' },
      { key:'pend', label:'Pendente',     color:'warn',    fn: d => ['Pendente emissão','Pendente'].includes(d.status_envio) }
    ],
    statusLabel: d => d.status_envio || '—'
  },
  demandas_assistenciais: {
    label: 'Demandas Assistenciais',
    endpoint: 'api_enfermagem.php',
    action: 'listar_assistenciais',
    nomeField: 'analista_nome',
    categorias: [
      { key:'and',  label:'Em andamento', color:'info',    fn: d => d.situacao === 'Em andamento' },
      { key:'conc', label:'Concluído',    color:'success', fn: d => d.situacao === 'Concluído' },
      { key:'pend', label:'A iniciar',    color:'warn',    fn: d => d.situacao !== 'Em andamento' && d.situacao !== 'Concluído' }
    ],
    statusLabel: d => d.situacao || '—'
  },
  acoes_saude: {
    label: 'Ações de Saúde',
    endpoint: 'api_enfermagem.php',
    action: 'listar_acoes_saude',
    nomeField: 'analista_nome',
    categorias: [
      { key:'and',  label:'Agendado',        color:'info',    fn: d => d.status === 'Agendado' },
      { key:'conc', label:'Realizado',       color:'success', fn: d => d.status === 'Realizado' },
      { key:'pend', label:'Em planejamento', color:'warn',    fn: d => d.status !== 'Agendado' && d.status !== 'Realizado' && d.status !== 'Cancelado' },
      { key:'canc', label:'Cancelado',       color:'danger',  fn: d => d.status === 'Cancelado' }
    ],
    statusLabel: d => d.status || '—'
  },
  cotacao: {
    label: 'Controle de Cotação',
    action: 'listar_cotacoes',
    nomeField: 'analista',
    categorias: [
      { key:'ainic', label:'A iniciar',    color:'muted',   fn: _ccIsAIniciar },
      { key:'and',   label:'Em andamento', color:'info',    fn: _ccIsEmAndamento },
      { key:'fin',   label:'Finalizado',   color:'success', fn: _ccIsFinalizado },
      { key:'atr',   label:'Atrasado',     color:'danger',  fn: _ccIsAtrasado }
    ],
    statusLabel: d => d.status || '—'
  }
};

// Mapeia "color" lógico -> {bg, border, text} usando CSS vars
const _DASH_CAT_COLOR = {
  info:    { bg:'rgba(88,166,255,.08)',  border:'rgba(88,166,255,.15)',  text:'var(--info)'    },
  success: { bg:'rgba(63,185,80,.08)',   border:'rgba(63,185,80,.15)',   text:'var(--success)' },
  warn:    { bg:'rgba(210,153,34,.08)',  border:'rgba(210,153,34,.15)',  text:'var(--warn)'    },
  danger:  { bg:'rgba(248,81,73,.10)',   border:'rgba(248,81,73,.20)',   text:'var(--danger)'  },
  muted:   { bg:'rgba(125,133,144,.08)', border:'rgba(125,133,144,.18)', text:'var(--muted)'   }
};

let _dashAnalistas = [];
let _dashData = [];
let _dashModulo = 'demandas';

document.addEventListener('DOMContentLoaded', async () => {
  try {
    const r = await apiGet('analistas');
    _dashAnalistas = r.data || [];
  } catch(e){ console.warn('analistas:', e); }
  await dashLoad();
});

async function dashLoad(){
  _dashModulo = document.getElementById('dash-modulo').value;
  const cfg = DASH_MODULOS[_dashModulo];
  if (!cfg) return;

  document.getElementById('dash-grid').innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:30px;color:var(--muted)">Carregando ${cfg.label.toLowerCase()}...</div>`;

  try {
    const r = cfg.endpoint
      ? await (await fetch(cfg.endpoint + '?action=' + encodeURIComponent(cfg.action))).json()
      : await apiGet(cfg.action);
    _dashData = r.data || [];
  } catch(e){
    _dashData = [];
    toast('Erro ao carregar ' + cfg.label.toLowerCase());
  }

  dashRender();
}

function dashRender(){
  const cfg = DASH_MODULOS[_dashModulo];
  const nomeField = cfg.nomeField || 'analista_nome';

  // Agrupa por analista — case-insensitive E sem acentuação
  // ("Nayara" = "NAYARA", "Angela" = "Ângela")
  const normaliza = s => String(s||'')
    .toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '')  // remove acentos
    .trim();
  const ehLixo = s => {
    const n = String(s||'').trim().toLowerCase();
    return !n || n === 'null' || n === 'undefined' || n.startsWith('selecione');
  };

  const map = {};        // chave canônica → array de itens
  const labelMap = {};   // chave canônica → label "bonito" pra exibição
  _dashData.forEach(d => {
    const nome = (d[nomeField] || '').trim();
    if (ehLixo(nome)) return;  // pula "Selecione...", null, etc.
    const key = normaliza(nome);
    if (!map[key]) {
      map[key] = [];
      // Usa a versão "bonita" — preferindo a que tem acentos e capitalização correta
      const looksTitle = /^[A-ZÀ-Ý][a-zà-ÿ]/.test(nome);
      labelMap[key] = looksTitle ? nome : (nome.charAt(0).toUpperCase() + nome.slice(1).toLowerCase());
    } else {
      // Atualiza pro label "melhor" (com acentos > sem acentos)
      const atual = labelMap[key] || '';
      const temAcento = /[À-ÿ]/.test(nome);
      const atualTemAcento = /[À-ÿ]/.test(atual);
      if (temAcento && !atualTemAcento) labelMap[key] = nome;
      else if (/^[A-ZÀ-Ý][a-zà-ÿ]/.test(nome) && !/^[A-ZÀ-Ý][a-zà-ÿ]/.test(atual)) labelMap[key] = nome;
    }
    map[key].push(d);
  });

  // Quem não pode ver tudo, vê só o próprio (admin e visualizador veem tudo)
  // Converte pro formato [labelBonito, items] usando o labelMap
  let entries = Object.entries(map).map(([key, items]) => [labelMap[key] || key, items]);
  if (!window.CAN_VIEW_ALL) {
    const userKey = String(window.USER_NOME || '').toLowerCase();
    entries = entries.filter(([nome]) => nome.toLowerCase() === userKey);
  }
  // Ordena por qtd descendente
  entries.sort((a, b) => b[1].length - a[1].length);

  // Stats gerais — mostra "concluído" (1ª cat verde) e "pendente"/"atrasado" (último vermelho/laranja)
  // Estratégia genérica: somatório total para cada categoria.
  const totaisPorCat = cfg.categorias.map(c => ({ key:c.key, label:c.label, color:c.color, total:0 }));
  _dashData.forEach(d => {
    cfg.categorias.forEach((c, i) => { if (c.fn(d)) totaisPorCat[i].total++; });
  });
  // Cards-resumo: tenta achar "Concluído"/"Finalizado" pra o card verde e "Pendente"/"Atrasado" pra o card vermelho
  const catFin = totaisPorCat.find(c => c.color === 'success');
  const catPen = totaisPorCat.find(c => c.color === 'warn' || c.color === 'danger');
  setText('dash-tot-anal',  entries.length);
  setText('dash-tot-itens', _dashData.length);
  setText('dash-tot-conc',  catFin ? catFin.total : 0);
  setText('dash-tot-pend',  catPen ? catPen.total : 0);
  // Atualiza labels dos cards-resumo conforme o módulo
  const lblConc = document.querySelector('.card.c-green .card-label');
  const lblPend = document.querySelector('.card.c-red .card-label');
  if (lblConc && catFin) lblConc.textContent = catFin.label;
  if (lblPend && catPen) lblPend.textContent = catPen.label;

  // Cards por analista
  // Layout em LINHAS (lista/tabela limpa) — uma linha por analista
  const gridCols = `minmax(170px,2fr) ${cfg.categorias.map(() => '1fr').join(' ')} 72px`;
  const header = `<div class="dash-row dash-row-head" style="grid-template-columns:${gridCols}">
    <div class="dash-c-name">Analista</div>
    ${cfg.categorias.map(c => `<div class="dash-c-num">${esc(c.label)}</div>`).join('')}
    <div class="dash-c-num">Total</div>
  </div>`;

  const maxTot = Math.max(1, ...entries.map(e => e[1].length));
  const linhas = entries.map(([nome, items], i) => {
    const color = _DASH_COLORS[i % _DASH_COLORS.length];
    const init  = nome.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
    const aInfo = _dashAnalistas.find(a => a.nome === nome);
    const avatar = aInfo && aInfo.foto
      ? `<img src="${escAttr(aInfo.foto)}" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid ${color};flex-shrink:0">`
      : `<div style="width:34px;height:34px;border-radius:50%;background:${color};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">${init}</div>`;

    const cats = cfg.categorias.map(c => {
      const col = _DASH_CAT_COLOR[c.color] || _DASH_CAT_COLOR.info;
      return { label: c.label, n: items.filter(c.fn).length, cor: col.text };
    });
    const barW = (items.length / maxTot * 100).toFixed(2);

    return `<div class="dash-row clickable" style="grid-template-columns:${gridCols};border-left:3px solid ${color}" onclick="dashShowDetail('${escAttr(nome)}')">
      <div class="dash-c-name">
        ${avatar}
        <div style="min-width:0">
          <div style="font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(nome)}</div>
          <div style="height:5px;border-radius:999px;overflow:hidden;margin-top:5px;max-width:170px;background:rgba(130,130,130,.14)"><div style="width:${barW}%;height:100%;border-radius:999px;background:linear-gradient(90deg,var(--accent),#43dccb)"></div></div>
        </div>
      </div>
      ${cats.map(c => `<div class="dash-c-num"><b style="color:${c.cor}">${c.n}</b></div>`).join('')}
      <div class="dash-c-num"><b style="color:var(--text)">${items.length}</b></div>
    </div>`;
  }).join('');

  document.getElementById('dash-grid').innerHTML = entries.length
    ? `<div class="dash-list">${header}${linhas}</div>`
    : '<p style="color:var(--muted);text-align:center;padding:30px">Sem dados pra mostrar.</p>';
}

function setText(id, v){ const el = document.getElementById(id); if (el) el.textContent = v; }

// Mostra modal com lista de itens daquele analista
function dashShowDetail(nome){
  const cfg = DASH_MODULOS[_dashModulo];
  const nomeField = cfg.nomeField || 'analista_nome';
  // Comparação tolerante a maiúsc/minúsc + acentos
  const norm = s => String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').trim();
  const alvo = norm(nome);
  const items = _dashData.filter(d => norm(d[nomeField]) === alvo);
  if (!items.length) { toast('Sem itens para ' + nome); return; }

  document.getElementById('dd-title').textContent = `${nome} — ${cfg.label}`;
  document.getElementById('dd-sub').textContent = `${items.length} ${cfg.label.toLowerCase()} na carteira`;

  const rows = items.map(d => {
    const grupoEmpresa = _dashModulo === 'cotacao'
      ? (d.razao_social || '—')
      : `${d.grupo || '—'}${d.empresa ? ' / ' + d.empresa : ''}`;
    const status = cfg.statusLabel(d);
    let dataExtra;
    if (_dashModulo === 'renovacao')                  dataExtra = `Vig: ${fmtDate(d.inicio_vigencia)}`;
    else if (_dashModulo === 'faturamento_atual')     dataExtra = `Comp: ${d.competencia || '—'}`;
    else if (_dashModulo === 'cotacao')               dataExtra = `Próx. ação: ${fmtDate(d.data_proxima_acao)}`;
    else if (_dashModulo === 'demandas_assistenciais') dataExtra = `Próx. ação: ${fmtDate(d.proxima_acao)}`;
    else if (_dashModulo === 'acoes_saude')           dataExtra = `Data: ${fmtDate(d.data)}`;
    else                                              dataExtra = `Receb: ${fmtDate(d.recebimento)}`;
    const subInfo = _dashModulo === 'cotacao'
      ? (d.operadora_atual || d.player_contratado || '')
      : (d.seguradora || '');
    return `<tr style="border-bottom:1px solid var(--border)">
      <td style="padding:8px;font-size:11.5px;color:var(--muted)">#${String(d.id).padStart(4,'0')}</td>
      <td style="padding:8px;font-size:13px"><strong>${esc(grupoEmpresa)}</strong><br><span style="font-size:11px;color:var(--muted)">${esc(subInfo)}</span></td>
      <td style="padding:8px;font-size:12px"><span class="badge b-analise">${esc(status)}</span></td>
      <td style="padding:8px;font-size:11.5px;color:var(--muted)">${dataExtra}</td>
    </tr>`;
  }).join('');

  document.getElementById('dd-body').innerHTML = `
    <div style="max-height:55vh;overflow-y:auto;border:1px solid var(--border);border-radius:8px">
      <table style="width:100%;border-collapse:collapse">
        <thead style="position:sticky;top:0;background:var(--bg-card)">
          <tr style="border-bottom:1px solid var(--border)">
            <th style="padding:8px;text-align:left;font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase">#</th>
            <th style="padding:8px;text-align:left;font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase">Grupo / Empresa</th>
            <th style="padding:8px;text-align:left;font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase">Status</th>
            <th style="padding:8px;text-align:left;font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase">Detalhe</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>
    <div style="margin-top:12px;text-align:center">
      <a href="${_dashModulo === 'cotacao' ? 'controle_cotacao' : _dashModulo}.php" class="btn btn-outline btn-sm" style="text-decoration:none">
        <span class="material-symbols-rounded" style="font-size:16px">open_in_new</span> Abrir página completa de ${cfg.label}
      </a>
    </div>`;

  openModal('mod-dash-detail');
}