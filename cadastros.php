<?php
$currentPage = 'cadastros';
$pageTitle   = 'Cadastros — Interweg Benefícios';
$pageScripts = ['assets/analistas.js', 'assets/cadastros.js'];

require_once __DIR__ . '/_header.php';
?>

<style>
  /* Modal Empresa — campos compactos */
  #modal-emp-ov .fg{margin-bottom:14px;gap:5px}
  #modal-emp-ov input[type=text],#modal-emp-ov input[type=email],#modal-emp-ov input[type=date],
  #modal-emp-ov select,#modal-emp-ov textarea{padding:8px 11px;font-size:13px;border-radius:7px}
  #modal-emp-ov label{font-size:11px}
  #modal-emp-ov .row2,#modal-emp-ov .row3{gap:24px;margin-bottom:6px}
  #modal-emp-ov input[type=date]{width:auto;max-width:180px}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Cadastros</div>
      <div class="page-sub">Gerencie seguradoras, empresas e analistas do sistema</div>
    </div>
  </div>

  <style>
    .cad-tabs{display:flex;gap:6px;border-bottom:1px solid var(--border);margin-bottom:22px;flex-wrap:wrap}
    .cad-tab-btn{display:flex;align-items:center;gap:8px;padding:11px 18px;border:none;background:none;cursor:pointer;font-size:14px;font-weight:700;color:var(--muted);border-bottom:3px solid transparent;margin-bottom:-1px;border-radius:8px 8px 0 0;transition:color .15s ease,background .15s ease}
    .cad-tab-btn:hover{color:var(--text);background:rgba(0,191,179,.05)}
    .cad-tab-btn.active{color:var(--accent);border-bottom-color:var(--accent)}
    .cad-tab-panel{display:none}
    .cad-tab-panel.active{display:block}
    .cad-form-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:20px;padding:16px 18px;background:var(--bg-card);border:1px solid var(--border);border-radius:14px}
    .cad-form-bar input,.cad-form-bar select{background:var(--bg);border:1px solid var(--border);border-radius:9px;padding:9px 13px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s ease}
    .cad-form-bar input:focus,.cad-form-bar select:focus{border-color:var(--accent)}
    .cad-tbl{width:100%;border-collapse:collapse;font-size:12.5px;background:var(--bg-card)}
    .cad-tbl th{background:var(--bg);color:var(--muted);padding:10px 12px;text-align:left;font-size:11px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;border-bottom:1.5px solid var(--border);position:sticky;top:0;z-index:1}
    .cad-tbl td{padding:9px 12px;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--text)}
    .cad-tbl tr:last-child td{border-bottom:none}
    .cad-tbl tr:hover td{background:rgba(0,191,179,.05)}
    .cad-tbl .acoes{white-space:nowrap}
    .cad-tbl .acoes button{padding:3px 6px !important;font-size:11px !important;min-width:0 !important;height:auto !important}
    .cad-tbl .ramo-tag{display:inline-block;padding:2px 9px;border-radius:999px;font-size:10px;font-weight:700;color:#fff}
    .cad-tbl .ramo-saude{background:#1d9e75}.cad-tbl .ramo-odonto{background:#7f77dd}
    .cad-tbl .ramo-vida{background:#378add}.cad-tbl .ramo-default{background:#888}
    .cad-tbl .tag-tipo{display:inline-block;padding:2px 9px;border-radius:999px;font-size:10px;font-weight:700;background:rgba(139,148,158,.15);color:var(--muted)}
    .cad-tbl-wrap{padding:20px 22px;background:var(--bg-card);border-radius:16px;border:1px solid var(--border);box-shadow:0 1px 2px rgba(16,24,40,.04),0 10px 30px -18px rgba(16,24,40,.12)}
    .cad-tbl-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px}
    .cad-tbl-busca{flex:1;min-width:280px;background:var(--bg);border:1px solid var(--border);border-radius:9px;padding:9px 13px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s ease}
    .cad-tbl-busca:focus{border-color:var(--accent)}
    .cad-tbl-scroll{max-height:600px;overflow:auto;border-radius:10px;border:1px solid var(--border)}
    .cad-tbl-count{font-size:12px;color:var(--muted);white-space:nowrap;background:rgba(0,191,179,.08);padding:4px 12px;border-radius:999px;font-weight:700}
  </style>

  <!-- ── ABAS ─────────────────────────────────────────────────── -->
  <div class="cad-tabs">
    <button class="cad-tab-btn active" data-tab="seg" onclick="cadTab('seg')"><span class="material-symbols-rounded" style="font-size:18px">shield</span> Seguradoras</button>
    <button class="cad-tab-btn" data-tab="emp" onclick="cadTab('emp')"><span class="material-symbols-rounded" style="font-size:18px">apartment</span> Empresas / Contratos</button>
    <?php if ($canViewAll || $isEnfermeira): ?>
    <button class="cad-tab-btn" data-tab="eq" onclick="cadTab('eq')"><span class="material-symbols-rounded" style="font-size:18px">group</span> Equipe</button>
    <?php endif; ?>
  </div>

  <!-- ── ABA: SEGURADORAS ── -->
  <div id="cad-panel-seg" class="cad-tab-panel active">
    <?php if (!$isVisualizador): ?>
    <div class="cad-form-bar">
      <input type="text" id="cad-seg-nome" placeholder="Nome da nova seguradora" style="flex:1;min-width:240px"/>
      <button class="btn btn-primary" onclick="cadAdd('seguradora')"><span class="material-symbols-rounded" style="font-size:16px">add</span> Adicionar seguradora</button>
    </div>
    <?php endif; ?>
    <div class="cad-tbl-wrap">
      <div class="cad-tbl-header">
        <h3 style="margin:0;color:var(--accent);display:flex;align-items:center;gap:8px"><span class="material-symbols-rounded">shield</span> Seguradoras</h3>
        <input type="text" id="cad-seg-tbl-filter" placeholder="Buscar por nome da seguradora..." oninput="filtrarSeguradorasTbl()" class="cad-tbl-busca"/>
        <span class="cad-tbl-count" id="cad-seg-tbl-count">—</span>
      </div>
      <div class="cad-tbl-scroll" style="max-height:520px">
        <table class="cad-tbl">
          <thead><tr>
            <th>#</th><th>Nome da Seguradora</th>
            <?php if (!$isVisualizador): ?><th class="acoes">Ações</th><?php endif; ?>
          </tr></thead>
          <tbody id="cad-seg-tbl-body"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── ABA: EMPRESAS / CONTRATOS ── -->
  <div id="cad-panel-emp" class="cad-tab-panel">
    <div class="cad-form-bar" style="justify-content:space-between">
      <div style="font-size:12px;color:var(--muted);flex:1;min-width:240px">Base mestre de clientes. Cada linha é uma empresa + contrato e alimenta Demandas, Faturamento e Renovação.</div>
      <?php if (!$isVisualizador): ?>
      <button class="btn btn-primary" onclick="openModalEmp()"><span class="material-symbols-rounded" style="font-size:16px">add</span> Nova empresa</button>
      <?php endif; ?>
    </div>
    <div class="cad-tbl-wrap">
      <div class="cad-tbl-header">
        <h3 style="margin:0;color:var(--info);display:flex;align-items:center;gap:8px"><span class="material-symbols-rounded">apartment</span> Empresas / Contratos</h3>
        <input type="text" id="cad-emp-tbl-filter" placeholder="Buscar por grupo, empresa, CNPJ, contrato, seguradora..." oninput="filtrarEmpresasTbl()" class="cad-tbl-busca"/>
        <span class="cad-tbl-count" id="cad-emp-tbl-count">—</span>
      </div>
      <div class="cad-tbl-scroll">
        <table class="cad-tbl">
          <thead><tr>
            <th>#</th><th>Grupo</th><th>Empresa</th><th>CNPJ</th><th>Contrato</th><th>Seguradora</th><th>Ramo</th><th>Analista</th>
            <?php if (!$isVisualizador): ?><th class="acoes">Ações</th><?php endif; ?>
          </tr></thead>
          <tbody id="cad-emp-tbl-body"></tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($canViewAll || $isEnfermeira): ?>
  <!-- ── ABA: EQUIPE ── -->
  <div id="cad-panel-eq" class="cad-tab-panel">
    <?php if (!$isVisualizador): ?>
    <div class="cad-form-bar">
      <input type="text" id="cad-anal-nome" placeholder="Nome completo" style="flex:2;min-width:180px"/>
      <input type="text" id="cad-anal-login" placeholder="Login / e-mail" style="flex:1;min-width:150px"/>
      <input type="password" id="cad-anal-senha" placeholder="Senha" style="min-width:130px"/>
      <select id="cad-anal-tipo" onchange="var g=document.getElementById('cad-anal-grupo'); if(g) g.style.display=(this.value==='cliente'?'':'none');">
        <option value="analista">Analista</option>
        <option value="enfermeira">Enfermeira</option>
        <option value="coordenador">Coordenador</option>
        <option value="head">Head</option>
        <option value="gerente">Gerente</option>
        <option value="admin">Admin</option>
        <option value="financeiro">Financeiro</option>
        <option value="novos_negocios">Novos Negócios</option>
        <option value="comercial_cotacao">Comercial (Cotação)</option>
        <option value="visualizador">Visualizador</option>
        <option value="renovacao">Renovação</option>
        <option value="cliente">Cliente (portal)</option>
      </select>
      <input type="text" id="cad-anal-grupo" placeholder="Grupo (ex.: MERCANTIL)" style="min-width:150px;display:none"/>
      <button class="btn btn-primary" onclick="cadAdd('analista')"><span class="material-symbols-rounded" style="font-size:16px">add</span> Adicionar</button>
    </div>
    <?php endif; ?>
    <div class="cad-tbl-wrap">
      <div class="cad-tbl-header">
        <h3 style="margin:0;color:var(--success);display:flex;align-items:center;gap:8px"><span class="material-symbols-rounded">group</span> Equipe</h3>
        <input type="text" id="cad-anal-tbl-filter" placeholder="Buscar por nome, login, tipo, papel..." oninput="filtrarAnalistasTbl()" class="cad-tbl-busca"/>
        <span class="cad-tbl-count" id="cad-anal-tbl-count">—</span>
      </div>
      <div class="cad-tbl-scroll">
        <table class="cad-tbl">
          <thead><tr>
            <th>#</th><th>Nome</th><th>Login</th><th>Tipo</th><th>Papel</th>
            <?php if (!$isVisualizador): ?><th class="acoes">Ações</th><?php endif; ?>
          </tr></thead>
          <tbody id="cad-anal-tbl-body"></tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script>
    function cadTab(t){
      document.querySelectorAll('.cad-tab-panel').forEach(p => p.classList.remove('active'));
      document.querySelectorAll('.cad-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === t));
      const p = document.getElementById('cad-panel-' + t);
      if (p) p.classList.add('active');
    }
  </script>

</main>

<!-- ── MODAL EMPRESA / CONTRATO (rico) ────────────────────────── -->
<div class="ov" id="modal-emp-ov" onclick="if(event.target===this)closeModal('modal-emp-ov')">
  <div class="modal" style="width:900px;max-width:95vw">
    <div class="modal-title" id="me-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--info)">apartment</span> Cadastro de Empresa / Contrato</div>
    <div class="modal-sub">Base mestre — cada linha representa uma empresa + contrato. Campos com (*) são obrigatórios.</div>
    <input type="hidden" id="ee-id"/>

    <div class="sec">Identificação do contrato</div>
    <div class="row3">
      <div class="fg"><label>Origem</label>
        <select id="ee-origem">
          <option value="">—</option>
          <option>Interweg MG</option><option>Interweg JF</option><option>Interweg SP</option>
          <option>INTERWEG</option><option>FINLÂNDIA</option><option>INTERWEG | FINLÂNDIA</option><option>VALENTE JUNIOR</option>
        </select>
      </div>
      <div class="fg"><label>Ramo</label>
        <select id="ee-ramo">
          <option value="">—</option>
          <option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option><option>Vida</option>
        </select>
      </div>
      <div class="fg"><label>Seguradora</label>
        <select id="ee-seguradora"><option value="">—</option></select>
      </div>
    </div>
    <div class="row3">
      <div class="fg"><label>Número do contrato *</label><input id="ee-contrato" type="text" placeholder="Ex: 12345-6"/></div>
      <div class="fg"><label>CNPJ *</label>
        <div style="display:flex;gap:6px">
          <input id="ee-cnpj" type="text" maxlength="18" placeholder="00.000.000/0000-00" style="flex:1"/>
          <button type="button" class="btn btn-outline btn-sm" onclick="eeConsultarCnpj(this)" title="Buscar dados do CNPJ na Receita" style="white-space:nowrap"><span class="material-symbols-rounded" style="font-size:16px">search</span></button>
        </div>
        <div id="ee-cnpj-info" style="font-size:11.5px;margin-top:3px;color:var(--muted)"></div>
      </div>
      <div class="fg"><label>Estipulante / Subestipulante *</label>
        <select id="ee-tipo">
          <option value="">—</option>
          <option value="Estipulante">Estipulante</option>
          <option value="Subestipulante">Subestipulante</option>
        </select>
      </div>
    </div>

    <div class="sec">Empresa</div>
    <div class="row3">
      <div class="fg"><label>Grupo *</label><input id="ee-grupo" type="text" placeholder="Nome do grupo econômico"/></div>
      <div class="fg"><label>Empresa *</label><input id="ee-nome" type="text" placeholder="Razão social"/></div>
      <div class="fg"><label>Porte da empresa *</label>
        <select id="ee-porte">
          <option value="">—</option>
          <option>Até 30 vidas</option>
          <option>Entre 30 e 100 vidas</option>
          <option>Entre 100 e 900</option>
          <option>Acima de 1000</option>
        </select>
      </div>
    </div>

    <div class="sec">Analista e datas</div>
    <div class="row3">
      <div class="fg"><label>Analista responsável</label>
        <select id="ee-analista-sel"><option value="">—</option></select>
      </div>
      <div class="fg"><label>Data de emissão</label><input id="ee-data-emi" type="date"/></div>
      <div class="fg"><label>Data de vencimento</label><input id="ee-data-venc" type="date"/></div>
    </div>
    <div class="row3">
      <div class="fg"><label>Início de vigência <span style="color:var(--muted);font-weight:400;text-transform:none">(usado pela Renovação)</span></label><input id="ee-ini-vig" type="date" oninput="cadCalcMesRen()"/></div>
      <div class="fg"><label>Data de renovação <span style="color:var(--muted);font-weight:400;text-transform:none">(auto: dia/mês da vigência no ano corrente)</span></label><input id="ee-mes-ren" type="date"/></div>
      <div class="fg"><label>Número de vidas <span style="color:var(--muted);font-weight:400;text-transform:none">(usado pela Renovação)</span></label><input id="ee-vidas" type="number" min="0" placeholder="Ex: 25"/></div>
    </div>
    <div class="row2">
      <div class="fg"><label>% de comissão *</label><input id="ee-perc-com" type="text" placeholder="Ex: 10,00"/></div>
      <div class="fg"><label>E-mails para envio de faturamento <span style="color:var(--muted);font-weight:400;text-transform:none">(separe por ; ou vírgula)</span></label><input id="ee-emails-fat" type="text" placeholder="financeiro@empresa.com.br; rh@empresa.com.br"/></div>
    </div>

    <div class="sec">Responsável pela empresa (renovação / visitas)</div>
    <div class="row3">
      <div class="fg"><label>Nome do responsável</label><input id="ee-resp-nome" type="text" placeholder="Nome completo"/></div>
      <div class="fg"><label>E-mail</label><input id="ee-resp-email" type="email" placeholder="email@empresa.com.br"/></div>
      <div class="fg"><label>Telefone</label><input id="ee-resp-tel" type="text" placeholder="(31) 99999-9999"/></div>
    </div>

    <div class="fg"><label>Observações</label><textarea id="ee-obs" rows="2" placeholder="Notas internas, contatos, particularidades..."></textarea></div>

    <div class="mfooter" style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
      <button type="button" class="btn btn-outline" id="ee-btn-pb" style="border-color:var(--accent);color:var(--accent);display:none" onclick="empAbrirPassagemBastao()" title="Abre o formulário completo da Passagem de Bastão dessa empresa (todos os campos: planos, valores, prazos, dependentes, documentos, contatos, etc.)">
        <span class="material-symbols-rounded" style="font-size:17px">swap_horiz</span>
        Visualizar / Editar Passagem de Bastão
      </button>
      <button type="button" class="btn btn-outline" id="ee-btn-fat" style="border-color:#3fb950;color:#3fb950;display:none" onclick="empEnviarFaturamento(this)" title="Cria a linha desta empresa no Faturamento da competência atual (se ainda não existir)">
        <span class="material-symbols-rounded" style="font-size:17px">receipt_long</span>
        Enviar para Faturamento
      </button>
      <div style="display:flex;gap:8px;margin-left:auto">
        <button class="btn btn-outline" onclick="closeModal('modal-emp-ov')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
        <button class="btn btn-primary" onclick="saveEmpresa()"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar</button>
      </div>
    </div>
  </div>
</div>

<!-- ── MODAL: NOVO/EDITAR ANALISTA (reusado de analistas.php) ──── -->
<div class="ov" id="mod-anal" onclick="if(event.target===this)closeModal('mod-anal')">
  <div class="modal" style="width:680px;max-width:95vw">
    <div class="modal-title" id="ma-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">person_add</span> Novo Integrante</div>
    <div class="modal-sub">Cadastro de integrante da equipe</div>
    <input type="hidden" id="ma-id"/>

    <div class="sec">Identificação</div>
    <div class="row2">
      <div class="fg"><label>Nome completo *</label><input type="text" id="ma-nome" placeholder="Ex: Maria Silva"/></div>
      <div class="fg"><label>Login (usuário do sistema)</label><input type="text" id="ma-login" placeholder="ex: maria.silva"/></div>
    </div>
    <div class="row2">
      <div class="fg"><label>E-mail</label><input type="email" id="ma-email" placeholder="maria@interweg.com.br"/></div>
      <div class="fg"><label>Tipo</label>
        <select id="ma-tipo" onchange="var r=document.getElementById('ma-grupo-row'); if(r) r.style.display=(this.value==='cliente'?'':'none');">
          <option value="analista">Analista</option>
          <option value="enfermeira">Enfermeira</option>
          <option value="coordenador">Coordenador</option>
          <option value="head">Head</option>
          <option value="gerente">Gerente</option>
          <option value="admin">Admin</option>
          <option value="financeiro">Financeiro</option>
          <option value="novos_negocios">Novos Negócios</option>
          <option value="comercial_cotacao">Comercial (Cotação)</option>
          <option value="visualizador">Visualizador</option>
          <option value="renovacao">Renovação</option>
          <option value="cliente">Cliente (portal)</option>
        </select>
      </div>
    </div>
    <div class="row2" id="ma-grupo-row" style="display:none">
      <div class="fg"><label>Grupo do cliente * (ex.: MERCANTIL)</label><input type="text" id="ma-grupo" placeholder="MERCANTIL"/></div>
      <div class="fg"><label>&nbsp;</label><div style="font-size:12px;color:var(--muted)">Define quais demandas o cliente enxerga no portal.</div></div>
    </div>

    <div class="sec">Função</div>
    <div class="fg"><label>Papel(éis) — separar por vírgula</label><input type="text" id="ma-papel" placeholder="Ex: Saúde, Odonto, Faturamento"/></div>
    <div class="fg"><label>Tarefas / Responsabilidades — uma por linha</label><textarea id="ma-tarefas" placeholder="Inclusões e exclusões&#10;Faturamento mensal&#10;Renovações UNIMED"></textarea></div>

    <div class="sec">Senha</div>
    <div class="row2">
      <div class="fg"><label>Senha (deixe em branco pra não alterar ao editar)</label><input type="password" id="ma-senha" placeholder="••••••••"/></div>
      <div class="fg"><label>Confirmar senha</label><input type="password" id="ma-senha2" placeholder="••••••••"/></div>
    </div>

    <div class="sec">Foto</div>
    <div class="row2">
      <div class="fg"><label>Upload de foto (PNG/JPG, máx 1MB)</label><input type="file" id="ma-foto" accept="image/*"/></div>
      <div class="fg"><label>Foto atual</label><div id="ma-foto-preview" style="font-size:12px;color:var(--muted)">Nenhuma foto carregada</div></div>
    </div>

    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-anal')"><?= $isVisualizador ? 'Fechar' : 'Cancelar' ?></button>
      <?php if (!$isVisualizador): ?>
      <button class="btn btn-danger" id="ma-btn-del" onclick="deleteAnalista()" style="display:none">Excluir</button>
      <button class="btn btn-primary" onclick="saveAnalista()">Salvar</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
