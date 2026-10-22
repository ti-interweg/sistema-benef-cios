<?php
$currentPage = 'fechamento';
$pageTitle   = 'Fechamento — Interweg Benefícios';
$pageScripts = ['assets/fechamento.js?v=' . time()];

require_once __DIR__ . '/_header.php';
?>

<!-- ─── STUBS INLINE (rodam IMEDIATO, antes do JS externo) ─── -->
<script>
window.fcOpenModal    = window.fcOpenModal    || function(){ alert('Carregando módulo... Aguarde 1s e tente de novo.'); };
window.fcLoad         = window.fcLoad         || function(){};
window.fcEdit         = window.fcEdit         || function(){ alert('Carregando módulo...'); };
window.fcSave         = window.fcSave         || function(){};
window.fcDelete       = window.fcDelete       || function(){};
window.fcExportXLSX   = window.fcExportXLSX   || function(){ alert('Carregando módulo...'); };
window.fcFiltrarPorCard = window.fcFiltrarPorCard || function(){};
</script>

<style>
  /* Modal Fechamento */
  #mod-fc .fg{margin-bottom:14px;gap:5px}
  #mod-fc input[type=text], #mod-fc input[type=date], #mod-fc input[type=number], #mod-fc select, #mod-fc textarea{padding:8px 11px;font-size:13px;border-radius:7px}
  #mod-fc label{font-size:11px}
  #mod-fc .row2, #mod-fc .row3, #mod-fc .row4{gap:18px;margin-bottom:6px}
  #mod-fc .sec{margin:14px 0 8px}
  #mod-fc input[type=date]{width:100%;max-width:180px}

  /* Badges de tipo */
  .fct{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;display:inline-block;white-space:nowrap;letter-spacing:.2px}
  .fct-novo        { background:#c6e9c8; color:#256029; }
  .fct-renovacao   { background:#cfe2ff; color:#0d4d8c; }
  .fct-inclusao    { background:#ffe699; color:#7a5d00; }
  .fct-reajuste    { background:#fcd0d3; color:#9b1c2a; }
  .fct-default     { background:#e9ecef; color:#666;    }
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Fechamento</div>
      <div class="page-sub">Registro de contratos fechados — valores, vidas, comissão e agenciamento</div>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-outline" onclick="fcExportXLSX()"><span class="material-symbols-rounded" style="font-size:17px">download</span> Exportar Excel</button>
      <button class="btn btn-primary" onclick="fcOpenModal()"><span class="material-symbols-rounded" style="font-size:19px">add</span> Novo Fechamento</button>
    </div>
  </div>

  <style>
    .card{cursor:pointer;transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease}
    .card:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.08)}
    .card.fc-active{border:2px solid var(--accent);box-shadow:0 0 0 3px rgba(0,191,179,.15)}

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
    /* Linhas do Fechamento com métricas extras (Fatura / Agenc. / Comis.) */
    .fc-brk-list{display:flex;flex-direction:column;gap:5px}
    .fc-brk-row{padding:9px 10px;border-radius:10px;cursor:pointer;transition:background .12s}
    .fc-brk-row:hover{background:rgba(0,191,179,.07)}
    .fc-brk-row.cc-brk-active .fc-brk-lbl{color:var(--accent);font-weight:600}
    .fc-brk-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px}
    .fc-brk-lbl{font-size:13px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .fc-brk-count{font-size:12px;color:var(--muted);white-space:nowrap}
    .fc-brk-count b{color:var(--text);font-weight:700;font-size:14px}
    .fc-brk-metrics{display:flex;flex-wrap:wrap;gap:3px 18px;margin-top:7px;font-size:11.5px;color:var(--muted)}
    .fc-brk-metrics b{color:var(--text);font-weight:600}
  </style>

  <div class="filters">
    <input class="search-inp" type="text" id="ff-search" placeholder="Buscar por empresa, CNPJ, operadora, observação..." oninput="fcLoad()" autocomplete="off"/>
    <!-- TIPO (multi) -->
    <div class="ms" id="msf-tipo">
      <button type="button" class="ms-btn" onclick="fcMsToggle('tipo',event)"><span class="ms-label" id="msf-tipo-label">Todos os tipos</span><span class="ms-caret">▾</span></button>
      <div class="ms-panel">
        <label class="ms-opt"><input type="checkbox" value="Renovação" onchange="fcMsChange('tipo')"> Renovação</label>
        <label class="ms-opt"><input type="checkbox" value="Nomeação" onchange="fcMsChange('tipo')"> Nomeação</label>
        <label class="ms-opt"><input type="checkbox" value="Seguro Novo" onchange="fcMsChange('tipo')"> Seguro Novo</label>
        <label class="ms-opt"><input type="checkbox" value="Seguro Novo de Cliente Interweg" onchange="fcMsChange('tipo')"> Seguro Novo de Cliente Interweg</label>
        <div class="ms-actions"><button type="button" onclick="fcMsClear('tipo')">Limpar</button></div>
      </div>
    </div>
    <!-- RAMO (multi) -->
    <div class="ms" id="msf-ramo">
      <button type="button" class="ms-btn" onclick="fcMsToggle('ramo',event)"><span class="ms-label" id="msf-ramo-label">Todos os ramos</span><span class="ms-caret">▾</span></button>
      <div class="ms-panel">
        <label class="ms-opt"><input type="checkbox" value="Saúde" onchange="fcMsChange('ramo')"> Saúde</label>
        <label class="ms-opt"><input type="checkbox" value="Odonto" onchange="fcMsChange('ramo')"> Odonto</label>
        <label class="ms-opt"><input type="checkbox" value="Saúde/Odonto" onchange="fcMsChange('ramo')"> Saúde/Odonto</label>
        <label class="ms-opt"><input type="checkbox" value="Vida em Grupo" onchange="fcMsChange('ramo')"> Vida em Grupo</label>
        <label class="ms-opt"><input type="checkbox" value="Vida Individual" onchange="fcMsChange('ramo')"> Vida Individual</label>
        <label class="ms-opt"><input type="checkbox" value="Seguro Estagiário" onchange="fcMsChange('ramo')"> Seguro Estagiário</label>
        <label class="ms-opt"><input type="checkbox" value="Outros" onchange="fcMsChange('ramo')"> Outros</label>
        <div class="ms-actions"><button type="button" onclick="fcMsClear('ramo')">Limpar</button></div>
      </div>
    </div>
    <!-- OPERADORA (multi) -->
    <div class="ms" id="msf-operadora">
      <button type="button" class="ms-btn" onclick="fcMsToggle('operadora',event)"><span class="ms-label" id="msf-operadora-label">Todas as operadoras</span><span class="ms-caret">▾</span></button>
      <div class="ms-panel" id="msf-operadora-panel">
        <label class="ms-opt"><input type="checkbox" value="AMIL" onchange="fcMsChange('operadora')"> AMIL</label>
        <label class="ms-opt"><input type="checkbox" value="Bradesco" onchange="fcMsChange('operadora')"> Bradesco</label>
        <label class="ms-opt"><input type="checkbox" value="SulAmérica" onchange="fcMsChange('operadora')"> SulAmérica</label>
        <label class="ms-opt"><input type="checkbox" value="Unimed BH" onchange="fcMsChange('operadora')"> Unimed BH</label>
        <label class="ms-opt"><input type="checkbox" value="Unimed MA" onchange="fcMsChange('operadora')"> Unimed MA</label>
        <label class="ms-opt"><input type="checkbox" value="Unimed Vitória" onchange="fcMsChange('operadora')"> Unimed Vitória</label>
        <label class="ms-opt"><input type="checkbox" value="Hapvida" onchange="fcMsChange('operadora')"> Hapvida</label>
        <label class="ms-opt"><input type="checkbox" value="NotreDame" onchange="fcMsChange('operadora')"> NotreDame</label>
        <label class="ms-opt"><input type="checkbox" value="Samel" onchange="fcMsChange('operadora')"> Samel</label>
        <label class="ms-opt"><input type="checkbox" value="Aurora" onchange="fcMsChange('operadora')"> Aurora</label>
        <label class="ms-opt"><input type="checkbox" value="Porto Seguro" onchange="fcMsChange('operadora')"> Porto Seguro</label>
        <label class="ms-opt"><input type="checkbox" value="Allianz" onchange="fcMsChange('operadora')"> Allianz</label>
        <label class="ms-opt"><input type="checkbox" value="Outras" onchange="fcMsChange('operadora')"> Outras</label>
        <div class="ms-actions"><button type="button" onclick="fcMsClear('operadora')">Limpar</button></div>
      </div>
    </div>
    <!-- MÊS (multi) -->
    <div class="ms" id="msf-mes">
      <button type="button" class="ms-btn" onclick="fcMsToggle('mes',event)"><span class="ms-label" id="msf-mes-label">Todos os meses</span><span class="ms-caret">▾</span></button>
      <div class="ms-panel">
        <label class="ms-opt"><input type="checkbox" value="Janeiro" onchange="fcMsChange('mes')"> Janeiro</label>
        <label class="ms-opt"><input type="checkbox" value="Fevereiro" onchange="fcMsChange('mes')"> Fevereiro</label>
        <label class="ms-opt"><input type="checkbox" value="Março" onchange="fcMsChange('mes')"> Março</label>
        <label class="ms-opt"><input type="checkbox" value="Abril" onchange="fcMsChange('mes')"> Abril</label>
        <label class="ms-opt"><input type="checkbox" value="Maio" onchange="fcMsChange('mes')"> Maio</label>
        <label class="ms-opt"><input type="checkbox" value="Junho" onchange="fcMsChange('mes')"> Junho</label>
        <label class="ms-opt"><input type="checkbox" value="Julho" onchange="fcMsChange('mes')"> Julho</label>
        <label class="ms-opt"><input type="checkbox" value="Agosto" onchange="fcMsChange('mes')"> Agosto</label>
        <label class="ms-opt"><input type="checkbox" value="Setembro" onchange="fcMsChange('mes')"> Setembro</label>
        <label class="ms-opt"><input type="checkbox" value="Outubro" onchange="fcMsChange('mes')"> Outubro</label>
        <label class="ms-opt"><input type="checkbox" value="Novembro" onchange="fcMsChange('mes')"> Novembro</label>
        <label class="ms-opt"><input type="checkbox" value="Dezembro" onchange="fcMsChange('mes')"> Dezembro</label>
        <div class="ms-actions"><button type="button" onclick="fcMsClear('mes')">Limpar</button></div>
      </div>
    </div>
    <!-- ANALISTA (multi, preenchido via JS) -->
    <div class="ms" id="msf-analista">
      <button type="button" class="ms-btn" onclick="fcMsToggle('analista',event)"><span class="ms-label" id="msf-analista-label">Todos os analistas</span><span class="ms-caret">▾</span></button>
      <div class="ms-panel" id="msf-analista-panel"></div>
    </div>
    <input type="date" id="ff-de" onchange="fcLoad()" title="Vigência (de)"/>
    <input type="date" id="ff-ate" onchange="fcLoad()" title="Vigência (até)"/>
  </div>

  <div class="table-wrap">
    <div class="table-head">
      <div class="table-title" id="fc-count">Lista de Fechamentos</div>
    </div>
    <div class="tscroll">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Tipo</th>
            <th>Entrada</th>
            <th>Ramo</th>
            <th>Operadora</th>
            <th>Mês</th>
            <th>Empresa</th>
            <th>CNPJ</th>
            <th>Analista</th>
            <th>Vigência Op.</th>
            <th>Vidas</th>
            <th>% Agenc.</th>
            <th>% Comis.</th>
            <th>Somatório de Faturas</th>
            <th>Obs.</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody id="fc-tbody"></tbody>
      </table>
    </div>
    <div class="pag">
      <span id="fc-pag-info">—</span>
      <div class="pag-btns" id="fc-pag-btns"></div>
    </div>
  </div>
</main>

<!-- ── MODAL FECHAMENTO ─────────────────────────────────── -->
<div class="ov" id="mod-fc" onclick="if(event.target===this)closeModal('mod-fc')">
  <div class="modal" style="width:920px;max-width:95vw">
    <div class="modal-title" id="mf-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">task_alt</span> Novo Fechamento</div>
    <div class="modal-sub">Registro de fechamento de contrato</div>
    <input type="hidden" id="mf-id"/>

    <div class="sec">Identificação</div>
    <div class="row3">
      <div class="fg"><label>Tipo *</label>
        <select id="mf-tipo">
          <option value="">Selecione...</option>
          <option>Renovação</option><option>Nomeação</option><option>Seguro Novo</option><option>Seguro Novo de Cliente Interweg</option>
        </select>
      </div>
      <div class="fg"><label>Entrada</label>
        <select id="mf-entrada">
          <option value="">Selecione...</option>
          <option>Interweg MG</option><option>Interweg JF</option><option>Interweg SP</option>
          <option>Parceiro - Elmo</option><option>Fred</option><option>Parceiro - Bernardo</option>
          <option>Finlândia</option>
        </select>
      </div>
      <div class="fg"><label>Ramo</label>
        <select id="mf-ramo">
          <option value="">Selecione...</option>
          <option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option>
          <option>Vida em Grupo</option><option>Vida Individual</option><option>Seguro Estagiário</option><option>Outros</option>
        </select>
      </div>
    </div>

    <div class="row3">
      <div class="fg"><label>Operadora</label>
        <select id="mf-operadora">
          <option value="">Selecione...</option>
          <option>AMIL</option><option>Bradesco</option><option>SulAmérica</option>
          <option>Unimed BH</option><option>Unimed MA</option><option>Unimed Vitória</option>
          <option>Hapvida</option><option>NotreDame</option><option>Samel</option><option>Aurora</option>
          <option>Porto Seguro</option><option>Allianz</option><option>Outras</option>
        </select>
      </div>
      <div class="fg"><label>Mês</label>
        <select id="mf-mes">
          <option value="">Selecione...</option>
          <option>Janeiro</option><option>Fevereiro</option><option>Março</option><option>Abril</option>
          <option>Maio</option><option>Junho</option><option>Julho</option><option>Agosto</option>
          <option>Setembro</option><option>Outubro</option><option>Novembro</option><option>Dezembro</option>
        </select>
      </div>
      <div class="fg"><label>Analista</label>
        <select id="mf-analista"><option value="">Selecione...</option></select>
      </div>
    </div>

    <div class="sec">Empresa</div>
    <div class="row2">
      <div class="fg"><label>Empresa *</label><input type="text" id="mf-empresa" placeholder="Ex: ARCOL ESTRUTURAS LTDA"/></div>
      <div class="fg"><label>CNPJ</label><input type="text" id="mf-cnpj" placeholder="00.000.000/0000-00"/></div>
    </div>

    <div class="sec">Valores</div>
    <div class="row2">
      <div class="fg"><label>Vigência Operadora</label><input type="date" id="mf-vigencia"/></div>
      <div class="fg"><label>Nº de Vidas</label><input type="number" id="mf-vidas" min="0" placeholder="Ex: 50"/></div>
    </div>
    <div class="fg">
      <label>Agenciamento (%) — informe as parcelas; a tabela mostra o total</label>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="text" id="mf-agenc-1" placeholder="1º" style="flex:1;min-width:78px" oninput="fcAgencTotal()"/>
        <input type="text" id="mf-agenc-2" placeholder="2º" style="flex:1;min-width:78px" oninput="fcAgencTotal()"/>
        <input type="text" id="mf-agenc-3" placeholder="3º" style="flex:1;min-width:78px" oninput="fcAgencTotal()"/>
        <input type="text" id="mf-agenc-4" placeholder="4º" style="flex:1;min-width:78px" oninput="fcAgencTotal()"/>
        <input type="text" id="mf-agenc-5" placeholder="5º" style="flex:1;min-width:78px" oninput="fcAgencTotal()"/>
      </div>
      <div style="font-size:12px;color:var(--muted);margin-top:5px">Total agenciamento: <strong id="mf-agenc-total" style="color:var(--accent)">0,00%</strong></div>
    </div>
    <div class="fg">
      <label>Valor das Faturas (R$) — informe as parcelas; a tabela mostra o total</label>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="text" id="mf-fatura-1" placeholder="1ª" style="flex:1;min-width:90px" oninput="fcFaturaTotal()"/>
        <input type="text" id="mf-fatura-2" placeholder="2ª" style="flex:1;min-width:90px" oninput="fcFaturaTotal()"/>
        <input type="text" id="mf-fatura-3" placeholder="3ª" style="flex:1;min-width:90px" oninput="fcFaturaTotal()"/>
        <input type="text" id="mf-fatura-4" placeholder="4ª" style="flex:1;min-width:90px" oninput="fcFaturaTotal()"/>
        <input type="text" id="mf-fatura-5" placeholder="5ª" style="flex:1;min-width:90px" oninput="fcFaturaTotal()"/>
      </div>
      <div style="font-size:12px;color:var(--muted);margin-top:5px">Total faturas: <strong id="mf-fatura-total" style="color:var(--accent)">R$ 0,00</strong></div>
    </div>
    <div class="row2">
      <div class="fg"><label>% Comissão</label><input type="text" id="mf-comis" placeholder="Ex: 7,50"/></div>
    </div>

    <div class="sec">Comissão Recebida (lançamento do Financeiro)</div>
    <div class="row2"><div class="fg"><label>1ª — Valor recebido (R$)</label><input type="text" id="mf-comrec-1" placeholder="Ex: 1.500,00"/></div><div class="fg"><label>1ª — Data recebida</label><input type="date" id="mf-comdata-1"/></div></div>
    <div class="row2"><div class="fg"><label>2ª — Valor recebido (R$)</label><input type="text" id="mf-comrec-2"/></div><div class="fg"><label>2ª — Data recebida</label><input type="date" id="mf-comdata-2"/></div></div>
    <div class="row2"><div class="fg"><label>3ª — Valor recebido (R$)</label><input type="text" id="mf-comrec-3"/></div><div class="fg"><label>3ª — Data recebida</label><input type="date" id="mf-comdata-3"/></div></div>
    <div class="row2"><div class="fg"><label>4ª — Valor recebido (R$)</label><input type="text" id="mf-comrec-4"/></div><div class="fg"><label>4ª — Data recebida</label><input type="date" id="mf-comdata-4"/></div></div>
    <div class="row2"><div class="fg"><label>5ª — Valor recebido (R$)</label><input type="text" id="mf-comrec-5"/></div><div class="fg"><label>5ª — Data recebida</label><input type="date" id="mf-comdata-5"/></div></div>

    <div class="fg"><label>Observações</label><textarea id="mf-obs" rows="3" placeholder="Observações gerais do fechamento..."></textarea></div>

    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeModal('mod-fc')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
      <button class="btn btn-outline" id="mf-btn-del" style="display:none;border-color:var(--danger);color:var(--danger)" onclick="fcDelete()"><span class="material-symbols-rounded" style="font-size:17px">delete</span> Excluir</button>
      <button class="btn btn-primary" data-edit-only onclick="fcSave()"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar Fechamento</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
           