// ═══════════════════════════════════════════════════════════════
// RESUMO MENSAL DE MOVIMENTAÇÕES
// Lê demandas (movimentações) por período, agrupa por cliente, gera
// e-mail de validação, envia e registra histórico. 100% aditivo.
// ═══════════════════════════════════════════════════════════════

// Helpers defensivos
if (typeof window.toast !== 'function') window.toast = m => { try { alert(m); } catch(e){} };
if (typeof window.esc !== 'function')   window.esc   = s => { const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; };
if (typeof window.openModal !== 'function')  window.openModal  = id => { const el=document.getElementById(id); if(el) el.classList.add('show'); };
if (typeof window.closeModal !== 'function') window.closeModal = id => { const el=document.getElementById(id); if(el) el.classList.remove('show'); };

const RM_API = 'api_resumo.php';
const RM_MESES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

let _rmData = [];   // resultado do período
let _rmDe = '', _rmAte = '';   // período (datas) selecionado

async function rmGet(action, params={}){
  const qs = Object.keys(params).length ? '&' + new URLSearchParams(params).toString() : '';
  const r = await fetch(RM_API + '?action=' + encodeURIComponent(action) + qs);
  return r.json();
}
async function rmPost(action, body={}){
  const r = await fetch(RM_API + '?action=' + encodeURIComponent(action), {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)
  });
  return r.json();
}

document.addEventListener('DOMContentLoaded', () => {
  // Default do período: do 1º dia do mês atual até hoje
  const hoje = new Date();
  const ini  = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
  const fmt  = d => d.getFullYear() + '-' + ('0'+(d.getMonth()+1)).slice(-2) + '-' + ('0'+d.getDate()).slice(-2);
  const de  = document.getElementById('rm-de');
  const ate = document.getElementById('rm-ate');
  if (de)  de.value  = fmt(ini);
  if (ate) ate.value = fmt(hoje);
  // Já carrega a lista ao abrir (sem tela vazia) e recarrega ao mudar os filtros.
  ['rm-de','rm-ate','rm-analista'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', () => { if (typeof rmGerar === 'function') rmGerar(); });
  });
  if (typeof rmGerar === 'function') rmGerar();
  // Popula o filtro de analistas (cada um filtra a própria carteira)
  fetch('api.php?action=analistas').then(r => r.json()).then(r => {
    const sel = document.getElementById('rm-analista');
    if (sel && r && r.data){
      // Somente perfis de analista (exclui financeiro, enfermeira, diretoria,
      // novos negócios etc.) e remove nomes duplicados.
      const vistos = {};
      const analistas = r.data
        .filter(a => String(a.tipo || '').trim().toLowerCase() === 'analista')
        .filter(a => { const k = (a.nome||'').trim().toLowerCase(); if(!k || vistos[k]) return false; vistos[k]=1; return true; })
        .sort((x,y) => String(x.nome||'').localeCompare(String(y.nome||''), 'pt'));
      sel.innerHTML = '<option value="">Todos os analistas</option>' +
        analistas.map(a => `<option value="${(a.nome||'').replace(/"/g,'&quot;')}">${esc(a.nome)}</option>`).join('');
    }
  }).catch(()=>{});
});

function rmShowTab(tab){
  const g = document.getElementById('rm-pane-gerar');
  const h = document.getElementById('rm-pane-hist');
  const tg = document.getElementById('rm-tab-gerar');
  const th = document.getElementById('rm-tab-hist');
  const on  = el => { if(el){ el.classList.add('active'); } };
  const off = el => { if(el){ el.classList.remove('active'); } };
  if (tab === 'hist'){
    if(g) g.style.display='none'; if(h) h.style.display='block';
    on(th); off(tg); rmHistorico();
  } else {
    if(g) g.style.display='block'; if(h) h.style.display='none';
    on(tg); off(th);
  }
}

// ─── GERAR ───────────────────────────────────────────────────
async function rmGerar(){
  _rmDe  = document.getElementById('rm-de').value;
  _rmAte = document.getElementById('rm-ate').value;
  if (!_rmDe || !_rmAte){ toast('Informe o período (De e Até)'); return; }
  const analista = document.getElementById('rm-analista').value;
  const cont = document.getElementById('rm-clientes');
  cont.innerHTML = '<div style="padding:24px;color:var(--muted)">Buscando movimentações…</div>';
  let r;
  try { r = await rmGet('resumo_periodo', { data_de:_rmDe, data_ate:_rmAte, analista }); }
  catch(e){ cont.innerHTML = '<div style="padding:24px;color:#c0392b">Erro ao buscar dados.</div>'; return; }
  if (!r || !r.ok){ cont.innerHTML = '<div style="padding:24px;color:#c0392b">'+esc((r&&r.msg)||'Erro')+'</div>'; return; }
  _rmData = r.data || [];
  if (!_rmData.length){
    cont.innerHTML = `<div style="padding:24px;color:var(--muted)">Nenhuma movimentação encontrada no período de ${rmFmtData(_rmDe)} a ${rmFmtData(_rmAte)}.</div>`;
    return;
  }
  cont.innerHTML = _rmData.map((c, idx) => `
    <div class="rm-cli">
      <div class="rm-cli-icon"><span class="material-symbols-rounded" style="font-size:20px">apartment</span></div>
      <div class="rm-cli-info">
        <div class="rm-cli-nome">${esc(c.grupo)}</div>
        <div class="rm-cli-meta">
          <span class="rm-badge">${c.qtd} mov.</span>
          ${c.emails
            ? `<span class="rm-emails" title="${esc(c.emails).replace(/"/g,'&quot;')}">${esc(c.emails)}</span>`
            : `<span class="rm-noemail">sem e-mail cadastrado</span>`}
        </div>
      </div>
      <button class="btn btn-primary rm-cli-btn" onclick="rmAbrirPreview(${idx})"><span class="material-symbols-rounded" style="font-size:17px">visibility</span> Pré-visualizar / Enviar</button>
    </div>
  `).join('');
}

// ─── PRÉ-VISUALIZAÇÃO ────────────────────────────────────────
function rmFmtData(s){
  if (!s) return '—';
  const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})/);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
}
function rmDataLimite(){
  const d = new Date(); d.setDate(d.getDate() + 7);
  return ('0'+d.getDate()).slice(-2) + '/' + ('0'+(d.getMonth()+1)).slice(-2) + '/' + d.getFullYear();
}
// Só estes tipos vão para o corpo do e-mail do resumo (movimentações cadastrais
// relevantes para o cliente). Compara por início do texto, pra pegar variações
// (ex.: "Inclusão de titular", "Abertura de subfatura empresa").
const RM_TIPOS_EMAIL = [
  'inclusão', 'exclusão', 'alteração cadastral',
  'plano extensão', 'plano de extensão',
  'subfatura', 'abertura de subfatura', 'transferência de planos'
];
function rmFiltrarTiposEmail(movs){
  return (movs || []).filter(m => {
    const t = String(m.tipo || '').trim().toLowerCase();
    return RM_TIPOS_EMAIL.some(p => t.startsWith(p));
  });
}
function rmMontarTabela(movs){
  const td = 'border:1px solid #ddd;padding:5px 8px';
  const linhas = movs.map(m => `
    <tr>
      <td style="${td};white-space:nowrap">${esc(rmFmtData(m.recebimento))}</td>
      <td style="${td}">${esc(m.empresa||'—')}</td>
      <td style="${td}">${esc(m.seguradora||'—')}</td>
      <td style="${td}">${esc(m.ramo||'—')}</td>
      <td style="${td}">${esc(m.tipo||'—')}</td>
      <td style="${td}">${esc(m.situacao||'—')}</td>
      <td style="${td};white-space:nowrap">${esc(rmFmtData(m.ini_tratativa))}</td>
      <td style="${td};white-space:nowrap">${esc(rmFmtData(m.conclusao))}</td>
      <td style="${td}">${esc(m.analista||'—')}</td>
      <td style="${td}">${esc(m.beneficiario||'—')}</td>
      <td style="${td}">${esc(m.descricao||'—')}</td>
      <td style="${td}">${esc(m.protocolo||'—')}</td>
    </tr>`).join('');
  const th = 'border:1px solid #ddd;padding:5px 8px;text-align:left;white-space:nowrap';
  return `<table style="border-collapse:collapse;width:100%;font-size:11px;margin:10px 0">
      <thead><tr style="background:#00C0B3;color:#fff">
        <th style="${th}">Recebimento</th>
        <th style="${th}">Empresa</th>
        <th style="${th}">Seguradora</th>
        <th style="${th}">Ramo</th>
        <th style="${th}">Tipo</th>
        <th style="${th}">Situação</th>
        <th style="${th}">Ini. Tratativa</th>
        <th style="${th}">Conclusão</th>
        <th style="${th}">Analista</th>
        <th style="${th}">Beneficiário</th>
        <th style="${th}">Descrição</th>
        <th style="${th}">Protocolo</th>
      </tr></thead>
      <tbody>${linhas}</tbody>
    </table>`;
}
function rmPeriodoLabel(){
  return rmFmtData(_rmDe) + ' a ' + rmFmtData(_rmAte);
}
function rmMontarCorpo(cliente, movs){
  const compLabel = rmPeriodoLabel();
  const movsEmail = rmFiltrarTiposEmail(movs);
  return `<p>Prezados,</p>
<p>Com o objetivo de mantermos a qualidade dos processos e garantirmos que todas as informações estejam devidamente atualizadas junto às operadoras, encaminhamos abaixo o resumo das movimentações realizadas no período de <strong>${esc(compLabel)}</strong>.</p>
${rmMontarTabela(movsEmail)}
<p>Solicitamos, por gentileza, que realizem a validação das informações e nos sinalizem até o dia <strong>${rmDataLimite()}</strong> caso identifiquem qualquer divergência ou necessidade de ajuste.</p>
<p>Essa conferência periódica é uma importante etapa de controle, contribuindo para a prevenção de inconsistências cadastrais, cobranças indevidas e demais impactos operacionais.</p>
<p>Permanecemos à disposição para quaisquer esclarecimentos.</p>
<p style="margin-bottom:2px">Atenciosamente,</p>
<p style="margin:8px 0 0;font-weight:700;color:#0a8a8d;font-size:15px">${esc(window.USER_NOME || 'Equipe Interweg')}</p>
<p style="margin:0;color:#444;font-size:13px">Analista de Benefícios</p>
<p style="margin:4px 0 0;font-size:12px;color:#666">Tel. (31) 3508-6000 &nbsp;|&nbsp; interweg.com.br &nbsp;|&nbsp; Av. Raja Gabáglia, 4.987 – 302 – Belo Horizonte/MG</p>
<p><img src="https://interweg-beneficios.com.br/assinatura_interweg.png" alt="Interweg Seguros e Benefícios" style="max-width:480px;width:100%;height:auto;display:block;margin-top:10px"/></p>`;
}

function rmAbrirPreview(idx){
  const c = _rmData[idx];
  if (!c) return;
  const compLabel = rmPeriodoLabel();
  document.getElementById('rm-modal-sub').textContent = `${c.grupo} · ${c.qtd} movimentação(ões) · ${compLabel}`;
  document.getElementById('rm-dest').value    = c.emails || '';
  document.getElementById('rm-assunto').value = `Resumo de Movimentações ${c.grupo} - ${compLabel}`;
  document.getElementById('rm-corpo').innerHTML = rmMontarCorpo(c.grupo, c.movimentacoes);
  // guarda contexto no botão
  const btn = document.getElementById('rm-btn-enviar');
  btn.dataset.cliente = c.grupo;
  btn.dataset.grupo   = c.grupo || '';
  btn.dataset.qtd     = c.qtd;
  btn.dataset.comp    = compLabel;
  openModal('mod-rm');
}

// ─── ENVIAR ──────────────────────────────────────────────────
async function rmEnviar(btn){
  const dest = document.getElementById('rm-dest').value.trim();
  if (!dest){ toast('Informe o destinatário'); return; }
  const body = {
    cliente:      btn.dataset.cliente || '',
    grupo:        btn.dataset.grupo || '',
    competencia:  btn.dataset.comp || '',
    qtd:          parseInt(btn.dataset.qtd) || 0,
    destinatario: dest,
    assunto:      document.getElementById('rm-assunto').value.trim(),
    corpo:        document.getElementById('rm-corpo').innerHTML
  };
  btn.disabled = true;
  let r;
  try { r = await rmPost('enviar_resumo', body); }
  catch(e){ btn.disabled = false; toast('Falha de conexão ao enviar'); return; }
  btn.disabled = false;
  if (r && r.ok){
    toast('Resumo enviado!');
    closeModal('mod-rm');
  } else {
    toast((r && r.msg) || 'Erro ao enviar');
  }
}

// ─── HISTÓRICO ───────────────────────────────────────────────
async function rmHistorico(){
  const tb = document.getElementById('rm-hist-tbody');
  tb.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px;color:var(--muted)">Carregando…</td></tr>';
  let r;
  try { r = await rmGet('historico_resumos'); } catch(e){ r = null; }
  const rows = (r && r.data) ? r.data : [];
  if (!rows.length){
    tb.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px;color:var(--muted)">Nenhum envio registrado.</td></tr>';
    return;
  }
  tb.innerHTML = rows.map(d => {
    const cor = d.status === 'Enviado' ? '#3fb950' : '#c0392b';
    return `<tr>
      <td style="color:var(--muted);font-size:11.5px">#${d.id}</td>
      <td><strong>${esc(d.cliente)}</strong><br><span style="color:var(--muted);font-size:11.5px">${esc(d.grupo||'')}</span></td>
      <td>${esc(d.competencia)}</td>
      <td style="font-size:11.5px">${esc(d.destinatario||'—')}</td>
      <td style="text-align:center">${d.qtd_movimentacoes}</td>
      <td><span style="color:${cor};font-weight:600">${esc(d.status)}</span></td>
      <td style="font-size:11.5px">${esc(d.usuario_nome||'—')}</td>
      <td style="font-size:11.5px;white-space:nowrap">${esc(rmFmtDataHora(d.enviado_em))}</td>
    </tr>`;
  }).join('');
}
function rmFmtDataHora(s){
  if (!s) return '—';
  const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
  return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : s;
}
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding
// FIM REAL
