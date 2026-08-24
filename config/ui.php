<?php

return [
    'ui_paths' => [
        'app_logo_path' => '/assets/images/logo.png',
        'user_photo_fallback_path' => '/assets/images/logo.png',
    ],
    'auth_styling' => [
        'background_image' => '/assets/images/bg4.jpg',
        #'background_video' => '/assets/images/bg6.mp4',
        'background_blur' => '6px',
        'card_transparency' => '0.9',
        'text_color' => '#ffffff',
    ],
    'button_colors' => [
        'info'            => 'linear-gradient(135deg, #818cf8, #6366f1)',
        'create'          => 'linear-gradient(135deg, #34d399, #059669)',
        'manual'          => 'linear-gradient(135deg, #f472b6, #db2777)',
        'enable'          => 'linear-gradient(135deg, #a78bfa, #7c3aed)',
        'disable'         => 'linear-gradient(135deg, #f87171, #dc2626)',
        'unlock'          => 'linear-gradient(135deg, #60a5fa, #2563eb)',
        'reset'           => 'linear-gradient(135deg, #fb923c, #ea580c)',
        'modify'          => 'linear-gradient(135deg, #2dd4bf, #0d9488)',
        'dashboard'       => 'linear-gradient(135deg, #14b8a6, #0d9488)',
        'update'          => 'linear-gradient(135deg, #6b7280, #4b5563)',
        'report'          => 'linear-gradient(135deg, #14b8a6, #0d9488)',
        'directory'       => 'linear-gradient(135deg, #fbbf24, #d97706)',
        'sync'            => 'linear-gradient(135deg, #3b82f6, #2563eb)',
        'mapping'         => 'linear-gradient(135deg, #8b5cf6, #7c3aed)',
        'users'           => 'linear-gradient(135deg, #10b981, #059669)',
        'health'          => 'linear-gradient(135deg, #06b6d4, #0891b2)',
        'groups'          => 'linear-gradient(135deg, #f59e0b, #d97706)',
        'reports'         => 'linear-gradient(135deg, #ec4899, #db2777)',
    ],
    'themes' => [
        'theme-corporate-blue' => [
            'bg' => '#f0f5fa',
            'card' => '#ffffff',
            'rail' => '#e3eaf0',
            'border' => '#d0d9e6',
            'text' => '#1a2332',
            'header' => '#1565C0',
            'header_text' => '#ffffff',
            'primary' => '#1976D2',
            'primary_rgb' => '25, 118, 210'
        ],
        'theme-red' => [
            'bg' => '#f6eee7',
            'card' => 'linear-gradient(180deg, #fffdfb 0%, #fff4f2 100%)',
            'rail' => '#f8ede5',
            'border' => '#ebd3ca',
            'text' => '#2e2d30',
            'header' => '#c44d73',
            'header_text' => '#ffffff',
            'primary' => '#c44d73',
            'primary_rgb' => '196, 77, 115'
        ],
        'theme-natural-green' => [
            'bg' => '#f0f7f1',
            'card' => '#ffffff',
            'rail' => '#e4f0e6',
            'border' => '#c8dccb',
            'text' => '#1a2d1c',
            'header' => '#2E7D32',
            'header_text' => '#ffffff',
            'primary' => '#388E3C',
            'primary_rgb' => '56, 142, 60'
        ],
        'theme-matte-black' => [
            'bg' => '#121212',
            'card' => '#1E1E1E',
            'rail' => '#252525',
            'border' => '#333333',
            'text' => '#E0E0E0',
            'header' => '#1E1E1E',
            'header_text' => '#e0e0e0',
            'primary' => '#BB86FC',
            'primary_rgb' => '187, 134, 252'
        ],
        'theme-glass-aura' => [
            'bg' => 'linear-gradient(135deg, #0a0a1a 0%, #1a103c 25%, #2d1b69 50%, #1a103c 75%, #0a0a1a 100%)',
            'card' => 'rgba(255, 255, 255, 0.08)',
            'rail' => 'rgba(15, 10, 40, 0.7)',
            'border' => 'rgba(255, 255, 255, 0.12)',
            'text' => '#ffffff',
            'header' => 'rgba(15, 10, 40, 0.6)',
            'header_text' => '#ffffff',
            'primary' => '#a78bfa',
            'primary_rgb' => '167, 139, 250'
        ],
        'theme-white-professional' => [
            'bg' => '#f8fafc',
            'card' => '#ffffff',
            'rail' => '#f1f5f9',
            'border' => '#e2e8f0',
            'text' => '#0f172a',
            'header' => '#ffffff',
            'header_text' => '#0f172a',
            'primary' => '#6366f1',
            'primary_rgb' => '99, 102, 241'
        ],
        'theme-blue-purple-pro' => [
            'bg' => 'linear-gradient(135deg, #eef2ff 0%, #e0e7ff 50%, #ede9fe 100%)',
            'card' => '#ffffff',
            'rail' => '#4f46e5',
            'border' => '#c7d2fe',
            'text' => '#1e1b4b',
            'header' => '#4f46e5',
            'header_text' => '#ffffff',
            'primary' => '#6366f1',
            'primary_rgb' => '99, 102, 241'
        ],
    ],
    'login_colors' => [
        'header_column_bg' => '240,242,245',
        'card_panel_bg' => '255,255,255',
    ],
    'tooltips' => [
        'bg' => 'rgba(30, 41, 59, 0.93)',
        'color' => '#ffffff',
        'font_size' => '0.72rem',
        'padding' => '6px 10px',
        'border_radius' => '6px',
        'border' => '1px solid rgba(255,255,255,0.1)',
        'shadow' => '0 4px 15px rgba(0,0,0,0.5)',
        'max_width' => '260px',
        'gap' => 6,
        'arrow_size' => 5,
        'arrow_color' => 'rgba(30, 41, 59, 0.93)',
        'z_index' => 99999,
    ],
    'typography' => [
        'primary_font' => "'Roboto', 'Kalpurush', 'Segoe UI', 'Tahoma', sans-serif",
        'secondary_font' => "'Roboto', 'serif'",
        'technical_font' => "'Roboto', monospace",
        'font_path' => '/kalpurush.ttf',
        'font_sizes' => [
            // ========== Base Typography Scale ==========
            // These control the overall text hierarchy. Change the value (e.g. '0.8rem')
            // and all components using that token update automatically.

            'xs'    => '0.7rem',   // (1) Badges, status labels, helper text, tiny meta (10.5px @ 15px base)
            'sm'    => '0.8rem',   // (2) Table headers, sidecard action buttons, secondary text (12px)
            'base'  => '0.95rem',  // (3) Main body text, form inputs, general content, primary buttons (14.25px)
            'md'    => '1rem',     // (4) Card titles, section headers (15px)
            'lg'    => '1.15rem',  // (5) Page titles, stat labels, dashboard panel titles (17.25px)
            'xl'    => '1.3rem',   // (6) Stat values, emphasis numbers, dashboard metrics (19.5px)
            'xxl'   => '1.6rem',   // (7) Section heroes, welcome banners, large emphasis (24px)

            // ========== Component-Specific Sizes ==========
            // Each UI component type gets its own variable so you can tune
            // them independently without affecting other parts of the app.

            'table'    => '0.8rem',  // (8)  All table body cells (td rows) — compact for data density
            'info'     => '0.85rem', // (9)  Server/Employee info card content, HRMS labels & values
            'feedback' => '0.85rem', // (10) Action result feedback message card text

            // ========== Heading Sizes ==========
            // HTML heading tags — used in documentation, about pages, form titles, etc.

            'h1'    => '2rem',     // (11) 30px — page-level main heading
            'h2'    => '1.6rem',   // (12) 24px — major section heading
            'h3'    => '1.3rem',   // (13) 19.5px — sub-section heading
            'h4'    => '1.1rem',   // (14) 16.5px — group heading
            'h5'    => '1rem',     // (15) 15px — minor heading
            'h6'    => '0.9rem',   // (16) 13.5px — smallest heading
        ],
    ],
];
