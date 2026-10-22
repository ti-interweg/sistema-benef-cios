<?php
/**
 * Layout compartilhado — cabeçalho + sidebar.
 * Cada página define $currentPage (ex: 'demandas', 'faturamento', 'renovacao',
 * 'cadastros', 'analistas', 'config', 'perfil', 'formcot', 'apresentacao')
 * pra marcar o item ativo no menu.
 */
require_once __DIR__ . '/config.php';
// Ambiente (homologação) — à prova de falha: se o arquivo não estiver no
// servidor, não derruba o sistema; só assume produção.
if (is_file(__DIR__ . '/ambiente.php')) { require_once __DIR__ . '/ambiente.php'; }
if (!defined('IS_HOMOLOG')) define('IS_HOMOLOG', false);
require_once __DIR__ . '/_perm_silmara.php';
auth();

// ── Timeout de sessão por inatividade (30 min) ──
// Se a pessoa ficar mais de 30 min sem nenhuma requisição (navegação ou keepalive),
// a sessão expira e ela volta pro login. Cada página/keepalive renova o relógio.
if (!empty($_SESSION['user_id'])) {
    if (!defined('IDLE_TIMEOUT')) define('IDLE_TIMEOUT', 12 * 60 * 60); // segundos (12 horas)
    $__agora = time();
    if (isset($_SESSION['last_activity']) && ($__agora - (int)$_SESSION['last_activity']) > IDLE_TIMEOUT) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $__cp = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $__cp['path'], $__cp['domain'], $__cp['secure'], $__cp['httponly']);
        }
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = $__agora;
}

// Troca OBRIGATÓRIA de senha no 1º acesso: bloqueia todo o sistema até a pessoa
// definir a própria senha. (trocar_senha.php e logout.php não incluem este header.)
if (!empty($_SESSION['must_change_pw'])) {
    header('Location: trocar_senha.php'); exit;
}

$userName    = e($_SESSION['user_nome']);
$userTipo    = $_SESSION['user_tipo'];
$isAdmin     = isAdmin();
$isNovosNeg  = isNovosNegocios();
$isFinanceiro = isFinanceiro();
$isVisualizador = isVisualizador();
$isEnfermeira = isEnfermeira();
$isComercialCot = isComercialCotacao();
$canViewAll  = canViewAll(); // admin OU visualizador
$isCliente   = (strtolower(trim($userTipo ?? '')) === 'cliente'); // portal do cliente
$isRenovacao = (strtolower(trim($userTipo ?? '')) === 'renovacao'); // escopo Renovação
// TI: acesso exclusivo (Faturamento Automação). Ajuste a lista se precisar.
$isTI = (strtolower(trim($_SESSION['user_email'] ?? '')) === 'ti@interweg.com.br')
     || in_array(strtolower(trim($_SESSION['user_login'] ?? '')), ['ti','ti@interweg.com.br'], true)
     || strtolower(trim($_SESSION['user_nome'] ?? '')) === 'ti';
$currentPage = $currentPage ?? 'demandas';
$pageTitle   = $pageTitle   ?? SITE_NAME;

// ── Auditoria: registra o acesso à página (somente GET, evita ruído) ──
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    require_once __DIR__ . '/auditoria.php';
    aud_log($currentPage, $currentPage, null, null, 'acesso');
}

// Cliente (portal): não acessa nenhuma tela interna — vai só pro portal próprio.
if ($isCliente) { header('Location: portal.php'); exit; }

// Triagem: só admin/visualizador (equipe interna).
if ($currentPage === 'triagem' && !$canViewAll) { header('Location: demandas.php'); exit; }

// Renovação (escopo restrito): só vê Renovação, Atas, Sinistralidade, Ações de Saúde, Devolutiva e Perfil.
if ($isRenovacao && !in_array($currentPage, ['renovacao','atas_visitas','sinistralidade','acoes_saude','programas_saude','entregaveis','devolutiva_nutricional','perfil'], true)) {
    header('Location: renovacao.php'); exit;
}

// Bloqueia acesso de usuários Novos Negócios a páginas fora da área Ferramentas.
$paginasNovosNeg = ['formcot','apresentacao','apresentacao_teste','assistente_apolice','controle_cotacao','fechamento','perfil'];
if ($isNovosNeg && !in_array($currentPage, $paginasNovosNeg, true)) {
    header('Location: controle_cotacao.php'); exit;
}

// Bloqueia acesso de usuários Financeiro a tudo que não seja Faturamento ou Perfil.
$paginasFinanceiro = ['faturamento','faturamento_backup','faturamento_outubro','faturamento_novembro','agenciamento_ag','fechamento','perfil'];
if ($isFinanceiro && !in_array($currentPage, $paginasFinanceiro, true)) {
    header('Location: faturamento.php'); exit;
}

// Bloqueia acesso de Enfermeiras a tudo que não seja Enfermagem, Cadastros ou Perfil.
$paginasEnfermeira = ['demandas_assistenciais','acoes_saude','programas_saude','entregaveis','sinistralidade','devolutiva_nutricional','cadastros','perfil'];
if ($isEnfermeira && !in_array($currentPage, $paginasEnfermeira, true)) {
    header('Location: demandas_assistenciais.php'); exit;
}

// Bloqueia acesso do Comercial (Cotação) a tudo que não seja Controle de Cotação, Fechamento ou Perfil.
$paginasComercialCot = ['controle_cotacao','fechamento','perfil'];
if ($isComercialCot && !in_array($currentPage, $paginasComercialCot, true)) {
    header('Location: controle_cotacao.php'); exit;
}

// Passagem de Bastão e Base de Clientes: acesso restrito a ADMIN (bloqueia URL direta).
if (in_array($currentPage, ['passagem_bastao','base_clientes'], true) && !$isAdmin) {
    header('Location: demandas.php'); exit;
}

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
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>

<!-- ════════════════════════════════════════════════════════════════
     MODAL SAFETY — Impede que TODOS os modais fechem ao clicar fora.
     Antes: 21 modais do sistema (cotação, faturamento, renovação,
     demanda, ata, cadastros, etc.) fechavam ao clicar no fundo escuro,
     perdendo dados não salvos. Agora: clicar fora não fecha mais — só
     fecha pelo botão Cancelar/Salvar/X explícito.
     ════════════════════════════════════════════════════════════════ -->
<script>
(function(){
  // Roda assim que o DOM carregar. Pra cada modal (.ov), remove o handler
  // inline `onclick="if(event.target===this)closeModal(...)"` e substitui
  // por um listener que IGNORA cliques no backdrop (event.target === this).
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.ov').forEach(function(ov){
      // Remove o onclick inline herdado dos PHPs
      ov.removeAttribute('onclick');
      ov.onclick = null;
      // Bloqueia propagação de clique no backdrop pra ninguém mais fechar
      ov.addEventListener('click', function(e){
        if (e.target === ov) {
          e.stopPropagation();
          // Feedback visual sutil — "chacoalha" o modal pra indicar
          // que clicar fora não fecha mais
          const modal = ov.querySelector('.modal');
          if (modal) {
            modal.style.transition = 'transform .15s ease';
            modal.style.transform = 'scale(1.01)';
            setTimeout(function(){ modal.style.transform = ''; }, 150);
          }
        }
      });
    });
  });

  // Adicional: ESC tenta fechar o modal aberto mas só se confirmar
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      const aberto = document.querySelector('.ov.show');
      if (aberto) {
        // Não fecha automaticamente — só destaque visual
        const modal = aberto.querySelector('.modal');
        if (modal) {
          modal.style.transition = 'transform .15s ease';
          modal.style.transform = 'scale(1.01)';
          setTimeout(function(){ modal.style.transform = ''; }, 150);
        }
        e.preventDefault();
      }
    }
  });
})();
</script>

<!-- ════════════════════════════════════════════════════════════════
     KEEPALIVE — Mantém a sessão viva enquanto a aba estiver aberta.
     Faz ping a cada 5 min na API; isso renova o cookie de sessão.
     Sem isso, depois de 12h SEM atividade, a sessão expira e tentar
     salvar dá erro silencioso. COM isso, a sessão fica viva pra
     sempre enquanto o sistema estiver aberto.
     Também detecta 401 (sessão caiu por algum motivo) e avisa.
     ════════════════════════════════════════════════════════════════ -->
<script>
(function(){
  // Ping a cada 5 minutos
  const PING_MS = 5 * 60 * 1000;
  let _sessionWarned = false;

  function ping(){
    fetch('api.php?action=keepalive', {credentials:'same-origin'})
      .then(r => {
        if (r.status === 401) {
          if (!_sessionWarned) {
            _sessionWarned = true;
            if (confirm('Sua sessão expirou. Clique OK pra fazer login novamente.\n\nATENÇÃO: alterações não salvas podem ser perdidas. Se tiver algo importante aberto, copie pro bloco de notas antes de continuar.')) {
              window.location.href = 'login.php?back=' + encodeURIComponent(window.location.pathname);
            }
          }
        }
      })
      .catch(() => { /* offline ou erro de rede — silencioso */ });
  }

  // Primeiro ping imediato (valida que sessão tá viva) + a cada 5 min
  setInterval(ping, PING_MS);

  // Intercepta TODOS os fetch() pra detectar 401 e avisar antes de perder dados
  const _origFetch = window.fetch;
  window.fetch = async function(...args){
    const resp = await _origFetch.apply(this, args);
    if (resp.status === 401 && !_sessionWarned) {
      _sessionWarned = true;
      // Tenta extrair JSON pra ver se é session_expired
      resp.clone().json().then(data => {
        if (data && data.session_expired) {
          alert('Sua sessão expirou enquanto você estava preenchendo!\n\nVocê será redirecionado pro login. Suas alterações abertas podem ser perdidas — copie o que tiver de importante antes de prosseguir.');
          window.location.href = 'login.php?back=' + encodeURIComponent(window.location.pathname);
        }
      }).catch(()=>{});
    }
    return resp;
  };
})();
</script>
<?php if ($isVisualizador): ?>
<style>
  /* Modo somente-visualização: esconde tudo que envolve edição/criação/exclusão */
  body.visualizador .btn-novo,
  body.visualizador .btn-criar,
  body.visualizador .btn-editar,
  body.visualizador .btn-excluir,
  body.visualizador .btn-importar,
  body.visualizador .btn-virar,
  body.visualizador button[onclick*="Editar"],
  body.visualizador button[onclick*="Excluir"],
  body.visualizador button[onclick*="Novo"],
  body.visualizador button[onclick*="Importar"],
  body.visualizador button[onclick*="virarAno"],
  body.visualizador button[onclick*="Salvar"],
  body.visualizador a[href*="logout"]:not(.btn-logout),
  body.visualizador .modal-form button[type="submit"] {
    display: none !important;
  }
  /* Inputs/selects ficam read-only visualmente */
  body.visualizador .modal-form input:not([type=button]):not([type=submit]),
  body.visualizador .modal-form select,
  body.visualizador .modal-form textarea {
    pointer-events: none;
    background: #f5f5f5 !important;
    cursor: not-allowed;
  }
</style>
<?php endif; ?>
</head>
<body>
<script>
// Aplica tema (dark/light) preservando classe 'visualizador' se aplicável
(function(){
  const t = localStorage.getItem('iw-theme') || 'dark';
  const extras = <?= $isVisualizador ? "' visualizador'" : "''" ?>;
  document.body.className = t + extras;
})();
// Constantes de sessão expostas pra todos os módulos JS
window.IS_ADMIN      = <?= $isAdmin ? 'true' : 'false' ?>;
window.IS_FINANCEIRO = <?= $isFinanceiro ? 'true' : 'false' ?>;
window.IS_NOVOSNEG   = <?= $isNovosNeg ? 'true' : 'false' ?>;
window.IS_VISUALIZADOR = <?= $isVisualizador ? 'true' : 'false' ?>;
window.CAN_VIEW_ALL    = <?= $canViewAll ? 'true' : 'false' ?>;
window.USER_NOME = <?= json_encode($_SESSION['user_nome'] ?? '') ?>;
window.USER_ID   = <?= intval($_SESSION['user_id'] ?? 0) ?>;
</script>

<?php if (IS_HOMOLOG): ?>
<div style="position:fixed;top:0;left:0;right:0;z-index:10000;background:#f59e0b;color:#231a00;text-align:center;
            font-size:12px;font-weight:800;letter-spacing:.5px;padding:4px 10px;box-shadow:0 2px 8px rgba(0,0,0,.2)">
  ⚠ AMBIENTE DE TESTE — HOMOLOGAÇÃO · alterações aqui NÃO afetam a produção · e-mails desativados
</div>
<style>body{padding-top:26px}</style>
<?php endif; ?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-row">
      <div class="logo-icon"><span class="material-symbols-rounded" style="font-size:22px;color:#fff">health_and_safety</span></div>
      <div>
        <div class="logo-text">INTER<span>WEG</span></div>
        <div class="logo-sub">Seguros</div>
      </div>
    </div>
  </div>
  <nav>
    <?php if ($isComercialCot): ?>
    <!-- Comercial (Cotação): vê só Controle de Cotação, Fechamento e Meu Perfil -->
    <div class="nav-section">Comercial</div>
    <a class="nav-item<?= _navActive('controle_cotacao',$currentPage) ?>" href="controle_cotacao.php"><span class="material-symbols-rounded nav-icon">monitoring</span><span>Controle de Cotação</span></a>
    <a class="nav-item<?= _navActive('fechamento',$currentPage) ?>" href="fechamento.php"><span class="material-symbols-rounded nav-icon">task_alt</span><span>Fechamento</span></a>
    <a class="nav-item<?= _navActive('perfil',$currentPage) ?>" href="perfil.php"><span class="material-symbols-rounded nav-icon">manage_accounts</span><span>Meu Perfil</span></a>
    <?php elseif ($isFinanceiro): ?>
    <!-- Financeiro: vê só Faturamento e Meu Perfil -->
    <div class="nav-section">Financeiro</div>
    <a class="nav-item<?= _navActive('faturamento',$currentPage) ?>" href="faturamento.php"><span class="material-symbols-rounded nav-icon">receipt_long</span><span>Faturamento Anteriores</span></a>
    <a class="nav-item<?= _navActive('faturamento_backup',$currentPage) ?>" href="faturamento_backup.php"><span class="material-symbols-rounded nav-icon">receipt_long</span><span>Faturamento Setembro</span></a>
    <a class="nav-item<?= _navActive('faturamento_outubro',$currentPage) ?>" href="faturamento_outubro.php"><span class="material-symbols-rounded nav-icon">calendar_month</span><span>Faturamento Outubro</span></a>
    <a class="nav-item<?= _navActive('agenciamento_ag',$currentPage) ?>" href="agenciamento_ag.php"><span class="material-symbols-rounded nav-icon">handshake</span><span>Agenciamento (AG)</span></a>
    <a class="nav-item<?= _navActive('fechamento',$currentPage) ?>" href="fechamento.php"><span class="material-symbols-rounded nav-icon">task_alt</span><span>Fechamento</span></a>
    <a class="nav-item<?= _navActive('perfil',$currentPage) ?>" href="perfil.php"><span class="material-symbols-rounded nav-icon">manage_accounts</span><span>Meu Perfil</span></a>
    <?php elseif ($isEnfermeira): ?>
    <!-- Enfermeira: vê só Enfermagem, Cadastros e Meu Perfil -->
    <div class="nav-section">Enfermagem</div>
    <a class="nav-item<?= _navActive('demandas_assistenciais',$currentPage) ?>" href="demandas_assistenciais.php"><span class="material-symbols-rounded nav-icon">medical_services</span><span>Demandas Assistenciais</span></a>
    <a class="nav-item<?= _navActive('acoes_saude',$currentPage) ?>" href="acoes_saude.php"><span class="material-symbols-rounded nav-icon">favorite</span><span>Ações de Saúde</span></a>
    <a class="nav-item<?= _navActive('programas_saude',$currentPage) ?>" href="programas_saude.php"><span class="material-symbols-rounded nav-icon">child_friendly</span><span>Interbaby / Intercuidado</span></a>
    <a class="nav-item<?= _navActive('entregaveis',$currentPage) ?>" href="entregaveis.php"><span class="material-symbols-rounded nav-icon">fact_check</span><span>Entregáveis por Porte</span></a>
    <a class="nav-item<?= _navActive('sinistralidade',$currentPage) ?>" href="sinistralidade.php"><span class="material-symbols-rounded nav-icon">monitoring</span><span>Sinistralidade</span></a>
    <a class="nav-item<?= _navActive('devolutiva_nutricional',$currentPage) ?>" href="devolutiva_nutricional.php"><span class="material-symbols-rounded nav-icon">monitor_heart</span><span>Devolutiva</span></a>
    <div class="nav-section">Menu</div>
    <a class="nav-item<?= _navActive('cadastros',$currentPage) ?>" href="cadastros.php"><span class="material-symbols-rounded nav-icon">edit_note</span><span>Cadastros</span></a>
    <a class="nav-item<?= _navActive('perfil',$currentPage) ?>" href="perfil.php"><span class="material-symbols-rounded nav-icon">manage_accounts</span><span>Meu Perfil</span></a>
    <?php elseif ($isRenovacao): ?>
    <!-- Renovação: escopo restrito -->
    <div class="nav-section">Renovação</div>
    <a class="nav-item<?= _navActive('renovacao',$currentPage) ?>" href="renovacao.php"><span class="material-symbols-rounded nav-icon">autorenew</span><span>Renovação</span></a>
    <a class="nav-item<?= _navActive('atas_visitas',$currentPage) ?>" href="atas_visitas.php"><span class="material-symbols-rounded nav-icon">edit_document</span><span>Atas de Visitas</span></a>
    <a class="nav-item<?= _navActive('sinistralidade',$currentPage) ?>" href="sinistralidade.php"><span class="material-symbols-rounded nav-icon">monitoring</span><span>Sinistralidade</span></a>
    <a class="nav-item<?= _navActive('acoes_saude',$currentPage) ?>" href="acoes_saude.php"><span class="material-symbols-rounded nav-icon">favorite</span><span>Ações de Saúde</span></a>
    <a class="nav-item<?= _navActive('programas_saude',$currentPage) ?>" href="programas_saude.php"><span class="material-symbols-rounded nav-icon">child_friendly</span><span>Interbaby / Intercuidado</span></a>
    <a class="nav-item<?= _navActive('entregaveis',$currentPage) ?>" href="entregaveis.php"><span class="material-symbols-rounded nav-icon">fact_check</span><span>Entregáveis por Porte</span></a>
    <a class="nav-item<?= _navActive('devolutiva_nutricional',$currentPage) ?>" href="devolutiva_nutricional.php"><span class="material-symbols-rounded nav-icon">monitor_heart</span><span>Devolutiva</span></a>
    <a class="nav-item<?= _navActive('perfil',$currentPage) ?>" href="perfil.php"><span class="material-symbols-rounded nav-icon">manage_accounts</span><span>Meu Perfil</span></a>
    <?php elseif (!$isNovosNeg): ?>
    <div class="nav-section">Menu</div>
    <?php if ($canViewAll): ?>
    <a class="nav-item<?= _navActive('triagem',$currentPage) ?>" href="triagem.php"><span class="material-symbols-rounded nav-icon">inbox</span><span>Triagem</span></a>
    <?php endif; ?>
    <a class="nav-item<?= _navActive('demandas',$currentPage) ?>" href="demandas.php"><span class="material-symbols-rounded nav-icon">assignment</span><span>Demandas</span></a>
    <a class="nav-item<?= _navActive('resumo_movimentacoes',$currentPage) ?>" href="resumo_movimentacoes.php"><span class="material-symbols-rounded nav-icon">summarize</span><span>Resumo Mensal</span></a>
    <a class="nav-item<?= _navActive('faturamento',$currentPage) ?>" href="faturamento.php"><span class="material-symbols-rounded nav-icon">receipt_long</span><span>Faturamento Anteriores</span></a>
    <a class="nav-item<?= _navActive('faturamento_backup',$currentPage) ?>" href="faturamento_backup.php"><span class="material-symbols-rounded nav-icon">receipt_long</span><span>Faturamento Setembro</span></a>
    <a class="nav-item<?= _navActive('faturamento_outubro',$currentPage) ?>" href="faturamento_outubro.php"><span class="material-symbols-rounded nav-icon">calendar_month</span><span>Faturamento Outubro</span></a>
    <?php if ($isAdmin || $isFinanceiro): ?>
    <a class="nav-item<?= _navActive('agenciamento_ag',$currentPage) ?>" href="agenciamento_ag.php"><span class="material-symbols-rounded nav-icon">handshake</span><span>Agenciamento (AG)</span></a>
    <?php endif; ?>
    <?php if ($canViewAll): // admin OU visualizador (espelho completo) ?>
    <a class="nav-item<?= _navActive('renovacao',$currentPage) ?>" href="renovacao.php"><span class="material-symbols-rounded nav-icon">autorenew</span><span>Renovação</span></a>
    <a class="nav-item<?= _navActive('atas_visitas',$currentPage) ?>" href="atas_visitas.php"><span class="material-symbols-rounded nav-icon">edit_document</span><span>Atas de Visitas</span></a>
    <a class="nav-item<?= _navActive('dashboard',$currentPage) ?>" href="dashboard.php"><span class="material-symbols-rounded nav-icon">dashboard</span><span>Dashboard</span></a>
    <?php endif; ?>
    <a class="nav-item<?= _navActive('cadastros',$currentPage) ?>" href="cadastros.php"><span class="material-symbols-rounded nav-icon">edit_note</span><span>Cadastros</span></a>
    <?php if ($isAdmin): ?>
    <a class="nav-item<?= _navActive('base_clientes',$currentPage) ?>" href="base_clientes.php"><span class="material-symbols-rounded nav-icon">contacts</span><span>Base de Clientes</span></a>
    <?php endif; ?>

    <?php if ($isAdmin): // Enfermagem (admin) ?>
    <div class="nav-section">Enfermagem</div>
    <a class="nav-item<?= _navActive('demandas_assistenciais',$currentPage) ?>" href="demandas_assistenciais.php"><span class="material-symbols-rounded nav-icon">medical_services</span><span>Demandas Assistenciais</span></a>
    <a class="nav-item<?= _navActive('acoes_saude',$currentPage) ?>" href="acoes_saude.php"><span class="material-symbols-rounded nav-icon">favorite</span><span>Ações de Saúde</span></a>
    <a class="nav-item<?= _navActive('programas_saude',$currentPage) ?>" href="programas_saude.php"><span class="material-symbols-rounded nav-icon">child_friendly</span><span>Interbaby / Intercuidado</span></a>
    <a class="nav-item<?= _navActive('entregaveis',$currentPage) ?>" href="entregaveis.php"><span class="material-symbols-rounded nav-icon">fact_check</span><span>Entregáveis por Porte</span></a>
    <a class="nav-item<?= _navActive('sinistralidade',$currentPage) ?>" href="sinistralidade.php"><span class="material-symbols-rounded nav-icon">monitoring</span><span>Sinistralidade</span></a>
    <a class="nav-item<?= _navActive('devolutiva_nutricional',$currentPage) ?>" href="devolutiva_nutricional.php"><span class="material-symbols-rounded nav-icon">monitor_heart</span><span>Devolutiva</span></a>
    <?php endif; ?>
    <?php if (!$isAdmin): // admin tem o link em outro lugar; outros tipos veem aqui ?>
    <a class="nav-item<?= _navActive('perfil',$currentPage) ?>" href="perfil.php"><span class="material-symbols-rounded nav-icon">manage_accounts</span><span>Meu Perfil</span></a>
    <?php endif; ?>
    <?php endif; // financeiro / novos_neg / default ?>
    <?php if ($isAdmin || $isNovosNeg || $isVisualizador || isSilmara()): ?>
    <div class="nav-section">Novos Negócios</div>
    <a class="nav-item" href="https://app.cotaco.es/cotacoes/" target="_blank"><span class="material-symbols-rounded nav-icon">request_quote</span><span>Cotações</span></a>
    <a class="nav-item<?= _navActive('formcot',$currentPage) ?>" href="formcot.php"><span class="material-symbols-rounded nav-icon">description</span><span>Formulário Cotação</span></a>
    <a class="nav-item<?= _navActive('apresentacao',$currentPage) ?>" href="apresentacao.php"><span class="material-symbols-rounded nav-icon">slideshow</span><span>Apresentação</span></a>
    <a class="nav-item<?= _navActive('apresentacao_teste',$currentPage) ?>" href="apresentacao_teste.php"><span class="material-symbols-rounded nav-icon">science</span><span>Apresentação Teste</span></a>
    <a class="nav-item<?= _navActive('assistente_apolice',$currentPage) ?>" href="assistente_apolice.php"><span class="material-symbols-rounded nav-icon">smart_toy</span><span>Assistente de Apólice</span></a>
    <a class="nav-item<?= _navActive('controle_cotacao',$currentPage) ?>" href="controle_cotacao.php"><span class="material-symbols-rounded nav-icon">monitoring</span><span>Controle de Cotação</span></a>
    <a class="nav-item<?= _navActive('fechamento',$currentPage) ?>" href="fechamento.php"><span class="material-symbols-rounded nav-icon">task_alt</span><span>Fechamento</span></a>
    <?php if ($isAdmin): // Passagem de Bastão: somente admin ?>
    <a class="nav-item<?= _navActive('passagem_bastao',$currentPage) ?>" href="passagem_bastao.php"><span class="material-symbols-rounded nav-icon">swap_horiz</span><span>Passagem de Bastão</span></a>
    <?php endif; ?>
    <?php if ($isNovosNeg): ?>
    <a class="nav-item<?= _navActive('perfil',$currentPage) ?>" href="perfil.php" style="margin-top:18px"><span class="material-symbols-rounded nav-icon">manage_accounts</span><span>Meu Perfil</span></a>
    <?php endif; ?>
    <?php endif; ?>
  </nav>
  <div class="sidebar-user">
    <div class="user-row">
      <div class="user-avatar"><?= mb_substr($userName,0,2) ?></div>
      <div>
        <div class="user-name"><?= $userName ?></div>
        <div class="user-role"><?= getRoleLabel($_SESSION['user_tipo'] ?? '') ?></div>
      </div>
    </div>
    <div style="display:flex;gap:6px;margin-bottom:10px">
      <button onclick="setTheme('light')" id="btn-light" class="btn-logout" style="flex:1;padding:6px 0;font-size:11px"><span class="material-symbols-rounded" style="font-size:15px">light_mode</span> Claro</button>
      <button onclick="setTheme('dark')" id="btn-dark" class="btn-logout" style="flex:1;padding:6px 0;font-size:11px"><span class="material-symbols-rounded" style="font-size:15px">dark_mode</span> Escuro</button>
    </div>
    <a href="logout.php" class="btn-logout"><span class="material-symbols-rounded" style="font-size:16px">logout</span> Sair da conta</a>
  </div>
</aside>
