<?php
/**
 * API - PAINEL FINANCEIRO DE AGENCIAMENTO (AG)
 * As parcelas de AG (agenciamento) vindas da Passagem de Bastão aparecem aqui
 * para o financeiro dar baixa (marcar recebido). Valor de cada parcela =
 * (% de agenciamento da parcela) x (Valor do 1º faturamento).
 * O Faturamento normal (comissão vitalícia) continua sendo gerado à parte.
 */
require_once __DIR__ . '/config.php';
auth();
header('Content-Type: application/json; charset=utf-8');

function agj($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function agbody(){ return json_decode(file_get_contents('php://input'), true) ?: []; }

// Só financeiro e admin podem dar baixa.
function ag_pode_escrever(){ return isAdmin() || isFinanceiro(); }

function ag_tabela(){
    db()->exec("CREATE TABLE IF NOT EXISTS ag_baixas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        parcela_num TINYINT NOT NULL,
        competencia VARCHAR(10) NULL,
        percentual DECIMAL(8,4) NULL,
        valor DECIMAL(12,2) NULL,
        recebido TINYINT(1) NOT NULL DEFAULT 0,
        data_recebimento DATE NULL,
        usuario VARCHAR(120) NULL,
        atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_emp_parc (empresa_id, parcela_num)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Garante as colunas da Passagem de Bastão usadas aqui (idempotente).
    foreach (['agenc_parcelas','fatura_parcelas','comp_receb_vitalicio','valor_fatura'] as $c) {
        try { db()->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS $c TEXT NULL"); } catch (Throwable $e) {}
    }
}
try { ag_tabela(); } catch (Throwable $e) {}

// "4.710,94" -> 4710.94 ; "7,5" -> 7.5
function ag_num($s){
    $s = trim((string)$s);
    if ($s === '') return null;
    $s = preg_replace('/[^\d,.-]/', '', $s);
    // formato BR: ponto = milhar, vírgula = decimal
    if (strpos($s, ',') !== false) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
    return is_numeric($s) ? (float)$s : null;
}
// "2026-10" -> "Out/26"
function ag_comp_label($ym){
    $ym = trim((string)$ym);
    if (!preg_match('/^(\d{4})-(\d{2})/', $ym, $m)) return $ym;
    $meses = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    $mi = (int)$m[2];
    return ($meses[$mi] ?? $m[2]) . '/' . substr($m[1], -2);
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // Lista os contratos com parcelas de AG + estado de baixa de cada parcela.
    case 'ag_listar': {
        $filtro = trim($_GET['filtro'] ?? 'pendentes'); // pendentes | concluidos | todos
        $emp = db()->query("SELECT id, grupo, nome, seguradora, numero_contrato, cnpj,
                                   valor_fatura, agenc_parcelas, fatura_parcelas, comp_receb_vitalicio
                            FROM empresas
                            WHERE ativo = 1
                              AND origem_cadastro IN ('passagem_bastao','passagem_bastao_subfatura')
                              AND ( (agenc_parcelas IS NOT NULL AND agenc_parcelas <> '')
                                 OR (fatura_parcelas IS NOT NULL AND fatura_parcelas <> '') )
                            ORDER BY grupo, nome")->fetchAll(PDO::FETCH_ASSOC);

        // baixas já lançadas (por empresa/parcela)
        $baixasRaw = db()->query("SELECT empresa_id, parcela_num, recebido, data_recebimento, valor
                                  FROM ag_baixas")->fetchAll(PDO::FETCH_ASSOC);
        $baixas = [];
        foreach ($baixasRaw as $b) { $baixas[$b['empresa_id'].'-'.$b['parcela_num']] = $b; }

        $out = [];
        $tot = ['parcelas'=>0,'pendentes'=>0,'recebidas'=>0,'valor_pendente'=>0,'valor_recebido'=>0];

        foreach ($emp as $e) {
            $valBase = ag_num($e['valor_fatura']);
            $pcts  = array_pad(explode(';', (string)$e['agenc_parcelas']), 5, '');
            $comps = array_pad(explode(';', (string)$e['fatura_parcelas']), 5, '');
            $parcelas = [];
            for ($i = 0; $i < 5; $i++) {
                $comp = trim($comps[$i] ?? '');
                $pctS = trim($pcts[$i] ?? '');
                // Só é uma parcela de AG de verdade se tiver % de agenciamento.
                // Competência sem % (ou parcela excluída) não vira linha aqui.
                if ($pctS === '') continue;
                $pct = ag_num($pctS);
                $valor = ($pct !== null && $valBase !== null) ? round($valBase * $pct / 100, 2) : null;
                $key = $e['id'].'-'.($i+1);
                $b = $baixas[$key] ?? null;
                $rec = $b ? (int)$b['recebido'] === 1 : false;
                // valor efetivamente baixado tem prioridade se existir
                if ($b && $b['valor'] !== null && $b['valor'] !== '') $valor = (float)$b['valor'];
                $parcelas[] = [
                    'num'              => $i+1,
                    'competencia'      => $comp,
                    'competencia_lbl'  => ag_comp_label($comp),
                    'percentual'       => $pct,
                    'valor'            => $valor,
                    'recebido'         => $rec,
                    'data_recebimento' => $b['data_recebimento'] ?? null,
                ];
                $tot['parcelas']++;
                if ($rec) { $tot['recebidas']++; $tot['valor_recebido'] += ($valor ?? 0); }
                else      { $tot['pendentes']++; $tot['valor_pendente'] += ($valor ?? 0); }
            }
            if (!$parcelas) continue;
            $pend = array_filter($parcelas, fn($p)=>!$p['recebido']);
            $status = count($pend) ? 'em_andamento' : 'concluido';
            if ($filtro === 'pendentes' && $status !== 'em_andamento') continue;
            if ($filtro === 'concluidos' && $status !== 'concluido') continue;
            $out[] = [
                'empresa_id'    => (int)$e['id'],
                'grupo'         => $e['grupo'],
                'empresa'       => $e['nome'],
                'seguradora'    => $e['seguradora'],
                'numero_contrato'=> $e['numero_contrato'],
                'cnpj'          => $e['cnpj'],
                'valor_fatura'  => $valBase,
                'comp_vitalicio'=> ag_comp_label($e['comp_receb_vitalicio']),
                'status'        => $status,
                'parcelas'      => $parcelas,
            ];
        }
        agj(['ok'=>true, 'data'=>$out, 'totais'=>$tot, 'pode_baixar'=>ag_pode_escrever()]);
        break;
    }

    // Marca/estorna a baixa de uma parcela de AG.
    case 'ag_baixar': {
        if (!ag_pode_escrever()) agj(['ok'=>false,'msg'=>'Acesso negado']);
        $d = agbody();
        $empId = intval($d['empresa_id'] ?? 0);
        $parc  = intval($d['parcela_num'] ?? 0);
        if ($empId <= 0 || $parc <= 0) agj(['ok'=>false,'msg'=>'Parcela inválida']);
        $rec   = !empty($d['recebido']) ? 1 : 0;
        $comp  = trim($d['competencia'] ?? '') ?: null;
        $pct   = ($d['percentual'] ?? '') === '' ? null : ag_num($d['percentual']);
        $valor = ($d['valor'] ?? '') === '' ? null : ag_num($d['valor']);
        $dtRec = $rec ? (trim($d['data_recebimento'] ?? '') ?: date('Y-m-d')) : null;
        $usr   = $_SESSION['user_nome'] ?? ($_SESSION['user_login'] ?? '');
        try {
            db()->prepare("INSERT INTO ag_baixas
                (empresa_id, parcela_num, competencia, percentual, valor, recebido, data_recebimento, usuario)
                VALUES (?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                competencia=VALUES(competencia), percentual=VALUES(percentual), valor=VALUES(valor),
                recebido=VALUES(recebido), data_recebimento=VALUES(data_recebimento), usuario=VALUES(usuario)")
              ->execute([$empId, $parc, $comp, $pct, $valor, $rec, $dtRec, $usr]);
            agj(['ok'=>true]);
        } catch (PDOException $e) { agj(['ok'=>false,'msg'=>'Erro: '.$e->getMessage()]); }
        break;
    }

    default:
        agj(['ok'=>false,'msg'=>'Ação desconhecida: '.$action]);
}
