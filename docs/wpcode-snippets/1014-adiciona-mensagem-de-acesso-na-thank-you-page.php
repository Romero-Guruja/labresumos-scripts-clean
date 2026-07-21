<?php
/**
 * WPCode snippet #1014 — Adiciona mensagem de acesso na Thank You page
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
// Adiciona mensagem de acesso na Thank You page
add_action('woocommerce_thankyou', 'lab_mensagem_acesso_moodle', 5);

function lab_mensagem_acesso_moodle($order_id) {
    ?>
    <div class="lab-acesso-conteudo">
        <h3>📚 Como acessar seu conteúdo</h3>
        <p>Todo o material adquirido estará disponível na nossa <strong>Área do Aluno</strong>.</p>
        <p>Acesse agora mesmo em:</p>
        <a href="https://labresumos.com.br/area-aluno/" target="_blank" class="lab-btn-acesso">
            Acessar Área do Aluno →
        </a>
    </div>
    <?php
}