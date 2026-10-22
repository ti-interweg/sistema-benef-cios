<?php
$currentPage = 'entregaveis';
$pageTitle   = 'Entregáveis por Porte — Interweg Benefícios';
$pageScripts = [];
require_once __DIR__ . '/_header.php';
?>
<style>
  .en-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:6px 0 14px}
  .en-toolbar .ctl{padding:8px 11px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg-card);color:var(--text)}
  .en-toolbar input,.en-toolbar select{width:auto;max-width:100%;box-sizing:border-box}
  #en-busca{width:260px}
  .chip{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:800}
  .c-feito{background:#d3f2d8;color:#256029}.c-ofer{background:#dbeafe;color:#1e40af}.c-pend{background:#fef3c7;color:#92400e}.c-na{background:#eee;color:#666}
  .en-ov{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:flex-start;justify-content:center;z-index:9999;overflow:auto;padding:24px 12px}
  .en-ov.show{display:flex}
  .en-modal{background:var(--bg-card);border-radius:14px;padding:22px;width:960px;max-width:97vw}
  #en-det-table{width:100%;border-collapse:collapse;font-size:12.5px}
  #en-det-table th,#en-det-table td{padding:7px 8px;border-bottom:1px solid var(--border);text-align:left;vertical-align:middle}
  #en-det-table select,#en-det-table input{padding:5px 7px;border:1px solid var(--border);border-radius:7px;font-size:12px;background:var(--bg-card);color:var(--text)}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Entregáveis por Porte</div>
      <div class="page-sub">O que já foi feito/oferecido por empresa, conforme o porte, pra saber o que falta oferecer</div>
    </div>
  </div>

  <div class="en-toolbar">
    <input type="text" id="en-busca" class="ctl" placeholder="Buscar empresa..." oninput="enRender()">
    <select id="en-porte" class="ctl" onchange="enRender()">
      <option value="">Todos os portes</option>
      <option>ATÉ 29</option><option>30 A 99</option><option>100 A 299</option><option>A PARTIR DE 300</option>
    </select>
    <select id="en-ordem" class="ctl" onchange="enRender()" title="Ordenar">
      <option value="grupo">Ordenar: A→Z</option>
      <option value="pend">Mais pendentes primeiro</option>
      <option value="feitos">Mais feitos primeiro</option>
    </select>
    <span id="en-resumo" style="font-size:12px;color:var(--muted);margin-left:auto"></span>
  </div>

  <div class="table-wrap">
    <div class="table-head"><div class="table-title" id="en-count">Empresas</div></div>
    <div class="tscroll">
      <table>
        <thead>
          <tr><th>Empresa</th><th>Porte</th>
          <th style="text-align:center">Feitos</th><th style="text-align:center">Oferecidos</th>
          <th style="text-align:center">Pendentes</th><th style="text-align:right">Ações</th></tr>
        </thead>
        <tbody id="en-tbody"></tbody>
      </table>
    </div>
  </div>
</main>

<!-- Modal detalhe da empresa -->
<div class="en-ov" id="en-det" onclick="if(event.target===this)enDetClose()">
  <div class="en-modal">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
      <h3 id="en-det-titulo" style="margin:0;color:var(--accent)">Empresa</h3>
      <button class="btn btn-outline btn-sm" onclick="enDetClose()"><span class="material-symbols-rounded" style="font-size:16px">close</span></button>
    </div>
    <div id="en-det-sub" style="color:var(--muted);font-size:12.5px;margin-bottom:12px"></div>
    <div style="overflow:auto;border:1px solid var(--border);border-radius:10px">
      <table id="en-det-table"><thead>
        <tr><th style="width:30%">Entregável</th><th style="width:16%">Porte</th><th style="width:20%">Status</th><th style="width:16%">Data</th><th>Observação</th></tr>
      </thead><tbody id="en-det-body"></tbody></table>
    </div>
    <div style="text-align:right;color:var(--muted);font-size:12px;margin-top:8px">Alterações são salvas automaticamente.</div>
  </div>
</div>

<script>
(function(){
  const esc = s => { const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; };
  const norm = s => String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').trim();
  const $ = id => document.getElementById(id);
  const apiGet = (a,p={})=>fetch('api_entregaveis.php?'+new URLSearchParams({action:a,...p})).then(r=>r.json());
  const apiPost = (a,b)=>fetch('api_entregaveis.php?action='+a,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b||{})}).then(r=>r.json());
  const fdate = s => { const m=String(s||'').match(/^(\d{4})-(\d{2})-(\d{2})/); return m?(m[3]+'/'+m[2]+'/'+m[1]):''; };
  let _emps=[], _detEid=0;

  window.enLoad = async function(){
    $('en-tbody').innerHTML='<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">Carregando…</td></tr>';
    try{ const r=await apiGet('ent_empresas'); _emps=(r&&r.ok)?r.data:[]; }catch(e){ _emps=[]; }
    enRender();
  };

  window.enRender = function(){
    const q=norm($('en-busca').value||''); const fp=$('en-porte').value||''; const ord=$('en-ordem').value||'grupo';
    let rows=_emps.filter(e=>(!q||norm(e.grupo).includes(q))&&(!fp||String(e.porte||'').toUpperCase()===fp));
    if(ord==='pend') rows.sort((a,b)=>b.pendentes-a.pendentes||a.grupo.localeCompare(b.grupo,'pt'));
    else if(ord==='feitos') rows.sort((a,b)=>b.feitos-a.feitos||a.grupo.localeCompare(b.grupo,'pt'));
    else rows.sort((a,b)=>a.grupo.localeCompare(b.grupo,'pt'));
    // resumo
    const tEmp=_emps.length, tPend=_emps.reduce((s,e)=>s+e.pendentes,0), tFeito=_emps.reduce((s,e)=>s+e.feitos,0);
    $('en-resumo').innerHTML='Empresas: <b>'+tEmp+'</b> · <span style="color:#256029">Feitos: <b>'+tFeito+'</b></span> · <span style="color:#92400e">Pendentes: <b>'+tPend+'</b></span>';
    const cnt=$('en-count'); if(cnt) cnt.textContent='Empresas ('+rows.length+')';
    if(!rows.length){ $('en-tbody').innerHTML='<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">Nenhuma empresa encontrada.</td></tr>'; return; }
    let h='';
    rows.forEach(e=>{
      h+='<tr>'
        +'<td><b>'+esc(e.grupo)+'</b>'+(e.enfermeira?'<br><span style="color:var(--muted);font-size:11px">'+esc(e.enfermeira)+'</span>':'')+'</td>'
        +'<td>'+esc(e.porte||'')+'</td>'
        +'<td style="text-align:center">'+(e.feitos?('<span class="chip c-feito">'+e.feitos+'</span>'):'<span style="color:var(--muted)">0</span>')+' <span style="color:var(--muted);font-size:11px">/ '+e.total+'</span></td>'
        +'<td style="text-align:center">'+(e.oferecidos?('<span class="chip c-ofer">'+e.oferecidos+'</span>'):'<span style="color:var(--muted)">0</span>')+'</td>'
        +'<td style="text-align:center">'+(e.pendentes?('<span class="chip c-pend">'+e.pendentes+'</span>'):'<span class="chip c-feito">0</span>')+'</td>'
        +'<td style="text-align:right"><button class="btn btn-outline btn-sm" onclick="enAbrir('+e.id+')">Abrir</button></td>'
        +'</tr>';
    });
    $('en-tbody').innerHTML=h;
  };

  window.enAbrir = async function(id){
    _detEid=id;
    $('en-det-titulo').textContent='Carregando…'; $('en-det-sub').textContent='';
    $('en-det-body').innerHTML='<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:20px">Carregando…</td></tr>';
    $('en-det').classList.add('show');
    try{
      const r=await apiGet('ent_detalhe',{empresa_id:id});
      if(!r||!r.ok){ $('en-det-body').innerHTML='<tr><td colspan="5">Erro ao carregar.</td></tr>'; return; }
      $('en-det-titulo').textContent=r.empresa.grupo;
      $('en-det-sub').innerHTML='Porte: <b>'+esc(r.empresa.porte||'')+'</b>'+(r.empresa.enfermeira?(' · Enfermeira: '+esc(r.empresa.enfermeira)):'');
      const opt=(v,sel)=>'<option value="'+v+'"'+(sel===v?' selected':'')+'>'+(v===''?'Pendente':v)+'</option>';
      let h='';
      r.itens.forEach(it=>{
        const sid=it.servico_id;
        h+='<tr>'
          +'<td><b>'+esc(it.nome)+'</b></td>'
          +'<td style="color:var(--muted)">'+esc(it.aplic)+'</td>'
          +'<td><select data-sid="'+sid+'" onchange="enSalvar('+sid+')">'
            +opt('',it.status)+opt('Oferecido',it.status)+opt('Feito',it.status)+opt('Não se aplica',it.status)+'</select></td>'
          +'<td><input type="date" data-sid="'+sid+'" value="'+(String(it.data||'').slice(0,10))+'" onchange="enSalvar('+sid+')"></td>'
          +'<td><input type="text" data-sid="'+sid+'" value="'+esc(it.observacao||'')+'" placeholder="..." style="width:100%" onchange="enSalvar('+sid+')"></td>'
          +'</tr>';
      });
      $('en-det-body').innerHTML = h || '<tr><td colspan="5" style="color:var(--muted)">Nenhum entregável para este porte.</td></tr>';
    }catch(e){ $('en-det-body').innerHTML='<tr><td colspan="5">Falha de conexão.</td></tr>'; }
  };
  window.enDetClose = function(){ $('en-det').classList.remove('show'); enLoad(); };

  window.enSalvar = async function(sid){
    const sel=document.querySelector('#en-det-body select[data-sid="'+sid+'"]');
    const dt =document.querySelector('#en-det-body input[type=date][data-sid="'+sid+'"]');
    const ob =document.querySelector('#en-det-body input[type=text][data-sid="'+sid+'"]');
    const body={empresa_id:_detEid,servico_id:sid,status:sel?sel.value:'',data:dt?dt.value:'',observacao:ob?ob.value:''};
    try{ const r=await apiPost('ent_status_salvar',body); if(!r||!r.ok) alert((r&&r.msg)||'Erro ao salvar'); }
    catch(e){ alert('Falha de conexão'); }
  };

  document.addEventListener('DOMContentLoaded', enLoad);
})();
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
