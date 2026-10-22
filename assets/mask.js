/* Máscaras + validação de formulário — global (todas as páginas).
   CNPJ: 00.000.000/0000-00. Se incompleto/ inválido, deixa o campo em
   vermelho com uma frase pequena embaixo. Funciona em campos dinâmicos. */
(function(){
  function cnpjDigits(v){ return String(v == null ? '' : v).replace(/\D/g, ''); }

  function fmtCnpj(v){
    v = cnpjDigits(v).slice(0, 14);
    let out = v;
    if (v.length > 2)  out = v.slice(0,2) + '.' + v.slice(2);
    if (v.length > 5)  out = v.slice(0,2) + '.' + v.slice(2,5) + '.' + v.slice(5);
    if (v.length > 8)  out = v.slice(0,2) + '.' + v.slice(2,5) + '.' + v.slice(5,8) + '/' + v.slice(8);
    if (v.length > 12) out = v.slice(0,2) + '.' + v.slice(2,5) + '.' + v.slice(5,8) + '/' + v.slice(8,12) + '-' + v.slice(12);
    return out;
  }
  window.fmtCnpj = window.fmtCnpj || fmtCnpj;

  function validCnpj(v){
    v = cnpjDigits(v);
    if (v.length !== 14) return false;
    if (/^(\d)\1{13}$/.test(v)) return false;
    const calc = base => {
      let len = base.length, pos = len - 7, sum = 0;
      for (let i = len; i >= 1; i--){ sum += parseInt(base[len-i],10) * pos--; if (pos < 2) pos = 9; }
      const r = sum % 11; return r < 2 ? 0 : 11 - r;
    };
    const d1 = calc(v.slice(0,12)), d2 = calc(v.slice(0,12) + d1);
    return v.slice(12) === ('' + d1 + d2);
  }
  window.validCnpj = window.validCnpj || validCnpj;

  var SEL  = '#ee-cnpj, #pb-cnpj, #pe-cnpj, .pb-subfat-cnpj, .js-cnpj, [data-cnpj]';
  var MAIN = '#ee-cnpj, #pb-cnpj, #pe-cnpj, .js-cnpj, [data-cnpj]';
  var TEL_SEL   = '[id*="telefone"], .js-tel, [data-tel]';
  var EMAIL_SEL = '[id*="email"], .js-email, [data-email]';

  // ── Telefone: (XX) XXXXX-XXXX ou (XX) XXXX-XXXX ──
  function fmtTel(v){
    v = String(v == null ? '' : v).replace(/\D/g, '').slice(0, 11);
    if (v.length === 0) return '';
    if (v.length <= 2)  return '(' + v;
    if (v.length <= 6)  return '(' + v.slice(0,2) + ') ' + v.slice(2);
    if (v.length <= 10) return '(' + v.slice(0,2) + ') ' + v.slice(2,6) + '-' + v.slice(6);
    return '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
  }
  window.fmtTel = window.fmtTel || fmtTel;

  // ── E-mail: valida um ou vários (separados por ; ou ,) ──
  function emailOk(v){
    v = String(v == null ? '' : v).trim();
    if (v === '') return true;
    var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return v.split(/[;,]/).map(function(s){ return s.trim(); }).filter(Boolean).every(function(s){ return re.test(s); });
  }
  window.emailOk = window.emailOk || emailOk;

  // Mostra/limpa a frase vermelha embaixo do campo
  function setMsg(t, ok, texto){
    var subRow = t.closest ? t.closest('.pb-subfat-row') : null;
    var cont = subRow || t.parentNode;
    t.style.borderColor = ok ? '' : '#c0392b';
    if (!cont) return;
    if (subRow) subRow.style.flexWrap = 'wrap';
    var msg = cont.querySelector('.fld-msg');
    if (ok){ if (msg) msg.remove(); return; }
    if (!msg){
      msg = document.createElement('div'); msg.className = 'fld-msg';
      msg.style.cssText = 'font-size:11px;color:#c0392b;margin-top:3px' + (subRow ? ';width:100%' : '');
      cont.appendChild(msg);
    }
    msg.textContent = texto;
  }

  function feedback(t){
    if (!ehCampo(t)) return;
    var d = cnpjDigits(t.value);
    var ok = (d.length === 0) || validCnpj(t.value); // vazio não fica vermelho
    setMsg(t, ok, (d.length < 14) ? 'CNPJ incompleto. Informe os 14 dígitos.' : 'CNPJ inválido.');
  }

  function ehCampo(t){ return t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA') && typeof t.matches === 'function'; }

  function tratar(t, mascarar){
    if (!ehCampo(t)) return;
    if (t.matches(SEL)){
      if (mascarar){ var m = fmtCnpj(t.value); if (m !== t.value){ t.value = m; try { t.setSelectionRange(m.length, m.length); } catch(_){} } }
      feedback(t);
    } else if (t.matches(TEL_SEL)){
      if (mascarar){ var mt = fmtTel(t.value); if (mt !== t.value){ t.value = mt; try { t.setSelectionRange(mt.length, mt.length); } catch(_){} } }
      var dt = String(t.value).replace(/\D/g, '');
      setMsg(t, (dt.length === 0 || dt.length === 10 || dt.length === 11), 'Telefone incompleto. Informe DDD + número.');
    } else if (t.matches(EMAIL_SEL)){
      setMsg(t, emailOk(t.value), (String(t.value).indexOf(';') >= 0 || String(t.value).indexOf(',') >= 0) ? 'Verifique os e-mails.' : 'E-mail inválido.');
    }
  }

  document.addEventListener('input',    function(e){ tratar(e.target, true);  });
  document.addEventListener('focusout', function(e){ tratar(e.target, true);  });
})();
