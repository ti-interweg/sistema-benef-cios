<?php
$currentPage = 'demandas_assistenciais';
$pageTitle   = 'Demandas Assistenciais — Interweg Benefícios';
$pageScripts = ['assets/demandas_assistenciais.js'];

require_once __DIR__ . '/_header.php';
// Admin e Enfermeiras têm acesso à área de Enfermagem
if (!$isAdmin && !$isEnfermeira) { header('Location: demandas.php'); exit; }
?>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Demandas Assistenciais</div>
      <div class="page-sub">Demandas de enfermagem (autorização, busca de rede, reembolso, agendamentos)</div>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-outline" onclick="daOpenModalLote()"><span class="material-symbols-rounded" style="font-size:19px">library_add</span> Lote</button>
      <button class="btn btn-primary" onclick="daOpenModal()"><span class="material-symbols-rounded" style="font-size:19px">add</span> Nova Demanda</button>
    </div>
  </div>

  <div class="cards">
    <div class="card c-teal" style="cursor:pointer" onclick="daFiltrarCard('total',this)"><div class="card-label">Total</div><div class="card-val" id="da-tot">—</div><div class="card-sub">demandas</div><div class="card-icon"><span class="material-symbols-rounded">medical_services</span></div></div>
    <div class="card c-blue" style="cursor:pointer" onclick="daFiltrarCard('iniciar',this)"><div class="card-label">A iniciar</div><div class="card-val" id="da-iniciar">—</div><div class="card-sub">aguardando</div><div class="card-icon"><span class="material-symbols-rounded">schedule</span></div></div>
    <div class="card c-yellow" style="cursor:pointer" onclick="daFiltrarCard('andamento',this)"><div class="card-label">Em andamento</div><div class="card-val" id="da-andamento">—</div><div class="card-sub">em curso</div><div class="card-icon"><span class="material-symbols-rounded">manage_search</span></div></div>
    <div class="card c-green" style="cursor:pointer" onclick="daFiltrarCard('concluido',this)"><div class="card-label">Concluídas</div><div class="card-val" id="da-concluido">—</div><div class="card-sub">finalizadas</div><div class="card-icon"><span class="material-symbols-rounded">task_alt</span></div></div>
    <div class="card c-red" style="cursor:pointer" onclick="daFiltrarCard('atrasadas',this)"><div class="card-label">Atrasadas</div><div class="card-val" id="da-atrasadas">—</div><div class="card-sub">próx. ação vencida</div><div class="card-icon"><span class="material-symbols-rounded">schedule</span></div></div>
  </div>

  <div class="filters" style="margin:20px 0">
    <input type="text" id="da-search" placeholder="Buscar por beneficiário, empresa, descrição..." oninput="daLoad()" style="flex:1;min-width:280px"/>
    <select id="da-situacao" onchange="daLoad()">
      <option value="">Todas as situações</option>
      <option>A iniciar</option>
      <option>Em andamento</option>
      <option>Concluído</option>
    </select>
    <select id="da-tipo" onchange="daLoad()">
      <option value="">Todos os tipos</option>
      <option>Autorização</option>
      <option>Busca de Rede</option>
      <option>Reembolso</option>
      <option>Agendamento Psicologia</option>
      <option>Agendamento</option>
      <option>Orientação</option>
      <option>Transferência Hospitalar</option>
      <option>Transcrição de Guias</option>
      <option>Check up</option>
      <option>Veracidade de atestado</option>
      <option>Cobrança indevida</option>
      <option>Outros</option>
    </select>
    <select id="da-ramo" onchange="daLoad()">
      <option value="">Todos os ramos</option>
      <option>Saúde</option>
      <option>Odonto</option>
      <option>Saúde/Odonto</option>
      <option>Vida</option>
      <option>Enfermagem</option>
    </select>
    <select id="da-enfermeira" onchange="daLoad()">
      <option value="">Todas as enfermeiras</option>
    </select>
  </div>

  <div class="table-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--border)">
      <div style="font-size:13px;font-weight:700">Lista de Demandas Assistenciais (<span id="da-count">0</span>)</div>
      <button class="btn btn-outline btn-sm" onclick="daExport()"><span class="material-symbols-rounded" style="font-size:16px">download</span> Exportar Excel</button>
    </div>
    <div style="overflow-x:auto;width:100%">
    <table class="iw-table" style="min-width:1400px">
      <thead>
        <tr>
          <th>#</th>
          <th>GRUPO / EMPRESA</th>
          <th>SEGURADORA</th>
          <th>RAMO</th>
          <th>TIPO</th>
          <th>SITUAÇÃO</th>
          <th>RECEBIMENTO</th>
          <th>PRÓX. AÇÃO</th>
          <th>CONCLUSÃO</th>
          <th>ENFERMEIRA</th>
          <th>BENEFICIÁRIO</th>
          <th>DESCRIÇÃO</th>
          <th>AÇÕES</th>
        </tr>
      </thead>
      <tbody id="da-tbody">
        <tr><td colspan="13" style="text-align:center;padding:30px;color:var(--muted)">Carregando...</td></tr>
      </tbody>
    </table>
    </div><!-- /overflow-x -->
  </div>
</main>

<!-- ────── MODAL ────── -->
<div class="ov" id="mod-da" onclick="if(event.target===this)closeModal('mod-da')">
  <div class="modal" style="width:760px;max-width:95vw">
    <div class="modal-title" id="mda-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">medical_services</span> Nova Demanda Assistencial</div>
    <input type="hidden" id="mda-id"/>

    <div class="sec">Identificação</div>
    <div class="row3">
      <div class="fg"><label>Grupo</label>
        <select id="mda-grupo" onchange="daUpdateEmpresas()">
          <option value="">Selecione...</option>
        </select>
      </div>
      <div class="fg"><label>Empresa</label>
        <select id="mda-empresa" onchange="daEmpresaChange()">
          <option value="">Selecione um grupo primeiro</option>
        </select>
      </div>
      <div class="fg"><label>Seguradora</label>
        <select id="mda-seguradora">
          <option value="">— Selecione —</option>
        </select>
      </div>
    </div>
    <div class="row3">
      <div class="fg"><label>Ramo</label>
        <select id="mda-ramo">
          <option value="">—</option>
          <option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option><option>Vida</option><option>Enfermagem</option>
        </select>
      </div>
      <div class="fg"><label>Tipo de demanda</label>
        <select id="mda-tipo">
          <option>Autorização</option>
          <option>Busca de Rede</option>
          <option>Reembolso</option>
          <option>Agendamento Psicologia</option>
          <option>Agendamento</option>
          <option>Orientação</option>
          <option>Transferência Hospitalar</option>
          <option>Transcrição de Guias</option>
          <option>Check up</option>
          <option>Veracidade de atestado</option>
          <option>Cobrança indevida</option>
          <option selected>Outros</option>
        </select>
      </div>
      <div class="fg"><label>Situação</label>
        <select id="mda-situacao">
          <option>A iniciar</option>
          <option>Em andamento</option>
          <option>Concluído</option>
        </select>
      </div>
    </div>

    <div class="sec">Datas</div>
    <div class="row3">
      <div class="fg"><label>Recebimento</label><input id="mda-recebimento" type="date"/></div>
      <div class="fg"><label>Próxima ação</label><input id="mda-proxima_acao" type="date"/></div>
      <div class="fg"><label>Data de conclusão</label><input id="mda-data_conclusao" type="date"/></div>
    </div>

    <div class="sec">Conteúdo</div>
    <div class="fg">
      <label>Dados do beneficiário</label>
      <textarea id="mda-dados" rows="3" placeholder="Nome, CPF, plano, carteirinha..."></textarea>
    </div>
    <div class="fg">
      <label>Descrição da demanda</label>
      <textarea id="mda-descricao" rows="4" placeholder="Detalhes do pedido, contexto, próximos passos..."></textarea>
    </div>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-da')">Cancelar</button>
      <button class="btn btn-danger" id="mda-btn-del" onclick="daDelete()" style="display:none">Excluir</button>
      <button class="btn btn-primary" onclick="daSave()">Salvar</button>
    </div>
  </div>
</div>

<!-- ── MODAL: CADASTRO EM LOTE ──────────────────────────────────── -->
<div class="ov" id="mod-da-lote" onclick="if(event.target===this)closeModal('mod-da-lote')">
  <div class="modal" style="width:1500px;max-width:97vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">library_add</span> Cadastro em Lote</div>
    <div class="modal-sub">Preencha cada linha (até 50). Você pode importar uma planilha ou usar o modelo para acelerar.</div>

    <div class="sec" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <span>Demandas — <span id="dal-count">0</span> linha(s) preenchida(s)</span>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-outline btn-sm" type="button" onclick="daLoteDownloadTemplate()" title="Baixar modelo .xlsx"><span class="material-symbols-rounded" style="font-size:15px">description</span> Modelo</button>
        <button class="btn btn-outline btn-sm" type="button" onclick="document.getElementById('dal-file').click()"><span class="material-symbols-rounded" style="font-size:15px">upload_file</span> Importar Planilha</button>
        <input type="file" id="dal-file" accept=".xlsx,.xls,.csv" style="display:none" onchange="daLoteImportFile(this)"/>
        <button class="btn btn-outline btn-sm" type="button" onclick="daLoteAddRow()"><span class="material-symbols-rounded" style="font-size:15px">add</span> Linha</button>
        <button class="btn btn-outline btn-sm" type="button" onclick="daLoteClear()"><span class="material-symbols-rounded" style="font-size:15px">delete_sweep</span> Limpar</button>
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
            <th style="padding:7px;min-width:160px;border-bottom:1px solid var(--border)">Tipo *</th>
            <th style="padding:7px;min-width:140px;border-bottom:1px solid var(--border)">Situação</th>
            <th style="padding:7px;min-width:130px;border-bottom:1px solid var(--border)">Recebimento</th>
            <th style="padding:7px;min-width:130px;border-bottom:1px solid var(--border)">Próx. Ação</th>
            <th style="padding:7px;min-width:130px;border-bottom:1px solid var(--border)">Conclusão</th>
            <th style="padding:7px;min-width:190px;border-bottom:1px solid var(--border)">Beneficiário</th>
            <th style="padding:7px;min-width:210px;border-bottom:1px solid var(--border)">Descrição</th>
            <th style="padding:7px;min-width:140px;border-bottom:1px solid var(--border)">Analista</th>
            <th style="padding:7px;width:36px;border-bottom:1px solid var(--border)"></th>
          </tr>
        </thead>
        <tbody id="dal-tbody"></tbody>
      </table>
    </div>

    <datalist id="dal-grupos-dl"></datalist>
    <datalist id="dal-empresas-dl"></datalist>
    <datalist id="dal-segs-dl"></datalist>
    <datalist id="dal-analistas-dl"></datalist>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-da-lote')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
      <button class="btn btn-primary" onclick="daSaveLote(this)"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar Lote</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
