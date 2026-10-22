<?php
$currentPage = 'base_clientes';
$pageTitle   = 'Base de Clientes — Interweg Benefícios';
$pageScripts = ['assets/base_clientes.js'];
require_once __DIR__ . '/_header.php';
?>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Base de Clientes</div>
      <div class="page-sub">Cadastro oficial dos clientes concluídos na Passagem de Bastão</div>
    </div>
    <div id="bc-count" style="font-weight:700;color:var(--muted)"></div>
  </div>

  <div class="filters" style="margin:12px 0">
    <input class="search-inp" type="text" id="bc-search" style="width:100%"
           placeholder="Buscar por empresa, grupo, CNPJ, contrato, seguradora ou analista..."
           oninput="bcLoad()" autocomplete="off"/>
  </div>

  <div class="tscroll">
    <table>
      <thead><tr>
        <th>Grupo / Empresa</th><th>CNPJ</th><th>Seguradora</th><th>Contrato</th>
        <th>Ramo</th><th style="text-align:center">Vidas</th><th>Início vigência</th><th>Analista</th>
        <th style="text-align:center">Ações</th>
      </tr></thead>
      <tbody id="bc-tbody">
        <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:20px">Carregando…</td></tr>
      </tbody>
    </table>
  </div>
</main>

<?php require_once __DIR__ . '/_footer.php'; ?>
