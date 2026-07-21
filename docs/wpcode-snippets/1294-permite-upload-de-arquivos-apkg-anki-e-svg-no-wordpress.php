<?php
/**
 * WPCode snippet #1294 — Permite upload de arquivos .apkg (Anki) e SVG no WordPress
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
/**
 * Permite upload de arquivos .apkg (Anki), SVG e PDF no WordPress
 */
add_filter('upload_mimes', function($mimes) {
    $mimes['apkg'] = 'application/zip';
    $mimes['svg']  = 'image/svg+xml';
    $mimes['pdf']  = 'application/pdf';
    return $mimes;
});

add_filter('wp_check_filetype_and_ext', function($data, $file, $filename, $mimes) {
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    
    $custom_types = [
        'apkg' => 'application/zip',
        'svg'  => 'image/svg+xml',
        'pdf'  => 'application/pdf',
    ];
    
    if (isset($custom_types[$ext])) {
        $data['ext']  = $ext;
        $data['type'] = $custom_types[$ext];
    }
    
    return $data;
}, 10, 4);