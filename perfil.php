<?php
$currentPage = 'perfil';
$pageTitle   = 'Meu Perfil — Interweg Benefícios';
$pageScripts = ['assets/perfil.js'];

require_once __DIR__ . '/_header.php';
?>

<style>
  #sec-perfil-page .fg{margin-bottom:14px;gap:5px}
  #sec-perfil-page input{padding:10px 14px 10px 38px;font-size:13px;border-radius:7px}
  #sec-perfil-page .pf-input-wrap{position:relative}
  #sec-perfil-page .pf-input-wrap .pf-ico{
    position:absolute;left:11px;top:50%;transform:translateY(-50%);
    color:var(--muted);font-size:18px;pointer-events:none;line-height:1;
  }
  #sec-perfil-page label{font-size:11px}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Meu Perfil</div>
      <div class="page-sub">Edite seus dados de acesso e senha</div>
    </div>
  </div>

  <div id="sec-perfil-page" style="display:grid;grid-template-columns:1fr;gap:20px;max-width:560px">

    <div class="table-wrap" style="padding:28px">
      <h3 style="margin-bottom:20px;color:var(--accent);display:flex;align-items:center;gap:8px"><span class="material-symbols-rounded">manage_accounts</span> Dados do Perfil</h3>

      <div class="fg">
        <label>Nome completo</label>
        <div class="pf-input-wrap">
          <span class="material-symbols-rounded pf-ico">person</span>
          <input id="pf-nome" type="text"/>
        </div>
      </div>
      <div class="fg">
        <label>Login <span style="color:var(--muted);font-weight:400;text-transform:none">(não pode ser alterado)</span></label>
        <div class="pf-input-wrap">
          <span class="material-symbols-rounded pf-ico">alternate_email</span>
          <input id="pf-login" type="text" readonly autocomplete="off" style="background:var(--bg-hover);color:var(--muted)"/>
        </div>
      </div>
      <div class="fg">
        <label>Tipo de conta</label>
        <div class="pf-input-wrap">
          <span class="material-symbols-rounded pf-ico">badge</span>
          <input id="pf-tipo" type="text" readonly style="background:var(--bg-hover);color:var(--muted)"/>
        </div>
      </div>

      <div class="fg" style="margin-top:14px">
        <label>Nova Senha <span style="color:var(--muted);font-weight:400;text-transform:none">(deixe em branco para não alterar)</span></label>
        <div class="pf-input-wrap">
          <span class="material-symbols-rounded pf-ico">lock</span>
          <input id="pf-senha" type="password" placeholder="••••••••" autocomplete="new-password"/>
        </div>
      </div>
      <div class="fg">
        <label>Confirmar Nova Senha</label>
        <div class="pf-input-wrap">
          <span class="material-symbols-rounded pf-ico">lock_reset</span>
          <input id="pf-senha2" type="password" placeholder="••••••••" autocomplete="new-password"/>
        </div>
      </div>

      <button class="btn btn-primary" onclick="savePerfil()" style="margin-top:8px">
        <span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar Alterações
      </button>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/_footer.php'; ?>