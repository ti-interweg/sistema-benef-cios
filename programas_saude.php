<?php
$currentPage = 'programas_saude';
$pageTitle   = 'Interbaby / Intercuidado — Interweg Benefícios';
$pageScripts = [];
require_once __DIR__ . '/_header.php';
?>
<style>
  .pg-wrap{width:100%}
  .pg-scroll{width:100%;max-width:100%;overflow-x:auto}
  .pg-tabs{display:flex;gap:6px;border-bottom:1px solid var(--border);margin:6px 0 14px}
  .pg-tab{padding:9px 18px;border:none;background:none;cursor:pointer;font-weight:700;font-size:14px;color:var(--muted);border-bottom:3px solid transparent;margin-bottom:-1px}
  .pg-tab.active{color:var(--accent);border-bottom-color:var(--accent)}
  .pg-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 12px}
  .pg-toolbar .ctl{padding:8px 11px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg-card);color:var(--text)}
  .pg-toolbar input,.pg-toolbar select{width:auto;max-width:100%;box-sizing:border-box}
  #pg-fkit{min-width:170px}
  #pg-comp{width:160px}
  #pg-table td{vertical-align:top}
  .kit-badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:10.5px;font-weight:800}
  .kit-ok{background:#d3f2d8;color:#256029}
  .kit-pend{background:#fef3c7;color:#92400e}
  .pg-ov{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:flex-start;justify-content:center;z-index:9999;overflow:auto;padding:30px 12px}
  .pg-ov.show{display:flex}
  .pg-modal{background:var(--bg-card);border-radius:14px;padding:22px;width:860px;max-width:96vw}
  .pg-modal .fg{margin-bottom:12px}
  .pg-modal label{display:block;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.02em;margin-bottom:4px}
  .pg-modal input,.pg-modal select,.pg-modal textarea{width:100%;padding:8px 11px;font-size:13px;border:1px solid var(--border);border-radius:8px;background:var(--bg-card);color:var(--text);box-sizing:border-box}
  .pg-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
  .pg-grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  /* ── Polimento visual da listagem ── */
  #pg-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
  @media(max-width:1100px){ #pg-cards{grid-template-columns:repeat(2,1fr)} }
  .table-wrap{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04)}
  .table-head{padding:12px 16px;border-bottom:1px solid var(--border);background:var(--bg-hover,#f7f9fa)}
  .table-title{font-weight:800;font-size:13.5px;color:var(--text)}
  #pg-table{width:100%;border-collapse:collapse;font-size:12.8px}
  #pg-table thead th{position:sticky;top:0;background:var(--bg-hover,#f1f4f6);color:var(--muted);text-transform:uppercase;font-size:10.5px;letter-spacing:.04em;font-weight:800;text-align:left;padding:9px 12px;border-bottom:2px solid var(--border);white-space:nowrap;z-index:1}
  #pg-table tbody td{padding:10px 12px;border-bottom:1px solid var(--border)}
  #pg-table tbody tr:nth-child(even){background:rgba(0,0,0,.015)}
  #pg-table tbody tr:hover{background:rgba(0,191,179,.07)}
  .tscroll{max-height:calc(100vh - 340px);overflow:auto}
</style>

<main class="main">
<div class="pg-wrap">
  <div class="page-header">
    <div>
      <div class="page-title">Programas de Saúde</div>
      <div class="page-sub">Acompanhamento Interbaby (gestantes) e Intercuidado</div>
    </div>
  </div>

  <div class="pg-tabs">
    <button class="pg-tab active" data-p="Interbaby" onclick="pgTab('Interbaby')">Interbaby</button>
    <button class="pg-tab" data-p="Intercuidado" onclick="pgTab('Intercuidado')">Intercuidado</button>
  </div>

  <div class="cards" id="pg-cards" style="margin:16px 0 4px">
    <div class="card c-teal"><div class="card-label">Total de participantes</div><div class="card-val" id="pgc-total">—</div><div class="card-sub" id="pgc-total-sub">no programa</div><div class="card-icon"><span class="material-symbols-rounded">groups</span></div></div>
    <div class="card c-green" id="pgc-card-env"><div class="card-label">Kit enviado</div><div class="card-val" id="pgc-env">—</div><div class="card-sub">entregues · <strong id="pgc-env-pct" style="color:#3fb950">0%</strong></div><div class="card-icon"><span class="material-symbols-rounded">check_circle</span></div></div>
    <div class="card c-yellow" id="pgc-card-pend"><div class="card-label">Kit pendente</div><div class="card-val" id="pgc-pend">—</div><div class="card-sub">a enviar · <strong id="pgc-pend-pct" style="color:#d29922">0%</strong></div><div class="card-icon"><span class="material-symbols-rounded">local_shipping</span></div></div>
    <div class="card c-blue" id="pgc-card-prox"><div class="card-label">Próx. contato (30 dias)</div><div class="card-val" id="pgc-prox">—</div><div class="card-sub">agendados</div><div class="card-icon"><span class="material-symbols-rounded">event_upcoming</span></div></div>
  </div>

  <div class="pg-toolbar">
    <label style="font-size:13px;color:var(--muted)">Competência
      <input type="month" id="pg-comp" class="ctl" onchange="pgLoad()" style="margin-left:6px">
    </label>
    <input type="text" id="pg-busca" class="ctl" placeholder="Buscar nome / empresa..." oninput="pgRender()" style="width:240px">
    <select id="pg-fkit" class="ctl" onchange="pgRender()" title="Filtrar pelo Kit">
      <option value="">Kit: todos</option>
      <option value="pend">Kit pendente (Enviar)</option>
      <option value="env">Kit enviado</option>
    </select>
    <button class="btn btn-outline btn-sm" onclick="pgBaixar()"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">download</span> Baixar</button>
    <button class="btn btn-outline btn-sm" onclick="document.getElementById('pg-file').click()"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">upload</span> Importar</button>
    <input type="file" id="pg-file" accept=".xlsx,.xls" style="display:none" onchange="pgImportarFile(this)">
    <button class="btn btn-outline btn-sm" onclick="pgVirarMes()" title="Avança a competência e cria a evolução do mês seguinte"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">event_repeat</span> Virar mês →</button>
    <button class="btn btn-primary btn-sm" onclick="pgNovo()"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">add</span> Novo</button>
    <span id="pg-resumo" style="font-size:12px;color:var(--muted);margin-left:auto"></span>
  </div>

  <div class="table-wrap">
    <div class="table-head"><div class="table-title" id="pg-count">Participantes</div></div>
    <div class="tscroll">
      <table id="pg-table"><thead id="pg-thead"></thead><tbody id="pg-tbody"></tbody></table>
    </div>
  </div>
</div>
</main>

<!-- Modal participante -->
<div class="pg-ov" id="pg-mod" onclick="if(event.target===this)pgModClose()">
  <div class="pg-modal">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
      <h3 id="pg-mod-titulo" style="margin:0;color:var(--accent)">Novo participante</h3>
      <button class="btn btn-outline btn-sm" onclick="pgModClose()"><span class="material-symbols-rounded" style="font-size:16px">close</span></button>
    </div>
    <input type="hidden" id="f-id"><input type="hidden" id="f-programa">
    <div class="pg-grid">
      <div class="fg"><label>Nome *</label><input id="f-nome" type="text"></div>
      <div class="fg"><label>Empresa / Grupo</label><input id="f-empresa" type="text"></div>
      <div class="fg"><label>Telefone</label><input id="f-telefone" type="text" inputmode="tel" placeholder="(31) 99999-9999" oninput="if(window.fmtTel)this.value=fmtTel(this.value)"></div>
      <div class="fg"><label>Próximo contato</label><input id="f-proximo_contato" type="date"></div>
    </div>
    <div class="pg-grid" id="pg-campos-baby">
      <div class="fg"><label>Data de nascimento</label><input id="f-dn" type="date"></div>
      <div class="fg"><label>Id gestacional (semanas)</label><input id="f-id_gestacional" type="text" placeholder="Ex: 22 semanas"></div>
      <div class="fg"><label>DPP (parto previsto)</label><input id="f-dpp" type="date"></div>
      <div class="fg"><label>Nome do bebê</label><input id="f-nome_bebe" type="text"></div>
      <div class="fg"><label>Histórico</label><input id="f-historico" type="text" placeholder="Ex: G1P0A0"></div>
      <div class="fg"><label>Ativ. física</label><input id="f-ativ_fisica" type="text"></div>
      <div class="fg"><label>Alimentação</label><input id="f-alimentacao" type="text"></div>
      <div class="fg"><label>Parto / Maternidade</label><input id="f-maternidade" type="text"></div>
      <div class="fg"><label>Alta</label><input id="f-alta" type="text"></div>
    </div>
    <div class="fg"><label>Comorbidade</label><textarea id="f-comorbidade" rows="2" placeholder="Ex: Hipertensão, diabetes..."></textarea></div>
    <div class="pg-grid2">
      <div class="fg"><label>Medicações</label><textarea id="f-medicacoes" rows="2"></textarea></div>
      <div class="fg" id="pg-fg-kit"><label>Kit</label><select id="f-kit"><option value="">—</option><option value="Enviar">Enviar (pendente)</option><option value="Enviado">Enviado</option></select></div>
    </div>
    <!-- Termo de consentimento (LGPD) — só Interbaby, só ao editar participante salvo -->
    <div id="pg-termos" style="display:none;margin-top:14px;border-top:1px solid var(--border);padding-top:14px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px">
        <div style="font-weight:800;font-size:13px;color:var(--text);display:flex;align-items:center;gap:6px">
          <span class="material-symbols-rounded" style="font-size:18px;color:var(--accent)">verified_user</span> Termo de consentimento (LGPD)
        </div>
        <a href="assets/termo_consentimento_modelo.docx" download class="btn btn-outline btn-sm" style="text-decoration:none"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">description</span> Baixar modelo</a>
      </div>
      <div id="pg-termo-novo">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;background:var(--bg-hover,#f7f9fa);padding:10px 12px;border-radius:10px">
          <input type="file" id="pg-termo-file" accept=".pdf,.jpg,.jpeg,.png" style="font-size:12.5px;flex:1 1 240px">
          <button class="btn btn-primary btn-sm" id="pg-termo-btn" onclick="pgTermoUpload(this)"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">upload_file</span> Anexar termo</button>
          <span style="font-size:11px;color:var(--muted);width:100%">PDF, JPG ou PNG · até 5 MB · guarda o histórico de vias enviadas.</span>
        </div>
      </div>
      <div id="pg-termo-lista" style="margin-top:10px"></div>
      <div id="pg-termo-aviso" style="display:none;font-size:12.5px;color:var(--muted);background:var(--bg-hover,#f7f9fa);padding:10px 12px;border-radius:10px">Salve o participante primeiro para anexar o termo.</div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px">
      <button class="btn btn-outline" onclick="pgModClose()">Cancelar</button>
      <button class="btn btn-primary" id="pg-btn-salvar" onclick="pgSalvar(this)"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar</button>
    </div>
  </div>
</div>

<!-- Modal evolução -->
<div class="pg-ov" id="pg-evo" onclick="if(event.target===this)pgEvoClose()">
  <div class="pg-modal" style="width:860px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
      <h3 id="pg-evo-titulo" style="margin:0;color:var(--accent)">Evolução</h3>
      <button class="btn btn-outline btn-sm" onclick="pgEvoClose()"><span class="material-symbols-rounded" style="font-size:16px">close</span></button>
    </div>
    <input type="hidden" id="evo-pid">
    <div class="fg"><label>Nova nota <span id="evo-comp-lbl" style="color:var(--accent)"></span> <span style="color:var(--muted);font-weight:400;text-transform:none">(adiciona ao histórico, não apaga as anteriores)</span></label>
      <textarea id="evo-nota" rows="14" placeholder="Evolução do mês..." style="min-height:300px;resize:vertical;line-height:1.5"></textarea></div>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin:6px 0 16px">
      <button class="btn btn-primary" id="pg-evo-salvar" onclick="pgEvoSalvar(this)"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar nota</button>
    </div>
    <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:6px">Histórico</div>
    <div id="evo-hist" style="max-height:240px;overflow:auto;font-size:12.5px"></div>
  </div>
</div>

<script>
(function(){
  const esc = s => { const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; };
  const norm = s => String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').trim();
  const $ = id => document.getElementById(id);
  const apiGet = (a,p={})=>fetch('api_programas.php?'+new URLSearchParams({action:a,...p})).then(r=>r.json());
  const apiPost = (a,b)=>fetch('api_programas.php?action='+a,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b||{})}).then(r=>r.json());
  const fdate = s => { const m=String(s||'').match(/^(\d{4})-(\d{2})-(\d{2})/); return m?(m[3]+'/'+m[2]+'/'+m[1]):''; };
  const clean = v => { const s=String(v==null?'':v).trim(); return (s==='—'||s==='-'||s==='–'||s==='N/A'||s.toUpperCase()==='SEM RESP')?'':s; };
  const telFmt = v => { const s=clean(v); if(!s) return ''; return (window.fmtTel?fmtTel(s):s); };
  const CAMPOS_BABY = ['dn','id_gestacional','dpp','nome_bebe','historico','ativ_fisica','alimentacao','maternidade','alta'];
  let _prog='Interbaby', _data=[], _comp='';

  window.pgTab = function(p){
    _prog=p;
    document.querySelectorAll('.pg-tab').forEach(b=>b.classList.toggle('active', b.dataset.p===p));
    pgLoad();
  };

  window.pgLoad = async function(){
    _comp=($('pg-comp').value)||'';
    if(!_comp){ const d=new Date(); _comp=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0'); $('pg-comp').value=_comp; }
    $('pg-tbody').innerHTML='<tr><td colspan="11" style="text-align:center;color:var(--muted);padding:30px">Carregando…</td></tr>';
    try{ const r=await apiGet('prog_listar',{programa:_prog,competencia:_comp}); _data=(r&&r.ok)?r.data:[]; }catch(e){ _data=[]; }
    // Limpa travessões "—" (e afins) de todos os campos de texto
    _data.forEach(row=>{ Object.keys(row).forEach(k=>{ if(typeof row[k]==='string'){ const c=clean(row[k]); if(c!==row[k]) row[k]=c; } }); });
    pgRender();
  };

  function kitBadge(k){ const s=(k||'').toLowerCase();
    if(s.indexOf('enviad')>=0) return '<span class="kit-badge kit-ok">Enviado</span>';
    if(s.indexOf('enviar')>=0) return '<span class="kit-badge kit-pend">Enviar</span>';
    return '';
  }
  function kitStatus(k){ const s=(k||'').toLowerCase(); if(s.indexOf('enviad')>=0) return 'env'; if(s.indexOf('enviar')>=0) return 'pend'; return ''; }

  window.pgRender = function(){
    const baby=(_prog==='Interbaby');
    const fkEl=$('pg-fkit'); if(fkEl) fkEl.style.display = baby?'':'none';
    const q=norm($('pg-busca').value||''); const fk = baby ? ($('pg-fkit').value||'') : '';
    let rows=_data.filter(r=>!q||norm(r.nome).includes(q)||norm(r.empresa).includes(q)||norm(r.grupo).includes(q));
    if(fk) rows=rows.filter(r=>kitStatus(r.kit)===fk);
    // resumo + cards de dashboard
    const tot=_data.length, env=_data.filter(r=>kitStatus(r.kit)==='env').length, pend=_data.filter(r=>kitStatus(r.kit)==='pend').length;
    const re=$('pg-resumo'); if(re) re.innerHTML='';
    // próximos contatos nos próximos 30 dias
    const hojeD=new Date(); const limite=new Date(); limite.setDate(limite.getDate()+30);
    const prox=_data.filter(r=>{ const d=r.proximo_contato||r.prox_contato; if(!d) return false; const dt=new Date(String(d).slice(0,10)); return dt>=new Date(hojeD.toISOString().slice(0,10)) && dt<=limite; }).length;
    const sv=(id,v)=>{ const el=$(id); if(el) el.textContent=v; };
    sv('pgc-total', tot);
    sv('pgc-env', env); sv('pgc-pend', pend); sv('pgc-prox', prox);
    const pe=$('pgc-env-pct'); if(pe) pe.textContent = tot? Math.round(env/tot*100)+'%':'0%';
    const pp=$('pgc-pend-pct'); if(pp) pp.textContent = tot? Math.round(pend/tot*100)+'%':'0%';
    // cards de kit só fazem sentido no Interbaby
    const ce=$('pgc-card-env'), cp=$('pgc-card-pend');
    if(ce) ce.style.display = baby?'':'none';
    if(cp) cp.style.display = baby?'':'none';
    const pc=$('pg-count'); if(pc) pc.textContent=_prog+': '+rows.length+' participante(s)';
    // cabeçalho (depende do programa)
    let th='<tr>'
      +'<th>Nome</th>'
      +'<th>Empresa/Grupo</th>'
      +'<th>Telefone</th>';
    if(baby) th+='<th>Id gest.</th><th>DPP</th><th>Kit</th>';
    th+='<th>Próx. contato</th><th>Nota do mês</th>'
      +'<th style="text-align:right">Ações</th></tr>';
    $('pg-thead').innerHTML=th;
    const ncol = baby?9:6;
    const hoje=new Date().toISOString().slice(0,10);
    if(!rows.length){ $('pg-tbody').innerHTML='<tr><td colspan="'+ncol+'" style="text-align:center;color:var(--muted);padding:30px">'+(_data.length?'Nenhum participante encontrado.':'Nenhum participante neste programa ainda.')+'</td></tr>'; return; }
    let h='';
    rows.forEach(r=>{
      const nota=clean(r.nota_mes);
      const temTermo=(+r.termos_qtd>0);
      const clip=temTermo?(' <span class="material-symbols-rounded" title="'+r.termos_qtd+' termo(s) anexado(s)" style="font-size:15px;vertical-align:middle;color:#3fb950">verified_user</span>'):'';
      h+='<tr>'
        +'<td><b>'+esc(clean(r.nome))+'</b>'+clip+'</td>'
        +'<td>'+esc(clean(r.empresa)||clean(r.grupo))+'</td>'
        +'<td style="white-space:nowrap">'+esc(telFmt(r.telefone))+'</td>';
      if(baby) h+='<td>'+esc(clean(r.id_gestacional))+'</td>'
        +'<td style="white-space:nowrap">'+fdate(r.dpp)+'</td>'
        +'<td>'+kitBadge(r.kit)+'</td>';
      const px=String(r.proximo_contato||'').slice(0,10);
      const pxOver = px && px < hoje;
      h+='<td style="white-space:nowrap'+(pxOver?';color:#c0392b;font-weight:700':'')+'" title="'+(pxOver?'Contato vencido':'')+'">'+(px?fdate(px):'')+'</td>';
      h+='<td><div style="max-height:160px;overflow:auto;color:var(--muted);white-space:pre-wrap;line-height:1.45" title="'+esc(nota)+'">'+esc(nota)+'</div></td>'
        +'<td style="text-align:right;white-space:nowrap">'
          +'<button class="btn btn-outline btn-sm" title="Evolução mês a mês" style="padding:4px 7px" onclick="pgEvoOpen('+r.id+')"><span class="material-symbols-rounded" style="font-size:15px">timeline</span></button> '
          +'<button class="btn btn-outline btn-sm" title="Editar" style="padding:4px 7px" onclick="pgEditar('+r.id+')"><span class="material-symbols-rounded" style="font-size:15px">edit</span></button> '
          +'<button class="btn btn-outline btn-sm" title="Excluir" style="padding:4px 7px" onclick="pgExcluir('+r.id+')"><span class="material-symbols-rounded" style="font-size:15px;color:#c0392b">delete</span></button>'
        +'</td></tr>';
    });
    $('pg-tbody').innerHTML=h;
  };

  // ── Incluir / Editar ──
  const FIELDS=['nome','empresa','telefone','proximo_contato','comorbidade','dn','id_gestacional','dpp','nome_bebe','historico','ativ_fisica','alimentacao','medicacoes','maternidade','kit','alta','endereco'];
  window.pgNovo = function(){
    $('pg-mod-titulo').textContent='Novo participante ('+_prog+')';
    $('f-id').value=''; $('f-programa').value=_prog;
    FIELDS.forEach(f=>{ const el=$('f-'+f); if(el) el.value=''; });
    $('pg-campos-baby').style.display=(_prog==='Interbaby')?'':'none';
    { const fk=$('pg-fg-kit'); if(fk) fk.style.display=(_prog==='Interbaby')?'':'none'; const en=$('pg-fg-end'); if(en) en.style.display=(_prog==='Interbaby')?'':'none'; }
    pgTermoToggle(0);
    $('pg-mod').classList.add('show');
  };
  window.pgEditar = function(id){
    const r=_data.find(x=>+x.id===id); if(!r) return;
    $('pg-mod-titulo').textContent='Editar ('+_prog+')';
    $('f-id').value=r.id; $('f-programa').value=_prog;
    FIELDS.forEach(f=>{ const el=$('f-'+f); if(el) el.value=(r[f]!=null?r[f]:''); });
    $('pg-campos-baby').style.display=(_prog==='Interbaby')?'':'none';
    { const fk=$('pg-fg-kit'); if(fk) fk.style.display=(_prog==='Interbaby')?'':'none'; const en=$('pg-fg-end'); if(en) en.style.display=(_prog==='Interbaby')?'':'none'; }
    pgTermoToggle(r.id);
    $('pg-mod').classList.add('show');
  };
  window.pgModClose = function(){ $('pg-mod').classList.remove('show'); };

  // ── Termo de consentimento (LGPD) ──
  function pgTermoToggle(pid){
    const sec=$('pg-termos'); if(!sec) return;
    const baby=(_prog==='Interbaby');
    sec.style.display = baby ? '' : 'none';
    if(!baby) return;
    const temId = (+pid>0);
    const fl=$('pg-termo-file'); if(fl) fl.value='';
    $('pg-termo-novo').style.display = temId ? '' : 'none';
    $('pg-termo-lista').style.display = temId ? '' : 'none';
    $('pg-termo-aviso').style.display = temId ? 'none' : '';
    if(temId) pgTermoListar(pid); else $('pg-termo-lista').innerHTML='';
  }
  window.pgTermoListar = async function(pid){
    const box=$('pg-termo-lista'); if(!box) return;
    box.innerHTML='<div style="color:var(--muted);font-size:12px">Carregando…</div>';
    try{
      const res=await apiGet('prog_termo_listar',{participante_id:pid});
      const rows=(res&&res.ok)?res.data:[];
      if(!rows.length){ box.innerHTML='<div style="color:var(--muted);font-size:12px">Nenhum termo anexado ainda.</div>'; return; }
      box.innerHTML = rows.map(t=>{
        const m=String(t.enviado_em||'').match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/);
        const quando=m?(m[3]+'/'+m[2]+'/'+m[1]+' '+m[4]):'';
        const kb=t.tamanho?(' · '+(t.tamanho>1048576?((t.tamanho/1048576).toFixed(1)+' MB'):(Math.round(t.tamanho/1024)+' KB'))):'';
        const quem=t.enviado_por?(' · '+esc(t.enviado_por)):'';
        const isPdf=(t.mime||'').indexOf('pdf')>=0;
        return '<div style="display:flex;align-items:center;gap:10px;border-top:1px solid var(--border);padding:8px 0">'
          +'<span class="material-symbols-rounded" style="font-size:20px;color:'+(isPdf?'#c0392b':'#2e7d9a')+'">'+(isPdf?'picture_as_pdf':'image')+'</span>'
          +'<div style="flex:1;min-width:0"><div style="font-weight:600;font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(t.nome_original||'termo')+'</div>'
          +'<div style="font-size:11px;color:var(--muted)">'+quando+kb+quem+'</div></div>'
          +'<a class="btn btn-outline btn-sm" style="text-decoration:none;padding:4px 7px" title="Abrir" target="_blank" href="api_programas.php?action=prog_termo_baixar&inline=1&id='+t.id+'"><span class="material-symbols-rounded" style="font-size:15px">visibility</span></a> '
          +'<a class="btn btn-outline btn-sm" style="text-decoration:none;padding:4px 7px" title="Baixar" href="api_programas.php?action=prog_termo_baixar&id='+t.id+'"><span class="material-symbols-rounded" style="font-size:15px">download</span></a> '
          +'<button class="btn btn-outline btn-sm" title="Excluir" style="padding:4px 7px" onclick="pgTermoExcluir('+t.id+','+pid+')"><span class="material-symbols-rounded" style="font-size:15px;color:#c0392b">delete</span></button>'
          +'</div>';
      }).join('');
    }catch(e){ box.innerHTML='<div style="color:var(--muted);font-size:12px">Erro ao carregar.</div>'; }
  };
  window.pgTermoUpload = async function(btn){
    const pid=+($('f-id').value||0);
    if(!(pid>0)){ alert('Salve o participante primeiro.'); return; }
    const fl=$('pg-termo-file'); const file=fl&&fl.files&&fl.files[0];
    if(!file){ alert('Selecione um arquivo (PDF, JPG ou PNG).'); return; }
    if(file.size>5*1024*1024){ alert('Arquivo acima de 5 MB.'); return; }
    const fd=new FormData(); fd.append('participante_id',pid); fd.append('arquivo',file);
    btn.disabled=true;
    try{
      const res=await fetch('api_programas.php?action=prog_termo_upload',{method:'POST',body:fd}).then(r=>r.json());
      if(!res||!res.ok){ alert((res&&res.msg)||'Erro no upload'); }
      else { if(fl) fl.value=''; pgTermoListar(pid); if(typeof toast==='function') toast('Termo anexado.'); }
    }catch(e){ alert('Falha de conexão'); }
    btn.disabled=false;
  };
  window.pgTermoExcluir = async function(id,pid){
    if(!await uiConfirm('Esta via do termo será removida do servidor.','Excluir termo?')) return;
    try{ const r=await apiPost('prog_termo_excluir',{id}); if(!r||!r.ok){ alert((r&&r.msg)||'Erro'); return; } pgTermoListar(pid); }
    catch(e){ alert('Falha de conexão'); }
  };
  window.pgSalvar = async function(btn){
    const nome=($('f-nome').value||'').trim(); if(!nome){ alert('Informe o nome.'); return; }
    const body={ id:$('f-id').value||0, programa:$('f-programa').value||_prog };
    FIELDS.forEach(f=>{ const el=$('f-'+f); if(el) body[f]=el.value; });
    btn.disabled=true;
    try{ const r=await apiPost('prog_salvar',body); if(!r||!r.ok){ alert((r&&r.msg)||'Erro ao salvar'); } else { pgModClose(); pgLoad(); } }
    catch(e){ alert('Falha de conexão'); }
    btn.disabled=false;
  };
  window.pgExcluir = async function(id){
    const rr=_data.find(x=>+x.id===id); const nome=rr?clean(rr.nome):'';
    if(!await uiConfirm('Remover "'+(nome||'este participante')+'" da lista?','Excluir participante?')) return;
    try{ const r=await apiPost('prog_excluir',{id}); if(!r||!r.ok){ alert((r&&r.msg)||'Erro'); return; } pgLoad(); }
    catch(e){ alert('Falha de conexão'); }
  };

  // ── Evolução mês a mês ──
  let _evoList=[];
  window.pgEvoReload = async function(pid){
    $('evo-hist').innerHTML='<div style="color:var(--muted)">Carregando…</div>';
    try{ const res=await apiGet('prog_evolucao_listar',{participante_id:pid});
      _evoList=(res&&res.ok)?res.data:[];
      $('evo-hist').innerHTML = _evoList.length ? _evoList.map(e=>{
        const m=String(e.criado_em||'').match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/);
        const quando = m?(m[3]+'/'+m[2]+'/'+m[1]+' '+m[4]):'';
        return '<div id="evoent-'+e.id+'" style="border-top:1px solid var(--border);padding:8px 0">'
          +'<div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;color:var(--muted)">'
            +'<span><b style="color:var(--accent)">'+esc(e.competencia)+'</b>'+(quando?(' · '+quando):'')+'</span>'
            +'<span style="white-space:nowrap"><button class="btn btn-outline btn-sm" title="Editar" style="padding:2px 6px" onclick="pgEvoEditar('+e.id+')"><span class="material-symbols-rounded" style="font-size:14px">edit</span></button> '
            +'<button class="btn btn-outline btn-sm" title="Excluir" style="padding:2px 6px" onclick="pgEvoExcluir('+e.id+')"><span class="material-symbols-rounded" style="font-size:14px;color:#c0392b">delete</span></button></span>'
          +'</div>'
          +'<div class="evo-ent-body" style="white-space:pre-wrap;margin-top:2px">'+esc(e.nota||'')+'</div></div>';
      }).join('') : '<div style="color:var(--muted)">Sem registros ainda.</div>';
    }catch(e){ $('evo-hist').innerHTML='<div style="color:var(--muted)">Erro ao carregar.</div>'; }
  };
  window.pgEvoEditar = function(id){
    const e=_evoList.find(x=>+x.id===id); const box=document.getElementById('evoent-'+id); if(!e||!box) return;
    const body=box.querySelector('.evo-ent-body'); if(!body) return;
    body.innerHTML='<textarea id="evoedit-'+id+'" rows="4" style="width:100%;box-sizing:border-box;padding:7px;border:1px solid var(--border);border-radius:7px;font-size:12.5px;line-height:1.45;background:var(--bg-card);color:var(--text)">'+esc(e.nota||'')+'</textarea>'
      +'<div style="text-align:right;margin-top:5px"><button class="btn btn-outline btn-sm" onclick="pgEvoReload('+(document.getElementById("evo-pid").value)+')">Cancelar</button> '
      +'<button class="btn btn-primary btn-sm" onclick="pgEvoEditSalvar('+id+')"><span class="material-symbols-rounded" style="font-size:15px">save</span> Salvar</button></div>';
    const ta=document.getElementById('evoedit-'+id); if(ta){ ta.focus(); }
  };
  window.pgEvoEditSalvar = async function(id){
    const ta=document.getElementById('evoedit-'+id); const nota=(ta?ta.value:'').trim();
    if(!nota){ alert('Escreva a nota.'); return; }
    try{ const r=await apiPost('prog_evolucao_editar',{id,nota}); if(!r||!r.ok){ alert((r&&r.msg)||'Erro'); return; } }
    catch(e){ alert('Falha de conexão'); return; }
    await pgEvoReload(document.getElementById('evo-pid').value);
  };
  window.pgEvoExcluir = async function(id){
    if(!await uiConfirm('Esta nota sai do histórico.','Excluir nota?')) return;
    try{ const r=await apiPost('prog_evolucao_excluir',{id}); if(!r||!r.ok){ alert((r&&r.msg)||'Erro'); return; } }
    catch(e){ alert('Falha de conexão'); return; }
    await pgEvoReload(document.getElementById('evo-pid').value);
  };
  window.pgEvoOpen = async function(id){
    const r=_data.find(x=>+x.id===id); if(!r) return;
    $('evo-pid').value=id; $('pg-evo-titulo').textContent='Evolução: '+(r.nome||'');
    $('evo-comp-lbl').textContent=_comp;
    $('evo-nota').value='';
    $('pg-evo').classList.add('show');
    pgEvoReload(id);
  };
  window.pgEvoClose = function(){ $('pg-evo').classList.remove('show'); pgLoad(); };
  window.pgEvoSalvar = async function(btn){
    const pid=$('evo-pid').value; const nota=($('evo-nota').value||'').trim();
    if(!nota){ alert('Escreva a nota antes de salvar.'); return; }
    btn.disabled=true;
    try{ const r=await apiPost('prog_evolucao_salvar',{participante_id:pid,competencia:_comp,nota});
      if(!r||!r.ok){ alert((r&&r.msg)||'Erro ao salvar'); }
      else { const row=_data.find(x=>+x.id===+pid); if(row) row.nota_mes=nota; $('evo-nota').value=''; await pgEvoReload(pid); }
    }catch(e){ alert('Falha de conexão'); }
    btn.disabled=false;
  };

  // ── Virar mês ──
  window.pgVirarMes = async function(){
    const el=$('pg-comp'); if(!el.value) return;
    const p=el.value.split('-'); let y=+p[0], m=+p[1]+1; if(m>12){ m=1; y++; }
    const prox=y+'-'+String(m).padStart(2,'0');
    if(!await uiConfirm('O mês atual ('+el.value+') fica guardado como está, e o próximo ('+prox+') é criado com a evolução em branco.','Virar '+_prog+' para '+prox+'?')) return;
    try{ const r=await apiPost('prog_virar_mes',{programa:_prog,competencia:prox}); if(!r||!r.ok){ alert((r&&r.msg)||'Erro'); return; } }
    catch(e){ alert('Falha de conexão'); return; }
    el.value=prox; pgLoad();
  };

  // ── Baixar ──
  window.pgBaixar = function(){
    if(typeof XLSX==='undefined'){ alert('Biblioteca de planilha não carregou. Recarregue a página.'); return; }
    const baby=(_prog==='Interbaby');
    const head=['Nome','Empresa','Telefone'].concat(baby?['DN','Id gestacional','DPP','Nome do bebê','Histórico','Ativ. Física','Alimentação','Medicações','Maternidade','Alta']:[]).concat(['Kit','Endereço','Competencia','Nota']);
    const aoa=[head];
    _data.forEach(r=>{ const base=[r.nome||'',r.empresa||'',r.telefone||''];
      const bb=baby?[r.dn||'',r.id_gestacional||'',r.dpp||'',r.nome_bebe||'',r.historico||'',r.ativ_fisica||'',r.alimentacao||'',r.medicacoes||'',r.maternidade||'',r.alta||'']:[];
      aoa.push(base.concat(bb).concat([r.kit||'',r.endereco||'',_comp,r.nota_mes||''])); });
    const ws=XLSX.utils.aoa_to_sheet(aoa); const wb=XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb,ws,_prog);
    XLSX.writeFile(wb,_prog.toLowerCase()+'_'+_comp+'.xlsx');
  };

  // ── Importar ──
  window.pgImportarFile = async function(input){
    const file=input.files&&input.files[0]; input.value=''; if(!file) return;
    if(typeof XLSX==='undefined'){ alert('Biblioteca de planilha não carregou. Recarregue a página.'); return; }
    try{
      const buf=await file.arrayBuffer(); const wb=XLSX.read(new Uint8Array(buf),{type:'array'});
      const nn=s=>String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').trim();
      const linhas=[];
      // Mapa de meses PT -> número, pra detectar a coluna de evolução (ex.: "Setembro", "Dezembro").
      const MES={janeiro:1,fevereiro:2,marco:3,abril:4,maio:5,junho:6,julho:7,agosto:8,setembro:9,outubro:10,novembro:11,dezembro:12};
      const _now=new Date(), curY=_now.getFullYear(), curM=_now.getMonth()+1;
      wb.SheetNames.forEach(sn=>{
        const ws=wb.Sheets[sn]; const rows=XLSX.utils.sheet_to_json(ws,{header:1,defval:'',raw:false});
        // acha a linha de cabeçalho que tenha "nome"
        let hr=-1, col={}, compCol=-1, compVal='';
        for(let i=0;i<Math.min(rows.length,6);i++){
          const cs=(rows[i]||[]).map(nn);
          if(cs.indexOf('nome')>=0){ hr=i; cs.forEach((c,j)=>{ if(col[c]==null) col[c]=j; const mm=MES[c]; if(mm){ compCol=j; const yy=(mm>curM)?curY-1:curY; compVal=yy+'-'+String(mm).padStart(2,'0'); } }); break; }
        }
        if(hr<0) return;
        const g=(row,name)=>{ const j=col[name]; return j==null?'':String(row[j]==null?'':row[j]).trim(); };
        const dpar=v=>{ const m=String(v||'').match(/(\d{2})\/(\d{2})\/(\d{4})/); if(m) return m[3]+'-'+m[2]+'-'+m[1]; const m2=String(v||'').match(/^(\d{4})-(\d{2})-(\d{2})/); return m2?m2[0]:''; };
        for(let i=hr+1;i<rows.length;i++){
          const r=rows[i]; if(!r) continue; const nome=g(r,'nome'); if(!nome||nn(nome)==='nome') continue;
          const empresa=g(r,'empresa'); const grupo = empresa||sn; // se não tem coluna empresa, usa o nome da aba como grupo
          linhas.push({
            nome, empresa: empresa||grupo, grupo,
            telefone:g(r,'telefone'),
            dn:dpar(g(r,'dn')),
            id_gestacional:g(r,'id gestacional (semanas)')||g(r,'id gestacional')||g(r,'id gestacional ')||g(r,'id gestacional (semanas) '),
            dpp:dpar(g(r,'dpp')),
            nome_bebe:g(r,'nome do bebe')||g(r,'nome bebe'),
            historico:g(r,'historico'),
            ativ_fisica:g(r,'ativ. fisica')||g(r,'exercicio'),
            alimentacao:g(r,'alimentacao'),
            medicacoes:g(r,'medicacoes')||g(r,'medicamentos'),
            maternidade:g(r,'parto / maternidade')||g(r,'maternidade'),
            kit:g(r,'kit'),
            alta:g(r,'alta'),
            endereco:g(r,'endereco'),
            competencia: compVal,
            nota: compCol>=0 ? String((r[compCol]==null?'':r[compCol])).trim() : ''
          });
        }
      });
      if(!linhas.length){ alert('Não encontrei linhas com a coluna "Nome" na planilha.'); return; }
      if(!await uiConfirm('Quem já existe pelo nome é atualizado; a nota do mês detectado entra na evolução.','Importar '+linhas.length+' participante(s) para o '+_prog+'?')) return;
      const r=await apiPost('prog_importar',{programa:_prog,linhas});
      if(!r||!r.ok){ alert((r&&r.msg)||'Erro ao importar'); return; }
      alert('Importado: '+r.participantes+' participante(s), '+r.evolucoes+' nota(s) de evolução.');
      pgLoad();
    }catch(e){ alert('Erro ao ler planilha: '+e.message); }
  };

  document.addEventListener('DOMContentLoaded', pgLoad);
})();
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
