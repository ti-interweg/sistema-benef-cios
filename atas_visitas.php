<?php
$currentPage = 'atas_visitas';
$pageTitle   = 'Atas de Visitas — Interweg Benefícios';
$pageScripts = ['assets/atas_visitas.js'];

require_once __DIR__ . '/_header.php';
// Acesso: admin OU visualizador (read-only)
if (!$canViewAll && !$isRenovacao) { header('Location: index.php'); exit; }
?>

<style>
  #mod-ata .fg{margin-bottom:14px;gap:5px}
  #mod-ata input[type=text],
  #mod-ata input[type=date],
  #mod-ata input[type=time],
  #mod-ata select,
  #mod-ata textarea{padding:8px 11px;font-size:13px;border-radius:7px;width:100%}
  #mod-ata label{font-size:11px}
  #mod-ata textarea{min-height:60px;resize:vertical;font-family:inherit;line-height:1.5}

  /* Botão "Expandir" ao lado dos labels dos textareas grandes */
  .btn-expand{background:transparent;border:1px solid var(--border);color:var(--muted);padding:3px 8px;border-radius:4px;font-size:10.5px;cursor:pointer;display:inline-flex;align-items:center;gap:3px;font-weight:600;text-transform:none;letter-spacing:0;transition:all .15s}
  .btn-expand:hover{border-color:var(--accent);color:var(--accent);background:rgba(0,191,179,.06)}

  /* Modal de expansão — overlay em cima do modal de ata */
  #mod-expand{position:fixed;inset:0;background:rgba(0,0,0,.78);z-index:1100;display:none;align-items:center;justify-content:center;padding:24px}
  #mod-expand.show{display:flex}
  #mod-expand .exp-box{background:var(--bg-card);border-radius:12px;box-shadow:0 24px 64px rgba(0,0,0,.6);width:95vw;max-width:1100px;height:90vh;display:flex;flex-direction:column;overflow:hidden}
  #mod-expand .exp-head{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;border-bottom:1px solid var(--border)}
  #mod-expand .exp-title{font-size:16px;font-weight:700;color:var(--text)}
  #mod-expand textarea{flex:1;width:100%;border:none;outline:none;padding:22px 28px;font-size:14px;line-height:1.65;font-family:inherit;background:var(--bg);color:var(--text);resize:none}
  #mod-expand .exp-foot{display:flex;justify-content:space-between;align-items:center;padding:12px 22px;border-top:1px solid var(--border);background:var(--bg)}
  #mod-expand .exp-info{font-size:11.5px;color:var(--muted)}
  #mod-ata .row2,
  #mod-ata .row3,
  #mod-ata .row4{gap:18px;margin-bottom:6px}
  #mod-ata .sec{margin:14px 0 8px;padding-bottom:3px;font-size:11px;text-transform:uppercase;letter-spacing:.6px;font-weight:700;color:var(--accent);border-bottom:1px solid var(--border)}

  /* Bloco de encaminhamentos (sub-tabela dentro do modal) */
  #ata-encs-wrap{border:1px solid var(--border);border-radius:8px;padding:10px;background:rgba(0,0,0,.02)}
  #ata-encs-list{display:flex;flex-direction:column;gap:8px}
  .enc-row{display:flex;flex-direction:column;gap:8px;padding:10px;background:var(--bg-card);border:1px solid var(--border);border-radius:6px}
  .enc-row textarea[name="desc"]{min-height:90px;font-size:12.5px;line-height:1.45;resize:vertical;font-family:inherit;width:100%}
  .enc-row textarea{min-height:36px;font-size:12px}
  .enc-row input,.enc-row select{font-size:12px;padding:6px 8px}
  /* Mini-label discreto acima de cada campo da linha de baixo */
  .enc-mini-lbl{font-size:9.5px;font-weight:700;text-transform:uppercase;color:var(--muted);letter-spacing:.4px;margin-bottom:2px;display:block}
  /* Linha de baixo: responsável (stack) | prazo | status | demanda | remover */
  .enc-row-bottom{display:grid;grid-template-columns:1fr 130px 130px 130px 36px;gap:10px;align-items:flex-start}
  /* Stack vertical na coluna Responsável: interno (dropdown) + externo (texto) */
  .enc-resp-stack{display:flex;flex-direction:column;gap:4px}
  .enc-resp-stack select,.enc-resp-stack input{width:100%}
  /* Cada coluna ocupa altura igual e os inputs/botões ficam alinhados no topo */
  .enc-row-bottom > div{display:flex;flex-direction:column}
  .enc-row .enc-action{display:flex;flex-direction:column}
  /* Botão "Gerar demanda" do tamanho de um input pra alinhar visualmente */
  .enc-row .enc-gerar{font-size:11px;padding:7px 10px;width:100%;background:rgba(0,191,179,.12);color:var(--accent);border:1px solid var(--accent);border-radius:6px;cursor:pointer;text-align:center;line-height:1.2;height:32px;display:flex;align-items:center;justify-content:center;font-weight:600}
  /* Botão X (remover) alinhado com o input (não com label) */
  .enc-row .enc-rm{align-self:flex-start;margin-top:18px}
  .enc-row .enc-rm{background:rgba(220,53,69,.12);color:#dc3545;border:none;width:32px;height:32px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center}
  .enc-row .enc-rm:hover{background:rgba(220,53,69,.24)}
  .enc-row .enc-gerar:hover{background:rgba(0,191,179,.22)}
  .enc-row .enc-gerar.done{background:rgba(40,167,69,.12);color:#28a745;border-color:#28a745;cursor:default;font-weight:600}
  .enc-row .enc-gerar.done:hover{background:rgba(40,167,69,.18)}

  /* Cabeçalho da grid escondido — cada linha tem mini-labels próprios agora */
  #ata-encs-head{display:none}

  /* ── Filtros de data (DATA VISITA: DE / ATÉ) ───────────────────── */
  /* Wrapper com label em cima do input — anula o flex-basis vertical */
  .filters .date-filter{
    display:flex;
    flex-direction:column;
    gap:3px;
    flex:0 0 auto;
    align-self:flex-start;
  }
  .filters .date-filter label{
    font-size:10px;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:.5px;
    font-weight:700;
  }
  /* Reset crítico: anula o `flex:0 1 160px` herdado de app.css que vira ALTURA
     dentro do wrapper flex-column. Força largura fixa e altura natural. */
  .filters .date-filter input[type=date]{
    flex:0 0 auto !important;
    width:150px !important;
    height:auto !important;
    min-width:0 !important;
    padding:8px 10px;
    font-size:13px;
    border:1.5px solid var(--border);
    border-radius:8px;
    background:var(--bg);
    color:var(--text);
    cursor:pointer;
    transition:border-color .15s;
    box-sizing:border-box;
  }
  .filters .date-filter input[type=date]:hover{border-color:var(--accent)}
  .filters .date-filter input[type=date]:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(0,191,179,.12)}

  /* ── Botões de ação na lista de atas (ver / editar / imprimir / excluir) ── */
  .btn-acao{
    background:transparent;
    border:1px solid var(--border);
    color:var(--muted);
    width:32px;
    height:32px;
    border-radius:7px;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    margin:0 2px;
    transition:all .15s;
    padding:0;
  }
  .btn-acao:hover{transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,.08)}
  .btn-acao.btn-ver{color:var(--accent)}
  .btn-acao.btn-ver:hover{background:rgba(0,191,179,.12);border-color:var(--accent)}
  .btn-acao.btn-del{color:#dc3545}
  .btn-acao.btn-del:hover{background:rgba(220,53,69,.12);border-color:#dc3545}

  /* Percepção - botões grandes */
  .percep-group{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
  .percep-opt{padding:10px;border:2px solid var(--border);border-radius:8px;background:var(--bg-card);cursor:pointer;text-align:center;font-size:12px;font-weight:600;transition:all .15s}
  .percep-opt:hover{border-color:var(--accent)}
  .percep-opt.sel{background:rgba(0,191,179,.12);border-color:var(--accent);color:var(--accent)}
  .percep-opt[data-v="Insatisfeito"].sel,
  .percep-opt[data-v="Preocupado com reajuste"].sel{background:rgba(220,53,69,.12);border-color:#dc3545;color:#dc3545}
  .percep-opt[data-v="Neutro"].sel{background:rgba(255,193,7,.12);border-color:#ffc107;color:#b8860b}

  /* Cards específicos */
  .badge-percep{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700}
  .bp-sat{background:rgba(40,167,69,.18);color:#28a745}
  .bp-neu{background:rgba(255,193,7,.18);color:#b8860b}
  .bp-ins{background:rgba(220,53,69,.18);color:#dc3545}
  .bp-pre{background:rgba(255,87,34,.18);color:#ff5722}
  .badge-status{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700}
  .bs-rasc{background:rgba(108,117,125,.18);color:#6c757d}
  .bs-fin{background:rgba(0,191,179,.18);color:var(--accent)}
  .bs-ass{background:rgba(40,167,69,.18);color:#28a745}
  /* ── Abas Programação ── */
  .ava-tabs{display:flex;gap:6px;border-bottom:1px solid var(--border);margin:4px 0 20px;flex-wrap:wrap}
  .ava-tab{display:inline-flex;align-items:center;gap:7px;padding:10px 16px;border:none;background:none;cursor:pointer;font-size:13.5px;font-weight:700;color:var(--muted);border-bottom:3px solid transparent;margin-bottom:-1px;border-radius:8px 8px 0 0}
  .ava-tab:hover{color:var(--text)}
  .ava-tab.active{color:var(--accent);border-bottom-color:var(--accent)}
  .prog-bar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:10px}
  .prog-nav{display:flex;align-items:center;gap:8px}
  .prog-range{font-weight:800;font-size:14px;color:var(--text);min-width:210px;text-align:center}
  .prog-totais{display:flex;gap:8px;flex-wrap:wrap}
  .prog-chip{background:var(--bg-card);border:1px solid var(--border);border-radius:20px;padding:5px 12px;font-size:12px;font-weight:700}
  .prog-chip b{color:var(--accent2)}
  .prog-hint{background:rgba(0,180,166,.07);border:1px solid rgba(0,180,166,.25);border-radius:10px;padding:9px 14px;font-size:12.5px;color:var(--muted);margin-bottom:14px}
  .prog-kanban{display:grid;grid-template-columns:repeat(6,1fr);gap:12px}
  @media(max-width:1100px){.prog-kanban{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:680px){.prog-kanban{grid-template-columns:1fr}}
  .prog-col{background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:10px;min-height:120px}
  .prog-col.hoje{border-color:var(--accent);box-shadow:0 0 0 2px rgba(0,180,166,.15)}
  .prog-col-head{display:flex;align-items:baseline;justify-content:space-between;margin-bottom:8px}
  .prog-col-head .d{font-weight:800;font-size:13px}
  .prog-col-head .dt{font-size:11px;color:var(--muted)}
  .prog-card{background:var(--bg-card);border:1px solid var(--border);border-radius:10px;padding:10px 11px;margin-bottom:8px;cursor:pointer;transition:transform .1s,box-shadow .1s}
  .prog-card:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.08)}
  .prog-card .emp{font-weight:700;font-size:12.5px;color:var(--text);line-height:1.25}
  .prog-card .sub{font-size:11px;color:var(--muted);margin-top:2px}
  .prog-vazio{color:var(--muted);font-size:11.5px;text-align:center;padding:14px 0}
  .prog-bdg{display:inline-block;margin-top:6px;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:800}
  .b-agend{background:#fff3cd;color:#7a5d00}.b-real{background:#d3f2d8;color:#256029}
  .b-rasc{background:#e9ecef;color:#555}.b-canc{background:#fdecec;color:#c0392b}
  /* Planejado vs Realizado */
  .pvr-row{display:grid;grid-template-columns:150px 1fr 90px;gap:12px;align-items:center;padding:9px 4px;border-bottom:1px solid var(--border);font-size:13px}
  .pvr-nm{font-weight:700}
  .pvr-bar{height:20px;background:rgba(0,0,0,.05);border-radius:6px;overflow:hidden;display:flex}
  .pvr-bar .real{background:linear-gradient(135deg,var(--accent),var(--accent2))}
  .pvr-bar .agend{background:#ffd54f}
  .pvr-leg{font-size:12px;color:var(--muted);margin:6px 0 14px;display:flex;gap:16px}
  .pvr-leg span::before{content:"";display:inline-block;width:11px;height:11px;border-radius:3px;margin-right:5px;vertical-align:-1px}
  .pvr-leg .lr::before{background:var(--accent)}.pvr-leg .la::before{background:#ffd54f}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Atas de Visitas</div>
      <div class="page-sub">Registro de atas das visitas presenciais e online aos clientes</div>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-outline" onclick="ataExportXLSX()" title="Exporta a lista atual para Excel"><span class="material-symbols-rounded" style="font-size:17px">download</span> Exportar</button>
      <?php if (!$isVisualizador): ?>
      <button class="btn btn-primary" onclick="ataOpenModal()"><span class="material-symbols-rounded" style="font-size:19px">add</span> Nova Ata</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="ava-tabs">
    <button class="ava-tab active" data-tab="atas" onclick="avaTab('atas')"><span class="material-symbols-rounded" style="font-size:17px">list_alt</span> Atas</button>
    <button class="ava-tab" data-tab="prog" onclick="avaTab('prog')"><span class="material-symbols-rounded" style="font-size:17px">calendar_month</span> Programação da semana</button>
    <button class="ava-tab" data-tab="pvr" onclick="avaTab('pvr')"><span class="material-symbols-rounded" style="font-size:17px">insights</span> Planejado vs Realizado</button>
  </div>

  <div id="ava-pane-atas" class="ava-pane">
  <div class="cards">
    <div class="card c-teal clickable" onclick="ataFiltroPor('total')"><div class="card-label">Total</div><div class="card-val" id="ata-tot">—</div><div class="card-sub">atas registradas</div><div class="card-icon"><span class="material-symbols-rounded">edit_document</span></div></div>
    <div class="card c-blue clickable" onclick="ataFiltroPor('mes_atual')"><div class="card-label">Mês atual</div><div class="card-val" id="ata-mes">—</div><div class="card-sub">visitas do mês</div><div class="card-icon"><span class="material-symbols-rounded">event</span></div></div>
    <div class="card c-green"><div class="card-label">Presenciais</div><div class="card-val" id="ata-pres">—</div><div class="card-sub">in loco</div><div class="card-icon"><span class="material-symbols-rounded">handshake</span></div></div>
    <div class="card c-yellow"><div class="card-label">Online</div><div class="card-val" id="ata-onl">—</div><div class="card-sub">remotas</div><div class="card-icon"><span class="material-symbols-rounded">videocam</span></div></div>
    <div class="card c-red clickable" onclick="ataFiltroPor('pendentes')"><div class="card-label">Encaminhamentos pendentes</div><div class="card-val" id="ata-pend">—</div><div class="card-sub">aguardando ação</div><div class="card-icon"><span class="material-symbols-rounded">pending_actions</span></div></div>
  </div>

  <div class="filters" style="margin:20px 0">
    <input class="search-inp" type="text" id="af-search" placeholder="Buscar por empresa, grupo, seguradora, responsável, objetivo..." oninput="ataLoad()" autocomplete="off" style="flex:1;min-width:280px"/>
    <select id="af-formato" onchange="ataLoad()">
      <option value="">Formato (todos)</option>
      <option>Presencial</option>
      <option>Online</option>
    </select>
    <select id="af-percepcao" onchange="ataLoad()">
      <option value="">Percepção (todas)</option>
      <option>Satisfeito</option>
      <option>Neutro</option>
      <option>Insatisfeito</option>
      <option>Preocupado com reajuste</option>
    </select>
    <select id="af-status" onchange="ataLoad()">
      <option value="">Status (todos)</option>
      <option>Agendada</option>
      <option>Rascunho</option>
      <option>Finalizada</option>
      <option>Assinada</option>
      <option>Cancelada</option>
    </select>
    <div class="date-filter">
      <label>Data visita: de</label>
      <input type="date" id="af-de" onchange="ataLoad()"/>
    </div>
    <div class="date-filter">
      <label>até</label>
      <input type="date" id="af-ate" onchange="ataLoad()"/>
    </div>
  </div>

  <div class="table-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--border)">
      <div style="font-size:13px;font-weight:700">Lista de Atas (<span id="ata-count">0</span>)</div>
    </div>
    <div style="overflow-x:auto;width:100%">
      <table class="iw-table" style="min-width:1300px">
        <thead>
          <tr>
            <th>#</th>
            <th>DATA</th>
            <th>HORÁRIO</th>
            <th>FORMATO</th>
            <th>GRUPO / EMPRESA</th>
            <th>OPERADORA</th>
            <th>OBJETIVO</th>
            <th>PERCEPÇÃO</th>
            <th>ENC.</th>
            <th>STATUS</th>
            <th>RESPONSÁVEL</th>
            <th>AÇÕES</th>
          </tr>
        </thead>
        <tbody id="ata-tbody">
          <tr><td colspan="12" style="text-align:center;padding:30px;color:var(--muted)">Carregando...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
  </div><!-- /ava-pane-atas -->

  <!-- ── PROGRAMAÇÃO DA SEMANA (kanban) ── -->
  <div id="ava-pane-prog" class="ava-pane" style="display:none">
    <div class="prog-bar">
      <div class="prog-nav">
        <button class="btn btn-outline btn-sm" onclick="progSemana(-1)"><span class="material-symbols-rounded" style="font-size:16px">chevron_left</span> anterior</button>
        <span id="prog-range" class="prog-range">—</span>
        <button class="btn btn-outline btn-sm" onclick="progSemana(1)">próxima <span class="material-symbols-rounded" style="font-size:16px">chevron_right</span></button>
        <button class="btn btn-outline btn-sm" onclick="progSemana(0)">Esta semana</button>
        <button class="btn btn-primary btn-sm" onclick="agendarAbrir()"><span class="material-symbols-rounded" style="font-size:16px">event_available</span> Agendar visita</button>
      </div>
      <div id="prog-totais" class="prog-totais"></div>
    </div>
    <div class="prog-hint">Previsão da semana pelas <b>datas de visita</b> das atas com status <b>Agendada</b>. Clique num card para abrir a ata.</div>
    <div id="prog-kanban" class="prog-kanban"></div>
  </div>

  <!-- ── PLANEJADO VS REALIZADO ── -->
  <div id="ava-pane-pvr" class="ava-pane" style="display:none">
    <div class="prog-bar">
      <div class="prog-nav">
        <button class="btn btn-outline btn-sm" onclick="pvrSemana(-1)"><span class="material-symbols-rounded" style="font-size:16px">chevron_left</span> anterior</button>
        <span id="pvr-range" class="prog-range">—</span>
        <button class="btn btn-outline btn-sm" onclick="pvrSemana(1)">próxima <span class="material-symbols-rounded" style="font-size:16px">chevron_right</span></button>
        <button class="btn btn-outline btn-sm" onclick="pvrSemana(0)">Esta semana</button>
      </div>
    </div>
    <div id="pvr-body"></div>
  </div>
</main>

<!-- ── MODAL DE EXPANSÃO (tela cheia para textareas) ─────────────── -->
<div id="mod-expand">
  <div class="exp-box">
    <div class="exp-head">
      <div class="exp-title" id="exp-title">Expandir campo</div>
      <button type="button" class="btn btn-outline btn-sm" onclick="ataFecharExpand()"><span class="material-symbols-rounded" style="font-size:16px">close</span> Fechar</button>
    </div>
    <textarea id="exp-textarea" placeholder="Digite com mais espaço aqui... (as mudanças são salvas automaticamente no campo original)"></textarea>
    <div class="exp-foot">
      <span class="exp-info">💡 Suas alterações são salvas automaticamente no campo original</span>
      <button type="button" class="btn btn-primary" onclick="ataFecharExpand()"><span class="material-symbols-rounded" style="font-size:16px">done</span> Concluído</button>
    </div>
  </div>
</div>

<!-- ── MODAL ATA DE VISITA ───────────────────────────────────────── -->
<div class="ov" id="mod-ata">
  <!-- Modal NÃO fecha mais ao clicar fora — o usuário precisa clicar explicitamente em
       Cancelar ou em Minimizar. Auto-save em localStorage roda enquanto digita,
       então mesmo se fechar acidentalmente o rascunho é recuperado ao reabrir. -->
  <div class="modal" style="width:1020px;max-width:97vw;max-height:92vh;overflow-y:auto">
    <div class="modal-title" id="ma-title" style="display:flex;align-items:center;justify-content:space-between;gap:10px">
      <span><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">edit_document</span> Nova Ata de Visita</span>
      <span style="display:flex;align-items:center;gap:8px">
        <span id="ma-autosave-info" style="font-size:11px;color:var(--muted);font-weight:400">Rascunho salvo automaticamente</span>
        <button type="button" class="btn btn-outline btn-sm" onclick="ataMinimizar()" title="Minimiza essa ata mantendo os dados — reabre depois sem perder nada"><span class="material-symbols-rounded" style="font-size:16px">minimize</span></button>
      </span>
    </div>
    <div class="modal-sub">Modelo TACOM, Ata de Visita de Relacionamento. Preencha conforme a conversa com o cliente.</div>
    <input type="hidden" id="ma-id"/>

    <div class="sec">Identificação</div>
    <div class="row3">
      <div class="fg"><label>Grupo *</label>
        <select id="ma-grupo"><option value="">Selecione...</option></select>
      </div>
      <div class="fg"><label>Empresa *</label>
        <select id="ma-empresa"><option value="">Selecione o grupo primeiro...</option></select>
      </div>
      <div class="fg"><label>Operadora</label>
        <select id="ma-seguradora"><option value="">Selecione...</option></select>
      </div>
    </div>

    <div class="row4">
      <div class="fg"><label>Data da visita *</label><input type="date" id="ma-data"/></div>
      <div class="fg"><label>Horário</label><input type="time" id="ma-horario"/></div>
      <div class="fg"><label>Formato</label>
        <select id="ma-formato">
          <option>Presencial</option>
          <option>Online</option>
        </select>
      </div>
      <div class="fg"><label>Status</label>
        <select id="ma-status">
          <option>Agendada</option>
          <option>Rascunho</option>
          <option>Finalizada</option>
          <option>Assinada</option>
          <option>Cancelada</option>
        </select>
      </div>
    </div>

    <div class="sec">Participantes (um por linha)</div>
    <div class="fg"><label>Empresa</label><textarea id="ma-part-empresa" rows="3" placeholder="Nome - Cargo&#10;Nome - Cargo"></textarea></div>
    <div class="fg"><label>Interweg</label><textarea id="ma-part-interweg" rows="3" placeholder="Nome - Cargo&#10;Nome - Cargo"></textarea></div>
    <div class="fg"><label>Operadora</label><textarea id="ma-part-operadora" rows="3" placeholder="Nome - Cargo&#10;Nome - Cargo"></textarea></div>

    <div class="sec">Conteúdo da Ata</div>
    <div class="fg">
      <label style="display:flex;align-items:center;justify-content:space-between">
        <span>1. Objetivo da visita</span>
        <button type="button" class="btn-expand" onclick="ataExpandir('ma-objetivo','Objetivo da visita')" title="Abre em tela cheia pra digitar com mais espaço"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">open_in_full</span> Expandir</button>
      </label>
      <textarea id="ma-objetivo" rows="4" placeholder="Ex.: visita de relacionamento, alinhamento pré-renovação, acompanhamento do contrato, apresentação de indicadores, esclarecimento de dúvidas, etc."></textarea>
    </div>
    <div class="fg">
      <label style="display:flex;align-items:center;justify-content:space-between">
        <span>2. Temas abordados</span>
        <button type="button" class="btn-expand" onclick="ataExpandir('ma-temas','Temas abordados')" title="Abre em tela cheia pra digitar com mais espaço"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">open_in_full</span> Expandir</button>
      </label>
      <textarea id="ma-temas" rows="8" placeholder="Ex.: utilização do plano, sinistralidade, atendimento da operadora, ações de saúde, expectativas para renovação"></textarea>
    </div>

    <div class="sec">3. Encaminhamentos e ações acordadas</div>
    <div id="ata-encs-wrap">
      <div id="ata-encs-head">
        <div>Descrição</div>
        <div>Responsável (Interno / Externo)</div>
        <div>Prazo</div>
        <div>Status</div>
        <div>Demanda</div>
        <div></div>
      </div>
      <div id="ata-encs-list"></div>
      <button type="button" class="btn btn-outline btn-sm" onclick="ataAddEnc()" style="margin-top:10px"><span class="material-symbols-rounded" style="font-size:15px">add</span> Adicionar encaminhamento</button>
    </div>

    <div class="sec">4. Percepção geral do cliente</div>
    <div class="percep-group" id="ma-percep">
      <div class="percep-opt" data-v="Satisfeito" onclick="ataSelPercep(this)">😊 Satisfeito</div>
      <div class="percep-opt" data-v="Neutro" onclick="ataSelPercep(this)">😐 Neutro</div>
      <div class="percep-opt" data-v="Insatisfeito" onclick="ataSelPercep(this)">😞 Insatisfeito</div>
      <div class="percep-opt" data-v="Preocupado com reajuste" onclick="ataSelPercep(this)">⚠️ Preocupado com reajuste</div>
    </div>
    <input type="hidden" id="ma-percepcao"/>

    <div class="sec" style="display:flex;align-items:center;justify-content:space-between">
      <span>5. Observações finais</span>
      <button type="button" class="btn-expand" onclick="ataExpandir('ma-obs','Observações finais')" title="Abre em tela cheia"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">open_in_full</span> Expandir</button>
    </div>
    <div class="fg">
      <textarea id="ma-obs" rows="5" placeholder="Pontos importantes não cobertos nos temas, expectativas do cliente, próximas ações etc."></textarea>
    </div>

    <div class="sec">Responsável pela visita</div>
    <div class="row3">
      <div class="fg"><label>Responsável (nome) *</label><input type="text" id="ma-resp" placeholder="Ex: Luana Orlandi Clemente"/></div>
      <div class="fg"><label>Analista do sistema</label>
        <select id="ma-analista-sel"><option value="">Selecione...</option></select>
      </div>
      <div class="fg"><label>Data do registro da ata</label><input type="date" id="ma-data-reg"/></div>
    </div>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="ataCancelar()">Cancelar</button>
      <button class="btn btn-outline" onclick="ataVisualizar()" title="Abre a ata em uma nova aba pra revisar o conteúdo antes de imprimir"><span class="material-symbols-rounded" style="font-size:17px">visibility</span> Visualizar</button>
      <button class="btn btn-outline" onclick="ataImprimir()" title="Abre versão imprimível e dispara o diálogo de impressão automaticamente"><span class="material-symbols-rounded" style="font-size:17px">print</span> Imprimir</button>
      <?php if (!$isVisualizador): ?>
      <button class="btn btn-primary" onclick="ataSave(this)"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar Ata</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal: enviar ata pro cliente por e-mail -->
<div class="ov" id="mod-ata-email" onclick="if(event.target===this)closeModal('mod-ata-email')">
  <div class="modal" style="width:480px;max-width:95vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">send</span> Enviar ata pro cliente</div>
    <div class="modal-sub" id="atae-sub">Confira o e-mail e envie a ata.</div>
    <input type="hidden" id="atae-id"/>
    <div class="fg" style="margin-top:14px">
      <label>E-mail do cliente <span style="font-weight:400;color:var(--muted)">(separe por ; ou vírgula pra vários)</span></label>
      <input type="text" id="atae-email" placeholder="cliente@empresa.com.br"/>
    </div>
    <div id="atae-msg" style="font-size:12px;margin-top:8px"></div>
    <div class="modal-actions" style="margin-top:16px">
      <button class="btn btn-outline" onclick="closeModal('mod-ata-email')">Cancelar</button>
      <button class="btn btn-primary" id="atae-btn" onclick="ataConfirmarEnvio(this)"><span class="material-symbols-rounded" style="font-size:17px">send</span> Enviar</button>
    </div>
  </div>
</div>

<!-- ── MODAL: AGENDAR VISITA (rápido) ── -->
<div class="ov" id="mod-agendar" onclick="if(event.target===this)agendarFechar()">
  <div class="modal" style="width:560px;max-width:95vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">event_available</span> Agendar visita</div>
    <div class="modal-sub">Cria a visita como <b>Agendada</b>. Depois é só abrir a ata para preencher e finalizar.</div>
    <div class="fg"><label>Empresa / Cliente *</label>
      <select id="ag-empresa" onchange="agendarPreencher()"><option value="">Selecione...</option></select>
    </div>
    <div class="row2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="fg"><label>Grupo</label><input type="text" id="ag-grupo" placeholder="—"></div>
      <div class="fg"><label>Seguradora</label><input type="text" id="ag-seguradora" placeholder="—"></div>
    </div>
    <div class="row2" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
      <div class="fg"><label>Data da visita *</label><input type="date" id="ag-data"></div>
      <div class="fg"><label>Horário</label><input type="time" id="ag-horario"></div>
      <div class="fg"><label>Formato</label>
        <select id="ag-formato"><option>Presencial</option><option>Online</option></select>
      </div>
    </div>
    <div class="fg"><label>Comercial / Analista</label>
      <select id="ag-analista"><option value="">Selecione...</option></select>
    </div>
    <div class="fg"><label>Objetivo (opcional)</label><input type="text" id="ag-objetivo" placeholder="Ex.: alinhamento de renovação, apresentação de sinistralidade..."></div>
    <div class="mfooter" style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px">
      <button class="btn btn-outline" onclick="agendarFechar()">Cancelar</button>
      <button class="btn btn-primary" id="ag-btn" onclick="agendarSalvar(this)"><span class="material-symbols-rounded" style="font-size:17px">event_available</span> Agendar</button>
    </div>
  </div>
</div>

<script>
(function(){
  const esc = s => { const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; };
  const DIAS = ['Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
  const MES = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
  let progOff = 0, pvrOff = 0;

  function ymd(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
  function mondayOf(off){ const x=new Date(); x.setHours(0,0,0,0); const dow=(x.getDay()+6)%7; x.setDate(x.getDate()-dow+off*7); return x; }
  function fmtBR(d){ return String(d.getDate()).padStart(2,'0')+'/'+String(d.getMonth()+1).padStart(2,'0'); }
  function stBadge(s){ s=(s||'').toLowerCase(); if(s.includes('agend'))return'b-agend'; if(s.includes('final')||s.includes('assin')||s.includes('realiz'))return'b-real'; if(s.includes('canc'))return'b-canc'; return'b-rasc'; }
  const ehRealizada = s => { s=(s||'').toLowerCase(); return s.includes('final')||s.includes('assin')||s.includes('realiz'); };

  window.avaTab = function(t){
    document.querySelectorAll('.ava-pane').forEach(p=>p.style.display='none');
    document.querySelectorAll('.ava-tab').forEach(b=>b.classList.toggle('active', b.dataset.tab===t));
    document.getElementById('ava-pane-'+t).style.display='';
    if(t==='prog') progRender();
    if(t==='pvr')  pvrRender();
  };

  async function semanaAtas(off){
    const seg=mondayOf(off); const sab=new Date(seg); sab.setDate(sab.getDate()+5);
    let rows=[];
    try{ const r=await apiGet('listar_atas',{data_de:ymd(seg),data_ate:ymd(sab)}); rows=(r&&r.data)||[]; }catch(e){}
    return {seg, sab, rows};
  }

  window.progSemana = function(dir){ progOff = dir===0 ? 0 : progOff+dir; progRender(); };
  async function progRender(){
    const {seg,sab,rows} = await semanaAtas(progOff);
    document.getElementById('prog-range').textContent = fmtBR(seg)+' a '+fmtBR(sab)+' · '+seg.getDate()+' '+MES[seg.getMonth()];
    // totais por comercial
    const porAna={}; rows.forEach(a=>{ const n=(a.analista_nome||'—').trim()||'—'; porAna[n]=(porAna[n]||0)+1; });
    document.getElementById('prog-totais').innerHTML =
      '<span class="prog-chip">Total: <b>'+rows.length+'</b></span>' +
      Object.entries(porAna).sort((a,b)=>b[1]-a[1]).map(([n,q])=>'<span class="prog-chip">'+esc(n)+': <b>'+q+'</b></span>').join('');
    // colunas
    const hojeStr = ymd(new Date());
    let h='';
    for(let i=0;i<6;i++){
      const d=new Date(seg); d.setDate(d.getDate()+i); const ds=ymd(d);
      const doDia = rows.filter(a=>(a.data_visita||'').slice(0,10)===ds);
      h+='<div class="prog-col'+(ds===hojeStr?' hoje':'')+'"><div class="prog-col-head"><span class="d">'+DIAS[i]+'</span><span class="dt">'+fmtBR(d)+'</span></div>';
      if(!doDia.length){ h+='<div class="prog-vazio">Sem visitas</div>'; }
      else { doDia.forEach(a=>{ const sub=[a.analista_nome, a.seguradora].filter(Boolean).map(esc).join(' · ');
        h+='<div class="prog-card" onclick="ataEdit('+a.id+')"><div class="emp">'+esc(a.empresa||a.grupo||'—')+'</div>'
          +(sub?'<div class="sub">'+sub+'</div>':'')
          +'<span class="prog-bdg '+stBadge(a.status)+'">'+esc(a.status||'—')+'</span></div>'; }); }
      h+='</div>';
    }
    document.getElementById('prog-kanban').innerHTML=h;
  }

  window.pvrSemana = function(dir){ pvrOff = dir===0 ? 0 : pvrOff+dir; pvrRender(); };
  async function pvrRender(){
    const {seg,sab,rows} = await semanaAtas(pvrOff);
    document.getElementById('pvr-range').textContent = fmtBR(seg)+' a '+fmtBR(sab);
    const map={}; rows.forEach(a=>{ const n=(a.analista_nome||'—').trim()||'—'; (map[n]=map[n]||{ag:0,re:0}); if(ehRealizada(a.status))map[n].re++; else map[n].ag++; });
    const arr=Object.entries(map).sort((a,b)=>(b[1].ag+b[1].re)-(a[1].ag+a[1].re));
    const totA=rows.filter(a=>!ehRealizada(a.status)).length, totR=rows.filter(a=>ehRealizada(a.status)).length;
    let h='<div class="pvr-leg"><span class="lr">Realizadas ('+totR+')</span><span class="la">Agendadas/pendentes ('+totA+')</span></div>';
    if(!arr.length){ h+='<div class="prog-vazio">Nenhuma visita nesta semana.</div>'; }
    const max=Math.max(1,...arr.map(([,v])=>v.ag+v.re));
    arr.forEach(([n,v])=>{ const tot=v.ag+v.re;
      h+='<div class="pvr-row"><div class="pvr-nm">'+esc(n)+'</div>'
        +'<div class="pvr-bar" style="width:'+Math.max(8,tot/max*100)+'%">'
        +(v.re?'<div class="real" style="flex:'+v.re+'"></div>':'')
        +(v.ag?'<div class="agend" style="flex:'+v.ag+'"></div>':'')
        +'</div><div style="text-align:right">'+v.re+'/'+tot+'</div></div>';
    });
    document.getElementById('pvr-body').innerHTML=h;
  }

  // ── Agendar visita (formulário rápido) ──
  let _agEmp=[], _agAna=[];
  window.agendarAbrir = async function(){
    if(!_agEmp.length){ try{ const e=await apiGet('empresas',{somente_clientes:1}); _agEmp=(e&&e.data)||[]; }catch(x){} }
    if(!_agAna.length){ try{ const a=await apiGet('analistas'); _agAna=(a&&a.data)||[]; }catch(x){} }
    const se=document.getElementById('ag-empresa');
    se.innerHTML='<option value="">Selecione...</option>'+_agEmp.map((e,i)=>'<option value="'+i+'">'+esc((e.grupo?e.grupo+' · ':'')+(e.nome||''))+'</option>').join('');
    const sa=document.getElementById('ag-analista');
    const permitido = n => /luana|sofhia|sofia|sophia/i.test(n||'');
    sa.innerHTML='<option value="">Selecione...</option>'+_agAna.filter(a=>permitido(a.nome)).map(a=>'<option value="'+esc(a.nome)+'">'+esc(a.nome)+'</option>').join('');
    ['ag-grupo','ag-seguradora','ag-data','ag-horario','ag-objetivo'].forEach(id=>document.getElementById(id).value='');
    const m=document.getElementById('mod-agendar'); m.classList.add('show'); m.style.display='flex';
  };
  window.agendarFechar = function(){ const m=document.getElementById('mod-agendar'); m.classList.remove('show'); m.style.display='none'; };
  window.agendarPreencher = function(){
    const i=document.getElementById('ag-empresa').value; if(i==='')return;
    const e=_agEmp[+i]||{};
    document.getElementById('ag-grupo').value=e.grupo||'';
    document.getElementById('ag-seguradora').value=e.seguradora||'';
    if(e.analista_nome) document.getElementById('ag-analista').value=e.analista_nome;
  };
  window.agendarSalvar = async function(btn){
    const i=document.getElementById('ag-empresa').value;
    const e = i!=='' ? (_agEmp[+i]||{}) : {};
    const empresa = e.nome || '';
    const data = document.getElementById('ag-data').value;
    if(!empresa){ (window.uiAlert?uiAlert:alert)('Selecione a empresa.'); return; }
    if(!data){ (window.uiAlert?uiAlert:alert)('Informe a data da visita.'); return; }
    if(btn) btn.disabled=true;
    const body = {
      empresa_id: e.id||null, grupo: document.getElementById('ag-grupo').value, empresa,
      seguradora: document.getElementById('ag-seguradora').value,
      data_visita: data, horario: document.getElementById('ag-horario').value,
      formato: document.getElementById('ag-formato').value,
      analista_nome: document.getElementById('ag-analista').value,
      objetivo: document.getElementById('ag-objetivo').value
    };
    try{
      const r=await fetch('api.php?action=agendar_visita',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(x=>x.json());
      if(r.ok){ agendarFechar(); if(window.toast)toast('Visita agendada!'); progRender(); }
      else { (window.uiAlert?uiAlert:alert)(r.msg||'Erro ao agendar.'); }
    }catch(x){ (window.uiAlert?uiAlert:alert)('Erro de conexão.'); }
    if(btn) btn.disabled=false;
  };
})();
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
