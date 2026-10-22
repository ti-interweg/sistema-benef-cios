// ═══════════════════════════════════════════════════════════════
// CONFIGURACOES.JS — Alertas e preferências do sistema
// ═══════════════════════════════════════════════════════════════

if (typeof window.toast !== 'function')  window.toast  = m => alert(m);
if (typeof window.apiGet !== 'function')  window.apiGet  = async (a,p={}) => (await fetch('api.php?'+new URLSearchParams({action:a,...p}))).json();
if (typeof window.apiPost !== 'function') window.apiPost = async (a,b={}) => (await fetch('api.php?action='+encodeURIComponent(a),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)})).json();

document.addEventListener('DOMContentLoaded', loadConfig);

async function loadConfig(){
  try {
    const j = await apiGet('get_config');
    if (j.ok && j.data) {
      if (j.data.email_notificacao) document.getElementById('cfg-email').value = j.data.email_notificacao;
      if (j.data.alerta_dias) {
        const s = document.getElementById('cfg-alerta');
        for (let o of s.options) if (o.value === j.data.alerta_dias) o.selected = true;
      }
      if (j.data.resumo_dias) {
        const s = document.getElementById('cfg-resumo');
        for (let o of s.options) if (o.value === j.data.resumo_dias) o.selected = true;
      }
    }
  } catch(e){
    console.error('Erro ao carregar config:', e);
  }
}

async function saveConfig(){
  const d = {
    email_notificacao: document.getElementById('cfg-email').value,
    alerta_dias:       document.getElementById('cfg-alerta').value,
    resumo_horario:    document.getElementById('cfg-resumo').selectedOptions[0]?.textContent.match(/\d{2}h\d{2}/)?.[0] || '08:00',
    resumo_dias:       document.getElementById('cfg-resumo').value
  };
  const j = await apiPost('save_config', d);
  toast(j.ok ? 'Configurações salvas com sucesso!' : 'Erro ao salvar');
}

async function testEmail(){
  const to = document.getElementById('cfg-email').value;
  if (!to){ toast('Preencha o e-mail'); return; }
  toast('Enviando e-mail de teste...');
  const j = await apiPost('test_email', { to });
  toast(j.msg || (j.ok ? 'Enviado!' : 'Erro ao enviar'));
}
