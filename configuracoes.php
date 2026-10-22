<?php
$currentPage = 'config';
$pageTitle   = 'Configurações — Interweg Benefícios';
$pageScripts = ['assets/configuracoes.js'];

require_once __DIR__ . '/_header.php';
if (!$canViewAll) { header('Location: demandas.php'); exit; }
require_once __DIR__ . '/auditoria.php';
?>

<style>
  #sec-config-page .fg{margin-bottom:14px;gap:5px}
  #sec-config-page input,#sec-config-page select{padding:8px 11px;font-size:13px;border-radius:7px}
  #sec-config-page label{font-size:11px}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Configurações</div>
      <div class="page-sub">Alertas, e-mails e preferências do sistema</div>
    </div>
  </div>

  <div id="sec-config-page" style="display:grid;grid-template-columns:1fr;gap:20px;max-width:600px">

    <div class="table-wrap" style="padding:28px">
      <h3 style="margin-bottom:20px;color:var(--accent);display:flex;align-items:center;gap:8px"><span class="material-symbols-rounded">mail</span> Alertas de E-mail</h3>

      <div class="fg">
        <label>E-mail para notificações</label>
        <input id="cfg-email" type="email" placeholder="ti@interweg.com.br"/>
      </div>

      <div class="fg">
        <label>Alertar quando próxima ação vencer</label>
        <select id="cfg-alerta">
          <option>1 dia antes</option>
          <option value="2">2 dias antes</option>
          <option>No dia</option>
          <option value="off">Não alertar</option>
        </select>
      </div>

      <div class="fg" style="margin-bottom:20px">
        <label>Resumo diário automático</label>
        <select id="cfg-resumo">
          <option value="uteis">Dias úteis às 08h00</option>
          <option value="todos">Todos os dias às 08h00</option>
          <option value="off">Não enviar</option>
        </select>
      </div>

      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <button class="btn btn-primary" onclick="saveConfig()"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar Configurações</button>
        <button class="btn btn-outline" onclick="testEmail()"><span class="material-symbols-rounded" style="font-size:17px">send</span> Enviar E-mail de Teste</button>
      </div>
    </div>

    <?php if (aud_pode_ver()): ?>
    <div class="table-wrap" style="padding:28px">
      <h3 style="margin-bottom:8px;color:var(--accent);display:flex;align-items:center;gap:8px"><span class="material-symbols-rounded">history</span> Histórico e Auditoria</h3>
      <p style="font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:18px">Quem acessou e o que foi alterado — data, hora, IP, usuário e processo. Acesso restrito.</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn btn-primary" href="auditoria_admin.php"><span class="material-symbols-rounded" style="font-size:17px">open_in_new</span> Abrir histórico</a>
        <a class="btn btn-outline" href="auditoria_indicadores.php"><span class="material-symbols-rounded" style="font-size:17px">leaderboard</span> Indicadores de uso</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <div class="table-wrap" style="padding:28px">
      <h3 style="margin-bottom:8px;color:var(--accent);display:flex;align-items:center;gap:8px"><span class="material-symbols-rounded">alternate_email</span> Usuários sem e-mail</h3>
      <p style="font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:18px">Verifica quem está sem e-mail válido no cadastro — nessas pessoas, a resposta do cliente cai no noreply.</p>
      <a class="btn btn-primary" href="checar_emails.php"><span class="material-symbols-rounded" style="font-size:17px">open_in_new</span> Verificar cadastros</a>
    </div>
    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . '/_footer.php'; ?>
