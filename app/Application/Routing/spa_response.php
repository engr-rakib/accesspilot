<?php

function core_admin_render_spa_response(array $payload): void
{
    header('Content-Type: application/json');

    ob_start();

    if (isset($payload['view_data']) && is_array($payload['view_data'])) {
        extract($payload['view_data']);
    }
    if (isset($payload['baseURL'])) {
        $baseURL = $payload['baseURL'];
    }
    if (isset($payload['app_config'])) {
        $app_config = $payload['app_config'];
    }

    if (isset($payload['content_for_layout']) && file_exists($payload['content_for_layout'])) {
        include $payload['content_for_layout'];
    } else {
        echo '<p>Error: Content file not found.</p>';
    }

    $content = ob_get_clean();

    echo json_encode([
        'success' => true,
        'title' => $payload['pageTitle'] ?? '',
        'description' => $payload['pageDescription'] ?? '',
        'content' => $content,
        'scripts' => $payload['page_scripts'] ?? [],
        'styles' => $payload['page_styles'] ?? [],
        'page' => $payload['page'] ?? 'default',
        'baseURL' => $payload['baseURL'] ?? '',
    ]);
    exit();
}
