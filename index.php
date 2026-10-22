<?php
/**
 * Index = redireciona pra Demandas (página inicial padrão).
 * Cada módulo agora tem seu próprio .php standalone.
 */
require_once __DIR__ . '/config.php';
auth();

// Página inicial por perfil (cada tipo cai na sua área permitida).
$__t = strtolower(trim((string)($_SESSION['user_tipo'] ?? '')));
if ($__t === 'financeiro') {
    header('Location: faturamento.php');
} elseif ($__t === 'comercial_cotacao') {
    header('Location: controle_cotacao.php');
} elseif ($__t === 'novos_negocios') {
    header('Location: formcot.php');
} elseif ($__t === 'enfermeira' || $__t === 'enfermagem') {
    header('Location: demandas_assistenciais.php');
} else {
    header('Location: demandas.php');
}
exit;


$userName    = e($_SESSION['user_nome']);
$userTipo    = $_SESSION['user_tipo'];
$isAdmin     = isAdmin();
$currentPage = $currentPage ?? 'demandas';
$pageTitle   = $pageTitle   ?? SITE_NAME;

function _navActive($page, $current) {
    return $page === $current ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= e($pageTitle) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0..1,-50..200"/>
<link rel="stylesheet" href="assets/app.css"/>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</head>
<body>
<script>
document.body.className=localStorage.getItem('iw-theme')||'dark';
// Constantes de sessão expostas pra todos os módulos JS
window.IS_ADMIN  = <?= $isAdmin ? 'true' : 'false' ?>;
window.USER_NOME = <?= json_encode($_SESSION['user_nome'] ?? '') ?>;
window.USER_ID   = <?= intval($_SESSION['user_id'] ?? 0) ?>;
</script>

<aside class="sidebar">
  <div class="sidebar-logo" style="text-align:center;padding:18px 14px 14px">
    <img src="logo_interweg.png" alt="Interweg Seguros" class="iw-logo"
         style="max-width:150px;width:100%;height:auto;display:block;margin:0 auto"
         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"/>
    <div class="logo-row" style="display:none;justify-content:center;align-items:center;gap:10px">
      <div class="logo-icon"><span class="material-symbols-rounded" style="font-size:22px;color:#fff">health_and_safety</span></div>
      <div>
        <div class="logo-text">INTER<span>WEG</span></div>
        <div class="logo-sub">Seguros</div>
      </div>
    </div>
  </div>
  <style>
    /* Logo se adapta ao tema: no dark, inverte branco→preto (some no fundo) e mantém o teal */
    body.dark .iw-logo{filter:invert(1) hue-rotate(180deg) saturate(1.6)}
    body.light .iw-logo{filter:none}
  </style>
  <nav>
    <div class="nav-section">Menu</div>
    <a class="nav-item<?= _navActive('demandas',$currentPage) ?>" href="demandas.php"><span class="material-symbols-rounded nav-icon">assignment</span><span>Demandas</span></a>
    <?php if ($isAdmin): ?>
    <a class="nav-item<?= _navActive('fluxo',$currentPage) ?>" href="fluxo.php"><span class="material-symbols-rounded nav-icon">account_tree</span><span>Fluxo Comercial</span></a>
    <?php endif; ?>
    <a class="nav-item<?= _navActive('faturamento',$currentPage) ?>" href="faturamento.php"><span class="material-symbols-rounded nav-icon">receipt_long</span><span>Faturamento</span></a>
    <?php if ($isAdmin): ?>
    <a class="nav-item<?= _navActive('renovacao',$currentPage) ?>" href="renovacao.php"><span class="material-symbols-rounded nav-icon">autorenew</span><span>Renovação</span></a>
    <a class="nav-item<?= _navActive('dashboard',$currentPage) ?>" href="dashboard.php"><span class="material-symbols-rounded nav-icon">dashboard</span><span>Dashboard</span></a>
    <a class="nav-item<?= _navActive('analistas',$currentPage) ?>" href="analistas.php"><span class="material-symbols-rounded nav-icon">group</span><span>Analistas</span></a>
    <?php endif; ?>
    <a class="nav-item<?= _navActive('cadastros',$currentPage) ?>" href="cadastros.php"><span class="material-symbols-rounded nav-icon">edit_note</span><span>Cadastros</span></a>
    <?php if ($isAdmin): ?>
    <a class="nav-item<?= _navActive('config',$currentPage) ?>" href="configuracoes.php"><span class="material-symbols-rounded nav-icon">settings</span><span>Configurações</span></a>
    <?php else: ?>
    <a class="nav-item<?= _navActive('perfil',$currentPage) ?>" href="perfil.php"><span class="material-symbols-rounded nav-icon">manage_accounts</span><span>Meu Perfil</span></a>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <div class="nav-section">Ferramentas</div>
    <a class="nav-item" href="https://app.cotaco.es/cotacoes/" target="_blank"><span class="material-symbols-rounded nav-icon">request_quote</span><span>Cotações</span></a>
    <a class="nav-item<?= _navActive('formcot',$currentPage) ?>" href="formcot.php"><span class="material-symbols-rounded nav-icon">description</span><span>Formulário Cotação</span></a>
    <a class="nav-item<?= _navActive('apresentacao',$currentPage) ?>" href="apresentacao.php"><span class="material-symbols-rounded nav-icon">slideshow</span><span>Apresentação</span></a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-user">
    <div class="user-row">
      <div class="user-avatar"><?= mb_substr($userName,0,2) ?></div>
      <div>
        <div class="user-name"><?= $userName ?></div>
        <div class="user-role"><?= $isAdmin ? 'Admin' : 'Analista' ?></div>
      </div>
    </div>
    <div style="display:flex;gap:6px;margin-bottom:10px">
      <button onclick="setTheme('light')" id="btn-light" class="btn-logout" style="flex:1;padding:6px 0;font-size:11px"><span class="material-symbols-rounded" style="font-size:15px">light_mode</span> Claro</button>
      <button onclick="setTheme('dark')" id="btn-dark" class="btn-logout" style="flex:1;padding:6px 0;font-size:11px"><span class="material-symbols-rounded" style="font-size:15px">dark_mode</span> Escuro</button>
    </div>
    <a href="logout.php" class="btn-logout"><span class="material-symbols-rounded" style="font-size:16px">logout</span> Sair da conta</a>
  </div>
</aside>