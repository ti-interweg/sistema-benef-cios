<?php
$currentPage = 'apresentacao_teste';
$pageTitle   = 'Apresentação Teste — Interweg Benefícios';
require_once __DIR__ . '/_header.php';
?>

<style>
  .ap-tabs{display:flex;gap:6px;border-bottom:1px solid var(--border);margin:6px 0 22px;flex-wrap:wrap}
  .ap-tab{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border:none;background:none;cursor:pointer;font-size:14px;font-weight:700;color:var(--muted);border-bottom:3px solid transparent;margin-bottom:-1px;border-radius:8px 8px 0 0;transition:color .15s ease,background .15s ease}
  .ap-tab:hover{color:var(--text);background:rgba(0,191,179,.05)}
  .ap-tab.active{color:var(--accent);border-bottom-color:var(--accent)}
  .ap-pane{display:none}
  .ap-pane.active{display:block}
  .ap-drop{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:18px 20px;background:var(--bg-card);border:2px dashed var(--border);border-radius:14px;margin-bottom:18px;transition:border-color .15s ease,background .15s ease}
  .ap-drop-over{border-color:var(--accent)!important;background:rgba(0,191,179,.08)!important}
  .ap-drop-over *{pointer-events:none}
  .ap-drop>.material-symbols-rounded{font-size:40px;color:var(--accent)}
  .ap-drop-over>.material-symbols-rounded{animation:apbounce .6s ease infinite}
  @keyframes apbounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
  .ap-uploadbtn{display:inline-flex;align-items:center;gap:8px;padding:11px 20px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-weight:700;font-size:13px;cursor:pointer;border:none;box-shadow:0 4px 14px rgba(0,180,166,.28);transition:transform .1s ease,box-shadow .15s ease;white-space:nowrap}
  .ap-uploadbtn:hover{transform:translateY(-1px);box-shadow:0 7px 20px rgba(0,180,166,.4)}
  .ap-uploadbtn:active{transform:translateY(0)}
  .ap-uploadbtn .material-symbols-rounded{font-size:20px;color:#fff}
  .ap-out{overflow:auto}
  .ap-sheet-title{font-size:13px;font-weight:800;color:var(--accent);text-transform:uppercase;letter-spacing:.4px;margin:16px 0 8px}
  table.ap-tbl{border-collapse:collapse;font-size:12px;background:var(--bg-card);min-width:100%}
  table.ap-tbl td{border:1px solid var(--border);padding:5px 8px;white-space:nowrap;vertical-align:middle;text-align:center;color:var(--text)}
  table.ap-tbl tr:first-child td{background:var(--bg);font-weight:700}
  .ap-empty{padding:40px;text-align:center;color:var(--muted)}
  .lc-wrap{overflow-x:auto;overflow-y:hidden}
  /* ── Comparativo de custos — identidade Interweg ─────────────────── */
  .lc-wrap{border-radius:14px;overflow-x:auto;overflow-y:hidden;border:1px solid var(--border);box-shadow:0 8px 30px rgba(0,0,0,.10);-webkit-overflow-scrolling:touch}
  table.lc{border-collapse:separate;border-spacing:0;font-size:12px;min-width:780px;width:100%;background:var(--bg-card);color:var(--text)}
  .lc td,.lc th{border-right:1px solid var(--border);border-bottom:1px solid var(--border);padding:7px 9px;text-align:center;white-space:nowrap}
  .lc td.vcell{width:92px;min-width:92px;max-width:104px;padding:5px 6px}
  .lc td.vcell input,.lc td.vcell textarea.lc-vlh{min-width:0;padding-left:2px;padding-right:2px}
  .lc td.fxcell{width:132px;min-width:120px;max-width:140px}
  .lc tr td:last-child{border-right:none}
  .lc tr:last-child td{border-bottom:none}
  /* rótulos à esquerda e faixa etária = gradiente turquesa da marca */
  .lc .lbl{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-weight:700;text-align:right;letter-spacing:.02em;text-transform:uppercase;font-size:11px;border-right-color:rgba(255,255,255,.22);white-space:normal;word-break:break-word;line-height:1.25}
  .lc .colhead{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-weight:700}
  .lc .faixa td:first-child{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-weight:700;text-align:center;border-right-color:rgba(255,255,255,.22)}
  .lc .faixa td:first-child input{color:#fff;text-align:center;font-weight:700}
  .lc .faixa td:first-child input::placeholder{color:rgba(255,255,255,.75)}
  /* zebra suave só nas colunas de valores (a 1ª coluna continua turquesa) */
  .lc .faixa:nth-child(even) td{background:rgba(0,180,166,.06)}
  .lc .faixa:nth-child(even) td:first-child{background:linear-gradient(135deg,var(--accent),var(--accent2));filter:brightness(.95)}
  /* inputs sem "pílula" — fundo transparente, só aparece caixa no foco
     (td input p/ vencer a regra global body.light input) */
  .lc td input{width:100%;min-width:70px;box-sizing:border-box;border:1px solid transparent!important;background:transparent!important;text-align:center;font-size:12px;padding:3px;color:var(--text);border-radius:4px}
  .lc td input::placeholder{color:var(--muted)}
  .lc td input:focus{border-color:var(--accent)!important;background:var(--bg-card)!important;outline:none;box-shadow:0 0 0 3px rgba(0,180,166,.18)}
  .lc td select{background:var(--bg-card);color:var(--text);border:1px solid var(--border);border-radius:5px;padding:3px 4px;font-size:11px}
  /* seguradora: célula colorida por operadora (definida inline) */
  .lc .seghead input{color:#fff!important;font-weight:800;text-align:center}
  .lc .seghead input::placeholder{color:rgba(255,255,255,.8)}
  /* linhas de totais e cabeçalho de faixa = gradiente cheio */
  .lc .tot td{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-weight:700;border-right-color:rgba(255,255,255,.22)}
  .lc .tot td input{color:#fff!important;font-weight:700;border-color:rgba(255,255,255,.35)!important}
  .lc .tot td input::placeholder{color:rgba(255,255,255,.75)}
  /* reembolso: rótulo em turquesa, valores neutros */
  .lc .reemb .lbl{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff}
  .lc .diff td{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-weight:800}
  .lc .out{font-weight:800;font-variant-numeric:tabular-nums}
  /* ao editar QUALQUER campo: caixa branca + texto escuro (vence o texto branco) */
  .lc td input:focus{color:#1f2937!important;background:#fff!important;border-color:var(--accent)!important;border-radius:4px;box-shadow:0 0 0 3px rgba(0,180,166,.22);text-align:center}
  /* Campo VIDAS (cabeçalho) — textarea que quebra linha e cresce com o conteúdo */
  .lc td textarea.lc-vlh{width:100%;min-width:70px;min-height:0!important;height:auto;box-sizing:border-box;border:1px solid transparent!important;background:transparent!important;text-align:center;font:inherit;font-size:12px;line-height:1.3;padding:3px;color:inherit;border-radius:4px;resize:none;overflow:hidden;white-space:pre-wrap;word-break:break-word;display:block}
  .lc .tot td textarea.lc-vlh{color:#fff}
  .lc td textarea.lc-vlh::placeholder{color:rgba(255,255,255,.75)}
  .lc td textarea.lc-vlh:focus{color:#1f2937!important;background:#fff!important;border-color:var(--accent)!important;box-shadow:0 0 0 3px rgba(0,180,166,.22);outline:none}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Apresentação Teste</div>
      <div class="page-sub">Suba a planilha e a página retorna os valores no layout. Cenário Conta Nova (novos), Renovação (já existente) e Coparticipação.</div>
    </div>
  </div>

  <!-- Salvar / abrir projeto (estudo do comparativo) -->
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin:4px 0 18px">
    <span style="font-size:12.5px;color:var(--muted);font-weight:700"><span class="material-symbols-rounded" style="font-size:17px;vertical-align:middle;color:var(--accent)">folder_open</span> Projeto</span>
    <input type="hidden" id="ap-proj-id">
    <input type="text" id="ap-proj-nome" placeholder="Nome do projeto (ex.: Cliente X - Out/26)" style="flex:1;min-width:220px;padding:8px 11px;border:1px solid var(--border);border-radius:9px;font-size:13px;background:var(--bg-card);color:var(--text)">
    <button class="btn btn-sm" style="background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff" onclick="apEstudoSalvar(this)"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">save</span> Salvar</button>
    <span style="width:1px;height:22px;background:var(--border)"></span>
    <select id="ap-proj-sel" class="btn btn-outline btn-sm" style="min-width:220px;padding:7px 10px"><option value="">Abrir projeto salvo...</option></select>
    <button class="btn btn-outline btn-sm" onclick="apEstudoAbrir()"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">folder</span> Abrir</button>
    <button class="btn btn-outline btn-sm" onclick="apEstudoNovo()" title="Começar um projeto novo (limpa a tela)"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">note_add</span> Novo</button>
    <button class="btn btn-outline btn-sm" onclick="apEstudoExcluir()" title="Excluir o projeto selecionado"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">delete</span></button>
    <span id="ap-proj-msg" style="font-size:12px;color:var(--muted)"></span>
  </div>

  <div class="ap-tabs">
    <button class="ap-tab active" data-tab="cn"  onclick="apTab('cn')"><span class="material-symbols-rounded" style="font-size:18px">fiber_new</span> Conta Nova</button>
    <button class="ap-tab" data-tab="ren" onclick="apTab('ren')"><span class="material-symbols-rounded" style="font-size:18px">autorenew</span> Renovação</button>
    <button class="ap-tab" data-tab="cop" onclick="apTab('cop')"><span class="material-symbols-rounded" style="font-size:18px">payments</span> Coparticipação</button>
  </div>

  <!-- CONTA NOVA -->
  <div id="ap-pane-cn" class="ap-pane active">
    <div class="ap-drop">
      <span class="material-symbols-rounded">cloud_upload</span>
      <div style="flex:1;min-width:220px">
        <div style="font-weight:700">Orçamentos: Conta Nova</div>
        <div style="font-size:12px;color:var(--muted)">Suba os PDFs das cotações (uma por seguradora). A IA lê cada uma e junta no comparativo abaixo. Pode selecionar vários.</div>
      </div>
      <label class="ap-uploadbtn"><span class="material-symbols-rounded">cloud_upload</span> Selecionar arquivos<input type="file" multiple accept=".pdf,.xlsx,.xls,.csv,.png,.jpg,.jpeg,.webp" onchange="apLer(this,'ap-out-cn')" hidden/></label>
    </div>
    <div id="ap-out-cn" class="ap-out"><div class="ap-empty">Suba a planilha para ver os valores brutos (opcional).</div></div>

    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin:24px 0 10px">
      <div class="page-title" style="font-size:15px">Comparativo de custos (editável)</div>
      <label style="font-size:12px;color:var(--muted)">IOF %<input id="lc-iof-ap-cn-layout" type="number" step="0.01" value="0" oninput="lcRecompute('ap-cn-layout')" style="width:70px;margin-left:6px;padding:5px 8px;border:1px solid var(--border);border-radius:8px"/></label>
      <button class="btn btn-sm" style="background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff" onclick="lcAddCol('ap-cn-layout')"><span class="material-symbols-rounded" style="font-size:15px">add</span> Coluna</button>
      <button class="btn btn-outline btn-sm" onclick="lcAbrirAgrupar('ap-cn-layout')"><span class="material-symbols-rounded" style="font-size:15px">join_inner</span> Agrupar</button>
      <button class="btn btn-outline btn-sm" onclick="lcReset('ap-cn-layout')"><span class="material-symbols-rounded" style="font-size:15px">delete_sweep</span> Limpar</button>
    </div>
    <div class="lc-wrap"><div id="ap-cn-layout"><div class="ap-empty">Suba as cotações acima. Cada uma vira uma coluna nesta tabela editável.</div></div></div>
  </div>

  <!-- RENOVAÇÃO -->
  <div id="ap-pane-ren" class="ap-pane">
    <div class="ap-drop">
      <span class="material-symbols-rounded">cloud_upload</span>
      <div style="flex:1;min-width:220px">
        <div style="font-weight:700">Orçamentos: Renovação</div>
        <div style="font-size:12px;color:var(--muted)">Suba os PDFs das cotações (uma por seguradora). A IA junta no comparativo abaixo. Pode selecionar vários.</div>
      </div>
      <label class="ap-uploadbtn"><span class="material-symbols-rounded">cloud_upload</span> Selecionar arquivos<input type="file" multiple accept=".pdf,.xlsx,.xls,.csv,.png,.jpg,.jpeg,.webp" onchange="apLer(this,'ap-out-ren')" hidden/></label>
    </div>
    <div id="ap-out-ren" class="ap-out"></div>
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin:20px 0 10px">
      <div class="page-title" style="font-size:15px">Comparativo de custos (editável)</div>
      <label style="font-size:12px;color:var(--muted)">IOF %<input id="lc-iof-ap-ren-layout" type="number" step="0.01" value="0" oninput="lcRecompute('ap-ren-layout')" style="width:70px;margin-left:6px;padding:5px 8px;border:1px solid var(--border);border-radius:8px"/></label>
      <button class="btn btn-sm" style="background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff" onclick="lcAddCol('ap-ren-layout')"><span class="material-symbols-rounded" style="font-size:15px">add</span> Coluna</button>
      <button class="btn btn-outline btn-sm" onclick="lcAbrirAgrupar('ap-ren-layout')"><span class="material-symbols-rounded" style="font-size:15px">join_inner</span> Agrupar</button>
      <button class="btn btn-outline btn-sm" onclick="lcReset('ap-ren-layout')"><span class="material-symbols-rounded" style="font-size:15px">delete_sweep</span> Limpar</button>
    </div>
    <div class="lc-wrap"><div id="ap-ren-layout"><div class="ap-empty">Suba as cotações acima. Cada uma vira uma coluna nesta tabela editável.</div></div></div>
  </div>

  <!-- COPARTICIPAÇÃO -->
  <div id="ap-pane-cop" class="ap-pane">
    <div class="ap-drop">
      <span class="material-symbols-rounded">cloud_upload</span>
      <div style="flex:1;min-width:220px">
        <div style="font-weight:700">Planilha de coparticipação</div>
        <div style="font-size:12px;color:var(--muted)">Modelos de coparticipação (Bradesco, SulAmérica, Amil…). Arraste o arquivo aqui ou escolha. Aceita .xlsx, .xls ou .csv.</div>
      </div>
      <label class="ap-uploadbtn"><span class="material-symbols-rounded">cloud_upload</span> Selecionar arquivos<input type="file" multiple accept=".pdf,.xlsx,.xls,.csv,.png,.jpg,.jpeg,.webp" onchange="apLer(this,'ap-out-cop')" hidden/></label>
    </div>
    <div id="ap-out-cop" class="ap-out"><div class="ap-empty">Suba a planilha para ver os valores aqui.</div></div>
  </div>

  <!-- Exportar o comparativo da aba ativa (vira página do PDF/PPT de apresentação) -->
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:18px 0 6px;padding-top:14px;border-top:1px solid var(--border)">
    <span style="font-size:12.5px;color:var(--muted)">Exportar esta aba:</span>
    <button class="btn btn-sm" style="background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff" onclick="apBaixarPPT(this)"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">slideshow</span> Baixar PPT</button>
    <button class="btn btn-outline btn-sm" onclick="apBaixarPDF(this)"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">picture_as_pdf</span> Baixar PDF</button>
    <button class="btn btn-outline btn-sm" onclick="apImprimir(this)"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">print</span> Imprimir</button>
    <span id="ap-export-msg" style="font-size:12px;color:var(--muted)"></span>
  </div>

  <!-- Modal: Agrupar orçamentos -->
  <div id="lc-agrupar-ov" style="display:none;position:fixed;inset:0;background:rgba(16,24,40,.45);z-index:99999;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)lcFecharAgrupar()">
    <div style="background:var(--bg-card,#fff);border:1px solid var(--border,#e5e7eb);border-radius:16px;box-shadow:0 24px 64px -16px rgba(16,24,40,.55);width:520px;max-width:95vw;max-height:85vh;overflow:auto;padding:24px">
      <div style="font-weight:800;font-size:16px;margin-bottom:6px"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">join_inner</span> Agrupar orçamentos</div>
      <div style="font-size:12.5px;color:var(--muted);margin-bottom:16px">Marque as colunas vizinhas que devem somar num total único na linha "Valor total de fatura". Por padrão, cada coluna tem o seu próprio total.</div>
      <div id="lc-agrupar-list"></div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px">
        <button class="btn btn-outline" onclick="lcFecharAgrupar()">Cancelar</button>
        <button class="btn btn-primary" onclick="lcAplicarAgrupar()"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">check</span> Aplicar</button>
      </div>
    </div>
  </div>

  <!-- Modal de confirmação (Limpar estudo) -->
  <div id="ap-confirm-ov" style="display:none;position:fixed;inset:0;background:rgba(16,24,40,.45);z-index:99999;align-items:center;justify-content:center;padding:20px;animation:apcfade .12s ease">
    <div style="background:var(--bg-card,#fff);border:1px solid var(--border,#e5e7eb);border-radius:16px;box-shadow:0 24px 64px -16px rgba(16,24,40,.55);width:440px;max-width:94vw;padding:24px;animation:apcup .16s cubic-bezier(.22,1,.36,1)">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
        <span style="width:42px;height:42px;border-radius:50%;background:rgba(220,53,69,.12);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0"><span class="material-symbols-rounded" style="color:#dc3545;font-size:24px">delete_sweep</span></span>
        <div style="font-size:17px;font-weight:800;color:var(--text,#1f2328)">Limpar estudo</div>
      </div>
      <div id="ap-confirm-msg" style="font-size:14px;color:var(--text,#444);line-height:1.55;margin:0 0 20px 2px">Tem certeza que você deseja excluir o estudo gerado? Esta ação não pode ser desfeita.</div>
      <div style="display:flex;justify-content:flex-end;gap:10px">
        <button id="ap-confirm-no" class="btn btn-outline" style="min-width:110px">Cancelar</button>
        <button id="ap-confirm-yes" class="btn btn-danger" style="min-width:110px"><span class="material-symbols-rounded" style="font-size:17px;vertical-align:-4px">delete</span> Excluir</button>
      </div>
    </div>
  </div>
  <style>@keyframes apcfade{from{opacity:0}to{opacity:1}}@keyframes apcup{from{transform:translateY(8px);opacity:.6}to{transform:translateY(0);opacity:1}}</style>
</main>

<script>
(function(){
  const $ = id => document.getElementById(id);
  const esc = s => { const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; };

  window.apTab = function(t){
    document.querySelectorAll('.ap-pane').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.ap-tab').forEach(b=>b.classList.toggle('active', b.dataset.tab===t));
    const p=$('ap-pane-'+t); if(p)p.classList.add('active');
  };

  // Converte uma worksheet em HTML preservando células mescladas e o texto formatado (R$)
  function sheetToHtml(ws){
    if(!ws || !ws['!ref']) return '<div class="ap-empty">Planilha vazia.</div>';
    const range = XLSX.utils.decode_range(ws['!ref']);
    // Limita o render pra não travar em planilhas gigantes
    const MAXR = 600, MAXC = 60;
    const rEnd = Math.min(range.e.r, range.s.r + MAXR - 1);
    const cEnd = Math.min(range.e.c, range.s.c + MAXC - 1);
    const trunc = (range.e.r > rEnd) || (range.e.c > cEnd);
    const merges = ws['!merges'] || [];
    const coberto = {}, span = {};
    merges.forEach(m=>{
      if(m.s.r>rEnd || m.s.c>cEnd) return;
      span[m.s.r+':'+m.s.c] = { rs:Math.min(m.e.r,rEnd)-m.s.r+1, cs:Math.min(m.e.c,cEnd)-m.s.c+1 };
      for(let r=m.s.r;r<=Math.min(m.e.r,rEnd);r++) for(let c=m.s.c;c<=Math.min(m.e.c,cEnd);c++){ if(!(r===m.s.r&&c===m.s.c)) coberto[r+':'+c]=1; }
    });
    let html='<table class="ap-tbl">';
    for(let r=range.s.r;r<=rEnd;r++){
      html+='<tr>';
      for(let c=range.s.c;c<=cEnd;c++){
        if(coberto[r+':'+c]) continue;
        const cell = ws[XLSX.utils.encode_cell({r,c})];
        let v = cell ? (cell.w!=null?cell.w:cell.v) : '';
        const sp = span[r+':'+c] || {rs:1,cs:1};
        html+=`<td${sp.rs>1?` rowspan="${sp.rs}"`:''}${sp.cs>1?` colspan="${sp.cs}"`:''}>${esc(v)}</td>`;
      }
      html+='</tr>';
    }
    html+='</table>';
    if(trunc) html+='<div class="ap-empty" style="padding:10px">Mostrando as primeiras '+MAXR+' linhas / '+MAXC+' colunas (planilha grande).</div>';
    return html;
  }

  // Renderiza as tabelas devolvidas pela IA ({titulo, colunas, linhas})
  function tabelasToHtml(tabelas){
    if(!Array.isArray(tabelas) || !tabelas.length) return '<div class="ap-empty">Nada para mostrar.</div>';
    return tabelas.map(t=>{
      let h = `<div class="ap-sheet-title">${esc(t.titulo||'Tabela')}</div><table class="ap-tbl">`;
      if(Array.isArray(t.colunas) && t.colunas.length){
        h += '<tr>' + t.colunas.map(c=>`<td>${esc(c)}</td>`).join('') + '</tr>';
      }
      (t.linhas||[]).forEach(ln=>{ h += '<tr>' + (ln||[]).map(v=>`<td>${esc(v)}</td>`).join('') + '</tr>'; });
      return h + '</table>';
    }).join('');
  }

  const CTX = { 'ap-out-cn':'conta_nova', 'ap-out-ren':'renovacao', 'ap-out-cop':'coparticipacao' };
  const LAYOUT_TARGET = { 'conta_nova':'ap-cn-layout', 'renovacao':'ap-ren-layout' };

  window.apLer = function(input, outId){
    const files = input.files;
    apProcess(files, outId);
    input.value = '';
  };

  function isPdf(file){ return /\.pdf$/i.test(file.name||'') || (file.type||'')==='application/pdf'; }
  function isImg(file){ return /\.(png|jpe?g|webp|gif|bmp)$/i.test(file.name||'') || /^image\//.test(file.type||''); }
  function imgMime(file){ const ty=(file.type||''); if(/^image\//.test(ty))return ty; const n=(file.name||'').toLowerCase(); if(/\.png$/.test(n))return 'image/png'; if(/\.webp$/.test(n))return 'image/webp'; if(/\.gif$/.test(n))return 'image/gif'; return 'image/jpeg'; }
  function b64(buf){ let bin=''; const bytes=new Uint8Array(buf); const CH=0x8000; for(let i=0;i<bytes.length;i+=CH){ bin+=String.fromCharCode.apply(null, bytes.subarray(i,i+CH)); } return btoa(bin); }

  function iaCall(body){
    const ctrl=new AbortController(); const to=setTimeout(()=>ctrl.abort(),180000);
    return fetch('apresentacao_ia.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body),signal:ctrl.signal})
      .then(x=>x.json()).then(r=>{ clearTimeout(to); return r; }).catch(()=>({erro:'conexão/tempo'}));
  }
  function lerArquivo(file, contexto){
    return new Promise(function(resolve){
      const reader=new FileReader();
      if(isPdf(file)){
        reader.onload=e=>{ iaCall({contexto:contexto, kind:'pdf', data:b64(e.target.result)}).then(resolve); };
        reader.onerror=()=>resolve({erro:'leitura'});
        reader.readAsArrayBuffer(file);
      } else if(isImg(file)){
        reader.onload=e=>{ iaCall({contexto:contexto, kind:'image', mime:imgMime(file), data:b64(e.target.result)}).then(resolve); };
        reader.onerror=()=>resolve({erro:'leitura'});
        reader.readAsArrayBuffer(file);
      } else {
        reader.onload=e=>{ let csv=''; try{ const wb=XLSX.read(e.target.result,{type:'array',cellText:true}); wb.SheetNames.forEach(n=>{ csv+='### '+n+'\n'+XLSX.utils.sheet_to_csv(wb.Sheets[n])+'\n\n'; }); }catch(err){ return resolve({erro:'planilha'}); } iaCall({contexto:contexto, kind:'text', data:csv}).then(resolve); };
        reader.onerror=()=>resolve({erro:'leitura'});
        reader.readAsArrayBuffer(file);
      }
    });
  }

  function apProcess(files, outId){
    const out=$(outId);
    const list = files && files.length!==undefined ? Array.from(files) : (files?[files]:[]);
    if(!list.length) return;
    const contexto = CTX[outId] || 'conta_nova';
    const alvoLayout = LAYOUT_TARGET[contexto] || null;

    // Contexto de LAYOUT (Conta Nova / Renovação): cada arquivo = uma cotação/coluna
    if(alvoLayout){
      if(out) out.innerHTML='';
      const tgt=document.getElementById(alvoLayout);
      let restantes=list.length, erros=0;
      const aviso=document.createElement('div'); aviso.className='ap-empty'; aviso.textContent='Lendo '+list.length+' cotação(ões) com IA…';
      if(tgt && (!window.lcTemDados || !window.lcTemDados(alvoLayout))) tgt.innerHTML='', tgt.appendChild(aviso); else if(tgt) tgt.insertBefore(aviso, tgt.firstChild);
      list.forEach(function(file){
        lerArquivo(file, contexto).then(function(r){
          restantes--;
          const cots = r && r.ok ? (r.cotacoes || (r.cotacao ? [r.cotacao] : [])) : [];
          if(cots.length){ cots.forEach(c=>window.lcAddCotacao(alvoLayout, c)); }
          else { erros++; }
          if(restantes<=0){ if(aviso&&aviso.parentNode) aviso.parentNode.removeChild(aviso); if(erros && tgt){ const w=document.createElement('div'); w.className='ap-empty'; w.style.color='#c0392b'; w.textContent=erros+' arquivo(s) a IA não conseguiu ler. Confira os PDFs.'; tgt.appendChild(w); } }
        });
      });
      return;
    }

    // Contexto Coparticipação: extrai a tabela e monta a grade editável
    if(out) out.innerHTML='<div class="ap-empty">Lendo a coparticipação com IA…</div>';
    Promise.all(list.map(f=>lerArquivo(f, contexto))).then(function(rs){
      const tabs=[]; rs.forEach(r=>{ if(r&&r.ok&&r.tabelas) tabs.push.apply(tabs, r.tabelas); });
      if(tabs.length && window.copBuild){ window.copBuild(tabs); }
      else { out.innerHTML='<div class="ap-empty" style="color:#c0392b">A IA não retornou a tabela de coparticipação. Confira o arquivo.</div>'; }
    });
  }

  // ── Arrastar e soltar em cada área de upload ──
  document.querySelectorAll('.ap-drop').forEach(function(dz){
    const inp = dz.querySelector('input[type=file]');
    const oc = inp ? (inp.getAttribute('onchange')||'') : '';
    const m = oc.match(/'(ap-out-[a-z]+)'/);
    const outId = m ? m[1] : null;
    if(!outId) return;
    ['dragenter','dragover'].forEach(function(ev){ dz.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); dz.classList.add('ap-drop-over'); }); });
    ['dragleave','dragend','drop'].forEach(function(ev){ dz.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); dz.classList.remove('ap-drop-over'); }); });
    dz.addEventListener('drop', function(e){
      const fs = e.dataTransfer && e.dataTransfer.files;
      if(fs && fs.length) apProcess(fs, outId);
    });
  });
  // Evita que soltar fora da área abra o arquivo no navegador
  window.addEventListener('dragover', function(e){ e.preventDefault(); });
  window.addEventListener('drop', function(e){ e.preventDefault(); });
})();
</script>

<script>
// Comparativo de custos EDITÁVEL — cada cotação (PDF) preenche uma coluna
(function(){
  const esc = s => { const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; };
  const brl = v => (v==null||v===''||isNaN(Number(v)))?'':'R$ '+Number(v).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
  const pn = s => { const n=parseFloat(String(s==null?'':s).replace(/[R$\s.]/g,'').replace(',','.')); return isNaN(n)?0:n; };
  // parse de percentual (ex.: IOF 2,38 ou 2.38) — NÃO remove ponto decimal como o pn()
  const pPct = s => { const n=parseFloat(String(s==null?'':s).trim().replace(',','.')); return isNaN(n)?0:n; };
  // Campo de cabeçalho VIDAS: textarea que quebra linha e cresce com o conteúdo.
  const lcVlh = (v,dl,ph) => '<textarea data-lc="'+dl+'" class="lc-vlh" rows="1" placeholder="'+esc(ph||'')+'" oninput="lcGrow(this)">'+esc(v==null?'':v)+'</textarea>';
  function lcGrow(el){ if(!el) return;
    const nl=(String(el.value||'').match(/\n/g)||[]).length;
    el.rows = Math.max(1, nl+1);        // 1 linha por quebra manual (Enter)
    el.style.height='auto';             // sem forçar altura: vazio/1 linha fica compacto
    if(el.scrollHeight > el.clientHeight) el.style.height=el.scrollHeight+'px'; // cobre quebra automática por largura
  }
  window.lcGrow = lcGrow;
  function lcGrowAll(el){ if(!el) return; el.querySelectorAll('textarea.lc-vlh').forEach(lcGrow); }
  function segColor(s){ s=String(s||'').toUpperCase(); if(s.indexOf('BRADESCO')>=0)return '#c0161d'; if(s.indexOf('SULAM')>=0||s.indexOf('SUL AM')>=0)return '#e0a200'; if(s.indexOf('AMIL')>=0)return '#2a2fb5'; if(s.indexOf('UNIMED')>=0)return '#1d9e75'; if(s.indexOf('HAPVIDA')>=0||s.indexOf('NOTREDAME')>=0||s.indexOf('NOTRE DAME')>=0||s.indexOf('GNDI')>=0)return '#e2601a'; if(s.indexOf('PORTO')>=0)return '#003a9b'; return '#00a589'; }
  // Logo da seguradora (cabeçalho mostra o logo em vez de só a cor)
  function segLogo(s){
    s=String(s||'').toUpperCase();
    const has=k=>s.indexOf(k)>=0;
    const P='logos_seguradoras/';
    if(has('HAPVIDA')) return P+'hapvida.png';
    if(has('SEGUROS UNIMED')||(has('UNIMED')&&has('SEGUROS'))) return P+'seguros_unimed.png';
    if(has('UNIMED')&&(has('JUIZ')||/\bJF\b/.test(s))) return P+'unimed_jf.png';
    if(has('UNIMED')&&(has('BELO HORIZONTE')||/\bBH\b/.test(s))) return P+'unimed_bh.png';
    if(has('BRADESCO')) return P+'bradesco.png';
    if(has('SULAM')||has('SUL AM')) return P+'sulamerica.png';
    if(has('AMIL')) return P+'amil.png';
    if(has('PORTO')) return P+'porto.png';
    if(has('SABIN')||has('SINAI')) return P+'sabin_sinai.png';
    if(has('AURORA')) return P+'aurora.png';
    return '';
  }
  // Monta a célula do cabeçalho de seguradora (com logo quando houver)
  function lcSegHeadCell(c,j,t){
    const lg=segLogo(c.seg);
    const cor=lg?'#64748b':'#fff';
    const icons='<span onclick="lcDupCol(\''+t+'\','+j+')" title="2ª opção (outra coluna de vidas)" style="cursor:pointer;font-weight:700;padding:2px 5px;flex:0 0 auto;position:relative;z-index:3;color:'+cor+'">⧉</span>'
               +'<span onclick="lcDelCol(\''+t+'\','+j+')" title="remover coluna" style="cursor:pointer;font-weight:700;padding:2px 6px;flex:0 0 auto;position:relative;z-index:3;color:'+cor+'">×</span>';
    if(lg){
      return '<td class="seghead" style="background:#fff;padding:5px 4px">'
        +'<div style="display:flex;align-items:center;justify-content:space-between;gap:2px">'
        +'<span style="width:16px;flex:0 0 auto"></span>'
        +'<img src="'+lg+'" alt="'+esc(c.seg)+'" style="height:34px;max-width:120px;object-fit:contain;flex:1 1 auto">'
        +'<span style="display:flex;flex:0 0 auto">'+icons+'</span></div>'
        +'<input data-lc="seg:'+j+'" value="'+esc(c.seg)+'" title="Seguradora (muda o logo ao recarregar)" style="width:100%;text-align:center;font-size:9px;color:#94a3b8!important;border:none;background:transparent"></td>';
    }
    return '<td class="seghead" style="background:'+segColor(c.seg)+'">'
      +'<div style="display:flex;align-items:center;gap:3px">'
      +'<input data-lc="seg:'+j+'" value="'+esc(c.seg)+'" placeholder="Seguradora" style="flex:1;width:auto;min-width:50px">'+icons+'</div></td>';
  }
  const DEF=['00 - 18','19 - 23','24 - 28','29 - 33','34 - 38','39 - 43','44 - 48','49 - 53','54 - 58','59 ou mais'];
  const LC={};
  const isRen = t => t==='ap-ren-layout';
  function S(t){ if(!LC[t]){ const ren=isRen(t); LC[t]={ vl:['VIDAS','VIDAS'], faixas:DEF.map(n=>({nome:n,v0:0,v1:0})), cols:[], acom:'APARTAMENTO', atual:{seg:'',prod:'',abr:'Nacional',cop:'NÃO',acom:'',valor:'',reaj:'',reajval:''} }; } return LC[t]; }

  // Modal de confirmação bonito (substitui o confirm nativo)
  window.apConfirm=function(msg, onYes){
    const ov=document.getElementById('ap-confirm-ov'); if(!ov){ if(confirm(msg||'Tem certeza?')) onYes&&onYes(); return; }
    const m=document.getElementById('ap-confirm-msg'); if(m && msg) m.textContent=msg;
    const yes=document.getElementById('ap-confirm-yes'), no=document.getElementById('ap-confirm-no');
    ov.style.display='flex';
    const close=()=>{ ov.style.display='none'; yes.onclick=null; no.onclick=null; ov.onclick=null; document.removeEventListener('keydown',onKey,true); };
    const onKey=(e)=>{ if(e.key==='Escape'){ e.preventDefault(); close(); } };
    yes.onclick=()=>{ close(); try{ onYes&&onYes(); }catch(err){} };
    no.onclick=close;
    ov.onclick=(e)=>{ if(e.target===ov) close(); };
    document.addEventListener('keydown',onKey,true);
  };
  window.lcReset=function(t){ window.apConfirm('Tem certeza que você deseja excluir o estudo gerado? Esta ação não pode ser desfeita.', function(){ delete LC[t]; const el=document.getElementById(t); if(el){ if(isRen(t)){ lcRenderRen(t); } else { el.innerHTML='<div class="ap-empty">Suba as cotações. Cada uma vira uma coluna nesta tabela editável.</div>'; } } if(typeof apSaveState==='function') apSaveState(); }); };
  window.lcTemDados=function(t){ return !!(LC[t] && LC[t].cols && LC[t].cols.length); };

  window.lcAddCotacao=function(t, cot){
    const st=S(t); cot=cot||{};
    const cv=cot.vidas||[];
    const has0=st.faixas.some(f=>Number(f.v0)>0), has1=st.faixas.some(f=>Number(f.v1)>0);
    let vcol=0;
    if(!has0){ st.faixas.forEach((f,i)=>f.v0=Number(cv[i]||0)); vcol=0; }
    else if(st.faixas.every((f,i)=>Number(f.v0)===Number(cv[i]||0))){ vcol=0; }
    else if(!has1){ st.faixas.forEach((f,i)=>f.v1=Number(cv[i]||0)); vcol=1; }
    else { vcol=1; }
    const vals=st.faixas.map((f,i)=> (cot.valores&&cot.valores[i]!=null)?Number(cot.valores[i]):'');
    st.cols.push({ seg:cot.seguradora||'', prod:cot.produto||'', cop:(cot.coparticipacao||''), abr:cot.abrangencia||'Nacional', acom:(cot.acomodacao||''), vcol:vcol, reemb:(cot.reembolso!=null?Number(cot.reembolso):''), vals:vals });
    if(cot.iof_pct!=null){ const io=document.getElementById('lc-iof-'+t); if(io && !pPct(io.value)) io.value=cot.iof_pct; }
    window.lcRender(t);
  };

  function snap(t){ const st=S(t); const el=document.getElementById(t); if(!el)return;
    const col=i=>st.cols[i]; const fx=i=>st.faixas[i]; // acessos protegidos (não quebra se índice sumiu)
    el.querySelectorAll('[data-lc]').forEach(inp=>{ try{ const p=inp.getAttribute('data-lc').split(':'); const k=p[0];
      if(k==='vl') st.vl[+p[1]]=inp.value;
      else if(k==='fnome'){ if(fx(+p[1])) fx(+p[1]).nome=inp.value; }
      else if(k==='v0'){ if(fx(+p[1])) fx(+p[1]).v0=pn(inp.value); }
      else if(k==='v1'){ if(fx(+p[1])) fx(+p[1]).v1=pn(inp.value); }
      else if(k==='vcol'){ if(col(+p[1])) col(+p[1]).vcol=parseInt(inp.value,10)||0; }
      else if(k==='val'){ if(col(+p[2])) col(+p[2]).vals[+p[1]]=(inp.value===''?'':pn(inp.value)); }
      else if(k==='reemb'){ if(col(+p[1])) col(+p[1]).reemb=(inp.value===''?'':pn(inp.value)); }
      else if(k==='difm'){ if(col(+p[1])) col(+p[1]).difm=(inp.value===''?'':pn(inp.value)); }
      else if(k==='difa'){ if(col(+p[1])) col(+p[1]).difa=(inp.value===''?'':pn(inp.value)); }
      else if(k==='areaj') st.atual.reaj=inp.value;
      else if(k==='avalor') st.atual.valor=inp.value;
      else if(k==='areajval') st.atual.reajval=inp.value;
      else if(['aseg','aprod','aabr','acop','aacom'].indexOf(k)>=0){ const kk=k.slice(1); st.atual[kk]=inp.value; }
      else if(['seg','prod','cop','abr','acom'].indexOf(k)>=0){ if(col(+p[1])) col(+p[1])[k]=inp.value; }
    }catch(e){ /* ignora célula problemática, não trava a ação */ } });
    if(typeof apSaveState==='function') apSaveState();
  }

  window.lcAddCol=function(t){ try{snap(t);}catch(e){} S(t).cols.push({seg:'',prod:'',cop:'',abr:'Nacional',acom:'',vcol:0,reemb:'',vals:S(t).faixas.map(()=>'')}); window.lcRender(t); };
  window.lcDelCol=function(t,j){ try{snap(t);}catch(e){} const st=S(t); if(st && st.cols && j>=0 && j<st.cols.length){ st.cols.splice(j,1); } window.lcRender(t); };
  // duplica a coluna ao lado, já aplicando o OUTRO grupo de vidas (2ª opção)
  window.lcDupCol=function(t,j){ try{snap(t);}catch(e){} const st=S(t); const c=st.cols[j]; if(!c)return; const clone=JSON.parse(JSON.stringify(c)); clone.vcol=(Number(c.vcol)===1?0:1); st.cols.splice(j+1,0,clone); window.lcRender(t); };
  // Alterna entre 1 e 2 colunas de VIDAS (os dados da 2ª ficam guardados quando ela é removida)
  window.lcRemoveVidas=function(t){ try{snap(t);}catch(e){} S(t).nvidas=1; window.lcRender(t); };
  window.lcAddVidas=function(t){ try{snap(t);}catch(e){} S(t).nvidas=2; window.lcRender(t); };
  // Renovação: remove/coloca a coluna "Atual com reajuste"
  window.lcRemoveReaj=function(t){ try{snap(t);}catch(e){} S(t).hideReaj=true; window.lcRender(t); };
  window.lcAddReaj=function(t){ try{snap(t);}catch(e){} S(t).hideReaj=false; window.lcRender(t); };

  window.lcRender=function(t){
    if(isRen(t)) return lcRenderRen(t);
    const st=S(t); const el=document.getElementById(t); if(!el)return;
    const cols=st.cols, faixas=st.faixas, nc=cols.length;
    if(!nc){ el.innerHTML='<div class="ap-empty">Suba as cotações. Cada uma vira uma coluna nesta tabela editável.</div>'; return; }
    const I=(v,dl,ph,style)=>'<input data-lc="'+dl+'" value="'+esc(v==null?'':v)+'" placeholder="'+esc(ph||'')+'" style="'+(style||'')+'">';
    const N=(v,dl)=>'<input data-lc="'+dl+'" value="'+(v!==''&&v!=null?esc(String(v).replace('.',',')):'')+'" placeholder="0,00" style="text-align:center">';
    let h='<div class="lc-wrap"><table class="lc"><tbody>';
    // Nº de colunas de VIDAS (1 ou 2). cspan = largura do rótulo à esquerda (FAIXA + VIDAS).
    const nv = (Number(st.nvidas)===1?1:2); const cspan = nv+1;
    // SEGURADORA (colorida por coluna) + add/remover + seletor de coluna de vidas
    h+='<tr><td class="lbl" colspan="'+cspan+'">SEGURADORA <button class="btn btn-outline btn-sm" style="padding:0 7px;margin-left:4px" onclick="lcAddCol(\''+t+'\')">+</button></td>'
      +cols.map((c,j)=>lcSegHeadCell(c,j,t)).join('')+'</tr>';
    h+='<tr><td class="lbl" colspan="'+cspan+'">PRODUTO</td>'+cols.map((c,j)=>'<td>'+I(c.prod,'prod:'+j,'Produto')+'</td>').join('')+'</tr>';
    h+='<tr><td class="lbl" colspan="'+cspan+'">ACOMODAÇÃO</td>'+cols.map((c,j)=>'<td>'+I(c.acom,'acom:'+j,'Apartamento')+'</td>').join('')+'</tr>';
    h+='<tr><td class="lbl" colspan="'+cspan+'">ABRANGÊNCIA</td>'+cols.map((c,j)=>'<td>'+I(c.abr,'abr:'+j,'Nacional')+'</td>').join('')+'</tr>';
    h+='<tr><td class="lbl" colspan="'+cspan+'">COPARTICIPAÇÃO</td>'+cols.map((c,j)=>'<td>'+I(c.cop,'cop:'+j,'SIM/NÃO')+'</td>').join('')+'</tr>';
    if(nv===2){
      h+='<tr><td class="lbl" colspan="'+cspan+'">VIDAS (coluna aplicada)</td>'+cols.map((c,j)=>'<td><select data-lc="vcol:'+j+'"><option value="0"'+(c.vcol==0?' selected':'')+'>'+esc(st.vl[0]||'VIDAS 1')+'</option><option value="1"'+(c.vcol==1?' selected':'')+'>'+esc(st.vl[1]||'VIDAS 2')+'</option></select></td>').join('')+'</tr>';
    }
    // Cabeçalho faixas — VIDAS 1 sempre; VIDAS 2 opcional (× remove, + adiciona de volta)
    let vidasHead='<td class="vcell" style="position:relative">'+lcVlh(st.vl[0],'vl:0','VIDAS')
      + (nv===1 ? '<span onclick="lcAddVidas(\''+t+'\')" title="Adicionar 2ª coluna de vidas" style="position:absolute;top:2px;right:4px;cursor:pointer;color:#fff;font-weight:800;font-size:13px;line-height:1">+</span>' : '')
      + '</td>';
    if(nv===2){
      vidasHead+='<td class="vcell" style="position:relative">'+lcVlh(st.vl[1],'vl:1','VIDAS')
        + '<span onclick="lcRemoveVidas(\''+t+'\')" title="Remover esta coluna de vidas" style="position:absolute;top:2px;right:4px;cursor:pointer;color:#fff;font-weight:800;font-size:13px;line-height:1">×</span></td>';
    }
    h+='<tr class="tot"><td class="fxcell">FAIXA ETÁRIA</td>'+vidasHead+'<td colspan="'+nc+'"></td></tr>';
    faixas.forEach((f,i)=>{
      h+='<tr class="faixa"><td class="fxcell">'+I(f.nome,'fnome:'+i)+'</td><td class="vcell">'+N(f.v0,'v0:'+i)+'</td>'
        + (nv===2 ? '<td class="vcell">'+N(f.v1,'v1:'+i)+'</td>' : '')
        +cols.map((c,j)=>'<td>'+N((c.vals[i]===undefined?'':c.vals[i]),'val:'+i+':'+j)+'</td>').join('')+'</tr>';
    });
    // Estimativa
    h+='<tr class="tot"><td class="lbl">ESTIMATIVA TOTAL</td><td><span data-out="vt:0"></span></td>'
      + (nv===2 ? '<td><span data-out="vt:1"></span></td>' : '')
      +cols.map((c,j)=>'<td class="out"><span data-out="est:'+j+'"></span></td>').join('')+'</tr>';
    // Valor total c/ IOF (por padrão, uma coluna = um total; pode agrupar pelo botão "Agrupar")
    let vf='<tr class="tot"><td class="lbl" colspan="'+cspan+'" style="white-space:nowrap">VALOR TOTAL DE FATURA</td>';
    { const g=lcGruposValidos(st,nc); let start=0; g.forEach(sz=>{ vf+='<td colspan="'+sz+'" class="out"><span data-out="grp:'+start+'"></span></td>'; start+=sz; }); }
    vf+='</tr>'; h+=vf;
    // Reembolso
    h+='<tr class="reemb"><td class="lbl" colspan="'+cspan+'">REEMBOLSO - CONSULTA MÉDICA</td>'+cols.map((c,j)=>'<td>'+N((c.reemb===''||c.reemb==null?'':c.reemb),'reemb:'+j)+'</td>').join('')+'</tr>';
    // Diferença mensal / anual (editáveis)
    h+='<tr class="tot"><td class="lbl" colspan="'+cspan+'">DIFERENÇA MENSAL</td>'+cols.map((c,j)=>'<td>'+N((c.difm===''||c.difm==null?'':c.difm),'difm:'+j)+'</td>').join('')+'</tr>';
    h+='<tr class="tot"><td class="lbl" colspan="'+cspan+'">DIFERENÇA ANUAL</td>'+cols.map((c,j)=>'<td>'+N((c.difa===''||c.difa==null?'':c.difa),'difa:'+j)+'</td>').join('')+'</tr>';
    h+='</tbody></table></div>';
    el.innerHTML=h;
    lcGrowAll(el);
    window.lcRecompute(t);
  };

  window.lcRecompute=function(t){
    if(isRen(t)) return lcRecomputeRen(t);
    snap(t); const st=S(t); const el=document.getElementById(t); if(!el)return;
    const iofEl=document.getElementById('lc-iof-'+t); const iof=iofEl?pPct(iofEl.value):0;
    const ipf=el.querySelector('[data-out="iofp"]'); if(ipf) ipf.textContent=(iof?iof.toLocaleString('pt-BR',{minimumFractionDigits:2}):'0')+'%';
    const set=(sel,v)=>{ const e=el.querySelector('[data-out="'+sel+'"]'); if(e)e.textContent=v; };
    set('vt:0', st.faixas.reduce((a,f)=>a+(Number(f.v0)||0),0));
    set('vt:1', st.faixas.reduce((a,f)=>a+(Number(f.v1)||0),0));
    const _nv=(Number(st.nvidas)===1?1:2);
    const est=st.cols.map(c=>{ let s=0; const vc=(_nv===1?0:c.vcol); st.faixas.forEach((f,i)=>{ const vv=(vc==1?Number(f.v1):Number(f.v0))||0; const val=(c.vals[i]===''||c.vals[i]==null?0:Number(c.vals[i])); s+=vv*val; }); return s; });
    est.forEach((s,j)=>set('est:'+j, brl(s)));
    { const g=lcGruposValidos(st, st.cols.length); let start=0; g.forEach(sz=>{ let s=0; for(let k=start;k<start+sz;k++) s+=est[k]; set('grp:'+start, brl(s*(1+iof/100))); start+=sz; }); }
  };

  // ── Agrupamento de colunas na linha "Valor total de fatura" ──
  // st.grupos = tamanhos de grupo em ordem (soma = nº de colunas). Padrão: tudo separado (1 cada).
  function lcGruposValidos(st, nc){
    let g = Array.isArray(st.grupos) ? st.grupos.map(x=>Math.max(1, parseInt(x,10)||1)) : null;
    if(!g || !g.length || g.reduce((a,b)=>a+b,0)!==nc){ g=[]; for(let k=0;k<nc;k++) g.push(1); }
    return g;
  }
  function lcMergesFromGrupos(g, nc){
    const idx=[]; let gi=0,left=g[0]||1;
    for(let c=0;c<nc;c++){ idx[c]=gi; left--; if(left===0 && gi<g.length-1){ gi++; left=g[gi]; } }
    const m=[]; for(let j=0;j<nc-1;j++) m[j]=(idx[j]===idx[j+1]); return m;
  }
  function lcGruposFromMerges(merged, nc){
    const g=[]; let size=1; for(let j=0;j<nc-1;j++){ if(merged[j]) size++; else { g.push(size); size=1; } } g.push(size); return g;
  }
  let _lcAgruparT=null;
  window.lcAbrirAgrupar=function(t){
    try{ snap(t); }catch(e){}
    const st=S(t); const nc=st.cols.length;
    if(nc<2){ (window.apAlert||window.alert)('Adicione pelo menos 2 colunas para agrupar.'); return; }
    _lcAgruparT=t;
    const g=lcGruposValidos(st,nc); const merged=lcMergesFromGrupos(g,nc);
    let html='';
    for(let c=0;c<nc;c++){
      const col=st.cols[c]||{}; const nome=((col.seg||'—')+' · '+(col.prod||'Produto')).trim();
      html+='<div style="padding:7px 10px;border:1px solid var(--border);border-radius:9px;margin-bottom:4px;font-size:13px"><b>Coluna '+(c+1)+'</b> <span style="color:var(--muted)">'+esc(nome)+'</span></div>';
      if(c<nc-1){
        html+='<label style="display:flex;align-items:center;gap:7px;font-size:12.5px;color:var(--accent);margin:0 0 6px 14px;cursor:pointer"><input type="checkbox" class="lc-merge-chk" data-j="'+c+'"'+(merged[c]?' checked':'')+'> somar a coluna '+(c+1)+' com a coluna '+(c+2)+' (um orçamento só)</label>';
      }
    }
    document.getElementById('lc-agrupar-list').innerHTML=html;
    const ov=document.getElementById('lc-agrupar-ov'); if(ov) ov.style.display='flex';
  };
  window.lcFecharAgrupar=function(){ const ov=document.getElementById('lc-agrupar-ov'); if(ov) ov.style.display='none'; };
  window.lcAplicarAgrupar=function(){
    const t=_lcAgruparT; if(!t) return;
    const st=S(t); const nc=st.cols.length;
    const merged=[]; for(let j=0;j<nc-1;j++) merged[j]=false;
    document.querySelectorAll('#lc-agrupar-list .lc-merge-chk').forEach(ch=>{ merged[+ch.getAttribute('data-j')]=ch.checked; });
    st.grupos=lcGruposFromMerges(merged,nc);
    lcFecharAgrupar();
    window.lcRender(t);
  };

  // ── Agrupa colunas cotadas consecutivas pela mesma seguradora ──
  function segGroups(cols){ const g=[]; cols.forEach((c,j)=>{ const nm=String(c.seg||'').trim().toUpperCase(); const last=g[g.length-1]; if(last && last.seg===nm){ last.count++; } else { g.push({seg:nm, start:j, count:1}); } }); return g; }

  // ── RENOVAÇÃO: ATUAL + ATUAL COM REAJUSTE (manuais) + COTADAS (upload) ──
  function lcRenderRen(t){
    const st=S(t); const el=document.getElementById(t); if(!el)return;
    const cols=st.cols, faixas=st.faixas, nc=cols.length, a=st.atual;
    const I=(v,dl,ph,style)=>'<input data-lc="'+dl+'" value="'+esc(v==null?'':v)+'" placeholder="'+esc(ph||'')+'" style="'+(style||'')+'">';
    const N=(v,dl)=>'<input data-lc="'+dl+'" value="'+(v!==''&&v!=null?esc(String(v).replace('.',',')):'')+'" placeholder="0,00" style="text-align:center">';
    const nv=(Number(st.nvidas)===1?1:2); const csl=1+nv; // bloco esquerdo: FAIXA + VIDAS
    const hr=!!st.hideReaj; const natual=hr?1:2; // colunas do bloco ATUAL (com ou sem reajuste)
    let h='<div class="lc-wrap"><table class="lc"><tbody>';
    // Linha topo: ATUAL | ATUAL COM REAJUSTE (removível) | COTADAS
    h+='<tr class="tot"><td colspan="'+csl+'" style="background:transparent;border:none"></td>'
      +'<td>ATUAL'+(hr?' <span onclick="lcAddReaj(\''+t+'\')" title="Adicionar coluna Atual com reajuste" style="cursor:pointer;color:#fff;font-weight:800;font-size:13px">+</span>':'')+'</td>'
      +(hr?'':'<td>ATUAL COM REAJUSTE <input data-lc="areaj" value="'+esc(a.reaj||'')+'" placeholder="0" style="width:46px;text-align:center;font-weight:800">% <span onclick="lcRemoveReaj(\''+t+'\')" title="Remover coluna Atual com reajuste" style="cursor:pointer;color:#fff;font-weight:800;font-size:13px">×</span></td>')
      +(nc?'<td colspan="'+nc+'">COTADAS</td>':'')+'</tr>';
    // SEGURADORA (com logo por coluna cotada)
    h+='<tr><td class="lbl" colspan="'+csl+'">OPERADORA/SEGURADORA <button class="btn btn-outline btn-sm" style="padding:0 7px;margin-left:4px" onclick="lcAddCol(\''+t+'\')">+</button></td>'
      +'<td class="seghead" style="background:'+segColor(a.seg)+'">'+I(a.seg,'aseg','Seguradora atual','min-width:80px')+'</td>'
      +(hr?'':'<td class="seghead" style="background:'+segColor(a.seg)+'"><input data-mir="aseg" readonly placeholder="Seguradora atual" style="width:100%;text-align:center;border:none;background:transparent;color:#fff;font-weight:700;min-width:80px"></td>')
      +cols.map((c,j)=>lcSegHeadCell(c,j,t)).join('')+'</tr>';
    // PRODUTO (os controles de 2ª opção/remover ficam no cabeçalho de seguradora)
    h+='<tr><td class="lbl" colspan="'+csl+'">PRODUTO</td>'
      +'<td>'+I(a.prod,'aprod','Produto atual')+'</td>'+(hr?'':'<td><input data-mir="aprod" readonly placeholder="Produto atual" style="width:100%;text-align:center;border:none;background:transparent;color:inherit"></td>')
      +cols.map((c,j)=>'<td>'+I(c.prod,'prod:'+j,'Produto')+'</td>').join('')+'</tr>';
    // ACOMODAÇÃO
    h+='<tr><td class="lbl" colspan="'+csl+'">ACOMODAÇÃO</td>'
      +'<td>'+I(a.acom,'aacom','Apartamento')+'</td>'+(hr?'':'<td><input data-mir="aacom" readonly placeholder="Apartamento" style="width:100%;text-align:center;border:none;background:transparent;color:inherit"></td>')
      +cols.map((c,j)=>'<td>'+I(c.acom,'acom:'+j,'Apartamento')+'</td>').join('')+'</tr>';
    // ABRANGÊNCIA
    h+='<tr><td class="lbl" colspan="'+csl+'">ABRANGÊNCIA</td>'
      +'<td>'+I(a.abr,'aabr','Nacional')+'</td>'+(hr?'':'<td><input data-mir="aabr" readonly placeholder="Nacional" style="width:100%;text-align:center;border:none;background:transparent;color:inherit"></td>')
      +cols.map((c,j)=>'<td>'+I(c.abr,'abr:'+j,'Nacional')+'</td>').join('')+'</tr>';
    // COPARTICIPAÇÃO
    h+='<tr><td class="lbl" colspan="'+csl+'">COPARTICIPAÇÃO</td>'
      +'<td>'+I(a.cop,'acop','SIM/NÃO')+'</td>'+(hr?'':'<td><input data-mir="acop" readonly placeholder="SIM/NÃO" style="width:100%;text-align:center;border:none;background:transparent;color:inherit"></td>')
      +cols.map((c,j)=>'<td>'+I(c.cop,'cop:'+j,'SIM/NÃO')+'</td>').join('')+'</tr>';
    // VIDAS (coluna aplicada) — só quando há 2 colunas de vidas
    if(nv===2){
      h+='<tr><td class="lbl" colspan="'+csl+'">VIDAS (coluna aplicada)</td><td colspan="'+natual+'" style="background:var(--bg-hover)"></td>'
        +cols.map((c,j)=>'<td><select data-lc="vcol:'+j+'"><option value="0"'+(c.vcol==0?' selected':'')+'>'+esc(st.vl[0]||'VIDAS 1')+'</option><option value="1"'+(c.vcol==1?' selected':'')+'>'+esc(st.vl[1]||'VIDAS 2')+'</option></select></td>').join('')+'</tr>';
    }
    // Cabeçalho faixas — VIDAS 1 sempre; VIDAS 2 opcional (× remove, + adiciona)
    let vhead='<td class="vcell" style="position:relative">'+lcVlh(st.vl[0],'vl:0','VIDAS')
      + (nv===1 ? '<span onclick="lcAddVidas(\''+t+'\')" title="Adicionar 2ª coluna de vidas" style="position:absolute;top:2px;right:4px;cursor:pointer;color:#fff;font-weight:800;font-size:13px;line-height:1">+</span>' : '')
      + '</td>';
    if(nv===2){
      vhead+='<td class="vcell" style="position:relative">'+lcVlh(st.vl[1],'vl:1','VIDAS')
        + '<span onclick="lcRemoveVidas(\''+t+'\')" title="Remover esta coluna de vidas" style="position:absolute;top:2px;right:4px;cursor:pointer;color:#fff;font-weight:800;font-size:13px;line-height:1">×</span></td>';
    }
    h+='<tr class="tot"><td class="fxcell">FAIXA ETÁRIA</td>'+vhead+'<td colspan="'+(natual+nc)+'">VALORES</td></tr>';
    // Faixas — ATUAL/REAJUSTE são um único valor (rowspan na 1ª faixa)
    faixas.forEach((f,i)=>{
      h+='<tr class="faixa"><td class="fxcell">'+I(f.nome,'fnome:'+i)+'</td><td class="vcell">'+N(f.v0,'v0:'+i)+'</td>'
        + (nv===2 ? '<td class="vcell">'+N(f.v1,'v1:'+i)+'</td>' : '');
      if(i===0){
        h+='<td rowspan="'+faixas.length+'" style="background:var(--bg-hover);vertical-align:middle">'+N(a.valor,'avalor')+'</td>'
          +(hr?'':'<td rowspan="'+faixas.length+'" style="background:var(--bg-hover);vertical-align:middle">'+N(a.reajval,'areajval')+'</td>');
      }
      h+=cols.map((c,j)=>'<td>'+N((c.vals[i]===undefined?'':c.vals[i]),'val:'+i+':'+j)+'</td>').join('')+'</tr>';
    });
    // ESTIMATIVA TOTAL
    h+='<tr class="tot"><td class="lbl">ESTIMATIVA TOTAL</td><td class="vcell"><span data-out="vt:0"></span></td>'
      + (nv===2 ? '<td class="vcell"><span data-out="vt:1"></span></td>' : '')
      +'<td class="out"><span data-out="atual"></span></td>'+(hr?'':'<td class="out"><span data-out="areaj_val2"></span></td>')
      +cols.map((c,j)=>'<td class="out"><span data-out="est:'+j+'"></span></td>').join('')+'</tr>';
    // VALOR TOTAL DE FATURA (por coluna por padrão; agrupável pelo botão "Agrupar") — já com IOF embutido
    let vf='<tr class="tot"><td class="lbl" colspan="'+csl+'" style="white-space:nowrap">VALOR TOTAL DE FATURA</td><td>—</td>'+(hr?'':'<td>—</td>');
    { const g=lcGruposValidos(st,nc); let start=0; g.forEach(sz=>{ vf+='<td colspan="'+sz+'" class="out"><span data-out="grp:'+start+'"></span></td>'; start+=sz; }); }
    vf+='</tr>'; h+=vf;
    // REEMBOLSO
    h+='<tr class="reemb"><td class="lbl" colspan="'+csl+'">REEMBOLSO - CONSULTA MÉDICA</td><td colspan="'+natual+'" style="background:var(--bg-hover)"></td>'
      +cols.map((c,j)=>'<td>'+N((c.reemb===''||c.reemb==null?'':c.reemb),'reemb:'+j)+'</td>').join('')+'</tr>';
    // DIFERENÇA MENSAL / ANUAL (editáveis por coluna)
    h+='<tr class="tot"><td class="lbl" colspan="'+csl+'">DIFERENÇA MENSAL</td><td colspan="'+natual+'" style="background:var(--bg-hover)"></td>'
      +cols.map((c,j)=>'<td>'+N((c.difm===''||c.difm==null?'':c.difm),'difm:'+j)+'</td>').join('')+'</tr>';
    h+='<tr class="tot"><td class="lbl" colspan="'+csl+'">DIFERENÇA ANUAL</td><td colspan="'+natual+'" style="background:var(--bg-hover)"></td>'
      +cols.map((c,j)=>'<td>'+N((c.difa===''||c.difa==null?'':c.difa),'difa:'+j)+'</td>').join('')+'</tr>';
    h+='</tbody></table></div>';
    el.innerHTML=h;
    lcGrowAll(el);
    lcRecomputeRen(t);
  }

  function lcRecomputeRen(t){
    snap(t); const st=S(t); const el=document.getElementById(t); if(!el)return;
    const iofEl=document.getElementById('lc-iof-'+t); const iof=iofEl?pPct(iofEl.value):0;
    const set=(sel,v)=>{ const e=el.querySelector('[data-out="'+sel+'"]'); if(e)e.textContent=v; };
    const ipf=el.querySelector('[data-out="iofp"]'); if(ipf) ipf.textContent=(iof?iof.toLocaleString('pt-BR',{minimumFractionDigits:2}):'0')+'%';
    set('vt:0', st.faixas.reduce((a,f)=>a+(Number(f.v0)||0),0));
    set('vt:1', st.faixas.reduce((a,f)=>a+(Number(f.v1)||0),0));
    // ATUAL + reajuste (o % preenche o valor reajustado; pode sobrescrever à mão)
    const av=pn(st.atual.valor), reaj=pn(st.atual.reaj);
    const reajInput=el.querySelector('[data-lc="areajval"]');
    const pctSet=String(st.atual.reaj).trim()!=='' && reaj!==0;
    let areaj;
    if(pctSet){ areaj=av*(1+reaj/100); if(reajInput && document.activeElement!==reajInput){ reajInput.value=(areaj? String(areaj.toFixed(2)).replace('.',',') : ''); st.atual.reajval=reajInput.value; } }
    else { areaj=pn(st.atual.reajval); }
    set('atual', brl(av)); set('areaj_val2', brl(areaj));
    // Espelha os dados do plano ATUAL na coluna "Atual com reajuste" (mesmo plano)
    el.querySelectorAll('[data-mir]').forEach(m=>{ const k=m.getAttribute('data-mir').slice(1); m.value=(st.atual[k]!=null?st.atual[k]:''); });
    // Estimativas por coluna cotada (com 1 coluna de vidas, usa a VIDAS 1)
    const _nv=(Number(st.nvidas)===1?1:2);
    const est=st.cols.map(c=>{ let s=0; const vc=(_nv===1?0:c.vcol); st.faixas.forEach((f,i)=>{ const vv=(vc==1?Number(f.v1):Number(f.v0))||0; const val=(c.vals[i]===''||c.vals[i]==null?0:Number(c.vals[i])); s+=vv*val; }); return s; });
    est.forEach((s,j)=>{ set('est:'+j, brl(s)); set('iofv:'+j, brl(s*(iof/100))); });
    // Valor total de fatura: por coluna por padrão; soma por grupo se agrupado.
    { const g=lcGruposValidos(st, st.cols.length); let start=0; g.forEach(sz=>{ let s=0; for(let k=start;k<start+sz;k++) s+=est[k]; set('grp:'+start, brl(s*(1+iof/100))); start+=sz; }); }
  }

  // ── COPARTICIPAÇÃO: tabela editável (layout Interweg) ──
  const COP={ seg:'', labelHead:'CLASSIFICAÇÃO DE COPARTICIPAÇÃO', plans:[], labels:[], grid:[] };
  window.copBuild=function(tabelas){
    const t=(tabelas&&tabelas[0])||{};
    COP.seg=t.titulo||'';
    const colu=t.colunas||[]; COP.labelHead=colu[0]||'CLASSIFICAÇÃO DE COPARTICIPAÇÃO'; COP.plans=colu.slice(1);
    const lin=t.linhas||[]; COP.labels=lin.map(r=>r[0]||''); COP.grid=lin.map(r=>{ const v=r.slice(1); while(v.length<COP.plans.length)v.push(''); return v; });
    if(!COP.plans.length){ COP.plans=['Plano']; COP.grid=COP.labels.map(()=>['']); }
    copRender();
    if(typeof apSaveState==='function') apSaveState();
  };
  function copSnap(){ const el=document.getElementById('ap-out-cop'); if(!el)return;
    el.querySelectorAll('[data-cop]').forEach(inp=>{ const p=inp.getAttribute('data-cop').split(':'); const k=p[0];
      if(k==='lh') COP.labelHead=inp.value;
      else if(k==='ph') COP.plans[+p[1]]=inp.value;
      else if(k==='lb') COP.labels[+p[1]]=inp.value;
      else if(k==='cell'){ (COP.grid[+p[1]]=COP.grid[+p[1]]||[])[+p[2]]=inp.value; }
    });
    if(typeof apSaveState==='function') apSaveState();
  }
  window.copReset=function(){ window.apConfirm('Tem certeza que você deseja excluir o estudo gerado? Esta ação não pode ser desfeita.', function(){ COP.seg='';COP.plans=[];COP.labels=[];COP.grid=[]; const el=document.getElementById('ap-out-cop'); if(el) el.innerHTML='<div class="ap-empty">Suba o arquivo de coparticipação da seguradora — a IA extrai a tabela aqui, editável.</div>'; if(typeof apSaveState==='function') apSaveState(); }); };
  window.copAddRow=function(){ copSnap(); COP.labels.push(''); COP.grid.push(COP.plans.map(()=>'')); copRender(); };
  window.copDelRow=function(i){ copSnap(); COP.labels.splice(i,1); COP.grid.splice(i,1); copRender(); };
  window.copAddCol=function(){ copSnap(); COP.plans.push(''); COP.grid.forEach(r=>r.push('')); copRender(); };
  window.copDelCol=function(j){ copSnap(); COP.plans.splice(j,1); COP.grid.forEach(r=>r.splice(j,1)); copRender(); };
  function copRender(){
    const el=document.getElementById('ap-out-cop'); if(!el)return;
    const I=(v,dl,ph,style)=>'<input data-cop="'+dl+'" value="'+esc(v==null?'':v)+'" placeholder="'+esc(ph||'')+'" style="'+(style||'')+'">';
    let h='<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 10px">'
      +'<div class="page-title" style="font-size:15px">Tabela de coparticipação (editável)</div>'
      +'<button class="btn btn-sm" style="background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff" onclick="copAddCol()"><span class="material-symbols-rounded" style="font-size:15px">add</span> Plano</button>'
      +'<button class="btn btn-outline btn-sm" onclick="copAddRow()"><span class="material-symbols-rounded" style="font-size:15px">add</span> Linha</button>'
      +'<button class="btn btn-outline btn-sm" onclick="copReset()"><span class="material-symbols-rounded" style="font-size:15px">delete_sweep</span> Limpar</button></div>';
    h+='<div class="lc-wrap"><table class="lc"><tbody>';
    h+='<tr class="tot"><td>'+I(COP.labelHead,'lh','CLASSIFICAÇÃO DE COPARTICIPAÇÃO','text-align:center;font-weight:800')+'</td>'
      +COP.plans.map((p,j)=>'<td>'+I(p,'ph:'+j,'Plano','text-align:center;font-weight:800')+' <span onclick="copDelCol('+j+')" title="remover" style="cursor:pointer;color:#fff;font-weight:700">×</span></td>').join('')+'</tr>';
    COP.labels.forEach((lb,i)=>{
      h+='<tr class="faixa"><td>'+I(lb,'lb:'+i,'Procedimento')+' <span onclick="copDelRow('+i+')" title="remover" style="cursor:pointer;color:#fff;font-weight:700">×</span></td>'
        +COP.plans.map((p,j)=>'<td>'+I((COP.grid[i]&&COP.grid[i][j])||'','cell:'+i+':'+j,'R$ 0,00','text-align:center')+'</td>').join('')+'</tr>';
    });
    h+='</tbody></table></div>';
    el.innerHTML=h;
  }

  // ── Persistência: guarda o comparativo no navegador para não perder no reload ──
  const AP_STORE_KEY = 'apTesteEstado_v1';
  function apSaveState(){
    try{
      const io1=document.getElementById('lc-iof-ap-cn-layout');
      const io2=document.getElementById('lc-iof-ap-ren-layout');
      const data={ LC:LC, COP:COP, iof_cn: io1?io1.value:'', iof_ren: io2?io2.value:'' };
      localStorage.setItem(AP_STORE_KEY, JSON.stringify(data));
    }catch(e){}
  }
  window.apSaveState = apSaveState;
  function apLoadState(){
    let s=null;
    try{ s=JSON.parse(localStorage.getItem(AP_STORE_KEY)||'null'); }catch(e){ s=null; }
    if(!s) return false;
    try{ if(s.LC) Object.keys(s.LC).forEach(k=>{ LC[k]=s.LC[k]; }); }catch(e){}
    try{ if(s.COP) Object.keys(s.COP).forEach(k=>{ COP[k]=s.COP[k]; }); }catch(e){}
    const io1=document.getElementById('lc-iof-ap-cn-layout'); if(io1 && s.iof_cn!=null) io1.value=s.iof_cn;
    const io2=document.getElementById('lc-iof-ap-ren-layout'); if(io2 && s.iof_ren!=null) io2.value=s.iof_ren;
    try{ if(LC['ap-cn-layout'] && LC['ap-cn-layout'].cols && LC['ap-cn-layout'].cols.length) window.lcRender('ap-cn-layout'); }catch(e){}
    try{ lcRenderRen('ap-ren-layout'); }catch(e){}
    try{ if(COP && COP.plans && COP.plans.length && (COP.labels.length || COP.seg)) copRender(); }catch(e){}
    return true;
  }

  // ── Salvar / abrir projeto (estudo do comparativo) no servidor ──
  function apColetarEstado(){
    try{ snap('ap-cn-layout'); }catch(e){}
    try{ snap('ap-ren-layout'); }catch(e){}
    try{ copSnap(); }catch(e){}
    const io1=document.getElementById('lc-iof-ap-cn-layout'), io2=document.getElementById('lc-iof-ap-ren-layout');
    return { LC:LC, COP:COP, iof_cn: io1?io1.value:'', iof_ren: io2?io2.value:'' };
  }
  function apAplicarEstado(s){
    if(!s) return;
    Object.keys(LC).forEach(k=>delete LC[k]);
    if(s.LC) Object.keys(s.LC).forEach(k=>{ LC[k]=s.LC[k]; });
    if(s.COP) Object.keys(s.COP).forEach(k=>{ COP[k]=s.COP[k]; });
    const io1=document.getElementById('lc-iof-ap-cn-layout'); if(io1) io1.value=s.iof_cn||'';
    const io2=document.getElementById('lc-iof-ap-ren-layout'); if(io2) io2.value=s.iof_ren||'';
    try{ if(LC['ap-cn-layout']&&LC['ap-cn-layout'].cols&&LC['ap-cn-layout'].cols.length) window.lcRender('ap-cn-layout'); else { const el=document.getElementById('ap-cn-layout'); if(el) el.innerHTML='<div class="ap-empty">Suba as cotações acima. Cada uma vira uma coluna nesta tabela editável.</div>'; } }catch(e){}
    try{ lcRenderRen('ap-ren-layout'); }catch(e){}
    try{ if(COP.plans&&COP.plans.length&&(COP.labels.length||COP.seg)) copRender(); else { const el=document.getElementById('ap-out-cop'); if(el) el.innerHTML='<div class="ap-empty">Suba a planilha para ver os valores aqui.</div>'; } }catch(e){}
    if(typeof apSaveState==='function') apSaveState();
  }
  function apProjMsg(m){ const e=document.getElementById('ap-proj-msg'); if(e) e.textContent=m||''; }
  window.apEstudoListar=async function(selId){
    try{ const r=await fetch('api_apresentacao.php?action=estudo_listar').then(x=>x.json());
      const sel=document.getElementById('ap-proj-sel'); if(!sel) return;
      const arr=(r&&r.data)||[];
      sel.innerHTML='<option value="">Abrir projeto salvo...</option>'+arr.map(p=>'<option value="'+p.id+'">'+esc(p.nome)+(p.usuario?(' ('+esc(p.usuario)+')'):'')+'</option>').join('');
      if(selId) sel.value=String(selId);
    }catch(e){}
  };
  window.apEstudoSalvar=async function(btn){
    const nome=(document.getElementById('ap-proj-nome').value||'').trim();
    if(!nome){ apProjMsg('Dê um nome ao projeto.'); const n=document.getElementById('ap-proj-nome'); if(n)n.focus(); return; }
    // Salva SEMPRE por nome: nome novo = projeto novo; nome igual = sobrescreve aquele.
    if(btn) btn.disabled=true; apProjMsg('Salvando...');
    try{
      const r=await fetch('api_apresentacao.php?action=estudo_salvar',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({id:0, nome:nome, dados:apColetarEstado()})}).then(x=>x.json());
      if(!r||!r.ok){ apProjMsg((r&&r.msg)||'Erro ao salvar'); return; }
      document.getElementById('ap-proj-id').value=r.id;
      await window.apEstudoListar(r.id);
      const ncn=((LC['ap-cn-layout']||{}).cols||[]).length, nren=((LC['ap-ren-layout']||{}).cols||[]).length;
      apProjMsg('Projeto "'+nome+'" salvo (Conta Nova: '+ncn+' col., Renovação: '+nren+' col.).');
    }catch(e){ apProjMsg('Erro de conexão'); } finally{ if(btn) btn.disabled=false; }
  };
  window.apEstudoAbrir=async function(){
    const sel=document.getElementById('ap-proj-sel'); const id=sel?sel.value:'';
    if(!id){ apProjMsg('Escolha um projeto na lista.'); return; }
    apProjMsg('Abrindo...');
    try{
      const r=await fetch('api_apresentacao.php?action=estudo_carregar&id='+encodeURIComponent(id)).then(x=>x.json());
      if(!r||!r.ok){ apProjMsg((r&&r.msg)||'Erro ao abrir'); return; }
      let dados=null; try{ dados=typeof r.dados==='string'?JSON.parse(r.dados||'null'):r.dados; }catch(e){ dados=null; }
      apAplicarEstado(dados);
      document.getElementById('ap-proj-id').value=r.id;
      document.getElementById('ap-proj-nome').value=r.nome||'';
      const ncn=((LC['ap-cn-layout']||{}).cols||[]).length, nren=((LC['ap-ren-layout']||{}).cols||[]).length;
      apProjMsg('Projeto "'+(r.nome||'')+'" aberto (Conta Nova: '+ncn+' col., Renovação: '+nren+' col.).');
    }catch(e){ apProjMsg('Erro de conexão'); }
  };
  window.apEstudoExcluir=async function(){
    const sel=document.getElementById('ap-proj-sel'); const id=sel?sel.value:'';
    if(!id){ apProjMsg('Escolha um projeto na lista.'); return; }
    const nome=sel.options[sel.selectedIndex]?sel.options[sel.selectedIndex].textContent:'';
    window.apConfirm('Excluir o projeto "'+nome+'"? Esta ação não pode ser desfeita.', async function(){
      try{ const r=await fetch('api_apresentacao.php?action=estudo_excluir',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id})}).then(x=>x.json());
        if(!r||!r.ok){ apProjMsg((r&&r.msg)||'Erro ao excluir'); return; }
        if(String(id)===document.getElementById('ap-proj-id').value){ document.getElementById('ap-proj-id').value=''; }
        await window.apEstudoListar(); apProjMsg('Projeto excluído.');
      }catch(e){ apProjMsg('Erro de conexão'); }
    });
  };
  window.apEstudoNovo=function(){
    window.apConfirm('Começar um projeto novo? A tela atual será limpa (o que não estiver salvo se perde).', function(){
      document.getElementById('ap-proj-id').value='';
      document.getElementById('ap-proj-nome').value='';
      apAplicarEstado({LC:{},COP:{seg:'',labelHead:'CLASSIFICAÇÃO DE COPARTICIPAÇÃO',plans:[],labels:[],grid:[]},iof_cn:'0',iof_ren:'0'});
      apProjMsg('Projeto novo.');
    });
  };

  // ── Exportar o comparativo da aba ativa (PPT / PDF / Imprimir) ──
  const AP_PPTX_CDN='https://cdn.jsdelivr.net/npm/pptxgenjs@3.12.0/dist/pptxgen.bundle.min.js';
  const AP_H2C_CDN='https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
  const AP_JSPDF_CDN='https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
  function apLoadScript(src){ return new Promise((res,rej)=>{ if(document.querySelector('script[src="'+src+'"]')){ res(); return; } const s=document.createElement('script'); s.src=src; s.onload=res; s.onerror=()=>rej(new Error('Falha ao carregar '+src)); document.head.appendChild(s); }); }
  function apExpMsg(m){ const e=document.getElementById('ap-export-msg'); if(e) e.textContent=m||''; }
  function apAlvoExport(){
    const pane=document.querySelector('.ap-pane.active'); if(!pane) return null;
    // Captura a TABELA inteira (não o .lc-wrap, que corta na largura visível)
    return pane.querySelector('table.lc') || null;
  }
  async function apCapturar(){
    const src=apAlvoExport();
    if(!src){ apExpMsg('Nada para exportar nesta aba. Suba uma cotação primeiro.'); return null; }
    apExpMsg('Gerando imagem...');
    if(!window.html2canvas) await apLoadScript(AP_H2C_CDN);
    // Clona a tabela num container fora da tela, com largura natural (evita corte por scroll/overflow)
    const holder=document.createElement('div');
    holder.style.cssText='position:fixed;left:-100000px;top:0;z-index:-1;background:#fff;width:max-content;padding:10px;box-sizing:content-box';
    const clone=src.cloneNode(true);
    clone.style.width='max-content'; clone.style.minWidth='0';
    // Troca os campos (input/textarea/select) por TEXTO — o html2canvas corta o texto
    // dentro de inputs; como div, renderiza certinho e com o mesmo alinhamento.
    const sf=src.querySelectorAll('input,textarea,select'), cf=clone.querySelectorAll('input,textarea,select');
    for(let i=0;i<cf.length;i++){ const f=cf[i], s=sf[i]; if(!f || !f.parentNode) continue;
      let val=''; const cs=s?window.getComputedStyle(s):null;
      if(s){ val = (s.tagName==='SELECT') ? ((s.options[s.selectedIndex]||{}).textContent||'') : (s.value||''); }
      const d=document.createElement('div');
      d.textContent=val;
      d.style.cssText='display:flex;align-items:center;justify-content:'+((cs&&cs.textAlign==='right')?'flex-end':(cs&&cs.textAlign==='left'?'flex-start':'center'))
        +';min-height:16px;text-align:'+(cs?cs.textAlign:'center')
        +';color:'+(cs?cs.color:'#000')+';font-family:'+(cs?cs.fontFamily:'inherit')
        +';font-size:'+(cs?cs.fontSize:'12px')+';font-weight:'+(cs?cs.fontWeight:'400')
        +';line-height:1.25;white-space:pre-wrap;word-break:break-word;padding:1px 4px';
      f.parentNode.replaceChild(d, f);
    }
    holder.appendChild(clone); document.body.appendChild(holder);
    const rect=holder.getBoundingClientRect();
    const fullW=Math.ceil(rect.width)+4, fullH=Math.ceil(rect.height)+4;
    let canvas=null;
    try{
      // Captura o HOLDER (tabela + folga de 10px) pra não cortar nenhuma borda
      canvas=await window.html2canvas(holder,{scale:2,backgroundColor:'#ffffff',width:fullW,height:fullH,windowWidth:fullW+40,windowHeight:fullH+40,scrollX:0,scrollY:0});
    } finally { try{ document.body.removeChild(holder); }catch(e){} }
    return canvas;
  }
  window.apImprimir=async function(btn){ try{ if(btn)btn.disabled=true;
    const c=await apCapturar(); if(!c){ if(btn)btn.disabled=false; return; }
    apExpMsg('');
    const img=c.toDataURL('image/png');
    const w=window.open('','_blank'); if(!w){ alert('Permita pop-ups para imprimir.'); if(btn)btn.disabled=false; return; }
    w.document.write('<html><head><title>Comparativo</title><style>@page{size:landscape;margin:8mm}html,body{margin:0}img{width:100%;height:auto;display:block}</style></head><body><img src="'+img+'"><scr'+'ipt>window.onafterprint=function(){window.close();};window.onload=function(){setTimeout(function(){window.print();},250);};</scr'+'ipt></body></html>');
    w.document.close();
  }catch(e){ apExpMsg('Erro: '+e.message); } finally{ if(btn)btn.disabled=false; } };
  window.apBaixarPDF=async function(btn){ try{ if(btn)btn.disabled=true;
    const c=await apCapturar(); if(!c){ if(btn)btn.disabled=false; return; }
    apExpMsg('Montando PDF...');
    if(!(window.jspdf&&window.jspdf.jsPDF)) await apLoadScript(AP_JSPDF_CDN);
    const jsPDF=window.jspdf.jsPDF;
    const land=c.width>=c.height;
    const pdf=new jsPDF({orientation:land?'landscape':'portrait',unit:'pt',format:'a4'});
    const pw=pdf.internal.pageSize.getWidth(), ph=pdf.internal.pageSize.getHeight(), m=18;
    const r=Math.min((pw-2*m)/c.width,(ph-2*m)/c.height);
    pdf.addImage(c.toDataURL('image/png'),'PNG',(pw-c.width*r)/2,m,c.width*r,c.height*r);
    pdf.save('Comparativo.pdf'); apExpMsg('');
  }catch(e){ apExpMsg('Erro: '+e.message); } finally{ if(btn)btn.disabled=false; } };
  window.apBaixarPPT=async function(btn){ try{ if(btn)btn.disabled=true;
    const c=await apCapturar(); if(!c){ if(btn)btn.disabled=false; return; }
    apExpMsg('Montando PPT...');
    if(!window.PptxGenJS) await apLoadScript(AP_PPTX_CDN);
    const pptx=new window.PptxGenJS(); pptx.defineLayout({name:'WIDE',width:13.333,height:7.5}); pptx.layout='WIDE';
    const slide=pptx.addSlide(); slide.background={color:'FFFFFF'};
    const iwIn=c.width/192, ihIn=c.height/192; // escala ~2x -> /192
    const maxW=12.6, maxH=6.9; const r=Math.min(maxW/iwIn, maxH/ihIn);
    const w=iwIn*r, hh=ihIn*r;
    slide.addImage({data:c.toDataURL('image/png'), x:(13.333-w)/2, y:(7.5-hh)/2, w:w, h:hh});
    await pptx.writeFile({fileName:'Comparativo.pptx'}); apExpMsg('');
  }catch(e){ apExpMsg('Erro: '+e.message); } finally{ if(btn)btn.disabled=false; } };

  document.addEventListener('input', function(e){
    ['ap-cn-layout','ap-ren-layout'].forEach(function(t){ const el=document.getElementById(t); if(el && el.contains(e.target)) window.lcRecompute(t); });
    const cop=document.getElementById('ap-out-cop'); if(cop && cop.contains(e.target)){ try{ copSnap(); }catch(_){ } apSaveState(); }
  });

  // Restaura o que estava salvo; se não houver, só deixa a Renovação pronta.
  const _apRestaurou = apLoadState();
  if(!_apRestaurou){ try{ if(document.getElementById('ap-ren-layout')) lcRenderRen('ap-ren-layout'); }catch(e){} }
  // Carrega a lista de projetos salvos no servidor
  if(typeof window.apEstudoListar==='function') window.apEstudoListar();
})();
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
