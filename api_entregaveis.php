<?php
/**
 * API - ENTREGÁVEIS POR PORTE
 * O que já foi feito/oferecido por empresa (conforme o porte), pra saber o que falta oferecer.
 */
require_once __DIR__ . '/config.php';
auth();
header('Content-Type: application/json; charset=utf-8');

function ej($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function ebody(){ return json_decode(file_get_contents('php://input'), true) ?: []; }

function ent_tabelas(){
    db()->exec("CREATE TABLE IF NOT EXISTS entregaveis_servicos (
        id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(160) NOT NULL, ordem INT NOT NULL DEFAULT 0,
        p29 VARCHAR(60) NULL, p30 VARCHAR(60) NULL, p100 VARCHAR(60) NULL, p300 VARCHAR(60) NULL,
        custo DECIMAL(10,2) NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, UNIQUE KEY uq_nome (nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS entregaveis_empresas (
        id INT AUTO_INCREMENT PRIMARY KEY, grupo VARCHAR(160) NOT NULL, porte VARCHAR(40) NULL,
        enfermeira VARCHAR(120) NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, UNIQUE KEY uq_grupo (grupo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS entregaveis_status (
        id INT AUTO_INCREMENT PRIMARY KEY, empresa_id INT NOT NULL, servico_id INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT '', data DATE NULL, observacao TEXT NULL,
        atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_emp_serv (empresa_id, servico_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
try { ent_tabelas(); } catch (Throwable $e) {}

// Porte -> coluna de aplicabilidade no serviço
function ent_col($porte){
    $p = mb_strtoupper(trim((string)$porte));
    if (strpos($p,'300')!==false) return 'p300';
    if (strpos($p,'100')!==false) return 'p100';
    if (strpos($p,'30')!==false)  return 'p30';
    return 'p29';
}
function ent_escreve(){ if (isVisualizador()) ej(['ok'=>false,'msg'=>'Acesso negado']); }

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // Lista empresas com progresso (total de entregáveis do porte, feitos, oferecidos, pendentes).
    case 'ent_empresas': {
        try {
            $servs = db()->query("SELECT id,p29,p30,p100,p300 FROM entregaveis_servicos WHERE ativo=1")->fetchAll(PDO::FETCH_ASSOC);
            $emps  = db()->query("SELECT id,grupo,porte,enfermeira FROM entregaveis_empresas WHERE ativo=1 ORDER BY grupo")->fetchAll(PDO::FETCH_ASSOC);
            $stRows= db()->query("SELECT empresa_id,servico_id,status FROM entregaveis_status")->fetchAll(PDO::FETCH_ASSOC);
            $smap=[]; foreach($stRows as $r){ $smap[$r['empresa_id']][$r['servico_id']]=$r['status']; }
            $out=[];
            foreach($emps as $e){
                $col=ent_col($e['porte']);
                $tot=0;$feitos=0;$ofer=0;$na=0;$pend=0;
                foreach($servs as $s){
                    $ap=trim((string)$s[$col]);
                    if ($ap==='') continue; // não é entregável pra esse porte
                    $tot++;
                    $st=$smap[$e['id']][$s['id']] ?? '';
                    if ($st==='Feito') $feitos++;
                    elseif ($st==='Oferecido') $ofer++;
                    elseif ($st==='Não se aplica') $na++;
                    else $pend++;
                }
                $e['total']=$tot; $e['feitos']=$feitos; $e['oferecidos']=$ofer; $e['nao_aplica']=$na; $e['pendentes']=$pend;
                $out[]=$e;
            }
            ej(['ok'=>true,'data'=>$out]);
        } catch (Throwable $e) { ej(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // Matriz completa de serviços (p/ a aba de referência).
    case 'ent_servicos': {
        try {
            $rows=db()->query("SELECT id,nome,ordem,p29,p30,p100,p300,custo FROM entregaveis_servicos WHERE ativo=1 ORDER BY ordem,nome")->fetchAll(PDO::FETCH_ASSOC);
            ej(['ok'=>true,'data'=>$rows]);
        } catch (Throwable $e) { ej(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // Detalhe de uma empresa: entregáveis do porte dela + status atual.
    case 'ent_detalhe': {
        $eid=intval($_GET['empresa_id'] ?? 0);
        if ($eid<=0) ej(['ok'=>false,'msg'=>'Empresa inválida']);
        try {
            $emp=db()->prepare("SELECT id,grupo,porte,enfermeira FROM entregaveis_empresas WHERE id=?");
            $emp->execute([$eid]); $empresa=$emp->fetch(PDO::FETCH_ASSOC);
            if (!$empresa) ej(['ok'=>false,'msg'=>'Empresa não encontrada']);
            $col=ent_col($empresa['porte']);
            $servs=db()->query("SELECT id,nome,ordem,$col AS aplic FROM entregaveis_servicos WHERE ativo=1 ORDER BY ordem,nome")->fetchAll(PDO::FETCH_ASSOC);
            $stRows=db()->prepare("SELECT servico_id,status,data,observacao FROM entregaveis_status WHERE empresa_id=?");
            $stRows->execute([$eid]);
            $smap=[]; foreach($stRows->fetchAll(PDO::FETCH_ASSOC) as $r){ $smap[$r['servico_id']]=$r; }
            $itens=[];
            foreach($servs as $s){
                $ap=trim((string)$s['aplic']);
                if ($ap==='') continue;
                $st=$smap[$s['id']] ?? [];
                $itens[]=['servico_id'=>$s['id'],'nome'=>$s['nome'],'aplic'=>$ap,
                          'status'=>$st['status'] ?? '', 'data'=>$st['data'] ?? '', 'observacao'=>$st['observacao'] ?? ''];
            }
            ej(['ok'=>true,'empresa'=>$empresa,'itens'=>$itens]);
        } catch (Throwable $e) { ej(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // Salva o status de um entregável (por empresa).
    case 'ent_status_salvar': {
        ent_escreve();
        $d=ebody();
        $eid=intval($d['empresa_id'] ?? 0); $sid=intval($d['servico_id'] ?? 0);
        $status=trim($d['status'] ?? '');
        if (!in_array($status, ['','Oferecido','Feito','Não se aplica'], true)) ej(['ok'=>false,'msg'=>'Status inválido']);
        $data=trim($d['data'] ?? ''); if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$data)) $data=null;
        $obs=trim($d['observacao'] ?? '');
        if ($eid<=0||$sid<=0) ej(['ok'=>false,'msg'=>'Dados inválidos']);
        try {
            $st=db()->prepare("INSERT INTO entregaveis_status (empresa_id,servico_id,status,data,observacao)
                               VALUES (?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE status=VALUES(status),data=VALUES(data),observacao=VALUES(observacao)");
            $st->execute([$eid,$sid,$status,$data,($obs===''?null:$obs)]);
            ej(['ok'=>true]);
        } catch (Throwable $e) { ej(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // Cria/edita empresa.
    case 'ent_empresa_salvar': {
        ent_escreve();
        $d=ebody();
        $id=intval($d['id'] ?? 0);
        $grupo=trim($d['grupo'] ?? ''); if($grupo==='') ej(['ok'=>false,'msg'=>'Grupo em branco']);
        $porte=trim($d['porte'] ?? ''); $enf=trim($d['enfermeira'] ?? '');
        try {
            if ($id>0) {
                db()->prepare("UPDATE entregaveis_empresas SET grupo=?,porte=?,enfermeira=? WHERE id=?")
                    ->execute([$grupo,$porte,($enf===''?null:$enf),$id]);
                ej(['ok'=>true,'id'=>$id]);
            } else {
                db()->prepare("INSERT INTO entregaveis_empresas (grupo,porte,enfermeira) VALUES (?,?,?)
                               ON DUPLICATE KEY UPDATE porte=VALUES(porte),enfermeira=VALUES(enfermeira)")
                    ->execute([$grupo,$porte,($enf===''?null:$enf)]);
                ej(['ok'=>true]);
            }
        } catch (Throwable $e) { ej(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    // Edita a matriz de um serviço (aplicabilidade por porte + custo).
    case 'ent_servico_salvar': {
        ent_escreve();
        $d=ebody(); $id=intval($d['id'] ?? 0);
        $nome=trim($d['nome'] ?? ''); if($nome==='') ej(['ok'=>false,'msg'=>'Nome em branco']);
        $g=fn($k)=>(trim($d[$k] ?? '')===''?null:trim($d[$k]));
        $custo=($d['custo'] ?? '')===''?null:floatval($d['custo']);
        try {
            if ($id>0) {
                db()->prepare("UPDATE entregaveis_servicos SET nome=?,p29=?,p30=?,p100=?,p300=?,custo=? WHERE id=?")
                    ->execute([$nome,$g('p29'),$g('p30'),$g('p100'),$g('p300'),$custo,$id]);
            } else {
                db()->prepare("INSERT INTO entregaveis_servicos (nome,p29,p30,p100,p300,custo) VALUES (?,?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE p29=VALUES(p29),p30=VALUES(p30),p100=VALUES(p100),p300=VALUES(p300),custo=VALUES(custo)")
                    ->execute([$nome,$g('p29'),$g('p30'),$g('p100'),$g('p300'),$custo]);
            }
            ej(['ok'=>true]);
        } catch (Throwable $e) { ej(['ok'=>false,'msg'=>$e->getMessage()]); }
        break;
    }

    default:
        ej(['ok'=>false,'msg'=>'Ação desconhecida: '.$action]);
}
