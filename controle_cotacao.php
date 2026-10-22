<?php
$currentPage = 'controle_cotacao';
$pageTitle   = 'Controle de Cotação — Interweg Benefícios';
$pageScripts = ['assets/controle_cotacao_v2.js?v=' . time()];

require_once __DIR__ . '/_header.php';
?>

<!-- STUBS INLINE (rodam IMEDIATO, antes do JS externo) -->
<script>
window.ccOpenModal    = window.ccOpenModal    || function(){ alert('Carregando...'); };
window.ccLoad         = window.ccLoad         || function(){};
window.ccEdit         = window.ccEdit         || function(){ alert('Carregando...'); };
window.ccSave         = window.ccSave         || function(){};
window.ccDelete       = window.ccDelete       || function(){};
window.ccExportXLSX   = window.ccExportXLSX   || function(){ alert('Carregando...'); };
window.ccFiltrarPorCard = window.ccFiltrarPorCard || function(){};
window.ccDashOpen     = window.ccDashOpen     || function(){ alert('Carregando...'); };
</script>

<style>
  #mod-cc .fg{margin-bottom:14px;gap:5px}
  #mod-cc input[type=text], #mod-cc input[type=date], #mod-cc input[type=number], #mod-cc select, #mod-cc textarea{padding:8px 11px;font-size:13px;border-radius:7px}
  #mod-cc label{font-size:11px}
  #mod-cc .row2, #mod-cc .row3, #mod-cc .row4{gap:18px;margin-bottom:6px}
  #mod-cc .sec{margin:14px 0 8px}
  #mod-cc input[type=date]{width:100%;max-width:180px}
  .ccs{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;display:inline-block;white-space:nowrap;letter-spacing:.2px}
  .ccs-aguardando-dados        { background:#cfe2ff; color:#0d4d8c; }
  .ccs-aguardando-liberacao    { background:#ff9800; color:#fff;    }
  .ccs-iniciar-cotacao         { background:#dc3545; color:#fff;    }
  .ccs-em-cotacao              { background:#ffe699; color:#7a5d00; }
  .ccs-estudo-apresentado      { background:#c6e9c8; color:#256029; }
  .ccs-aguardando-documentacao { background:#cfe2ff; color:#0d4d8c; }
  .ccs-iniciar-implantacao     { background:#dc3545; color:#fff;    }
  .ccs-em-implantacao          { background:#1f4f99; color:#fff;    }
  .ccs-em-negociacao           { background:#d1b3ff; color:#3e0b78; }
  .ccs-passagem-bastao         { background:#fcd0d3; color:#9b1c2a; }
  .ccs-implantacao-finalizada  { background:#2f9b48; color:#fff;    }
  .ccs-declinada               { background:#8a3ab9; color:#fff;    }
  .ccs-iniciar-nomeacao        { background:#ffd7a8; color:#8a4a00; }
  .ccs-em-nomeacao             { background:#fcd5e0; color:#9b1c4e; }
  .ccs-nomeacao-concluida      { background:#d8f0c2; color:#3a6b1c; }
  .ccs-default                 { background:#e9ecef; color:#666;    }
  .card{cursor:pointer;transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease}
  .card:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.08)}
  .card.cc-active{border:2px solid var(--accent);box-shadow:0 0 0 3px rgba(0,191,179,.15)}

  /* ── Filtro multi-seleção (checkbox) ── */
  .ms{position:relative;display:inline-block}
  .ms-btn{display:flex;align-items:center;justify-content:space-between;gap:8px;min-width:150px;max-width:230px;height:38px;padding:8px 11px;font-size:13px;border:1.5px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);cursor:pointer;white-space:nowrap;overflow:hidden;font-family:inherit}
  .ms-btn .ms-label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .ms-caret{flex-shrink:0;opacity:.6;font-size:11px;transition:transform .15s}
  .ms.open .ms-caret{transform:rotate(180deg)}
  .ms-panel{display:none;position:absolute;z-index:60;top:calc(100% + 4px);left:0;min-width:230px;max-height:320px;overflow:auto;background:var(--bg-card);border:1px solid var(--border);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.18);padding:6px}
  .ms.open .ms-panel{display:block}
  .ms-opt{display:flex;align-items:center;gap:8px;padding:6px 8px;font-size:12.5px;border-radius:6px;cursor:pointer;color:var(--text);white-space:nowrap}
  .ms-opt:hover{background:rgba(0,191,179,.08)}
  .ms-opt input{width:15px;height:15px;cursor:pointer;flex-shrink:0;accent-color:var(--accent)}
  .ms-actions{display:flex;justify-content:flex-end;gap:6px;padding:6px 6px 2px;border-top:1px solid var(--border);margin-top:4px;position:sticky;bottom:0;background:var(--bg-card)}
  .ms-actions button{font-size:11px;background:none;border:none;color:var(--accent);cursor:pointer;font-weight:700;padding:3px 6px;font-family:inherit}

  /* ── Painel de resumo (por entrada / por tipo) — lista ranqueada ── */
  .cc-breakdown{display:flex;flex-direction:column;gap:12px;margin:6px 0 18px}
  .cc-break-block{background:var(--bg-card,#fff);border:1px solid var(--border);border-radius:14px;padding:16px 18px}
  .cc-break-title{display:flex;align-items:center;justify-content:space-between;width:100%;background:none;border:0;padding:0;cursor:pointer;font-family:inherit;font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--muted);margin-bottom:14px}
  .cc-brk-chev{font-size:12px;opacity:.6;transition:transform .15s}
  .cc-break-block.collapsed .cc-brk-chev{transform:rotate(-90deg)}
  .cc-break-block.collapsed .cc-break-title{margin-bottom:0}
  .cc-break-block.collapsed .cc-brk-list,.cc-break-block.collapsed .fc-brk-list{display:none}
  .cc-brk-list{display:flex;flex-direction:column;gap:3px}
  .cc-brk-row{display:grid;grid-template-columns:130px 1fr 172px;align-items:center;gap:12px;padding:6px 8px;border-radius:8px;cursor:pointer;transition:background .12s}
  .cc-brk-row:hover{background:rgba(0,191,179,.07)}
  .cc-brk-zero{opacity:.5}
  .cc-brk-lbl{font-size:13px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .cc-brk-track{height:8px;border-radius:999px;background:rgba(130,130,130,.14);overflow:hidden}
  .cc-brk-fill{height:100%;border-radius:999px;background:var(--accent,#00c9a7);transition:width .5s ease}
  .cc-brk-val{font-size:12px;color:var(--muted);text-align:right;white-space:nowrap}
  .cc-brk-val b{color:var(--text);font-weight:700}
  .cc-brk-active{background:rgba(0,191,179,.12)}
  .cc-brk-active .cc-brk-lbl{color:var(--accent);font-weight:600}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Controle de Cotação</div>
      <div class="page-sub">Acompanhamento de cotações desde o recebimento até o fechamento</div>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-outline" onclick="ccDashOpen()"><span class="material-symbols-rounded" style="font-size:17px">insights</span> Dashboard</button>
      <button class="btn btn-outline" onclick="ccExportXLSX()"><span class="material-symbols-rounded" style="font-size:17px">download</span> Exportar Excel</button>
      <button class="btn btn-primary" onclick="ccOpenModal()"><span class="material-symbols-rounded" style="font-size:19px">add</span> Nova Cotação</button>
    </div>
  </div>

  <div class="filters">
    <input class="search-inp" type="text" id="cf-search" placeholder="Buscar por razão social, operadora atual, players cotados..." oninput="ccLoad()" autocomplete="off"/>
    <!-- STATUS (multi) -->
    <div class="ms" id="ms-status">
      <button type="button" class="ms-btn" onclick="ccMsToggle('status',event)"><span class="ms-label" id="ms-status-label">Todos os status</span><span class="ms-caret">▾</span></button>
      <div class="ms-panel">
        <label class="ms-opt"><input type="checkbox" value="AGUARDANDO DADOS" onchange="ccMsChange('status')"> AGUARDANDO DADOS</label>
        <label class="ms-opt"><input type="checkbox" value="AGUARDANDO LIBERAÇÃO DE COTAÇÃO" onchange="ccMsChange('status')"> AGUARDANDO LIBERAÇÃO DE COTAÇÃO</label>
        <label class="ms-opt"><input type="checkbox" value="INICIAR COTAÇÃO" onchange="ccMsChange('status')"> INICIAR COTAÇÃO</label>
        <label class="ms-opt"><input type="checkbox" value="EM COTAÇÃO" onchange="ccMsChange('status')"> EM COTAÇÃO</label>
        <label class="ms-opt"><input type="checkbox" value="ESTUDO APRESENTADO" onchange="ccMsChange('status')"> ESTUDO APRESENTADO</label>
        <label class="ms-opt"><input type="checkbox" value="AGUARDANDO DOCUMENTAÇÃO" onchange="ccMsChange('status')"> AGUARDANDO DOCUMENTAÇÃO</label>
        <label class="ms-opt"><input type="checkbox" value="INICIAR IMPLANTAÇÃO" onchange="ccMsChange('status')"> INICIAR IMPLANTAÇÃO</label>
        <label class="ms-opt"><input type="checkbox" value="EM IMPLANTAÇÃO" onchange="ccMsChange('status')"> EM IMPLANTAÇÃO</label>
        <label class="ms-opt"><input type="checkbox" value="EM NEGOCIAÇÃO COM A OPERADORA" onchange="ccMsChange('status')"> EM NEGOCIAÇÃO COM A OPERADORA</label>
        <label class="ms-opt"><input type="checkbox" value="PASSAGEM DE BASTÃO" onchange="ccMsChange('status')"> PASSAGEM DE BASTÃO</label>
        <label class="ms-opt"><input type="checkbox" value="IMPLANTAÇÃO FINALIZADA" onchange="ccMsChange('status')"> IMPLANTAÇÃO FINALIZADA</label>
        <label class="ms-opt"><input type="checkbox" value="DECLINADA" onchange="ccMsChange('status')"> DECLINADA</label>
        <label class="ms-opt"><input type="checkbox" value="INICIAR NOMEAÇÃO" onchange="ccMsChange('status')"> INICIAR NOMEAÇÃO</label>
        <label class="ms-opt"><input type="checkbox" value="EM NOMEAÇÃO" onchange="ccMsChange('status')"> EM NOMEAÇÃO</label>
        <label class="ms-opt"><input type="checkbox" value="NOMEAÇÃO CONCLUÍDA" onchange="ccMsChange('status')"> NOMEAÇÃO CONCLUÍDA</label>
        <div class="ms-actions"><button type="button" onclick="ccMsClear('status')">Limpar</button></div>
      </div>
    </div>
    <!-- ENTRADA (multi) -->
    <div class="ms" id="ms-entrada">
      <button type="button" class="ms-btn" onclick="ccMsToggle('entrada',event)"><span class="ms-label" id="ms-entrada-label">Todas as entradas</span><span class="ms-caret">▾</span></button>
      <div class="ms-panel">
        <label class="ms-opt"><input type="checkbox" value="Interweg MG" onchange="ccMsChange('entrada')"> Belo Horizonte</label>
        <label class="ms-opt"><input type="checkbox" value="Interweg SP" onchange="ccMsChange('entrada')"> São Paulo</label>
        <label class="ms-opt"><input type="checkbox" value="Interweg JF" onchange="ccMsChange('entrada')"> Juiz de Fora</label>
        <label class="ms-opt"><input type="checkbox" value="Finlândia" onchange="ccMsChange('entrada')"> Finlândia</label>
        <label class="ms-opt"><input type="checkbox" value="Fred" onchange="ccMsChange('entrada')"> Fred</label>
        <label class="ms-opt"><input type="checkbox" value="Parceiro - Bernardo" onchange="ccMsChange('entrada')"> Parceiro - Bernardo</label>
        <label class="ms-opt"><input type="checkbox" value="Parceiro - Elmo" onchange="ccMsChange('entrada')"> Parceiro - Elmo</label>
        <div class="ms-actions"><button type="button" onclick="ccMsClear('entrada')">Limpar</button></div>
      </div>
    </div>
    <!-- TIPO (multi) -->
    <div class="ms" id="ms-tipo">
      <button type="button" class="ms-btn" onclick="ccMsToggle('tipo',event)"><span class="ms-label" id="ms-tipo-label">Todos os tipos</span><span class="ms-caret">▾</span></button>
      <div class="ms-panel">
        <label class="ms-opt"><input type="checkbox" value="Seguro Novo" onchange="ccMsChange('tipo')"> Seguro Novo</label>
        <label class="ms-opt"><input type="checkbox" value="Seguro Novo de Cliente Interweg" onchange="ccMsChange('tipo')"> Seguro Novo de Cliente Interweg</label>
        <label class="ms-opt"><input type="checkbox" value="Renovação" onchange="ccMsChange('tipo')"> Renovação</label>
        <label class="ms-opt"><input type="checkbox" value="Nomeação" onchange="ccMsChange('tipo')"> Nomeação</label>
        <div class="ms-actions"><button type="button" onclick="ccMsClear('tipo')">Limpar</button></div>
      </div>
    </div>
    <!-- RAMO (multi) -->
    <div class="ms" id="ms-ramo">
      <button type="button" class="ms-btn" onclick="ccMsToggle('ramo',event)"><span class="ms-label" id="ms-ramo-label">Todos os ramos</span><span class="ms-caret">▾</span></button>
      <div class="ms-panel">
        <label class="ms-opt"><input type="checkbox" value="Saúde" onchange="ccMsChange('ramo')"> Saúde</label>
        <label class="ms-opt"><input type="checkbox" value="Odonto" onchange="ccMsChange('ramo')"> Odonto</label>
        <label class="ms-opt"><input type="checkbox" value="Saúde/Odonto" onchange="ccMsChange('ramo')"> Saúde/Odonto</label>
        <label class="ms-opt"><input type="checkbox" value="Vida em Grupo" onchange="ccMsChange('ramo')"> Vida em Grupo</label>
        <label class="ms-opt"><input type="checkbox" value="Vida Individual" onchange="ccMsChange('ramo')"> Vida Individual</label>
        <label class="ms-opt"><input type="checkbox" value="Seguro Estagiário" onchange="ccMsChange('ramo')"> Seguro Estagiário</label>
        <div class="ms-actions"><button type="button" onclick="ccMsClear('ramo')">Limpar</button></div>
      </div>
    </div>
    <!-- ANALISTA (multi, preenchido via JS) -->
    <div class="ms" id="ms-analista">
      <button type="button" class="ms-btn" onclick="ccMsToggle('analista',event)"><span class="ms-label" id="ms-analista-label">Todos os analistas</span><span class="ms-caret">▾</span></button>
      <div class="ms-panel" id="ms-analista-panel"></div>
    </div>
    <input type="date" id="cf-de" onchange="ccLoad()" title="Data recebimento (de)"/>
    <input type="date" id="cf-ate" onchange="ccLoad()" title="Data recebimento (até)"/>
  </div>

  <div class="table-wrap">
    <div class="table-head"><div class="table-title" id="cc-count">Lista de Cotações</div></div>
    <div class="tscroll">
      <table>
        <thead><tr>
          <th>#</th><th>Razão Social</th><th>Status</th><th>Observação / Último Status</th>
          <th>Entrada</th><th>Analista</th><th>Tipo</th><th>Ramo</th>
          <th>Recebimento</th><th>Próxima Ação</th><th>Operadora Atual</th><th>Vidas</th>
          <th>Solic. Estudo</th><th>Players Cotados</th><th>Envio Estudo</th><th>Sinaliz. Fechamento</th>
          <th>Player Contratado</th><th>Prêmio</th><th>Finalização</th><th>Motivo Declínio</th><th>Ações</th>
        </tr></thead>
        <tbody id="cc-tbody"></tbody>
      </table>
    </div>
    <div class="pag"><span id="cc-pag-info">—</span><div class="pag-btns" id="cc-pag-btns"></div></div>
  </div>
</main>

<div class="ov" id="mod-cc" onclick="if(event.target===this)closeModal('mod-cc')">
  <div class="modal" style="width:920px;max-width:95vw">
    <div class="modal-title" id="mc-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">request_quote</span> Nova Cotação</div>
    <div class="modal-sub">Acompanhamento da cotação desde o recebimento até o fechamento ou declínio</div>
    <input type="hidden" id="mc-id"/>
    <div class="sec">Identificação</div>
    <div class="row2">
      <div class="fg"><label>Razão Social *</label><input type="text" id="mc-razao" placeholder="Ex: ARCOL ESTRUTURAS LTDA"/></div>
      <div class="fg"><label>Status *</label>
        <select id="mc-status" onchange="ccTogglePassagemBtn()">
          <option value="AGUARDANDO DADOS">AGUARDANDO DADOS</option>
          <option value="AGUARDANDO LIBERAÇÃO DE COTAÇÃO">AGUARDANDO LIBERAÇÃO DE COTAÇÃO</option>
          <option value="INICIAR COTAÇÃO">INICIAR COTAÇÃO</option>
          <option value="EM COTAÇÃO">EM COTAÇÃO</option>
          <option value="ESTUDO APRESENTADO">ESTUDO APRESENTADO</option>
          <option value="AGUARDANDO DOCUMENTAÇÃO">AGUARDANDO DOCUMENTAÇÃO</option>
          <option value="INICIAR IMPLANTAÇÃO">INICIAR IMPLANTAÇÃO</option>
          <option value="EM IMPLANTAÇÃO">EM IMPLANTAÇÃO</option>
          <option value="EM NEGOCIAÇÃO COM A OPERADORA">EM NEGOCIAÇÃO COM A OPERADORA</option>
          <option value="PASSAGEM DE BASTÃO">PASSAGEM DE BASTÃO</option>
          <option value="IMPLANTAÇÃO FINALIZADA">IMPLANTAÇÃO FINALIZADA</option>
          <option value="DECLINADA">DECLINADA</option>
          <option value="INICIAR NOMEAÇÃO">INICIAR NOMEAÇÃO</option>
          <option value="EM NOMEAÇÃO">EM NOMEAÇÃO</option>
          <option value="NOMEAÇÃO CONCLUÍDA">NOMEAÇÃO CONCLUÍDA</option>
        </select>
      </div>
    </div>
    <div class="fg"><label>CNPJ</label><input type="text" id="mc-cnpj" placeholder="00.000.000/0000-00"/></div>
    <div class="fg"><label>Observação / Último Status</label><textarea id="mc-obs" rows="2" placeholder="Última atualização da cotação..."></textarea></div>
    <div class="sec">Origem e Distribuição</div>
    <div class="row3">
      <div class="fg"><label>Entrada/Origem *</label>
        <select id="mc-entrada">
          <option value="">Selecione...</option>
          <option value="Interweg MG">Belo Horizonte</option>
          <option value="Interweg SP">São Paulo</option>
          <option value="Interweg JF">Juiz de Fora</option>
          <option value="Finlândia">Finlândia</option>
          <option value="Fred">Fred</option>
          <option value="Parceiro - Bernardo">Parceiro - Bernardo</option>
          <option value="Parceiro - Elmo">Parceiro - Elmo</option>
        </select>
      </div>
      <div class="fg"><label>Analista Designada</label>
        <select id="mc-analista"><option value="">Selecione...</option></select>
      </div>
      <div class="fg"><label>Tipo *</label>
        <select id="mc-tipo">
          <option value="">Selecione...</option>
          <option>Seguro Novo</option><option>Seguro Novo de Cliente Interweg</option><option>Renovação</option><option>Nomeação</option>
        </select>
      </div>
    </div>
    <div class="row3">
      <div class="fg"><label>Ramo *</label>
        <select id="mc-ramo">
          <option value="">Selecione...</option>
          <option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option>
          <option>Vida em Grupo</option><option>Vida Individual</option><option>Seguro Estagiário</option>
        </select>
      </div>
      <div class="fg"><label>Data Recebimento *</label><input type="date" id="mc-recebimento"/></div>
      <div class="fg"><label>Próxima Ação (data)</label><input type="date" id="mc-prox-acao"/></div>
      <div class="fg"><label>Nº de Vidas</label><input type="number" id="mc-vidas" min="0" placeholder="Ex: 50"/></div>
    </div>
    <div class="sec">Cotação</div>
    <div class="row3">
      <div class="fg"><label>Operadora Atual</label><input type="text" id="mc-operadora-atual" placeholder="Ex: Bradesco / Sem Apólice"/></div>
      <div class="fg"><label>Data Solicitação Estudo</label><input type="date" id="mc-data-solic"/></div>
      <div class="fg"><label>Data Envio Estudo</label><input type="date" id="mc-data-envio"/></div>
    </div>
    <div class="row3">
      <div class="fg"><label>Players Cotados Saúde</label><input type="text" id="mc-players-saude" placeholder="Ex: Bradesco, Amil, SulAmérica"/></div>
      <div class="fg"><label>Players Cotados Odonto</label><input type="text" id="mc-players-odonto" placeholder="Ex: OdontoPrev, Bradesco Dental"/></div>
      <div class="fg"><label>Players Cotados Vida</label><input type="text" id="mc-players-vida" placeholder="Ex: MetLife, Prudential"/></div>
    </div>
    <div class="sec">Fechamento</div>
    <div class="row3">
      <div class="fg"><label>Data Sinalização Fechamento</label><input type="date" id="mc-data-sinaliz"/></div>
      <div class="fg"><label>Player Contratado</label><input type="text" id="mc-player-contratado" placeholder="Ex: SulAmérica"/></div>
      <div class="fg"><label>Prêmio (R$)</label><input type="text" id="mc-premio" placeholder="Ex: 4.710,94"/></div>
    </div>
    <div class="row2">
      <div class="fg"><label>Data Finalização</label><input type="date" id="mc-data-final"/></div>
      <div class="fg"><label>Motivo Declínio (se aplicável)</label><textarea id="mc-motivo" rows="3" style="resize:vertical" placeholder="Ex: Cliente optou por outro plano (pode usar Enter para quebrar linhas)"></textarea></div>
    </div>

    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeModal('mod-cc')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
      <button class="btn btn-outline" id="mc-btn-del" style="display:none;border-color:var(--danger);color:var(--danger)" onclick="ccDelete()"><span class="material-symbols-rounded" style="font-size:17px">delete</span> Excluir</button>
      <button class="btn btn-outline" id="mc-btn-passagem" style="display:none;border-color:var(--accent);color:var(--accent)" onclick="ccGerarPassagem()"><span class="material-symbols-rounded" style="font-size:17px">swap_horiz</span> Gerar passagem de bastão</button>
      <button class="btn btn-primary" data-edit-only onclick="ccSave()"><span class="material-symbols-rounded" style="font-size:18px">save</span> Salvar Cotação</button>
    </div>
  </div>
</div>

<!-- ───────── DASHBOARD DE COTAÇÕES ───────── -->
<div class="ov" id="mod-cc-dash" onclick="if(event.target===this)ccDashClose()">
  <div class="modal" style="width:96vw;max-width:1200px;max-height:93vh;overflow-y:auto;padding:0">
    <div style="position:sticky;top:0;z-index:5;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;border-radius:16px 16px 0 0">
      <div>
        <div style="font-size:18px;font-weight:800"><span class="material-symbols-rounded" style="vertical-align:middle">insights</span> Dashboard de Cotações</div>
        <div id="ccdash-sub" style="font-size:12px;opacity:.9;margin-top:2px">Baseado nos dados do Controle de Cotação</div>
      </div>
      <button onclick="ccDashClose()" class="material-symbols-rounded" style="background:rgba(255,255,255,.18);border:none;color:#fff;cursor:pointer;font-size:22px;width:36px;height:36px;border-radius:9px">close</button>
    </div>
    <div id="ccdash-body" style="padding:22px 24px 30px"><div style="text-align:center;padding:60px;color:var(--muted)">Carregando…</div></div>
  </div>
</div>

<style>
  #mod-cc-dash .ccd-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:20px}
  #mod-cc-dash .ccd-kpi{background:var(--bg-card);border:1px solid var(--border);border-left:5px solid var(--accent);border-radius:12px;padding:14px 16px;box-shadow:0 2px 10px rgba(0,0,0,.04)}
  #mod-cc-dash .ccd-kpi .l{font-size:10.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;font-weight:700}
  #mod-cc-dash .ccd-kpi .v{font-size:23px;font-weight:800;margin-top:5px}
  #mod-cc-dash .ccd-kpi .s{font-size:11px;color:var(--muted);margin-top:3px}
  #mod-cc-dash .ccd-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
  @media(max-width:820px){#mod-cc-dash .ccd-grid{grid-template-columns:1fr}}
  #mod-cc-dash .ccd-card{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:16px 18px;margin-bottom:18px}
  #mod-cc-dash .ccd-card h3{font-size:13.5px;color:var(--text);font-weight:800;margin-bottom:12px;display:flex;align-items:center;gap:8px}
  #mod-cc-dash .ccd-card h3 .material-symbols-rounded{font-size:18px;color:var(--accent2)}
  #mod-cc-dash .ccd-row{display:flex;align-items:center;gap:10px;margin-bottom:8px;font-size:12.5px}
  #mod-cc-dash .ccd-row .nm{width:38%;min-width:110px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  #mod-cc-dash .ccd-bar{flex:1;height:18px;background:rgba(0,0,0,.05);border-radius:6px;overflow:hidden;position:relative}
  #mod-cc-dash .ccd-bar>span{display:flex;align-items:center;justify-content:flex-end;height:100%;border-radius:6px;color:#fff;font-size:10.5px;font-weight:700;padding-right:6px;min-width:20px;box-sizing:border-box}
  #mod-cc-dash .ccd-val{width:96px;text-align:right;font-weight:700;font-variant-numeric:tabular-nums;color:var(--text)}
  #mod-cc-dash .ccd-empty{color:var(--muted);font-size:12px;padding:6px 0}
  /* Pontos de atenção */
  #mod-cc-dash .ccd-alert{display:flex;align-items:center;gap:12px;padding:11px 6px;border-bottom:1px solid var(--border);font-size:13px}
  #mod-cc-dash .ccd-alert:last-child{border-bottom:none}
  #mod-cc-dash .ccd-alert .material-symbols-rounded{font-size:20px}
  #mod-cc-dash .ccd-alert .al-nm{flex:1;color:var(--text)}
  #mod-cc-dash .ccd-alert .al-v{font-weight:800;font-size:15px}
  /* Statebin map */
  #mod-cc-dash .ccd-map{display:grid;grid-template-columns:repeat(7,1fr);gap:5px;max-width:460px}
  #mod-cc-dash .ccd-uf{aspect-ratio:1;border-radius:7px;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#0e5a58;background:rgba(0,180,166,.10)}
  #mod-cc-dash .ccd-uf .c{font-size:11px;font-weight:800;margin-top:1px}
  #mod-cc-dash .ccd-uf.on{color:#fff}
</style>

<script>
(function(){
  const brl = v => 'R$ '+Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:0,maximumFractionDigits:0});
  const esc = s => { const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; };
  const num = v => { const n=parseFloat(v); return isNaN(n)?0:n; };
  const MESES=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

  window.ccDashClose = function(){ const m=document.getElementById('mod-cc-dash'); if(m) m.classList.remove('show'), m.style.display='none'; };

  window.ccDashOpen = async function(){
    const m=document.getElementById('mod-cc-dash'); if(!m) return;
    m.style.display='flex'; m.classList.add('show');
    const body=document.getElementById('ccdash-body');
    body.innerHTML='<div style="text-align:center;padding:60px;color:var(--muted)">Carregando…</div>';
    let rows=[];
    try { const r=await apiGet('listar_cotacoes'); rows=(r&&r.data)||[]; }
    catch(e){ body.innerHTML='<div style="text-align:center;padding:60px;color:#c0392b">Não consegui carregar as cotações.</div>'; return; }
    ccDashRender(rows);
  };

  function tally(rows, keyFn){ const m={}; rows.forEach(r=>{ const k=keyFn(r); if(k==null||k==='')return; (Array.isArray(k)?k:[k]).forEach(v=>{ v=String(v).trim(); if(v) m[v]=(m[v]||0)+1; }); }); return m; }
  function sumBy(rows, keyFn, valFn){ const m={}; rows.forEach(r=>{ const k=keyFn(r); if(!k)return; m[k]=(m[k]||0)+num(valFn(r)); }); return m; }
  function splitList(s){ return String(s||'').split(/[;,\/\n]+/).map(x=>x.trim()).filter(Boolean); }

  const PAL=['#00b4a6','#2a6df4','#e0a200','#8a3ab9','#3aa0ff','#1f9b48','#e2601a','#c0161d','#0a8a8d','#d1567f','#6b7280','#12b886'];
  function bars(obj, {money=false, limit=10, mono=false}={}){
    const arr=Object.entries(obj).sort((a,b)=>b[1]-a[1]).slice(0,limit);
    if(!arr.length) return '<div class="ccd-empty">Sem dados.</div>';
    const max=Math.max.apply(null, arr.map(x=>x[1]))||1;
    return arr.map(([k,v],i)=>{ const col=mono?'linear-gradient(135deg,var(--accent),var(--accent2))':PAL[i%PAL.length];
      return '<div class="ccd-row"><span class="nm" title="'+esc(k)+'">'+esc(k)+'</span>'
      +'<span class="ccd-bar"><span style="width:'+Math.max(4,(v/max*100)).toFixed(1)+'%;background:'+col+'">'+(money?'':v.toLocaleString('pt-BR'))+'</span></span>'
      +(money?'<span class="ccd-val">'+brl(v)+'</span>':'')+'</div>'; }).join('');
  }

  // Mapa de estados (statebin) — posições [linha, coluna] em grade 7 col
  const UF_POS={RR:[1,4],AP:[1,5],AM:[2,3],PA:[2,4],MA:[2,5],CE:[2,6],RN:[2,7],
    AC:[3,2],RO:[3,3],MT:[3,4],TO:[3,5],PI:[3,6],PB:[3,7],MS:[4,4],GO:[4,5],BA:[4,6],PE:[4,7],
    DF:[5,5],MG:[5,6],AL:[5,7],SP:[6,5],RJ:[6,6],SE:[6,7],PR:[7,5],ES:[7,6],SC:[8,5],RS:[9,5]};
  function ufMap(porUF){
    const max=Math.max(1, ...Object.values(porUF));
    let h='<div class="ccd-map" style="grid-template-rows:repeat(9,1fr)">';
    Object.entries(UF_POS).forEach(([uf,[r,c]])=>{
      const v=porUF[uf]||0; const t=v/max;
      const bg=v?('rgba(0,160,150,'+(0.25+t*0.75).toFixed(2)+')'):'rgba(0,0,0,.05)';
      h+='<div class="ccd-uf'+(v?' on':'')+'" style="grid-row:'+r+';grid-column:'+c+';background:'+bg+'" title="'+uf+': '+v+'">'+uf+'<span class="c">'+(v||'')+'</span></div>';
    });
    h+='</div>';
    return h;
  }

  function ccDashRender(rows){
    const body=document.getElementById('ccdash-body');
    const sub=document.getElementById('ccdash-sub');
    const n=rows.length;
    const norm=s=>String(s||'').toLowerCase();
    const vidas=rows.reduce((a,r)=>a+num(r.numero_vidas),0);
    const premio=rows.reduce((a,r)=>a+num(r.premio),0);
    const ticket=vidas>0?premio/vidas:0;
    const ganhasArr=rows.filter(r=>/finaliz|conclu|passagem/.test(norm(r.status)));
    const ganhas=ganhasArr.length;
    const premioGanho=ganhasArr.reduce((a,r)=>a+num(r.premio),0);
    const declin=rows.filter(r=>/declin/.test(norm(r.status))).length;
    const emAberto=rows.filter(r=>!/finaliz|conclu|declin/.test(norm(r.status))).length;
    const conv=n>0?Math.round(ganhas/n*100):0;
    if(sub) sub.textContent=n+' cotações · '+vidas.toLocaleString('pt-BR')+' vidas · atualizado agora';

    // Pontos de atenção
    const hoje=new Date().toISOString().slice(0,10);
    const semAnalista=rows.filter(r=>!(r.analista||'').trim()).length;
    const proxVencida=rows.filter(r=>(r.data_proxima_acao||'')&&(r.data_proxima_acao<hoje)&&!/finaliz|conclu|declin/.test(norm(r.status))).length;
    const semPremioGanho=ganhasArr.filter(r=>!num(r.premio)).length;
    const semCnpj=rows.filter(r=>!(r.cnpj||'').trim()).length;
    const semUF=rows.filter(r=>(r.cnpj||'').trim()&&!(r.uf||'').trim()).length;

    // agregações
    const porStatus=tally(rows,r=>r.status), porOperCtr=tally(rows,r=>r.player_contratado||r.operadora_atual);
    const playersCot=tally(rows,r=>splitList(r.players_cotados)), porTipo=tally(rows,r=>r.tipo), porRamo=tally(rows,r=>r.ramo);
    const porAnalista=tally(rows,r=>r.analista), porEntrada=tally(rows,r=>r.entrada);
    const premioOper=sumBy(rows,r=>(r.player_contratado||r.operadora_atual||'').trim(),r=>r.premio);
    const vidasOper=sumBy(rows,r=>(r.player_contratado||r.operadora_atual||'').trim(),r=>r.numero_vidas);
    const declinios=tally(rows.filter(r=>/declin/.test(norm(r.status))||r.motivo_declinio),r=>r.motivo_declinio);
    const porUF=tally(rows,r=>(r.uf||'').trim().toUpperCase());
    const porMes={}; rows.forEach(r=>{ const d=(r.data_recebimento||'').slice(0,7); if(/^\d{4}-\d{2}$/.test(d)){ const [y,mo]=d.split('-'); porMes[MESES[+mo-1]+'/'+y.slice(2)]=(porMes[MESES[+mo-1]+'/'+y.slice(2)]||0)+1; } });
    const mesesOrd={}; Object.keys(porMes).sort((a,b)=>{ const pa=a.split('/'),pb=b.split('/'); return (pa[1]+String(MESES.indexOf(pa[0])).padStart(2,'0')).localeCompare(pb[1]+String(MESES.indexOf(pb[0])).padStart(2,'0')); }).forEach(k=>mesesOrd[k]=porMes[k]);

    const card=(icon,t,html)=>'<div class="ccd-card"><h3><span class="material-symbols-rounded">'+icon+'</span>'+t+'</h3>'+html+'</div>';
    const kpi=(l,v,s,c)=>'<div class="ccd-kpi" style="border-left-color:'+c+'"><div class="l">'+l+'</div><div class="v" style="color:'+c+'">'+v+'</div><div class="s">'+s+'</div></div>';
    const alert=(icon,c,nm,v,warn)=>'<div class="ccd-alert"><span class="material-symbols-rounded" style="color:'+c+'">'+icon+'</span><span class="al-nm">'+nm+'</span><span class="al-v" style="color:'+(warn?c:'var(--muted)')+'">'+v+'</span></div>';

    let h='';
    h+='<div class="ccd-kpis">'
      +kpi('Cotações',n.toLocaleString('pt-BR'),vidas.toLocaleString('pt-BR')+' vidas cotadas','#00b4a6')
      +kpi('Ganhas',ganhas+' / '+n,'conversão '+conv+'%','#1f9b48')
      +kpi('Em aberto',emAberto.toLocaleString('pt-BR'),'em andamento','#2a6df4')
      +kpi('Prêmio total',brl(premio),'ganho '+brl(premioGanho),'#0a8a8d')
      +kpi('Ticket médio / vida',brl(ticket),'prêmio ÷ vidas','#e0a200')
      +'</div>';

    // Pontos de atenção + Motivos de declínio (lado a lado)
    let pa='';
    pa+=alert('person_off','#c0161d','Cotações sem analista',semAnalista,semAnalista>0);
    pa+=alert('event_busy','#e0a200','Próxima ação vencida (em aberto)',proxVencida,proxVencida>0);
    pa+=alert('payments','#8a3ab9','Ganhas sem prêmio informado',semPremioGanho,semPremioGanho>0);
    pa+=alert('badge','#2a6df4','Sem CNPJ cadastrado',semCnpj,false);
    pa+=alert('map','#e2601a','Com CNPJ mas sem UF (rodar preenchimento)',semUF,semUF>0);
    h+='<div class="ccd-grid">'
      + card('notifications','Pontos de atenção', pa)
      + card('trending_down','Motivos de declínio ('+declin+' declinadas)', bars(declinios,{limit:12}))
      +'</div>';

    // Mapa por estado
    let mapExtra='';
    if(semUF>0){ mapExtra='<div style="margin-top:12px;font-size:11.5px;color:var(--muted)">'+semUF+' cotação(ões) com CNPJ ainda sem UF. '
      +'<button class="btn btn-outline btn-sm" onclick="ccBackfillUF(this)"><span class="material-symbols-rounded" style="font-size:14px">travel_explore</span> Preencher UF pela Receita</button></div>'; }
    h+='<div class="ccd-grid">'
      + card('public','Distribuição por estado (UF)', ufMap(porUF)+mapExtra)
      + card('donut_large','Por status', bars(porStatus,{limit:20}))
      +'</div>';

    h+='<div class="ccd-grid">'
      + card('apartment','Operadora contratada / atual', bars(porOperCtr))
      + card('groups','Players mais cotados', bars(playersCot))
      +'</div>';
    h+='<div class="ccd-grid">'
      + card('savings','Prêmio por operadora (R$)', bars(premioOper,{money:true}))
      + card('diversity_3','Vidas por operadora', bars(vidasOper))
      +'</div>';
    h+='<div class="ccd-grid">'
      + card('category','Por tipo', bars(porTipo))
      + card('medical_services','Por ramo', bars(porRamo))
      +'</div>';
    h+='<div class="ccd-grid">'
      + card('badge','Por analista', bars(porAnalista))
      + card('call_received','Por entrada', bars(porEntrada))
      +'</div>';
    h+=card('calendar_month','Volume por mês (recebimento)', bars(mesesOrd,{limit:24,mono:true}));
    body.innerHTML=h;
  }

  window.ccBackfillUF = async function(btn){
    if(btn){ btn.disabled=true; btn.innerHTML='<span class="material-symbols-rounded" style="font-size:14px">hourglass_top</span> Consultando…'; }
    try{
      let rest=1, voltas=0;
      while(rest>0 && voltas<40){
        const r=await apiGet('backfill_uf_cotacoes',{lote:30});
        if(!r||!r.ok) break;
        rest=r.restantes||0; voltas++;
        if(btn) btn.innerHTML='<span class="material-symbols-rounded" style="font-size:14px">hourglass_top</span> Faltam '+rest+'…';
      }
    }catch(e){}
    ccDashOpen(); // recarrega o dash com as UFs preenchidas
  };
})();
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
