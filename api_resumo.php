<?php
/**
 * =====================================================
 * API - RESUMO MENSAL DE MOVIMENTAÇÕES (Demandas)
 * =====================================================
 * Funcionalidade ADITIVA: apenas LÊ a tabela `demandas` existente,
 * agrupa por empresa/cliente no período e registra o histórico de
 * envios na tabela nova `resumo_envios`. Não altera nada do que já existe.
 */

require_once __DIR__ . '/config.php';
auth();

header('Content-Type: application/json; charset=utf-8');

function rj($d) { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function rbody() { return json_decode(file_get_contents('php://input'), true) ?: []; }

$action = $_GET['action'] ?? '';

switch ($action) {

    // ── 1) Resumo do período agrupado por cliente ──────────────────
    case 'resumo_periodo':
        // Filtro por intervalo de datas (De / Até), sobre a data de recebimento.
        $de  = trim($_GET['data_de']  ?? '');
        $ate = trim($_GET['data_ate'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
            rj(['ok' => false, 'msg' => 'Informe o período (datas De e Até) válido.']);
        }
        if ($de > $ate) { $tmp = $de; $de = $ate; $ate = $tmp; } // garante ordem

        // Movimentações = TODAS as demandas da empresa no período (por recebimento).
        // Respeita carteira: quem não é admin/visualizador/financeiro vê só a própria.
        $sql = "SELECT d.id, d.grupo, d.empresa, d.seguradora, d.ramo, d.tipo_demanda, d.situacao,
                       d.dados_beneficiario, d.descricao_demanda, d.protocolo,
                       d.recebimento, d.inicio_tratativa, d.data_conclusao,
                       COALESCE(a.nome, d.analista_nome) AS analista_nome
                FROM demandas d
                LEFT JOIN analistas a ON d.analista_id = a.id
                WHERE d.recebimento BETWEEN ? AND ?";
        $params = [$de, $ate];
        if (!canViewAll() && !isFinanceiro()) {
            $sql .= " AND COALESCE(a.nome, d.analista_nome) = ?";
            $params[] = $_SESSION['user_nome'] ?? '';
        }
        // Filtro opcional por analista (dropdown da tela)
        $analistaFiltro = trim($_GET['analista'] ?? '');
        if ($analistaFiltro !== '') {
            $sql .= " AND COALESCE(a.nome, d.analista_nome) = ?";
            $params[] = $analistaFiltro;
        }
        $sql .= " ORDER BY d.empresa, d.recebimento, d.id";

        try {
            $st = db()->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            rj(['ok' => false, 'msg' => 'Erro ao buscar movimentações: ' . $e->getMessage()]);
        }

        // Agrupa por GRUPO ignorando maiúsc/minúsc e espaços nas pontas:
        // ex.: "valorem" e "VALOREM" viram UM grupo só (chave normalizada).
        // Cada movimentação carrega a empresa de origem (pra mostrar na tabela).
        $grupos = [];
        foreach ($rows as $r) {
            $orig = trim((string)($r['grupo'] ?? ''));
            $key  = ($orig !== '') ? mb_strtoupper($orig) : '(SEM GRUPO)';
            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'grupo'         => ($orig !== '' ? $orig : '(sem grupo)'),
                    'key'           => $key,
                    'movimentacoes' => [],
                    '_labels'       => [],
                ];
            }
            // conta cada variação de escrita pra usar a mais frequente como rótulo
            if ($orig !== '') $grupos[$key]['_labels'][$orig] = ($grupos[$key]['_labels'][$orig] ?? 0) + 1;
            $grupos[$key]['movimentacoes'][] = [
                'recebimento'   => $r['recebimento'],
                'empresa'       => $r['empresa'],
                'seguradora'    => $r['seguradora'],
                'ramo'          => $r['ramo'],
                'tipo'          => $r['tipo_demanda'],
                'situacao'      => $r['situacao'],
                'ini_tratativa' => $r['inicio_tratativa'],
                'conclusao'     => $r['data_conclusao'],
                'analista'      => $r['analista_nome'],
                'beneficiario'  => $r['dados_beneficiario'],
                'descricao'     => $r['descricao_demanda'],
                'protocolo'     => $r['protocolo'],
            ];
        }

        // E-mails de faturamento de TODAS as empresas do grupo (case-insensitive),
        // já que o resumo agora vai por grupo.
        $out = [];
        foreach ($grupos as $g) {
            // rótulo = grafia mais usada dentro do grupo
            if (!empty($g['_labels'])) { arsort($g['_labels']); $g['grupo'] = array_key_first($g['_labels']); }
            unset($g['_labels']);

            $set = [];
            try {
                $se = db()->prepare("SELECT emails_faturamento, resp_email
                                     FROM empresas WHERE UPPER(TRIM(grupo)) = ? AND ativo = 1");
                $se->execute([$g['key']]);
                while ($emp = $se->fetch(PDO::FETCH_ASSOC)) {
                    $fonte = trim($emp['emails_faturamento'] ?? '');
                    if ($fonte === '') $fonte = trim($emp['resp_email'] ?? '');
                    foreach (preg_split('/[;,]/', $fonte) as $em) {
                        $em = trim($em);
                        if ($em !== '') $set[mb_strtolower($em)] = $em;
                    }
                }
            } catch (PDOException $e) { /* segue sem e-mail */ }
            $g['emails'] = implode('; ', array_values($set));
            $g['qtd']    = count($g['movimentacoes']);
            unset($g['key']);
            $out[] = $g;
        }

        rj(['ok' => true, 'data' => $out, 'total_clientes' => count($out)]);
        break;

    // ── 2) Enviar o resumo (e registrar histórico) ─────────────────
    case 'enviar_resumo':
        if (isFinanceiro()) rj(['ok' => false, 'msg' => 'Seu perfil não tem permissão para enviar resumos.']);

        $d        = rbody();
        $cliente  = trim($d['cliente']      ?? '');
        $grupo    = trim($d['grupo']        ?? '');
        $comp     = trim($d['competencia']  ?? '');
        $dest     = trim($d['destinatario'] ?? '');
        $assunto  = trim($d['assunto']      ?? '');
        $corpo    = trim($d['corpo']        ?? '');
        $qtd      = intval($d['qtd']        ?? 0);

        if (!$cliente || !$dest || !$corpo) {
            rj(['ok' => false, 'msg' => 'Cliente, destinatário e corpo do e-mail são obrigatórios.']);
        }

        // Valida ao menos um e-mail (aceita lista separada por ; ou ,)
        $listaEmails = preg_split('/[;,]/', $dest);
        $validos = [];
        foreach ($listaEmails as $em) {
            $em = trim($em);
            if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) $validos[] = $em;
        }
        if (!count($validos)) {
            rj(['ok' => false, 'msg' => 'Nenhum e-mail de destinatário válido informado.']);
        }

        $assuntoFinal = $assunto ?: ('Resumo de Movimentações ' . $cliente . ' - ' . $comp);

        // Envio via SMTP autenticado (Hostinger bloqueia o mail() nativo).
        // Blindagem: se o mail_smtp.php não existir/estiver corrompido, devolve
        // mensagem clara em vez de derrubar o PHP (evita 500 sem corpo).
        $mailFile = __DIR__ . '/mail_smtp.php';
        if (!is_file($mailFile)) {
            rj(['ok' => false, 'status' => 'Erro', 'msg' => 'Arquivo mail_smtp.php não encontrado no servidor. Suba-o em public_html (mesma pasta do api_resumo.php).']);
        }
        require_once $mailFile;
        if (!function_exists('enviarEmailSMTP')) {
            rj(['ok' => false, 'status' => 'Erro', 'msg' => 'mail_smtp.php está incompleto/truncado no servidor (a função de envio não foi carregada). Re-suba pelo ZIP.']);
        }
        $erroEnvio = '';
        // Reply-To = e-mail REAL do analista logado (resposta do cliente vai pra ele).
        // 1) tenta a sessão; 2) busca no cadastro (mais confiável); 3) valida.
        $replyTo = trim($_SESSION['user_email'] ?? '');
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $login = trim($_SESSION['user_login'] ?? '');
            $replyTo = filter_var($login, FILTER_VALIDATE_EMAIL) ? $login : '';
        }
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            // Busca o e-mail cadastrado direto na tabela de origem do usuário.
            try {
                $src = $_SESSION['user_src'] ?? 'analistas';
                if (in_array($src, ['analistas', 'usuarios'], true)) {
                    $q = db()->prepare("SELECT email FROM {$src} WHERE id = ? LIMIT 1");
                    $q->execute([$_SESSION['user_id'] ?? 0]);
                    $em = trim((string)$q->fetchColumn());
                    if (filter_var($em, FILTER_VALIDATE_EMAIL)) $replyTo = $em;
                }
            } catch (Throwable $e) {}
        }
        // Sem e-mail válido do analista: NÃO envia (senão a resposta do cliente
        // cairia no noreply). Avisa pra cadastrar o e-mail antes.
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            rj(['ok' => false, 'msg' => 'Você está sem e-mail cadastrado. Cadastre seu e-mail em "Meu Perfil" antes de enviar, para que a resposta do cliente chegue até você (e não ao remetente automático).']);
        }
        $sent = enviarEmailSMTP(implode(',', $validos), $assuntoFinal, $corpo, $erroEnvio, $replyTo);
        $status = $sent ? 'Enviado' : 'Erro';

        // Registra no histórico (logs de auditoria) — sempre, mesmo se falhar
        try {
            $ins = db()->prepare("INSERT INTO resumo_envios
                (cliente, grupo, competencia, destinatario, assunto, corpo,
                 qtd_movimentacoes, status, usuario_id, usuario_nome)
                VALUES (?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([
                $cliente, $grupo, $comp, implode('; ', $validos), $assuntoFinal, $corpo,
                $qtd, $status, $_SESSION['user_id'] ?? null, $_SESSION['user_nome'] ?? ''
            ]);
        } catch (PDOException $e) { /* não trava o retorno do envio */ }

        if ($sent) rj(['ok' => true, 'status' => $status]);
        rj(['ok' => false, 'status' => $status, 'msg' => 'Falha no envio: ' . ($erroEnvio ?: 'servidor de e-mail recusou') . ' (registrado como "Erro" no histórico).']);
        break;

    // ── 3) Histórico de envios ─────────────────────────────────────
    case 'historico_resumos':
        try {
            $rows = db()->query("SELECT id, cliente, grupo, competencia, destinatario,
                                        qtd_movimentacoes, status, usuario_nome, enviado_em
                                 FROM resumo_envios
                                 ORDER BY enviado_em DESC
                                 LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $rows = [];
        }
        rj(['ok' => true, 'data' => $rows]);
        break;

    default:
        rj(['ok' => false, 'msg' => 'Ação desconhecida: ' . $action]);
}
