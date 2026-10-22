<?php
$currentPage = 'assistente_apolice';
$pageTitle   = 'Assistente de Apólice — Interweg Benefícios';
require_once __DIR__ . '/_header.php';
?>
<style>
  .ap-top{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:flex-start}
  .ap-actions{display:flex;flex-direction:column;gap:10px;align-items:flex-end}
  .ap-sel{padding:9px 12px;border:1px solid var(--border);border-radius:10px;font-size:13px;background:var(--bg-card);color:var(--text);min-width:220px}
  .ap-grid{display:grid;grid-template-columns:320px 1fr;gap:16px;margin-top:16px;align-items:start}
  @media(max-width:860px){.ap-grid{grid-template-columns:1fr}}
  .ap-card{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:16px}
  .ap-drop{border:2px dashed var(--border);border-radius:12px;padding:16px;text-align:center;color:var(--muted);font-size:12.5px;cursor:pointer}
  .ap-drop.drag{border-color:var(--accent);background:rgba(0,191,179,.06)}
  .ap-doc{border:1px solid var(--border);border-radius:11px;padding:11px 12px;margin-top:10px;cursor:pointer}
  .ap-doc.sel{border-color:var(--accent);box-shadow:0 0 0 2px rgba(0,191,179,.18)}
  .ap-doc b{font-size:13px}
  .ap-badge{display:inline-block;padding:1px 8px;border-radius:20px;font-size:10px;font-weight:800;background:#eef2f7;color:#475569;margin-left:6px;text-transform:uppercase}
  .ap-meta{font-size:11px;color:var(--muted);margin-top:3px;line-height:1.5}
  .ap-chat{display:flex;flex-direction:column;min-height:420px}
  .ap-msgs{flex:1;overflow:auto;display:flex;flex-direction:column;gap:12px;padding:4px 2px;max-height:56vh}
  .ap-q{align-self:flex-end;background:var(--accent);color:#063;max-width:85%;padding:9px 13px;border-radius:14px 14px 3px 14px;font-size:13px}
  .ap-a{align-self:flex-start;background:var(--bg-hover,#f3f5f7);max-width:92%;padding:11px 14px;border-radius:14px 14px 14px 3px;font-size:13px;line-height:1.55;white-space:pre-wrap}
  .ap-a .ap-foot{margin-top:7px;font-size:10.5px;color:var(--muted)}
  .ap-ask{display:flex;gap:8px;margin-top:12px}
  .ap-ask textarea{flex:1;resize:vertical;min-height:46px;max-height:160px;padding:11px 13px;border:1px solid var(--border);border-radius:12px;font-size:13px;background:var(--bg-card);color:var(--text);font-family:inherit}
  .ap-empty{color:var(--muted);text-align:center;padding:40px 10px;font-size:13px}
  .ap-note{font-size:11px;color:var(--muted);margin-top:8px}
  .ap-cmp{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:var(--bg-hover,#f3f5f7);border-radius:10px;padding:8px 11px;font-size:12px;margin-bottom:10px}
</style>

<main class="main">
  <div class="ap-top">
    <div>
      <div class="page-title">Assistente de apólice</div>
      <div class="page-sub">Anexe a apólice e pergunte em português. A resposta sai do documento, com a cláusula de origem.</div>
    </div>
    <div class="ap-actions">
      <select id="ap-tipo" class="ap-sel">
        <option>Apólice</option>
        <option>Especificação</option>
      </select>
      <button class="btn btn-primary" onclick="document.getElementById('ap-file').click()"><span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle">attach_file</span> Anexar documento</button>
      <input type="file" id="ap-file" accept="application/pdf" style="display:none" onchange="apUpload(this.files[0])">
    </div>
  </div>

  <div style="margin-top:14px;max-width:520px">
    <label style="display:block;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:5px">Cliente</label>
    <select id="ap-grupo" class="ap-sel" style="width:100%" onchange="apLoad()">
      <option value="">Todas as carteiras</option>
    </select>
    <div class="ap-note">Escolha uma carteira para anexar um documento novo.</div>
  </div>

  <div class="ap-grid">
    <!-- Lista de documentos -->
    <div class="ap-card">
      <div style="display:flex;align-items:center;justify-content:space-between">
        <div style="font-weight:800"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">folder</span> Documentos</div>
        <span id="ap-count" style="font-size:12px;color:var(--muted)">0</span>
      </div>
      <div class="ap-drop" id="ap-drop" onclick="document.getElementById('ap-file').click()">
        <span class="material-symbols-rounded" style="font-size:22px">upload_file</span><br>
        Arraste a apólice em PDF para cá<br>ou use o botão Anexar documento
      </div>
      <div id="ap-list"></div>
    </div>

    <!-- Documento selecionado + chat -->
    <div class="ap-card ap-chat" id="ap-panel">
      <div class="ap-empty" id="ap-empty">Selecione um documento à esquerda para começar.</div>
      <div id="ap-active" style="display:none">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px">
          <div>
            <div style="font-weight:800" id="ap-a-nome"></div>
            <div class="ap-meta" id="ap-a-meta"></div>
          </div>
          <div style="display:flex;gap:6px;white-space:nowrap">
            <a class="btn btn-outline btn-sm" id="ap-a-ver" target="_blank"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">picture_as_pdf</span> Ver o PDF</a>
            <button class="btn btn-outline btn-sm" onclick="apExcluir()" title="Excluir documento"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">delete</span></button>
          </div>
        </div>
        <div class="ap-cmp" style="margin-top:12px">
          <span class="material-symbols-rounded" style="font-size:18px;color:var(--accent)">compare_arrows</span> Comparar com
          <select id="ap-cmp-sel" class="ap-sel" style="min-width:240px;padding:6px 10px"><option value="">Nenhum, só este documento</option></select>
          <span id="ap-cmp-note" style="color:var(--muted)"></span>
        </div>

        <div class="ap-msgs" id="ap-msgs"></div>

        <div class="ap-ask">
          <textarea id="ap-pergunta" placeholder="Pergunte sobre este documento. Ex.: qual a franquia em caso de roubo?" onkeydown="if(event.key==='Enter'&&(event.ctrlKey||event.metaKey)){apPerguntar();event.preventDefault();}"></textarea>
          <button class="btn btn-primary" id="ap-send" onclick="apPerguntar()" style="align-self:flex-end"><span class="material-symbols-rounded">send</span></button>
        </div>
        <div class="ap-note" id="ap-rodape">As respostas saem do documento anexado e podem conter erro de leitura. Confira no texto da apólice antes de usar com o cliente.</div>
      </div>
    </div>
  </div>
</main>

<script>
(function(){
  const $=id=>document.getElementById(id);
  const esc=s=>{const d=document.createElement('div');d.textContent=(s==null?'':s);return d.innerHTML;};
  const kb=n=>n>=1024?(Math.round(n/1024))+' KB':n+' B';
  const fdt=s=>{ if(!s)return''; const d=new Date(s.replace(' ','T')); return isNaN(d)?s:d.toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}); };
  let DOCS=[], SEL=null, REST=null, PODE=true;

  window.apLoadGrupos=async function(){
    try{ const r=await fetch('api_apolice.php?action=ap_grupos').then(x=>x.json());
      if(r&&r.ok){ const s=$('ap-grupo'); (r.grupos||[]).forEach(g=>{ const o=document.createElement('option'); o.value=g; o.textContent=g; s.appendChild(o); }); }
    }catch(e){}
  };

  window.apLoad=async function(){
    const g=$('ap-grupo').value;
    try{ const r=await fetch('api_apolice.php?action=ap_listar&grupo='+encodeURIComponent(g)).then(x=>x.json());
      DOCS=(r&&r.data)||[]; REST=(r&&r.restantes); PODE=!!(r&&r.pode_editar);
    }catch(e){ DOCS=[]; }
    apRenderList();
    if(SEL && !DOCS.some(d=>+d.id===+SEL.id)){ SEL=null; apRenderPanel(); }
    apRodape();
  };

  function apRodape(){
    const base='As respostas saem do documento anexado e podem conter erro de leitura. Confira no texto da apólice antes de usar com o cliente.';
    $('ap-rodape').textContent = base + (REST!=null ? '  ·  '+REST+' perguntas restantes hoje' : '');
  }

  function apRenderList(){
    $('ap-count').textContent=DOCS.length;
    $('ap-list').innerHTML = DOCS.map(d=>`
      <div class="ap-doc${SEL&&+SEL.id===+d.id?' sel':''}" onclick="apSelecionar(${d.id})">
        <b>${esc(d.nome_arquivo)}</b> <span class="ap-badge">${esc(d.tipo)}</span>
        <div class="ap-meta">${kb(d.tamanho)} · ${d.perguntas||0} pergunta(s)<br>${esc(d.usuario||'')} · ${fdt(d.criado_em)}${d.grupo?('<br>📁 '+esc(d.grupo)):''}</div>
      </div>`).join('') || '<div class="ap-note" style="margin-top:10px">Nenhum documento nesta carteira ainda.</div>';
  }

  window.apSelecionar=function(id){
    SEL=DOCS.find(d=>+d.id===id)||null;
    apRenderPanel(); apCarregarHistorico();
  };

  function apRenderPanel(){
    if(!SEL){ $('ap-empty').style.display=''; $('ap-active').style.display='none'; apRenderList(); return; }
    $('ap-empty').style.display='none'; $('ap-active').style.display='';
    $('ap-a-nome').textContent=SEL.nome_arquivo;
    $('ap-a-meta').innerHTML=esc(SEL.tipo)+' · '+kb(SEL.tamanho)+' · anexado por '+esc(SEL.usuario||'')+' em '+fdt(SEL.criado_em)+(SEL.grupo?(' · 📁 '+esc(SEL.grupo)):'');
    $('ap-a-ver').href='api_apolice.php?action=ap_ver&id='+SEL.id;
    // dropdown comparar: outros docs da mesma carteira
    const outros=DOCS.filter(d=>+d.id!==+SEL.id && (d.grupo||'')===(SEL.grupo||''));
    const s=$('ap-cmp-sel'); s.innerHTML='<option value="">Nenhum, só este documento</option>'+outros.map(d=>`<option value="${d.id}">${esc(d.nome_arquivo)} (${esc(d.tipo)})</option>`).join('');
    $('ap-cmp-note').textContent = outros.length? '' : 'Anexe um segundo documento desta carteira para poder comparar.';
    apRenderList();
  }

  async function apCarregarHistorico(){
    $('ap-msgs').innerHTML='<div class="ap-note" style="text-align:center">Carregando histórico…</div>';
    try{ const r=await fetch('api_apolice.php?action=ap_perguntas&doc_id='+SEL.id).then(x=>x.json());
      const arr=(r&&r.data)||[];
      $('ap-msgs').innerHTML = arr.length ? arr.map(m=>msgHtml(m.pergunta, m.resposta, m)).join('') : '<div class="ap-note" style="text-align:center">Faça a primeira pergunta sobre este documento.</div>';
      apScroll();
    }catch(e){ $('ap-msgs').innerHTML=''; }
  }

  function msgHtml(q, a, meta){
    let foot='';
    if(meta){ const bits=[]; if(meta.modelo)bits.push(meta.modelo); if(meta.segundos)bits.push(meta.segundos+'s'); if(meta.tokens_in)bits.push((meta.tokens_in)+' tokens lidos'); if(meta.tokens_out)bits.push(meta.tokens_out+' escritos'); foot=bits.join(' · '); }
    return `<div class="ap-q">${esc(q)}</div><div class="ap-a">${esc(a)}${foot?`<div class="ap-foot">${esc(foot)}</div>`:''}</div>`;
  }
  function apScroll(){ const m=$('ap-msgs'); m.scrollTop=m.scrollHeight; }

  window.apPerguntar=async function(){
    if(!SEL) return;
    const ta=$('ap-pergunta'); const q=(ta.value||'').trim(); if(!q) return;
    if(REST!=null && REST<=0){ alert('Você atingiu o limite de perguntas de hoje.'); return; }
    const cmp=$('ap-cmp-sel').value;
    ta.value='';
    $('ap-msgs').insertAdjacentHTML('beforeend', `<div class="ap-q">${esc(q)}</div><div class="ap-a" id="ap-loading"><em style="color:var(--muted)">Lendo o documento e respondendo…</em></div>`);
    apScroll();
    $('ap-send').disabled=true;
    try{
      const r=await fetch('api_apolice.php?action=ap_perguntar',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({doc_id:SEL.id, doc_id2:(cmp?+cmp:0), pergunta:q})}).then(x=>x.json());
      $('ap-send').disabled=false;
      const ld=$('ap-loading'); if(ld) ld.remove();
      if(!r||!r.ok){ $('ap-msgs').insertAdjacentHTML('beforeend', `<div class="ap-a" style="color:#c0392b">${esc((r&&r.msg)||'Erro ao responder')}</div>`); apScroll(); return; }
      $('ap-msgs').insertAdjacentHTML('beforeend', msgHtml(q, r.resposta, r));
      REST=r.restantes; apRodape();
      // atualiza contagem de perguntas do doc
      SEL.perguntas=(+SEL.perguntas||0)+1; const d=DOCS.find(x=>+x.id===+SEL.id); if(d)d.perguntas=SEL.perguntas; apRenderList();
      apScroll();
    }catch(e){ $('ap-send').disabled=false; const ld=$('ap-loading'); if(ld) ld.remove(); $('ap-msgs').insertAdjacentHTML('beforeend','<div class="ap-a" style="color:#c0392b">Erro de conexão.</div>'); }
  };

  window.apUpload=async function(file){
    if(!file) return;
    if(file.type!=='application/pdf' && !/\.pdf$/i.test(file.name)){ alert('Envie um arquivo PDF.'); return; }
    const fd=new FormData();
    fd.append('arquivo', file);
    fd.append('grupo', $('ap-grupo').value);
    fd.append('tipo', $('ap-tipo').value);
    $('ap-drop').innerHTML='<em>Enviando '+esc(file.name)+'…</em>';
    try{
      const r=await fetch('api_apolice.php?action=ap_upload',{method:'POST',body:fd}).then(x=>x.json());
      if(!r||!r.ok){ alert((r&&r.msg)||'Falha no upload'); }
      await apLoad();
      if(r&&r.ok&&r.id){ apSelecionar(+r.id); }
    }catch(e){ alert('Erro de conexão no upload'); }
    $('ap-drop').innerHTML='<span class="material-symbols-rounded" style="font-size:22px">upload_file</span><br>Arraste a apólice em PDF para cá<br>ou use o botão Anexar documento';
    $('ap-file').value='';
  };

  window.apExcluir=async function(){
    if(!SEL) return;
    if(!confirm('Excluir o documento "'+SEL.nome_arquivo+'"? As perguntas feitas nele serão mantidas no histórico.')) return;
    try{ const r=await fetch('api_apolice.php?action=ap_excluir',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:SEL.id})}).then(x=>x.json());
      if(!r||!r.ok){ alert((r&&r.msg)||'Erro ao excluir'); return; }
      SEL=null; await apLoad(); apRenderPanel();
    }catch(e){ alert('Erro de conexão'); }
  };

  // drag & drop
  const drop=$('ap-drop');
  ['dragenter','dragover'].forEach(ev=>drop.addEventListener(ev,e=>{e.preventDefault();drop.classList.add('drag');}));
  ['dragleave','drop'].forEach(ev=>drop.addEventListener(ev,e=>{e.preventDefault();drop.classList.remove('drag');}));
  drop.addEventListener('drop',e=>{ const f=e.dataTransfer.files&&e.dataTransfer.files[0]; if(f) apUpload(f); });

  apLoadGrupos(); apLoad();
})();
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
