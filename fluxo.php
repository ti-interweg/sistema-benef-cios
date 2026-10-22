<?php
require_once 'config.php';
auth();
$userName = e($_SESSION['user_nome']);
$userTipo = $_SESSION['user_tipo'];
$isAdmin  = isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Fluxo Comercial - <?= SITE_NAME ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0..1,-50..200"/>
<style>.material-symbols-rounded{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;vertical-align:middle;line-height:1;display:inline-flex;align-items:center}</style>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  :root, body.dark{
    --bg:#0d1117;--bg-card:#161b22;--bg-hover:#1c2128;--border:#30363d;
    --accent:#00c9a7;--accent2:#00a589;--text:#e6edf3;--muted:#8b949e;
    --danger:#f85149;--warn:#d29922;--success:#3fb950;--info:#58a6ff;
  }
  body.light{
    --bg:#f0f2f5;--bg-card:#ffffff;--bg-hover:#f5f5f5;--border:#d0d7de;
    --accent:#00b4a6;--accent2:#009e92;--text:#1f2328;--muted:#656d76;
    --danger:#cf222e;--warn:#bf8700;--success:#1a7f37;--info:#0969da;
  }
  body.light .sidebar{background:#fff;border-right-color:#d0d7de}
  body.light .logo-icon{box-shadow:0 4px 14px rgba(0,180,166,.2)}
  body.light input,body.light select,body.light textarea{background:#f6f8fa;border-color:#d0d7de;color:#1f2328}
  body.light .modal{background:#fff;border-color:#d0d7de;box-shadow:0 24px 64px rgba(0,0,0,.15)}
  body.light ::-webkit-scrollbar-thumb{background:#c1c8cf}
  body{font-family:'Segoe UI',Tahoma,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
  ::-webkit-scrollbar{width:6px;height:6px}
  ::-webkit-scrollbar-track{background:var(--bg)}
  ::-webkit-scrollbar-thumb{background:#30363d;border-radius:4px}

  /* ── SIDEBAR (mesmo do index) ────────────────────── */
  .sidebar{position:fixed;top:0;left:0;width:220px;height:100vh;background:var(--bg-card);border-right:1px solid var(--border);display:flex;flex-direction:column;z-index:100;overflow-y:auto}
  .sidebar-logo{padding:22px 20px 18px;border-bottom:1px solid var(--border)}
  .logo-row{display:flex;align-items:center;gap:10px}
  .logo-icon{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:18px;box-shadow:0 4px 14px rgba(0,201,167,.3);flex-shrink:0}
  .logo-text{font-size:14.5px;font-weight:700;color:var(--text)}
  .logo-text span{color:var(--accent)}
  .logo-sub{font-size:10.5px;color:var(--muted);margin-top:2px;text-transform:uppercase;letter-spacing:.5px}
  .sidebar nav{flex:1;padding:10px 0}
  .nav-section{padding:14px 20px 5px;font-size:10px;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);font-weight:700}
  .nav-item{display:flex;align-items:center;gap:11px;padding:11px 18px;cursor:pointer;font-size:13.5px;transition:all .15s;border-left:3px solid transparent;text-decoration:none;color:var(--muted);border-radius:0}
  .nav-item:hover{background:var(--bg-hover);color:var(--text)}
  .nav-item.active{background:rgba(0,201,167,.1);border-left-color:var(--accent);color:var(--accent)}
  .nav-icon{font-size:16px;flex-shrink:0}
  .sidebar-user{padding:14px 18px;border-top:1px solid var(--border)}
  .user-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}
  .user-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .user-name{font-size:13px;font-weight:600;color:var(--text)}
  .user-role{font-size:11px;color:var(--muted)}
  .btn-logout{display:block;background:var(--bg-hover);border:1px solid var(--border);color:var(--muted);border-radius:8px;padding:8px 14px;font-size:12px;cursor:pointer;text-align:center;text-decoration:none;width:100%;transition:all .15s;font-family:inherit}
  .btn-logout:hover{border-color:var(--danger);color:var(--danger)}

  /* ── MAIN ────────────────────────────────────────── */
  .main{margin-left:220px;padding:28px 32px;min-height:100vh}
  .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
  .page-title{font-size:20px;font-weight:700;color:var(--text)}
  .page-sub{font-size:13px;color:var(--muted);margin-top:3px}

  /* ── PIPELINE KANBAN ─────────────────────────────── */
  .pipeline{display:flex;gap:12px;overflow-x:auto;padding-bottom:12px;min-height:calc(100vh - 200px)}
  .pipe-col{
    min-width:260px;max-width:280px;flex:1;
    background:var(--bg-card);border:1px solid var(--border);border-radius:12px;
    display:flex;flex-direction:column;
  }
  .pipe-header{
    padding:14px 16px;border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;
    border-radius:12px 12px 0 0;
  }
  .pipe-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
  .pipe-count{
    background:var(--bg);border:1px solid var(--border);
    border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;color:var(--muted);
  }
  .pipe-body{flex:1;padding:10px;overflow-y:auto;display:flex;flex-direction:column;gap:8px}

  /* Cards dentro do pipeline */
  .flow-card{
    background:var(--bg);border:1px solid var(--border);border-radius:10px;
    padding:14px 16px;cursor:pointer;transition:all .15s;
    border-left:3px solid transparent;
  }
  .flow-card:hover{border-color:var(--accent);background:var(--bg-hover)}
  .flow-card .fc-empresa{font-size:13px;font-weight:700;color:var(--text);margin-bottom:4px}
  .flow-card .fc-grupo{font-size:11px;color:var(--muted);margin-bottom:8px}
  .flow-card .fc-meta{display:flex;gap:6px;flex-wrap:wrap}
  .fc-tag{
    font-size:10px;padding:2px 8px;border-radius:20px;font-weight:600;
    display:inline-flex;align-items:center;gap:3px;
  }
  .fc-tag.saude{background:rgba(88,166,255,.12);color:var(--info)}
  .fc-tag.odonto{background:rgba(188,140,255,.12);color:#bc8cff}
  .fc-tag.vida{background:rgba(63,185,80,.12);color:#3fb950}
  .fc-tag.vidas{background:rgba(0,201,167,.12);color:var(--accent)}
  .fc-tag.sla-ok{background:rgba(63,185,80,.12);color:var(--success)}
  .fc-tag.sla-atencao{background:rgba(210,153,34,.12);color:var(--warn)}
  .fc-tag.sla-estourado{background:rgba(248,81,73,.12);color:var(--danger)}
  .fc-analista{font-size:11px;color:var(--muted);margin-top:8px;display:flex;align-items:center;gap:5px}
  .fc-pendencia{
    display:flex;align-items:center;gap:5px;margin-top:6px;padding:5px 8px;
    background:rgba(210,153,34,.1);border:1px solid rgba(210,153,34,.25);
    border-radius:6px;font-size:10.5px;color:var(--warn);font-weight:600;
  }
  .fc-pendencia .material-symbols-rounded{font-size:13px}

  /* ── ETAPA COLORS ────────────────────────────────── */
  .pipe-col[data-etapa="prospeccao"] .pipe-header{background:rgba(88,166,255,.08)}
  .pipe-col[data-etapa="prospeccao"] .pipe-title{color:var(--info)}
  .pipe-col[data-etapa="dados_cotacao"] .pipe-header{background:rgba(188,140,255,.08)}
  .pipe-col[data-etapa="dados_cotacao"] .pipe-title{color:#bc8cff}
  .pipe-col[data-etapa="conferencia"] .pipe-header{background:rgba(210,153,34,.08)}
  .pipe-col[data-etapa="conferencia"] .pipe-title{color:var(--warn)}
  .pipe-col[data-etapa="cotacao"] .pipe-header{background:rgba(0,201,167,.08)}
  .pipe-col[data-etapa="cotacao"] .pipe-title{color:var(--accent)}
  .pipe-col[data-etapa="apresentacao"] .pipe-header{background:rgba(63,185,80,.08)}
  .pipe-col[data-etapa="apresentacao"] .pipe-title{color:var(--success)}
  .pipe-col[data-etapa="negociacao"] .pipe-header{background:rgba(255,166,87,.08)}
  .pipe-col[data-etapa="negociacao"] .pipe-title{color:#ffa657}
  .pipe-col[data-etapa="fechamento"] .pipe-header{background:rgba(0,201,167,.12)}
  .pipe-col[data-etapa="fechamento"] .pipe-title{color:var(--accent)}
  .pipe-col[data-etapa="implantacao"] .pipe-header{background:rgba(63,185,80,.12)}
  .pipe-col[data-etapa="implantacao"] .pipe-title{color:var(--success)}

  /* ── STATS BAR ───────────────────────────────────── */
  .stats-bar{display:flex;gap:14px;margin-bottom:20px;flex-wrap:wrap}
  .stat-card{
    background:var(--bg-card);border:1px solid var(--border);border-radius:10px;
    padding:14px 20px;display:flex;align-items:center;gap:12px;min-width:180px;flex:1;
  }
  .stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px}
  .stat-num{font-size:22px;font-weight:800;color:var(--text)}
  .stat-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.3px}

  /* ── MODAL FLUXO ─────────────────────────────────── */
  .ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:flex-start;justify-content:center;padding:24px 0;overflow-y:auto}
  .ov.show{display:flex}
  .modal{background:var(--bg-card);border:1px solid var(--border);border-radius:16px;width:600px;max-width:95vw;padding:28px 30px;box-shadow:0 24px 64px rgba(0,0,0,.6);margin:auto}
  .modal-title{font-size:17px;font-weight:700;color:var(--text);margin-bottom:3px}
  .modal-sub{font-size:13px;color:var(--muted);margin-bottom:20px}
  .sec{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--accent);margin:16px 0 10px;padding-bottom:5px;border-bottom:1px solid rgba(0,201,167,.2)}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:13px}
  .row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:13px}
  .fg{display:flex;flex-direction:column;gap:5px;margin-bottom:13px}
  .fg.full{grid-column:1/-1}
  label{font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.3px}
  input[type=text],input[type=date],input[type=number],select,textarea{
    background:var(--bg);border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;
    font-size:13.5px;color:var(--text);outline:none;width:100%;font-family:inherit;transition:border .15s;
  }
  input:focus,select:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(0,201,167,.1)}
  textarea{resize:vertical;min-height:68px}
  .mfooter{display:flex;gap:10px;justify-content:flex-end;margin-top:16px;padding-top:14px;border-top:1px solid var(--border)}
  .btn{padding:10px 20px;border-radius:9px;font-size:13.5px;cursor:pointer;border:none;font-weight:600;transition:all .2s;display:inline-flex;align-items:center;gap:6px;font-family:inherit}
  .btn-primary{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;box-shadow:0 4px 14px rgba(0,201,167,.25)}
  .btn-primary:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(0,201,167,.35)}
  .btn-outline{background:var(--bg-card);color:var(--muted);border:1px solid var(--border);font-size:13px;padding:7px 14px}
  .btn-outline:hover{border-color:var(--accent);color:var(--accent)}

  /* ── SLA TIMELINE ────────────────────────────────── */
  .sla-info{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:12px 16px;margin:12px 0}
  .sla-row{display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:12px}
  .sla-label{color:var(--muted)}
  .sla-val{font-weight:700;color:var(--text)}
  .sla-val.ok{color:var(--success)}
  .sla-val.atencao{color:var(--warn)}
  .sla-val.estourado{color:var(--danger)}

  /* ── TOAST ───────────────────────────────────────── */
  .toast{position:fixed;bottom:24px;right:24px;background:var(--bg-card);border:1px solid var(--accent);color:var(--text);padding:13px 20px;border-radius:10px;font-size:14px;font-weight:500;box-shadow:0 8px 28px rgba(0,0,0,.4);transform:translateY(70px);opacity:0;transition:all .3s;z-index:999;max-width:320px}
  .toast.show{transform:translateY(0);opacity:1}

  @media(max-width:900px){
    .sidebar{width:56px}
    .nav-item span,.logo-text,.logo-sub,.sidebar-user,.nav-section{display:none}
    .main{margin-left:56px;padding:18px}
    .pipeline{flex-direction:column}
    .pipe-col{min-width:100%;max-width:100%}
  }
</style>
</head>
<body>
<script>document.body.className=localStorage.getItem('iw-theme')||'dark';</script>

<!-- ── SIDEBAR ─────────────────────────────────── -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-row">
      <div class="logo-icon"><span class="material-symbols-rounded" style="font-size:22px;color:#fff">health_and_safety</span></div>
      <div>
        <div class="logo-text">INTER<span>WEG</span></div>
        <div class="logo-sub">Seguros</div>
      </div>
    </div>
  </div>
  <nav>
    <div class="nav-section">Menu</div>
    <a class="nav-item" href="index.php"><span class="material-symbols-rounded nav-icon">assignment</span><span>Demandas</span></a>
    <a class="nav-item active" href="fluxo.php"><span class="material-symbols-rounded nav-icon">account_tree</span><span>Fluxo Comercial</span></a>
    <?php if ($isAdmin): ?>
    <a class="nav-item" href="index.php#analistas"><span class="material-symbols-rounded nav-icon">group</span><span>Analistas</span></a>
    <a class="nav-item" href="index.php#cadastros"><span class="material-symbols-rounded nav-icon">edit_note</span><span>Cadastros</span></a>
    <a class="nav-item" href="index.php#config"><span class="material-symbols-rounded nav-icon">settings</span><span>Configurações</span></a>
    <?php endif; ?>
    <?php if ($isAdmin || stripos($userName, 'Anna Luiza') !== false): ?>
    <div class="nav-section">Ferramentas</div>
    <a class="nav-item" href="https://app.cotaco.es/cotacoes/" target="_blank"><span class="material-symbols-rounded nav-icon">request_quote</span><span>Cotações</span></a>
    <a class="nav-item" href="index.php#apresentacao"><span class="material-symbols-rounded nav-icon">slideshow</span><span>Apresentação</span></a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-user">
    <div class="user-row">
      <div class="user-avatar"><?= mb_substr($userName,0,2) ?></div>
      <div>
        <div class="user-name"><?= $userName ?></div>
        <div class="user-role"><?= $isAdmin ? 'Admin' : 'Analista' ?></div>
      </div>
    </div>
    <div style="display:flex;gap:6px;margin-bottom:10px">
      <button onclick="setTheme('light')" id="btn-light" class="btn-logout" style="flex:1;padding:6px 0;font-size:11px"><span class="material-symbols-rounded" style="font-size:15px">light_mode</span> Claro</button>
      <button onclick="setTheme('dark')" id="btn-dark" class="btn-logout" style="flex:1;padding:6px 0;font-size:11px"><span class="material-symbols-rounded" style="font-size:15px">dark_mode</span> Escuro</button>
    </div>
    <a href="logout.php" class="btn-logout"><span class="material-symbols-rounded" style="font-size:16px">logout</span> Sair da conta</a>
  </div>
</aside>

<!-- ── MAIN ────────────────────────────────────── -->
<div class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Fluxo Comercial</div>
      <div class="page-sub">Pipeline de demandas comerciais · Saúde / Odonto / Vida</div>
    </div>
    <button class="btn btn-primary" onclick="abrirNovaDemanda()">
      <span class="material-symbols-rounded" style="font-size:17px">add</span> Nova Demanda
    </button>
  </div>

  <!-- Stats -->
  <div class="stats-bar">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(88,166,255,.12)"><span class="material-symbols-rounded" style="color:var(--info)">inbox</span></div>
      <div><div class="stat-num" id="st-total">0</div><div class="stat-label">Total Ativas</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(0,201,167,.12)"><span class="material-symbols-rounded" style="color:var(--accent)">schedule</span></div>
      <div><div class="stat-num" id="st-prazo">0</div><div class="stat-label">No Prazo</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(210,153,34,.12)"><span class="material-symbols-rounded" style="color:var(--warn)">warning</span></div>
      <div><div class="stat-num" id="st-atencao">0</div><div class="stat-label">Atenção SLA</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(248,81,73,.12)"><span class="material-symbols-rounded" style="color:var(--danger)">error</span></div>
      <div><div class="stat-num" id="st-estourado">0</div><div class="stat-label">SLA Estourado</div></div>
    </div>
  </div>

  <!-- Pipeline Kanban -->
  <div class="pipeline" id="pipeline">
    <div class="pipe-col" data-etapa="prospeccao">
      <div class="pipe-header"><span class="pipe-title">Prospecção</span><span class="pipe-count" id="cnt-prospeccao">0</span></div>
      <div class="pipe-body" id="col-prospeccao"></div>
    </div>
    <div class="pipe-col" data-etapa="dados_cotacao">
      <div class="pipe-header"><span class="pipe-title">Dados p/ Cotação</span><span class="pipe-count" id="cnt-dados_cotacao">0</span></div>
      <div class="pipe-body" id="col-dados_cotacao"></div>
    </div>
    <div class="pipe-col" data-etapa="conferencia">
      <div class="pipe-header"><span class="pipe-title">Conferência <small style="opacity:.5">D+1</small></span><span class="pipe-count" id="cnt-conferencia">0</span></div>
      <div class="pipe-body" id="col-conferencia"></div>
    </div>
    <div class="pipe-col" data-etapa="cotacao">
      <div class="pipe-header"><span class="pipe-title">Cotação <small style="opacity:.5">D+2</small></span><span class="pipe-count" id="cnt-cotacao">0</span></div>
      <div class="pipe-body" id="col-cotacao"></div>
    </div>
    <div class="pipe-col" data-etapa="apresentacao">
      <div class="pipe-header"><span class="pipe-title">Apresentação</span><span class="pipe-count" id="cnt-apresentacao">0</span></div>
      <div class="pipe-body" id="col-apresentacao"></div>
    </div>
    <div class="pipe-col" data-etapa="negociacao">
      <div class="pipe-header"><span class="pipe-title">Negociação</span><span class="pipe-count" id="cnt-negociacao">0</span></div>
      <div class="pipe-body" id="col-negociacao"></div>
    </div>
    <div class="pipe-col" data-etapa="fechamento">
      <div class="pipe-header"><span class="pipe-title">Fechamento</span><span class="pipe-count" id="cnt-fechamento">0</span></div>
      <div class="pipe-body" id="col-fechamento"></div>
    </div>
    <div class="pipe-col" data-etapa="implantacao">
      <div class="pipe-header"><span class="pipe-title">Implantação</span><span class="pipe-count" id="cnt-implantacao">0</span></div>
      <div class="pipe-body" id="col-implantacao"></div>
    </div>
  </div>
</div>

<!-- ── MODAL NOVA DEMANDA / EDITAR ──────────────── -->
<div class="ov" id="ov-fluxo" onclick="if(event.target===this)fecharModal()">
  <div class="modal">
    <div class="modal-title" id="fluxo-modal-title">Nova Demanda Comercial</div>
    <div class="modal-sub">Preencha os dados para iniciar o fluxo</div>
    <input type="hidden" id="fl-id" value="">

    <div class="sec">Dados da Empresa</div>
    <div class="row2">
      <div class="fg"><label>Grupo</label><input type="text" id="fl-grupo" placeholder="Grupo econômico"></div>
      <div class="fg"><label>Empresa</label><input type="text" id="fl-empresa" placeholder="Razão social"></div>
    </div>
    <div class="row3">
      <div class="fg"><label>Ramo</label>
        <select id="fl-ramo" onchange="atualizarSLAInfo()">
          <option value="">Selecione</option>
          <option value="Saúde">Saúde</option>
          <option value="Odonto">Odonto</option>
          <option value="Vida">Vida</option>
        </select>
      </div>
      <div class="fg"><label>Nº de Vidas</label><input type="number" id="fl-vidas" placeholder="Ex: 30" min="1" onchange="atualizarSLAInfo()"></div>
      <div class="fg"><label>Seguradora</label><input type="text" id="fl-seguradora" placeholder="Ex: Amil"></div>
    </div>

    <div class="sec">Fluxo</div>
    <div class="row2">
      <div class="fg"><label>Etapa Atual</label>
        <select id="fl-etapa" onchange="atualizarSLAInfo()">
          <option value="prospeccao">Prospecção</option>
          <option value="dados_cotacao">Dados p/ Cotação</option>
          <option value="conferencia">Conferência (D+1)</option>
          <option value="cotacao">Cotação (D+2)</option>
          <option value="apresentacao">Apresentação</option>
          <option value="negociacao">Negociação</option>
          <option value="fechamento">Fechamento</option>
          <option value="implantacao">Implantação</option>
        </select>
      </div>
      <div class="fg"><label>Data Recebimento</label><input type="date" id="fl-recebimento" onchange="atualizarSLAInfo()"></div>
    </div>

    <!-- SLA Info automática -->
    <div class="sla-info" id="sla-info" style="display:none">
      <div style="font-size:11px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">SLA Previsto</div>
      <div class="sla-row"><span class="sla-label">Conferência (D+1)</span><span class="sla-val" id="sla-conf">-</span></div>
      <div class="sla-row"><span class="sla-label">Cotação</span><span class="sla-val" id="sla-cot">-</span></div>
      <div class="sla-row"><span class="sla-label">Tipo Apresentação</span><span class="sla-val" id="sla-apres">-</span></div>
    </div>

    <div class="row2">
      <div class="fg"><label>Analista</label>
        <select id="fl-analista"><option value="">Selecione</option></select>
      </div>
      <div class="fg"><label>Responsável Pós-Venda</label><input type="text" id="fl-posvenda" placeholder="Ex: Letícia"></div>
    </div>
    <div class="fg full"><label>Observações</label><textarea id="fl-obs" placeholder="Informações adicionais..."></textarea></div>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="fecharModal()">Cancelar</button>
      <button class="btn btn-primary" onclick="salvarDemandaFluxo()">
        <span class="material-symbols-rounded" style="font-size:16px">save</span> Salvar
      </button>
    </div>
  </div>
</div>

<!-- ── TOAST ────────────────────────────────────── -->
<div class="toast" id="toast-el"></div>

<script>
// ═══════════════════════════════════════════════════
//  CONSTANTES E ETAPAS
// ═══════════════════════════════════════════════════
const ETAPAS = [
  {key:'prospeccao',    label:'Prospecção'},
  {key:'dados_cotacao', label:'Dados p/ Cotação'},
  {key:'conferencia',   label:'Conferência'},
  {key:'cotacao',       label:'Cotação'},
  {key:'apresentacao',  label:'Apresentação'},
  {key:'negociacao',    label:'Negociação'},
  {key:'fechamento',    label:'Fechamento'},
  {key:'implantacao',   label:'Implantação'},
];

const ETAPA_LABELS = {};
ETAPAS.forEach(e => ETAPA_LABELS[e.key] = e.label);

let demandas = [];

// ═══════════════════════════════════════════════════
//  SLA - Cálculo baseado no fluxo comercial
// ═══════════════════════════════════════════════════

// Adiciona N dias úteis a uma data
function addDiasUteis(date, dias) {
  const d = new Date(date);
  let added = 0;
  while (added < dias) {
    d.setDate(d.getDate() + 1);
    const dow = d.getDay();
    if (dow !== 0 && dow !== 6) added++;
  }
  return d;
}

function formatDate(d) {
  if (!d) return '-';
  const dt = (d instanceof Date) ? d : new Date(d + 'T12:00:00');
  return dt.toLocaleDateString('pt-BR');
}

// Calcula SLA baseado no ramo e vidas
function calcularSLA(ramo, vidas, dataRecebimento) {
  if (!dataRecebimento) return null;
  const rec = new Date(dataRecebimento + 'T12:00:00');

  // Conferência: sempre D+1
  const conferencia = addDiasUteis(rec, 1);

  // Cotação: depende do ramo e vidas
  let cotacao;
  let diasCotacao;
  if (ramo === 'Vida') {
    // Vida: D+2 (portal) ou D+2 + 5 dias úteis (email)
    cotacao = addDiasUteis(rec, 2 + 5); // pior caso
    diasCotacao = 'D+2 a D+7';
  } else {
    // Saúde/Odonto
    vidas = parseInt(vidas) || 0;
    if (vidas < 30) {
      cotacao = addDiasUteis(rec, 2);
      diasCotacao = 'D+2';
    } else if (vidas < 100) {
      cotacao = addDiasUteis(rec, 2 + 10); // pior caso: email = 7-10 dias
      diasCotacao = 'D+2 a D+12';
    } else {
      cotacao = addDiasUteis(rec, 2 + 10);
      diasCotacao = 'D+2 + 7-10 dias úteis';
    }
  }

  // Tipo de apresentação
  let tipoApres;
  if (ramo === 'Vida') {
    tipoApres = 'estudo_comparativo'; // Vida: sempre estudo
  } else {
    vidas = parseInt(vidas) || 0;
    tipoApres = vidas >= 30 ? 'estudo_comparativo' : 'link';
  }

  return {
    conferencia,
    cotacao,
    diasCotacao,
    tipoApres,
    tipoApresLabel: tipoApres === 'estudo_comparativo' ? 'Estudo Comparativo' : 'Link da Plataforma'
  };
}

// Calcula status do SLA (no_prazo, atencao, estourado)
function calcSLAStatus(slaPrevisto) {
  if (!slaPrevisto) return 'no_prazo';
  const hoje = new Date();
  hoje.setHours(0,0,0,0);
  const sla = new Date(slaPrevisto + 'T12:00:00');
  const diff = Math.floor((sla - hoje) / (1000*60*60*24));
  if (diff < 0) return 'estourado';
  if (diff <= 1) return 'atencao';
  return 'no_prazo';
}

// ═══════════════════════════════════════════════════
//  ATUALIZAR PREVIEW DE SLA NO MODAL
// ═══════════════════════════════════════════════════
function atualizarSLAInfo() {
  const ramo = document.getElementById('fl-ramo').value;
  const vidas = document.getElementById('fl-vidas').value;
  const rec = document.getElementById('fl-recebimento').value;
  const info = document.getElementById('sla-info');

  if (!ramo || !rec) { info.style.display = 'none'; return; }

  const sla = calcularSLA(ramo, vidas, rec);
  if (!sla) { info.style.display = 'none'; return; }

  info.style.display = 'block';
  document.getElementById('sla-conf').textContent = formatDate(sla.conferencia);
  document.getElementById('sla-cot').textContent = formatDate(sla.cotacao) + ' (' + sla.diasCotacao + ')';
  document.getElementById('sla-apres').textContent = sla.tipoApresLabel;
  document.getElementById('sla-apres').className = 'sla-val ' + (sla.tipoApres === 'estudo_comparativo' ? 'atencao' : 'ok');
}

// ═══════════════════════════════════════════════════
//  CARREGAR DADOS
// ═══════════════════════════════════════════════════
async function carregarDemandas() {
  try {
    const r = await fetch('api.php?action=listar_fluxo');
    const j = await r.json();
    if (j.ok) {
      demandas = j.data || [];
      renderPipeline();
    }
  } catch(e) {
    console.error('Erro ao carregar:', e);
    // Fallback: carregar do endpoint normal e filtrar
    try {
      const r2 = await fetch('api.php?action=listar');
      const j2 = await r2.json();
      if (j2.ok) {
        demandas = (j2.data || []).filter(d => d.situacao !== 'Concluído');
        renderPipeline();
      }
    } catch(e2) { console.error(e2); }
  }
}

async function carregarAnalistas() {
  try {
    const r = await fetch('api.php?action=analistas');
    const j = await r.json();
    if (j.ok) {
      const sel = document.getElementById('fl-analista');
      sel.innerHTML = '<option value="">Selecione</option>';
      (j.data || []).forEach(a => {
        sel.innerHTML += `<option value="${a.id}">${esc(a.nome)}</option>`;
      });
    }
  } catch(e) {}
}

// ═══════════════════════════════════════════════════
//  RENDER PIPELINE
// ═══════════════════════════════════════════════════
function renderPipeline() {
  // Limpar colunas
  ETAPAS.forEach(e => {
    document.getElementById('col-' + e.key).innerHTML = '';
    document.getElementById('cnt-' + e.key).textContent = '0';
  });

  // Contadores SLA
  let total = 0, prazo = 0, atencao = 0, estourado = 0;

  // Agrupar por etapa
  const grupos = {};
  ETAPAS.forEach(e => grupos[e.key] = []);

  demandas.forEach(d => {
    const etapa = d.etapa || 'prospeccao';
    if (!grupos[etapa]) grupos[etapa] = [];
    grupos[etapa].push(d);
    total++;

    const slaStatus = d.sla_status || calcSLAStatus(d.sla_previsto);
    if (slaStatus === 'estourado') estourado++;
    else if (slaStatus === 'atencao') atencao++;
    else prazo++;
  });

  // Render cada coluna
  ETAPAS.forEach(e => {
    const items = grupos[e.key] || [];
    document.getElementById('cnt-' + e.key).textContent = items.length;
    const col = document.getElementById('col-' + e.key);

    items.forEach(d => {
      const slaStatus = d.sla_status || calcSLAStatus(d.sla_previsto);
      const ramoClass = (d.ramo||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
      const vidas = d.vidas ? d.vidas + ' vidas' : '';

      const card = document.createElement('div');
      card.className = 'flow-card';
      card.style.borderLeftColor = slaStatus === 'estourado' ? 'var(--danger)' : slaStatus === 'atencao' ? 'var(--warn)' : 'var(--accent)';
      card.onclick = () => editarDemandaFluxo(d);
      const isPendente = d.pendencia == 1;
      const motivoPend = d.motivo_pendencia || d.situacao || '';
      card.innerHTML = `
        <div class="fc-empresa">${esc(d.empresa || d.grupo || 'Sem nome')}</div>
        <div class="fc-grupo">${esc(d.grupo || '')}</div>
        <div class="fc-meta">
          ${d.ramo ? '<span class="fc-tag '+ramoClass+'">'+esc(d.ramo)+'</span>' : ''}
          ${vidas ? '<span class="fc-tag vidas">'+esc(vidas)+'</span>' : ''}
          <span class="fc-tag sla-${slaStatus}">${slaStatus === 'estourado' ? 'SLA Estourado' : slaStatus === 'atencao' ? 'Atenção' : 'No Prazo'}</span>
        </div>
        ${isPendente ? '<div class="fc-pendencia"><span class="material-symbols-rounded">pause_circle</span>'+esc(motivoPend)+'</div>' : ''}
        ${d.analista_nome ? '<div class="fc-analista"><span class="material-symbols-rounded" style="font-size:14px">person</span>'+esc(d.analista_nome)+'</div>' : ''}
      `;
      col.appendChild(card);
    });
  });

  // Stats
  document.getElementById('st-total').textContent = total;
  document.getElementById('st-prazo').textContent = prazo;
  document.getElementById('st-atencao').textContent = atencao;
  document.getElementById('st-estourado').textContent = estourado;
}

// ═══════════════════════════════════════════════════
//  MODAL
// ═══════════════════════════════════════════════════
function abrirNovaDemanda() {
  document.getElementById('fluxo-modal-title').textContent = 'Nova Demanda Comercial';
  document.getElementById('fl-id').value = '';
  document.getElementById('fl-grupo').value = '';
  document.getElementById('fl-empresa').value = '';
  document.getElementById('fl-ramo').value = '';
  document.getElementById('fl-vidas').value = '';
  document.getElementById('fl-seguradora').value = '';
  document.getElementById('fl-etapa').value = 'prospeccao';
  document.getElementById('fl-recebimento').value = new Date().toISOString().split('T')[0];
  document.getElementById('fl-analista').value = '';
  document.getElementById('fl-posvenda').value = '';
  document.getElementById('fl-obs').value = '';
  document.getElementById('sla-info').style.display = 'none';
  document.getElementById('ov-fluxo').classList.add('show');
}

function editarDemandaFluxo(d) {
  document.getElementById('fluxo-modal-title').textContent = 'Editar Demanda · ' + (d.empresa || d.grupo || '#'+d.id);
  document.getElementById('fl-id').value = d.id;
  document.getElementById('fl-grupo').value = d.grupo || '';
  document.getElementById('fl-empresa').value = d.empresa || '';
  document.getElementById('fl-ramo').value = d.ramo || '';
  document.getElementById('fl-vidas').value = d.vidas || '';
  document.getElementById('fl-seguradora').value = d.seguradora || '';
  document.getElementById('fl-etapa').value = d.etapa || 'prospeccao';
  document.getElementById('fl-recebimento').value = d.recebimento || '';
  document.getElementById('fl-analista').value = d.analista_id || '';
  document.getElementById('fl-posvenda').value = d.responsavel_posvenda || '';
  document.getElementById('fl-obs').value = d.obs_fluxo || d.detalhamento || '';
  atualizarSLAInfo();
  document.getElementById('ov-fluxo').classList.add('show');
}

function fecharModal() {
  document.getElementById('ov-fluxo').classList.remove('show');
}

// ═══════════════════════════════════════════════════
//  SALVAR
// ═══════════════════════════════════════════════════
async function salvarDemandaFluxo() {
  const id = document.getElementById('fl-id').value;
  const ramo = document.getElementById('fl-ramo').value;
  const vidas = document.getElementById('fl-vidas').value;
  const rec = document.getElementById('fl-recebimento').value;
  const etapa = document.getElementById('fl-etapa').value;

  // Calcular SLA e tipo apresentação
  const sla = calcularSLA(ramo, vidas, rec);
  let slaPrevisto = null;
  let tipoApres = null;
  if (sla) {
    slaPrevisto = sla.cotacao.toISOString().split('T')[0];
    tipoApres = sla.tipoApres;
  }

  const body = {
    id: id || undefined,
    grupo: document.getElementById('fl-grupo').value,
    empresa: document.getElementById('fl-empresa').value,
    seguradora: document.getElementById('fl-seguradora').value,
    ramo,
    vidas: vidas ? parseInt(vidas) : null,
    etapa,
    tipo_apresentacao: tipoApres,
    sla_previsto: slaPrevisto,
    recebimento: rec,
    analista_id: document.getElementById('fl-analista').value || null,
    responsavel_posvenda: document.getElementById('fl-posvenda').value,
    obs_fluxo: document.getElementById('fl-obs').value,
  };

  const action = id ? 'editar_fluxo' : 'criar_fluxo';

  try {
    const r = await fetch('api.php?action=' + action, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(body)
    });
    const j = await r.json();
    if (j.ok) {
      toast(id ? 'Demanda atualizada!' : 'Demanda criada!');
      fecharModal();
      carregarDemandas();
    } else {
      toast(j.msg || 'Erro ao salvar');
    }
  } catch(e) {
    toast('Erro de conexão');
  }
}

// ═══════════════════════════════════════════════════
//  UTILS
// ═══════════════════════════════════════════════════
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

function toast(msg) {
  const el = document.getElementById('toast-el');
  el.textContent = msg;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 3000);
}

// ═══════════════════════════════════════════════════
//  INIT
// ═══════════════════════════════════════════════════
carregarAnalistas();
carregarDemandas();

// ── TEMA CLARO/ESCURO ──
function setTheme(t){
  document.body.className=t;
  localStorage.setItem('iw-theme',t);
  document.getElementById('btn-light').style.borderColor=t==='light'?'var(--accent)':'';
  document.getElementById('btn-light').style.color=t==='light'?'var(--accent)':'';
  document.getElementById('btn-dark').style.borderColor=t==='dark'?'var(--accent)':'';
  document.getElementById('btn-dark').style.color=t==='dark'?'var(--accent)':'';
}
(function(){const t=localStorage.getItem('iw-theme')||'dark';setTheme(t)})();
</script>
</body>
</html>
