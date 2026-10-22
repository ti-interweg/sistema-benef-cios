/* ============================================================
   Sessão — timeout por inatividade (front).
   - Desloga automaticamente após 30 min sem atividade.
   - Avisa 1 min antes, com opção de continuar conectada.
   - Enquanto a pessoa usa a tela, envia "keepalive" pra não
     expirar a sessão no servidor durante o uso ativo.
   ============================================================ */
(function(){
  if (window.__sessaoInit) return;
  window.__sessaoInit = true;

  const IDLE_LIMIT  = 12 * 60 * 60 * 1000; // 12 horas
  const WARN_BEFORE = 60 * 1000;      // avisa 60s antes
  const PING_MIN    = 5 * 60 * 1000;  // keepalive no máx. a cada 5 min

  let lastActivity = Date.now();
  let lastPing     = 0;
  let warnEl       = null;
  let warnTimer    = null;

  function marcarAtividade(){
    lastActivity = Date.now();
    if (warnEl) esconderAviso();
    // Renova a sessão no servidor, no máximo 1x a cada 5 min
    if (Date.now() - lastPing > PING_MIN){
      lastPing = Date.now();
      fetch('keepalive.php', { cache:'no-store' }).catch(()=>{});
    }
  }

  ['mousemove','mousedown','keydown','scroll','touchstart','click'].forEach(ev => {
    document.addEventListener(ev, marcarAtividade, { passive:true });
  });

  async function sairPorInatividade(){
    try { await fetch('logout.php', { cache:'no-store' }); } catch(_){}
    window.location.href = 'login.php?timeout=1';
  }

  function css(){
    if (document.getElementById('sessao-css')) return;
    const st = document.createElement('style');
    st.id = 'sessao-css';
    st.textContent = `
      #sessao-ov{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:11000;display:flex;align-items:center;justify-content:center}
      #sessao-box{background:var(--bg-card,#fff);color:var(--text);border:1px solid var(--border);border-radius:16px;
        padding:22px 24px;max-width:380px;width:92vw;box-shadow:0 20px 60px rgba(0,0,0,.3);text-align:center}
      #sessao-box h3{margin:0 0 8px;font-size:17px}
      #sessao-box p{margin:0 0 16px;font-size:13.5px;color:var(--muted);line-height:1.5}
      #sessao-box .sess-count{font-weight:700;color:var(--text)}
      #sessao-box .sess-btns{display:flex;gap:10px;justify-content:center}
      #sessao-box button{font-family:inherit;font-size:13px;font-weight:600;border-radius:10px;padding:9px 16px;cursor:pointer;border:1px solid var(--border)}
      #sessao-box .sess-stay{background:var(--accent,#00bfb3);color:#fff;border-color:transparent}
      #sessao-box .sess-out{background:transparent;color:var(--text)}
    `;
    document.head.appendChild(st);
  }

  function mostrarAviso(){
    if (warnEl) return;
    css();
    warnEl = document.createElement('div');
    warnEl.id = 'sessao-ov';
    warnEl.innerHTML = `
      <div id="sessao-box">
        <h3>Sua sessão vai expirar</h3>
        <p>Por segurança, você será desconectada por inatividade em <span class="sess-count" id="sessao-count">60</span>s.</p>
        <div class="sess-btns">
          <button class="sess-out"  id="sessao-out">Sair agora</button>
          <button class="sess-stay" id="sessao-stay">Continuar conectada</button>
        </div>
      </div>`;
    document.body.appendChild(warnEl);
    document.getElementById('sessao-stay').addEventListener('click', () => {
      lastPing = 0;            // força renovar já
      marcarAtividade();       // renova sessão + esconde aviso
    });
    document.getElementById('sessao-out').addEventListener('click', sairPorInatividade);

    // Contagem regressiva no texto
    warnTimer = setInterval(() => {
      const restante = Math.max(0, Math.ceil((IDLE_LIMIT - (Date.now() - lastActivity)) / 1000));
      const c = document.getElementById('sessao-count');
      if (c) c.textContent = restante;
      if (restante <= 0) sairPorInatividade();
    }, 1000);
  }

  function esconderAviso(){
    if (warnTimer){ clearInterval(warnTimer); warnTimer = null; }
    if (warnEl){ warnEl.remove(); warnEl = null; }
  }

  // Verifica o estado a cada 15s
  setInterval(() => {
    const idle = Date.now() - lastActivity;
    if (idle >= IDLE_LIMIT) { sairPorInatividade(); return; }
    if (idle >= IDLE_LIMIT - WARN_BEFORE) mostrarAviso();
  }, 15000);
})();
