/* Diálogos modernos (alert / confirm / prompt) — global em todas as páginas.
   Substituem os popups nativos do navegador por modais estilizados.
   Uso:
     await uiAlert('mensagem'[, 'Título']);
     const ok = await uiConfirm('mensagem'[, 'Título']);
     const txt = await uiPrompt('mensagem'[, {title, value, placeholder}]);  // null se cancelar
   window.alert também passa a usar o estilo novo. */
(function(){
  if (window.__uiDialogReady) return;
  window.__uiDialogReady = true;

  var css = ''
    + '.uidlg-ov{position:fixed;inset:0;background:rgba(16,24,40,.45);display:flex;align-items:center;justify-content:center;z-index:99999;padding:20px;animation:uidlgFade .12s ease}'
    + '@keyframes uidlgFade{from{opacity:0}to{opacity:1}}'
    + '.uidlg{background:var(--bg-card,#fff);border:1px solid var(--border,#e5e7eb);border-radius:16px;box-shadow:0 20px 60px -20px rgba(16,24,40,.5);width:440px;max-width:94vw;padding:22px 24px;animation:uidlgUp .15s cubic-bezier(.22,1,.36,1)}'
    + '@keyframes uidlgUp{from{transform:translateY(8px);opacity:.6}to{transform:translateY(0);opacity:1}}'
    + '.uidlg-h{display:flex;align-items:center;gap:9px;font-size:16px;font-weight:800;color:var(--text,#111);margin-bottom:8px}'
    + '.uidlg-h .material-symbols-rounded{font-size:22px;color:var(--accent,#00bfb3)}'
    + '.uidlg-msg{font-size:13.5px;color:var(--text,#333);line-height:1.55;white-space:pre-line;margin-bottom:14px}'
    + '.uidlg-inp{width:100%;box-sizing:border-box;background:var(--bg,#f7f7f7);border:1px solid var(--border,#e5e7eb);border-radius:10px;padding:11px 13px;font-size:14px;color:var(--text,#111);outline:none;margin-bottom:16px;transition:border-color .15s}'
    + '.uidlg-inp:focus{border-color:var(--accent,#00bfb3)}'
    + '.uidlg-ft{display:flex;justify-content:flex-end;align-items:center;gap:8px}'
    + '.uidlg-btn{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;border:1px solid transparent;cursor:pointer;font-size:14px;font-weight:700;padding:0 18px;border-radius:10px;width:140px;min-width:140px;height:42px;line-height:1;text-align:center;text-transform:none;transition:filter .15s,background .15s}'
    + '.uidlg-btn.sec{background:var(--bg,#eef1f4);color:var(--text,#333);border:1px solid var(--border,#e5e7eb)}'
    + '.uidlg-btn.sec:hover{filter:brightness(.97)}'
    + '.uidlg-btn.pri{background:var(--accent,#00bfb3);color:#fff}'
    + '.uidlg-btn.pri:hover{filter:brightness(1.05)}'
    + '.uidlg-btn.danger{background:#c0392b;color:#fff}';
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  function build(opts){
    return new Promise(function(resolve){
      var ov = document.createElement('div'); ov.className = 'uidlg-ov';
      var icon = opts.icon || (opts.type==='confirm' ? 'help' : (opts.type==='prompt' ? 'edit' : 'info'));
      var isPrompt = opts.type === 'prompt';
      var isConfirm = opts.type === 'confirm';
      var inputHtml = isPrompt
        ? '<input class="uidlg-inp" type="text" id="uidlg-input" placeholder="'+(opts.placeholder||'').replace(/"/g,'&quot;')+'">'
        : '';
      var okLabel = opts.okLabel || (isPrompt ? 'Confirmar' : (isConfirm ? 'Confirmar' : 'OK'));
      var cancelBtn = (isPrompt || isConfirm) ? '<button class="uidlg-btn sec" data-act="cancel">Cancelar</button>' : '';
      var okClass = opts.danger ? 'danger' : 'pri';
      ov.innerHTML =
        '<div class="uidlg" role="dialog" aria-modal="true">'
        + (opts.title ? '<div class="uidlg-h"><span class="material-symbols-rounded">'+icon+'</span>'+esc(opts.title)+'</div>' : '')
        + '<div class="uidlg-msg">'+esc(opts.message||'')+'</div>'
        + inputHtml
        + '<div class="uidlg-ft">'+cancelBtn+'<button class="uidlg-btn '+okClass+'" data-act="ok">'+esc(okLabel)+'</button></div>'
        + '</div>';
      document.body.appendChild(ov);
      var input = ov.querySelector('#uidlg-input');
      if (input){ input.value = opts.value || ''; setTimeout(function(){ input.focus(); input.select(); }, 30); }

      function close(val){ ov.remove(); document.removeEventListener('keydown', onKey, true); resolve(val); }
      function onOk(){ close(isPrompt ? (input ? input.value : '') : true); }
      function onCancel(){ close(isPrompt ? null : false); }
      function onKey(e){
        if (e.key === 'Escape'){ e.preventDefault(); onCancel(); }
        else if (e.key === 'Enter' && (isPrompt || opts.type==='alert' || isConfirm)){ e.preventDefault(); onOk(); }
      }
      ov.addEventListener('click', function(e){ if (e.target === ov) onCancel(); });
      ov.querySelector('[data-act="ok"]').addEventListener('click', onOk);
      var cb = ov.querySelector('[data-act="cancel"]'); if (cb) cb.addEventListener('click', onCancel);
      document.addEventListener('keydown', onKey, true);
    });
  }
  function esc(s){ var d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; }

  window.uiAlert   = function(message, title){ return build({type:'alert',   message:message, title:title||'Aviso',      icon:'info'}); };
  window.uiConfirm = function(message, title){ return build({type:'confirm', message:message, title:title||'Confirmar',  icon:'help'}); };
  window.uiPrompt  = function(message, opts){ opts=opts||{}; return build({type:'prompt', message:message, title:opts.title||'', value:opts.value, placeholder:opts.placeholder, okLabel:opts.okLabel}); };

  // alert() nativo passa a usar o estilo novo (não bloqueia, mas fica bonito)
  if (!window.__nativeAlert){ window.__nativeAlert = window.alert; window.alert = function(m){ try { window.uiAlert(String(m==null?'':m)); } catch(_){ window.__nativeAlert(m); } }; }
})();
