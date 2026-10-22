<?php
require_once 'config.php';
require_once __DIR__ . '/mfa.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/senha_politica.php';

// Página inicial por perfil (evita depender do /index e leva cada tipo pra sua área).
function iw_landing($tipo) {
    $t = strtolower(trim((string)$tipo));
    if ($t === 'financeiro')        return 'faturamento.php';
    if ($t === 'comercial_cotacao') return 'controle_cotacao.php';
    if ($t === 'novos_negocios')    return 'formcot.php';
    if ($t === 'enfermeira' || $t === 'enfermagem') return 'demandas_assistenciais.php';
    if ($t === 'cliente')           return 'portal.php';
    if ($t === 'renovacao')         return 'renovacao.php';
    return 'demandas.php';
}

if (!empty($_SESSION['user_id'])) { header('Location: ' . iw_landing($_SESSION['user_tipo'] ?? '')); exit; }

// Migração idempotente: coluna que obriga a trocar a senha no 1º acesso.
foreach (['analistas','usuarios'] as $_t) {
    try { db()->exec("ALTER TABLE $_t ADD COLUMN must_change_pw TINYINT NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
}
// Coluna de grupo (usada por logins do tipo 'cliente' — escopo do portal do cliente).
try { db()->exec("ALTER TABLE analistas ADD COLUMN grupo VARCHAR(120) NULL"); } catch (Throwable $e) {}
// Migração idempotente das colunas de MFA.
mfa_migrar();
// Migração idempotente da política de senha (coluna senha_alterada_em).
senha_migrar();

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if ($login && $senha) {
        $src = 'analistas';
        // 1. Verifica na tabela analistas (login + senha)
        $stmt = db()->prepare("SELECT id, nome, tipo, senha, email, grupo FROM analistas WHERE login = ? AND ativo = 1 LIMIT 1");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        // 2. Fallback: verifica na tabela usuarios (email + senha) para admin legado
        if (!$user) {
            $stmt2 = db()->prepare("SELECT id, nome, tipo, senha, email FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
            $stmt2->execute([$login]);
            $user = $stmt2->fetch();
            $src = 'usuarios';
        }

        if ($user && password_verify($senha, $user['senha'])) {
            session_regenerate_id(true);

            // E-mail REAL: usa a coluna email; se vazia, cai pro login.
            $emailReal = trim($user['email'] ?? '');
            $emailUse  = ($emailReal !== '' ? $emailReal : $login);

            // Flag de troca obrigatória — lida de forma isolada (nunca quebra o login).
            $must = 0;
            try {
                $qm = db()->prepare("SELECT COALESCE(must_change_pw,0) FROM {$src} WHERE id = ? LIMIT 1");
                $qm->execute([$user['id']]);
                $must = intval($qm->fetchColumn());
            } catch (Throwable $e) { $must = 0; }

            // Política de senha: se a senha estiver vencida (30 dias), força a troca.
            try { if (senha_expirada($src, $user['id'])) $must = 1; } catch (Throwable $e) {}

            // ── MFA obrigatório (admin/head/coordenador): NÃO concede sessão ainda. ──
            // Guarda o estado pendente e manda pro fluxo de 2º fator. A sessão completa
            // (user_id) só é criada depois que o código for validado.
            $exigeMfa = mfa_obrigatorio($user['tipo'], $login);
            // Dispositivo confiável (confirmou 2FA nos últimos 14 dias)? Pula o código.
            if ($exigeMfa) {
                try { if (mfa_confianca_valida($src, $user['id'])) $exigeMfa = false; } catch (Throwable $e) {}
            }
            if ($exigeMfa) {
                $mfa = mfa_status_usuario($src, $user['id']);
                $_SESSION['mfa_pending'] = [
                    'uid'   => $user['id'],
                    'src'   => $src,
                    'tipo'  => $user['tipo'],
                    'nome'  => $user['nome'],
                    'login' => $login,
                    'email' => $emailUse,
                    'must'  => ($must === 1) ? 1 : 0,
                    'ts'    => time(),
                ];
                if ($mfa['enabled'] === 1 && $mfa['secret'] !== '') {
                    header('Location: mfa_verificar.php'); exit;   // já cadastrado
                }
                header('Location: mfa_setup.php'); exit;           // 1º cadastro
            }

            // ── Perfis sem MFA nesta fase: fluxo atual (sessão completa direta). ──
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_nome']  = $user['nome'];
            $_SESSION['user_tipo']  = $user['tipo'];
            $_SESSION['user_login'] = $login;
            $_SESSION['user_src']   = $src;
            $_SESSION['user_email'] = $emailUse;
            $_SESSION['user_grupo'] = $user['grupo'] ?? null;
            $_SESSION['must_change_pw'] = ($must === 1) ? 1 : 0;
            aud_log('login', 'sessao', $user['id'], null, 'login');
            if ($_SESSION['must_change_pw']) { header('Location: trocar_senha.php'); exit; }
            header('Location: ' . iw_landing($user['tipo'])); exit;
        } else {
            aud_log('login_falha', 'sessao', null, ['login' => $login], 'login');
            $erro = 'Login ou senha incorretos.';
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Login - Controle de Demandas | Interweg</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0..1,-50..200"/>
<style>.material-symbols-rounded{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;vertical-align:middle;font-size:20px;line-height:1}</style>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  :root, body.dark{
    --bg:#0d1117;--bg-card:#161b22;--border:#30363d;
    --accent:#00c9a7;--accent2:#00a589;
    --text:#e6edf3;--muted:#8b949e;--input-bg:#0d1117;--placeholder:#484f58;
    --card-shadow:0 24px 64px rgba(0,0,0,.5);
    --glow1:rgba(0,201,167,.08);--glow2:rgba(0,201,167,.05);
  }
  body.light{
    --bg:#f0f2f5;--bg-card:#ffffff;--border:#d0d7de;
    --accent:#00b4a6;--accent2:#009e92;
    --text:#1f2328;--muted:#656d76;--input-bg:#f6f8fa;--placeholder:#9ca3af;
    --card-shadow:0 24px 64px rgba(0,0,0,.1);
    --glow1:rgba(0,180,166,.06);--glow2:rgba(0,180,166,.04);
  }
  body{
    font-family:'Segoe UI',Tahoma,sans-serif;
    background:var(--bg);
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
    overflow:hidden;
    transition:background .3s;
  }
  body::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(ellipse at 20% 50%, var(--glow1) 0%, transparent 60%),
               radial-gradient(ellipse at 80% 20%, var(--glow2) 0%, transparent 50%);
    pointer-events:none;
  }
  .wrap{
    display:flex;align-items:center;justify-content:center;
    gap:80px;width:100%;max-width:900px;padding:20px;position:relative;z-index:1;
  }
  .brand{flex:1;max-width:340px;color:var(--text)}
  .brand-logo{display:flex;align-items:center;gap:12px;margin-bottom:32px}
  .brand h1{font-size:30px;font-weight:700;line-height:1.3;margin-bottom:14px;color:var(--text)}
  .brand h1 em{color:var(--accent);font-style:normal}
  .brand p{font-size:14px;color:var(--muted);line-height:1.6}
  .brand-features{margin-top:28px;display:flex;flex-direction:column;gap:10px}
  .feature{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--muted)}

  .card{
    background:var(--bg-card);
    border:1px solid var(--border);
    border-radius:18px;
    padding:40px 36px;
    width:380px;
    box-shadow:var(--card-shadow);
    transition:background .3s,border-color .3s;
  }
  .card-title{font-size:20px;font-weight:700;color:var(--text);margin-bottom:6px}
  .card-sub{font-size:13px;color:var(--muted);margin-bottom:28px}
  .form-group{margin-bottom:18px}
  label{display:block;font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px}
  input{
    width:100%;background:var(--input-bg);border:1.5px solid var(--border);
    border-radius:10px;padding:12px 16px;font-size:14px;color:var(--text);
    outline:none;font-family:inherit;transition:border .2s,background .3s;
  }
  input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(0,201,167,.12)}
  input::placeholder{color:var(--placeholder)}
  .btn{
    width:100%;background:linear-gradient(135deg,var(--accent),var(--accent2));
    color:#fff;border:none;border-radius:10px;padding:14px;
    font-size:15px;font-weight:700;cursor:pointer;
    transition:all .2s;margin-top:6px;letter-spacing:.2px;
    box-shadow:0 6px 20px rgba(0,201,167,.3);
  }
  .btn:hover{transform:translateY(-1px);box-shadow:0 10px 28px rgba(0,201,167,.4)}
  .btn:active{transform:translateY(0)}
  .erro{
    background:rgba(198,40,40,.12);border:1px solid rgba(198,40,40,.3);
    color:#ff7070;border-radius:10px;padding:12px 16px;
    font-size:13px;margin-bottom:18px;text-align:center;
  }
  .footer{text-align:center;margin-top:22px;font-size:12px;color:var(--placeholder)}

  /* Toggle de tema */
  .theme-toggle{
    position:fixed;top:20px;right:20px;z-index:10;
    background:var(--bg-card);border:1px solid var(--border);
    border-radius:10px;padding:6px;display:flex;gap:4px;
    box-shadow:0 2px 10px rgba(0,0,0,.15);
  }
  .theme-toggle button{
    background:transparent;border:1px solid transparent;border-radius:7px;
    padding:6px 10px;cursor:pointer;font-size:12px;font-weight:600;
    color:var(--muted);display:flex;align-items:center;gap:4px;
    font-family:inherit;transition:all .15s;
  }
  .theme-toggle button.active{background:var(--bg);border-color:var(--accent);color:var(--accent)}
  .theme-toggle button:hover:not(.active){color:var(--text)}

  @media(max-width:700px){
    .brand{display:none}
    .card{width:100%;max-width:380px}
  }
</style>
</head>
<body>
<!-- Toggle Tema -->
<div class="theme-toggle">
  <button onclick="setTheme('light')" id="tl"><span class="material-symbols-rounded" style="font-size:15px">light_mode</span> Claro</button>
  <button onclick="setTheme('dark')" id="td"><span class="material-symbols-rounded" style="font-size:15px">dark_mode</span> Escuro</button>
</div>
<div class="wrap">
  <!-- Branding -->
  <div class="brand">
    <div class="brand-logo">
      <img src="logo_interweg.webp" alt="Interweg Seguros" style="height:70px" onerror="this.src='logo_interweg.png'">
    </div>
    <h1>Controle de<br/><em>Demandas</em><br/>de Benefícios</h1>
    <p>Sistema interno de gestão de solicitações de benefícios da equipe Interweg.</p>
    <div class="brand-features">
      <div class="feature"><span class="material-symbols-rounded" style="color:#00c9a7;font-size:18px">task_alt</span>Gestão completa de demandas</div>
      <div class="feature"><span class="material-symbols-rounded" style="color:#00c9a7;font-size:18px">dashboard</span>Dashboard por analista</div>
      <div class="feature"><span class="material-symbols-rounded" style="color:#00c9a7;font-size:18px">notifications_active</span>Alertas de próxima ação</div>
      <div class="feature"><span class="material-symbols-rounded" style="color:#00c9a7;font-size:18px">download</span>Exportação em XML</div>
    </div>
  </div>

  <!-- Login Card -->
  <div class="card">
    <div class="card-title">Bem-vindo(a)</div>
    <div class="card-sub">Entre com sua conta para continuar</div>

    <?php if ($erro): ?>
    <div class="erro">⚠ <?= e($erro) ?></div>
    <?php elseif (!empty($_GET['timeout'])): ?>
    <div class="erro">⚠ Sua sessão expirou por inatividade. Faça login novamente.</div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Login</label>
        <input type="text" name="login" placeholder="seu login" required autocomplete="username"
               value="<?= e($_POST['login'] ?? '') ?>"/>
      </div>
      <div class="form-group">
        <label>Senha</label>
        <input type="password" name="senha" placeholder="••••••••" required/>
      </div>
      <button class="btn" type="submit">Entrar →</button>
    </form>
    <div style="text-align:center;margin-top:14px">
      <a href="esqueci_senha.php" style="color:#00bfb3;font-size:13px;text-decoration:none;font-weight:600">Esqueci minha senha</a>
    </div>

    <div class="footer">Interweg Benefícios &copy; <?= date('Y') ?></div>
  </div>
</div>
<script>
function setTheme(t){
  document.body.className=t;
  localStorage.setItem('iw-theme',t);
  document.getElementById('tl').className=t==='light'?'active':'';
  document.getElementById('td').className=t==='dark'?'active':'';
}
(function(){const t=localStorage.getItem('iw-theme')||'dark';setTheme(t)})();
</script>
</body>
</html>

