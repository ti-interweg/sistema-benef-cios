/* ============================================================
   Notificações internas (sino) — global em todas as páginas.
   Mostra um sininho fixo no canto superior direito com um painel
   estilo caixa de e-mail. Puxa de api_notificacoes.php.
   ============================================================ */
(function(){
  if (window.__notifInit) return; // evita duplicar
  window.__notifInit = true;

  const esc = s => String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  function fmtQuando(s){
    if (!s) return '';
    const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    if (!m) return esc(s);
    return `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}`;
  }

  let _open = false;
  let _dados = [];

  function css(){
    if (document.getElementById('notif-css')) return;
    const st = document.createElement('style');
    st.id = 'notif-css';
    st.textContent = `
      #notif-wrap{position:fixed;top:14px;right:18px;z-index:9000}
      #notif-btn{position:relative;width:42px;height:42px;border-radius:50%;border:1px solid var(--border);
        background:var(--bg-card,#fff);color:var(--text);cursor:pointer;display:flex;align-items:center;justify-content:center;
        box-shadow:0 4px 14px rgba(0,0,0,.10);transition:transform .12s}
      #notif-btn:hover{transform:scale(1.05)}
      #notif-badge{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;
        background:#e5484d;color:#fff;font-size:11px;font-weight:700;display:none;align-items:center;justify-content:center;line-height:1}
      #notif-panel{position:absolute;top:50px;right:0;width:360px;max-width:92vw;max-height:70vh;overflow:hidden;
        background:var(--bg-card,#fff);border:1px solid var(--border);border-radius:14px;
        box-shadow:0 12px 40px rgba(0,0,0,.22);display:none;flex-direction:column}
      #notif-panel.open{display:flex}
      .notif-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--border)}
      .notif-head b{font-size:14px;color:var(--text)}
      .notif-mark{background:none;border:0;color:var(--accent,#00bfb3);font-size:12px;font-weight:600;cursor:pointer;padding:2px 4px}
      .notif-list{overflow-y:auto;max-height:60vh}
      .notif-item{display:block;padding:11px 14px;border-bottom:1px solid var(--border);text-decoration:none;color:inherit;cursor:pointer;transition:background .12s}
      .notif-item:hover{background:rgba(0,191,179,.07)}
      .notif-item.unread{background:rgba(0,191,179,.06)}
      .notif-item.unread .notif-title::before{content:'';display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--accent,#00bfb3);margin-right:7px;vertical-align:middle}
      .notif-title{font-size:13px;font-weight:600;color:var(--text);margin-bottom:3px}
      .notif-msg{font-size:12px;color:var(--muted);line-height:1.35}
      .notif-when{font-size:11px;color:var(--muted);margin-top:4px}
      .notif-empty{padding:26px 14px;text-align:center;color:var(--muted);font-size:13px}
      .nav-badge{display:none;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;
        margin-left:auto;border-radius:999px;background:#e5484d;color:#fff;font-size:11px;font-weight:700;line-height:1}
    `;
    document.head.appendChild(st);
  }

  function ensureDOM(){
    if (document.getElementById('notif-wrap')) return;
    css();
    const wrap = document.createElement('div');
    wrap.id = 'notif-wrap';
    wrap.innerHTML = `
      <button id="notif-btn" title="Notificações" aria-label="Notificações">
        <span class="material-symbols-rounded" style="font-size:22px">notifications</span>
        <span id="notif-badge">0</span>
      </button>
      <div id="notif-panel">
        <div class="notif-head"><b>Notificações</b><button class="notif-mark" id="notif-mark-all">Marcar todas como lidas</button></div>
        <div class="notif-list" id="notif-list"></div>
      </div>`;
    document.body.appendChild(wrap);

    document.getElementById('notif-btn').addEventListener('click', (e) => { e.stopPropagation(); toggle(); });
    document.getElementById('notif-mark-all').addEventListener('click', marcarTodas);
    document.addEventListener('click', (e) => {
      if (_open && !e.target.closest('#notif-wrap')) close();
    });
  }

  function toggle(){ _open ? close() : abrir(); }
  function close(){ _open = false; const p = document.getElementById('notif-panel'); if (p) p.classList.remove('open'); }
  async function abrir(){ _open = true; const p = document.getElementById('notif-panel'); if (p) p.classList.add('open'); await carregar(true); }

  function render(){
    const list = document.getElementById('notif-list');
    if (!list) return;
    if (!_dados.length){ list.innerHTML = '<div class="notif-empty">Nenhuma notificação.</div>'; return; }
    list.innerHTML = _dados.map(n => {
      const href = n.url ? esc(n.url) : 'javascript:void(0)';
      return `<a class="notif-item${n.lida ? '' : ' unread'}" href="${href}" data-id="${n.id}">
        <div class="notif-title">${esc(n.titulo || 'Notificação')}</div>
        ${n.mensagem ? `<div class="notif-msg">${esc(n.mensagem)}</div>` : ''}
        <div class="notif-when">${fmtQuando(n.criado_em)}</div>
      </a>`;
    }).join('');
    list.querySelectorAll('.notif-item').forEach(a => {
      a.addEventListener('click', () => {
        const id = parseInt(a.getAttribute('data-id') || '0', 10);
        if (id) marcarLida(id); // marca lida ao clicar (a navegação segue no href)
      });
    });
  }

  function setBadge(n){
    const b = document.getElementById('notif-badge');
    if (!b) return;
    if (n > 0){ b.textContent = n > 99 ? '99+' : String(n); b.style.display = 'flex'; }
    else b.style.display = 'none';
  }

  // Badge vermelho no item "Passagem de Bastão" do menu = passagens não lidas
  function updateNavBadge(){
    const n = _dados.filter(x => !x.lida && String(x.tipo || '') === 'passagem').length;
    document.querySelectorAll('a.nav-item[href*="passagem_bastao.php"]').forEach(a => {
      let b = a.querySelector('.nav-badge');
      if (!b){ b = document.createElement('span'); b.className = 'nav-badge'; a.appendChild(b); }
      if (n > 0){ b.textContent = n > 99 ? '99+' : String(n); b.style.display = 'flex'; }
      else b.style.display = 'none';
    });
  }

  async function carregar(force){
    try {
      const r = await fetch('api_notificacoes.php?action=listar', { cache: 'no-store' }).then(x => x.json());
      if (r && r.ok){
        _dados = r.data || [];
        setBadge(r.nao_lidas || 0);
        updateNavBadge();
        if (_open || force) render();
      }
    } catch (_){}
  }

  async function marcarLida(id){
    try { await fetch('api_notificacoes.php?action=marcar_lida', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id }) }); } catch(_){}
    const it = _dados.find(x => x.id === id); if (it) it.lida = true;
    setBadge(_dados.filter(x => !x.lida).length);
    updateNavBadge();
  }

  async function marcarTodas(e){
    if (e) e.stopPropagation();
    try { await fetch('api_notificacoes.php?action=marcar_todas', { method:'POST' }); } catch(_){}
    _dados.forEach(x => x.lida = true);
    setBadge(0); updateNavBadge(); render();
  }

  // Permite que outras telas forcem uma atualização imediata do sino/badge
  window.notifRefresh = function(){ return carregar(false); };

  function init(){
    ensureDOM();
    carregar(false);
    setInterval(() => { if (!document.hidden) carregar(false); }, 30000); // atualiza a cada 30s
    // Atualiza ao voltar o foco pra aba
    document.addEventListener('visibilitychange', () => { if (!document.hidden) carregar(false); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
