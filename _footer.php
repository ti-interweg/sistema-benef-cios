<!-- ── COMMON UI ELEMENTS ──────────────────────────────────────────── -->
<div class="toast" id="toast"></div>

<?php
// Cache-buster baseado no mtime do arquivo: força o navegador a buscar
// a versão nova depois que um JS é atualizado, sem precisar de Ctrl+F5.
// Usa @ pra suprimir warnings (que poderiam interromper o output).
if (!function_exists('_scriptTag')) {
    function _scriptTag($src) {
        $path = __DIR__ . '/' . ltrim($src, '/');
        $v = @filemtime($path);
        if (!$v) $v = time();
        echo '<script src="' . htmlspecialchars($src) . '?v=' . $v . '"></script>' . "\n";
    }
}
_scriptTag('assets/common.js');
// Diálogos modernos (alert/confirm/prompt estilizados) — global
_scriptTag('assets/ui-dialog.js');
// Máscaras de formulário (CNPJ etc.) — global
_scriptTag('assets/mask.js');
// Sino de notificações — global em todas as páginas com header
_scriptTag('assets/notificacoes.js');
// Timeout de sessão por inatividade — global
_scriptTag('assets/sessao.js');
if (!empty($pageScripts) && is_array($pageScripts)) {
    foreach ($pageScripts as $src) {
        _scriptTag($src);
    }
}
?>
</body>
</html>
