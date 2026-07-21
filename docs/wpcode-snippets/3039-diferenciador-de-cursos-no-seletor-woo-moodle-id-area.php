<?php
/**
 * WPCode snippet #3039 — Diferenciador de Cursos no Seletor Woo↔Moodle (ID + Área)
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
/**
 * Diferenciador de Cursos no Seletor Woo↔Moodle (ID + Área)
 * Adiciona [#ID • Área] ao texto de cada opção do dropdown de cursos,
 * para distinguir cursos de mesmo nome (ex.: Fiscal x Tribunais).
 * Cosmético: altera apenas o texto exibido, nunca o value salvo.
 */
add_action( 'admin_footer', function () {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'product' ) {
        return;
    }

    // ⇩ MAPA: course_id => área. Preencha com os IDs reais.
    //   (só os que você quer diferenciar; o resto mostra só o ID)
    $areas = array(
        1935 => 'Tribunais',   // Direito Administrativo - Resumo
        1937 => 'Tribunais',   // Direito Administrativo - Flashcards
        3025 => 'Fiscal',      // Direito Administrativo
        3026 => 'Fiscal',      // Direito Administrativo - Resumo
        // ... adicione os outros pares conforme precisar
    );
    ?>
    <script>
    (function () {
        var areas = <?php echo wp_json_encode( $areas ); ?>;
        var sel = document.querySelector('select[name="product_options[moodle_post_course_id][]"]');
        if (!sel) return;

        sel.querySelectorAll('option').forEach(function (op) {
            var id = op.value;
            var base = op.textContent.replace(/\s+/g, ' ').trim(); // limpa tabs/quebras
            var area = areas[id] ? ' • ' + areas[id] : '';
            op.textContent = base + '  [#' + id + area + ']';
        });
    })();
    </script>
    <?php
}, 9999 );