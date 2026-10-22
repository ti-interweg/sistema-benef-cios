// ═══════════════════════════════════════════════════════════════
// PERFIL.JS - Edição do próprio usuário
// ═══════════════════════════════════════════════════════════════

window.USER_ID = (typeof USER_ID !== 'undefined') ? USER_ID : 0;

if (typeof window.toast !== 'function') window.toast = m => alert(m);
if (typeof window.apiGet !== 'function')  window.apiGet  = async (a,p={}) => (await fetch('api.php?'+new URLSearchParams({action:a,...p}))).json();
if (typeof window.apiPost !== 'function') window.apiPost = async (a,b={}) => (await fetch('api.php?action='+encodeURIComponent(a),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)})).json();

document.addEventListener('DOMContentLoaded', loadPerfil);

// Mapeia o enum 'tipo' para um label amigável
function _perfilLabelTipo(t){
  const map = {
    admin:           'Administrador',
    analista:        'Analista',
    financeiro:      'Financeiro',
    novos_negocios:  'Novos Negócios',
    visualizador:    'Visualizador',
  };
  return map[(t || '').toLowerCase()] || 'Analista';
}

async function loadPerfil(){
  try {
    const r = await apiGet('meu_perfil');
    if (!r.ok) { toast('Não foi possível carregar seu perfil'); return; }
    const me = r.data;
    document.getElementById('pf-nome').value  = me.nome  || '';
    document.getElementById('pf-login').value = me.login || '';
    document.getElementById('pf-tipo').value  = _perfilLabelTipo(me.tipo);
    document.getElementById('pf-senha').value  = '';
    document.getElementById('pf-senha2').value = '';
    // Guarda a tabela de origem pro save saber pra qual endpoint mandar
    window._perfilOrigem = me.origem_tabela || 'analistas';
  } catch(e){
    console.error('Erro ao carregar perfil:', e);
    toast('Erro: ' + e.message);
  }
}

async function savePerfil(){
  const nome   = document.getElementById('pf-nome').value.trim();
  const login  = document.getElementById('pf-login').value.trim();
  const senha  = document.getElementById('pf-senha').value.trim();
  const senha2 = document.getElementById('pf-senha2').value.trim();

  if (!nome || !login){ toast('Nome e login são obrigatórios'); return; }
  if (senha && senha !== senha2){ toast('As senhas não coincidem'); return; }

  const body = { nome, login };
  if (senha) body.senha = senha;

  // Endpoint novo que detecta automaticamente a tabela (analistas ou usuarios)
  const r = await apiPost('editar_meu_perfil', body);
  if (r.ok){
    toast('Perfil atualizado com sucesso!');
    // Atualiza nome na sidebar sem recarregar
    const userNameEl = document.querySelector('.user-name');
    if (userNameEl) userNameEl.textContent = nome;
  } else {
    toast(r.msg || 'Erro ao salvar');
  }
}
