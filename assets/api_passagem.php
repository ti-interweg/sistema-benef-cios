<?php
/**
 * API - PASSAGEM DE BASTÃO
 * Cria uma empresa NOVA na tabela `empresas` com os campos da planilha de
 * passagem de bastão + os do cadastro de empresa/contrato.
 * Bloqueia duplicidade: mesmo CNPJ E mesmo número de contrato.
 */
require_once __DIR__ . '/config.php';
auth();
header('Content-Type: application/json; charset=utf-8');

function pj($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function pbody(){ return json_decode(file_get_contents('php://input'), true) ?: []; }

// Grava um registro de histórico (auditoria) da passagem de bastão.
// $acao = 'criar' | 'editar' | 'excluir'. Falha silenciosa pra não travar o save principal.
function pbLogHistorico($empresaId, $acao, $dadosArr = null) {
    if (!$empresaId) return;
    try {
        // Snapshot completo: pega todos os campos da empresa naquele momento
        if ($dadosArr === null) {
            $st = db()->prepare("SELECT * FROM empresas WHERE id = ?");
            $st->execute([$empresaId]);
            $dadosArr = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        }
        $snap = json_encode($dadosArr, JSON_UNESCAPED_UNICODE);
        $uid  = intval($_SESSION['user_id']   ?? 0) ?: null;
        $unm  = trim($_SESSION['user_nome']   ?? '');
        $st = db()->prepare("INSERT INTO passagem_bastao_historico
                              (empresa_id, acao, usuario_id, usuario_nome, snapshot, criado_em)
                              VALUES (?, ?, ?, ?, ?, NOW())");
        $st->execute([$empresaId, $acao, $uid, ($unm ?: null), $snap]);
    } catch (PDOException $e) { /* silencioso: tabela pode não existir ainda */ }
}

// Integra a Passagem de Bastão com Faturamento e Renovação.
// Regra: Faturamento p/ TODA empresa; Renovação SOMENTE se Estipulante.
function pbAutoFatRen($empresaId, $m){
    if (empty($m['grupo']) || empty($m['nome'])) return;
    $meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    $comp  = $meses[(int)date('n')-1] . '/' . substr(date('Y'), -2);
    // ── Faturamento (sempre) — cria na competência atual se ainda não existir ──
    try {
        $chk = db()->prepare("SELECT 1 FROM faturamento
            WHERE cnpj <=> ? AND numero_contrato <=> ? AND empresa = ? AND competencia = ? AND seguradora <=> ? LIMIT 1");
        $chk->execute([$m['cnpj'] ?? null, $m['numero_contrato'] ?? null, $m['nome'], $comp, $m['seguradora'] ?? null]);
        if (!$chk->fetchColumn()) {
            $ins = db()->prepare("INSERT INTO faturamento
                (origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa,
                 analista_id, analista_nome, competencia, data_emissao, data_vencimento,
                 percentual_comissao, emails_envio, status_envio)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Pendente emissão')");
            $ins->execute([
                $m['origem'] ?? null, $m['ramo'] ?? null, $m['seguradora'] ?? null, $m['numero_contrato'] ?? null, $m['cnpj'] ?? null,
                $m['grupo'], $m['nome'], $m['analista_id'] ?? null, $m['analista_nome'] ?? null, $comp,
                $m['data_emissao'] ?? null, $m['data_vencimento'] ?? null, $m['percentual_comissao'] ?? null, $m['emails_faturamento'] ?? null
            ]);
        }
    } catch (PDOException $e) { error_log('pb auto-faturamento falhou (empresa '.$empresaId.'): '.$e->getMessage()); }
    // ── Renovação — SOMENTE se Estipulante ──
    if (($m['tipo_estipulacao'] ?? '') === 'Estipulante') {
        try {
            $iniVig = $m['inicio_vigencia'] ?? null;
            $mesRen = $m['mes_renovacao'] ?? null;
            $mesRenAuto = $mesRen
                ?: ($iniVig ? date('Y-m-d', strtotime($iniVig . ' +1 year')) : null)
                ?: date('Y-m-d', strtotime('+1 year'));
            $anoCiclo = intval(date('Y', strtotime($mesRenAuto)));
            $chkR = db()->prepare("SELECT 1 FROM renovacao WHERE empresa_id = ? AND ano_ciclo = ? LIMIT 1");
            $chkR->execute([$empresaId, $anoCiclo]);
            if (!$chkR->fetchColumn()) {
                $insR = db()->prepare("INSERT INTO renovacao
                    (empresa_id, ano_ciclo, mes_renovacao, status, tipo, aditivo,
                     numero_vidas_ciclo, porte_ciclo, analista_seguradora, criado_em, atualizado_em)
                    VALUES (?, ?, ?, 'A iniciar', 'Renovação', NULL, ?, ?, ?, NOW(), NOW())");
                $insR->execute([$empresaId, $anoCiclo, $mesRenAuto, $m['numero_vidas'] ?? null, $m['porte'] ?? null, $m['analista_nome'] ?? null]);
            }
        } catch (PDOException $e) { error_log('pb auto-renovacao falhou (empresa '.$empresaId.'): '.$e->getMessage()); }
    }
}

// Auto-migração: colunas de contato de Renovação (idempotente, falha silenciosa)
try {
    foreach (['resp_renov_nome','resp_renov_email','resp_renov_telefone'] as $col) {
        $ex = db()->query("SHOW COLUMNS FROM empresas LIKE '$col'")->fetch();
        if (!$ex) db()->exec("ALTER TABLE empresas ADD COLUMN $col VARCHAR(255) NULL");
    }
    // Data de nascimento do responsável (contato dia a dia)
    if (!db()->query("SHOW COLUMNS FROM empresas LIKE 'data_nascimento'")->fetch()) {
        db()->exec("ALTER TABLE empresas ADD COLUMN data_nascimento DATE NULL");
    }
} catch (PDOException $e) { /* silencioso */ }

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'criar_passagem_bastao':
        // Pós-venda/Novos Negócios/Admin podem; financeiro e visualizador não.
        if (isFinanceiro() || isVisualizador()) pj(['ok' => false, 'msg' => 'Acesso negado']);

        $d        = pbody();
        $nome     = trim($d['nome']  ?? '');
        $grupo    = trim($d['grupo'] ?? '');
        $cnpj     = trim($d['cnpj']  ?? '');
        $contrato = trim($d['numero_contrato'] ?? '');
        $editId   = intval($d['id'] ?? 0);

        if (!$nome) pj(['ok' => false, 'msg' => 'Razão Social / Empresa é obrigatória.']);
        if ($grupo === '') $grupo = $nome; // grupo é obrigatório na base; usa o nome

        // CNPJ: formata se válido; se não, guarda como veio (intake — valida depois)
        $cnpjFmt = null;
        if ($cnpj !== '') {
            $cnpjFmt = (function_exists('validarCNPJ') && validarCNPJ($cnpj) && function_exists('formatarCNPJ'))
                       ? formatarCNPJ($cnpj) : $cnpj;
        }

        // DEDUP: bloqueia se já existir empresa com MESMO CNPJ E MESMO contrato
        if ($cnpjFmt !== null && $contrato !== '') {
            $cnpjNum = preg_replace('/\D/', '', $cnpjFmt);
            try {
                $st = db()->prepare(
                  "SELECT id, nome FROM empresas
                   WHERE REPLACE(REPLACE(REPLACE(REPLACE(cnpj,'.',''),'/',''),'-',''),' ','') = ?
                     AND TRIM(numero_contrato) = ? AND id <> ? LIMIT 1");
                $st->execute([$cnpjNum, $contrato, $editId]);
                $dup = $st->fetch(PDO::FETCH_ASSOC);
                if ($dup) {
                    pj(['ok' => false, 'duplicado' => true,
                        'msg' => 'Já existe empresa com esse CNPJ e número de contrato (#' . $dup['id'] . ' — ' . $dup['nome'] . '). Cadastro bloqueado para não duplicar (pode ser renovação).']);
                }
            } catch (PDOException $e) { /* segue */ }
        }

        // Mapa coluna => valor (todas opcionais viram NULL se vazias)
        $t = function($k) use ($d){ $v = trim($d[$k] ?? ''); return $v === '' ? null : $v; };
        $map = [
            'grupo'                 => $grupo,
            'nome'                  => $nome,
            'cnpj'                  => $cnpjFmt,
            'origem'                => $t('origem'),
            'ramo'                  => $t('ramo'),
            'seguradora'            => $t('seguradora'),
            'numero_contrato'       => ($contrato === '' ? null : $contrato),
            'tipo_estipulacao'      => $t('tipo_estipulacao'),
            'tipo_passagem'         => $t('tipo_passagem'),
            'porte'                 => $t('porte'),
            'analista_id'           => (intval($d['analista_id'] ?? 0) ?: null),
            'analista_nome'         => $t('analista_nome'),
            'data_emissao'          => (($d['data_emissao']    ?? '') ?: null),
            'data_vencimento'       => (($d['data_vencimento'] ?? '') ?: null),
            'inicio_vigencia'       => (($d['inicio_vigencia'] ?? '') ?: null),
            'numero_vidas'          => ((isset($d['numero_vidas']) && $d['numero_vidas'] !== '') ? intval($d['numero_vidas']) : null),
            'percentual_comissao'   => ((isset($d['percentual_comissao']) && $d['percentual_comissao'] !== '') ? floatval(str_replace(',', '.', $d['percentual_comissao'])) : null),
            'emails_faturamento'    => $t('emails_faturamento'),
            'resp_nome'             => $t('resp_nome'),
            'resp_email'            => $t('resp_email'),
            'resp_telefone'         => $t('resp_telefone'),
            'data_nascimento'       => $t('data_nascimento'),
            'resp_renov_nome'       => $t('resp_renov_nome'),
            'resp_renov_email'      => $t('resp_renov_email'),
            'resp_renov_telefone'   => $t('resp_renov_telefone'),
            'observacao'            => $t('observacao'),
            // --- campos da planilha de passagem de bastão ---
            'comercial_interweg'            => $t('comercial_interweg'),
            'corretora'                     => $t('corretora'),
            'possui_subfaturas'             => $t('possui_subfaturas'),
            'subfaturas_desc'               => $t('subfaturas_desc'),
            'planos'                        => $t('planos'),
            'permanencia_contratual'        => $t('permanencia_contratual'),
            'modalidade_contratacao'        => $t('modalidade_contratacao'),
            'possui_copart'                 => $t('possui_copart'),
            'copart_desc'                   => $t('copart_desc'),
            'tipo_custo'                    => $t('tipo_custo'),
            'valor_fatura'                  => $t('valor_fatura'),
            'comissionamento'               => $t('comissionamento'),
            'data_venc_boleto'              => $t('data_venc_boleto'),
            'data_corte_inclusao'           => $t('data_corte_inclusao'),
            'data_corte_exclusao'           => $t('data_corte_exclusao'),
            'necessidade_rateio'            => $t('necessidade_rateio'),
            'contato_dia_a_dia'             => $t('contato_dia_a_dia'),
            'acessos_portais'               => $t('acessos_portais'),
            'politica_beneficios'           => $t('politica_beneficios'),
            'possui_odonto_conjugado'       => $t('possui_odonto_conjugado'),
            'odonto_conjugado_desc'         => $t('odonto_conjugado_desc'),
            'possui_agregados'              => $t('possui_agregados'),
            'agregados_desc'                => $t('agregados_desc'),
            'idade_limite_filho'            => $t('idade_limite_filho'),
            'exclui_filho_auto'             => $t('exclui_filho_auto'),
            'prazo_inclusao_sem_carencia'   => $t('prazo_inclusao_sem_carencia'),
            'necessita_doc_mov'             => $t('necessita_doc_mov'),
            'doc_mov_desc'                  => $t('doc_mov_desc'),
            'copia_contrato_aditivos'       => $t('copia_contrato_aditivos'),
            'possui_relatorio_sinistralidade' => $t('possui_relatorio_sinistralidade'),
            'particularidades'              => $t('particularidades'),
            'executivo_operadora'           => $t('executivo_operadora'),
            'executivo_operadora_email'     => $t('executivo_operadora_email'),
            'executivo_operadora_telefone'  => $t('executivo_operadora_telefone'),
            'caminho_pasta_rede'            => $t('caminho_pasta_rede'),
            'notificar_email_passagem'      => !empty($d['notificar_email_passagem']) ? 1 : 0,
            'destinatarios_email_passagem'  => $t('destinatarios_email_passagem'),
            'origem_cadastro'               => 'passagem_bastao',
            'ativo'                         => 1,
        ];

        try {
            if ($editId > 0) {
                // Edição: não mexe em origem_cadastro/ativo
                unset($map['origem_cadastro'], $map['ativo']);
                $cols = array_keys($map);
                $sets = implode(', ', array_map(function($c){ return "$c=?"; }, $cols));
                $st = db()->prepare("UPDATE empresas SET $sets WHERE id = ?");
                $st->execute(array_merge(array_values($map), [$editId]));
                pbAutoFatRen($editId, $map);   // garante Faturamento (sempre) + Renovação (se Estipulante)
                pbLogHistorico($editId, 'editar');
                pj(['ok' => true, 'id' => $editId, 'editado' => true]);
            } else {
                $cols  = array_keys($map);
                $place = implode(',', array_fill(0, count($cols), '?'));
                $st = db()->prepare("INSERT INTO empresas (" . implode(',', $cols) . ") VALUES ($place)");
                $st->execute(array_values($map));
                $newId = db()->lastInsertId();

                // ── Sincroniza de volta na cotação de origem ──
                // Preenche SOMENTE os campos que estão vazios lá com o que foi digitado aqui.
                $cid = intval($d['cotacao_id'] ?? 0);
                if ($cid > 0) {
                    try {
                        $cur = db()->prepare("SELECT entrada, analista, ramo, operadora_atual, player_contratado, premio, numero_vidas FROM controle_cotacao WHERE id = ?");
                        $cur->execute([$cid]);
                        $cc = $cur->fetch(PDO::FETCH_ASSOC);
                        if ($cc) {
                            // prêmio a partir de valor_fatura (string BR -> float)
                            $pPremio = null;
                            $vf = trim((string)($d['valor_fatura'] ?? ''));
                            if ($vf !== '') {
                                $s = preg_replace('/[^\d,\.]/', '', $vf);
                                if (strpos($s, ',') !== false) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
                                if (is_numeric($s)) $pPremio = floatval($s);
                            }
                            $emptyTxt = function($v){ return $v === null || trim((string)$v) === ''; };
                            $upd = []; $par = [];
                            if ($emptyTxt($cc['entrada'])           && $map['origem'])        { $upd[]='entrada=?';           $par[]=$map['origem']; }
                            if ($emptyTxt($cc['analista'])          && $map['analista_nome']) { $upd[]='analista=?';          $par[]=$map['analista_nome']; }
                            if ($emptyTxt($cc['ramo'])              && $map['ramo'])          { $upd[]='ramo=?';              $par[]=$map['ramo']; }
                            if ($emptyTxt($cc['operadora_atual'])   && $map['seguradora'])    { $upd[]='operadora_atual=?';   $par[]=$map['seguradora']; }
                            if ($emptyTxt($cc['player_contratado']) && $map['seguradora'])    { $upd[]='player_contratado=?'; $par[]=$map['seguradora']; }
                            if (($cc['premio'] === null || $cc['premio'] === '') && $pPremio !== null)            { $upd[]='premio=?';       $par[]=$pPremio; }
                            if (($cc['numero_vidas'] === null || $cc['numero_vidas'] === '') && $map['numero_vidas'] !== null) { $upd[]='numero_vidas=?'; $par[]=$map['numero_vidas']; }
                            if ($upd) {
                                $par[] = $cid;
                                db()->prepare("UPDATE controle_cotacao SET " . implode(', ', $upd) . " WHERE id = ?")->execute($par);
                            }
                        }
                    } catch (PDOException $e) { /* não bloqueia o salvamento da passagem */ }
                }

                pbAutoFatRen($newId, $map);   // Faturamento (sempre) + Renovação (se Estipulante)
                pbLogHistorico($newId, 'criar');
                pj(['ok' => true, 'id' => $newId, 'cotacao_sincronizada' => ($cid > 0)]);
            }
        } catch (PDOException $e) {
            pj(['ok' => false, 'msg' => 'Erro ao salvar: ' . $e->getMessage()]);
        }
        break;

    case 'editar_passagem_bastao':
        // Edita SOMENTE os campos do contrato/empresa (não toca nos campos extras da planilha).
        if (isFinanceiro() || isVisualizador()) pj(['ok' => false, 'msg' => 'Acesso negado']);
        $d   = pbody();
        $id  = intval($d['id'] ?? 0);
        if (!$id) pj(['ok' => false, 'msg' => 'ID inválido']);
        $nome = trim($d['nome'] ?? '');
        if (!$nome) pj(['ok' => false, 'msg' => 'Razão Social / Empresa é obrigatória.']);
        $cnpj = trim($d['cnpj'] ?? '');
        $contrato = trim($d['numero_contrato'] ?? '');
        $cnpjFmt = null;
        if ($cnpj !== '') {
            $cnpjFmt = (function_exists('validarCNPJ') && validarCNPJ($cnpj) && function_exists('formatarCNPJ')) ? formatarCNPJ($cnpj) : $cnpj;
        }
        if ($cnpjFmt !== null && $contrato !== '') {
            $cnpjNum = preg_replace('/\D/', '', $cnpjFmt);
            try {
                $st = db()->prepare("SELECT id, nome FROM empresas WHERE REPLACE(REPLACE(REPLACE(REPLACE(cnpj,'.',''),'/',''),'-',''),' ','') = ? AND TRIM(numero_contrato) = ? AND id <> ? LIMIT 1");
                $st->execute([$cnpjNum, $contrato, $id]);
                $dup = $st->fetch(PDO::FETCH_ASSOC);
                if ($dup) pj(['ok' => false, 'duplicado' => true, 'msg' => 'Já existe empresa com esse CNPJ e número de contrato (#' . $dup['id'] . ' — ' . $dup['nome'] . ').']);
            } catch (PDOException $e) { /* segue */ }
        }
        $te = function($k) use ($d){ $v = trim($d[$k] ?? ''); return $v === '' ? null : $v; };
        $std = [
            'origem' => $te('origem'), 'ramo' => $te('ramo'), 'seguradora' => $te('seguradora'),
            'numero_contrato' => ($contrato === '' ? null : $contrato), 'cnpj' => $cnpjFmt,
            'tipo_estipulacao' => $te('tipo_estipulacao'), 'tipo_passagem' => $te('tipo_passagem'),
            'grupo' => (trim($d['grupo'] ?? '') ?: $nome),
            'nome' => $nome, 'porte' => $te('porte'), 'analista_nome' => $te('analista_nome'),
            'data_emissao' => (($d['data_emissao'] ?? '') ?: null), 'data_vencimento' => (($d['data_vencimento'] ?? '') ?: null),
            'inicio_vigencia' => (($d['inicio_vigencia'] ?? '') ?: null),
            'numero_vidas' => ((isset($d['numero_vidas']) && $d['numero_vidas'] !== '') ? intval($d['numero_vidas']) : null),
            'percentual_comissao' => ((isset($d['percentual_comissao']) && $d['percentual_comissao'] !== '') ? floatval(str_replace(',', '.', $d['percentual_comissao'])) : null),
            'emails_faturamento' => $te('emails_faturamento'), 'resp_nome' => $te('resp_nome'),
            'resp_email' => $te('resp_email'), 'resp_telefone' => $te('resp_telefone'), 'observacao' => $te('observacao'),
        ];
        $sets = implode(', ', array_map(function($c){ return "$c=?"; }, array_keys($std)));
        try {
            $st = db()->prepare("UPDATE empresas SET $sets WHERE id = ?");
            $st->execute(array_merge(array_values($std), [$id]));
            pbLogHistorico($id, 'editar');
            pj(['ok' => true, 'id' => $id]);
        } catch (PDOException $e) { pj(['ok' => false, 'msg' => 'Erro ao salvar: ' . $e->getMessage()]); }
        break;

    case 'get_passagem':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) pj(['ok' => false, 'msg' => 'ID inválido']);
        try {
            $st = db()->prepare("SELECT * FROM empresas WHERE id = ?");
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            pj($row ? ['ok' => true, 'data' => $row] : ['ok' => false, 'msg' => 'Registro não encontrado']);
        } catch (PDOException $e) { pj(['ok' => false, 'msg' => $e->getMessage()]); }
        break;

    case 'excluir_passagem_bastao':
        // Exclui (soft delete: marca ativo=0). Mantém histórico no banco.
        if (isFinanceiro() || isVisualizador()) pj(['ok' => false, 'msg' => 'Acesso negado']);
        $id = intval($_GET['id'] ?? ($_POST['id'] ?? 0));
        if (!$id) { $b = pbody(); $id = intval($b['id'] ?? 0); }
        if (!$id) pj(['ok' => false, 'msg' => 'ID inválido']);
        try {
            $st = db()->prepare("UPDATE empresas SET ativo = 0
                                 WHERE id = ? AND origem_cadastro = 'passagem_bastao'");
            $st->execute([$id]);
            if ($st->rowCount() === 0) {
                pj(['ok' => false, 'msg' => 'Registro não encontrado ou não é uma passagem de bastão.']);
            }
            pbLogHistorico($id, 'excluir');
            pj(['ok' => true, 'id' => $id]);
        } catch (PDOException $e) {
            pj(['ok' => false, 'msg' => 'Erro ao excluir: ' . $e->getMessage()]);
        }
        break;

    case 'historico_passagem_bastao':
        // Retorna o histórico completo de uma passagem de bastão
        $id = intval($_GET['id'] ?? 0);
        if (!$id) pj(['ok' => false, 'msg' => 'ID inválido']);
        try {
            $st = db()->prepare("SELECT h.id, h.acao, h.usuario_id, h.usuario_nome, h.snapshot, h.criado_em
                                  FROM passagem_bastao_historico h
                                  WHERE h.empresa_id = ?
                                  ORDER BY h.criado_em DESC, h.id DESC");
            $st->execute([$id]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            // Decodifica o snapshot pra cada linha
            foreach ($rows as &$r) {
                $r['snapshot_obj'] = $r['snapshot'] ? json_decode($r['snapshot'], true) : null;
            }
            pj(['ok' => true, 'data' => $rows]);
        } catch (PDOException $e) {
            pj(['ok' => false, 'msg' => 'Erro ao carregar histórico: ' . $e->getMessage()]);
        }
        break;

    case 'listar_passagem_bastao':
        try {
            $rows = db()->query("SELECT id, nome, grupo, cnpj, seguradora, numero_contrato, ramo, origem,
                                         numero_vidas, inicio_vigencia, analista_nome, comercial_interweg, criado_em
                                  FROM empresas
                                  WHERE origem_cadastro = 'passagem_bastao' AND ativo = 1
                                  ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $rows = []; }
        pj(['ok' => true, 'data' => $rows]);
        break;

    case 'enviar_email_passagem':
        if (isFinanceiro() || isVisualizador()) pj(['ok' => false, 'msg' => 'Acesso negado']);
        $d         = pbody();
        $empresaId = intval($d['empresa_id'] ?? 0);
        $destRaw   = trim($d['destinatarios'] ?? '');
        $assunto   = trim($d['assunto'] ?? '');
        $corpoTxt  = trim($d['corpo'] ?? '');
        if (!$empresaId || !$destRaw || !$assunto || !$corpoTxt) {
            pj(['ok' => false, 'msg' => 'Campos obrigatórios faltando (empresa, destinatários, assunto, corpo).']);
        }
        // Valida destinatários
        $partes  = preg_split('/[;,]/', $destRaw);
        $validos = [];
        foreach ($partes as $em) {
            $em = trim($em);
            if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) $validos[] = $em;
        }
        if (!count($validos)) pj(['ok' => false, 'msg' => 'Nenhum destinatário válido.']);

        // Monta corpo HTML com a assinatura institucional (mesmo padrão do resumo mensal)
        $analistaNome = trim($_SESSION['user_nome'] ?? '') ?: 'Equipe Interweg';
        // Cargo na assinatura: Novos Negócios assina como Analista de Placement
        $cargoAssin = (($_SESSION['user_tipo'] ?? '') === 'novos_negocios')
            ? 'Analista de Placement - Novos Negócios'
            : 'Analista de Benefícios';
        $corpoHtml = '';
        foreach (preg_split('/\r?\n\r?\n/', $corpoTxt) as $par) {
            $par = trim($par);
            if ($par === '') continue;
            $corpoHtml .= '<p>' . nl2br(htmlspecialchars($par, ENT_QUOTES, 'UTF-8')) . '</p>' . "\n";
        }
        $corpoHtml .=
            '<p style="margin-bottom:2px">Atenciosamente,</p>' .
            '<p style="margin:8px 0 0;font-weight:700;color:#0a8a8d;font-size:15px">' . htmlspecialchars($analistaNome, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p style="margin:0;color:#444;font-size:13px">' . htmlspecialchars($cargoAssin, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p style="margin:4px 0 0;font-size:12px;color:#666">Tel. (31) 3508-6000 &nbsp;|&nbsp; interweg.com.br &nbsp;|&nbsp; Av. Raja Gabáglia, 4.987 &ndash; 302 &ndash; Belo Horizonte/MG</p>' .
            '<p><img src="https://interweg-beneficios.com.br/assinatura_interweg.png" alt="Interweg Seguros e Benefícios" style="max-width:480px;width:100%;height:auto;display:block;margin-top:10px"/></p>';

        // Envia via SMTP
        $mailFile = __DIR__ . '/mail_smtp.php';
        if (!is_file($mailFile)) pj(['ok' => false, 'msg' => 'mail_smtp.php não encontrado no servidor.']);
        require_once $mailFile;
        if (!function_exists('enviarEmailSMTP')) pj(['ok' => false, 'msg' => 'Função de envio SMTP indisponível.']);

        $replyTo = trim($_SESSION['user_email'] ?? '');
        if ($replyTo === '') $replyTo = trim($_SESSION['user_login'] ?? '');
        $erroEnvio = '';
        $sent = enviarEmailSMTP(implode(',', $validos), $assunto, $corpoHtml, $erroEnvio, $replyTo);
        $status = $sent ? 'enviado' : 'erro';

        // Sempre registra no log
        try {
            $ins = db()->prepare("INSERT INTO passagem_bastao_email_log
                (empresa_id, enviado_por, enviado_por_nome, destinatarios, assunto, corpo, status, erro_msg)
                VALUES (?,?,?,?,?,?,?,?)");
            $ins->execute([
                $empresaId,
                $_SESSION['user_id'] ?? null,
                $_SESSION['user_nome'] ?? '',
                implode('; ', $validos),
                $assunto,
                $corpoHtml,
                $status,
                $sent ? null : ($erroEnvio ?: 'falha desconhecida')
            ]);
        } catch (PDOException $e) { /* não bloqueia */ }

        if ($sent) {
            // Atualiza empresa: salva destinatários e data de envio
            try {
                db()->prepare("UPDATE empresas
                                  SET destinatarios_email_passagem = ?,
                                      email_passagem_enviado_em    = NOW()
                                WHERE id = ?")
                    ->execute([implode('; ', $validos), $empresaId]);
            } catch (PDOException $e) { /* não bloqueia */ }
            pj(['ok' => true, 'enviado_em' => date('Y-m-d H:i:s')]);
        }
        pj(['ok' => false, 'msg' => 'Falha no envio SMTP: ' . ($erroEnvio ?: 'servidor recusou')]);
        break;

    default:
        pj(['ok' => false, 'msg' => 'Ação desconhecida: ' . $action]);
}
