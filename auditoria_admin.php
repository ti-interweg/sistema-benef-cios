<?php
require_once 'config.php';
require_once __DIR__ . '/auditoria.php';
auth();
// Acesso restrito: apenas logins/e-mails da lista AUD_VER (você + TI).
if (!aud_pode_ver()) { header('Location: demandas.php'); exit; }
aud_migrar_once();

/* ── Filtros ── */
$q      = trim($_GET['q'] ?? '');
$evento = trim($_GET['evento'] ?? '');
$de     = trim($_GET['de'] ?? '');
$ate    = trim($_GET['ate'] ?? '');
$pag    = max(1, intval($_GET['pag'] ?? 1));
$porPag = 50;

$where = "WHERE 1=1";
$params = [];
if ($q !== '') {
    $where .= " AND (user_nome LIKE ? OR acao LIKE ? OR entidade LIKE ? OR ip LIKE ? OR detalhe LIKE ? OR user_tipo LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($evento !== '') { $where .= " AND evento = ?"; $params[] = $evento; }
if ($de !== '')  { $where .= " AND criado_em >= ?"; $params[] = $de . ' 00:00:00'; }
if ($ate !== '') { $where .= " AND criado_em <= ?"; $params[] = $ate . ' 23:59:59'; }

/* ── Export CSV (antes de qualquer HTML) ── */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auditoria_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM p/ Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Data/Hora', 'Usuário', 'Perfil', 'IP', 'Evento', 'Processo/Ação', 'Entidade', 'ID', 'Detalhe'], ';');
    try {
        $st = db()->prepare("SELECT * FROM log_auditoria $where ORDER BY id DESC LIMIT 20000");
        $st->execute($params);
        foreach ($st as $r) {
            fputcsv($out, [
                $r['criado_em'], $r['user_nome'], $r['user_tipo'], $r['ip'],
                $r['evento'], $r['acao'], $r['entidade'], $r['entidade_id'], $r['detalhe'],
            ], ';');
        }
    } catch (Throwable $e) {}
    fclose($out);
    exit;
}

/* ── Consulta paginada ── */
$total = 0;
try {
    $stc = db()->prepare("SELECT COUNT(*) FROM log_auditoria $where");
    $stc->execute($params);
    $total = intval($stc->fetchColumn());
} catch (Throwable $e) {}
$totPag = max(1, ceil($total / $porPag));
$pag = min($pag, $totPag);
$off = ($pag - 1) * $porPag;

$linhas = [];
try {
    $st = db()->prepare("SELECT * FROM log_auditoria $where ORDER BY id DESC LIMIT $porPag OFFSET $off");
    $st->execute($params);
    $linhas = $st->fetchAll();
} catch (Throwable $e) {}

/* KPIs rápidos (últimas 24h) */
$kpi = ['acessos' => 0, 'alteracoes' => 0, 'logins' => 0];
try {
    $k = db()->query("SELECT evento, COUNT(*) c FROM log_auditoria WHERE criado_em >= (NOW() - INTERVAL 1 DAY) GROUP BY evento");
    foreach ($k as $row) {
        if ($row['evento'] === 'acesso') $kpi['acessos'] += $row['c'];
        elseif ($row['evento'] === 'login') $kpi['logins'] += $row['c'];
        elseif (in_array($row['evento'], ['alteracao', 'exclusao'], true)) $kpi['alteracoes'] += $row['c'];
    }
} catch (Throwable $e) {}

function aud_badge($ev) {
    $map = [
        'acesso'    => ['#3b82f6', 'Acesso'],
        'alteracao' => ['#0a8a8d', 'Alteração'],
        'exclusao'  => ['#ef4444', 'Exclusão'],
        'login'     => ['#10b981', 'Login'],
        'logout'    => ['#6b7280', 'Logout'],
    ];
    $c = $map[$ev] ?? ['#6b7280', $ev];
    return '<span style="font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;background:' . $c[0] . '22;color:' . $c[0] . '">' . htmlspecialchars($c[1], ENT_QUOTES, 'UTF-8') . '</span>';
}
function aud_esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Nomes amigáveis para páginas e ações (deixa a leitura limpa).
function aud_label($acao) {
    $a = (string)$acao;
    $paginas = [
        'demandas' => 'Demandas', 'resumo_movimentacoes' => 'Resumo Mensal',
        'faturamento' => 'Faturamento', 'renovacao' => 'Renovação',
        'atas_visitas' => 'Atas de Visitas', 'dashboard' => 'Dashboard',
        'analistas' => 'Equipe', 'cadastros' => 'Cadastros', 'config' => 'Configurações',
        'mfa_admin' => 'Verificação 2 etapas', 'auditoria_admin' => 'Histórico e Auditoria',
        'demandas_assistenciais' => 'Demandas Assistenciais', 'acoes_saude' => 'Ações de Saúde',
        'sinistralidade' => 'Sinistralidade', 'devolutiva_nutricional' => 'Devolutiva de Saúde',
        'controle_cotacao' => 'Controle de Cotação', 'fechamento' => 'Fechamento',
        'passagem_bastao' => 'Passagem de Bastão', 'formcot' => 'Formulário Cotação',
        'apresentacao' => 'Apresentação', 'perfil' => 'Meu Perfil', 'index' => 'Início',
        'login' => 'Login', 'login_falha' => 'Login (senha incorreta)', 'logout' => 'Logout',
    ];
    if (isset($paginas[$a])) return $paginas[$a];
    // Ações da API: verbo_entidade -> "Verbo · Entidade"
    $verbos = [
        'criar' => 'Criar', 'editar' => 'Editar', 'excluir' => 'Excluir', 'deletar' => 'Excluir',
        'remover' => 'Remover', 'salvar' => 'Salvar', 'atualizar' => 'Atualizar',
        'importar' => 'Importar', 'enviar' => 'Enviar', 'virar' => 'Virar competência',
        'gerar' => 'Gerar', 'aplicar' => 'Aplicar', 'reset' => 'Resetar', 'duplicar' => 'Duplicar',
        'trocar' => 'Trocar', 'concluir' => 'Concluir', 'cancelar' => 'Cancelar',
    ];
    $p = explode('_', $a, 2);
    $verbo = $verbos[$p[0]] ?? ucfirst($p[0]);
    $resto = isset($p[1]) ? str_replace('_', ' ', $p[1]) : '';
    return $resto !== '' ? ($verbo . ' · ' . ucfirst($resto)) : $verbo;
}

// Detalhe legível: mostra alguns campos-chave em vez do JSON cru.
function aud_detalhe_fmt($detalhe) {
    $detalhe = (string)$detalhe;
    if ($detalhe === '') return '';
    $j = json_decode(rtrim($detalhe, '…'), true);
    if (!is_array($j)) return $detalhe;
    $prioridade = ['empresa', 'grupo', 'razao_social', 'nome', 'seguradora', 'ramo',
                   'competencia', 'status', 'status_envio', 'valor_a_pagar', 'comissao_recebida',
                   'cnpj', 'tipo_demanda', 'titulo'];
    $partes = [];
    foreach ($prioridade as $k) {
        if (isset($j[$k]) && $j[$k] !== '' && $j[$k] !== '(oculto)') {
            $partes[] = $k . ': ' . $j[$k];
            if (count($partes) >= 4) break;
        }
    }
    if (!$partes) {
        foreach ($j as $k => $v) {
            if ($k === 'id' || $v === '' || is_array($v)) continue;
            $partes[] = $k . ': ' . $v;
            if (count($partes) >= 3) break;
        }
    }
    return implode(' · ', $partes);
}

$currentPage = 'auditoria_admin';
$pageTitle   = 'Histórico e Auditoria — Interweg Benefícios';
require_once __DIR__ . '/_header.php';
?>
<style>
  .au-cards{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px}
  .au-kpi{flex:1;min-width:150px;background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:15px 18px}
  .au-kpi .n{font-size:24px;font-weight:800;color:var(--accent)}
  .au-kpi .l{font-size:11.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
  .au-filtros{display:flex;gap:10px;flex-wrap:wrap;align-items:end;background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:14px 16px;margin-bottom:16px}
  .au-filtros .fg{display:flex;flex-direction:column;gap:5px}
  .au-filtros label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;font-weight:700}
  .au-filtros input,.au-filtros select{padding:8px 11px;font-size:13px;border-radius:8px;background:var(--input-bg,var(--bg));border:1px solid var(--border);color:var(--text)}
  .au-table{width:100%;border-collapse:collapse;background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;font-size:13px}
  .au-table th{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);text-align:left;padding:11px 12px;border-bottom:1px solid var(--border);white-space:nowrap}
  .au-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:top}
  .au-table tr:last-child td{border-bottom:none}
  .au-mono{font-family:ui-monospace,Consolas,monospace;font-size:12px;color:var(--muted)}
  .au-det{max-width:340px;color:var(--muted);font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .au-pag{display:flex;gap:6px;align-items:center;justify-content:center;margin-top:16px;flex-wrap:wrap}
  .au-pag a,.au-pag span{padding:6px 11px;border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text);text-decoration:none}
  .au-pag .on{background:var(--accent);color:#fff;border-color:var(--accent)}
  .btn-csv{background:transparent;border:1px solid var(--border);color:var(--text);border-radius:8px;padding:9px 14px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
  .btn-csv:hover{border-color:var(--accent);color:var(--accent)}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Histórico e Auditoria</div>
      <div class="page-sub">Quem acessou e o que foi alterado — data, hora, IP, usuário e processo.</div>
    </div>
    <a class="btn-csv" href="?export=csv&q=<?= urlencode($q) ?>&evento=<?= urlencode($evento) ?>&de=<?= urlencode($de) ?>&ate=<?= urlencode($ate) ?>"><span class="material-symbols-rounded" style="font-size:18px">download</span> Exportar CSV</a>
  </div>

  <div class="au-cards">
    <div class="au-kpi"><div class="n"><?= number_format($total, 0, ',', '.') ?></div><div class="l">Registros (filtro atual)</div></div>
    <div class="au-kpi"><div class="n"><?= $kpi['acessos'] ?></div><div class="l">Acessos · 24h</div></div>
    <div class="au-kpi"><div class="n"><?= $kpi['alteracoes'] ?></div><div class="l">Alterações · 24h</div></div>
    <div class="au-kpi"><div class="n"><?= $kpi['logins'] ?></div><div class="l">Logins · 24h</div></div>
  </div>

  <form class="au-filtros" method="GET">
    <div class="fg" style="flex:1;min-width:200px"><label>Busca (usuário, ação, IP…)</label><input type="text" name="q" value="<?= aud_esc($q) ?>" placeholder="Ex: faturamento, Gabriela, 187.…"/></div>
    <div class="fg"><label>Evento</label>
      <select name="evento">
        <option value="">Todos</option>
        <?php foreach (['acesso'=>'Acesso','alteracao'=>'Alteração','exclusao'=>'Exclusão','login'=>'Login','logout'=>'Logout'] as $v=>$lb): ?>
          <option value="<?= $v ?>" <?= $evento===$v?'selected':'' ?>><?= $lb ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fg"><label>De</label><input type="date" name="de" value="<?= aud_esc($de) ?>"/></div>
    <div class="fg"><label>Até</label><input type="date" name="ate" value="<?= aud_esc($ate) ?>"/></div>
    <div class="fg"><label>&nbsp;</label><button class="btn btn-primary" type="submit" style="padding:9px 16px">Filtrar</button></div>
    <?php if ($q||$evento||$de||$ate): ?><div class="fg"><label>&nbsp;</label><a href="auditoria_admin.php" style="padding:9px 12px;color:var(--muted);font-size:13px;text-decoration:none">Limpar</a></div><?php endif; ?>
  </form>

  <table class="au-table">
    <thead>
      <tr><th>Data / Hora</th><th>Usuário</th><th>IP</th><th>Evento</th><th>Processo / Ação</th><th>Registro</th><th>Detalhe</th></tr>
    </thead>
    <tbody>
      <?php if (!$linhas): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:28px">Nenhum registro para os filtros.</td></tr>
      <?php else: foreach ($linhas as $r): ?>
        <tr>
          <td class="au-mono" style="white-space:nowrap"><?= aud_esc(date('d/m/Y H:i:s', strtotime($r['criado_em']))) ?></td>
          <td><?= aud_esc($r['user_nome'] ?: '—') ?><?php if($r['user_tipo']): ?><br><span style="font-size:11px;color:var(--muted)"><?= aud_esc($r['user_tipo']) ?></span><?php endif; ?></td>
          <td class="au-mono"><?= aud_esc($r['ip'] ?: '—') ?></td>
          <td><?= aud_badge($r['evento']) ?></td>
          <td><?= aud_esc(aud_label($r['acao'])) ?></td>
          <td>
            <?php if ($r['evento'] === 'acesso'): ?>—<?php
              elseif ($r['entidade_id'] !== null && $r['entidade_id'] !== ''): ?><span class="au-mono">#<?= aud_esc($r['entidade_id']) ?></span><?php
              else: ?>—<?php endif; ?>
          </td>
          <td class="au-det" title="<?= aud_esc($r['detalhe']) ?>"><?= aud_esc(aud_detalhe_fmt($r['detalhe']) ?: '—') ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <?php if ($totPag > 1): $qs = 'q='.urlencode($q).'&evento='.urlencode($evento).'&de='.urlencode($de).'&ate='.urlencode($ate); ?>
  <div class="au-pag">
    <?php if ($pag > 1): ?><a href="?<?= $qs ?>&pag=<?= $pag-1 ?>">‹ Anterior</a><?php endif; ?>
    <span class="on"><?= $pag ?> / <?= $totPag ?></span>
    <?php if ($pag < $totPag): ?><a href="?<?= $qs ?>&pag=<?= $pag+1 ?>">Próxima ›</a><?php endif; ?>
  </div>
  <?php endif; ?>

  <p style="font-size:12px;color:var(--muted);margin-top:14px;line-height:1.6">
    Registra automaticamente acessos a páginas e ações de escrita (criar/editar/excluir/enviar), além de login e logout.
    Campos sensíveis (senha, código, segredo) não são gravados. Os registros ficam no banco na tabela <b>log_auditoria</b>.
  </p>
</main>

<?php require_once __DIR__ . '/_footer.php'; ?>
