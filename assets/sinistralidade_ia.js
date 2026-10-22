// ═══════════════════════════════════════════════════════════════
// IA / BI - leitura por IA de visão e análise de BI
// (separado do sinistralidade.js para não estourar o tamanho do arquivo)
// ═══════════════════════════════════════════════════════════════

// Resume a base bruta de utilização (auto-detecta colunas) → texto curto pra IA narrar
function sinAgregarBI(aoa){
  const rows=(aoa||[]).filter(r=>r && r.some(c=>c!=null && String(c).trim()!==''));
  if(rows.length<3) return null;
  const ncol=Math.max(...rows.map(r=>r.length));
  const cell=(r,i)=>(r[i]==null?'':String(r[i]).trim());
  const numf=s=>{ s=String(s).replace(/[R$\s%]/g,''); if(!/\d/.test(s)) return 0; if(/,\d{1,2}$/.test(s)) s=s.replace(/\./g,'').replace(',','.'); const n=parseFloat(s); return isNaN(n)?0:n; };
  const colVals=i=>rows.map(r=>cell(r,i));
  let costIdx=-1,bestDec=0;
  for(let i=0;i<ncol;i++){ const dec=colVals(i).filter(v=>/\d[.,]\d{1,2}$/.test(v)).length; if(dec>bestDec && dec>rows.length*0.25){ bestDec=dec; costIdx=i; } }
  if(costIdx<0) return null;
  const total=rows.reduce((a,r)=>a+numf(cell(r,costIdx)),0);
  const findCol=test=>{ let bi=-1,bs=0; for(let i=0;i<ncol;i++){ const s=colVals(i).filter(test).length; if(s>bs){bs=s;bi=i;} } return bs>0?bi:-1; };
  const CATS=[/consulta/i,/laborator|exame|ressonan|tomograf|imagem|raio|ultrass|analise|radiolog/i,/interna/i,/terapia|sess|psico|fono|fisio/i,/medicament/i];
  let tipoIdx=-1,tipoScore=0;
  for(let i=0;i<ncol;i++){ const dv=[...new Set(colVals(i))].filter(Boolean); if(dv.length<3||dv.length>60) continue; const sc=CATS.filter(re=>dv.some(v=>re.test(v))).length; if(sc>tipoScore){ tipoScore=sc; tipoIdx=i; } }
  if(tipoScore<2) tipoIdx=-1;
  const sexoIdx=findCol(v=>/^(masculino|feminino)$/i.test(v));
  const tdIdx=findCol(v=>/^(titular|dependente)$/i.test(v));
  const reembIdx=findCol(v=>/^(reembolso|rede)$/i.test(v));
  const prestIdx=findCol(v=>/hospital|laborat|instituto|clinic|cooper|fleury|mater|centro|unimed|hermes|oswaldo/i.test(v));
  let benefIdx=-1;
  for(let i=0;i<ncol;i++){ if(i===costIdx) continue; const c=colVals(i); if(c.filter(v=>/^\d{10,11}$/.test(v)).length>rows.length*0.5 && new Set(c).size>=10){ benefIdx=i; break; } }
  if(benefIdx<0) for(let i=0;i<ncol;i++){ if(i===costIdx) continue; const c=colVals(i); const dist=new Set(c).size; if(c.filter(v=>/^\d{6,9}$/.test(v)).length>rows.length*0.5 && dist>=10 && dist<rows.length*0.7){ benefIdx=i; break; } }
  let nascIdx=-1;
  for(let i=0;i<ncol;i++){ if(colVals(i).filter(v=>/^(19|20)\d{2}-\d{2}-\d{2}/.test(v) && parseInt(v.slice(0,4),10)<2015).length>rows.length*0.3){ nascIdx=i; break; } }
  const fmt=n=>'R$ '+n.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
  const L=['Custo total da utilização: '+fmt(total)+' ('+rows.length+' procedimentos)'];
  if(benefIdx>=0){ const m={}; rows.forEach(r=>{ const b=cell(r,benefIdx); m[b]=(m[b]||0)+numf(cell(r,costIdx)); }); const vals=Object.values(m).sort((a,b)=>b-a); const top=vals.slice(0,10).reduce((a,b)=>a+b,0); L.push(vals.length+' beneficiários. Os 10 maiores usuários concentram '+(total?(top/total*100).toFixed(2):'0')+'% do custo total.'); }
  if(tipoIdx>=0){ const cat={}; const add=(k,v)=>{ cat[k]=cat[k]||[0,0]; cat[k][0]++; cat[k][1]+=v; }; let el=[0,0],ps=[0,0];
    rows.forEach(r=>{ const t=cell(r,tipoIdx).toUpperCase(), v=numf(cell(r,costIdx)); let k;
      if(/CONSULTA/.test(t)){ k='Consultas'; if(/PRONTO|URG/.test(t)){ps[0]++;ps[1]+=v;}else{el[0]++;el[1]+=v;} }
      else if(/LABORATOR|RESSONAN|TOMOGRAF|ULTRASS|IMAGEM|EXAME|RADIOLOG|ANALISE/.test(t)) k='Exames';
      else if(/INTERNA/.test(t)) k='Internações';
      else if(/TERAPIA|SESS|PSICO|FONO|FISIO/.test(t)) k='Terapias';
      else if(/MEDICAMENT/.test(t)) k='Medicamentos'; else k='Outros';
      add(k,v); });
    Object.keys(cat).forEach(k=>L.push(k+': '+cat[k][0]+' itens, '+fmt(cat[k][1])));
    if(el[0]||ps[0]) L.push('Consultas eletivas: '+el[0]+' ('+fmt(el[1])+'); pronto-socorro: '+ps[0]+' ('+fmt(ps[1])+').');
    const rank=Object.entries(cat).sort((a,b)=>b[1][1]-a[1][1]); if(rank.length) L.push('Ranking de grupos por custo: '+rank.map(x=>x[0]+' ('+fmt(x[1][1])+')').join(' > ')+'.');
  }
  let planoIdx=-1;
  for(let i=0;i<ncol;i++){ if([costIdx,sexoIdx,tdIdx,reembIdx,tipoIdx,benefIdx].includes(i)) continue; const dv=[...new Set(colVals(i))].filter(Boolean); if(dv.length>=2 && dv.length<=12 && dv.every(v=>/[a-zà-ú]/i.test(v)) && (dv.reduce((a,v)=>a+v.length,0)/dv.length)>10){ planoIdx=i; break; } }
  if(planoIdx>=0){ const m={}; rows.forEach(r=>{ const p=cell(r,planoIdx); if(p) m[p]=(m[p]||0)+numf(cell(r,costIdx)); }); const ent=Object.entries(m).sort((a,b)=>b[1]-a[1]); if(ent.length>=2) L.push('Utilização por produto/plano: '+ent.map(x=>x[0]+' '+fmt(x[1])+' ('+(total?(x[1]/total*100).toFixed(1):'0')+'%)').join('; ')+'.'); }
  if(prestIdx>=0){ const m={}; rows.forEach(r=>{ const p=cell(r,prestIdx); if(p) m[p]=(m[p]||0)+numf(cell(r,costIdx)); }); const top=Object.entries(m).sort((a,b)=>b[1]-a[1]).slice(0,4); if(top.length) L.push('Top prestadores por custo: '+top.map(x=>x[0]+' ('+fmt(x[1])+')').join('; ')+'.'); }
  if(reembIdx>=0){ const re=rows.filter(r=>/REEMB/i.test(cell(r,reembIdx))).reduce((a,r)=>a+numf(cell(r,costIdx)),0); if(re>0) L.push('Reembolso total: '+fmt(re)+'.'); }
  if(sexoIdx>=0){ const m={}; rows.forEach(r=>{ const s=cell(r,sexoIdx).toUpperCase(); m[s]=(m[s]||0)+numf(cell(r,costIdx)); }); L.push('Custo por sexo: '+Object.entries(m).map(x=>x[0]+' '+(total?(x[1]/total*100).toFixed(2):'0')+'%').join('; ')+'.'); }
  if(tdIdx>=0){ const m={}; rows.forEach(r=>{ const s=cell(r,tdIdx).toUpperCase(); m[s]=(m[s]||0)+numf(cell(r,costIdx)); }); L.push('Custo titular/dependente: '+Object.entries(m).map(x=>x[0]+' '+fmt(x[1])+' ('+(total?(x[1]/total*100).toFixed(1):'0')+'%)').join('; ')+'.'); }
  if(nascIdx>=0){ const seen={}; let soma=0,n=0; const yr=new Date().getFullYear(); rows.forEach(r=>{ const b=benefIdx>=0?cell(r,benefIdx):Math.random(); const m=cell(r,nascIdx).match(/^(\d{4})-/); if(m && !seen[b]){ seen[b]=1; soma+=yr-parseInt(m[1],10); n++; } }); if(n) L.push('Idade média dos beneficiários com utilização: '+Math.round(soma/n)+' anos.'); }
  return L.join('\n');
}

// Lê a planilha BRUTA de utilização (Excel) e usa a IA pra preencher os campos do BI
async function sinImportarBI(input){
  const file=input.files&&input.files[0]; if(!file) return;
  const status=document.getElementById('sin-bi-status');
  const setS=t=>{ if(status) status.innerHTML=t; };
  setS('<span style="color:#0a8a8d"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle">hourglass_top</span> Lendo a planilha e analisando com IA... pode levar até 1 min.</span>');
  try{
    if(typeof XLSX==='undefined') await sinLoadScript('https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js');
    const buf=await file.arrayBuffer();
    const wb=XLSX.read(new Uint8Array(buf),{type:'array'});
    const ws=wb.Sheets[wb.SheetNames[0]];
    const aoa=XLSX.utils.sheet_to_json(ws,{header:1,defval:''});
    let dados=sinAgregarBI(aoa);
    if(!dados){ dados=XLSX.utils.sheet_to_csv(ws); if(dados.length>120000) dados=dados.slice(0,120000); }
    const fd=new FormData(); fd.append('dados', dados);
    const r=await fetch('analise_bi.php',{method:'POST',body:fd});
    const j=await r.json().catch(()=>null);
    if(!r.ok || !j || j.erro){ setS('<span style="color:#c0392b">Erro: '+((j&&j.erro)||('HTTP '+r.status))+(j&&j.detalhe?(' — '+String(j.detalhe).slice(0,400)):'')+'</span>'); return; }
    const map={high:'sin-bi-high',consultas:'sin-bi-consultas',exames:'sin-bi-exames',internacoes:'sin-bi-internacoes',terapias:'sin-bi-terapias',prestadores:'sin-bi-prestadores',produtos:'sin-bi-produtos',ranking:'sin-bi-ranking',demografico:'sin-bi-demografico'};
    let n=0; Object.keys(map).forEach(k=>{ const el=document.getElementById(map[k]); if(el && j[k]){ el.value=j[k]; n++; } });
    setS('<span style="color:#0a8a8d">✓ '+n+' campo(s) preenchidos pela IA — revise.</span>');
    toast(n+' campo(s) de BI preenchidos pela IA. Revise antes de gerar.');
  }catch(e){ console.error(e); setS('<span style="color:#c0392b">Erro ao ler a planilha: '+e.message+'</span>'); }
  finally{ input.value=''; }
}

// Lê a imagem com IA de visão (Claude) via endpoint PHP. Retorna {cols,rows} ou null.
async function sinLerIA(file){
  try{
    const fd=new FormData(); fd.append('imagem', file);
    const r=await fetch('ocr_sinistralidade.php',{method:'POST',body:fd});
    const j=await r.json().catch(()=>null);
    if(r.ok && j && j.cols && Array.isArray(j.rows) && j.rows.length) return j;
    // Falhou: NÃO engole o erro — devolve pra ser exibido na tela e diagnosticar.
    const erro = (j && j.erro) || ('HTTP ' + r.status);
    const detalhe = (j && (j.detalhe || j.bruto)) || '';
    console.warn('IA visão falhou:', erro, detalhe);
    return { erro: erro, detalhe: detalhe };
  }catch(e){ console.warn('IA visão exceção:', e); return { erro: 'Falha de conexão com a IA: ' + (e.message||e) }; }
}
