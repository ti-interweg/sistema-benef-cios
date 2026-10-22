<?php
$currentPage = 'triagem';
$pageTitle   = 'Triagem — Interweg Benefícios';
require_once __DIR__ . '/_header.php';
?>
<style>
  .tg-wrap{max-width:none;width:100%}
  .tg-card{background:var(--bg-card);border:1px solid var(--border);border-left:4px solid var(--warn);border-radius:14px;padding:16px 18px;margin-bottom:14px;box-shadow:0 2px 10px rgba(0,0,0,.04)}
  .tg-top{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px}
  .tg-tp{font-weight:800;padding:3px 10px;border-radius:11px;font-size:12px}
  .tg-tp.inc{background:#d3f2d8;color:#1f6b34}.tg-tp.exc{background:#fdecec;color:#c0392b}
  .tg-proto{font-size:12px;color:var(--muted);font-weight:700}
  .tg-grupo{font-weight:800;color:var(--accent2)}
  .tg-info{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:6px 18px;font-size:13px;margin:8px 0}
  .tg-info b{color:var(--muted);font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.3px;display:block}
  .tg-act{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid var(--border)}
  .tg-act select{padding:8px 11px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;min-width:200px;background:var(--bg);color:var(--text)}
  .tg-empty{text-align:center;color:var(--muted);padding:60px}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Triagem de Demandas</div>
      <div class="page-sub">Movimentações enviadas pelos clientes aguardando distribuição para um analista</div>
    </div>
    <button class="btn btn-outline" onclick="tgLoad()"><span class="material-symbols-rounded" style="font-size:17px">refresh</span> Atualizar</button>
  </div>
  <div class="tg-wrap" id="tg-lista"><div class="tg-empty">Carregando…</div></div>
</main>

<script>
if (typeof window.apiGet !== 'function') window.apiGet = async (a,p={}) => (await fetch('api.php?'+new URLSearchParams({action:a,...p}))).json();
async function apiPost(action, body){ return (await fetch('api.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json(); }
const esc = s => { const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; };
let TG_ANALISTAS = [];

async function tgLoad(){
  const box=document.getElementById('tg-lista');
  box.innerHTML='<div class="tg-empty">Carregando…</div>';
  try{
    if(!TG_ANALISTAS.length){ const a=await apiGet('analistas'); TG_ANALISTAS=(a&&(a.data||a))||[]; }
    const r=await apiGet('triagem_listar'); const rows=(r&&r.data)||[];
    if(!rows.length){ box.innerHTML='<div class="tg-empty"><span class="material-symbols-rounded" style="font-size:40px;color:var(--success)">task_alt</span><br>Nenhuma demanda em triagem.</div>'; return; }
    // Só analistas de pós-venda (tipo "analista"): exclui admin, financeiro, enfermeira, coordenador, novos negócios, etc.
    const TG_POS = TG_ANALISTAS.filter(a => (a.tipo||'').toLowerCase().trim()==='analista');
    const opts='<option value="">Escolher analista…</option>'+TG_POS.map(a=>'<option value="'+esc(a.id)+'">'+esc(a.nome)+'</option>').join('');
    box.innerHTML=rows.map(d=>{
      const inc=(d.tipo_demanda||'').toLowerCase().includes('inclus');
      const dt=(d.recebimento||d.criado_em||'').slice(0,10).split('-').reverse().join('/');
      return '<div class="tg-card" data-id="'+d.id+'">'
        +'<div class="tg-top"><span class="tg-tp '+(inc?'inc':'exc')+'">'+esc(d.tipo_demanda||'')+'</span>'
        +'<span class="tg-grupo">'+esc(d.grupo||d.origem||'')+'</span>'
        +'<span class="tg-proto">'+esc(d.protocolo||('#'+d.id))+' · '+esc(dt)+'</span></div>'
        +'<div class="tg-info">'
        +'<div><b>Beneficiário</b>'+esc(d.dados_beneficiario||'—')+'</div>'
        +'<div><b>Empresa</b>'+esc(d.empresa||'—')+'</div>'
        +'<div><b>Seguradora</b>'+esc(d.seguradora||'—')+'</div>'
        +'<div><b>Ramo</b>'+esc(d.ramo||'—')+'</div>'
        +(d.detalhamento?'<div style="grid-column:1/-1"><b>Observação</b>'+esc(d.detalhamento)+'</div>':'')
        +'</div>'
        +'<div class="tg-act">'
        +(d.anexo_url?'<a class="btn btn-outline btn-sm" href="'+esc(d.anexo_url)+'" target="_blank"><span class="material-symbols-rounded" style="font-size:15px">attach_file</span> Anexo</a>':'')
        +'<select id="sel-'+d.id+'">'+opts+'</select>'
        +'<button class="btn btn-primary btn-sm" onclick="tgAtribuir('+d.id+')"><span class="material-symbols-rounded" style="font-size:15px">how_to_reg</span> Atribuir</button>'
        +'</div></div>';
    }).join('');
  }catch(e){ box.innerHTML='<div class="tg-empty" style="color:#c0392b">Erro ao carregar.</div>'; }
}

async function tgAtribuir(id){
  const sel=document.getElementById('sel-'+id); const aid=sel.value;
  if(!aid){ (window.uiAlert?uiAlert:alert)('Escolha um analista.'); return; }
  const nome=sel.options[sel.selectedIndex].text;
  const r=await apiPost('triagem_atribuir',{id:id, analista_id:aid, analista_nome:nome});
  if(r.ok){ const c=document.querySelector('.tg-card[data-id="'+id+'"]'); if(c){ c.style.transition='opacity .3s'; c.style.opacity='0'; setTimeout(()=>{ c.remove(); if(!document.querySelectorAll('.tg-card').length) tgLoad(); },300); } if(window.toast)toast('Demanda atribuída a '+nome); }
  else if((r.msg||'').toLowerCase().includes('acesso negado')){
    (window.uiAlert?uiAlert:alert)('Sua sessão expirou ou mudou (ex.: login no portal do cliente no mesmo navegador). Faça login novamente como administrador.');
    setTimeout(()=>{ location.href='login.php'; }, 400);
  }
  else { (window.uiAlert?uiAlert:alert)(r.msg||'Erro ao atribuir.'); }
}
tgLoad();
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
