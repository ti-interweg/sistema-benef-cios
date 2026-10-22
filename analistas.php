<?php
$currentPage = 'analistas';
$pageTitle   = 'Analistas — Interweg Benefícios';
$pageScripts = ['assets/analistas.js'];

require_once __DIR__ . '/_header.php';
if (!$canViewAll) { header('Location: demandas.php'); exit; }
?>

<style>
  #mod-anal .fg{margin-bottom:14px;gap:5px}
  #mod-anal input[type=text],#mod-anal input[type=email],#mod-anal input[type=password],
  #mod-anal select,#mod-anal textarea{padding:8px 11px;font-size:13px;border-radius:7px}
  #mod-anal label{font-size:11px}
  #mod-anal textarea{min-height:80px}
  #mod-anal .row2,#mod-anal .row3{gap:24px;margin-bottom:6px}
  .anal-card{transition:all .2s;cursor:pointer}
  .anal-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.18)}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Equipe</div>
      <div class="page-sub">Cadastro e gestão de todos os integrantes — analistas, enfermeiras, coordenadores, financeiro</div>
    </div>
    <div style="display:flex;gap:8px">
      <?php if (!$isVisualizador): ?>
      <button class="btn btn-primary" onclick="openModalAnalista()"><span class="material-symbols-rounded" style="font-size:19px">person_add</span> Novo Integrante</button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filtro por tipo -->
  <div class="filters" style="margin:14px 0">
    <select id="anal-filtro-tipo" onchange="loadAnalistas()" style="min-width:240px">
      <option value="">Todos os tipos</option>
      <option value="analista">Analistas</option>
      <option value="enfermeira">Enfermeiras</option>
      <option value="coordenador">Coordenadores</option>
      <option value="head">Head</option>
      <option value="gerente">Gerentes</option>
      <option value="admin">Admin</option>
      <option value="financeiro">Financeiro</option>
      <option value="novos_negocios">Novos Negócios</option>
      <option value="comercial_cotacao">Comercial (Cotação)</option>
    </select>
  </div>

  <!-- Cards-resumo: só o Total da Equipe (demandas/média foram removidas
       porque o número já aparece dentro de cada card de pessoa) -->
  <div class="cards" id="anal-cards" style="grid-template-columns:1fr">
    <div class="card c-teal"><div class="card-label">Total da Equipe</div><div class="card-val" id="anal-tot">—</div><div class="card-sub">cadastrados ativos</div><div class="card-icon"><span class="material-symbols-rounded">group</span></div></div>
  </div>

  <div id="anal-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;margin-top:18px"></div>
</main>

<!-- ── MODAL: NOVO/EDITAR ANALISTA ──────────────────────────────── -->
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
        <select id="ma-tipo">
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
        </select>
      </div>
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
