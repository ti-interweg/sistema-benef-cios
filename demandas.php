<?php
$currentPage = 'demandas';
$pageTitle   = 'Demandas — Interweg Benefícios';
$pageScripts = ['assets/demandas.js?v=' . time()];

require_once __DIR__ . '/_header.php';
?>

<style>
  /* Modal demanda — inputs mais compactos */
  #mod-demanda .fg{margin-bottom:9px;gap:3px}
  #mod-demanda input[type=text],
  #mod-demanda input[type=date],
  #mod-demanda input[type=email],
  #mod-demanda select,
  #mod-demanda textarea{padding:6px 9px;font-size:12.5px;border-radius:6px}
  #mod-demanda label{font-size:10.5px}
  #mod-demanda textarea{min-height:48px}
  #mod-demanda .row2,
  #mod-demanda .row3,
  #mod-demanda .row4{gap:10px}
  #mod-demanda .sec{margin:10px 0 6px;padding-bottom:3px;font-size:10.5px}
  #mod-demanda .modal-sub{margin-bottom:12px}
  /* Date inputs com largura fixa pra não ficarem com espaço vazio enorme até o ícone do calendário */
  #mod-demanda input[type=date]{
    width:auto;max-width:160px;
  }
  #mod-demanda input[type=date]::-webkit-calendar-picker-indicator{
    padding:0;margin-left:4px;cursor:pointer;opacity:.6
  }
  #mod-demanda input[type=date]::-webkit-calendar-picker-indicator:hover{opacity:1}

  /* Custom dropdown (cdd) usado no filtro de analista */
  .cdd{position:relative;flex:1;min-width:0}
  .cdd-btn{width:100%;background:var(--bg);border:1.5px solid var(--border);border-radius:8px;padding:7px 11px;font-size:13px;color:var(--text);cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:6px;font-family:inherit;text-align:left}
  .cdd-btn:hover{border-color:var(--accent)}
  .cdd-arrow{font-size:9px;color:var(--muted)}
  .cdd-list{display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--bg-card);border:1px solid var(--border);border-radius:8px;list-style:none;padding:4px 0;max-height:280px;overflow-y:auto;box-shadow:0 6px 24px rgba(0,0,0,.3);z-index:20}
  .cdd.open .cdd-list{display:block}
  .cdd-list li{padding:7px 12px;font-size:13px;cursor:pointer;color:var(--text)}
  .cdd-list li:hover{background:var(--bg-hover)}
  .cdd-list li.selected{background:rgba(0,201,167,.1);color:var(--accent)}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Controle de Demandas</div>
      <div class="page-sub">Gerencie todas as solicitações de benefícios</div>
    </div>
    <div style="display:flex;gap:8px">
      <?php if (!$isVisualizador): ?>
      <button class="btn btn-outline" onclick="openModalLote()"><span class="material-symbols-rounded" style="font-size:19px">library_add</span> Lote</button>
      <button class="btn btn-primary" onclick="openModalDemanda()"><span class="material-symbols-rounded" style="font-size:19px">add</span> Nova Demanda</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="cards">
    <div class="card c-teal clickable" onclick="dmFiltrarPor('total')"><div class="card-label">Total</div><div class="card-val" id="s-total">—</div><div class="card-sub">demandas</div><div class="card-icon"><span class="material-symbols-rounded">assignment</span></div></div>
    <div class="card c-blue clickable" onclick="dmFiltrarPor('analise')"><div class="card-label">Em Análise</div><div class="card-val" id="s-analise">—</div><div class="card-sub">em andamento</div><div class="card-icon"><span class="material-symbols-rounded">manage_search</span></div></div>
    <div class="card c-green clickable" onclick="dmFiltrarPor('concluido')"><div class="card-label">Concluídas</div><div class="card-val" id="s-concluido">—</div><div class="card-sub">finalizadas</div><div class="card-icon"><span class="material-symbols-rounded">task_alt</span></div></div>
    <div class="card c-red clickable" onclick="dmFiltrarPor('atrasadas')"><div class="card-label">Atrasadas</div><div class="card-val" id="s-atrasadas">—</div><div class="card-sub">próx. ação vencida</div><div class="card-icon"><span class="material-symbols-rounded">schedule</span></div></div>
  </div>

  <!-- LINHA 1 — Calendários + Selects (tudo menos busca) -->
  <div class="filters" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:10px">
    <!-- Recebimento (esquerda) -->
    <div style="display:flex;flex-direction:column;gap:3px;flex:1 1 280px;min-width:260px">
      <label style="color:var(--text);font-size:12px;font-weight:600">Recebimento</label>
      <div style="display:flex;gap:6px;align-items:center">
        <input type="date" id="f-receb-de"  onchange="dmLoad()" style="flex:1;min-width:0" title="Recebimento de"/>
        <span style="color:var(--muted);font-size:12px;flex-shrink:0">a</span>
        <input type="date" id="f-receb-ate" onchange="dmLoad()" style="flex:1;min-width:0" title="Recebimento até (opcional)"/>
      </div>
    </div>
    <!-- Próx. ação -->
    <div style="display:flex;flex-direction:column;gap:3px;flex:0 0 auto">
      <label style="color:var(--text);font-size:12px;font-weight:600">Próx. ação</label>
      <div style="display:flex;gap:4px;align-items:center">
        <input type="date" id="f-prox-de"  onchange="dmLoad()" style="width:120px" title="Próxima ação de"/>
        <span style="color:var(--muted);font-size:12px;flex-shrink:0">a</span>
        <input type="date" id="f-prox-ate" onchange="dmLoad()" style="width:120px" title="Próxima ação até (opcional)"/>
      </div>
    </div>
    <div class="cdd" id="f-analista-dd">
      <input type="hidden" id="f-analista" value=""/>
      <button type="button" class="cdd-btn" onclick="cddToggle('f-analista-dd',event)">
        <span class="cdd-label">Todos os analistas</span><span class="cdd-arrow">▼</span>
      </button>
      <ul class="cdd-list" id="f-analista-list">
        <li class="selected" data-value="" onclick="cddPick('f-analista-dd','','Todos os analistas')">Todos os analistas</li>
      </ul>
    </div>
    <select id="f-situacao" onchange="dmLoad()">
      <option value="">Todas as situações</option>
      <option>Em análise</option>
      <option>Concluído</option>
      <option>Pendente documentação empresa</option>
      <option>Pendente retorno operadora</option>
    </select>
    <select id="f-ramo" onchange="dmLoad()">
      <option value="">Todos os ramos</option><option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option><option>Vida</option><option>Enfermagem</option>
    </select>
    <select id="f-tipo" onchange="dmLoad()">
      <option value="">Todos os tipos</option>
      <option>Inclusão</option><option>Exclusão</option><option>Faturamento</option>
      <option>Autorização</option><option>Cobrança Indevida</option><option>Busca por prestador</option>
      <option>Outras demandas</option><option>Alteração Cadastral</option>
      <option>Plano Extensão</option><option>Subfatura</option><option>Abertura de subfatura</option><option>Transferência de planos</option>
      <option>Resumo mensal</option>
    </select>
    <select id="f-origem" onchange="dmLoad()">
      <option value="">Todas as origens</option>
      <option>Interweg MG</option><option>Interweg JF</option><option>Interweg SP</option>
      <option>INTERWEG</option><option>FINLÂNDIA</option><option>INTERWEG | FINLÂNDIA</option><option>VALENTE JUNIOR</option>
    </select>
  </div>

  <!-- LINHA 2 — Apenas a busca -->
  <div class="filters">
    <div class="search-wrap" id="f-search-wrap" style="flex:1">
      <span class="material-symbols-rounded search-ico">search</span>
      <input class="search-inp" type="text" id="f-search" placeholder="Buscar por beneficiário, empresa, protocolo..." autocomplete="off"/>
      <button type="button" class="search-clear" id="f-search-clear" onclick="dmClearSearch()" title="Limpar busca">
        <span class="material-symbols-rounded" style="font-size:16px">close</span>
      </button>
    </div>
  </div>

  <div class="table-wrap">
    <div class="table-head">
      <div class="table-title" id="tcount">Lista de Demandas</div>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span id="dm-selinfo" style="font-size:12px;color:var(--accent);font-weight:600"></span>
        <label style="font-size:12px;color:var(--muted)">Data conclusão</label>
        <input type="date" id="dm-lote-data" title="Data de conclusão do lote" style="height:32px;background:var(--bg);border:1.5px solid var(--border);border-radius:8px;padding:4px 8px;font-size:12px;color:var(--text)"/>
        <button class="btn btn-outline btn-sm" id="dm-btn-concluir" onclick="dmConcluirLote()" disabled><span class="material-symbols-rounded" style="font-size:16px">done_all</span> Concluir selecionadas</button>
        <button class="btn btn-outline btn-sm" onclick="dmOpenExportModal()"><span class="material-symbols-rounded" style="font-size:16px">download</span> Exportar Excel</button>
      </div>
    </div>
    <div class="tscroll">
      <table>
        <thead>
          <tr>
            <th style="text-align:center;width:34px"><input type="checkbox" id="dm-selall" onclick="dmToggleAll(this)" title="Selecionar todas"></th>
            <th>#</th><th>Grupo / Empresa</th><th>Origem</th><th>Seguradora</th><th>Ramo</th>
            <th>Tipo</th><th>Situação</th><th>Atraso</th><th>Recebimento</th>
            <th>Próx. Ação</th><th>Analista</th><th>Beneficiário</th>
            <th>Protocolo</th><th>Criado em</th><th>Ações</th>
          </tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <div class="pag">
      <span id="pag-info">—</span>
      <div class="pag-btns" id="pag-btns"></div>
    </div>
  </div>
</main>

<!-- ── MODAL: NOVA / EDITAR DEMANDA ─────────────────────────────── -->
<div class="ov" id="mod-demanda">
  <div class="modal" style="width:760px;max-width:95vw">
    <div class="modal-title" id="md-title">Nova Demanda</div>
    <div class="modal-sub">Cadastro de demanda de benefícios</div>
    <input type="hidden" id="md-id"/>

    <div class="sec">Identificação</div>
    <div class="row3">
      <div class="fg">
        <label>Origem</label>
        <select id="md-origem">
          <option value="">—</option><option>Interweg MG</option><option>Interweg JF</option><option>Interweg SP</option>
          <option>INTERWEG</option><option>FINLÂNDIA</option><option>INTERWEG | FINLÂNDIA</option><option>VALENTE JUNIOR</option>
        </select>
      </div>
      <div class="fg">
        <label>Ramo</label>
        <select id="md-ramo"><option value="">—</option><option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option><option>Vida</option><option>Enfermagem</option></select>
      </div>
      <div class="fg">
        <label>Seguradora</label>
        <select id="md-seguradora"><option value="">Selecione...</option></select>
      </div>
    </div>
    <div class="row2">
      <div class="fg"><label>Grupo</label><select id="md-grupo"><option value="">Selecione...</option></select></div>
      <div class="fg"><label>Empresa</label><select id="md-empresa"><option value="">Selecione um grupo primeiro</option></select></div>
    </div>

    <div class="sec">Demanda</div>
    <div class="row3">
      <div class="fg">
        <label>Tipo de Demanda</label>
        <select id="md-tipo">
          <option>Inclusão</option><option>Exclusão</option><option>Faturamento</option>
          <option>Autorização</option><option>Cobrança Indevida</option><option>Busca por prestador</option>
          <option>Outras demandas</option><option>Alteração Cadastral</option>
          <option>Plano Extensão</option><option>Subfatura</option><option>Abertura de subfatura</option><option>Transferência de planos</option>
          <option>Resumo mensal</option>
        </select>
      </div>
      <div class="fg">
        <label>Situação</label>
        <select id="md-situacao">
          <option>Em análise</option>
          <option>Concluído</option>
          <option>Pendente documentação empresa</option>
          <option>Pendente retorno operadora</option>
        </select>
      </div>
      <div class="fg"><label>Analista</label><select id="md-analista"><option value="">Selecione...</option></select></div>
    </div>
    <div class="row2">
      <div class="fg"><label>Recebimento</label><input type="date" id="md-recebimento"/></div>
      <div class="fg"><label>Início Tratativa</label><input type="date" id="md-ini-trat"/></div>
    </div>
    <div class="row2">
      <div class="fg"><label>Próxima Ação</label><input type="date" id="md-prox"/></div>
      <div class="fg"><label>Conclusão</label><input type="date" id="md-conclusao"/></div>
    </div>
    <div class="fg full"><label>Protocolo</label><input type="text" id="md-protocolo"/></div>
    <div class="fg full"><label>Beneficiário(s) / Dados</label><textarea id="md-benef" rows="2"></textarea></div>
    <div class="fg full"><label>Assunto Email</label><input type="text" id="md-assunto"/></div>
    <div class="fg full"><label>Observações</label><textarea id="md-obs" rows="3"></textarea></div>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-demanda')">Cancelar</button>
      <button class="btn btn-danger" id="md-btn-del" onclick="dmDelete()" style="display:none">Excluir</button>
      <button class="btn btn-primary" onclick="dmSave()">Salvar</button>
    </div>
  </div>
</div>

<!-- ── MODAL: CADASTRO EM LOTE ──────────────────────────────────── -->
<div class="ov" id="mod-lote" onclick="if(event.target===this)closeModal('mod-lote')">
  <div class="modal" style="width:1500px;max-width:97vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">library_add</span> Cadastro em Lote</div>
    <div class="modal-sub">Preencha cada linha (até 50). O Analista vem da coluna "Analista" da planilha (ou da própria tabela). Você pode importar uma planilha ou usar o modelo para acelerar.</div>

    <input type="hidden" id="ml-recebimento" value=""/>
    <input type="hidden" id="ml-analista-sel" value=""/>

    <div class="sec" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <span>Demandas — <span id="ml-count">0</span> linha(s) preenchida(s)</span>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-outline btn-sm" type="button" onclick="loteDownloadTemplate()" title="Baixar modelo .xlsx"><span class="material-symbols-rounded" style="font-size:15px">description</span> Modelo</button>
        <button class="btn btn-outline btn-sm" type="button" onclick="document.getElementById('ml-file').click()"><span class="material-symbols-rounded" style="font-size:15px">upload_file</span> Importar Planilha</button>
        <input type="file" id="ml-file" accept=".xlsx,.xls,.csv" style="display:none" onchange="loteImportFile(this)"/>
        <button class="btn btn-outline btn-sm" type="button" onclick="loteAddRow()"><span class="material-symbols-rounded" style="font-size:15px">add</span> Linha</button>
        <button class="btn btn-outline btn-sm" type="button" onclick="loteClear()"><span class="material-symbols-rounded" style="font-size:15px">delete_sweep</span> Limpar</button>
      </div>
    </div>

    <div style="max-height:46vh;overflow:auto;border:1px solid var(--border);border-radius:8px">
      <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead style="position:sticky;top:0;background:var(--bg-card);z-index:1">
          <tr style="text-align:left;white-space:nowrap">
            <th style="padding:7px;width:26px;border-bottom:1px solid var(--border)">#</th>
            <th style="padding:7px;min-width:170px;border-bottom:1px solid var(--border)">Grupo</th>
            <th style="padding:7px;min-width:200px;border-bottom:1px solid var(--border)">Empresa</th>
            <th style="padding:7px;min-width:140px;border-bottom:1px solid var(--border)">Seguradora</th>
            <th style="padding:7px;min-width:110px;border-bottom:1px solid var(--border)">Ramo</th>
            <th style="padding:7px;min-width:150px;border-bottom:1px solid var(--border)">Tipo *</th>
            <th style="padding:7px;min-width:150px;border-bottom:1px solid var(--border)">Situação</th>
            <th style="padding:7px;min-width:190px;border-bottom:1px solid var(--border)">Beneficiário</th>
            <th style="padding:7px;min-width:210px;border-bottom:1px solid var(--border)">Descrição</th>
            <th style="padding:7px;min-width:160px;border-bottom:1px solid var(--border)">Assunto E-mail</th>
            <th style="padding:7px;min-width:120px;border-bottom:1px solid var(--border)">Protocolo</th>
            <th style="padding:7px;min-width:130px;border-bottom:1px solid var(--border)">Recebimento</th>
            <th style="padding:7px;min-width:130px;border-bottom:1px solid var(--border)">Ini. Tratativa</th>
            <th style="padding:7px;min-width:130px;border-bottom:1px solid var(--border)">Próx. Ação</th>
            <th style="padding:7px;min-width:130px;border-bottom:1px solid var(--border)">Conclusão</th>
            <th style="padding:7px;min-width:140px;border-bottom:1px solid var(--border)">Analista</th>
            <th style="padding:7px;min-width:200px;border-bottom:1px solid var(--border)">Detalhamento</th>
            <th style="padding:7px;width:36px;border-bottom:1px solid var(--border)"></th>
          </tr>
        </thead>
        <tbody id="ml-tbody"></tbody>
      </table>
    </div>

    <datalist id="ml-grupos-dl"></datalist>
    <datalist id="ml-empresas-dl"></datalist>
    <datalist id="ml-segs-dl"></datalist>
    <datalist id="ml-analistas-dl"></datalist>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-lote')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
      <button class="btn btn-primary" onclick="saveLote(this)"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar Lote</button>
    </div>
  </div>
</div>

<!-- ─── MODAL: EXPORTAR EXCEL ────────────────────────────────────── -->
<div class="ov" id="mod-export" onclick="if(event.target===this)closeModal('mod-export')">
  <div class="modal" style="width:540px;max-width:95vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">download</span> Exportar Demandas para Excel</div>
    <div class="modal-sub">Escolha o intervalo de <strong>data de recebimento</strong> e o que incluir no arquivo.</div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;margin-top:10px">
      <div class="fg">
        <label>Data inicial</label>
        <input id="ex-data-ini" type="date"/>
      </div>
      <div class="fg">
        <label>Data final</label>
        <input id="ex-data-fim" type="date"/>
      </div>
    </div>

    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px">
      <button type="button" class="btn btn-outline btn-sm" onclick="dmExportPreset('mes')">Este mês</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="dmExportPreset('mes-ant')">Mês passado</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="dmExportPreset('30d')">Últimos 30 dias</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="dmExportPreset('ano')">Este ano</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="dmExportPreset('tudo')">Tudo</button>
    </div>

    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;margin-bottom:4px">
      <input type="checkbox" id="ex-respeita-filtros" checked/>
      Respeitar os filtros aplicados na tela (analista, situação, ramo, tipo, busca)
    </label>
    <p style="font-size:11.5px;color:var(--muted);margin:0 0 14px 22px">
      Desmarque pra exportar todas as demandas (limitado pelo intervalo de data acima).
    </p>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-export')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
      <button class="btn btn-primary" id="ex-btn-go" onclick="dmDoExport(this)"><span class="material-symbols-rounded" style="font-size:17px">download</span> Baixar Excel</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
