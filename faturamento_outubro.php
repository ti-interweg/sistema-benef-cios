<?php
$currentPage = 'faturamento_outubro';
$pageTitle   = 'Faturamento Outubro — Interweg Benefícios';
require_once __DIR__ . '/_header.php';

// Cria a tabela espelho (vazia) com a MESMA estrutura do Faturamento Atual,
// caso ainda não exista. Começa sem nenhuma linha.
try { db()->exec("CREATE TABLE IF NOT EXISTS faturamento_outubro LIKE faturamento_backup"); } catch (Throwable $e) {}

// Só a Letícia (ou admin) pode ALTERAR o Faturamento Outubro; os demais só visualizam.
$__un = strtolower($_SESSION['user_nome'] ?? '');
$podeAlterarOut = isAdmin() || strpos($__un, 'leticia') !== false || strpos($__un, 'letícia') !== false;
// Edição em lote (barra de seleção) liberada para todos os analistas, exceto visualizador/financeiro
$podeEditarLinhas = !$isVisualizador && !$isFinanceiro;

$rows = [];
$temTabela = true;
try {
    try { db()->exec("ALTER TABLE faturamento_outubro ADD COLUMN criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) {}
    $rows = db()->query("SELECT id, origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa,
        analista_nome, competencia, competencia_real, data_emissao, data_vencimento, status_envio,
        data_envio, numero_vidas, premio_liquido, percentual_comissao, valor_previsto, comissao_recebida,
        emails_envio, criado_em
        FROM faturamento_outubro ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $temTabela = false; }
?>
<style>
  .pill-az{display:inline-block;padding:3px 11px;border-radius:12px;background:#e3f0fd;color:#1565c0;font-weight:600;font-size:12px;white-space:nowrap}
  .av-an{width:28px;height:28px;min-width:28px;border-radius:50%;color:#37474f;font-size:11px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
  /* Barra de ações em lote — tudo em uma linha */
  #fb-bulk-bar{display:none;align-items:center;gap:8px;flex-wrap:nowrap;overflow-x:auto;padding:10px 14px;margin-bottom:10px;background:rgba(0,191,179,.08);border:1px solid var(--accent);border-radius:10px}
  #fb-bulk-bar .bulk-chip{flex:0 0 auto;font-weight:700;color:#fff;background:var(--accent);border-radius:20px;padding:4px 12px;font-size:12px;white-space:nowrap}
  #fb-bulk-bar .bulk-ctl{flex:0 0 auto;height:34px;padding:0 10px;border:1px solid var(--border);border-radius:8px;background:#fff;font-size:13px}
  #fb-bulk-bar select.bulk-ctl{min-width:150px;max-width:180px}
  #fb-bulk-bar input.bulk-ctl{width:140px}
  #fb-bulk-bar .btn{flex:0 0 auto;white-space:nowrap}
  #fb-bulk-bar .bulk-sep{flex:0 0 auto;width:1px;height:22px;background:var(--border)}
</style>
<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Faturamento Outubro</div>
      <div class="page-sub">Faturamento de outubro (tabela própria, edição completa)</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn btn-outline" onclick="fbExport()"><span class="material-symbols-rounded" style="font-size:17px">download</span> Exportar Excel</button>
<?php if ($podeAlterarOut): ?>
      <button class="btn btn-outline" onclick="fbAutoOpen()" title="Enviar o Faturamento Outubro por e-mail automaticamente todo dia"><span class="material-symbols-rounded" style="font-size:17px">schedule_send</span> Automação</button>
<?php endif; ?>
<?php if ($podeAlterarOut): ?>
      <button class="btn btn-outline" onclick="fbVirarOutubro()" title="Leva os contratos do Faturamento Outubro para o Faturamento Novembro"><span class="material-symbols-rounded" style="font-size:17px">event_repeat</span> Virar mês → Novembro</button>
<?php endif; ?>
<?php if (!$isVisualizador && !$isFinanceiro): ?>
      <button class="btn btn-outline" onclick="fbeOpen()"><span class="material-symbols-rounded" style="font-size:17px">upload</span> Importar Fechamento</button>
<?php endif; ?>
<?php if (!$isVisualizador && !$isFinanceiro): ?>
      <button class="btn btn-primary" onclick="fbNovo()"><span class="material-symbols-rounded" style="font-size:19px">add</span> Novo Faturamento</button>
<?php endif; ?>
    </div>
  </div>

<?php if (!$temTabela): ?>
  <div style="padding:16px;background:#fff3cd;border:1px solid #ffe69c;border-radius:8px;color:#664d03">
    A tabela <b>faturamento_outubro</b> ainda não existe e não pôde ser criada automaticamente. Verifique as permissões do banco.
  </div>
<?php else: ?>
  <div class="cards">
    <div class="card c-teal clickable" onclick="fbFiltrar('total')"><div class="card-label">Total</div><div class="card-val" id="fb-total">—</div><div class="card-sub">registros</div><div class="card-icon"><span class="material-symbols-rounded">receipt_long</span></div></div>
    <div class="card c-red clickable" onclick="fbFiltrar('hoje')"><div class="card-label">A emitir hoje</div><div class="card-val" id="fb-hoje">—</div><div class="card-sub">clique para filtrar</div><div class="card-icon"><span class="material-symbols-rounded">today</span></div></div>
    <div class="card c-blue clickable" onclick="fbFiltrar('aguardando')"><div class="card-label">Pendentes de envio</div><div class="card-val" id="fb-aguard">—</div><div class="card-sub">pendente emissão + aguardando + em análise</div><div class="card-icon"><span class="material-symbols-rounded">mark_email_unread</span></div></div>
    <div class="card c-green clickable" onclick="fbFiltrar('enviado')"><div class="card-label">Enviados</div><div class="card-val" id="fb-env">—</div><div class="card-sub">concluídos</div><div class="card-icon"><span class="material-symbols-rounded">mark_email_read</span></div></div>
  </div>

  <div class="cards" style="grid-template-columns:repeat(3,1fr)">
    <div class="card c-teal"><div class="card-label">Total Prêmio Líquido</div><div class="card-val" id="fb-premio" style="font-size:22px">R$ —</div><div class="card-sub">soma no período filtrado</div><div class="card-icon"><span class="material-symbols-rounded">payments</span></div></div>
    <div class="card c-blue"><div class="card-label">Comissão Prevista</div><div class="card-val" id="fb-previsto" style="font-size:22px">R$ —</div><div class="card-sub">cálculo automático</div><div class="card-icon"><span class="material-symbols-rounded">request_quote</span></div></div>
    <div class="card c-green"><div class="card-label">Comissão Recebida</div><div class="card-val" id="fb-recebido" style="font-size:22px">R$ —</div><div class="card-sub">preenchido pelo financeiro</div><div class="card-icon"><span class="material-symbols-rounded">account_balance_wallet</span></div></div>
  </div>

  <div class="filters">
    <input class="search-inp" type="text" id="fb-search" placeholder="Buscar por empresa, grupo, CNPJ, contrato, seguradora..." autocomplete="off"/>
    <select id="fb-origem"><option value="">Todas as origens</option></select>
    <select id="fb-ramo"><option value="">Todos os ramos</option></select>
    <select id="fb-seguradora"><option value="">Todas as seguradoras</option></select>
    <input id="fb-grupo" list="fb-grupo-list" placeholder="Grupo (digite p/ buscar)" autocomplete="off" style="min-width:180px"/>
    <datalist id="fb-grupo-list"></datalist>
    <input id="fb-empresa" list="fb-empresa-list" placeholder="Empresa (digite p/ buscar)" autocomplete="off" style="min-width:180px"/>
    <datalist id="fb-empresa-list"></datalist>
    <select id="fb-analista"><option value="">Todos os analistas</option></select>
    <select id="fb-status"><option value="">Todos os status</option></select>
    <div style="display:flex;gap:6px;align-items:center;flex:1 1 300px;min-width:270px" title="Filtrar por data de emissão (deixe 'até' vazio pra dia exato)">
      <input type="date" id="fb-emissao-de" style="flex:1;min-width:0" title="Emissão de"/>
      <span style="color:var(--muted);font-size:12px">a</span>
      <input type="date" id="fb-emissao-ate" style="flex:1;min-width:0" title="Emissão até (opcional)"/>
    </div>
  </div>

<?php if ($podeEditarLinhas): ?>
  <div id="fb-bulk-bar">
    <span class="bulk-chip"><span id="fb-bulk-count">0</span> selec.</span>
    <select id="fb-bulk-status" class="bulk-ctl">
      <option value="">Status…</option>
      <option>Pendente emissão</option><option>Aguardando envio</option><option>Enviado</option>
      <option>Sem emissão</option><option>Cancelado</option><option>Inadimplente</option>
    </select>
    <button class="btn btn-primary btn-sm" onclick="fbBulkStatus()">Aplicar</button>
    <span class="bulk-sep"></span>
    <select id="fb-bulk-analista" class="bulk-ctl"><option value="">Analista…</option></select>
    <button class="btn btn-primary btn-sm" onclick="fbBulkAnalista()">Trocar</button>
    <span class="bulk-sep"></span>
    <input type="month" id="fb-bulk-comp" class="bulk-ctl" title="Nova competência"/>
    <button class="btn btn-primary btn-sm" onclick="fbBulkCompetencia()" title="Alterar competência"><span class="material-symbols-rounded" style="font-size:16px">event</span> Comp.</button>
    <span class="bulk-sep"></span>
    <input type="month" id="fb-bulk-venc" class="bulk-ctl" title="Novo mês de vencimento"/>
    <button class="btn btn-primary btn-sm" onclick="fbBulkVencimento()" title="Alterar vencimento"><span class="material-symbols-rounded" style="font-size:16px">event_available</span> Venc.</button>
    <span class="bulk-sep"></span>
    <button class="btn btn-danger btn-sm" onclick="fbBulkExcluir()"><span class="material-symbols-rounded" style="font-size:16px">delete</span> Excluir</button>
    <button class="btn btn-outline btn-sm" onclick="fbLimpar()">Limpar</button>
  </div>
<?php endif; ?>

  <div class="table-wrap">
    <div class="table-head" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div class="table-title" id="fb-count">Lista de Faturamentos</div>
      <div style="display:flex;align-items:center;gap:6px">
        <input type="text" id="fb-sel-id" placeholder="Filtrar por ID (ex.: 22822, 21067)" style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:7px 11px;font-size:13px;color:var(--text);outline:none;min-width:210px"/>
        <button class="btn btn-outline btn-sm" onclick="fbForce='';render()"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">filter_alt</span> Filtrar</button>
      </div>
    </div>
    <div style="overflow:auto">
    <table>
      <thead><tr>
        <th><input type="checkbox" id="fb-check-all" onclick="fbToggleAll(this)"></th>
        <th style="cursor:pointer;user-select:none;white-space:nowrap" onclick="fbSortId()" title="Ordenar por ID">ID <span id="fb-sort-ind" style="font-size:10px;color:var(--accent)"></span></th><th>Origem</th><th>Ramo</th><th>Seguradora</th><th>Contrato / CNPJ</th>
        <th>Grupo / Empresa</th><th>Analista</th><th>Mês de envio</th><th>Competência</th>
        <th>Emissão</th><th>Vencimento</th><th>Status</th><th>Envio</th><th style="white-space:nowrap">Vidas</th>
        <th>Prêmio Líq.</th><th>% Com.</th><th>Prev. Comissão</th><th>Com. Recebida</th><th>E-mails</th><th>Criado em</th><th>Ler fatura</th><th>Ações</th>
      </tr></thead>
      <tbody id="fb-body"></tbody>
    </table>
    </div>
  </div>

  <!-- Modal de edição (mesmo visual da tela principal) -->
  <div class="ov" id="fb-modal" onclick="if(event.target===this)fbCloseModal()">
    <div class="modal" style="width:880px;max-width:95vw">
      <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">receipt_long</span> <span id="fb-m-titulo">Editar Faturamento</span> <span id="fb-m-id" style="color:var(--muted);font-weight:400"></span></div>
      <div class="modal-sub">Edição do backup de faturamento</div>

      <div class="sec">Identificação</div>
      <div class="row3">
        <div class="fg"><label>Origem *</label>
          <select id="fb-m-origem"><option value="">Selecione...</option>
            <option>Interweg MG</option><option>Interweg JF</option><option>Interweg SP</option><option>INTERWEG</option><option>FINLÂNDIA</option><option>INTERWEG | FINLÂNDIA</option><option>VALENTE JUNIOR</option>
          </select></div>
        <div class="fg"><label>Ramo *</label>
          <select id="fb-m-ramo"><option value="">Selecione...</option><option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option><option>Vida</option></select></div>
        <div class="fg"><label>Seguradora *</label><select id="fb-m-seguradora"><option value="">Selecione...</option></select></div>
      </div>
      <div class="row3">
        <div class="fg"><label>Número do contrato *</label><input type="text" id="fb-m-numero_contrato"/></div>
        <div class="fg"><label>CNPJ *</label><input type="text" id="fb-m-cnpj"/></div>
        <div class="fg"><label>Analista *</label><select id="fb-m-analista_nome"><option value="">Selecione...</option></select></div>
      </div>
      <div class="row2">
        <div class="fg"><label>Grupo *</label><input type="text" id="fb-m-grupo"/></div>
        <div class="fg"><label>Empresa *</label><input type="text" id="fb-m-empresa"/></div>
      </div>
      <div class="row1"><div class="fg"><label>Razão Social</label><input type="text" id="fb-m-razao_social"/></div></div>

      <div class="sec">Datas e Envio</div>
      <div class="row4">
        <div class="fg"><label>Mês de envio *</label><input type="text" id="fb-m-competencia" placeholder="Set/26"/></div>
        <div class="fg"><label>Competência</label><input type="text" id="fb-m-competencia_real" placeholder="Set/26"/></div>
        <div class="fg"><label>Data de emissão</label><input type="date" id="fb-m-data_emissao"/></div>
        <div class="fg"><label>Data de vencimento</label><input type="date" id="fb-m-data_vencimento"/></div>
        <div class="fg"><label>Status do envio *</label>
          <select id="fb-m-status_envio"><option>Pendente emissão</option><option>Aguardando envio</option><option>Enviado</option><option>Sem emissão</option><option>Cancelado</option><option>Inadimplente</option></select></div>
      </div>
      <div class="row2">
        <div class="fg"><label>Data de envio</label><input type="date" id="fb-m-data_envio"/></div>
        <div class="fg"><label>E-mails de envio</label><input type="text" id="fb-m-emails_envio"/></div>
      </div>

      <div class="sec">Valores (comissão calculada automaticamente)</div>
      <div class="row4">
        <div class="fg"><label>Nº de vidas</label><input type="number" id="fb-m-numero_vidas"/></div>
        <div class="fg"><label>Prêmio líquido (R$)</label><input type="number" step="0.01" id="fb-m-premio_liquido" oninput="fbCalc()"/></div>
        <div class="fg"><label>% de comissão</label><input type="number" step="0.01" id="fb-m-percentual_comissao" placeholder="Ex: 5" oninput="fbCalc()"/></div>
        <div class="fg"><label>Valor previsto (auto)</label><input type="number" step="0.01" id="fb-m-valor_previsto" readonly style="background:var(--bg-hover);color:var(--accent);font-weight:700"/></div>
      </div>
      <div class="row2">
        <div class="fg"><label style="color:var(--info)">Comissão recebida (financeiro)</label><input type="number" step="0.01" id="fb-m-comissao_recebida"/></div>
        <div class="fg"><label style="color:var(--info)">Valor a pagar (financeiro)</label><input type="number" step="0.01" id="fb-m-valor_a_pagar"/></div>
      </div>
      <div class="row2"><div class="fg" style="grid-column:1 / -1"><label>Observações</label><input type="text" id="fb-m-observacoes"/></div></div>

      <div class="mfooter">
        <button class="btn btn-outline" onclick="fbCloseModal()"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
        <button class="btn btn-danger" id="fb-m-del" onclick="fbExcluir()" style="display:none"><span class="material-symbols-rounded" style="font-size:17px">delete</span> Excluir</button>
        <button class="btn btn-primary" onclick="fbSalvar(this)"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar Faturamento</button>
      </div>
    </div>
  </div>

<!-- Modal Importar Fechamento (backup) -->
<div class="ov" id="fbe-modal" onclick="if(event.target===this)fbeClose()">
  <div class="modal" style="width:780px;max-width:96vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">upload_file</span> Importar Fechamento Mensal (Backup)</div>
    <div class="modal-sub">Atualiza vidas, prêmio e marca como <strong>Enviado</strong> no backup, na competência selecionada. Outras competências NÃO são afetadas.</div>
    <div style="background:rgba(0,191,179,.06);border:1px dashed var(--accent);border-radius:10px;padding:12px 14px;margin:12px 0;font-size:12.5px">
      <strong style="color:var(--accent)">Como funciona:</strong>
      <ol style="margin:6px 0 0 18px;padding:0;line-height:1.65">
        <li>A planilha precisa ter: <strong>Apólice/Contrato</strong>, <strong>Vidas</strong>, <strong>Prêmio Líquido</strong>. Opcional: <em>Data Envio</em>.</li>
        <li>Casa cada linha pelo <strong>número do contrato</strong> dentro da competência escolhida — não cria linha nova.</li>
        <li>As casadas recebem vidas, prêmio, data de envio e status=<em>Enviado</em>.</li>
        <li>Contratos não encontrados são listados e ignorados.</li>
      </ol>
    </div>
    <div class="row4">
      <div class="fg"><label>Mês de envio alvo *</label><input type="month" id="fbe-comp" title="Usado para casar as linhas"/></div>
      <div class="fg"><label>Competência (gravar)</label><input type="month" id="fbe-compreal" title="Opcional: grava na coluna Competência das linhas casadas"/></div>
      <div class="fg"><label>Data de envio padrão</label><input type="date" id="fbe-data"/></div>
      <div class="fg"><label>Modo de atualização</label>
        <select id="fbe-modo"><option value="sobrescrever">Sobrescrever valores existentes</option><option value="completar">Só preencher onde estiver vazio</option></select></div>
    </div>
    <div class="fg" style="margin-top:6px">
      <label>Arquivo Excel (.xlsx)</label>
      <input type="file" id="fbe-file" accept=".xlsx,.xls" onchange="fbePreview()" style="padding:10px;background:var(--bg);border:1.5px dashed var(--border);border-radius:8px;width:100%;cursor:pointer"/>
    </div>
    <div id="fbe-preview" style="display:none;margin-top:10px;padding:10px;background:var(--bg);border:1px solid var(--border);border-radius:8px;font-size:12.5px;max-height:280px;overflow-y:auto"></div>
    <div class="mfooter" style="margin-top:14px">
      <button class="btn btn-outline" onclick="fbeClose()">Cancelar</button>
      <button class="btn btn-primary" id="fbe-btn" onclick="fbeAplicar(this)" disabled><span class="material-symbols-rounded" style="font-size:16px">cloud_upload</span> Aplicar atualização</button>
    </div>
  </div>
</div>

<script>
const FB_DATA = <?= json_encode($rows, JSON_UNESCAPED_UNICODE) ?>;
const POUT = <?= $podeEditarLinhas ? 'true' : 'false' ?>; // checkboxes/seleção em lote: todos os analistas (exceto visualizador/financeiro)
const $ = id => document.getElementById(id);
const hoje = new Date().toISOString().slice(0,10);
const money = v => (v==null||v==='') ? '—' : 'R$ '+Number(v).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
const fdate = d => { if(!d||d==='0000-00-00') return '—'; const m=String(d).match(/^(\d{4})-(\d{2})-(\d{2})/); return m?`${m[3]}/${m[2]}/${m[1]}`:d; };
const fdatahora = d => { if(!d) return '—'; const m=String(d).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/); return m?`${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}`:fdate(d); };
const compISO = d => { if(!d) return ''; const m=String(d).match(/^(\d{4})-(\d{2})/); return m?m[1]+'-'+m[2]:''; };
let fbForce = '';

function fillSel(id,label,vals){ const s=$(id); if(!s) return; s.innerHTML='<option value="">'+label+'</option>'; [...new Set(vals.filter(Boolean))].sort((a,b)=>String(a).localeCompare(String(b),'pt')).forEach(v=>{const o=document.createElement('option');o.value=v;o.textContent=v;s.appendChild(o);}); }
fillSel('fb-origem','Todas as origens', FB_DATA.map(r=>r.origem));
fillSel('fb-ramo','Todos os ramos', FB_DATA.map(r=>r.ramo));
fillSel('fb-seguradora','Todas as seguradoras', FB_DATA.map(r=>r.seguradora));
// Analistas do Faturamento Outubro: DINÂMICO pelo cadastro (tipo "analista", ativos)
// + extras fixos (carteiras operacionais). Analista novo entra sozinho ao cadastrar.
const FB_ANALISTAS_EXTRAS = ['Operacao 1','Operacao 2'];
let FB_ANALISTAS_OUT = [...FB_ANALISTAS_EXTRAS];
fillSel('fb-analista','Todos os analistas', FB_ANALISTAS_OUT);
document.addEventListener('DOMContentLoaded', function(){
  if (typeof apiGet !== 'function') return;
  apiGet('analistas').then(function(r){
    var reais = (r.data||[]).filter(function(a){ return (a.tipo||'').toLowerCase().trim()==='analista'; }).map(function(a){ return a.nome; });
    FB_ANALISTAS_OUT = [...new Set([].concat(reais, FB_ANALISTAS_EXTRAS))];
    fillSel('fb-analista','Todos os analistas', FB_ANALISTAS_OUT);
    if($('fb-bulk-analista')) fillSel('fb-bulk-analista','Trocar analista para…', FB_ANALISTAS_OUT);
    if($('fb-m-analista_nome')) fillSel('fb-m-analista_nome','Selecione...', FB_ANALISTAS_OUT);
  }).catch(function(){});
});
function fillDatalist(id,vals){ const dl=$(id); dl.innerHTML=''; [...new Set(vals.filter(Boolean))].sort((a,b)=>String(a).localeCompare(String(b),'pt')).forEach(v=>{const o=document.createElement('option');o.value=v;dl.appendChild(o);}); }
fillDatalist('fb-grupo-list', FB_DATA.map(r=>r.grupo));
fillDatalist('fb-empresa-list', FB_DATA.map(r=>r.empresa));
fillSel('fb-status','Todos os status', ['Pendente emissão','Aguardando envio','Enviado','Sem emissão','Cancelado','Inadimplente']);

function scBadge(s){ const k=(s||'').toLowerCase(); if(k.includes('envi')&&!k.includes('aguard'))return 'background:#d1fae5;color:#065f46'; if(k.includes('pend')||k.includes('aguard')||k.includes('anal'))return 'background:#fef3c7;color:#92400e'; if(k.includes('cancel'))return 'background:#fee2e2;color:#991b1b'; return 'background:#eef2ff;color:#3730a3'; }

function fbFiltrar(t){ fbForce = (fbForce===t)?'':t; render(); }

function aplicaFiltros(){
  const q=$('fb-search').value.toLowerCase().trim(), o=$('fb-origem').value, ra=$('fb-ramo').value,
        se=$('fb-seguradora').value, gr=$('fb-grupo').value.toLowerCase().trim(),
        em2=$('fb-empresa').value.toLowerCase().trim(), an=$('fb-analista').value,
        st=$('fb-status').value, cp=(($('fb-competencia')||{}).value||''),
        ede=$('fb-emissao-de').value, eate=$('fb-emissao-ate').value;
  const idsF=(($('fb-sel-id')||{}).value||'').split(/[^0-9]+/).filter(Boolean);
  return FB_DATA.filter(r=>{
    if(idsF.length && !idsF.includes(String(r.id)))return false;
    if(o&&r.origem!==o)return false; if(ra&&r.ramo!==ra)return false;
    if(se&&r.seguradora!==se)return false;
    if(gr&&!(r.grupo||'').toLowerCase().includes(gr))return false;
    if(em2&&!(r.empresa||'').toLowerCase().includes(em2))return false;
    if(an&&r.analista_nome!==an)return false; if(st&&r.status_envio!==st)return false;
    if(ede){ const em=(r.data_emissao||'').slice(0,10); if(!em)return false;
      if(eate){ if(em<ede||em>eate)return false; } else { if(em!==ede)return false; } }
    if(cp){ const m=cp.split('-'); const meses=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez']; const alvo=meses[parseInt(m[1],10)-1]+'/'+m[0].slice(-2); if(r.competencia!==alvo)return false; }
    if(q){ const hay=((r.empresa||'')+' '+(r.grupo||'')+' '+(r.cnpj||'')+' '+(r.numero_contrato||'')+' '+(r.seguradora||'')).toLowerCase(); if(!hay.includes(q))return false; }
    const sc=(r.status_envio||'').toLowerCase();
    if(fbForce==='hoje'){ if(!(r.data_emissao===hoje && !sc.includes('envi')))return false; }
    else if(fbForce==='aguardando'){ if(!(sc.includes('pend')||sc.includes('aguard')||sc.includes('anal')))return false; }
    else if(fbForce==='enviado'){ if(!sc.includes('envi'))return false; }
    return true;
  });
}

var fbSortDir='desc';
function fbSortId(){
  fbSortDir = (fbSortDir==='desc') ? 'asc' : 'desc';
  const ind=document.getElementById('fb-sort-ind'); if(ind) ind.textContent = (fbSortDir==='desc'?'▼':'▲');
  render();
}
function render(){
  let rows=aplicaFiltros();
  if(fbSortDir==='asc')  rows=rows.slice().sort((a,b)=>(Number(a.id)||0)-(Number(b.id)||0));
  if(fbSortDir==='desc') rows=rows.slice().sort((a,b)=>(Number(b.id)||0)-(Number(a.id)||0));
  let hoje_n=0,aguard=0,env=0,premio=0,previsto=0,recebido=0;
  FB_DATA.forEach(r=>{ const sc=(r.status_envio||'').toLowerCase();
    if(r.data_emissao===hoje&&!sc.includes('envi'))hoje_n++;
    if(sc.includes('pend')||sc.includes('aguard')||sc.includes('anal'))aguard++;
    if(sc.includes('envi'))env++;
  });
  rows.forEach(r=>{ premio+=Number(r.premio_liquido)||0; previsto+=Number(r.valor_previsto)||0; recebido+=Number(r.comissao_recebida)||0; });
  $('fb-total').textContent=FB_DATA.length.toLocaleString('pt-BR');
  $('fb-hoje').textContent=hoje_n; $('fb-aguard').textContent=aguard.toLocaleString('pt-BR'); $('fb-env').textContent=env.toLocaleString('pt-BR');
  $('fb-premio').textContent=money(premio); $('fb-previsto').textContent=money(previsto); $('fb-recebido').textContent=money(recebido);
  $('fb-count').textContent='Lista de Faturamentos ('+rows.length.toLocaleString('pt-BR')+')';
  const esc=s=>{const d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
  $('fb-body').innerHTML = rows.slice(0,4000).map(r=>`<tr style="${r._lida?'background:#fff8d6':''}">
    <td>${POUT?`<input type="checkbox" class="fb-chk" value="${r.id}" onclick="fbAtualizaBarra()">`:''}</td>
    <td>${esc(r.id)}</td><td>${r.origem?`<span class="pill-az">${esc(r.origem)}</span>`:''}</td><td>${r.ramo?`<span class="pill-az">${esc(r.ramo)}</span>`:''}</td><td>${esc(r.seguradora)}</td>
    <td><b>${esc(r.numero_contrato)}</b><br><span style="color:var(--muted);font-size:11px">${esc(r.cnpj)}</span></td>
    <td><b>${esc(r.grupo)}</b><br><span style="color:var(--muted);font-size:11px">${esc(r.empresa)}</span></td>
    <td>${avatarAnalista(r.analista_nome)}</td><td>${esc(r.competencia)}</td><td>${esc(r.competencia_real)||'—'}</td>
    <td>${fdate(r.data_emissao)}</td><td>${fdate(r.data_vencimento)}</td>
    <td><span style="padding:2px 8px;border-radius:20px;font-size:11px;${scBadge(r.status_envio)}">${esc(r.status_envio)||'—'}</span></td>
    <td>${fdate(r.data_envio)}</td><td style="white-space:nowrap">${esc(r.numero_vidas)||'—'}</td>
    <td>${money(r.premio_liquido)}</td><td>${r.percentual_comissao!=null&&r.percentual_comissao!==''?(+(Number(r.percentual_comissao)*100).toFixed(2))+'%':'—'}</td>
    <td style="color:var(--accent);font-weight:600">${money(r.valor_previsto)}</td>
    <td style="color:${(r.comissao_recebida!=null&&r.comissao_recebida!=='')?'var(--success)':'var(--muted)'};font-weight:600">${(r.comissao_recebida!=null&&r.comissao_recebida!=='')?money(r.comissao_recebida):'—'}</td>
    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${(r.emails_envio||'').replace(/"/g,'')}">${esc(r.emails_envio)||'—'}</td>
    <td style="white-space:nowrap;color:var(--muted);font-size:12px">${fdatahora(r.criado_em)}</td>
    <td style="text-align:center">${(!window.IS_VISUALIZADOR && String(r.seguradora||'').toUpperCase().includes('AMIL'))?`<button title="Ler fatura AMIL (PDF/Excel) e preencher vidas e prêmio" onclick="fbLerFatura(${r.id})" class="btn btn-outline btn-sm" style="padding:4px 10px"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">upload</span></button>`:'<span style="color:var(--muted)">—</span>'}</td>
    <td style="white-space:nowrap">
      <div>
        ${window.IS_VISUALIZADOR
          ? '<span style="color:var(--muted)">—</span>'
          : (window.IS_FINANCEIRO
            ? `<button class="btn btn-outline btn-sm" title="Lançar comissão recebida" onclick="fbLancarComRec(${r.id})"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">payments</span> Lançar comissão</button>`
            : `<button class="btn btn-outline btn-sm" title="Ajuste rápido" onclick="fbAjuste(${r.id})"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">bolt</span></button>
               <button class="btn btn-outline btn-sm" onclick="fbEditar(${r.id})">Editar</button>`)
        }
      </div>
    </td></tr>`).join('')
    + (rows.length>4000?'<tr><td colspan="23" style="text-align:center;color:#999;padding:12px">Mostrando 4000 de '+rows.length+' — refine a busca</td></tr>':'');
  fbAtualizaBarra();
}
['fb-search','fb-sel-id','fb-origem','fb-ramo','fb-seguradora','fb-grupo','fb-empresa','fb-analista','fb-status','fb-emissao-de','fb-emissao-ate']
  .forEach(id=>$(id).addEventListener('input',()=>{fbForce='';render();}));

// dropdown de analista do lote — lista fixa do Outubro
fillSel('fb-bulk-analista','Trocar analista para…', FB_ANALISTAS_OUT);

const FB_TAB = 'faturamento_outubro'; // esta página opera na tabela espelho de outubro
async function fbApiPost(action, body){
  // injeta a tabela-alvo em toda gravação (os endpoints aceitam lista branca)
  const payload = Object.assign({ tabela: FB_TAB }, body || {});
  const r = await fetch('api.php?action='+action, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
  return r.json();
}
// Inclui as empresas do cadastro (base de clientes) no Faturamento Outubro.
// Pula as que já existem em outubro (mesmo CNPJ + contrato + empresa + competência).
async function fbGerarCarteiraOutubro(btn){
  const grupo = (await uiPrompt('Filtrar por grupo/empresa? (deixe vazio para TODA a carteira)',
                 {title:'Gerar pela carteira → Outubro', placeholder:'(vazio = toda a carteira)'}));
  if (grupo === null) return; // cancelou
  if (!await uiConfirm('Incluir as empresas do cadastro'+(grupo.trim()?(' do grupo/empresa "'+grupo.trim()+'"'):' (TODA a carteira)')+' no Faturamento Outubro (Out/2026)?\n\nEntram com emissão, vencimento, analista e % comissão; vidas e valores em branco. As que já estão em outubro são puladas.','Gerar pela carteira')) return;
  if (btn) btn.disabled = true;
  try {
    const r = await fbApiPost('gerar_faturamento_carteira_backup', { competencia: 'Out/2026', grupo: grupo.trim() });
    if (r.ok) {
      await uiAlert('Incluídas: '+r.criadas+'\nPuladas (já estavam em outubro): '+r.puladas+'\nTotal na carteira: '+r.total, 'Pronto');
      location.reload();
    } else {
      await uiAlert(r.msg || 'Erro ao gerar', 'Erro');
    }
  } catch(e){ await uiAlert('Erro de conexão', 'Erro'); }
  if (btn) btn.disabled = false;
}
// Avatar (bolinha com iniciais) do analista, cor fixa por nome
function avatarAnalista(nome){
  const esc=s=>{const d=document.createElement('div');d.textContent=(s==null?'':s);return d.innerHTML;};
  nome=(nome||'').trim();
  if(!nome) return '<span style="color:var(--muted)">—</span>';
  const partes=nome.split(/\s+/);
  const ini=((partes[0]||'')[0]||'')+((partes[1]||'')[0]||'');
  const cores=['#ffcdd2','#bbdefb','#c8e6c9','#ffe0b2','#e1bee7','#b2dfdb','#c5cae9','#f8bbd0','#d7ccc8','#b2ebf2'];
  let h=0; for(let i=0;i<nome.length;i++) h=(h*31+nome.charCodeAt(i))>>>0;
  const cor=cores[h%cores.length];
  return `<div style="display:flex;align-items:center;gap:8px">
    <span class="av-an" style="background:${cor}">${esc(ini.toUpperCase())}</span>
    <span>${esc(nome)}</span></div>`;
}
// Virar mês: traz os contratos do Faturamento Setembro para o Outubro
function fbVirarOutubro(){
  document.getElementById('vout-msg').textContent='';
  document.getElementById('vout-de').value='';
  const m=document.getElementById('mod-virar-out'); m.classList.add('show'); m.style.display='flex';
}
function fbVirarOutubroFechar(){ const m=document.getElementById('mod-virar-out'); m.classList.remove('show'); m.style.display='none'; }
async function fbVirarOutubroConfirmar(btn){
  const de = document.getElementById('vout-de').value;
  if(btn) btn.disabled=true;
  const msg=document.getElementById('vout-msg'); msg.style.color='var(--muted)'; msg.textContent='Virando...';
  try{
    const r = await fbApiPost('virar_para_novembro', de ? { de } : {});
    if(r.ok){ msg.style.color='var(--success,#28a745)'; msg.textContent=r.msg||'Mês virado'; if(window.toast)toast('Mês virado para Novembro'); setTimeout(()=>{ window.location.href='faturamento_novembro.php'; },800); }
    else { msg.style.color='#c0392b'; msg.textContent=r.msg||'Erro ao virar'; }
  }catch(e){ msg.style.color='#c0392b'; msg.textContent='Erro de conexão'; }
  if(btn) btn.disabled=false;
}
function fbChks(){ return [...document.querySelectorAll('.fb-chk')]; }
function fbSelIds(){ return fbChks().filter(c=>c.checked).map(c=>+c.value); }
function fbToggleAll(el){ fbChks().forEach(c=>c.checked=el.checked); fbAtualizaBarra(); }
function fbAtualizaBarra(){ const bar=$('fb-bulk-bar'); if(!bar) return; const n=fbSelIds().length; bar.style.display=n?'flex':'none'; const c=$('fb-bulk-count'); if(c)c.textContent=n; }
function fbLimpar(){ const a=$('fb-check-all'); if(a)a.checked=false; fbChks().forEach(c=>c.checked=false); fbAtualizaBarra(); }
function fbSelecionarPorId(){
  const raw=($('fb-sel-id').value||'').trim();
  if(!raw){ alert('Digite um ou mais IDs (separados por vírgula, espaço ou linha).'); return; }
  const ids=new Set(raw.split(/[^0-9]+/).filter(Boolean));
  if(!ids.size){ alert('Nenhum ID válido.'); return; }
  const presentes=new Set(fbChks().map(c=>String(c.value)));
  let achou=0;
  fbChks().forEach(c=>{ if(ids.has(String(c.value))){ c.checked=true; achou++; } });
  const faltou=[...ids].filter(id=>!presentes.has(id));
  fbAtualizaBarra();
  if(faltou.length) alert(achou+' linha(s) selecionada(s).\nNão encontradas na lista atual (confira os filtros): '+faltou.join(', '));
}
async function fbBulkStatus(){
  const ids=fbSelIds(); if(!ids.length){alert('Selecione ao menos um');return;}
  const st=$('fb-bulk-status').value; if(!st){alert('Escolha o status');return;}
  const r=await fbApiPost('atualizar_status_backup_lote',{ids,status:st});
  if(!r.ok){alert(r.msg||'Erro');return;}
  ids.forEach(id=>{const x=FB_DATA.find(d=>+d.id===id); if(x){x.status_envio=st; if(st==='Enviado'&&!x.data_envio)x.data_envio=hoje;}});
  fbLimpar(); render();
}
async function fbBulkAnalista(){
  const ids=fbSelIds(); if(!ids.length){alert('Selecione ao menos um');return;}
  const an=$('fb-bulk-analista').value; if(!an){alert('Escolha o analista');return;}
  const r=await fbApiPost('atualizar_analista_backup_lote',{ids,analista_nome:an});
  if(!r.ok){alert(r.msg||'Erro');return;}
  ids.forEach(id=>{const x=FB_DATA.find(d=>+d.id===id); if(x)x.analista_nome=an;});
  fbLimpar(); render();
}
// ===== Importar Fechamento (backup) =====
let _fbeRows=[];
const _MESES=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
function fbeCompBR(iso){ if(!iso)return ''; const [y,m]=iso.split('-'); return _MESES[+m-1]+'/'+y; }
// chave de comparação de competência tolerante a ano de 2 ou 4 dígitos (Out/26 == Out/2026)
function fbeCompKey(s){ s=String(s==null?'':s).trim().toLowerCase().replace(/\s+/g,''); const m=s.match(/([a-zç]{3,})[\/\-]?(\d{2,4})/); if(!m)return s; let y=m[2]; if(y.length===4)y=y.slice(-2); return m[1].slice(0,3)+'/'+y; }
function fbeClean(v){ return String(v==null?'':v).trim().toUpperCase().replace(/\s+/g,''); }
function fbeNum(v){ if(v==null||v==='')return null; let s=String(v).replace(/[^\d,.-]/g,''); if(s.indexOf(',')>=0&&s.indexOf('.')>=0)s=s.replace(/\./g,'').replace(',','.'); else s=s.replace(',','.'); const n=parseFloat(s); return isNaN(n)?null:n; }
function fbeDate(v){ if(!v)return null; if(typeof v==='number'){const d=new Date(Math.round((v-25569)*86400*1000));return d.toISOString().slice(0,10);} const m=String(v).match(/(\d{2})\/(\d{2})\/(\d{4})/); if(m)return `${m[3]}-${m[2]}-${m[1]}`; const m2=String(v).match(/^(\d{4})-(\d{2})-(\d{2})/); return m2?m2[0]:null; }
function fbeOpen(){ $('fbe-file').value=''; $('fbe-preview').style.display='none'; $('fbe-btn').disabled=true; _fbeRows=[]; $('fbe-modal').style.display='flex'; }
function fbeClose(){ $('fbe-modal').style.display='none'; }
function fbePreview(){
  const file=$('fbe-file').files[0]; if(!file)return;
  const iso=$('fbe-comp').value; if(!iso){alert('Selecione a competência alvo primeiro.');$('fbe-file').value='';return;}
  if(typeof XLSX==='undefined'){alert('Biblioteca de planilha não carregou. Recarregue a página.');return;}
  const compBR=fbeCompBR(iso);
  const reader=new FileReader();
  reader.onload=e=>{
    try{
      const wb=XLSX.read(new Uint8Array(e.target.result),{type:'array'});
      const ws=wb.Sheets[wb.SheetNames[0]];
      const rows=XLSX.utils.sheet_to_json(ws,{header:1,defval:'',raw:true});
      if(!rows.length){alert('Planilha vazia.');return;}
      const norm=s=>String(s||'').trim().toLowerCase();
      // Detecta a linha do cabecalho (pode nao ser a 1a — planilhas com titulo em cima)
      const acha=(cells,frag)=>cells.findIndex(c=>norm(c).includes(frag));
      let hrow=-1, header=[], ci=-1, vi=-1, pi=-1, di=-1;
      for(let k=0;k<Math.min(rows.length,15);k++){
        const cells=(rows[k]||[]).map(norm);
        const c=(cells.findIndex(x=>x.includes('contrat')||x.includes('apolice')||x.includes('apólice')));
        const v=cells.findIndex(x=>x.includes('vida'));
        const p=cells.findIndex(x=>x.includes('premio')||x.includes('prêmio'));
        if(c>=0&&v>=0&&p>=0){ hrow=k; header=cells; ci=c; vi=v; pi=p; di=cells.findIndex(x=>x.includes('envio')); break; }
      }
      if(hrow<0){alert('Não achei o cabeçalho com Contrato, Vidas e Prêmio na planilha.');return;}
      // mapa contrato->linha do backup, na competência
      const alvo=fbeCompKey(compBR);
      const cand=FB_DATA.filter(d=>fbeCompKey(d.competencia)===alvo);
      const map=new Map();
      cand.forEach(d=>{const k=fbeClean(d.numero_contrato);if(!k)return;if(!map.has(k))map.set(k,[]);map.get(k).push(d);});
      _fbeRows=[]; let cas=0,orf=0,dup=0; const orfL=[];
      for(let i=hrow+1;i<rows.length;i++){const r=rows[i];if(!r)continue;const k=fbeClean(r[ci]);if(!k)continue;
        const mt=map.get(k)||[];
        const rec={contrato:k,vidas:fbeNum(r[vi]),premio:fbeNum(r[pi]),dataEnvio:di>=0?fbeDate(r[di]):null};
        if(mt.length===0){orf++;orfL.push(k);}
        else{if(mt.length>1)dup++; cas++; rec.id=mt[0].id; _fbeRows.push(rec);}
      }
      let html=`<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:8px">
        <div style="padding:8px;border-radius:6px;background:rgba(63,185,80,.12);color:#3fb950"><b>${cas}</b> serão atualizadas</div>
        <div style="padding:8px;border-radius:6px;background:rgba(212,61,61,.12);color:#d43d3d"><b>${orf}</b> não encontradas em ${compBR}</div>
        <div style="padding:8px;border-radius:6px;background:rgba(240,136,62,.12);color:#f0883e"><b>${dup}</b> contrato(s) duplicado(s)</div></div>`;
      if(orf>0)html+='<details><summary style="cursor:pointer;color:#d43d3d">Ver órfãos</summary><div style="margin-top:6px">'+orfL.slice(0,60).map(k=>'Contrato <b>'+k+'</b>').join('<br>')+(orf>60?'<br>...':'')+'</div></details>';
      $('fbe-preview').innerHTML=html; $('fbe-preview').style.display=''; $('fbe-btn').disabled=(cas===0);
    }catch(err){alert('Erro ao ler planilha: '+err.message);}
  };
  reader.readAsArrayBuffer(file);
}
async function fbeAplicar(btn){
  if(!_fbeRows.length){alert('Nada a aplicar.');return;}
  const dpad=$('fbe-data').value||new Date().toISOString().slice(0,10);
  const modo=$('fbe-modo').value;
  const mesEnvio=$('fbe-comp').value ? fbeCompBR($('fbe-comp').value) : '';
  const compReal=$('fbe-compreal').value ? fbeCompBR($('fbe-compreal').value) : '';
  const updates=_fbeRows.map(r=>({id:r.id,vidas:r.vidas,premio:r.premio,data_envio:r.dataEnvio||dpad}));
  if(!await uiConfirm('Aplicar '+updates.length+' atualização(ões) no backup?','Importar fechamento'))return;
  btn.disabled=true;
  const r=await fbApiPost('importar_fechamento_backup',{updates,modo,mes_envio:mesEnvio,competencia_real:compReal});
  btn.disabled=false;
  if(!r.ok){alert(r.msg||'Erro');return;}
  alert(r.afetados+' linha(s) atualizada(s). A página vai recarregar.');
  location.reload();
}
async function fbIncluirCompetencia(){
  const comp=await uiPrompt('Incluir no Atual as linhas do faturamento ao vivo desta competência (mês de envio). Ex.: Set/26',{title:'Incluir por competência',placeholder:'Set/26'});
  if(!comp)return;
  const r=await fbApiPost('incluir_competencia_backup',{competencia:comp.trim()});
  if(!r.ok){alert(r.msg||'Erro');return;}
  alert(r.inseridos+' linha(s) incluída(s) no backup. A página vai recarregar.');
  location.reload();
}
async function fbBulkCompetencia(){
  const ids=fbSelIds(); if(!ids.length){alert('Selecione ao menos um');return;}
  const iso=$('fb-bulk-comp').value; if(!/^\d{4}-\d{2}$/.test(iso)){alert('Escolha a competência no calendário');return;}
  const meses=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
  const [y,m]=iso.split('-'); const comp=meses[+m-1]+'/'+y.slice(-2);
  if(!await uiConfirm('Alterar a competência de '+ids.length+' linha(s) para '+comp+'?','Alterar competência'))return;
  const r=await fbApiPost('atualizar_competencia_backup_lote',{ids,competencia:comp});
  if(!r.ok){alert(r.msg||'Erro');return;}
  ids.forEach(id=>{const x=FB_DATA.find(d=>+d.id===id); if(x)x.competencia_real=comp;});
  fbLimpar(); render();
}
async function fbBulkVencimento(){
  const ids=fbSelIds(); if(!ids.length){alert('Selecione ao menos um');return;}
  const iso=$('fb-bulk-venc').value; if(!/^\d{4}-\d{2}$/.test(iso)){alert('Escolha o mês de vencimento no calendário');return;}
  const [y,m]=iso.split('-');
  if(!await uiConfirm('Mudar o mês do vencimento de '+ids.length+' linha(s) para '+m+'/'+y+'? O dia é mantido. Linhas sem vencimento não mudam.','Alterar vencimento'))return;
  const r=await fbApiPost('atualizar_vencimento_backup_lote',{ids,ano_mes:iso});
  if(!r.ok){alert(r.msg||'Erro');return;}
  toast('Vencimento atualizado em '+(r.afetados||0)+' linha(s)');
  fbLimpar(); location.reload();
}
async function fbBulkExcluir(){
  const ids=fbSelIds(); if(!ids.length){alert('Selecione ao menos um');return;}
  if(!await uiConfirm('Excluir '+ids.length+' linha(s)? Essa ação não volta.','Excluir'))return;
  const r=await fbApiPost('excluir_faturamento_backup_lote',{ids});
  if(!r.ok){alert(r.msg||'Erro ao excluir');return;}
  ids.forEach(id=>{const i=FB_DATA.findIndex(d=>+d.id===id); if(i>=0)FB_DATA.splice(i,1);});
  fbLimpar(); render();
}
let fbEditId=null;
const FB_CAMPOS=['origem','ramo','seguradora','numero_contrato','cnpj','analista_nome','grupo','empresa','razao_social','competencia','competencia_real','data_emissao','data_vencimento','status_envio','data_envio','emails_envio','numero_vidas','premio_liquido','percentual_comissao','valor_previsto','comissao_recebida','valor_a_pagar','observacoes'];
// popula selects do modal
fillSel('fb-m-seguradora','Selecione...', FB_DATA.map(r=>r.seguradora));
fillSel('fb-m-analista_nome','Selecione...', FB_ANALISTAS_OUT);
function fbSetSel(id,val){ const s=$(id); if(!s)return; if(val&&![...s.options].some(o=>o.value===val)){const o=document.createElement('option');o.value=val;o.textContent=val;s.appendChild(o);} s.value=val||''; }
function fbCalc(){ const p=parseFloat($('fb-m-premio_liquido').value)||0; const pc=parseFloat($('fb-m-percentual_comissao').value)||0; $('fb-m-valor_previsto').value=(p*pc/100).toFixed(2); }
function fbPreencher(r){
  FB_CAMPOS.forEach(c=>{ const el=$('fb-m-'+c); if(!el)return;
    let v=r[c]==null?'':r[c];
    if((c==='data_emissao'||c==='data_vencimento'||c==='data_envio')&&v) v=String(v).slice(0,10);
    if(c==='percentual_comissao'&&v!=='') v=+(Number(v)*100).toFixed(2); // fração -> %
    if(el.tagName==='SELECT') fbSetSel('fb-m-'+c, v); else el.value=v;
  });
  fbCalc();
}
// ── FINANCEIRO: lançar comissão recebida (modal estilizado, igual ao Faturamento) ──
// FINANCEIRO: lançar comissão recebida + valor a pagar num ÚNICO modal (um clique só).
function fbComMask(el){ const v=(el.value||'').replace(/\D/g,''); el.value = v ? (parseInt(v,10)/100).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}) : ''; }
function fbLancarComRec(id){
  const r=FB_DATA.find(x=>+x.id===id); if(!r){ alert('Faturamento não encontrado'); return; }
  const brVal=v=>(v==null||v==='')?'':String(v).replace('.',',');
  $('fb-com-id').value=id;
  let ctx=(r.grupo||'?')+' · '+(r.empresa||'?')+' · '+(r.seguradora||'?')+' · '+(r.competencia||'');
  if(r.premio_liquido!=null&&r.premio_liquido!=='') ctx+='\nPrêmio líquido: '+money(r.premio_liquido);
  if(r.valor_previsto!=null&&r.valor_previsto!=='') ctx+='\nValor previsto: '+money(r.valor_previsto);
  $('fb-com-ctx').textContent=ctx;
  $('fb-com-rec').value=brVal(r.comissao_recebida); fbComMask($('fb-com-rec'));
  $('fb-com-pag').value=brVal(r.valor_a_pagar);     fbComMask($('fb-com-pag'));
  $('fb-com-modal').style.display='flex';
  setTimeout(()=>{ const i=$('fb-com-rec'); if(i){ i.focus(); i.select(); } }, 30);
}
function fbComClose(){ $('fb-com-modal').style.display='none'; }
async function fbComSalvar(btn){
  const id=$('fb-com-id').value;
  const toNum=s=>{ s=String(s==null?'':s).trim().replace(/\./g,'').replace(',','.'); const n=parseFloat(s); return isNaN(n)?null:n; };
  const crRaw=($('fb-com-rec').value||'').trim(), vpRaw=($('fb-com-pag').value||'').trim();
  const cr=crRaw===''?'':toNum(crRaw), vp=vpRaw===''?'':toNum(vpRaw);
  btn.disabled=true;
  const res=await fbApiPost('lancar_comissao_backup',{id, comissao_recebida:cr, valor_a_pagar:vp});
  btn.disabled=false;
  if(!res||!res.ok){ alert((res&&res.msg)||'Erro ao salvar'); return; }
  const r=FB_DATA.find(x=>+x.id===+id); if(r){ r.comissao_recebida=(cr===''?null:cr); r.valor_a_pagar=(vp===''?null:vp); }
  fbComClose(); render();
}

function fbEditar(id){
  const r=FB_DATA.find(d=>+d.id===id); if(!r)return; fbEditId=id;
  $('fb-m-titulo').textContent='Editar Faturamento'; $('fb-m-id').textContent='#'+id;
  $('fb-m-del').style.display='inline-flex';
  fbPreencher(r); $('fb-modal').style.display='flex';
}
function fbNovo(){
  fbEditId=null; $('fb-m-titulo').textContent='Novo Faturamento'; $('fb-m-id').textContent='';
  $('fb-m-del').style.display='none';
  fbPreencher({competencia:'Set/26',competencia_real:'Set/26',status_envio:'Pendente emissão'});
  $('fb-modal').style.display='flex';
}
function fbCloseModal(){ $('fb-modal').style.display='none'; fbEditId=null; }
async function fbSalvar(btn){
  btn.disabled=true;
  const body={};
  FB_CAMPOS.forEach(c=>{ const el=$('fb-m-'+c); if(!el)return; let v=el.value;
    if(c==='percentual_comissao'&&v!=='') v=(Number(v)/100); // % -> fração
    body[c]=v;
  });
  let r;
  if(fbEditId){ body.id=fbEditId; r=await fbApiPost('atualizar_faturamento_backup', body); }
  else { r=await fbApiPost('inserir_faturamento_backup', body); }
  btn.disabled=false;
  if(!r.ok){alert(r.msg||'Erro ao salvar');return;}
  if(fbEditId){ const x=FB_DATA.find(d=>+d.id===fbEditId); if(x)FB_CAMPOS.forEach(c=>{x[c]=body[c]===''?null:body[c];}); }
  else { const nova={id:r.id}; FB_CAMPOS.forEach(c=>nova[c]=body[c]===''?null:body[c]); FB_DATA.unshift(nova); }
  fbCloseModal(); render();
}
async function fbExcluir(){
  if(!fbEditId)return;
  if(!await uiConfirm('Excluir esta linha? Essa ação não volta.','Excluir'))return;
  const r=await fbApiPost('excluir_faturamento_backup',{id:fbEditId});
  if(!r.ok){alert(r.msg||'Erro ao excluir');return;}
  const i=FB_DATA.findIndex(d=>+d.id===fbEditId); if(i>=0)FB_DATA.splice(i,1);
  fbCloseModal(); render();
}

function fbLerFatura(id){
  const inp=document.createElement('input'); inp.type='file'; inp.accept='.pdf,.xlsx,.xls,.png,.jpg,.jpeg';
  inp.onchange=async()=>{ const f=inp.files[0]; if(f) await fbEnviarFatura(id,f); };
  inp.click();
}
async function fbEnviarFatura(id,f){
  const ext=(f.name.split('.').pop()||'').toLowerCase();
  let body;
  try{
    if(ext==='xlsx'||ext==='xls'){
      const buf=await f.arrayBuffer(); const wb=XLSX.read(new Uint8Array(buf),{type:'array'});
      let txt=''; wb.SheetNames.forEach(n=>{txt+=XLSX.utils.sheet_to_csv(wb.Sheets[n])+'\n';});
      body={id,tabela:FB_TAB,kind:'text',data:txt};
    } else {
      const b64=await new Promise(res=>{const r=new FileReader();r.onload=()=>res(String(r.result).split(',')[1]);r.readAsDataURL(f);});
      body={id,tabela:FB_TAB,kind:(ext==='pdf'?'pdf':'image'),data:b64,mime:f.type};
    }
  }catch(e){alert('Erro ao ler o arquivo: '+e.message);return;}
  const r=await fetch('ler_fatura_automacao.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(x=>x.json()).catch(()=>({erro:'conexão'}));
  if(r.erro){alert('Erro: '+r.erro);return;}
  const row=FB_DATA.find(x=>+x.id===id);
  if(row){ if(r.vidas!=null)row.numero_vidas=r.vidas; if(r.premio!=null)row.premio_liquido=r.premio; if(r.previsto!=null)row.valor_previsto=r.previsto; row.status_envio='Enviado'; row._lida=true; render(); }
  alert('Fatura lida ✓\nVidas: '+(r.vidas??'—')+'\nPrêmio: '+(r.premio??'—'));
}

function fbExport(){
  const rows=aplicaFiltros();
  const headers=['ID','Origem','Ramo','Seguradora','Contrato','CNPJ','Grupo','Empresa','Analista','Mês de envio','Competência','Emissão','Vencimento','Status','Envio','Vidas','Prêmio Líquido','% Comissão','Prev. Comissão','Com. Recebida'];
  const BRL='R$ #,##0.00', PCT='0.00%';
  const num=v=>(v==null||v===''||isNaN(Number(v)))?null:Number(v);
  const fdt=d=>{ if(!d)return''; const s=String(d).slice(0,10); const m=s.match(/^(\d{4})-(\d{2})-(\d{2})/); return m?(m[3]+'/'+m[2]+'/'+m[1]):s; };
  // % Comissão como TEXTO "5,00%" (a fração 0,05 do banco vira 5,00%) — não depende de formato do Excel
  const pct=v=>(v==null||v===''||isNaN(Number(v)))?'':String(+(Number(v)*100).toFixed(2)).replace('.',',')+'%';
  const aoa=[headers];
  rows.forEach(r=>aoa.push([
    num(r.id), r.origem||'', r.ramo||'', r.seguradora||'', String(r.numero_contrato==null?'':r.numero_contrato), String(r.cnpj==null?'':r.cnpj),
    r.grupo||'', r.empresa||'', r.analista_nome||'', r.competencia||'', r.competencia_real||'',
    fdt(r.data_emissao), fdt(r.data_vencimento), r.status_envio||'', fdt(r.data_envio),
    num(r.numero_vidas), num(r.premio_liquido), pct(r.percentual_comissao), num(r.valor_previsto), num(r.comissao_recebida)
  ]));
  const ws=XLSX.utils.aoa_to_sheet(aoa);
  ws['!cols']=[{wch:7},{wch:10},{wch:8},{wch:16},{wch:14},{wch:20},{wch:22},{wch:30},{wch:14},{wch:12},{wch:12},{wch:12},{wch:12},{wch:16},{wch:12},{wch:7},{wch:15},{wch:11},{wch:15},{wch:15}];
  const range=XLSX.utils.decode_range(ws['!ref']);
  const head={font:{bold:true,color:{rgb:'FFFFFFFF'}},fill:{patternType:'solid',fgColor:{rgb:'FF0A8A8D'}},alignment:{horizontal:'center',vertical:'center'}};
  for(let c=range.s.c;c<=range.e.c;c++){ const a=XLSX.utils.encode_cell({r:0,c}); if(ws[a])ws[a].s=head; }
  for(let R=1;R<=range.e.r;R++){
    [16,18,19].forEach(c=>{ const cell=ws[XLSX.utils.encode_cell({r:R,c})]; if(cell&&cell.v!=null){ cell.t='n'; cell.z=BRL; cell.s={numFmt:BRL}; } });
    const pc=ws[XLSX.utils.encode_cell({r:R,c:17})]; if(pc){ pc.s={alignment:{horizontal:'center'}}; }
    const vc=ws[XLSX.utils.encode_cell({r:R,c:15})]; if(vc&&vc.v!=null){ vc.t='n'; vc.s={alignment:{horizontal:'center'}}; }
  }
  const wb=XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb,ws,'Faturamento Outubro');
  XLSX.writeFile(wb,'faturamento_outubro.xlsx');
}
async function fbVirarMes(){
  const de = await uiPrompt('De qual competência / mês de envio? (ex.: Set/26)', {title:'Virar mês', placeholder:'Set/26'});
  if(!de) return;
  const para = await uiPrompt('Para qual competência? (ex.: Out/26)', {title:'Virar mês', placeholder:'Out/26'});
  if(!para) return;
  if(de.trim()===para.trim()){ await uiAlert('Origem e destino não podem ser iguais.'); return; }
  if(!await uiConfirm('Clonar as linhas de "'+de.trim()+'" para "'+para.trim()+'"?\n\nAs datas avançam o nº de meses e vidas/prêmio/status são zerados. Linhas que já existem no destino não são duplicadas.','Virar mês')) return;
  const r = await fbApiPost('virar_competencia_backup', { de: de.trim(), para: para.trim() });
  if(!r.ok){ await uiAlert(r.msg||'Erro ao virar'); return; }
  await uiAlert(r.msg||'Virada concluída.','Pronto');
  location.reload();
}
async function fbGerarCarteira(){
  const comp = await uiPrompt('Informe o Mês de envio (competência) para gerar pela carteira (Base de Clientes). Ex.: Set/26', {title:'Gerar mês pela carteira', placeholder:'Set/26'});
  if(!comp) return;
  const grupo = (await uiPrompt('Filtrar por grupo/empresa? (deixe vazio para TODA a carteira)', {title:'Gerar mês pela carteira', placeholder:'(vazio = toda a carteira)'})) || '';
  if(!await uiConfirm('Gerar faturamentos PENDENTES para "'+comp.trim()+'"'+(grupo.trim()?(' do grupo/empresa "'+grupo.trim()+'"'):' de TODA a carteira')+'?\n\nContratos que já existem nesse mês serão pulados.','Gerar mês pela carteira')) return;
  const r = await fbApiPost('gerar_faturamento_carteira_backup', { competencia: comp.trim(), grupo: grupo.trim() });
  if(!r.ok){ await uiAlert(r.msg||'Erro ao gerar'); return; }
  await uiAlert('Geradas: '+r.criadas+'\nPuladas (já existiam no mês): '+r.puladas+'\nTotal na carteira: '+r.total,'Pronto');
  location.reload();
}
// ── Ajuste Rápido (modal compacto) ──
const FB_MESES=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
function fbCompToISO(comp){ const m=String(comp||'').match(/^([A-Za-zçÇ]{3})\/(\d{2})$/); if(!m)return''; const i=FB_MESES.findIndex(x=>x.toLowerCase()===m[1].toLowerCase()); if(i<0)return''; return '20'+m[2]+'-'+('0'+(i+1)).slice(-2); }
function fbIsoToComp(iso){ const m=String(iso||'').match(/^(\d{4})-(\d{2})$/); if(!m)return''; return FB_MESES[parseInt(m[2],10)-1]+'/'+m[1].slice(-2); }
let fbqId=null;
function fbAjuste(id){
  const r=FB_DATA.find(d=>+d.id===id); if(!r)return; fbqId=id;
  $('fbq-sub').textContent=`${r.grupo||''} · ${r.empresa||''} · ${r.seguradora||''} · Contrato ${r.numero_contrato||'—'} · Competência ${r.competencia||'—'}`;
  $('fbq-vidas').value = r.numero_vidas!=null?r.numero_vidas:'';
  $('fbq-premio').value = r.premio_liquido!=null?r.premio_liquido:'';
  fbSetSel('fbq-status', r.status_envio||'');
  $('fbq-data-envio').value = r.data_envio?String(r.data_envio).slice(0,10):'';
  $('fbq-perc').value = (r.percentual_comissao!=null&&r.percentual_comissao!=='')?(+(Number(r.percentual_comissao)*100).toFixed(2))+' %':'';
  $('fbq-com-rec').value = r.comissao_recebida!=null?r.comissao_recebida:'';
  $('fbq-competencia').value = fbCompToISO(r.competencia);
  $('fbq-competencia-real').value = fbCompToISO(r.competencia_real);
  fbqCalc();
  $('fbq-modal').style.display='flex';
}
function fbqCalc(){ const row=FB_DATA.find(d=>+d.id===fbqId)||{}; const p=parseFloat($('fbq-premio').value)||0; const frac=Number(row.percentual_comissao)||0; $('fbq-valor-prev').value=(p*frac).toFixed(2); }
function fbqMarcarEnviado(){ fbSetSel('fbq-status','Enviado'); if(!$('fbq-data-envio').value)$('fbq-data-envio').value=new Date().toISOString().slice(0,10); }
function fbqClose(){ $('fbq-modal').style.display='none'; fbqId=null; }
async function fbqSalvar(btn){
  if(!fbqId)return; btn.disabled=true;
  const row=FB_DATA.find(d=>+d.id===fbqId)||{};
  const body={};
  FB_CAMPOS.forEach(c=>{ body[c]= row[c]==null?'':row[c]; if(c==='percentual_comissao'&&body[c]!=='') body[c]=Number(row[c]); });
  body.numero_vidas    = $('fbq-vidas').value;
  body.premio_liquido  = $('fbq-premio').value;
  body.status_envio    = $('fbq-status').value;
  body.data_envio      = $('fbq-data-envio').value;
  body.comissao_recebida = $('fbq-com-rec').value;
  body.competencia      = fbIsoToComp($('fbq-competencia').value) || row.competencia || '';
  body.competencia_real = fbIsoToComp($('fbq-competencia-real').value);
  const p=parseFloat(body.premio_liquido)||0; const frac=Number(row.percentual_comissao)||0;
  body.valor_previsto=(p*frac).toFixed(2);
  body.id=fbqId;
  const r=await fbApiPost('atualizar_faturamento_backup', body);
  btn.disabled=false;
  if(!r.ok){alert(r.msg||'Erro ao salvar');return;}
  FB_CAMPOS.forEach(c=>{ row[c]= (body[c]===''?null:body[c]); });
  fbqClose(); render();
}

render();
</script>
<?php endif; ?>

  <!-- Modal Ajuste Rápido (compacto) -->
  <div class="ov" id="fbq-modal" onclick="if(event.target===this)fbqClose()">
    <div class="modal" style="width:640px;max-width:96vw">
      <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">bolt</span> Ajuste Rápido</div>
      <div class="modal-sub" id="fbq-sub">—</div>
      <div class="row2">
        <div class="fg"><label>Nº de vidas</label><input type="number" id="fbq-vidas"/></div>
        <div class="fg"><label>Prêmio líquido (R$)</label><input type="number" step="0.01" id="fbq-premio" placeholder="0,00" oninput="fbqCalc()"/></div>
      </div>
      <div class="row3">
        <div class="fg"><label>Status do envio</label><select id="fbq-status"><option>Pendente emissão</option><option>Aguardando envio</option><option>Enviado</option><option>Sem emissão</option><option>Cancelado</option><option>Inadimplente</option></select></div>
        <div class="fg"><label>Data de envio</label><input type="date" id="fbq-data-envio"/></div>
        <div class="fg"><label>% de comissão</label><input type="text" id="fbq-perc" readonly style="background:var(--bg-hover);color:var(--muted)"/></div>
      </div>
      <div class="row2">
        <div class="fg"><label>Valor previsto (auto)</label><input type="text" id="fbq-valor-prev" readonly style="background:var(--bg-hover);color:var(--accent);font-weight:700"/></div>
        <div class="fg"><label style="color:var(--info)">Comissão recebida (financeiro)</label><input type="number" step="0.01" id="fbq-com-rec" placeholder="0,00"/></div>
      </div>
      <div class="row2">
        <div class="fg"><label>Mês de envio</label><input type="month" id="fbq-competencia"/></div>
        <div class="fg"><label>Competência</label><input type="month" id="fbq-competencia-real"/></div>
      </div>
      <div style="display:flex;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">
        <button class="btn btn-outline" onclick="fbqMarcarEnviado()" style="border-color:var(--success);color:var(--success)"><span class="material-symbols-rounded" style="font-size:16px">mark_email_read</span> Marcar como Enviado (hoje)</button>
      </div>
      <div class="mfooter" style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px">
        <button class="btn btn-outline" onclick="fbqClose()">Cancelar</button>
        <button class="btn btn-primary" onclick="fbqSalvar(this)"><span class="material-symbols-rounded" style="font-size:16px">save</span> Salvar Ajuste</button>
      </div>
    </div>
  </div>

<?php if ($podeAlterarOut): ?>
  <!-- ── MODAL: VIRAR MÊS (Setembro → Outubro) ── -->
  <div class="ov" id="mod-virar-out" onclick="if(event.target===this)fbVirarOutubroFechar()">
    <div class="modal" style="width:520px;max-width:95vw">
      <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">event_repeat</span> Virar mês → Novembro</div>
      <div class="modal-sub">Leva os contratos do Faturamento Outubro para o Faturamento Novembro, mantendo analista e % de comissão, com emissão e vencimento avançados em 1 mês. Vidas e valores ficam em branco. O Novembro precisa estar vazio.</div>
      <div class="row2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
        <div class="fg"><label>DE (origem)</label>
          <select id="vout-de"><option value="">Todas as competências</option></select>
        </div>
        <div class="fg"><label>PARA (destino)</label>
          <input type="text" value="Faturamento Novembro" readonly style="background:var(--bg);color:var(--muted)">
        </div>
      </div>
      <div id="vout-msg" style="font-size:12.5px;margin-top:4px"></div>
      <div class="mfooter" style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
        <button class="btn btn-outline" onclick="fbVirarOutubroFechar()">Cancelar</button>
        <button class="btn btn-primary" onclick="fbVirarOutubroConfirmar(this)"><span class="material-symbols-rounded" style="font-size:16px">event_repeat</span> Confirmar Virada</button>
      </div>
    </div>
  </div>

  <!-- ── MODAL: AUTOMAÇÃO DO BACKUP POR E-MAIL ── -->
  <div class="ov" id="fbauto-modal" onclick="if(event.target===this)fbAutoClose()">
    <div class="modal" style="width:520px;max-width:95vw">
      <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">schedule_send</span> Automação do backup</div>
      <div class="modal-sub">Envia o Excel do Faturamento Outubro por e-mail automaticamente, todo dia.</div>

      <div class="fg" style="margin-top:16px;display:flex;align-items:center;gap:10px">
        <label class="switch" style="position:relative;display:inline-block;width:46px;height:26px">
          <input type="checkbox" id="fbauto-ativo" style="opacity:0;width:0;height:0">
          <span id="fbauto-slider" style="position:absolute;cursor:pointer;inset:0;background:#ccc;border-radius:26px;transition:.2s"></span>
        </label>
        <span id="fbauto-ativo-txt" style="font-weight:600">Desativado</span>
      </div>

      <div class="row2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:8px">
        <div class="fg"><label>Horário do envio</label><input type="time" id="fbauto-hora" value="18:00"/></div>
        <div class="fg"><label>Último envio</label><input type="text" id="fbauto-ultimo" value="—" readonly style="background:var(--bg);color:var(--muted)"/></div>
      </div>
      <div class="fg"><label>Destinatário(s) <span style="font-weight:400;color:var(--muted)">(separe por ; para vários)</span></label>
        <input type="text" id="fbauto-dest" placeholder="beneficios3@interweg.com.br"/>
      </div>
      <div id="fbauto-msg" style="font-size:12.5px;margin-top:4px"></div>

      <div class="mfooter" style="display:flex;justify-content:space-between;gap:8px;margin-top:16px">
        <button class="btn btn-outline" onclick="fbAutoEnviarAgora(this)"><span class="material-symbols-rounded" style="font-size:16px">send</span> Enviar agora</button>
        <span style="display:flex;gap:8px">
          <button class="btn btn-outline" onclick="fbAutoClose()">Fechar</button>
          <button class="btn btn-primary" onclick="fbAutoSalvar(this)"><span class="material-symbols-rounded" style="font-size:16px">save</span> Salvar</button>
        </span>
      </div>
    </div>
  </div>
<?php endif; ?>
</main>

<?php if ($podeAlterarOut): ?>
<script>
function fbAutoSlider(){
  const on = document.getElementById('fbauto-ativo').checked;
  document.getElementById('fbauto-slider').style.background = on ? 'var(--accent)' : '#ccc';
  const dot = on ? 'translateX(20px)' : 'translateX(0)';
  document.getElementById('fbauto-slider').style.boxShadow = 'none';
  document.getElementById('fbauto-ativo-txt').textContent = on ? 'Ativado' : 'Desativado';
  let knob = document.getElementById('fbauto-knob');
  if(!knob){ knob=document.createElement('span'); knob.id='fbauto-knob';
    knob.style.cssText='position:absolute;height:20px;width:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s';
    document.getElementById('fbauto-slider').appendChild(knob); }
  knob.style.transform = dot;
}
async function fbAutoOpen(){
  document.getElementById('fbauto-msg').textContent='';
  try{
    const r = await apiGet('get_auto_faturamento', {fonte:'outubro'}); const d=(r&&r.data)||{};
    document.getElementById('fbauto-ativo').checked = !!d.ativo;
    document.getElementById('fbauto-hora').value = d.hora || '18:00';
    document.getElementById('fbauto-dest').value = d.destinatario || 'beneficios3@interweg.com.br';
    document.getElementById('fbauto-ultimo').value = d.ultimo_envio ? d.ultimo_envio.split('-').reverse().join('/') : '—';
  }catch(e){}
  fbAutoSlider();
  const m=document.getElementById('fbauto-modal'); m.classList.add('show'); m.style.display='flex';
}
function fbAutoClose(){ const m=document.getElementById('fbauto-modal'); m.classList.remove('show'); m.style.display='none'; }
document.addEventListener('change', e=>{ if(e.target && e.target.id==='fbauto-ativo') fbAutoSlider(); });

async function fbAutoSalvar(btn){
  const body = {
    fonte: 'outubro',
    ativo: document.getElementById('fbauto-ativo').checked,
    hora: document.getElementById('fbauto-hora').value || '18:00',
    destinatario: document.getElementById('fbauto-dest').value.trim()
  };
  if(btn) btn.disabled=true;
  try{
    const r = await apiPost('salvar_auto_faturamento', body);
    const msg=document.getElementById('fbauto-msg');
    if(r.ok){ msg.style.color='var(--success,#28a745)'; msg.textContent='✓ Automação salva.'; if(window.toast)toast('Automação salva'); }
    else { msg.style.color='#c0392b'; msg.textContent = r.msg || 'Erro ao salvar'; }
  }catch(e){ document.getElementById('fbauto-msg').textContent='Erro de conexão'; }
  if(btn) btn.disabled=false;
}
async function fbAutoEnviarAgora(btn){
  const dest = document.getElementById('fbauto-dest').value.trim();
  const msg=document.getElementById('fbauto-msg');
  msg.style.color='var(--muted)'; msg.textContent='Enviando...';
  if(btn) btn.disabled=true;
  try{
    const r = await apiPost('enviar_backup_agora', { fonte:'outubro', destinatario: dest });
    if(r.ok){ msg.style.color='var(--success,#28a745)'; msg.textContent='✓ Enviado para '+r.enviado_para+' ('+(r.linhas||0)+' linhas).'; }
    else { msg.style.color='#c0392b'; msg.textContent = r.msg || 'Falha no envio'; }
  }catch(e){ msg.style.color='#c0392b'; msg.textContent='Erro de conexão'; }
  if(btn) btn.disabled=false;
}
</script>
<?php endif; ?>

<!-- Modal: lançar comissão recebida (financeiro) — um clique só -->
<div class="ov" id="fb-com-modal" onclick="if(event.target===this)fbComClose()">
  <div class="modal" style="width:430px;max-width:95vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">payments</span> Lançar comissão recebida</div>
    <div id="fb-com-ctx" style="font-size:12.5px;color:var(--muted);margin:8px 0 14px;white-space:pre-line;line-height:1.5"></div>
    <input type="hidden" id="fb-com-id">
    <div style="margin-bottom:12px">
      <label style="display:block;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px">Comissão recebida (R$)</label>
      <input id="fb-com-rec" type="text" inputmode="decimal" placeholder="Ex: 1.234,56" oninput="fbComMask(this)" style="width:100%;box-sizing:border-box;padding:9px 11px;border:1px solid var(--border);border-radius:9px;font-size:14px;background:var(--bg-card);color:var(--text)">
    </div>
    <div style="margin-bottom:4px">
      <label style="display:block;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px">Valor a pagar (R$)</label>
      <input id="fb-com-pag" type="text" inputmode="decimal" placeholder="Ex: 1.234,56" oninput="fbComMask(this)" style="width:100%;box-sizing:border-box;padding:9px 11px;border:1px solid var(--border);border-radius:9px;font-size:14px;background:var(--bg-card);color:var(--text)">
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
      <button class="btn btn-outline" onclick="fbComClose()">Cancelar</button>
      <button class="btn btn-primary" onclick="fbComSalvar(this)"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">save</span> Salvar</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
