<?php
$currentPage = 'agenciamento_ag';
$pageTitle   = 'Agenciamento (AG) — Interweg Benefícios';
require_once __DIR__ . '/_header.php';
?>
<style>
  .ag-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:6px 0 14px}
  .ag-toolbar .ctl{padding:8px 11px;border:1px solid var(--border);border-radius:9px;font-size:13px;background:var(--bg-card);color:var(--text)}
  .ag-toolbar #ag-busca{width:280px}
  .ag-chip{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:800}
  .ag-c-pend{background:#fef3c7;color:#92400e}.ag-c-rec{background:#d3f2d8;color:#256029}.ag-c-ok{background:#dbeafe;color:#1e40af}
  .ag-ov{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:9999;padding:24px 12px}
  .ag-ov.show{display:flex}
  .ag-modal{background:var(--bg-card);border-radius:16px;padding:22px;width:440px;max-width:96vw}
  .ag-grp{font-weight:800;color:var(--text)} .ag-sub{color:var(--muted);font-size:11px}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Agenciamento (AG)</div>
      <div class="page-sub">Baixa das parcelas de agenciamento. Quando todas as parcelas são recebidas, o contrato é concluído. A comissão vitalícia segue no Faturamento normal.</div>
    </div>
  </div>

  <div class="cards">
    <div class="card c-red"><div class="card-label">Parcelas pendentes</div><div class="card-val" id="ag-k-pend">—</div><div class="card-sub">aguardando recebimento</div><div class="card-icon"><span class="material-symbols-rounded">pending_actions</span></div></div>
    <div class="card c-blue"><div class="card-label">Valor pendente</div><div class="card-val" id="ag-k-vpend" style="font-size:22px">R$ —</div><div class="card-sub">soma a receber</div><div class="card-icon"><span class="material-symbols-rounded">request_quote</span></div></div>
    <div class="card c-green"><div class="card-label">Parcelas recebidas</div><div class="card-val" id="ag-k-rec">—</div><div class="card-sub">baixadas</div><div class="card-icon"><span class="material-symbols-rounded">task_alt</span></div></div>
    <div class="card c-teal"><div class="card-label">Valor recebido</div><div class="card-val" id="ag-k-vrec" style="font-size:22px">R$ —</div><div class="card-sub">soma baixada</div><div class="card-icon"><span class="material-symbols-rounded">account_balance_wallet</span></div></div>
  </div>

  <div class="ag-toolbar">
    <input type="text" id="ag-busca" class="ctl" placeholder="Buscar grupo, empresa, seguradora, CNPJ..." oninput="agRender()">
    <select id="ag-filtro" class="ctl" onchange="agLoad()">
      <option value="pendentes">Com parcelas pendentes</option>
      <option value="concluidos">Concluídos (tudo recebido)</option>
      <option value="todos">Todos</option>
    </select>
    <span id="ag-resumo" style="font-size:12px;color:var(--muted);margin-left:auto"></span>
  </div>

  <div class="table-wrap">
    <div class="table-head"><div class="table-title" id="ag-count">Parcelas de AG</div></div>
    <div style="overflow:auto">
      <table>
        <thead><tr>
          <th>Grupo / Empresa</th><th>Seguradora</th><th>Contrato / CNPJ</th>
          <th style="white-space:nowrap">Parcela</th><th>Competência</th>
          <th style="text-align:right">% AG</th><th style="text-align:right">Valor</th>
          <th style="text-align:center">Status</th><th style="text-align:center">Ação</th>
        </tr></thead>
        <tbody id="ag-tbody"></tbody>
      </table>
    </div>
  </div>
</main>

<!-- Modal baixa -->
<div class="ag-ov" id="ag-modal" onclick="if(event.target===this)agClose()">
  <div class="ag-modal">
    <div style="font-weight:800;font-size:16px;margin-bottom:4px"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">payments</span> <span id="ag-m-title">Dar baixa de parcela de AG</span></div>
    <div id="ag-m-ctx" style="font-size:12.5px;color:var(--muted);margin:8px 0 14px;white-space:pre-line;line-height:1.5"></div>
    <input type="hidden" id="ag-m-emp"><input type="hidden" id="ag-m-parc">
    <input type="hidden" id="ag-m-comp"><input type="hidden" id="ag-m-pct"><input type="hidden" id="ag-m-valor">
    <div id="ag-m-dtwrap" style="margin-bottom:6px">
      <label style="display:block;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px">Data do recebimento</label>
      <input id="ag-m-dt" type="date" style="width:100%;box-sizing:border-box;padding:9px 11px;border:1px solid var(--border);border-radius:10px;font-size:14px;background:var(--bg-card);color:var(--text)">
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
      <button class="btn btn-outline" onclick="agClose()">Cancelar</button>
      <button class="btn btn-danger" id="ag-m-estornar" style="display:none" onclick="agEstornar(this)"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">undo</span> Estornar</button>
      <button class="btn btn-primary" id="ag-m-confirmar" onclick="agConfirmar(this)"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">check</span> Confirmar recebimento</button>
    </div>
  </div>
</div>

<script>
(function(){
  const $=id=>document.getElementById(id);
  const esc=s=>{const d=document.createElement('div');d.textContent=(s==null?'':s);return d.innerHTML;};
  const brl=v=>(v==null||v==='')?'—':('R$ '+Number(v).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}));
  let AG_DATA=[], AG_PODE=false;

  window.agLoad=async function(){
    const f=$('ag-filtro').value;
    try{ const r=await fetch('api_agenciamento.php?action=ag_listar&filtro='+encodeURIComponent(f)).then(x=>x.json());
      AG_DATA=(r&&r.data)||[]; AG_PODE=!!(r&&r.pode_baixar);
      const t=(r&&r.totais)||{};
      $('ag-k-pend').textContent=(t.pendentes||0).toLocaleString('pt-BR');
      $('ag-k-rec').textContent=(t.recebidas||0).toLocaleString('pt-BR');
      $('ag-k-vpend').textContent=brl(t.valor_pendente||0);
      $('ag-k-vrec').textContent=brl(t.valor_recebido||0);
    }catch(e){ AG_DATA=[]; }
    agRender();
  };

  window.agRender=function(){
    const q=($('ag-busca').value||'').toLowerCase().trim();
    let contratos=AG_DATA;
    if(q){ contratos=contratos.filter(c=>[c.grupo,c.empresa,c.seguradora,c.numero_contrato,c.cnpj].some(x=>String(x||'').toLowerCase().includes(q))); }
    let linhas=0;
    const rows=contratos.map(c=>{
      return c.parcelas.map((p,idx)=>{
        linhas++;
        const stTxt = p.recebido ? 'Recebido' : 'Pendente';
        const stCls = p.recebido ? 'ag-c-rec' : 'ag-c-pend';
        const info = idx===0
          ? `<div class="ag-grp">${esc(c.grupo)}</div><div class="ag-sub">${esc(c.empresa)}</div>`
          : `<div class="ag-sub" style="opacity:.6">${esc(c.empresa)}</div>`;
        const segCnpj = idx===0
          ? `<td>${esc(c.seguradora)||'—'}</td><td><b>${esc(c.numero_contrato)||'—'}</b><br><span class="ag-sub">${esc(c.cnpj)||''}</span></td>`
          : `<td></td><td></td>`;
        const acao = AG_PODE
          ? (p.recebido
              ? `<button class="btn btn-outline btn-sm" onclick="agAbrir(${c.empresa_id},${p.num})" title="Ver / estornar">Baixado</button>`
              : `<button class="btn btn-primary btn-sm" onclick="agAbrir(${c.empresa_id},${p.num})"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">check</span> Dar baixa</button>`)
          : '<span style="color:var(--muted)">—</span>';
        return `<tr>
          <td>${info}</td>${segCnpj}
          <td style="white-space:nowrap">${p.num}ª${idx===0&&c.status==='concluido'?' <span class="ag-chip ag-c-ok">OK</span>':''}</td>
          <td>${esc(p.competencia_lbl)||'—'}</td>
          <td style="text-align:right">${p.percentual!=null?(+p.percentual).toLocaleString('pt-BR',{minimumFractionDigits:0,maximumFractionDigits:2})+'%':'—'}</td>
          <td style="text-align:right;font-weight:600">${brl(p.valor)}</td>
          <td style="text-align:center"><span class="ag-chip ${stCls}">${stTxt}</span>${p.recebido&&p.data_recebimento?'<br><span class="ag-sub">'+esc(p.data_recebimento.split('-').reverse().join('/'))+'</span>':''}</td>
          <td style="text-align:center">${acao}</td>
        </tr>`;
      }).join('');
    }).join('');
    $('ag-tbody').innerHTML = rows || '<tr><td colspan="9" style="text-align:center;color:var(--muted);padding:18px">Nenhuma parcela de AG encontrada.</td></tr>';
    $('ag-count').textContent='Parcelas de AG ('+linhas+')';
    $('ag-resumo').textContent=contratos.length+' contrato(s)';
  };

  window.agAbrir=function(empId,parc){
    const c=AG_DATA.find(x=>x.empresa_id===empId); if(!c){return;}
    const p=c.parcelas.find(x=>x.num===parc); if(!p){return;}
    $('ag-m-emp').value=empId; $('ag-m-parc').value=parc;
    $('ag-m-comp').value=p.competencia||''; $('ag-m-pct').value=(p.percentual==null?'':p.percentual); $('ag-m-valor').value=(p.valor==null?'':p.valor);
    let ctx=(c.grupo||'?')+' · '+(c.empresa||'?')+' · '+(c.seguradora||'?');
    ctx+='\n'+p.num+'ª parcela · '+(p.competencia_lbl||'')+(p.percentual!=null?' · '+p.percentual+'%':'');
    ctx+='\nValor: '+brl(p.valor);
    $('ag-m-ctx').textContent=ctx;
    const dt=$('ag-m-dt'); dt.value=p.data_recebimento||new Date().toISOString().slice(0,10);
    if(p.recebido){
      $('ag-m-title').textContent='Parcela de AG recebida';
      $('ag-m-confirmar').style.display='none';
      $('ag-m-estornar').style.display='';
    }else{
      $('ag-m-title').textContent='Dar baixa de parcela de AG';
      $('ag-m-confirmar').style.display='';
      $('ag-m-estornar').style.display='none';
    }
    $('ag-modal').classList.add('show');
  };
  window.agClose=function(){ $('ag-modal').classList.remove('show'); };

  async function agSalvar(recebido, btn){
    btn.disabled=true;
    const body={ empresa_id:+$('ag-m-emp').value, parcela_num:+$('ag-m-parc').value,
      competencia:$('ag-m-comp').value, percentual:$('ag-m-pct').value, valor:$('ag-m-valor').value,
      recebido:recebido, data_recebimento:$('ag-m-dt').value };
    try{
      const r=await fetch('api_agenciamento.php?action=ag_baixar',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(x=>x.json());
      btn.disabled=false;
      if(!r||!r.ok){ alert((r&&r.msg)||'Erro ao salvar'); return; }
      agClose(); agLoad();
    }catch(e){ btn.disabled=false; alert('Erro de conexão'); }
  }
  window.agConfirmar=function(btn){ agSalvar(true, btn); };
  window.agEstornar=function(btn){ agSalvar(false, btn); };

  agLoad();
})();
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
