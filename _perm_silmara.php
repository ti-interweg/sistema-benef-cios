<?php
/**
 * Permissão especial: a Silmara usa os dois sistemas (Comercial + Benefícios).
 * No Benefícios, além do acesso normal, ela também enxerga a seção "Ferramentas"
 * (Cotações, Formulário, Apresentação, Controle de Cotação, Fechamento, Passagem de Bastão)
 * e o quadro completo de cotações/fechamentos, como o time de Novos Negócios.
 *
 * Identificação pelo nome de login. Se um dia mudar, é só ajustar aqui.
 */
if (!function_exists('isSilmara')) {
    function isSilmara(): bool {
        return stripos($_SESSION['user_nome'] ?? '', 'silmara') !== false;
    }
}
