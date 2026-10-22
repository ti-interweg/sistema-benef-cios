<?php
require_once 'config.php';

// ── Login próprio do portal (somente usuários tipo 'cliente') ──
$erro = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['portal_login'])) {
    $lg = trim($_POST['login'] ?? '');
    $pw = (string)($_POST['senha'] ?? '');
    if ($lg !== '' && $pw !== '') {
        try {
            $st = db()->prepare("SELECT id, nome, tipo, senha, email, grupo FROM analistas WHERE login = ? AND ativo = 1 LIMIT 1");
            $st->execute([$lg]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $u = null; }
        if ($u && strtolower(trim($u['tipo'])) === 'cliente' && password_verify($pw, $u['senha'])) {
            $_SESSION['user_id']    = $u['id'];
            $_SESSION['user_nome']  = $u['nome'];
            $_SESSION['user_tipo']  = 'cliente';
            $_SESSION['user_login'] = $lg;
            $_SESSION['user_src']   = 'analistas';
            $_SESSION['user_email'] = $u['email'] ?? null;
            $_SESSION['user_grupo'] = $u['grupo'] ?? null;
            header('Location: portal'); exit;
        }
        $erro = 'Login ou senha inválidos.';
    } else {
        $erro = 'Informe login e senha.';
    }
}

$authed = !empty($_SESSION['user_id']) && strtolower($_SESSION['user_tipo'] ?? '') === 'cliente';

// Marca da empresa na tela de login (?e=slug) — apenas visual
$brandEmpresa = '';
if (!empty($_GET['e'])) {
    $brandEmpresa = strtoupper(trim(str_replace('-', ' ', preg_replace('/[^a-zA-Z0-9\- ]/', '', $_GET['e']))));
}

// ── Não autenticado como cliente → tela de login dedicada do portal ──
if (!$authed):
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Portal do Cliente — Interweg Benefícios</title>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
<style>
  :root{--accent:#00b4a6;--accent2:#009e92;--ink:#14313a;--muted:#6b7c86;--line:#e3ebee;--danger:#c0392b}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',Tahoma,sans-serif;min-height:100vh;display:flex;background:#eef2f4}
  .material-symbols-rounded{vertical-align:middle;line-height:1}
  .split{display:flex;width:100%;min-height:100vh}
  /* Painel de marca */
  .brandside{flex:1.05;background:linear-gradient(140deg,#00b4a6 0%,#009e92 55%,#0a7f77 100%);color:#fff;padding:56px 60px;display:flex;flex-direction:column;justify-content:center;position:relative;overflow:hidden}
  .brandside::before{content:"";position:absolute;width:520px;height:520px;border-radius:50%;background:rgba(255,255,255,.07);top:-160px;right:-160px}
  .brandside::after{content:"";position:absolute;width:360px;height:360px;border-radius:50%;background:rgba(255,255,255,.06);bottom:-120px;left:-100px}
  .brandside .logo{font-size:26px;font-weight:800;letter-spacing:1px;position:relative}
  .brandside h1{font-size:34px;font-weight:800;line-height:1.15;margin:22px 0 10px;position:relative}
  .brandside p{font-size:15px;opacity:.92;max-width:420px;position:relative;line-height:1.5}
  .feats{margin-top:34px;display:flex;flex-direction:column;gap:16px;position:relative}
  .feat{display:flex;align-items:center;gap:14px;font-size:14.5px}
  .feat .ic{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center}
  .feat .ic .material-symbols-rounded{font-size:23px}
  /* Formulário */
  .formside{flex:.95;display:flex;align-items:center;justify-content:center;padding:40px}
  .box{width:100%;max-width:380px}
  .box .hi{font-size:13px;color:var(--accent2);font-weight:800;text-transform:uppercase;letter-spacing:1px}
  .box h2{font-size:26px;font-weight:800;color:var(--ink);margin:6px 0 4px}
  .box .sc{font-size:14px;color:var(--muted);margin-bottom:26px}
  .box label{display:block;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin:16px 0 6px}
  .ipwrap{position:relative}
  .ipwrap .material-symbols-rounded{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:20px}
  .box input{width:100%;padding:13px 14px 13px 42px;font-size:15px;border:1.5px solid var(--line);border-radius:11px;background:#fafcfc;color:var(--ink)}
  .box input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(0,180,166,.15)}
  .box .go{margin-top:24px;width:100%;padding:14px;border:none;border-radius:12px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-size:16px;font-weight:800;cursor:pointer;box-shadow:0 6px 18px rgba(0,180,166,.32);display:flex;align-items:center;justify-content:center;gap:8px}
  .box .go:hover{filter:brightness(1.03)}
  .err{margin-top:16px;background:#fdecec;color:var(--danger);padding:11px 14px;border-radius:10px;font-size:13px;display:flex;align-items:center;gap:8px}
  .foot{margin-top:26px;font-size:12px;color:var(--muted);text-align:center}
  @media(max-width:860px){ .brandside{display:none} .formside{flex:1} }
</style>
</head>
<body>
  <div class="split">
    <div class="brandside">
      <div class="logo">INTERWEG</div>
      <?php if ($brandEmpresa): ?><div style="position:relative;margin-top:10px;font-size:13px;font-weight:700;letter-spacing:.5px;opacity:.95;background:rgba(255,255,255,.15);display:inline-block;padding:5px 12px;border-radius:20px"><?= htmlspecialchars($brandEmpresa, ENT_QUOTES) ?></div><?php endif; ?>
      <h1>Portal do Cliente</h1>
      <p>Solicite inclusões e exclusões de beneficiários e acompanhe o andamento direto com o nosso time, sem e-mail e sem planilha.</p>
      <div class="feats">
        <div class="feat"><div class="ic"><span class="material-symbols-rounded">bolt</span></div>Inclusão e exclusão em poucos cliques</div>
        <div class="feat"><div class="ic"><span class="material-symbols-rounded">timeline</span></div>Acompanhe o status de cada solicitação</div>
        <div class="feat"><div class="ic"><span class="material-symbols-rounded">shield</span></div>Ambiente exclusivo e seguro da sua empresa</div>
      </div>
    </div>
    <div class="formside">
      <form class="box" method="post" action="">
        <div class="hi">Interweg Benefícios</div>
        <h2>Bem-vindo</h2>
        <div class="sc">Entre com o acesso fornecido pela Interweg.</div>
        <input type="hidden" name="portal_login" value="1">
        <label>Login</label>
        <div class="ipwrap"><span class="material-symbols-rounded">person</span>
          <input type="text" name="login" placeholder="seu login" autofocus required></div>
        <label>Senha</label>
        <div class="ipwrap"><span class="material-symbols-rounded">lock</span>
          <input type="password" name="senha" placeholder="••••••••" required></div>
        <?php if ($erro): ?><div class="err"><span class="material-symbols-rounded" style="font-size:18px">error</span><?= htmlspecialchars($erro, ENT_QUOTES) ?></div><?php endif; ?>
        <button class="go" type="submit"><span class="material-symbols-rounded">login</span> Entrar</button>
        <div class="foot">Problemas para acessar? Fale com seu contato na Interweg.</div>
      </form>
    </div>
  </div>
</body>
</html>
<?php
    exit;
endif;

$grupo = trim($_SESSION['user_grupo'] ?? '');
$nome  = trim($_SESSION['user_nome'] ?? 'Cliente');

// Empresas/subestipulantes do grupo (para o seletor). Combina cadastro + demandas.
$empresas = [];
try {
    $st = db()->prepare("SELECT DISTINCT nome FROM empresas WHERE grupo = ? AND nome <> '' ORDER BY nome");
    $st->execute([$grupo]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) $empresas[$n] = true;
} catch (Throwable $e) {}
try {
    $st = db()->prepare("SELECT DISTINCT empresa FROM demandas WHERE grupo = ? AND empresa <> '' ORDER BY empresa");
    $st->execute([$grupo]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) $empresas[$n] = true;
} catch (Throwable $e) {}
$empresas = array_keys($empresas);
sort($empresas);

// Seguradoras do grupo (cadastro + demandas) para o seletor.
$seguradoras = [];
try {
    $st = db()->prepare("SELECT DISTINCT seguradora FROM empresas WHERE grupo = ? AND seguradora <> '' ORDER BY seguradora");
    $st->execute([$grupo]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) $seguradoras[$n] = true;
} catch (Throwable $e) {}
try {
    $st = db()->prepare("SELECT DISTINCT seguradora FROM demandas WHERE grupo = ? AND seguradora <> '' ORDER BY seguradora");
    $st->execute([$grupo]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) $seguradoras[$n] = true;
} catch (Throwable $e) {}
$seguradoras = array_keys($seguradoras);
sort($seguradoras);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Portal do Cliente — Interweg Benefícios</title>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
<style>
  :root{--accent:#00b4a6;--accent2:#009e92;--ink:#14313a;--muted:#6b7c86;--line:#e3ebee;--bg:#eef2f4;--card:#fff;--danger:#c0392b}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',Tahoma,sans-serif;background:var(--bg);color:var(--ink);min-height:100vh}
  .material-symbols-rounded{vertical-align:middle;line-height:1}
  .topbar{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;padding:16px 26px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 4px 16px rgba(0,180,166,.25)}
  .topbar .brand{font-size:19px;font-weight:800;letter-spacing:.3px}
  .topbar .brand small{display:block;font-size:11px;font-weight:600;opacity:.9}
  .topbar a.sair{color:#fff;text-decoration:none;font-size:13px;font-weight:700;background:rgba(255,255,255,.18);padding:8px 14px;border-radius:9px}
  .wrap{max-width:none;width:100%;margin:26px auto;padding:0 40px 50px;box-sizing:border-box}
  .grid{display:grid;grid-template-columns:1fr 1.2fr;gap:20px}
  @media(max-width:860px){.grid{grid-template-columns:1fr}}
  .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px;box-shadow:0 4px 18px rgba(0,0,0,.05)}
  .card h2{font-size:16px;color:var(--accent2);font-weight:800;margin-bottom:4px;display:flex;align-items:center;gap:8px}
  .card .sub{font-size:12.5px;color:var(--muted);margin-bottom:16px}
  label{display:block;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.3px;margin:12px 0 5px}
  input,select,textarea{width:100%;padding:10px 12px;font-size:14px;border:1.5px solid var(--line);border-radius:9px;font-family:inherit;background:#fafcfc;color:var(--ink)}
  input:focus,select:focus,textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(0,180,166,.15)}
  textarea{min-height:70px;resize:vertical}
  .seg{display:flex;gap:10px;margin-top:4px}
  .seg label{flex:1;margin:0;cursor:pointer}
  .seg input{display:none}
  .seg span{display:block;text-align:center;padding:11px;border:1.5px solid var(--line);border-radius:10px;font-weight:700;font-size:13.5px;color:var(--muted);text-transform:none;letter-spacing:0}
  .seg input:checked + span{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border-color:transparent}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .btn{margin-top:18px;width:100%;padding:13px;border:none;border-radius:11px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-size:15px;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(0,180,166,.3)}
  .btn:disabled{opacity:.6;cursor:default}
  .msg{margin-top:12px;padding:11px 14px;border-radius:10px;font-size:13px;display:none}
  .msg.ok{background:#e7f8f4;color:#0e6b60;display:block}
  .msg.err{background:#fdecec;color:var(--danger);display:block}
  table{width:100%;border-collapse:collapse;font-size:12.5px;margin-top:4px}
  th,td{padding:9px 8px;text-align:left;border-bottom:1px solid var(--line)}
  th{font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted)}
  .bdg{padding:3px 9px;border-radius:11px;font-size:10.5px;font-weight:800;white-space:nowrap}
  .b-triagem{background:#fff3cd;color:#7a5d00}
  .b-analise{background:#cfe2ff;color:#0d4d8c}
  .b-pend{background:#ffe0b2;color:#8a4a00}
  .b-conc{background:#d3f2d8;color:#256029}
  .b-def{background:#e9ecef;color:#555}
  .tp{font-weight:800}.tp.inc{color:#1f9b48}.tp.exc{color:#c0392b}
  .empty{color:var(--muted);font-size:13px;padding:26px 0;text-align:center}
</style>
</head>
<body>
  <div class="topbar">
    <div class="brand">INTERWEG · Portal do Cliente<small><?= htmlspecialchars($grupo ?: 'Grupo', ENT_QUOTES) ?> · <?= htmlspecialchars($nome, ENT_QUOTES) ?></small></div>
    <a class="sair" href="logout.php"><span class="material-symbols-rounded" style="font-size:16px">logout</span> Sair</a>
  </div>

  <div class="wrap">
    <div class="grid">
      <!-- NOVA MOVIMENTAÇÃO -->
      <div class="card">
        <h2><span class="material-symbols-rounded">add_circle</span> Nova movimentação</h2>
        <div class="sub">Solicite a inclusão ou exclusão de um beneficiário. Nossa equipe recebe e dá andamento.</div>
        <form id="frm" onsubmit="return dmEnviar(event)">
          <label>Tipo de movimentação</label>
          <div class="seg">
            <label><input type="radio" name="tipo_demanda" value="Inclusão" checked><span>➕ Inclusão</span></label>
            <label><input type="radio" name="tipo_demanda" value="Exclusão"><span>➖ Exclusão</span></label>
          </div>

          <label>Empresa / Subestipulante</label>
          <input list="lst-emp" name="empresa" placeholder="Selecione ou digite a empresa" required autocomplete="off">
          <datalist id="lst-emp"><?php foreach ($empresas as $e): ?><option value="<?= htmlspecialchars($e, ENT_QUOTES) ?>"></option><?php endforeach; ?></datalist>

          <div class="row2">
            <div>
              <label>Seguradora</label>
              <input list="lst-seg" name="seguradora" placeholder="Selecione ou digite a seguradora" autocomplete="off">
              <datalist id="lst-seg"><?php foreach ($seguradoras as $s): ?><option value="<?= htmlspecialchars($s, ENT_QUOTES) ?>"></option><?php endforeach; ?></datalist>
            </div>
            <div>
              <label>Ramo</label>
              <select name="ramo">
                <option>Saúde</option>
                <option>Odonto</option>
                <option>Saúde/Odonto</option>
              </select>
            </div>
          </div>

          <div class="row2">
            <div><label>Nome do beneficiário</label><input name="beneficiario_nome" placeholder="Nome completo" required></div>
            <div><label>CPF</label><input name="beneficiario_cpf" placeholder="000.000.000-00" inputmode="numeric"></div>
          </div>

          <label>Observação</label>
          <textarea name="observacao" placeholder="Dados do plano, dependentes, data de vigência desejada, etc."></textarea>

          <label>Anexo (opcional)</label>
          <input type="file" name="anexo" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv">

          <button class="btn" id="btn" type="submit"><span class="material-symbols-rounded" style="font-size:18px">send</span> Enviar solicitação</button>
          <div class="msg" id="msg"></div>
        </form>
      </div>

      <!-- MINHAS MOVIMENTAÇÕES -->
      <div class="card">
        <h2><span class="material-symbols-rounded">history</span> Minhas movimentações</h2>
        <div class="sub">Acompanhe o status das solicitações que você enviou.</div>
        <div id="lista"><div class="empty">Carregando…</div></div>
      </div>
    </div>
  </div>

<script>
  const cpfMask = v => v.replace(/\D/g,'').slice(0,11).replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');
  document.querySelector('[name=beneficiario_cpf]').addEventListener('input', e=>{ e.target.value=cpfMask(e.target.value); });
  const esc = s => { const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; };

  function badge(s){ const n=(s||'').toLowerCase();
    let c='b-def'; if(n.includes('triagem'))c='b-triagem'; else if(n.includes('anális')||n.includes('analis'))c='b-analise';
    else if(n.includes('pend'))c='b-pend'; else if(n.includes('conclu'))c='b-conc';
    return '<span class="bdg '+c+'">'+esc(s||'—')+'</span>'; }

  async function dmEnviar(ev){
    ev.preventDefault();
    const btn=document.getElementById('btn'), msg=document.getElementById('msg');
    msg.className='msg'; msg.textContent='';
    btn.disabled=true; btn.innerHTML='Enviando…';
    try{
      const fd=new FormData(document.getElementById('frm'));
      const r=await fetch('api.php?action=cliente_criar_demanda',{method:'POST',body:fd}).then(x=>x.json());
      if(r.ok){ msg.className='msg ok'; msg.textContent='Solicitação enviada! Protocolo '+(r.protocolo||r.id)+'.';
        document.getElementById('frm').reset(); dmLista(); }
      else if((r.msg||'').toLowerCase().includes('acesso negado')){
        // Sessão do portal expirou por inatividade → volta para o login.
        msg.className='msg err'; msg.textContent='Sua sessão expirou. Redirecionando para o login…';
        setTimeout(()=>{ location.reload(); }, 1600);
      }
      else { msg.className='msg err'; msg.textContent=r.msg||'Não foi possível enviar.'; }
    }catch(e){ msg.className='msg err'; msg.textContent='Erro de conexão.'; }
    btn.disabled=false; btn.innerHTML='<span class="material-symbols-rounded" style="font-size:18px">send</span> Enviar solicitação';
    return false;
  }

  async function dmLista(){
    const box=document.getElementById('lista');
    try{
      const r=await fetch('api.php?action=cliente_listar_demandas').then(x=>x.json());
      const rows=(r&&r.data)||[];
      if(!rows.length){ box.innerHTML='<div class="empty">Você ainda não enviou nenhuma movimentação.</div>'; return; }
      let h='<table><thead><tr><th>Protocolo</th><th>Tipo</th><th>Beneficiário</th><th>Empresa</th><th>Status</th><th>Enviado</th></tr></thead><tbody>';
      rows.forEach(d=>{ const inc=(d.tipo_demanda||'').toLowerCase().includes('inclus');
        const dt=(d.recebimento||d.criado_em||'').slice(0,10).split('-').reverse().join('/');
        h+='<tr><td>'+esc(d.protocolo||('#'+d.id))+'</td>'
          +'<td class="tp '+(inc?'inc':'exc')+'">'+esc(d.tipo_demanda||'')+'</td>'
          +'<td>'+esc(d.dados_beneficiario||'')+(d.anexo_url?' <a href="'+esc(d.anexo_url)+'" target="_blank" title="anexo"><span class="material-symbols-rounded" style="font-size:15px;color:var(--accent2)">attach_file</span></a>':'')+'</td>'
          +'<td>'+esc(d.empresa||'')+'</td>'
          +'<td>'+badge(d.situacao)+'</td>'
          +'<td>'+esc(dt)+'</td></tr>';
      });
      h+='</tbody></table>';
      box.innerHTML=h;
    }catch(e){ box.innerHTML='<div class="empty">Não consegui carregar suas movimentações.</div>'; }
  }
  dmLista();
</script>
</body>
</html>
