<?php
$currentPage = 'dashboard';
$pageTitle   = 'Dashboard — Interweg Benefícios';
$pageScripts = ['assets/dashboard.js', 'assets/resumo_cotacoes.js', 'assets/resumo_fechamentos.js', 'assets/resumo_demandas.js', 'assets/resumo_faturamento.js', 'assets/resumo_sinistralidade.js'];

require_once __DIR__ . '/_header.php';
if (!$canViewAll) { header('Location: demandas.php'); exit; }
?>

<style>
  .cc-breakdown{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin:8px 0 20px}
  .cc-break-block{position:relative;background:var(--bg-card,#fff);border:1px solid var(--border);border-radius:18px;padding:20px 22px;box-shadow:0 1px 2px rgba(16,24,40,.04),0 8px 24px -12px rgba(16,24,40,.10);transition:box-shadow .25s ease,transform .25s ease}
  .cc-break-block:hover{box-shadow:0 2px 4px rgba(16,24,40,.05),0 16px 40px -16px rgba(0,191,179,.28);transform:translateY(-2px)}
  .cc-break-title{display:flex;align-items:center;gap:9px;width:100%;font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:var(--muted);margin-bottom:16px}
  .cc-break-title::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--accent,#00c9a7);box-shadow:0 0 0 4px rgba(0,191,179,.14);flex:none}
  .cc-brk-list{display:flex;flex-direction:column;gap:2px}
  .cc-brk-row{display:grid;grid-template-columns:150px 1fr 52px;align-items:center;gap:14px;padding:8px 10px;border-radius:10px;transition:background .15s ease}
  .cc-brk-row:hover{background:rgba(0,191,179,.07)}
  .cc-brk-zero{opacity:.45}
  .cc-brk-lbl{font-size:13px;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .cc-brk-track{height:10px;border-radius:999px;background:rgba(130,130,130,.12);overflow:hidden}
  .cc-brk-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,var(--accent,#00c9a7),#43dccb);box-shadow:0 1px 6px rgba(0,191,179,.35);transition:width .6s cubic-bezier(.22,1,.36,1)}
  .cc-brk-val{font-size:13px;color:var(--muted);text-align:right;white-space:nowrap}
  .cc-brk-val b{display:inline-block;min-width:30px;padding:2px 9px;border-radius:999px;background:rgba(0,191,179,.10);color:var(--accent,#00a99d);font-weight:800;font-size:13px}
  .cc-brk-zero .cc-brk-val b{background:rgba(130,130,130,.10);color:var(--muted)}
  #dash-cot-resumo{grid-template-columns:repeat(3,minmax(0,1fr))}
  .cc-more-btn{margin-top:8px;background:none;border:none;color:var(--accent,#00a99d);font-size:12px;font-weight:700;cursor:pointer;padding:4px 10px;border-radius:8px;transition:background .15s ease}
  .cc-more-btn:hover{background:rgba(0,191,179,.10)}
  .sin-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 10px;border-radius:10px;transition:background .15s ease}
  .sin-row:hover{background:rgba(0,191,179,.06)}
  .sin-emp{font-size:13px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
  .sin-vals{display:flex;gap:14px;white-space:nowrap;font-size:12px;color:var(--muted)}
  .sin-vals b{font-weight:700}
  @media (max-width:900px){#dash-cot-resumo{grid-template-columns:1fr}}
  /* Dashboard por Analista — lista em linhas */
  .dash-list{background:var(--bg-card,#fff);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 1px 2px rgba(16,24,40,.04),0 8px 24px -14px rgba(16,24,40,.10)}
  .dash-row{display:grid;align-items:center;gap:14px;padding:14px 18px 16px;border-bottom:1px solid var(--border)}
  .dash-row:last-child{border-bottom:none}
  .dash-row.clickable{cursor:pointer;transition:background .15s ease}
  .dash-row.clickable:hover{background:rgba(0,191,179,.06)}
  .dash-row-head{background:rgba(130,130,130,.05);font-size:11px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;color:var(--muted)}
  .dash-c-name{display:flex;align-items:center;gap:11px;min-width:0}
  .dash-c-num{text-align:center;font-size:13px;color:var(--muted);white-space:nowrap}
  .dash-c-num b{font-weight:600;font-size:16px}
  .dash-row-head .dash-c-name{font-size:11px;font-weight:800;letter-spacing:.5px;text-transform:uppercase}
  /* Resumo de Fechamentos */
  .fc-brk-list{display:flex;flex-direction:column;gap:5px}
  .fc-brk-row{padding:9px 10px;border-radius:10px}
  .fc-brk-row.cc-brk-zero{opacity:.5}
  .fc-brk-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px}
  .fc-brk-lbl{font-size:13px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .fc-brk-count{font-size:12px;color:var(--muted);white-space:nowrap}
  .fc-brk-count b{color:var(--text);font-weight:700;font-size:14px}
  .fc-brk-metrics{display:flex;flex-wrap:wrap;gap:3px 18px;margin-top:7px;font-size:11.5px;color:var(--muted)}
  .fc-brk-metrics b{color:var(--text);font-weight:600}
</style>

<main class="main">
  <!-- Resumo de Demandas (no topo) -->
  <div style="margin-bottom:26px">
    <div class="page-title" style="font-size:16px">Resumo de Demandas</div>
    <div class="page-sub" style="margin-bottom:10px">Demandas por situação e por analista.</div>
    <div id="dash-dem-resumo" class="cc-breakdown"></div>
  </div>

  <!-- Resumo de Faturamento Atual (no topo) -->
  <div style="margin-bottom:26px">
    <div class="page-title" style="font-size:16px">Resumo de Faturamento Atual</div>
    <div class="page-sub" style="margin-bottom:10px">Faturamento atual por status, seguradora e analista.</div>
    <div id="dash-fat-resumo" class="cc-breakdown"></div>
  </div>

  <!-- Resumo de Sinistralidade (no topo) -->
  <div style="margin-bottom:26px">
    <div class="page-title" style="font-size:16px">Resumo de Sinistralidade</div>
    <div class="page-sub" style="margin-bottom:10px">Sinistralidade por empresa (mês e acumulado 12 meses). Vermelho acima do breakeven.</div>
    <div id="dash-sin-resumo" class="cc-breakdown"></div>
  </div>

  <!-- Resumo de Cotações (no topo) -->
  <div style="margin-bottom:26px">
    <div class="page-title" style="font-size:16px">Resumo de Cotações · Saúde e Odonto</div>
    <div class="page-sub" style="margin-bottom:10px">Cotações por situação, entrada e tipo. Considera apenas ramos de saúde e odonto.</div>
    <div id="dash-cot-resumo" class="cc-breakdown"></div>
  </div>

  <!-- Resumo de Fechamentos (no topo) -->
  <div style="margin-bottom:26px">
    <div class="page-title" style="font-size:16px">Resumo de Fechamentos</div>
    <div class="page-sub" style="margin-bottom:10px">Fechamentos por entrada e tipo. Agenciamento e Comissão em R$ (Fatura × %, apenas saúde e odonto).</div>
    <div class="cards" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
      <div class="card c-teal"><div class="card-label">Total</div><div class="card-val" id="dash-fech-total">—</div><div class="card-sub">fechamentos no ano</div><div class="card-icon"><span class="material-symbols-rounded">task_alt</span></div></div>
      <div class="card c-blue"><div class="card-label">Vidas</div><div class="card-val" id="dash-fech-vidas">—</div><div class="card-sub">total de beneficiários</div><div class="card-icon"><span class="material-symbols-rounded">group</span></div></div>
      <div class="card c-green"><div class="card-label">Total fatura</div><div class="card-val" id="dash-fech-valor">—</div><div class="card-sub">somatório no período</div><div class="card-icon"><span class="material-symbols-rounded">payments</span></div></div>
      <div class="card c-orange"><div class="card-label">Novos</div><div class="card-val" id="dash-fech-novos">—</div><div class="card-sub">contratos novos</div><div class="card-icon"><span class="material-symbols-rounded">add_circle</span></div></div>
    </div>
    <div id="dash-fech-resumo" class="cc-breakdown"></div>
  </div>

  <div class="page-header" style="margin-top:6px">
    <div>
      <div class="page-title">Dashboard por Analista</div>
      <div class="page-sub">Visão consolidada da carteira de cada analista. Clique no card para ver detalhes.</div>
    </div>
    <div style="display:flex;gap:8px">
      <select id="dash-modulo" onchange="dashLoad()" title="Qual carteira mostrar">
        <option value="demandas">Demandas</option>
        <option value="faturamento_atual">Faturamento Atual</option>
        <option value="renovacao">Renovação</option>
        <option value="demandas_assistenciais">Demandas Assistenciais</option>
        <option value="acoes_saude">Ações de Saúde</option>
        <option value="cotacao">Controle de Cotação</option>
      </select>
    </div>
  </div>

  <!-- Cards-resumo gerais -->
  <div class="cards" style="grid-template-columns:repeat(4,1fr)">
    <div class="card c-teal"><div class="card-label">Analistas com Carteira</div><div class="card-val" id="dash-tot-anal">—</div><div class="card-sub">com pelo menos 1 item</div><div class="card-icon"><span class="material-symbols-rounded">group</span></div></div>
    <div class="card c-blue"><div class="card-label">Total de Itens</div><div class="card-val" id="dash-tot-itens">—</div><div class="card-sub">na carteira selecionada</div><div class="card-icon"><span class="material-symbols-rounded">inventory_2</span></div></div>
    <div class="card c-green"><div class="card-label">Concluídos</div><div class="card-val" id="dash-tot-conc">—</div><div class="card-sub">total da carteira</div><div class="card-icon"><span class="material-symbols-rounded">task_alt</span></div></div>
    <div class="card c-red"><div class="card-label">Pendentes</div><div class="card-val" id="dash-tot-pend">—</div><div class="card-sub">aguardando ação</div><div class="card-icon"><span class="material-symbols-rounded">pending_actions</span></div></div>
  </div>

  <div id="dash-grid" style="margin-top:18px"></div>
</main>

<!-- ── DRAWER de detalhe do analista (lateral) ─────────────────── -->
<div class="ov" id="mod-dash-detail" onclick="if(event.target===this)closeModal('mod-dash-detail')">
  <div class="modal" style="width:780px;max-width:95vw;max-height:85vh;overflow-y:auto">
    <div class="modal-title" id="dd-title">Detalhe do Analista</div>
    <div class="modal-sub" id="dd-sub"></div>
    <div id="dd-body" style="margin-top:14px"></div>
    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-dash-detail')">Fechar</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>