<?php
$currentPage = 'faturamento';
$pageTitle   = 'Faturamento — Interweg Benefícios';
$pageScripts = ['assets/faturamento.js'];

require_once __DIR__ . '/_header.php';
?>

<style>
  /* Modal Faturamento — espaçamento confortável + amarelo discreto */
  #mod-fat .fg, #mod-fat-mensal .fg, #mod-virar .fg{margin-bottom:14px;gap:5px}
  #mod-fat input[type=text], #mod-fat input[type=date], #mod-fat input[type=number], #mod-fat input[type=month], #mod-fat select, #mod-fat textarea,
  #mod-fat-mensal input, #mod-fat-mensal select, #mod-fat-mensal textarea,
  #mod-virar input, #mod-virar select{padding:8px 11px;font-size:13px;border-radius:7px}
  #mod-fat label, #mod-fat-mensal label, #mod-virar label{font-size:11px}
  #mod-fat .row2, #mod-fat .row3, #mod-fat .row4{gap:28px;margin-bottom:6px}
  #mod-fat .sec, #mod-fat-mensal .sec, #mod-virar .sec{margin:14px 0 8px}

  /* Amarelo dos campos obrigatórios financeiros: faixa lateral fina, sem fundo gritante */
  #mod-fat .fg-warn{
    background:transparent !important;
    border:none !important;
    border-left:3px solid var(--warn) !important;
    padding:2px 0 2px 10px !important;
    border-radius:0 !important;
  }
  #mod-fat .fg-warn label{color:var(--warn) !important}

  /* Date/month inputs com largura compacta (sem espaço vazio até o calendário) */
  #mod-fat input[type=date], #mod-fat input[type=month]{width:100%;max-width:180px}
  #mod-fat input[type=date]::-webkit-calendar-picker-indicator,
  #mod-fat input[type=month]::-webkit-calendar-picker-indicator{padding:0;margin-left:4px;cursor:pointer;opacity:.6}
  #mod-fat input[type=date]::-webkit-calendar-picker-indicator:hover,
  #mod-fat input[type=month]::-webkit-calendar-picker-indicator:hover{opacity:1}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Faturamento Anteriores</div>
      <div class="page-sub">Rotina mensal de emissão, envio e conferência de comissão</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn btn-outline" onclick="fatOpenExportModal()"><span class="material-symbols-rounded" style="font-size:17px">download</span> Exportar Excel</button>
      <?php if (!$isFinanceiro && !$isVisualizador): ?>
      <button class="btn btn-outline" onclick="fatOpenImportFechamento()" title="Importar planilha mensal preenchida (vidas, prêmio, marca como Enviado)"><span class="material-symbols-rounded" style="font-size:17px">upload</span> Importar Fechamento</button>
      <?php endif; ?>
      <?php if ($isAdmin): ?>
      <button class="btn btn-outline" onclick="openModalVirarMes()" title="Duplicar todas as linhas de uma competência para outra"><span class="material-symbols-rounded" style="font-size:17px">event_repeat</span> Virar mês</button>
      <?php endif; ?>
      <?php if (!$isFinanceiro && !$isVisualizador): ?>
      <button class="btn btn-outline" onclick="fatOpenGerarCarteira()" title="Cria os faturamentos pendentes do mês a partir de TODA a carteira (base de clientes)"><span class="material-symbols-rounded" style="font-size:17px">playlist_add</span> Gerar mês pela carteira</button>
      <?php endif; ?>
      <?php if (!$isFinanceiro && !$isVisualizador): ?>
      <a class="btn btn-primary" href="faturamento_backup.php" title="Novos cadastros vão para o Faturamento Atual"><span class="material-symbols-rounded" style="font-size:19px">add</span> Novo Faturamento (no Atual)</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="cards">
    <div class="card c-teal clickable" onclick="fatFiltrarPor('total')"><div class="card-label">Total</div><div class="card-val" id="fat-total">—</div><div class="card-sub">registros</div><div class="card-icon"><span class="material-symbols-rounded">receipt_long</span></div></div>
    <div class="card c-red clickable" onclick="fatFiltrarHoje()"><div class="card-label">A emitir hoje</div><div class="card-val" id="fat-hoje">—</div><div class="card-sub">clique para filtrar</div><div class="card-icon"><span class="material-symbols-rounded">today</span></div></div>
    <div class="card c-blue clickable" onclick="fatFiltrarPor('aguardando')"><div class="card-label">Pendentes de envio</div><div class="card-val" id="fat-aguard">—</div><div class="card-sub">pendente emissão + aguardando + em análise</div><div class="card-icon"><span class="material-symbols-rounded">mark_email_unread</span></div></div>
    <div class="card c-green clickable" onclick="fatFiltrarPor('enviado')"><div class="card-label">Enviados</div><div class="card-val" id="fat-env">—</div><div class="card-sub">concluídos</div><div class="card-icon"><span class="material-symbols-rounded">mark_email_read</span></div></div>
  </div>

  <div class="cards" style="grid-template-columns:repeat(3,1fr)">
    <div class="card c-teal"><div class="card-label">Total Prêmio Líquido</div><div class="card-val" id="fat-tot-premio" style="font-size:22px">R$ —</div><div class="card-sub">soma no período filtrado</div><div class="card-icon"><span class="material-symbols-rounded">payments</span></div></div>
    <div class="card c-blue"><div class="card-label">Comissão Prevista</div><div class="card-val" id="fat-tot-previsto" style="font-size:22px">R$ —</div><div class="card-sub">cálculo automático</div><div class="card-icon"><span class="material-symbols-rounded">request_quote</span></div></div>
    <div class="card c-green"><div class="card-label">Comissão Recebida</div><div class="card-val" id="fat-tot-recebido" style="font-size:22px">R$ —</div><div class="card-sub">preenchido pelo financeiro</div><div class="card-icon"><span class="material-symbols-rounded">account_balance_wallet</span></div></div>
  </div>

  <div class="filters">
    <input class="search-inp" type="text" id="ff-search" placeholder="Buscar por empresa, grupo, CNPJ, contrato, seguradora..." oninput="fatLoad()" autocomplete="off"/>
    <select id="ff-origem" onchange="fatLoad()">
      <option value="">Todas as origens</option>
      <option>Interweg MG</option><option>Interweg JF</option><option>Interweg SP</option><option>INTERWEG</option><option>FINLÂNDIA</option><option>INTERWEG | FINLÂNDIA</option><option>VALENTE JUNIOR</option>
    </select>
    <select id="ff-ramo" onchange="fatLoad()">
      <option value="">Todos os ramos</option>
      <option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option><option>Vida</option>
    </select>
    <select id="ff-seguradora" onchange="fatLoad()"><option value="">Todas as seguradoras</option></select>
    <select id="ff-grupo" onchange="fatLoad()"><option value="">Todos os grupos</option></select>
    <select id="ff-analista" onchange="fatLoad()"><option value="">Todos os analistas</option></select>
    <select id="ff-status" onchange="fatLoad()">
      <option value="">Todos os status</option>
      <option>Pendente emissão</option><option>Aguardando envio</option><option>Enviado</option><option>Sem emissão</option><option>Cancelado</option><option>Inadimplente</option>
      <option value="__vazio__">— Sem status (vazio) —</option>
    </select>
    <div class="emissao-range" style="display:flex;gap:6px;align-items:center;flex:1 1 320px;min-width:280px" title="Filtrar por data de emissão (deixe 'até' vazio pra filtrar dia exato)">
      <input type="date" id="ff-emissao-de"  onchange="fatLoad()" style="flex:1;min-width:0" title="Emissão de"/>
      <span style="color:var(--muted);font-size:12px;flex-shrink:0">a</span>
      <input type="date" id="ff-emissao-ate" onchange="fatLoad()" style="flex:1;min-width:0" title="Emissão até (opcional)"/>
    </div>
    <input type="month" id="ff-competencia" onchange="fatLoad()" style="min-width:150px" title="Competência"/>
  </div>

  <div class="table-wrap">
    <div class="table-head">
      <div class="table-title" id="fat-count">Lista de Faturamentos</div>
      <button class="btn btn-outline btn-sm" onclick="fatToggleResumo()"><span class="material-symbols-rounded" style="font-size:16px">summarize</span> Totais por Grupo/Seguradora</button>
    </div>
    <div id="fat-resumo" style="display:none;padding:16px 18px;border-bottom:1px solid var(--border);background:rgba(0,201,167,.03)">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <div>
          <div style="font-size:11.5px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">Por Grupo</div>
          <div id="fat-resumo-grupo" style="max-height:260px;overflow-y:auto"></div>
        </div>
        <div>
          <div style="font-size:11.5px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">Por Seguradora</div>
          <div id="fat-resumo-seg" style="max-height:260px;overflow-y:auto"></div>
        </div>
      </div>
    </div>
    <div id="fat-bulk-bar" style="display:none;align-items:center;gap:10px;padding:10px 14px;margin-bottom:10px;background:rgba(0,191,179,.08);border:1px solid var(--accent);border-radius:8px">
      <span style="font-weight:700;color:var(--accent)"><span id="fat-bulk-count">0</span> selecionado(s)</span>
      <select id="fat-bulk-status" style="padding:7px 10px;border-radius:7px">
        <option value="">Alterar status para…</option>
        <option>Pendente emissão</option>
        <option>Aguardando envio</option>
        <option>Enviado</option>
        <option>Sem emissão</option>
        <option>Cancelado</option>
        <option>Inadimplente</option>
      </select>
      <button class="btn btn-primary btn-sm" onclick="fatBulkAplicar()"><span class="material-symbols-rounded" style="font-size:16px">done_all</span> Aplicar</button>
      <button class="btn btn-outline btn-sm" style="border-color:var(--success);color:var(--success)" onclick="fatBulkConcluir()"><span class="material-symbols-rounded" style="font-size:16px">mark_email_read</span> Concluir (Enviado)</button>
      <span style="width:1px;height:22px;background:var(--border)"></span>
      <input type="month" id="fat-bulk-comp" style="padding:6px 9px;border-radius:7px" title="Nova competência"/>
      <button class="btn btn-primary btn-sm" onclick="fatBulkCompetencia()"><span class="material-symbols-rounded" style="font-size:16px">event</span> Alterar competência</button>
      <span style="width:1px;height:22px;background:var(--border)"></span>
      <select id="fat-bulk-analista" style="padding:7px 10px;border-radius:7px"><option value="">Trocar analista para…</option></select>
      <button class="btn btn-primary btn-sm" onclick="fatBulkAnalista()"><span class="material-symbols-rounded" style="font-size:16px">person</span> Trocar analista</button>
      <button class="btn btn-outline btn-sm" onclick="fatBulkLimpar()">Limpar seleção</button>
    </div>
    <div class="tscroll">
      <table>
        <thead>
          <tr>
            <th style="width:26px;text-align:center"><input type="checkbox" id="fat-check-all" onclick="fatToggleAll(this)"></th><th>#</th><th>Origem</th><th>Ramo</th><th>Seguradora</th>
            <th>Contrato / CNPJ</th><th>Grupo / Empresa</th><th>Analista</th>
            <th>Mês de envio</th><th>Competência</th><th>Emissão</th><th>Vencimento</th><th>Status</th>
            <th>Envio</th><th>Vidas</th><th>Prêmio Líq.</th><th>% Com.</th>
            <th>Prev. Comissão</th><th>Com. Recebida</th><th>Valor a Pagar</th><th>E-mails</th><th>Ações</th>
          </tr>
        </thead>
        <tbody id="fat-tbody"></tbody>
      </table>
    </div>
    <div class="pag">
      <span id="fat-pag-info">—</span>
      <div class="pag-btns" id="fat-pag-btns"></div>
    </div>
  </div>
</main>

<!-- ── MODAL FATURAMENTO (cadastro completo, igual o legado) ────── -->
<div class="ov" id="mod-fat" onclick="if(event.target===this)closeModal('mod-fat')">
  <div class="modal" style="width:880px;max-width:95vw">
    <div class="modal-title" id="mf-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">receipt_long</span> Novo Faturamento</div>
    <div class="modal-sub">Preencha todos os campos obrigatórios (*) para cadastrar um novo contrato no controle de faturamento</div>
    <input type="hidden" id="mf-id"/>

    <div class="sec">Identificação</div>
    <div class="row3">
      <div class="fg"><label>Origem *</label>
        <select id="mf-origem">
          <option value="">Selecione...</option>
          <option>Interweg MG</option>
          <option>Interweg JF</option>
          <option>Interweg SP</option>
          <option>INTERWEG</option>
          <option>FINLÂNDIA</option>
          <option>INTERWEG | FINLÂNDIA</option>
          <option>VALENTE JUNIOR</option>
        </select>
      </div>
      <div class="fg"><label>Ramo *</label>
        <select id="mf-ramo">
          <option value="">Selecione...</option>
          <option>Saúde</option>
          <option>Odonto</option>
          <option>Saúde/Odonto</option>
          <option>Vida</option>
        </select>
      </div>
      <div class="fg"><label>Seguradora *</label>
        <select id="mf-seguradora"><option value="">Selecione...</option></select>
      </div>
    </div>
    <div class="row3">
      <div class="fg"><label>Número do contrato *</label><input type="text" id="mf-contrato" placeholder="Ex: 12345-6"/></div>
      <div class="fg"><label>CNPJ *</label><input type="text" id="mf-cnpj" maxlength="18" placeholder="00.000.000/0000-00"/></div>
      <div class="fg"><label>Analista *</label>
        <select id="mf-analista-sel"><option value="">Selecione...</option></select>
      </div>
    </div>
    <div class="row2">
      <div class="fg"><label>Grupo *</label>
        <select id="mf-grupo"><option value="">Selecione...</option></select>
      </div>
      <div class="fg"><label>Empresa *</label>
        <select id="mf-empresa"><option value="">Selecione...</option></select>
      </div>
    </div>
    <div class="row1">
      <div class="fg"><label>Razão Social</label>
        <input type="text" id="mf-razao-social" placeholder="Razão social oficial (preenche automático ao escolher empresa)"/>
      </div>
    </div>

    <div class="sec">Datas e Envio</div>
    <div class="row4">
      <div class="fg"><label>Mês de envio *</label><input type="month" id="mf-competencia"/></div>
      <div class="fg"><label>Competência</label><input type="month" id="mf-competencia-real"/></div>
      <div class="fg"><label>Data de emissão *</label><input type="date" id="mf-emissao"/></div>
      <div class="fg"><label>Data de vencimento *</label><input type="date" id="mf-vencimento"/></div>
      <div class="fg"><label>Status do envio *</label>
        <select id="mf-status">
          <option>Pendente emissão</option>
          <option>Aguardando envio</option>
          <option>Enviado</option>
          <option>Sem emissão</option>
          <option>Cancelado</option>
          <option>Inadimplente</option>
        </select>
      </div>
    </div>
    <div class="row2">
      <div class="fg"><label>Data de envio <span style="color:var(--muted);font-weight:400;text-transform:none">(editável mensalmente)</span></label><input type="date" id="mf-data-envio"/></div>
      <div class="fg"><label>E-mails de envio *</label><input type="text" id="mf-emails" placeholder="financeiro@empresa.com.br; rh@empresa.com.br"/></div>
    </div>

    <div class="sec">Valores (editáveis mensalmente — comissão calculada automaticamente)</div>
    <div class="row4">
      <div class="fg fg-warn">
        <label>Nº de vidas *</label>
        <input type="number" id="mf-vidas" min="1" placeholder="Ex: 25"/>
      </div>
      <div class="fg fg-warn">
        <label>Prêmio líquido (R$) *</label>
        <input type="text" id="mf-premio" placeholder="0,00" oninput="fatCalc()"/>
      </div>
      <div class="fg"><label>% de comissão *</label>
        <input type="text" id="mf-perc-com" placeholder="Ex: 10,00" oninput="fatCalc()"/>
      </div>
      <div class="fg"><label>Valor previsto (auto)</label>
        <input type="text" id="mf-valor-prev" readonly style="background:var(--bg-hover);color:var(--accent);font-weight:700"/>
      </div>
    </div>
    <div class="row2">
      <div class="fg"><label style="color:var(--info)">Comissão recebida (financeiro)</label>
        <input type="text" id="mf-com-rec" placeholder="0,00"/>
      </div>
      <div class="fg"><label style="color:var(--info)">Valor a pagar (financeiro)</label>
        <input type="text" id="mf-valor-pagar" placeholder="0,00"/>
      </div>
    </div>
    <div class="row2">
      <div class="fg" style="grid-column:1 / -1"><label>Observações</label>
        <input type="text" id="mf-obs" placeholder="Notas, pendências..."/>
      </div>
    </div>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-fat')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
      <button class="btn btn-danger" id="mf-btn-del" onclick="fatDelete()" style="display:none"><span class="material-symbols-rounded" style="font-size:17px">delete</span> Excluir</button>
      <button class="btn btn-primary" onclick="fatSave()"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar Faturamento</button>
    </div>
  </div>
</div>

<!-- ── MODAL: AJUSTE RÁPIDO MENSAL ──────────────────────────────── -->
<div class="ov" id="mod-fat-mensal" onclick="if(event.target===this)closeModal('mod-fat-mensal')">
  <div class="modal" style="width:640px;max-width:95vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">bolt</span> Ajuste Rápido</div>
    <div class="modal-sub" id="mfm-ctx" style="font-size:12px"></div>
    <input type="hidden" id="mfm-id"/>

    <div class="row2">
      <div class="fg fg-warn"><label>Nº de vidas</label><input type="number" id="mfm-vidas" min="1"/></div>
      <div class="fg fg-warn"><label>Prêmio líquido (R$)</label><input type="text" id="mfm-premio" placeholder="0,00" oninput="fatMensalCalc()"/></div>
    </div>
    <div class="row3">
      <div class="fg"><label>Status do envio</label>
        <select id="mfm-status">
          <option>Pendente emissão</option>
          <option>Aguardando envio</option>
          <option>Enviado</option>
          <option>Sem emissão</option>
          <option>Cancelado</option>
          <option>Inadimplente</option>
        </select>
      </div>
      <div class="fg"><label>Data de envio</label><input type="date" id="mfm-data-envio"/></div>
      <div class="fg"><label>% de comissão</label>
        <input type="text" id="mfm-perc" readonly style="background:var(--bg-hover);color:var(--muted)"/>
      </div>
    </div>
    <div class="row2">
      <div class="fg"><label>Valor previsto (auto)</label>
        <input type="text" id="mfm-valor-prev" readonly style="background:var(--bg-hover);color:var(--accent);font-weight:700"/>
      </div>
      <div class="fg"><label style="color:var(--info)">Comissão recebida (financeiro)</label>
        <input type="text" id="mfm-com-rec" placeholder="0,00"/>
      </div>
    </div>
    <div class="row2">
      <div class="fg"><label>Mês de envio</label><input type="month" id="mfm-competencia"/></div>
      <div class="fg"><label>Competência</label><input type="month" id="mfm-competencia-real"/></div>
    </div>

    <div style="display:flex;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">
      <button class="btn btn-outline" onclick="fatMensalMarcarEnviado()" style="border-color:var(--success);color:var(--success)">
        <span class="material-symbols-rounded" style="font-size:16px">mark_email_read</span> Marcar como Enviado (hoje)
      </button>
    </div>

    <div class="modal-actions" style="margin-top:14px">
      <button class="btn btn-outline" onclick="closeModal('mod-fat-mensal')">Cancelar</button>
      <button class="btn btn-primary" onclick="fatMensalSave()"><span class="material-symbols-rounded" style="font-size:16px">save</span> Salvar Ajuste</button>
    </div>
  </div>
</div>

<!-- ── MODAL: IMPORTAR FECHAMENTO MENSAL ────────────────────────── -->
<div class="ov" id="mod-fat-fech" onclick="if(event.target===this)closeModal('mod-fat-fech')">
  <div class="modal" style="width:780px;max-width:96vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">upload_file</span> Importar Fechamento Mensal</div>
    <div class="modal-sub">Atualiza vidas, prêmio e marca como <strong>Enviado</strong> nos faturamentos da competência selecionada. Outras competências NÃO são afetadas.</div>

    <div style="background:rgba(0,191,179,.06);border:1px dashed var(--accent);border-radius:10px;padding:12px 14px;margin:12px 0;font-size:12.5px">
      <strong style="color:var(--accent)">Como funciona:</strong>
      <ol style="margin:6px 0 0 18px;padding:0;line-height:1.65">
        <li>A planilha precisa ter as colunas: <strong>Apólice/Contrato</strong>, <strong>Vidas</strong>, <strong>Prêmio Líquido</strong>. Opcional: <em>Data Envio</em>.</li>
        <li>O sistema casa cada linha pelo <strong>número do contrato</strong> dentro da competência escolhida — não cria linha nova, só atualiza as que já existem.</li>
        <li>Linhas que casam têm vidas, prêmio, data de envio e status=<em>Enviado</em> atualizados de uma vez.</li>
        <li>Contratos não encontrados nessa competência são listados, mas ignorados.</li>
      </ol>
    </div>

    <div class="row3">
      <div class="fg"><label>Competência alvo *</label>
        <input type="month" id="mfe-comp" placeholder="2026-04"/>
      </div>
      <div class="fg"><label>Data de envio padrão</label>
        <input type="date" id="mfe-data-envio" title="Se a planilha tiver coluna Data Envio, ela tem prioridade"/>
      </div>
      <div class="fg"><label>Modo de atualização</label>
        <select id="mfe-modo">
          <option value="sobrescrever">Sobrescrever valores existentes</option>
          <option value="completar">Só preencher onde estiver vazio</option>
        </select>
      </div>
    </div>

    <div class="fg" style="margin-top:6px">
      <label>Arquivo Excel (.xlsx)</label>
      <input type="file" id="mfe-file" accept=".xlsx,.xls" onchange="fatFechamentoPreview()" style="padding:10px;background:var(--bg);border:1.5px dashed var(--border);border-radius:8px;width:100%;cursor:pointer"/>
    </div>

    <div id="mfe-preview" style="display:none;margin-top:10px;padding:10px;background:var(--bg);border:1px solid var(--border);border-radius:8px;font-size:12.5px;max-height:280px;overflow-y:auto"></div>

    <div class="modal-actions" style="margin-top:14px">
      <button class="btn btn-outline" onclick="closeModal('mod-fat-fech')">Cancelar</button>
      <button class="btn btn-primary" id="mfe-btn-aplicar" onclick="fatFechamentoAplicar(this)" disabled><span class="material-symbols-rounded" style="font-size:16px">cloud_upload</span> Aplicar atualização</button>
    </div>
  </div>
</div>

<!-- ── MODAL: VIRAR MÊS (admin) ─────────────────────────────────── -->
<div class="ov" id="mod-virar">
  <div class="modal" style="width:520px">
    <div class="modal-title">Virar Competência</div>
    <div class="modal-sub">Duplica todas as linhas de uma competência para a próxima (vidas, prêmio e datas zerados; ajustam-se as datas ±N meses)</div>
    <div class="row2">
      <div class="fg"><label>De (origem)</label><select id="vm-de"><option value="">Selecione...</option></select></div>
      <div class="fg"><label>Para (destino)</label><input type="month" id="vm-para"/></div>
    </div>
    <div id="vm-info" style="font-size:12.5px;color:var(--muted);margin-top:6px;padding:10px;background:var(--bg);border-radius:8px">Selecione a competência de origem.</div>
    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-virar')">Cancelar</button>
      <button class="btn btn-primary" onclick="fatVirarMes(this)">Confirmar Virada</button>
    </div>
  </div>
</div>

<!-- ─── MODAL: GERAR MÊS PELA CARTEIRA ──────────────────────────── -->
<div class="ov" id="mod-gerar-carteira">
  <div class="modal" style="width:520px">
    <div class="modal-title">Gerar mês pela carteira</div>
    <div class="modal-sub">Cria os faturamentos pendentes do mês a partir de TODA a carteira (base de clientes ativos). Contratos que já existem no mês são pulados.</div>
    <div class="row2">
      <div class="fg"><label>Competência (mês)</label><input type="month" id="gc-comp"/></div>
      <div class="fg"><label>Grupo (opcional — deixe vazio p/ todos)</label><input type="text" id="gc-grupo" placeholder="ex: Mercantil"/></div>
    </div>
    <div class="row2">
      <div class="fg"><label>Data de emissão</label><input type="date" id="gc-emissao"/></div>
      <div class="fg"><label>Data de vencimento</label><input type="date" id="gc-venc"/></div>
    </div>
    <div id="gc-info" style="font-size:12.5px;color:var(--muted);margin-top:6px;padding:10px;background:var(--bg);border-radius:8px">Escolha o mês, as datas e (se quiser) filtre por grupo.</div>
    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-gerar-carteira')">Cancelar</button>
      <button class="btn btn-outline" onclick="fatBaixarPreviaCarteira(this)" title="Baixa a planilha da carteira pra revisar antes de gerar"><span class="material-symbols-rounded" style="font-size:16px">download</span> Baixar prévia</button>
      <button class="btn btn-primary" onclick="fatGerarCarteira(this)"><span class="material-symbols-rounded" style="font-size:16px">playlist_add</span> Gerar faturamentos</button>
    </div>
  </div>
</div>

<!-- ─── MODAL: EXPORTAR EXCEL ────────────────────────────────────── -->
<div class="ov" id="mod-fat-export" onclick="if(event.target===this)closeModal('mod-fat-export')">
  <div class="modal" style="width:560px;max-width:95vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">download</span> Exportar Faturamentos para Excel</div>
    <div class="modal-sub">Escolha o intervalo de <strong>data de emissão</strong> e o que incluir no arquivo.</div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;margin-top:10px">
      <div class="fg">
        <label>Data inicial</label>
        <input id="fex-data-ini" type="date"/>
      </div>
      <div class="fg">
        <label>Data final</label>
        <input id="fex-data-fim" type="date"/>
      </div>
    </div>

    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px">
      <button type="button" class="btn btn-outline btn-sm" onclick="fatExportPreset('mes')">Este mês</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="fatExportPreset('mes-ant')">Mês passado</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="fatExportPreset('30d')">Últimos 30 dias</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="fatExportPreset('ano')">Este ano</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="fatExportPreset('tudo')">Tudo</button>
    </div>

    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;margin-bottom:4px">
      <input type="checkbox" id="fex-respeita-filtros" checked/>
      Respeitar os filtros aplicados na tela (analista, status, competência etc.)
    </label>
    <p style="font-size:11.5px;color:var(--muted);margin:0 0 14px 22px">
      Desmarque pra exportar todos os faturamentos do período.
    </p>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-fat-export')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
      <button class="btn btn-primary" id="fex-btn-go" onclick="fatDoExport(this)"><span class="material-symbols-rounded" style="font-size:17px">download</span> Baixar Excel</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>