<?php
$currentPage = 'formcot';
$pageTitle   = 'Formulário de Cotação — Interweg';
$pageScripts = ['assets/formcot.js'];

require_once __DIR__ . '/_header.php';
if (!$isAdmin && !$isNovosNeg && !$isVisualizador) { header('Location: index.php'); exit; }
?>

<style>
  .fc-fields{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
  .fc-field{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px 12px;display:flex;flex-direction:column;gap:6px;position:relative}
  .fc-field-head{display:flex;justify-content:space-between;align-items:center;gap:6px}
  .fc-field-label{font-size:11.5px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.3px;flex:1}
  .fc-field-label[contenteditable]{outline:none;border-bottom:1px dashed transparent}
  .fc-field-label[contenteditable]:hover,.fc-field-label[contenteditable]:focus{border-bottom-color:var(--muted)}
  .fc-field-del{background:transparent;border:none;color:var(--muted);cursor:pointer;font-family:inherit}
  .fc-field-del:hover{color:var(--danger)}
  .fc-field input,.fc-field textarea,.fc-field select{background:var(--bg-card);border:1px solid var(--border);border-radius:6px;padding:7px 10px;font-size:13px;color:var(--text);width:100%;font-family:inherit;outline:none}
  .fc-field input:focus,.fc-field textarea:focus,.fc-field select:focus{border-color:var(--accent)}
  #fc-vidas-tab th,#fc-vidas-tab td{border:1px solid var(--border);padding:6px 8px;text-align:left}
  #fc-vidas-tab th{background:rgba(0,201,167,.08);color:var(--accent);font-weight:700;font-size:11px;text-transform:uppercase;position:relative}
  #fc-vidas-tab td input{background:transparent;border:none;width:100%;color:var(--text);outline:none;font-size:12px;font-family:inherit}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Formulário de Cotação Interweg</div>
      <div class="page-sub">Preencha os campos. Você pode adicionar ou remover campos em cada seção.</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <select id="fc-tipo" onchange="fcSetTemplate()" style="background:var(--bg);border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;color:var(--text);outline:none;height:38px">
        <option value="saude_pme">Saúde — PME (2 a 29 vidas)</option>
        <option value="saude_emp">Saúde — Empresarial (30+ vidas)</option>
      </select>
      <button class="btn btn-outline" onclick="fcLimpar()" style="white-space:nowrap;height:38px"><span class="material-symbols-rounded" style="font-size:16px">delete_sweep</span> Limpar</button>
      <button class="btn btn-outline" onclick="fcAbrirPreviewCliente()" style="border-color:#00bfb3;color:#00bfb3;white-space:nowrap;height:38px"><span class="material-symbols-rounded" style="font-size:16px">visibility</span> Visualizar</button>
      <button class="btn btn-outline" onclick="fcGerarLinkCliente()" style="border-color:#00bfb3;color:#00bfb3;background:rgba(0,191,179,.08);white-space:nowrap;height:38px"><span class="material-symbols-rounded" style="font-size:16px">link</span> Gerar Link</button>
      <button class="btn btn-primary" onclick="fcExportXLSX()" style="white-space:nowrap;height:38px"><span class="material-symbols-rounded" style="font-size:16px">download</span> Exportar XLSX</button>
    </div>
  </div>

  <!-- Atalhos pra baixar o formulário oficial em branco (pra equipe comercial preencher à mão) -->
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 4px;padding:10px 14px;background:rgba(0,191,179,.06);border:1px dashed var(--border);border-radius:10px">
    <span style="font-size:12px;color:var(--muted);align-self:center;margin-right:6px">
      <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle">file_download</span>
      Baixar formulário em branco:
    </span>
    <a class="btn btn-outline btn-sm"
       href="formularios/SAÚDE - Formulário de Cotação PME (2 a 29 vidas).xlsx"
       download
       style="border-color:#00bfb3;color:#00bfb3;text-decoration:none;display:inline-flex;align-items:center;gap:4px">
      <span class="material-symbols-rounded" style="font-size:14px">description</span>
      Saúde — PME (2 a 29 vidas)
    </a>
    <a class="btn btn-outline btn-sm"
       href="formularios/SAÚDE - Formulário de Cotação (+30 vidas).xlsx"
       download
       style="border-color:#00bfb3;color:#00bfb3;text-decoration:none;display:inline-flex;align-items:center;gap:4px">
      <span class="material-symbols-rounded" style="font-size:14px">description</span>
      Saúde — Empresarial (+30 vidas)
    </a>
  </div>

  <div class="table-wrap" style="padding:24px">
    <div class="fc-section" data-section="dados">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:2px solid var(--accent);padding-bottom:8px">
        <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin:0"><span class="material-symbols-rounded">business</span> Dados da Empresa</h3>
        <button class="btn btn-outline btn-sm" onclick="fcAddCampo('dados')"><span class="material-symbols-rounded" style="font-size:14px">add</span> Campo</button>
      </div>
      <div class="fc-fields" id="fc-fields-dados"></div>
    </div>

    <div class="fc-section" data-section="plano_atual" style="margin-top:28px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:2px solid var(--accent);padding-bottom:8px">
        <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin:0"><span class="material-symbols-rounded">history</span> Plano Atual <span style="font-size:12px;color:var(--muted);font-weight:400">(o que o cliente tem hoje)</span></h3>
        <div style="display:flex;gap:6px;align-items:center">
          <button class="btn btn-outline btn-sm" onclick="fcAddCampo('plano_atual')"><span class="material-symbols-rounded" style="font-size:14px">add</span> Campo</button>
          <button class="btn btn-outline btn-sm" onclick="fcExcluirSecao('plano_atual')" title="Excluir bloco Plano Atual (cliente sem plano vigente)" style="color:var(--muted);padding:6px 8px;min-width:auto"><span class="material-symbols-rounded" style="font-size:16px">close</span></button>
        </div>
      </div>
      <div class="fc-fields" id="fc-fields-plano_atual"></div>
    </div>

    <!-- Placeholder mostrado quando o bloco "Plano Atual" é excluído -->
    <div class="fc-section-excluida" data-section-excluida="plano_atual" style="margin-top:28px;padding:14px 18px;border:1px dashed var(--border);border-radius:10px;background:rgba(0,191,179,.04);justify-content:space-between;align-items:center;gap:12px;display:none">
      <span style="color:var(--muted);font-size:13px;display:inline-flex;align-items:center;gap:6px">
        <span class="material-symbols-rounded" style="font-size:16px">history_toggle_off</span>
        Bloco "Plano Atual" excluído desta cotação.
      </span>
      <button class="btn btn-outline btn-sm" onclick="fcRestaurarSecao('plano_atual')" style="border-color:#00bfb3;color:#00bfb3">
        <span class="material-symbols-rounded" style="font-size:14px">undo</span> Restaurar
      </button>
    </div>

    <div class="fc-section" data-section="contrato" style="margin-top:28px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:2px solid var(--accent);padding-bottom:8px">
        <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin:0"><span class="material-symbols-rounded">description</span> Informações Contratuais <span style="font-size:12px;color:var(--muted);font-weight:400">(do plano cotado)</span></h3>
        <button class="btn btn-outline btn-sm" onclick="fcAddCampo('contrato')"><span class="material-symbols-rounded" style="font-size:14px">add</span> Campo</button>
      </div>
      <div class="fc-fields" id="fc-fields-contrato"></div>
    </div>

    <div class="fc-section" data-section="vidas" style="margin-top:28px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:2px solid var(--accent);padding-bottom:8px">
        <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin:0"><span class="material-symbols-rounded">groups</span> Base de Vidas</h3>
        <div style="display:flex;gap:6px">
          <button class="btn btn-outline btn-sm" onclick="fcAddColuna()"><span class="material-symbols-rounded" style="font-size:14px">add</span> Coluna</button>
          <button class="btn btn-outline btn-sm" onclick="fcAddVida()"><span class="material-symbols-rounded" style="font-size:14px">add</span> Vida</button>
        </div>
      </div>
      <div style="overflow-x:auto">
        <table id="fc-vidas-tab" style="width:100%;border-collapse:collapse;font-size:12px">
          <thead><tr id="fc-vidas-head"></tr></thead>
          <tbody id="fc-vidas-body"></tbody>
        </table>
      </div>
    </div>

    <div class="fc-section" data-section="coberturas" style="margin-top:28px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:2px solid var(--accent);padding-bottom:8px">
        <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin:0"><span class="material-symbols-rounded">checklist</span> Coberturas e Assistências</h3>
        <button class="btn btn-outline btn-sm" onclick="fcAddCampo('coberturas')"><span class="material-symbols-rounded" style="font-size:14px">add</span> Item</button>
      </div>
      <div class="fc-fields" id="fc-fields-coberturas"></div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/_footer.php'; ?>
