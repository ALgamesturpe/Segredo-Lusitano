<?php
// PWA Manifest — gerado com SITE_URL dinâmico
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/manifest+json');
header('Cache-Control: no-cache');

$icon = SITE_URL . '/assets/images/logo_icon.png';

echo json_encode([
    'name'             => 'Segredo Lusitano',
    'short_name'       => 'Segredo',
    'description'      => 'Descobre os segredos escondidos de Portugal',
    'start_url'        => SITE_URL . '/',
    'scope'            => SITE_URL . '/',
    'display'          => 'standalone',
    'background_color' => '#1a3a2a',
    'theme_color'      => '#1a3a2a',
    'orientation'      => 'portrait-primary',
    'lang'             => 'pt-PT',
    'icons'            => [
        ['src' => $icon, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ['src' => $icon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
