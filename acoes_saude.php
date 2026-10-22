<?php
$currentPage = 'acoes_saude';
$pageTitle   = 'Ações de Saúde — Interweg Benefícios';
$pageScripts = ['assets/margem_grupos.js', 'assets/acoes_saude.js'];

require_once __DIR__ . '/_header.php';
if (!$isAdmin && !$isEnfermeira && !$isRenovacao) { header('Location: demandas.php'); exit; }
?>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Ações de Saúde</div>
      <div class="page-sub">Planejamento e execução de ações de saúde nos clientes</div>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-outline" onclick="asOpenModalLote()"><span class="material-symbols-rounded" style="font-size:19px">library_add</span> Lote</button>
      <button class="btn btn-primary" onclick="asOpenModal()"><span class="material-symbols-rounded" style="font-size:19px">add</span> Nova Ação</button>
    </div>
  </div>

  <div class="cards">
    <div class="card c-teal" style="cursor:pointer" onclick="asFiltrarCard('',this)"><div class="card-label">Total</div><div class="card-val" id="as-n-total">—</div><div class="card-sub">ações · <strong id="as-v-total" style="color:var(--accent)">R$ 0</strong></div><div class="card-icon"><span class="material-symbols-rounded">favorite</span></div></div>
    <div class="card c-yellow" style="cursor:pointer" onclick="asFiltrarCard('Agendada',this)"><div class="card-label">Agendadas</div><div class="card-val" id="as-n-agen">—</div><div class="card-sub">confirmadas · <strong id="as-v-agen" style="color:#d29922">R$ 0</strong></div><div class="card-icon"><span class="material-symbols-rounded">event</span></div></div>
    <div class="card c-green" style="cursor:pointer" onclick="asFiltrarCard('Realizada',this)"><div class="card-label">Realizadas</div><div class="card-val" id="as-n-real">—</div><div class="card-sub">concluídas · <strong id="as-v-real" style="color:#3fb950">R$ 0</strong></div><div class="card-icon"><span class="material-symbols-rounded">task_alt</span></div></div>
    <div class="card c-blue" style="cursor:pointer" onclick="asFiltrarCard('Desmarcada',this)"><div class="card-label">Desmarcadas</div><div class="card-val" id="as-n-plan">—</div><div class="card-sub">não realizadas · <strong id="as-v-plan" style="color:#58a6ff">R$ 0</strong></div><div class="card-icon"><span class="material-symbols-rounded">event_busy</span></div></div>
  </div>

  <!-- Alternância de visão: Lista / Calendário -->
  <div style="display:flex;gap:4px;border-bottom:1px solid var(--border);margin:18px 0 0">
    <button id="as-tab-lista" style="padding:9px 16px;border:none;background:none;cursor:pointer;font-weight:700;font-size:13px;color:var(--accent);border-bottom:3px solid var(--accent);margin-bottom:-1px" onclick="asView('lista')"><span class="material-symbols-rounded" style="font-size:17px;vertical-align:middle">list</span> Lista</button>
    <button id="as-tab-cal" style="padding:9px 16px;border:none;background:none;cursor:pointer;font-weight:700;font-size:13px;color:var(--muted);border-bottom:3px solid transparent;margin-bottom:-1px" onclick="asView('cal')"><span class="material-symbols-rounded" style="font-size:17px;vertical-align:middle">calendar_month</span> Calendário</button>
  </div>

  <style>
    .ascal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--border);border:1px solid var(--border);border-radius:10px;overflow:hidden}
    .ascal-dow{background:linear-gradient(135deg,var(--accent),var(--accent2,var(--accent)));color:#fff;font-weight:700;font-size:11.5px;text-transform:uppercase;letter-spacing:.3px;padding:9px 6px;text-align:center}
    .ascal-day{background:var(--bg-card);min-height:108px;padding:5px 6px;position:relative}
    .ascal-day.out{background:var(--bg-hover,#f3f5f7);opacity:.55}
    .ascal-day.today{box-shadow:inset 0 0 0 2px var(--accent)}
    .ascal-num{font-size:12px;font-weight:700;color:var(--muted);text-align:right;margin-bottom:2px}
    .ascal-ev{display:block;font-size:11px;line-height:1.25;padding:3px 6px;border-radius:5px;margin-top:3px;cursor:pointer;white-space:normal;word-break:break-word;border-left:3px solid var(--accent);background:rgba(0,191,179,.1);color:var(--text)}
    .ascal-ev.st-realizado{border-left-color:#3fb950;background:rgba(63,185,80,.12)}
    .ascal-ev.st-agendado{border-left-color:#d29922;background:rgba(210,153,34,.12)}
    .ascal-ev.st-plan{border-left-color:#58a6ff;background:rgba(88,166,255,.12)}
    .ascal-ev.st-cancelado{border-left-color:#999;background:rgba(150,150,150,.12);text-decoration:line-through;opacity:.7}
  </style>

  <div id="as-view-lista">

  <style>
    /* Força filtros em uma linha só (sobrescreve width:100% do CSS global) */
    #as-filters{display:flex !important;flex-wrap:wrap;gap:8px;align-items:center;margin:20px 0}
    #as-filters > *{width:auto !important;flex:0 0 auto}
    #as-filters #as-search{flex:1 1 240px;min-width:240px}
    #as-filters #as-status,
    #as-filters #as-porte{min-width:170px}
    #as-filters #as-data_de{margin-left:auto;width:160px}
    #as-filters #as-data_ate{width:160px}
  </style>
  <div id="as-filters">
    <input type="text" id="as-search" placeholder="Buscar por empresa, tema, responsável..." oninput="asLoad()"/>
    <select id="as-status" onchange="asLoad()">
      <option value="">Todos os status</option>
      <option>Agendada</option>
      <option>Realizada</option>
      <option>Desmarcada</option>
    </select>
    <select id="as-porte" onchange="asLoad()">
      <option value="">Todos os portes</option>
      <option>Até 30 vidas</option>
      <option>Entre 30 e 100 vidas</option>
      <option>Entre 100 e 900</option>
      <option>Acima de 1000</option>
    </select>
    <input type="date" id="as-data_de"  onchange="asLoad()" title="Data de"  placeholder="Data de"/>
    <input type="date" id="as-data_ate" onchange="asLoad()" title="Data até" placeholder="Data até"/>
  </div>

  <div class="table-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--border)">
      <div style="font-size:13px;font-weight:700">Lista de Ações de Saúde (<span id="as-count">0</span>)</div>
      <button class="btn btn-outline btn-sm" onclick="asExport()"><span class="material-symbols-rounded" style="font-size:16px">download</span> Exportar Excel</button>
    </div>
    <div style="overflow-x:auto;width:100%">
    <table class="iw-table" style="min-width:1800px">
      <thead>
        <tr>
          <th>#</th>
          <th>GRUPO</th>
          <th>EMPRESA</th>
          <th>PORTE</th>
          <th>TIPO DE SERVIÇO</th>
          <th>TEMA</th>
          <th>DATA</th>
          <th>HORÁRIO</th>
          <th>RESPONSÁVEL</th>
          <th>LOCAL</th>
          <th>LINK/ENDEREÇO</th>
          <th>STATUS</th>
          <th style="text-align:right">CUSTO PREVISTO</th>
          <th style="text-align:right">CUSTO REALIZADO</th>
          <th style="text-align:right">TRANSPORTE</th>
          <th style="text-align:right">CUSTO TOTAL</th>
          <th style="text-align:right">MARGEM P/ AÇÃO</th>
          <th style="text-align:right">MARGEM RESTANTE</th>
          <th>AÇÕES</th>
        </tr>
      </thead>
      <tbody id="as-tbody">
        <tr><td colspan="15" style="text-align:center;padding:30px;color:var(--muted)">Carregando...</td></tr>
      </tbody>
    </table>
    </div><!-- /overflow-x -->
  </div>
  </div><!-- /as-view-lista -->

  <!-- VISÃO CALENDÁRIO -->
  <div id="as-view-cal" style="display:none">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:18px 0 12px">
      <div style="display:flex;align-items:center;gap:8px">
        <button class="btn btn-outline btn-sm" onclick="asCalNav(-1)" title="Mês anterior"><span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle">chevron_left</span></button>
        <div id="as-cal-titulo" style="font-weight:800;font-size:16px;min-width:170px;text-align:center"></div>
        <button class="btn btn-outline btn-sm" onclick="asCalNav(1)" title="Próximo mês"><span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle">chevron_right</span></button>
        <button class="btn btn-outline btn-sm" onclick="asCalHoje()">Hoje</button>
      </div>
      <input type="text" id="as-cal-busca" placeholder="Filtrar por empresa, serviço, responsável..." oninput="asCalRender()" style="padding:8px 11px;border:1px solid var(--border);border-radius:8px;font-size:13px;min-width:240px">
    </div>
    <div id="as-cal-grid"></div>
    <div id="as-cal-legenda" style="display:flex;flex-wrap:wrap;gap:14px 18px;align-items:center;margin-top:14px;padding:10px 14px;background:var(--bg-card);border:1px solid var(--border);border-radius:10px;font-size:12px">
      <div style="display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center">
        <span style="font-weight:700;color:var(--muted)">Categorias:</span>
        <span style="display:inline-flex;align-items:center;gap:5px"><i style="width:12px;height:12px;border-radius:3px;background:#00a890"></i>Pit Stop (+ Bio)</span>
        <span style="display:inline-flex;align-items:center;gap:5px"><i style="width:12px;height:12px;border-radius:3px;background:#3b82f6"></i>Palestra / Treinamento</span>
        <span style="display:inline-flex;align-items:center;gap:5px"><i style="width:12px;height:12px;border-radius:3px;background:#8b5cf6"></i>Bem-estar</span>
        <span style="display:inline-flex;align-items:center;gap:5px"><i style="width:12px;height:12px;border-radius:3px;background:#f59e0b"></i>InterVision</span>
        <span style="display:inline-flex;align-items:center;gap:5px"><i style="width:12px;height:12px;border-radius:3px;background:#ec4899"></i>Campanha / Ação</span>
        <span style="display:inline-flex;align-items:center;gap:5px"><i style="width:12px;height:12px;border-radius:3px;background:#06b6d4"></i>Relacionamento / Comitê</span>
        <span style="display:inline-flex;align-items:center;gap:5px"><i style="width:12px;height:12px;border-radius:3px;background:#6b7280"></i>Outros</span>
        <span style="display:inline-flex;align-items:center;gap:5px"><i style="width:12px;height:12px;border-radius:3px;background:#ef4444"></i>Feriado / Bloqueio</span>
      </div>
      <div style="display:flex;gap:12px;align-items:center;border-left:1px solid var(--border);padding-left:16px">
        <span style="font-weight:700;color:var(--muted)">Status:</span>
        <span style="display:inline-flex;align-items:center;gap:5px"><i style="width:14px;height:12px;border-radius:3px;background:#00a890;border-left:3px solid #00a890"></i>Realizada (cheia)</span>
        <span style="display:inline-flex;align-items:center;gap:5px"><i style="width:14px;height:12px;border-radius:3px;background:rgba(0,168,144,.14);border-left:3px solid #00a890"></i>Agendada (clara)</span>
        <span style="display:inline-flex;align-items:center;gap:5px"><i style="width:14px;height:12px;border-radius:3px;background:rgba(150,150,150,.12);border-left:3px solid #999"></i><span style="text-decoration:line-through;opacity:.7">Desmarcada</span></span>
      </div>
    </div>
  </div>
</main>

<!-- ────── MODAL ────── -->
<div class="ov" id="mod-as" onclick="if(event.target===this)closeModal('mod-as')">
  <div class="modal" style="width:820px;max-width:95vw">
    <div class="modal-title" id="mas-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">favorite</span> Nova Ação de Saúde</div>
    <input type="hidden" id="mas-id"/>

    <div class="sec">Empresa</div>
    <div class="row3">
      <div class="fg"><label>Grupo</label><select id="mas-grupo"><option value="">Selecione...</option></select></div>
      <div class="fg"><label>Empresa</label><select id="mas-empresa"><option value="">Selecione um grupo primeiro</option></select></div>
      <div class="fg"><label>Porte <span style="color:var(--muted);font-weight:400;font-size:11px">(automático pela empresa)</span></label>
        <select id="mas-porte">
          <option value="">—</option>
          <option>Até 30 vidas</option>
          <option>Entre 30 e 100 vidas</option>
          <option>Entre 100 e 900</option>
          <option>Acima de 1000</option>
        </select>
      </div>
    </div>
    <div class="row2">
      <div class="fg"><label>Margem para ação <span style="color:var(--muted);font-weight:400;text-transform:none">(disponível p/ o grupo)</span></label>
        <input id="mas-margem_acao" type="text" readonly style="background:var(--bg-hover);color:var(--muted)" placeholder="—"/>
      </div>
    </div>

    <div class="sec">Ação</div>
    <div class="row3">
      <div class="fg"><label>Tipo de serviço <span style="color:var(--muted);font-weight:400;font-size:11px">(categoria)</span></label>
        <input id="mas-tipo_servico" type="text" list="mas-tipo-dl" placeholder="Pit Stop, Palestra, Bem-estar..."/>
        <datalist id="mas-tipo-dl">
          <option>Pit Stop (+ Bio)</option>
          <option>Palestra / Treinamento</option>
          <option>Bem-estar</option>
          <option>InterVision</option>
          <option>Campanha / Ação</option>
          <option>Relacionamento / Comitê</option>
          <option>Outros / a confirmar</option>
          <option>Feriado / Bloqueio</option>
        </datalist>
      </div>
      <div class="fg" style="grid-column:span 2"><label>Tema</label><input id="mas-tema" type="text" placeholder="Ex: Outubro Rosa, Saúde Mental"/></div>
    </div>
    <div class="fg" id="svc-faltam-wrap" style="display:none;margin-top:2px">
      <label style="color:#c0392b">Faltam oferecer <span id="svc-faixa" style="color:var(--muted);font-weight:400;font-size:11px"></span></label>
      <div id="svc-faltam" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:8px"></div>
    </div>
    <div class="row3">
      <div class="fg"><label>Data <span style="color:#c0392b">*</span></label><input id="mas-data" type="date" required/></div>
      <div class="fg"><label>Horário</label><input id="mas-horario" type="time"/></div>
      <div class="fg"><label>Status</label>
        <select id="mas-status">
          <option>Agendada</option>
          <option>Realizada</option>
          <option>Desmarcada</option>
        </select>
      </div>
    </div>
    <div class="row3">
      <div class="fg"><label>Responsável</label><input id="mas-responsavel" type="text"/></div>
      <div class="fg"><label>Local</label><input id="mas-local" type="text" placeholder="Ex: Matriz, Venda Nova, Online"/></div>
      <div class="fg"><label>Link / Endereço</label><input id="mas-endereco" type="text" placeholder="Link da sala online ou endereço"/></div>
    </div>
    <div class="row2">
      <div class="fg" style="grid-column:span 2"><label>Motivo <span style="color:var(--muted);font-weight:400;font-size:11px">(ex.: justificativa se desmarcada)</span></label><input id="mas-motivo" type="text"/></div>
    </div>

    <div class="sec">Custos</div>
    <div class="row3">
      <div class="fg"><label>Custo previsto (R$)</label><input id="mas-custo_previsto" type="number" step="0.01" min="0" placeholder="0,00"/></div>
      <div class="fg"><label>Custo realizado (R$)</label><input id="mas-custo_realizado" type="number" step="0.01" min="0" placeholder="0,00"/></div>
      <div class="fg"><label>Transporte (R$)</label><input id="mas-transporte" type="number" step="0.01" min="0" placeholder="0,00"/></div>
    </div>
    <div class="row2">
      <div class="fg"><label>Custo total <span style="color:var(--muted);font-weight:400;text-transform:none">(automático)</span></label><input id="mas-custo_total_calc" type="text" readonly style="background:var(--bg-hover);color:var(--muted)"/></div>
    </div>
    <div id="mas-margem-aviso" style="display:none;margin-top:8px;padding:9px 12px;border-radius:6px;font-size:12.5px;font-weight:600"></div>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-as')">Cancelar</button>
      <button class="btn btn-danger" id="mas-btn-del" onclick="asDelete()" style="display:none">Excluir</button>
      <button class="btn btn-primary" onclick="asSave()">Salvar</button>
    </div>
  </div>
</div>

<!-- ── MODAL: CADASTRO EM LOTE ──────────────────────────────────── -->
<div class="ov" id="mod-as-lote" onclick="if(event.target===this)closeModal('mod-as-lote')">
  <div class="modal" style="width:1700px;max-width:97vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">library_add</span> Cadastro em Lote — Ações de Saúde</div>
    <div class="modal-sub">Preencha cada linha (até 50). Você pode importar uma planilha ou usar o modelo para acelerar.</div>

    <div class="sec" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <span>Ações — <span id="asl-count">0</span> linha(s) preenchida(s)</span>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-outline btn-sm" type="button" onclick="asLoteDownloadTemplate()" title="Baixar modelo .xlsx"><span class="material-symbols-rounded" style="font-size:15px">description</span> Modelo</button>
        <button class="btn btn-outline btn-sm" type="button" onclick="document.getElementById('asl-file').click()"><span class="material-symbols-rounded" style="font-size:15px">upload_file</span> Importar Planilha</button>
        <input type="file" id="asl-file" accept=".xlsx,.xls,.csv" style="display:none" onchange="asLoteImportFile(this)"/>
        <button class="btn btn-outline btn-sm" type="button" onclick="asLoteAddRow()"><span class="material-symbols-rounded" style="font-size:15px">add</span> Linha</button>
        <button class="btn btn-outline btn-sm" type="button" onclick="asLoteClear()"><span class="material-symbols-rounded" style="font-size:15px">delete_sweep</span> Limpar</button>
      </div>
    </div>

    <div style="max-height:46vh;overflow:auto;border:1px solid var(--border);border-radius:8px">
      <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead style="position:sticky;top:0;background:var(--bg-card);z-index:1">
          <tr style="text-align:left;white-space:nowrap">
            <th style="padding:7px;width:26px;border-bottom:1px solid var(--border)">#</th>
            <th style="padding:7px;min-width:160px;border-bottom:1px solid var(--border)">Grupo</th>
            <th style="padding:7px;min-width:200px;border-bottom:1px solid var(--border)">Empresa</th>
            <th style="padding:7px;min-width:170px;border-bottom:1px solid var(--border)">Porte</th>
            <th style="padding:7px;min-width:160px;border-bottom:1px solid var(--border)">Tipo de Serviço *</th>
            <th style="padding:7px;min-width:200px;border-bottom:1px solid var(--border)">Tema</th>
            <th style="padding:7px;min-width:130px;border-bottom:1px solid var(--border)">Data</th>
            <th style="padding:7px;min-width:90px;border-bottom:1px solid var(--border)">Horário</th>
            <th style="padding:7px;min-width:150px;border-bottom:1px solid var(--border)">Responsável</th>
            <th style="padding:7px;min-width:200px;border-bottom:1px solid var(--border)">Endereço</th>
            <th style="padding:7px;min-width:150px;border-bottom:1px solid var(--border)">Status</th>
            <th style="padding:7px;min-width:120px;border-bottom:1px solid var(--border)">Custo previsto</th>
            <th style="padding:7px;min-width:120px;border-bottom:1px solid var(--border)">Transporte</th>
            <th style="padding:7px;width:36px;border-bottom:1px solid var(--border)"></th>
          </tr>
        </thead>
        <tbody id="asl-tbody"></tbody>
      </table>
    </div>

    <datalist id="asl-grupos-dl"></datalist>
    <datalist id="asl-empresas-dl"></datalist>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-as-lote')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
      <button class="btn btn-primary" onclick="asSaveLote(this)"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar Lote</button>
    </div>
  </div>
</div>

<script>
// Clique nos cards filtra a tabela pelo status (definido aqui no PHP para não
// depender de re-subir o JS). Usa o asLoad() que já existe na página.
window.asFiltrarCard = function(status, el){
  document.querySelectorAll('.cards .card').forEach(function(c){ c.style.outline=''; c.style.outlineOffset=''; });
  if (el){ el.style.outline = '2px solid var(--accent)'; el.style.outlineOffset = '-1px'; }
  var sel = document.getElementById('as-status');
  if (sel) sel.value = status;
  if (typeof asLoad === 'function') asLoad();
};

// Valores por status (Realizado / Agendado / Em planejamento).
// Refletem os filtros de EMPRESA (busca), PORTE e DATA — mas ignoram o filtro
// de status, pra sempre mostrar a quebra completa (quanto realizado x agendado).
// O "Custo Total" continua sendo o total geral (não mexe).
(function(){
  function _set(id, v){ var el = document.getElementById(id); if (el) el.textContent = v; }
  function _fmt(n){
    n = parseFloat(n) || 0;
    return (typeof fmtBRL === 'function')
      ? fmtBRL(n)
      : 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits:2, maximumFractionDigits:2 });
  }
  function recalcular(){
    // Monta os filtros atuais, EXCETO status (pra ver realizado e agendado juntos)
    var qs = [];
    var s  = document.getElementById('as-search');   if (s  && s.value.trim()) qs.push('search='   + encodeURIComponent(s.value.trim()));
    var p  = document.getElementById('as-porte');     if (p  && p.value)        qs.push('porte='    + encodeURIComponent(p.value));
    var de = document.getElementById('as-data_de');   if (de && de.value)       qs.push('data_de='  + encodeURIComponent(de.value));
    var at = document.getElementById('as-data_ate');  if (at && at.value)       qs.push('data_ate=' + encodeURIComponent(at.value));
    var url = 'api_enfermagem.php?action=listar_acoes_saude' + (qs.length ? '&' + qs.join('&') : '');
    fetch(url)
      .then(function(r){ return r.json(); })
      .then(function(r){
        var rows = (r && r.data) ? r.data : [];
        var nTot = rows.length, vTot = 0;
        var n = { 'Agendada':0, 'Realizada':0, 'Desmarcada':0 };
        var v = { 'Agendada':0, 'Realizada':0, 'Desmarcada':0 };
        rows.forEach(function(d){
          var st = (d.status || '').trim();
          var c  = parseFloat(d.custo_total) || 0;
          vTot += c;
          if (n[st] !== undefined) { n[st] += 1; v[st] += c; }
        });
        // Total (itens + valor)
        _set('as-n-total', nTot);
        _set('as-v-total', _fmt(vTot));
        // Por status (itens + valor)
        _set('as-n-agen', n['Agendada']);    _set('as-v-agen', _fmt(v['Agendada']));
        _set('as-n-real', n['Realizada']);   _set('as-v-real', _fmt(v['Realizada']));
        _set('as-n-plan', n['Desmarcada']);  _set('as-v-plan', _fmt(v['Desmarcada']));
      })
      .catch(function(){});
  }
  function ligar(){
    // Recalcula quando empresa/porte/data mudam (status NÃO entra)
    ['as-search','as-porte','as-data_de','as-data_ate'].forEach(function(id){
      var el = document.getElementById(id);
      if (el){ el.addEventListener('input', recalcular); el.addEventListener('change', recalcular); }
    });
    recalcular();
  }
  if (document.readyState !== 'loading') ligar();
  else document.addEventListener('DOMContentLoaded', ligar);
})();
</script>


<script>
(function(){
  const esc = s => { const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; };
  const norm = s => String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/\s+/g,' ').trim();
  // Matriz de serviços por faixa: f = [30-99, a partir de 100, a partir de 300]
  // Apenas serviços ligados a SAÚDE (os comerciais/operacionais — Movimentação,
  // Faturamento, Apoio operacional, Estudo de mercado/Renovação, Conexa, Flui, Mar,
  // WeeBI — foram removidos deste atalho).
  const SERVICOS = [
    {n:'Apoio assistencial', f:[1,1,1]},
    {n:'Análise de sinistralidade', f:[0,1,1]},
    {n:'Comitê de Saúde', f:[0,0,1]},
    {n:'Inter Baby', f:[1,1,1]},
    {n:'Inter Cuidado', f:[1,1,1]},
    {n:'Informativo de saúde', f:[1,1,1]},
    {n:'Pit Stop + Bioimpedância', f:[0,1,1], obs:['','1x ao ano','A cada 6 meses']},
    {n:'Quick Massage', f:[0,1,1], obs:['','Avaliação sob demanda','Avaliação sob demanda']},
    {n:'Palestras', f:[1,1,1], obs:['Online','Online / Presencial','Online / Presencial']},
    {n:'Atendimento psicológico emergencial (pontual)', f:[1,1,1]},
    {n:'Apoio psicológico (5 sessões)', f:[0,1,1]},
  ];
  const FAIXA_LBL = ['30 a 99 vidas','a partir de 100 vidas','a partir de 300 vidas'];
  function faixaDoPorte(p){ p=(p||'').toLowerCase();
    if(p.includes('acima')||p.includes('1000')) return 2;          // Acima de 1000 → a partir de 300
    if(p.includes('100 e 900')||p.includes('100')) return 1;       // Entre 100 e 900 → a partir de 100
    return 0;                                                       // Até 30 / Entre 30 e 100 → 30-99
  }
  let _acoes=null;
  async function carregarAcoes(){ if(_acoes)return _acoes; try{ const r=await fetch('api_enfermagem.php?action=listar_acoes_saude').then(x=>x.json()); _acoes=(r&&r.data)||[]; }catch(e){ _acoes=[]; } return _acoes; }

  // ── Cores por categoria (modelo da planilha) ──
  const AS_CATS=[
    {k:'pit stop',    c:'#00a890', lbl:'Pit Stop (+ Bio)'},
    {k:'palestra',    c:'#3b82f6', lbl:'Palestra / Treinamento'},
    {k:'treinamento', c:'#3b82f6', lbl:''},
    {k:'bem-estar',   c:'#8b5cf6', lbl:'Bem-estar'},
    {k:'bem estar',   c:'#8b5cf6', lbl:''},
    {k:'intervision', c:'#f59e0b', lbl:'InterVision'},
    {k:'campanha',    c:'#ec4899', lbl:'Campanha / Ação'},
    {k:'relacionamento',c:'#06b6d4', lbl:'Relacionamento / Comitê'},
    {k:'comitê',      c:'#06b6d4', lbl:''},
    {k:'comite',      c:'#06b6d4', lbl:''},
    {k:'feriado',     c:'#ef4444', lbl:'Feriado / Bloqueio'},
    {k:'bloqueio',    c:'#ef4444', lbl:''},
  ];
  function asCatColor(tipo){ const t=norm(tipo||'');
    for(const o of AS_CATS){ if(t.indexOf(o.k)>=0) return o.c; }
    return '#6b7280'; // Outros / a confirmar
  }
  function asTint(hex){ // fundo claro a partir da cor
    const n=hex.replace('#',''); const r=parseInt(n.slice(0,2),16),g=parseInt(n.slice(2,4),16),b=parseInt(n.slice(4,6),16);
    return 'rgba('+r+','+g+','+b+',.14)';
  }

  // ── Visão Calendário (igual ao calendário de ações, por mês) ──
  let _asCalY=null, _asCalM=null;
  window.asView=function(v){
    const lista=document.getElementById('as-view-lista'), cal=document.getElementById('as-view-cal');
    const tL=document.getElementById('as-tab-lista'), tC=document.getElementById('as-tab-cal');
    const on=b=>{ if(b){ b.style.color='var(--accent)'; b.style.borderBottomColor='var(--accent)'; } };
    const off=b=>{ if(b){ b.style.color='var(--muted)'; b.style.borderBottomColor='transparent'; } };
    if(v==='cal'){ if(lista)lista.style.display='none'; if(cal)cal.style.display=''; on(tC); off(tL);
      if(_asCalY==null){ const d=new Date(); _asCalY=d.getFullYear(); _asCalM=d.getMonth(); }
      asCalRender();
    } else { if(cal)cal.style.display='none'; if(lista)lista.style.display=''; on(tL); off(tC); }
  };
  window.asCalNav=function(delta){ _asCalM+=delta; if(_asCalM<0){ _asCalM=11; _asCalY--; } if(_asCalM>11){ _asCalM=0; _asCalY++; } asCalRender(); };
  window.asCalGoTo=function(y,m){ _asCalY=y; _asCalM=m; asCalRender(); };
  window.asCalHoje=function(){ const d=new Date(); _asCalY=d.getFullYear(); _asCalM=d.getMonth(); asCalRender(); };
  window.asCalAbrir=function(id){ if(id && typeof window.asEdit==='function') window.asEdit(id); };
  window.asCalRender=async function(){
    const grid=document.getElementById('as-cal-grid'); if(!grid) return;
    // Busca sempre atualizado (sem cache) pra refletir ações recém-criadas/editadas
    let acoes=[];
    try{ const r=await fetch('api_enfermagem.php?action=listar_acoes_saude').then(x=>x.json()); acoes=(r&&r.data)||[]; _acoes=acoes; }catch(e){ acoes=_acoes||[]; }
    const q=((document.getElementById('as-cal-busca')||{}).value||'').toLowerCase().trim();
    const MES=['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    const tt=document.getElementById('as-cal-titulo'); if(tt) tt.textContent=MES[_asCalM]+' '+_asCalY;
    const porDia={};
    acoes.forEach(a=>{ const dt=String(a.data||'').slice(0,10); if(!/^\d{4}-\d{2}-\d{2}$/.test(dt)) return;
      if(q){ const hay=((a.empresa||'')+' '+(a.tipo_servico||'')+' '+(a.tema||'')+' '+(a.responsavel||'')).toLowerCase(); if(!hay.includes(q)) return; }
      (porDia[dt]=porDia[dt]||[]).push(a); });
    const first=new Date(_asCalY,_asCalM,1);
    const dow=(first.getDay()+6)%7; // segunda=0
    const start=new Date(first); start.setDate(first.getDate()-dow);
    const now=new Date(); const hojeStr=now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0');
    const DOW=['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'];
    let h='<div class="ascal-grid">';
    DOW.forEach(d=>h+='<div class="ascal-dow">'+d+'</div>');
    for(let w=0;w<6;w++){ for(let d=0;d<7;d++){
      const cur=new Date(start); cur.setDate(start.getDate()+w*7+d);
      const ds=cur.getFullYear()+'-'+String(cur.getMonth()+1).padStart(2,'0')+'-'+String(cur.getDate()).padStart(2,'0');
      const fora=(cur.getMonth()!==_asCalM);
      const evs=porDia[ds]||[];
      h+='<div class="ascal-day'+(fora?' out':'')+(ds===hojeStr?' today':'')+'"><div class="ascal-num">'+cur.getDate()+'</div>';
      evs.forEach(a=>{ const st=norm(a.status||'');
        const col=asCatColor(a.tipo_servico||'');
        let style='';
        if(st.indexOf('desmarc')>=0||st.indexOf('cancel')>=0){
          // Desmarcada → cinza riscado
          style='border-left-color:#999;background:rgba(150,150,150,.12);color:#888;text-decoration:line-through;opacity:.75';
        } else if(st.indexOf('realiz')>=0){
          // Realizada → cor cheia
          style='border-left-color:'+col+';background:'+col+';color:#fff';
        } else {
          // Agendada (e demais) → cor clara
          style='border-left-color:'+col+';background:'+asTint(col)+';color:var(--text)';
        }
        const hora=a.horario?(String(a.horario).slice(0,5)+' '):'';
        const label=esc(hora)+esc(a.tipo_servico||a.tema||'Ação')+(a.empresa?(' · '+esc(a.empresa)):'');
        const tip=esc((a.empresa||'')+' - '+(a.tipo_servico||'')+(a.tema?(' - '+a.tema):'')+(a.responsavel?(' ('+a.responsavel+')'):'')+(a.status?(' ['+a.status+']'):''));
        h+='<span class="ascal-ev" style="'+style+'" title="'+tip+'" onclick="asCalAbrir('+(a.id||0)+')">'+label+'</span>';
      });
      h+='</div>';
    }}
    h+='</div>';
    grid.innerHTML=h;
  };

  window.svcPreenche = function(nome){ const el=document.getElementById('mas-tipo_servico'); if(el){ el.value=nome; el.dispatchEvent(new Event('input')); } };
  async function svcFaltam(){
    const wrap=document.getElementById('svc-faltam-wrap'), box=document.getElementById('svc-faltam');
    if(!wrap||!box) return;
    const nome=(document.getElementById('mas-empresa')||{}).value||'';
    const grupo=(document.getElementById('mas-grupo')||{}).value||'';
    const porte=(document.getElementById('mas-porte')||{}).value||'';
    if(!nome){ wrap.style.display='none'; return; }
    const fi=faixaDoPorte(porte);
    document.getElementById('svc-faixa').textContent='· '+FAIXA_LBL[fi];
    const aplic=SERVICOS.filter(s=>s.f[fi]);
    const acoes=await carregarAcoes();
    // "Já oferecido" de forma GLOBAL: considera qualquer ação do mesmo GRUPO
    // (qualquer empresa do grupo). Sem grupo, cai para a empresa.
    const gN=norm(grupo), nN=norm(nome);
    const done=acoes.filter(a=> gN ? norm(a.grupo)===gN : norm(a.empresa)===nN);
    const falta=aplic.filter(s=>{ const alvo=norm(s.n); return !done.some(a=>{ const t=norm(a.tipo_servico); return t && (t===alvo||t.includes(alvo)||alvo.includes(t)); }); });
    if(!falta.length){ box.innerHTML='<span style="color:var(--success);font-size:12px">✅ Todos os serviços da faixa já foram oferecidos.</span>'; wrap.style.display=''; return; }
    box.innerHTML=falta.map(s=>{ const ob=s.obs?s.obs[fi]:''; const nn=String(s.n).replace(/\\/g,'\\\\').replace(/'/g,"\\'");
      return '<button type="button" class="btn btn-outline btn-sm" style="border-color:#f0b4b4;color:#c0392b;width:100%;text-align:left;white-space:normal" title="'+esc(ob||'')+'" onclick="svcPreenche(\''+nn+'\')">+ '+esc(s.n)+(ob?' <span style="opacity:.7">('+esc(ob)+')</span>':'')+'</button>'; }).join('');
    wrap.style.display='';
  }
  const _e=document.getElementById('mas-empresa'); if(_e) _e.addEventListener('change', ()=>setTimeout(svcFaltam,60));
  const _g=document.getElementById('mas-grupo');   if(_g) _g.addEventListener('change', ()=>setTimeout(svcFaltam,120));
  const _p=document.getElementById('mas-porte');   if(_p) _p.addEventListener('change', svcFaltam);
  // Recalcula quando o modal abre (inclui o fluxo de Editar, que preenche sem disparar change)
  const _modal=document.getElementById('mod-as');
  if(_modal && window.MutationObserver){
    new MutationObserver(()=>{ const vis=_modal.classList.contains('show')||_modal.style.display==='flex'; if(vis) setTimeout(svcFaltam,150); })
      .observe(_modal,{attributes:true,attributeFilter:['class','style']});
  }
})();
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
