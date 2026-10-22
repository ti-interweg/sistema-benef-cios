<?php
$currentPage = 'faturamento_automacao';
$pageTitle   = 'Faturamento Automação — Interweg Benefícios';
require_once __DIR__ . '/_header.php';

// Trava: só TI acessa.
if (empty($isTI)) { header('Location: demandas.php'); exit; }

$rows = [];
$temTabela = true;
try {
    $rows = db()->query("SELECT id, origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa,
        razao_social, analista_nome, competencia, competencia_real, data_emissao, data_vencimento, status_envio,
        data_envio, numero_vidas, premio_liquido, percentual_comissao, valor_previsto, caminho_pasta
        FROM faturamento_automacao ORDER BY empresa")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $temTabela = false; }
$total = count($rows);
$premio = 0; foreach ($rows as $r) { $premio += (float)$r['premio_liquido']; }
?>
<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Faturamento Automação</div>
      <div class="page-sub">Área exclusiva do TI — AMIL Set/26</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <button class="btn btn-primary" onclick="faeOpen()"><span class="material-symbols-rounded" style="font-size:17px">upload</span> Importar vidas/prêmio</button>
      <span style="font-weight:700;color:var(--accent)"><?= number_format($total,0,',','.') ?> registros</span>
    </div>
  </div>

  <!-- Modal importar vidas/premio -->
  <div class="ov" id="fae-modal" onclick="if(event.target===this)faeClose()">
    <div class="modal" style="width:720px;max-width:96vw">
      <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">upload_file</span> Importar Vidas / Prêmio</div>
      <div class="modal-sub">Casa pela <strong>Apólice/Contrato</strong> e atualiza Nº de vidas e Prêmio Líquido nas linhas.</div>
      <div style="background:rgba(0,191,179,.06);border:1px dashed var(--accent);border-radius:10px;padding:10px 12px;margin:10px 0;font-size:12.5px">
        A planilha precisa ter: <strong>Apólice/Contrato</strong>, <strong>Vidas</strong>, <strong>Prêmio Líquido</strong>. Opcional: <em>Data Envio</em>.
      </div>
      <div class="row3">
        <div class="fg"><label>Data de envio padrão</label><input type="date" id="fae-data"/></div>
        <div class="fg"><label>Modo</label><select id="fae-modo"><option value="sobrescrever">Sobrescrever valores</option><option value="completar">Só preencher onde vazio</option></select></div>
        <div class="fg"><label>Arquivo (.xlsx)</label><input type="file" id="fae-file" accept=".xlsx,.xls" onchange="faePreview()"/></div>
      </div>
      <div id="fae-preview" style="display:none;margin-top:10px;padding:10px;background:var(--bg);border:1px solid var(--border);border-radius:8px;font-size:12.5px;max-height:260px;overflow:auto"></div>
      <div class="mfooter" style="margin-top:14px">
        <button class="btn btn-outline" onclick="faeClose()">Cancelar</button>
        <button class="btn btn-primary" id="fae-btn" onclick="faeAplicar(this)" disabled>Aplicar</button>
      </div>
    </div>
  </div>

<?php if (!$temTabela): ?>
  <div style="padding:16px;background:#fff3cd;border:1px solid #ffe69c;border-radius:8px;color:#664d03">
    A tabela <b>faturamento_automacao</b> ainda não existe. Rode o script <code>_automacao_criar_amil.sql</code>.
  </div>
<?php else: ?>
  <div class="cards" style="grid-template-columns:repeat(2,1fr)">
    <div class="card c-teal"><div class="card-label">Total AMIL Set/26</div><div class="card-val" id="fa-total"><?= $total ?></div><div class="card-sub">registros</div></div>
    <div class="card c-blue"><div class="card-label">Prêmio Líquido</div><div class="card-val" style="font-size:22px"><?= 'R$ '.number_format($premio,2,',','.') ?></div><div class="card-sub">soma</div></div>
  </div>

  <div class="filters">
    <input class="search-inp" type="text" id="fa-q" placeholder="Buscar empresa, grupo, CNPJ, contrato..." autocomplete="off"/>
    <select id="fa-analista"><option value="">Todos os analistas</option></select>
    <select id="fa-status"><option value="">Todos os status</option></select>
    <span id="fa-count" style="color:var(--muted);font-size:13px"></span>
  </div>

  <div class="table-wrap"><div class="table-head"><div class="table-title">AMIL — Set/26</div></div>
  <div style="overflow:auto"><table>
    <thead><tr><th>#</th><th>Ramo</th><th>Contrato / CNPJ</th><th>Grupo / Empresa</th><th>Analista</th>
      <th>Mês envio</th><th>Competência</th><th>Emissão</th><th>Vencimento</th><th>Status</th><th>Vidas</th><th>Prêmio Líq.</th><th>% Com.</th><th>Ler fatura</th></tr></thead>
    <tbody id="fa-body"></tbody>
  </table></div></div>

<script>
const FA=<?= json_encode($rows, JSON_UNESCAPED_UNICODE) ?>;
const g=id=>document.getElementById(id);
const money=v=>v==null||v===''?'—':'R$ '+Number(v).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
const fd=d=>{if(!d||d==='0000-00-00')return '—';const m=String(d).match(/^(\d{4})-(\d{2})-(\d{2})/);return m?`${m[3]}/${m[2]}/${m[1]}`:d;};
function fill(id,label,vals){const s=g(id);s.innerHTML='<option value="">'+label+'</option>';[...new Set(vals.filter(Boolean))].sort().forEach(v=>{const o=document.createElement('option');o.value=v;o.textContent=v;s.appendChild(o);});}
fill('fa-analista','Todos os analistas',FA.map(r=>r.analista_nome));
fill('fa-status','Todos os status',FA.map(r=>r.status_envio));
function render(){
  const q=g('fa-q').value.toLowerCase().trim(),an=g('fa-analista').value,st=g('fa-status').value;
  const out=FA.filter(r=>{
    if(an&&r.analista_nome!==an)return false; if(st&&r.status_envio!==st)return false;
    if(q){const h=((r.empresa||'')+' '+(r.grupo||'')+' '+(r.cnpj||'')+' '+(r.numero_contrato||'')).toLowerCase();if(!h.includes(q))return false;}
    return true;
  });
  g('fa-count').textContent=out.length+' de '+FA.length;
  const esc=s=>{const d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
  g('fa-body').innerHTML=out.map(r=>`<tr>
    <td>${esc(r.id)}</td><td>${esc(r.ramo)}</td>
    <td><b>${esc(r.numero_contrato)}</b><br><span style="color:var(--muted);font-size:11px">${esc(r.cnpj)}</span></td>
    <td><b>${esc(r.grupo)}</b><br><span style="color:var(--muted);font-size:11px">${esc(r.empresa)}</span></td>
    <td>${esc(r.analista_nome)}</td><td>${esc(r.competencia)}</td><td>${esc(r.competencia_real)||'—'}</td>
    <td>${fd(r.data_emissao)}</td><td>${fd(r.data_vencimento)}</td><td>${esc(r.status_envio)||'—'}</td>
    <td>${esc(r.numero_vidas)||'—'}</td><td>${money(r.premio_liquido)}</td>
    <td>${r.percentual_comissao!=null&&r.percentual_comissao!==''?(+(Number(r.percentual_comissao)*100).toFixed(2))+'%':'—'}</td>
    <td style="white-space:nowrap"><button class="btn btn-outline btn-sm" title="Ler fatura (PDF/Excel/imagem) e preencher vidas e prêmio" onclick="faUpload(${r.id})"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">upload</span></button></td></tr>`).join('');
}
function faUpload(id){
  const inp=document.createElement('input'); inp.type='file'; inp.accept='.pdf,.xlsx,.xls,.png,.jpg,.jpeg';
  inp.onchange=async()=>{ const f=inp.files[0]; if(f) await faEnviarFatura(id,f); };
  inp.click();
}
async function faEnviarFatura(id,f){
  const ext=(f.name.split('.').pop()||'').toLowerCase();
  let body;
  try{
    if(ext==='xlsx'||ext==='xls'){
      const buf=await f.arrayBuffer(); const wb=XLSX.read(new Uint8Array(buf),{type:'array'});
      let txt=''; wb.SheetNames.forEach(n=>{txt+=XLSX.utils.sheet_to_csv(wb.Sheets[n])+'\n';});
      body={id,kind:'text',data:txt};
    } else {
      const b64=await new Promise(res=>{const r=new FileReader();r.onload=()=>res(String(r.result).split(',')[1]);r.readAsDataURL(f);});
      body={id,kind:(ext==='pdf'?'pdf':'image'),data:b64,mime:f.type};
    }
  }catch(e){alert('Erro ao ler o arquivo: '+e.message);return;}
  const btnMsg='Lendo a fatura com IA...';
  const r=await fetch('ler_fatura_automacao.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(x=>x.json()).catch(()=>({erro:'conexão'}));
  if(r.erro){alert('Erro: '+r.erro+(r.bruto?('\n'+r.bruto):''));return;}
  const row=FA.find(x=>+x.id===id);
  if(row){ if(r.vidas!=null)row.numero_vidas=r.vidas; if(r.premio!=null)row.premio_liquido=r.premio; if(r.previsto!=null)row.valor_previsto=r.previsto; row.status_envio='Enviado'; row._lida=true; render(); }
  alert('Fatura lida ✓\nVidas: '+(r.vidas??'—')+'\nPrêmio: '+(r.premio??'—'));
}
async function faApiPost(a,b){const r=await fetch('api.php?action='+a,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)});return r.json();}
// ===== Importar vidas/premio =====
let _faeRows=[];
function faeClean(v){return String(v==null?'':v).trim().toUpperCase().replace(/\s+/g,'');}
function faeNum(v){if(v==null||v==='')return null;let s=String(v).replace(/[^\d,.-]/g,'');if(s.indexOf(',')>=0&&s.indexOf('.')>=0)s=s.replace(/\./g,'').replace(',','.');else s=s.replace(',','.');const n=parseFloat(s);return isNaN(n)?null:n;}
function faeDate(v){if(!v)return null;if(typeof v==='number'){const d=new Date(Math.round((v-25569)*86400*1000));return d.toISOString().slice(0,10);}const m=String(v).match(/(\d{2})\/(\d{2})\/(\d{4})/);if(m)return `${m[3]}-${m[2]}-${m[1]}`;const m2=String(v).match(/^(\d{4})-(\d{2})-(\d{2})/);return m2?m2[0]:null;}
function faeOpen(){g('fae-file').value='';g('fae-preview').style.display='none';g('fae-btn').disabled=true;_faeRows=[];g('fae-modal').style.display='flex';}
function faeClose(){g('fae-modal').style.display='none';}
function faePreview(){
  const file=g('fae-file').files[0]; if(!file)return;
  if(typeof XLSX==='undefined'){alert('Biblioteca de planilha não carregou. Recarregue a página.');return;}
  const reader=new FileReader();
  reader.onload=e=>{
    try{
      const wb=XLSX.read(new Uint8Array(e.target.result),{type:'array'});
      const ws=wb.Sheets[wb.SheetNames[0]];
      const rows=XLSX.utils.sheet_to_json(ws,{header:1,defval:'',raw:true});
      const norm=s=>String(s||'').trim().toLowerCase();
      let hrow=-1,ci=-1,vi=-1,pi=-1,di=-1;
      for(let k=0;k<Math.min(rows.length,15);k++){
        const cel=(rows[k]||[]).map(norm);
        const c=cel.findIndex(x=>x.includes('contrat')||x.includes('apolice')||x.includes('apólice'));
        const v=cel.findIndex(x=>x.includes('vida'));
        const p=cel.findIndex(x=>x.includes('premio')||x.includes('prêmio'));
        if(c>=0&&v>=0&&p>=0){hrow=k;ci=c;vi=v;pi=p;di=cel.findIndex(x=>x.includes('envio'));break;}
      }
      if(hrow<0){alert('Não achei o cabeçalho (Contrato, Vidas, Prêmio) na planilha.');return;}
      const map=new Map();
      FA.forEach(d=>{const k=faeClean(d.numero_contrato);if(!k)return;if(!map.has(k))map.set(k,[]);map.get(k).push(d);});
      _faeRows=[];let cas=0,orf=0;const orfL=[];
      for(let i=hrow+1;i<rows.length;i++){const r=rows[i];if(!r)continue;const k=faeClean(r[ci]);if(!k)continue;
        const mt=map.get(k)||[];
        if(mt.length===0){orf++;orfL.push(k);}
        else{cas++;_faeRows.push({id:mt[0].id,vidas:faeNum(r[vi]),premio:faeNum(r[pi]),dataEnvio:di>=0?faeDate(r[di]):null});}
      }
      g('fae-preview').innerHTML=`<div style="display:flex;gap:8px;margin-bottom:6px">
        <div style="padding:8px;border-radius:6px;background:rgba(63,185,80,.12);color:#3fb950"><b>${cas}</b> serão atualizadas</div>
        <div style="padding:8px;border-radius:6px;background:rgba(212,61,61,.12);color:#d43d3d"><b>${orf}</b> não encontradas</div></div>`
        + (orf?'<details><summary style="cursor:pointer;color:#d43d3d">Ver não encontradas</summary><div style="margin-top:6px">'+orfL.slice(0,60).map(k=>'Contrato <b>'+k+'</b>').join('<br>')+'</div></details>':'');
      g('fae-preview').style.display='';g('fae-btn').disabled=(cas===0);
    }catch(err){alert('Erro ao ler planilha: '+err.message);}
  };
  reader.readAsArrayBuffer(file);
}
async function faeAplicar(btn){
  if(!_faeRows.length){alert('Nada a aplicar.');return;}
  const dpad=g('fae-data').value||new Date().toISOString().slice(0,10);
  const modo=g('fae-modo').value;
  const updates=_faeRows.map(r=>({id:r.id,vidas:r.vidas,premio:r.premio,data_envio:r.dataEnvio||dpad}));
  if(!confirm('Aplicar '+updates.length+' atualização(ões)?'))return;
  btn.disabled=true;
  const r=await faApiPost('importar_fechamento_automacao',{updates,modo});
  btn.disabled=false;
  if(!r.ok){alert(r.msg||'Erro');return;}
  alert(r.afetados+' linha(s) atualizada(s). A página vai recarregar.');
  location.reload();
}
async function faEditarCaminho(id){
  const r=FA.find(x=>+x.id===id); if(!r)return;
  const atual=r.caminho_pasta||caminho(r);
  const novo=prompt('Caminho da pasta desta linha (deixe vazio para voltar ao automático):',atual);
  if(novo===null)return;
  const res=await faApiPost('atualizar_caminho_automacao',{id,caminho_pasta:novo.trim()});
  if(!res.ok){alert(res.msg||'Erro ao salvar');return;}
  r.caminho_pasta=novo.trim()||null; render();
}
const _MES={Jan:'01',Fev:'02',Mar:'03',Abr:'04',Mai:'05',Jun:'06',Jul:'07',Ago:'08',Set:'09',Out:'10',Nov:'11',Dez:'12'};
function caminho(r){
  if(r.caminho_pasta) return r.caminho_pasta;
  const c=String(r.competencia||'').split('/'); const mm=_MES[c[0]]||'??'; const ano='20'+(c[1]||'');
  const razao=r.razao_social||r.empresa||'';
  return `\\\\192.168.5.11\\intermezzo$\\Beneficios\\#EMPRESAS\\${r.grupo||''}\\Faturamento\\${ano}\\${mm}.${ano}\\${r.ramo||''}\\${r.numero_contrato||''} - ${razao}`;
}
function faCopiar(txt){ navigator.clipboard.writeText(txt).then(()=>{},()=>{}); }
['fa-q','fa-analista','fa-status'].forEach(id=>g(id).addEventListener('input',render));
render();
</script>
<?php endif; ?>
</main>
<?php require_once __DIR__ . '/_footer.php'; ?>
