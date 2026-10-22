// ═══════════════════════════════════════════════════════════════
// COMMON.JS — helpers compartilhados por todas as páginas
// (cuidado: esse arquivo carrega ANTES dos scripts de cada página)
// ═══════════════════════════════════════════════════════════════

// ── Tema ───────────────────────────────────────────────────────
function setTheme(t){
  document.body.className = t;
  try { localStorage.setItem('iw-theme', t); } catch(e){}
  const bl = document.getElementById('btn-light');
  const bd = document.getElementById('btn-dark');
  if (bl) bl.style.borderColor = t === 'light' ? 'var(--accent)' : '';
  if (bd) bd.style.borderColor = t === 'dark'  ? 'var(--accent)' : '';
}

// ── Toast ──────────────────────────────────────────────────────
function toast(msg, ms=3000){
  const t = document.getElementById('toast');
  if(!t){ alert(msg); return; }
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(window._toastTimer);
  window._toastTimer = setTimeout(()=>t.classList.remove('show'), ms);
}

// ── Escape HTML ────────────────────────────────────────────────
function esc(s){
  const d = document.createElement('div');
  d.textContent = (s == null ? '' : String(s));
  return d.innerHTML;
}
function escAttr(s){ return esc(s).replace(/"/g,'&quot;'); }

// ── Formatadores BR ────────────────────────────────────────────
function fmtBRL(v){
  const n = parseFloat(v);
  if (isNaN(n)) return 'R$ 0,00';
  return 'R$ ' + n.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
}
function numToBR(v){
  const n = parseFloat(v);
  if (isNaN(n)) return '0';
  return n.toLocaleString('pt-BR', {minimumFractionDigits:0, maximumFractionDigits:2});
}
function brToNum(v){
  if (v == null) return 0;
  const s = String(v).trim().replace(/[R$\s.]/g,'').replace(',','.');
  const n = parseFloat(s);
  return isNaN(n) ? 0 : n;
}
function fmtPerc(v){
  const n = parseFloat(v);
  if (isNaN(n)) return '—';
  return n.toLocaleString('pt-BR', {minimumFractionDigits:0, maximumFractionDigits:2}) + '%';
}
function fmtComp(c){
  if (!c) return '—';
  return String(c);
}
function fmtDate(s){
  if (!s) return '—';
  const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (m) return `${m[3]}/${m[2]}/${m[1]}`;
  return String(s);
}
function fmtDateTime(s){
  if (!s) return '—';
  const dt = new Date(String(s).replace(' ', 'T'));
  if (isNaN(dt.getTime())) return String(s);
  return dt.toLocaleDateString('pt-BR') + ' ' + dt.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
}

// ── Modal genérico (overlay + .ov.show) ────────────────────────
function openModal(id){
  const el = document.getElementById(id);
  if (el) el.classList.add('show');
}
function closeModal(id){
  const el = document.getElementById(id);
  if (el) el.classList.remove('show');
}

// ── API helper ─────────────────────────────────────────────────
async function apiGet(action, params = {}){
  const p = new URLSearchParams({action, ...params});
  const r = await fetch('api.php?' + p);
  return r.json();
}
async function apiPost(action, body = {}){
  const r = await fetch('api.php?action=' + encodeURIComponent(action), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return r.json();
}

// ── Cores p/ avatares ─────────────────────────────────────────
const COLORS = [
  '#00c9a7','#58a6ff','#bc8cff','#ffa657','#3fb950',
  '#f85149','#d29922','#79c0ff','#ff7b72','#a5d6ff'
];

// ── Helper: mostrar/esconder loading global (se precisar) ─────
function setLoading(on){
  let el = document.getElementById('global-loading');
  if (!el && on){
    el = document.createElement('div');
    el.id = 'global-loading';
    el.style.cssText = 'position:fixed;top:0;left:220px;right:0;height:3px;background:var(--accent);z-index:9999;animation:lpulse 1s infinite';
    document.body.appendChild(el);
    if (!document.getElementById('lpulse-style')){
      const s = document.createElement('style');
      s.id = 'lpulse-style';
      s.textContent = '@keyframes lpulse{0%,100%{opacity:.3}50%{opacity:1}}';
      document.head.appendChild(s);
    }
  } else if (el && !on){
    el.remove();
  }
}

// ── Init de tema (apenas botões) ──────────────────────────────
document.addEventListener('DOMContentLoaded', ()=>{
  const t = localStorage.getItem('iw-theme') || 'dark';
  setTheme(t);
});
