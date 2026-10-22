<?php
$currentPage = 'renovacao';
$pageTitle   = 'Renovação — Interweg Benefícios';
$pageScripts = ['assets/renovacao.js'];

require_once __DIR__ . '/_header.php';
if (!$canViewAll && !$isRenovacao) { header('Location: index.php'); exit; }
?>

<style>
  /* Modal Renovação — espaçamento confortável (mesmo padrão do Faturamento) */
  #mod-ren .fg{margin-bottom:14px;gap:5px}
  #mod-ren input[type=text],
  #mod-ren input[type=date],
  #mod-ren input[type=email],
  #mod-ren input[type=number],
  #mod-ren select,
  #mod-ren textarea{padding:8px 11px;font-size:13px;border-radius:7px}
  #mod-ren label{font-size:11px}
  #mod-ren textarea{min-height:48px}
  #mod-ren .row2,
  #mod-ren .row3,
  #mod-ren .row4{gap:28px;margin-bottom:6px}
  #mod-ren .sec{margin:14px 0 8px;padding-bottom:3px}
  #mod-ren .modal-sub{margin-bottom:12px}

  /* Date inputs com largura compacta */
  #mod-ren input[type=date], #mod-ren input[type=month]{width:100%;max-width:180px}
  #mod-ren input[type=date]::-webkit-calendar-picker-indicator,
  #mod-ren input[type=month]::-webkit-calendar-picker-indicator{padding:0;margin-left:4px;cursor:pointer;opacity:.6}
  #mod-ren input[type=date]::-webkit-calendar-picker-indicator:hover,
  #mod-ren input[type=month]::-webkit-calendar-picker-indicator:hover{opacity:1}

  /* Destaque amarelo (faixa lateral) pros campos financeiros principais */
  #mod-ren .fg-warn{
    background:transparent;
    border-left:3px solid var(--warn);
    padding:2px 0 2px 10px;
  }
  #mod-ren .fg-warn label{color:var(--warn)}

  /* Todos os campos do modal alinhados à esquerda (padrão de digitação) */
</style>

<!-- ── MODAL VIRAR ANO ────────────────────────────────────────────── -->
<div class="ov" id="mod-virar-ano" onclick="if(event.target===this)closeModal('mod-virar-ano')">
  <div class="modal" style="width:520px;max-width:96vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">event_repeat</span> Virar Ano</div>
    <div class="modal-sub">Duplica todas as renovações de um ano para o próximo. As novas entradas vêm com status <strong>"A iniciar"</strong> e os campos de tratativa (sinistralidade, % proposto, % finalizado, prêmio reajustado) ficam em branco.</div>

    <div class="sec">Origem e destino</div>
    <div class="row2">
      <div class="fg"><label>Ano de origem *</label>
        <select id="va-de"></select>
      </div>
      <div class="fg"><label>Ano de destino *</label>
        <input type="number" id="va-para" min="2024" max="2099" placeholder="Ex: 2027"/>
      </div>
    </div>

    <div style="background:rgba(0,191,179,.08);border-left:3px solid var(--accent);padding:10px 14px;border-radius:6px;margin-top:14px;font-size:13px;color:var(--text)">
      <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;color:var(--accent)">info</span>
      Pra cada contrato (identificado pelo <strong>número do contrato</strong>) é criada uma linha nova no ano de destino, com <strong>mes_renovacao</strong> deslocado em +1 ano (mesmo dia/mês). O <strong>prêmio reajustado</strong> do ciclo anterior vira o <strong>prêmio anterior</strong> do novo ciclo. Vidas, porte e analista são herdados; sinistralidade e percentuais ficam em branco.
    </div>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-virar-ano')">Cancelar</button>
      <button class="btn btn-primary" onclick="renVirarAno(this)"><span class="material-symbols-rounded" style="font-size:17px">event_repeat</span> Confirmar Virada</button>
    </div>
  </div>
</div>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Controle de Renovação</div>
      <div class="page-sub">Acompanhamento de renovações, reajustes e aditivos contratuais</div>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-outline" onclick="renExportXLSX()" title="Exporta a lista atual para Excel"><span class="material-symbols-rounded" style="font-size:17px">download</span> Exportar</button>
      <?php if (!$isVisualizador): ?>
      <button class="btn btn-outline" onclick="renDownloadModelo()" title="Baixa um modelo XLSX em branco com os cabeçalhos certos"><span class="material-symbols-rounded" style="font-size:17px">description</span> Modelo</button>
      <button class="btn btn-outline" onclick="document.getElementById('ren-import-file').click()" style="border-color:#00bfb3;color:#00bfb3" title="Importar planilha de renovações (use o botão Modelo se quiser o XLSX em branco)"><span class="material-symbols-rounded" style="font-size:17px">upload_file</span> Importar XLSX</button>
      <input type="file" id="ren-import-file" accept=".xlsx,.xls" style="display:none" onchange="renImportXLSX(this)"/>
      <button class="btn btn-outline" onclick="openModalVirarAno()" title="Duplicar todas as renovações de um ano para o próximo (igual o Virar Mês do faturamento)"><span class="material-symbols-rounded" style="font-size:17px">event_repeat</span> Virar Ano</button>
      <button class="btn btn-primary" onclick="openModalRen()"><span class="material-symbols-rounded" style="font-size:19px">add</span> Nova Renovação</button>
      <?php endif; ?>
    </div>
  </div>

  <!-- 4 cards operacionais: análise + ponto de atenção -->
  <div class="cards" style="grid-template-columns:repeat(4,1fr)">
    <div class="card c-teal clickable" onclick="renFiltroPor('em_tratativa')" title="Tudo que ainda não foi fechado, cancelado, recusado ou migrado"><div class="card-label">Carteira Ativa</div><div class="card-val" id="ren-em-trat">—</div><div class="card-sub">renovações em andamento</div><div class="card-icon"><span class="material-symbols-rounded">pending_actions</span></div></div>
    <div class="card c-yellow clickable" onclick="renFiltroPor('prox30')" title="Vencem nos próximos 30 dias — janela de ação imediata"><div class="card-label">A Vencer em 30 dias</div><div class="card-val" id="ren-prox30">—</div><div class="card-sub">ação imediata</div><div class="card-icon"><span class="material-symbols-rounded">alarm</span></div></div>
    <div class="card c-red clickable" onclick="renFiltroPor('atrasadas')" title="Data passou e ainda não fecharam — urgente"><div class="card-label">Atrasadas</div><div class="card-val" id="ren-atrasadas">—</div><div class="card-sub">data vencida</div><div class="card-icon"><span class="material-symbols-rounded">schedule</span></div></div>
    <div class="card c-blue clickable" onclick="renFiltroPor('sin_alta')" title="Sinistralidade acima de 70% — risco financeiro alto"><div class="card-label">Sinistralidade Alta</div><div class="card-val" id="ren-sin-alta-card">—</div><div class="card-sub">acima de 70%</div><div class="card-icon"><span class="material-symbols-rounded">warning</span></div></div>
  </div>

  <div class="cards" style="grid-template-columns:repeat(4,1fr)">
    <div class="card c-teal"><div class="card-label">Prêmio Anterior (total)</div><div class="card-val" id="ren-tot-ant" style="font-size:20px">R$ —</div><div class="card-sub">carteira</div><div class="card-icon"><span class="material-symbols-rounded">history</span></div></div>
    <div class="card c-blue"><div class="card-label">Prêmio Reajustado</div><div class="card-val" id="ren-tot-reaj" style="font-size:20px">R$ —</div><div class="card-sub">após renovação</div><div class="card-icon"><span class="material-symbols-rounded">trending_up</span></div></div>
    <div class="card c-green"><div class="card-label">Receita a Mais</div><div class="card-val" id="ren-tot-rec" style="font-size:20px">R$ —</div><div class="card-sub">ganho mensal estimado</div><div class="card-icon"><span class="material-symbols-rounded">savings</span></div></div>
    <div class="card c-blue"><div class="card-label">Sinistralidade Média</div><div class="card-val" id="ren-media-sin" style="font-size:20px">—</div><div class="card-sub">% da carteira</div><div class="card-icon"><span class="material-symbols-rounded">analytics</span></div></div>
  </div>

  <!-- Cards de impacto ANUAL (cálculos sobre a carteira filtrada) -->
  <div class="cards" style="grid-template-columns:repeat(2,1fr)">
    <div class="card c-green"><div class="card-label">Receita Adicional ANUAL</div><div class="card-val" id="ren-receita-anual" style="font-size:22px">R$ —</div><div style="font-size:12px;color:var(--muted);margin-top:2px">Projeção: <b id="ren-receita-anual-est" style="color:var(--accent)">R$ —</b></div><div class="card-sub">real (já reajustado) · projeção usa o % proposto/final</div><div class="card-icon"><span class="material-symbols-rounded">payments</span></div></div>
    <div class="card c-teal"><div class="card-label">Comissão Adicional ANUAL</div><div class="card-val" id="ren-comis-anual" style="font-size:22px">R$ —</div><div style="font-size:12px;color:var(--muted);margin-top:2px">Projeção: <b id="ren-comis-anual-est" style="color:var(--accent)">R$ —</b></div><div class="card-sub">real · projeção usa o % proposto/final</div><div class="card-icon"><span class="material-symbols-rounded">account_balance_wallet</span></div></div>
  </div>

  <div class="filters">
    <input class="search-inp" type="text" id="rf-search" placeholder="Buscar por empresa, CNPJ, contrato, responsável..." oninput="renLoad()" autocomplete="off"/>
    <select id="rf-origem" onchange="renLoad()">
      <option value="">Todas as origens</option>
      <option>Interweg MG</option><option>Interweg JF</option><option>Interweg SP</option>
      <option>INTERWEG</option><option>FINLÂNDIA</option><option>INTERWEG | FINLÂNDIA</option><option>VALENTE JUNIOR</option>
    </select>
    <select id="rf-ramo" onchange="renLoad()">
      <option value="">Todos os ramos</option>
      <option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option><option>Vida</option>
    </select>
    <select id="rf-seguradora" onchange="renLoad()"><option value="">Todas as seguradoras</option></select>
    <select id="rf-analista" onchange="renLoad()"><option value="">Todos os analistas</option></select>
    <select id="rf-status" onchange="renLoad()">
      <option value="">Todos os status</option>
      <option>A iniciar</option>
      <option>Pendente envio</option>
      <option>Em tratativa</option>
      <option>Em andamento</option>
      <option>Aguardando cliente</option>
      <option>Aguardando seguradora</option>
      <option>Proposta enviada</option>
      <option>Aceita</option>
      <option>Recusada</option>
      <option>Migrada</option>
      <option>Cancelado</option>
      <option>Finalizado</option>
    </select>
    <select id="rf-aditivo" onchange="renLoad()">
      <option value="">Aditivo (todos)</option>
      <option>Não há</option>
      <option>Em andamento</option>
      <option>Concluído</option>
    </select>
    <select id="rf-periodo" onchange="renLoad()">
      <option value="">Período (todos)</option>
      <option value="mes_atual">Mês atual</option>
      <option value="proximos30">Próximos 30 dias</option>
      <option value="proximos90">Próximos 90 dias</option>
      <option value="atrasado">Em atraso</option>
      <option value="em_tratativa">Em tratativa</option>
    </select>
    <input type="month" id="rf-mes" onchange="renLoad()" title="Mês de renovação específico"/>
  </div>

  <div class="table-wrap">
    <div class="table-head">
      <div class="table-title" id="ren-count">Lista de Renovações</div>
    </div>
    <div class="tscroll">
      <table>
        <thead>
          <tr>
            <th>#</th><th>Origem</th><th>Ramo</th><th>Seguradora</th>
            <th>Contrato</th><th>Empresa</th><th>Analista</th>
            <th>Início Vig.</th><th>Mês Ren.</th><th>Vidas</th><th>Porte</th>
            <th>%Com.</th><th>Ini. Trat.</th><th>Status</th><th>Sinistr.</th>
            <th>%Prop.</th><th>%Fim</th><th>Aditivo</th>
            <th>Prêmio Ant.</th><th>Prêmio Reaj.</th><th>Receita +/mês</th>
            <th>Receita /ano</th><th>Comissão /ano</th>
            <th>Obs.</th><th>Ações</th>
          </tr>
        </thead>
        <tbody id="ren-tbody"></tbody>
      </table>
    </div>
    <div class="pag">
      <span id="ren-pag-info">—</span>
      <div class="pag-btns" id="ren-pag-btns"></div>
    </div>
  </div>
</main>

<!-- ── MODAL RENOVAÇÃO (igual o legado) ─────────────────────────── -->
<div class="ov" id="mod-ren" onclick="if(event.target===this)closeModal('mod-ren')">
  <div class="modal" style="width:960px;max-width:97vw">
    <div class="modal-title" id="mr-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">autorenew</span> Nova Renovação</div>
    <div class="modal-sub">Controle de renovação contratual — preencha os campos obrigatórios (*)</div>
    <input type="hidden" id="mr-id"/>

    <div class="sec">Identificação</div>
    <div class="row3">
      <div class="fg"><label>Origem *</label>
        <select id="mr-origem">
          <option value="">Selecione...</option>
          <option>Interweg MG</option><option>Interweg JF</option><option>Interweg SP</option>
          <option>INTERWEG</option><option>FINLÂNDIA</option><option>INTERWEG | FINLÂNDIA</option><option>VALENTE JUNIOR</option>
        </select>
      </div>
      <div class="fg"><label>Ramo *</label>
        <select id="mr-ramo">
          <option value="">Selecione...</option>
          <option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option><option>Vida</option>
        </select>
      </div>
      <div class="fg"><label>Seguradora *</label>
        <select id="mr-seguradora"><option value="">Selecione...</option></select>
      </div>
    </div>
    <div class="row3">
      <div class="fg"><label>Número do contrato *</label><input type="text" id="mr-contrato" placeholder="Ex: 12345-6"/></div>
      <div class="fg"><label>CNPJ *</label><input type="text" id="mr-cnpj" maxlength="18" placeholder="00.000.000/0000-00"/></div>
      <div class="fg"><label>Analista responsável *</label>
        <select id="mr-analista-sel"><option value="">Selecione...</option></select>
      </div>
    </div>
    <div class="fg" style="position:relative">
      <label>Empresa *</label>
      <input type="text" id="mr-empresa-search" placeholder="Digite o nome da empresa (ex: BRM, BRASTORNO, BUS2)..." autocomplete="off" oninput="renEmpresaBuscar(this.value)" onfocus="renEmpresaBuscar(this.value)"/>
      <!-- Resultados do autocomplete (lista dropdown filtrada) -->
      <div id="mr-empresa-results" style="position:absolute;top:100%;left:0;right:0;max-height:280px;overflow-y:auto;background:var(--bg-card);border:1.5px solid var(--accent);border-top:none;border-radius:0 0 7px 7px;box-shadow:0 6px 16px rgba(0,0,0,.15);z-index:1000;display:none"></div>
      <!-- Valores armazenados (alimentam o save) -->
      <input type="hidden" id="mr-empresa" value=""/>
      <input type="hidden" id="mr-empresa-id" value=""/>
      <!-- mr-grupo é deduzido automaticamente da empresa selecionada -->
      <input type="hidden" id="mr-grupo" value=""/>
    </div>

    <div class="sec">Vigência e Porte</div>
    <div class="row4">
      <div class="fg"><label>Início de vigência *</label><input type="date" id="mr-ini-vig" oninput="renCalcRenovacao()"/></div>
      <div class="fg"><label>Data de renovação * <span style="color:var(--muted);font-weight:400;text-transform:none">(auto: vigência + 1 ano)</span></label><input type="date" id="mr-mes-ren"/></div>
      <div class="fg"><label>Número de vidas *</label><input type="number" id="mr-vidas" min="1" placeholder="Ex: 50" oninput="renAjustaPorte()"/></div>
      <div class="fg"><label>Porte da empresa *</label>
        <select id="mr-porte">
          <option>Até 30 vidas</option>
          <option selected>Entre 30 e 100 vidas</option>
          <option>Entre 100 e 900</option>
          <option>Acima de 1000</option>
        </select>
      </div>
    </div>

    <div class="sec">Tratativa e Status</div>
    <div class="row4">
      <div class="fg"><label>% de comissão *</label><input type="text" id="mr-perc-com" placeholder="10,00"/></div>
      <div class="fg"><label>Início da tratativa</label><input type="date" id="mr-ini-trat"/></div>
      <div class="fg fg-warn"><label>Status</label>
        <select id="mr-status">
          <option>A iniciar</option>
          <option>Pendente envio</option>
          <option>Em tratativa</option>
          <option>Em andamento</option>
          <option>Aguardando cliente</option>
          <option>Aguardando seguradora</option>
          <option>Proposta enviada</option>
          <option>Aceita</option>
          <option>Recusada</option>
          <option>Migrada</option>
          <option>Cancelado</option>
          <option>Finalizado</option>
        </select>
      </div>
      <div class="fg fg-warn"><label>Sinistralidade (%)</label><input type="text" id="mr-sinistr" placeholder="Ex: 68,50"/></div>
    </div>
    <div class="row3">
      <div class="fg fg-warn"><label>% Proposto reajuste</label><input type="text" id="mr-perc-prop" placeholder="Ex: 15,00"/></div>
      <div class="fg fg-warn"><label>% Finalizado</label><input type="text" id="mr-perc-fim" placeholder="Ex: 12,50" oninput="renCalcReajuste();renCalc()"/></div>
      <div class="fg fg-warn"><label>Aditivo</label>
        <select id="mr-aditivo">
          <option value="">—</option>
          <option>Não há</option>
          <option>Em andamento</option>
          <option>Concluído</option>
        </select>
      </div>
    </div>

    <div class="sec">Valores (campos verdes são calculados automaticamente)</div>
    <div class="row3">
      <div class="fg fg-warn">
        <label>Prêmio anterior (R$)</label>
        <input type="text" id="mr-prem-ant" placeholder="0,00" oninput="renCalcReajuste();renCalc()"/>
      </div>
      <div class="fg fg-warn">
        <label>Prêmio reajuste (R$) <span style="color:var(--muted);font-weight:400;text-transform:none">(auto: Ant × (1 + %Fim))</span></label>
        <input type="text" id="mr-prem-reaj" placeholder="0,00" oninput="renCalc()"/>
      </div>
      <div class="fg">
        <label>Receita a mais / mês (R$)</label>
        <input type="text" id="mr-receita" readonly style="background:var(--bg-hover);color:var(--accent);font-weight:700"/>
      </div>
    </div>
    <div class="row2">
      <div class="fg">
        <label>Receita Adicional ANUAL (R$)</label>
        <input type="text" id="mr-receita-anual" readonly style="background:var(--bg-hover);color:var(--accent);font-weight:700"/>
      </div>
      <div class="fg">
        <label>Comissão Adicional ANUAL (R$)</label>
        <input type="text" id="mr-comis-anual" readonly style="background:var(--bg-hover);color:var(--accent);font-weight:700"/>
      </div>
    </div>

    <div class="sec">Responsável pela empresa (cliente)</div>
    <div class="row3">
      <div class="fg"><label>Nome do responsável</label><input type="text" id="mr-resp-nome" placeholder="Nome completo"/></div>
      <div class="fg"><label>E-mail</label><input type="email" id="mr-resp-email" placeholder="email@empresa.com.br"/></div>
      <div class="fg"><label>Telefone</label><input type="text" id="mr-resp-tel" placeholder="(31) 99999-9999"/></div>
    </div>

    <div class="sec">Outros</div>
    <div class="row2">
      <div class="fg"><label>Analista que atende na seguradora</label><input type="text" id="mr-anal-seg" placeholder="Nome / contato da seguradora"/></div>
    </div>
    <div class="fg" style="margin-top:8px">
      <label>Observações</label>
      <textarea id="mr-obs" rows="5" placeholder="Notas, pendências, histórico de tratativas, contatos..." style="width:100%;min-height:120px;resize:vertical;font-family:inherit;line-height:1.5"></textarea>
    </div>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-ren')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
      <button class="btn btn-danger" id="mr-btn-del" onclick="renDelete()" style="display:none"><span class="material-symbols-rounded" style="font-size:17px">delete</span> Excluir</button>
      <button class="btn btn-primary" onclick="renSave()"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar Renovação</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
