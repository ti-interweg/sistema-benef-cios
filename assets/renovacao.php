<?php
$currentPage = 'renovacao';
$pageTitle   = 'Renovação — Interweg Benefícios';
$pageScripts = ['assets/renovacao.js'];

require_once __DIR__ . '/_header.php';
if (!$isAdmin) { header('Location: index.php'); exit; }
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
      Os campos <strong>inicio_vigencia</strong> e <strong>mes_renovacao</strong> são deslocados em +1 ano (preservando dia/mês). Origem e CNPJ continuam idênticos, ligando o novo ciclo ao contrato original.
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
      <div class="page-title">Controle de Renovação — A iniciar</div>
      <div class="page-sub">Acompanhamento de renovações, reajustes e aditivos contratuais</div>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-outline" onclick="renExportXLSX()" title="Exporta a lista atual para Excel"><span class="material-symbols-rounded" style="font-size:17px">download</span> Exportar</button>
      <button class="btn btn-outline" onclick="renDownloadModelo()" title="Baixa um modelo XLSX em branco com os cabeçalhos certos"><span class="material-symbols-rounded" style="font-size:17px">description</span> Modelo</button>
      <button class="btn btn-outline" onclick="document.getElementById('ren-import-file').click()" style="border-color:#00bfb3;color:#00bfb3" title="Importar planilha de renovações (use o botão Modelo se quiser o XLSX em branco)"><span class="material-symbols-rounded" style="font-size:17px">upload_file</span> Importar XLSX</button>
      <input type="file" id="ren-import-file" accept=".xlsx,.xls" style="display:none" onchange="renImportXLSX(this)"/>
      <button class="btn btn-outline" onclick="openModalVirarAno()" title="Duplicar todas as renovações de um ano para o próximo (igual o Virar Mês do faturamento)"><span class="material-symbols-rounded" style="font-size:17px">event_repeat</span> Virar Ano</button>
      <button class="btn btn-primary" onclick="openModalRen()"><span class="material-symbols-rounded" style="font-size:19px">add</span> Nova Renovação</button>
    </div>
  </div>

  <div class="cards">
    <div class="card c-teal"><div class="card-label">Total</div><div class="card-val" id="ren-total">—</div><div class="card-sub">renovações</div><div class="card-icon"><span class="material-symbols-rounded">autorenew</span></div></div>
    <div class="card c-red clickable" onclick="renFiltroMesAtual()"><div class="card-label">Mês atual</div><div class="card-val" id="ren-mes">—</div><div class="card-sub">a iniciar agora</div><div class="card-icon"><span class="material-symbols-rounded">event</span></div></div>
    <div class="card c-blue clickable" onclick="renFiltroProx90()"><div class="card-label">Próximos 90 dias</div><div class="card-val" id="ren-prox90">—</div><div class="card-sub">planejamento</div><div class="card-icon"><span class="material-symbols-rounded">calendar_month</span></div></div>
    <div class="card c-green"><div class="card-label">Finalizadas</div><div class="card-val" id="ren-fim">—</div><div class="card-sub">concluídas</div><div class="card-icon"><span class="material-symbols-rounded">task_alt</span></div></div>
  </div>

  <div class="cards" style="grid-template-columns:repeat(4,1fr)">
    <div class="card c-teal"><div class="card-label">Prêmio Anterior (total)</div><div class="card-val" id="ren-tot-ant" style="font-size:20px">R$ —</div><div class="card-sub">carteira</div><div class="card-icon"><span class="material-symbols-rounded">history</span></div></div>
    <div class="card c-blue"><div class="card-label">Prêmio Reajustado</div><div class="card-val" id="ren-tot-reaj" style="font-size:20px">R$ —</div><div class="card-sub">após renovação</div><div class="card-icon"><span class="material-symbols-rounded">trending_up</span></div></div>
    <div class="card c-green"><div class="card-label">Receita a Mais</div><div class="card-val" id="ren-tot-rec" style="font-size:20px">R$ —</div><div class="card-sub">ganho mensal estimado</div><div class="card-icon"><span class="material-symbols-rounded">savings</span></div></div>
    <div class="card c-blue"><div class="card-label">Sinistralidade Média</div><div class="card-val" id="ren-media-sin" style="font-size:20px">—</div><div class="card-sub">% da carteira</div><div class="card-icon"><span class="material-symbols-rounded">analytics</span></div></div>
  </div>

  <!-- Cards de impacto ANUAL (cálculos sobre a carteira filtrada) -->
  <div class="cards" style="grid-template-columns:repeat(3,1fr)">
    <div class="card c-green"><div class="card-label">Receita Adicional ANUAL</div><div class="card-val" id="ren-receita-anual" style="font-size:22px">R$ —</div><div class="card-sub">ganho extra/ano da carteira</div><div class="card-icon"><span class="material-symbols-rounded">payments</span></div></div>
    <div class="card c-teal"><div class="card-label">Comissão Adicional ANUAL</div><div class="card-val" id="ren-comis-anual" style="font-size:22px">R$ —</div><div class="card-sub">corretora ganha a mais/ano</div><div class="card-icon"><span class="material-symbols-rounded">account_balance_wallet</span></div></div>
    <div class="card c-red"><div class="card-label">Sinistralidade ALTA</div><div class="card-val" id="ren-sin-alta" style="font-size:22px">—</div><div class="card-sub">contratos com sinistr. &gt; 70%</div><div class="card-icon"><span class="material-symbols-rounded">warning</span></div></div>
  </div>

  <div class="filters">
    <input class="search-inp" type="text" id="rf-search" placeholder="Buscar por empresa, grupo, CNPJ, contrato, responsável..." oninput="renLoad()" autocomplete="off"/>
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
    <select id="rf-grupo" onchange="renLoad()"><option value="">Todos os grupos</option></select>
    <select id="rf-analista" onchange="renLoad()"><option value="">Todos os analistas</option></select>
    <select id="rf-status" onchange="renLoad()">
      <option value="">Todos os status</option>
      <option>A iniciar</option><option>Em tratativa</option>
      <option>Aguardando cliente</option><option>Aguardando seguradora</option>
      <option>Proposta enviada</option><option>Aceita</option>
      <option>Recusada</option><option>Migrada</option>
      <option>Cancelada</option><option>Finalizada</option>
    </select>
    <select id="rf-tipo" onchange="renLoad()">
      <option value="">Todos os tipos</option>
      <option>Renovação</option><option>Contrato novo</option><option>Migração</option>
    </select>
    <select id="rf-aditivo" onchange="renLoad()">
      <option value="">Aditivo (todos)</option>
      <option>Sim</option><option>Em andamento</option><option>Não</option>
    </select>
    <select id="rf-periodo" onchange="renLoad()">
      <option value="">Período (todos)</option>
      <option value="mes_atual">Mês atual</option>
      <option value="proximos90">Próximos 90 dias</option>
      <option value="atrasado">Em atraso</option>
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
            <th>Contrato / CNPJ</th><th>Grupo / Empresa</th><th>Analista</th>
            <th>Início Vig.</th><th>Mês Ren.</th><th>Vidas</th><th>Porte</th>
            <th>%Com.</th><th>Ini. Trat.</th><th>Status</th><th>Sinistr.</th>
            <th>%Prop.</th><th>%Fim</th><th>%Aum.Real</th><th>Tipo</th><th>Aditivo</th>
            <th>Prêmio Ant.</th><th>Prêmio Reaj.</th><th>Receita +/mês</th>
            <th>Receita /ano</th><th>Comissão /ano</th>
            <th>Resp. Cliente</th><th>Ações</th>
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
    <div class="row2">
      <div class="fg"><label>Grupo *</label>
        <select id="mr-grupo"><option value="">Selecione...</option></select>
      </div>
      <div class="fg"><label>Empresa *</label>
        <select id="mr-empresa"><option value="">Selecione o grupo primeiro...</option></select>
      </div>
    </div>

    <div class="sec">Vigência e Porte</div>
    <div class="row4">
      <div class="fg"><label>Início de vigência *</label><input type="date" id="mr-ini-vig" oninput="renCalcRenovacao()"/></div>
      <div class="fg"><label>Data de renovação * <span style="color:var(--muted);font-weight:400;text-transform:none">(auto: vigência + 1 ano)</span></label><input type="date" id="mr-mes-ren"/></div>
      <div class="fg"><label>Número de vidas *</label><input type="number" id="mr-vidas" min="1" placeholder="Ex: 50"/></div>
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
          <option>A iniciar</option><option>Em tratativa</option>
          <option>Aguardando cliente</option><option>Aguardando seguradora</option>
          <option>Proposta enviada</option><option>Aceita</option>
          <option>Recusada</option><option>Migrada</option>
          <option>Cancelada</option><option>Finalizada</option>
        </select>
      </div>
      <div class="fg fg-warn"><label>Sinistralidade (%)</label><input type="text" id="mr-sinistr" placeholder="Ex: 68,50"/></div>
    </div>
    <div class="row4">
      <div class="fg fg-warn"><label>% Proposto reajuste</label><input type="text" id="mr-perc-prop" placeholder="Ex: 15,00"/></div>
      <div class="fg fg-warn"><label>% Finalizado</label><input type="text" id="mr-perc-fim" placeholder="Ex: 12,50"/></div>
      <div class="fg"><label>Tipo *</label>
        <select id="mr-tipo">
          <option>Renovação</option><option>Contrato novo</option><option>Migração</option>
        </select>
      </div>
      <div class="fg fg-warn"><label>Aditivo</label>
        <select id="mr-aditivo">
          <option>Não</option><option>Em andamento</option><option>Sim</option>
        </select>
      </div>
    </div>

    <div class="sec">Valores (campos verdes são calculados automaticamente)</div>
    <div class="row3">
      <div class="fg fg-warn">
        <label>Prêmio anterior (R$)</label>
        <input type="text" id="mr-prem-ant" placeholder="0,00" oninput="renCalc()"/>
      </div>
      <div class="fg fg-warn">
        <label>Prêmio reajuste (R$)</label>
        <input type="text" id="mr-prem-reaj" placeholder="0,00" oninput="renCalc()"/>
      </div>
      <div class="fg">
        <label>Receita a mais / mês (R$)</label>
        <input type="text" id="mr-receita" readonly style="background:var(--bg-hover);color:var(--accent);font-weight:700"/>
      </div>
    </div>
    <div class="row3">
      <div class="fg">
        <label>% Aumento Real <span style="color:var(--muted);font-weight:400;text-transform:none">(reajuste efetivo)</span></label>
        <input type="text" id="mr-aumento-real" readonly style="background:var(--bg-hover);color:var(--accent);font-weight:700"/>
      </div>
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
      <div class="fg"><label>Observações</label><input type="text" id="mr-obs" placeholder="Notas, pendências..."/></div>
    </div>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-ren')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
      <button class="btn btn-danger" id="mr-btn-del" onclick="renDelete()" style="display:none"><span class="material-symbols-rounded" style="font-size:17px">delete</span> Excluir</button>
      <button class="btn btn-primary" onclick="renSave()"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar Renovação</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
