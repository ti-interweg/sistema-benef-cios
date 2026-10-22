/* ============================================================
   Base de Clientes — lista os clientes concluídos (empresas)
   ============================================================ */
let _bcTimer = null;

async function bcLoad(){
  const tb = document.getElementById('bc-tbody');
  if (!tb) return;
  const q = (document.getElementById('bc-search') || {}).value || '';
  try {
    const r = await fetch('api_base_clientes.php?action=listar&search=' + encodeURIComponent(q)).then(x => x.json());
    const data = (r && r.data) || [];
    const cnt = document.getElementById('bc-count');
    if (cnt) cnt.textContent = data.length + (data.length === 1 ? ' cliente' : ' clientes');

    const esc  = s => String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const fmtD = s => { if (!s) return '—'; const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]}` : esc(s); };

    if (!data.length) {
      tb.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--muted);padding:20px">Nenhum cliente encontrado.</td></tr>';
      return;
    }
    tb.innerHTML = data.map(d => `<tr>
      <td><b>${esc(d.nome || '—')}</b>${(d.grupo && d.grupo !== d.nome) ? `<br><span style="color:var(--muted);font-size:11px">${esc(d.grupo)}</span>` : ''}</td>
      <td style="font-size:12px">${esc(d.cnpj || '—')}</td>
      <td style="font-size:12px">${esc(d.seguradora || '—')}</td>
      <td style="font-size:12px">${esc(d.numero_contrato || '—')}</td>
      <td style="font-size:12px">${esc(d.ramo || '—')}</td>
      <td style="text-align:center">${d.numero_vidas != null && d.numero_vidas !== '' ? esc(d.numero_vidas) : '—'}</td>
      <td style="font-size:12px;white-space:nowrap">${fmtD(d.inicio_vigencia)}</td>
      <td style="font-size:12px">${esc(d.analista_nome || '—')}</td>
      <td style="white-space:nowrap;text-align:center">
        <a class="btn btn-outline btn-sm" href="passagem_bastao.php?ver=${d.id}" title="Visualizar todos os dados (somente leitura)" style="padding:4px 8px"><span class="material-symbols-rounded" style="font-size:14px">visibility</span></a>
        <a class="btn btn-outline btn-sm" href="passagem_bastao.php?edit=${d.id}" title="Editar cadastro" style="padding:4px 8px"><span class="material-symbols-rounded" style="font-size:14px">edit</span></a>
        <button class="btn btn-outline btn-sm" onclick="bcExcluir(${d.id}, this)" title="Excluir cliente" style="padding:4px 8px;border-color:#e5484d;color:#e5484d"><span class="material-symbols-rounded" style="font-size:14px">delete</span></button>
      </td>
    </tr>`).join('');
  } catch (e) {
    tb.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#c0392b;padding:20px">Erro ao carregar a base de clientes.</td></tr>';
  }
}

// Excluir cliente (exclusão suave: desativa — some da lista, dados preservados)
async function bcExcluir(id, btn){
  const tr = btn ? btn.closest('tr') : null;
  const nome = tr ? (tr.querySelector('td b') || {}).textContent || ('#' + id) : ('#' + id);
  if (!confirm('Excluir o cliente "' + nome + '" da Base de Clientes?\n\nEle será removido da lista, mas os faturamentos e renovações são preservados. Dá para reverter reativando a empresa.')) return;
  if (btn) btn.disabled = true;
  try {
    const r = await fetch('api_base_clientes.php?action=excluir', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id })
    }).then(x => x.json());
    if (!r.ok) { alert(r.msg || 'Não foi possível excluir.'); if (btn) btn.disabled = false; return; }
    bcLoad();
  } catch (e) {
    alert('Erro de conexão ao excluir.');
    if (btn) btn.disabled = false;
  }
}

// debounce leve na busca (o oninput chama bcLoad direto; aqui só garante init)
document.addEventListener('DOMContentLoaded', bcLoad);
