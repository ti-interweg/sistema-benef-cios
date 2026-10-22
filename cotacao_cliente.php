<?php
// =========================================================
// cotacao_cliente.php
// Página pública para o cliente preencher a cotação enviada
// pelo analista. Acessada via link com token único.
// =========================================================

require_once __DIR__ . '/config.php';

$token = trim($_GET['t'] ?? '');
if (!$token || !preg_match('/^[a-zA-Z0-9]{16,32}$/', $token)) {
    http_response_code(400);
    die('Link inválido.');
}

$stmt = db()->prepare("SELECT * FROM cotacoes_envio WHERE token = ?");
$stmt->execute([$token]);
$cot = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cot) {
    http_response_code(404);
    die('Cotação não encontrada ou link expirado.');
}

$jaRespondida = ($cot['status'] === 'respondida');
$template = json_decode($cot['template_json'] ?? '{}', true) ?: [];
$resposta = $jaRespondida ? (json_decode($cot['resposta_json'] ?? '{}', true) ?: []) : [];
$tipoNome = $cot['tipo_nome'] ?? 'Cotação';

// Helper pra pegar valor já respondido (se houver)
function v($state, $secao, $idx, $campo='valor') {
    $arr = $state[$secao] ?? [];
    if (!isset($arr[$idx])) return '';
    return $arr[$idx][$campo] ?? '';
}

$dataAtual = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Cotação Interweg — <?= htmlspecialchars($tipoNome) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --teal:#00bfb3;
    --teal-dark:#006677;
    --gray:#808285;
    --gray-light:#f5f7f8;
    --bg:#ffffff;
    --text:#1a2226;
    --danger:#d43d3d;
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0;background:var(--gray-light);font-family:'Montserrat','Gilroy','Segoe UI',sans-serif;color:var(--text)}
  .doc{max-width:880px;margin:0 auto;background:var(--bg);box-shadow:0 4px 24px rgba(0,0,0,.08)}
  header.fc-head{background:linear-gradient(135deg, var(--teal-dark) 0%, var(--teal) 100%);color:#fff;padding:36px 48px}
  header.fc-head .top{display:flex;align-items:center;justify-content:space-between;gap:20px}
  header.fc-head .logo{display:flex;align-items:center}
  header.fc-head .logo img{height:64px;width:auto;display:block}
  header.fc-head .logo .fallback{display:none;font-family:'Gilroy','Montserrat',sans-serif;font-weight:800;letter-spacing:1px;font-size:28px;color:#fff}
  header.fc-head .logo .fallback span.acc{color:var(--teal);font-weight:800}
  header.fc-head .meta{font-size:11px;text-transform:uppercase;letter-spacing:2px;opacity:.85;text-align:right}
  header.fc-head h1{font-family:'Gilroy','Montserrat',sans-serif;margin:24px 0 6px;font-size:30px;font-weight:700;letter-spacing:-.3px}
  header.fc-head .sub{font-size:13px;opacity:.9;font-weight:400}
  main{padding:36px 48px 48px}
  .fc-section{margin-bottom:36px}
  .fc-section h2{font-family:'Gilroy','Montserrat',sans-serif;font-weight:700;font-size:17px;color:var(--teal-dark);text-transform:uppercase;letter-spacing:1.2px;margin:0 0 14px;display:flex;align-items:center;gap:10px;padding-bottom:8px;border-bottom:2px solid var(--teal)}
  .fc-section h2 .dot{width:10px;height:10px;border-radius:50%;background:var(--teal);box-shadow:0 0 0 3px rgba(0,191,179,.18)}
  .fc-row{display:grid;grid-template-columns:240px 1fr;gap:18px;padding:11px 0;border-bottom:1px solid #ececec;align-items:center}
  .fc-row:last-child{border-bottom:none}
  .fc-lbl{font-size:12.5px;font-weight:600;color:var(--gray);text-transform:uppercase;letter-spacing:.4px}
  .fc-input,.fc-textarea,.fc-select{width:100%;padding:10px 12px;border:1.5px solid #dde1e3;border-radius:8px;font-family:inherit;font-size:14px;color:var(--text);background:#fff;outline:none;transition:border-color .15s}
  .fc-input:focus,.fc-textarea:focus,.fc-select:focus{border-color:var(--teal)}
  .fc-textarea{resize:vertical;min-height:60px;line-height:1.4}
  .fc-tablewrap{overflow-x:auto;border:1px solid #e8eaeb;border-radius:6px}
  table.fc-table{width:100%;border-collapse:collapse;font-size:12px}
  table.fc-table th{background:var(--teal-dark);color:#fff;text-align:left;padding:9px 10px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;font-size:10.5px}
  table.fc-table td{padding:0;border-bottom:1px solid #eef0f1}
  table.fc-table input{width:100%;padding:8px 10px;border:none;background:transparent;font-family:inherit;font-size:12px;color:var(--text);outline:none}
  table.fc-table input:focus{background:#f0fbfa}
  table.fc-table tr:nth-child(even) td{background:#fafbfb}
  .add-row-btn{margin-top:10px;background:#fff;border:1.5px dashed var(--teal);color:var(--teal-dark);padding:9px 16px;border-radius:8px;cursor:pointer;font-family:inherit;font-weight:600;font-size:13px;display:inline-flex;align-items:center;gap:6px}
  .add-row-btn:hover{background:var(--teal);color:#fff;border-style:solid}
  .submit-row{margin-top:32px;padding-top:24px;border-top:2px solid #e8eaeb;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
  .submit-row .left{font-size:12.5px;color:var(--gray)}
  .submit-btn{background:var(--teal);color:#fff;border:none;padding:13px 28px;border-radius:10px;cursor:pointer;font-family:inherit;font-weight:700;font-size:14.5px;letter-spacing:.4px;text-transform:uppercase;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(0,191,179,.35)}
  .submit-btn:hover{background:var(--teal-dark);box-shadow:0 6px 18px rgba(0,102,119,.4)}
  .submit-btn:disabled{opacity:.6;cursor:wait}
  footer.fc-foot{margin-top:8px;padding:24px 48px;background:var(--text);color:#fff}
  footer.fc-foot .row{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
  footer.fc-foot .brand{font-family:'Gilroy','Montserrat',sans-serif;font-weight:700;letter-spacing:1px;font-size:14px;color:var(--teal)}
  footer.fc-foot .info{font-size:11px;opacity:.75;line-height:1.6}
  .alert{padding:14px 18px;border-radius:10px;font-size:13.5px;margin-bottom:24px;font-weight:500}
  .alert-success{background:#e6f9f7;color:var(--teal-dark);border-left:4px solid var(--teal)}
  .alert-info{background:#eff6f7;color:var(--teal-dark);border-left:4px solid var(--teal-dark)}
  @media (max-width:600px){
    header.fc-head, main{padding-left:24px;padding-right:24px}
    header.fc-head h1{font-size:22px}
    .fc-row{grid-template-columns:1fr;gap:6px}
    .fc-lbl{font-size:11.5px}
  }
</style>
</head>
<body>
<article class="doc">
  <header class="fc-head">
    <div class="top">
      <div class="logo">
        <img src="logo_interweg.webp" alt="Interweg Seguros" onerror="this.src='logo_interweg.png';this.onerror=function(){this.style.display='none';this.nextElementSibling.style.display='block'}"/>
        <div class="fallback">INTER<span class="acc">WEG</span></div>
      </div>
      <div class="meta">
        Cotação · <?= htmlspecialchars($dataAtual) ?>
      </div>
    </div>
    <h1>Formulário de Cotação</h1>
    <div class="sub"><?= htmlspecialchars($tipoNome) ?></div>
  </header>

  <main>
    <?php if ($jaRespondida): ?>
      <div class="alert alert-success">
        ✓ Esta cotação já foi respondida em <?= date('d/m/Y H:i', strtotime($cot['respondido_em'])) ?>. Os dados abaixo são apenas para consulta.
      </div>
    <?php else: ?>
      <div class="alert alert-info">
        Preencha os campos abaixo e clique em <strong>Enviar Cotação</strong> ao final. Os dados ficam salvos em segurança e voltam direto pro analista responsável.
      </div>
    <?php endif; ?>

    <form id="cot-form">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"/>

      <?php
        $secoes = [
          ['key'=>'dados',       'titulo'=>'Dados da Empresa'],
          ['key'=>'plano_atual', 'titulo'=>'Plano Atual (Vigente)', 'sub'=>'O que você tem hoje'],
          ['key'=>'contrato',    'titulo'=>'Informações Contratuais', 'sub'=>'Plano cotado'],
          ['key'=>'coberturas',  'titulo'=>'Coberturas e Assistências'],
        ];
        foreach ($secoes as $s):
          $campos = $template[$s['key']] ?? [];
          if (empty($campos)) continue;
      ?>
        <section class="fc-section">
          <h2><span class="dot"></span><?= htmlspecialchars($s['titulo']) ?><?php if(!empty($s['sub'])): ?> <span style="font-size:13px;color:#888;font-weight:400">— <?= htmlspecialchars($s['sub']) ?></span><?php endif; ?></h2>
          <?php foreach ($campos as $idx => $f):
            $label = htmlspecialchars($f['label'] ?? '');
            $tipo  = $f['tipo'] ?? 'text';
            $opcoes= $f['opcoes'] ?? [];
            // Para plano_atual, usa o valor pré-preenchido pelo analista se ainda não respondeu
            $valExist = $jaRespondida
                ? v($resposta, $s['key'], $idx)
                : ($s['key'] === 'plano_atual' ? ($f['valor'] ?? '') : '');
            $name = "f[{$s['key']}][{$idx}]";
            $disabled = $jaRespondida ? 'disabled' : '';
          ?>
            <div class="fc-row">
              <div class="fc-lbl"><?= $label ?></div>
              <div>
              <?php if ($tipo === 'select'): ?>
                <select name="<?= $name ?>" class="fc-select" <?= $disabled ?>>
                  <option value="">Selecione...</option>
                  <?php foreach ($opcoes as $op): ?>
                    <option value="<?= htmlspecialchars($op) ?>" <?= $valExist === $op ? 'selected' : '' ?>><?= htmlspecialchars($op) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($tipo === 'textarea'): ?>
                <textarea name="<?= $name ?>" class="fc-textarea" rows="2" <?= $disabled ?>><?= htmlspecialchars($valExist) ?></textarea>
              <?php else: ?>
                <input type="<?= htmlspecialchars($tipo) ?>" name="<?= $name ?>" class="fc-input" value="<?= htmlspecialchars($valExist) ?>" <?= $disabled ?>/>
              <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>

      <?php $vidasCols = $template['vidas_cols'] ?? []; if (!empty($vidasCols)): ?>
        <section class="fc-section">
          <h2><span class="dot"></span>Base de Vidas</h2>
          <div class="fc-tablewrap">
            <table class="fc-table" id="cot-vidas">
              <thead><tr>
                <?php foreach ($vidasCols as $c): ?>
                  <th><?= htmlspecialchars($c) ?></th>
                <?php endforeach; ?>
              </tr></thead>
              <tbody>
                <?php
                  $vidasResp = $jaRespondida ? ($resposta['vidas_rows'] ?? []) : [];
                  $rows = !empty($vidasResp) ? $vidasResp : [array_fill(0, count($vidasCols), '')];
                  foreach ($rows as $ri => $row):
                ?>
                  <tr>
                    <?php foreach ($vidasCols as $ci => $col): ?>
                      <td><input type="text" name="vidas[<?= $ri ?>][<?= $ci ?>]" value="<?= htmlspecialchars($row[$ci] ?? '') ?>" <?= $jaRespondida ? 'disabled' : '' ?>/></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (!$jaRespondida): ?>
            <button type="button" class="add-row-btn" onclick="addVida()"><span style="font-size:16px">+</span> Adicionar vida</button>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if (!$jaRespondida): ?>
        <div class="fc-section">
          <h2><span class="dot"></span>Seus dados (para retorno)</h2>
          <div class="fc-row"><div class="fc-lbl">Seu nome *</div><div><input type="text" name="cliente_nome" class="fc-input" required/></div></div>
          <div class="fc-row"><div class="fc-lbl">Seu e-mail *</div><div><input type="email" name="cliente_email" class="fc-input" required/></div></div>
        </div>

        <div class="submit-row">
          <div class="left">Ao enviar, você confirma que as informações são verdadeiras.</div>
          <button type="submit" class="submit-btn" id="cot-submit">Enviar Cotação ➜</button>
        </div>
      <?php endif; ?>
    </form>
  </main>

  <footer class="fc-foot">
    <div class="row">
      <div class="brand">INTERWEG SEGUROS</div>
      <div class="info">
        Documento gerado em <?= htmlspecialchars($dataAtual) ?><br>
        interweg-beneficios.com.br
      </div>
    </div>
  </footer>
</article>

<?php if (!$jaRespondida): ?>
<script>
  const VIDAS_COLS = <?= json_encode($vidasCols) ?>;
  function addVida(){
    const tbody = document.querySelector('#cot-vidas tbody');
    const ri = tbody.children.length;
    const tr = document.createElement('tr');
    tr.innerHTML = VIDAS_COLS.map((_,ci)=>`<td><input type="text" name="vidas[${ri}][${ci}]"/></td>`).join('');
    tbody.appendChild(tr);
  }

  document.getElementById('cot-form').addEventListener('submit', async function(e){
    e.preventDefault();
    const btn = document.getElementById('cot-submit');
    btn.disabled = true; btn.textContent = 'Enviando...';

    const fd = new FormData(this);
    // Estrutura f[secao][idx] vira objeto
    const resp = {dados:[], contrato:[], coberturas:[], vidas_rows:[]};
    const vidasMap = {};
    for(const [k,val] of fd.entries()){
      let m;
      if(m = k.match(/^f\[(\w+)\]\[(\d+)\]$/)){
        const sec = m[1], i = parseInt(m[2]);
        resp[sec][i] = resp[sec][i] || {};
        resp[sec][i].valor = val;
      } else if(m = k.match(/^vidas\[(\d+)\]\[(\d+)\]$/)){
        const ri = m[1], ci = m[2];
        if(!vidasMap[ri]) vidasMap[ri] = [];
        vidasMap[ri][ci] = val;
      }
    }
    Object.keys(vidasMap).sort((a,b)=>+a-+b).forEach(k=>{
      // pula linhas totalmente vazias
      const r = vidasMap[k];
      if(r.some(v=>v && v.trim())) resp.vidas_rows.push(r);
    });

    try{
      const r = await fetch('cotacao_submit.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
          token: fd.get('token'),
          cliente_nome: fd.get('cliente_nome'),
          cliente_email: fd.get('cliente_email'),
          resposta: resp
        })
      }).then(x=>x.json());
      if(!r.ok){ throw new Error(r.msg||'Erro ao enviar'); }
      // Sucesso
      document.querySelector('main').innerHTML = `
        <div class="alert alert-success" style="font-size:15px;padding:24px">
          ✓ <strong>Cotação enviada com sucesso!</strong><br><br>
          Recebemos suas informações. O analista responsável vai retornar em breve com a proposta. Pode fechar esta página.
        </div>`;
      window.scrollTo(0,0);
    } catch(err){
      btn.disabled = false; btn.textContent = 'Enviar Cotação ➜';
      alert('Erro ao enviar: '+(err.message||err));
    }
  });
</script>
<?php endif; ?>
</body>
</html>
