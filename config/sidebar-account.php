<?php

return [
    [
        'title' => 'Gestión SII (DTE)', 'icon' => 'ShieldCheck', 'permission' => 'finanzas.*',
        'items' => [
            ['title' => 'Panel General DTE', 'href' => '/sii'],
            ['title' => 'Documentos Emitidos', 'href' => '/sii/documentos'],
            ['title' => 'Subir Archivo CAF', 'href' => '/sii/caf/subir'],
            ['title' => 'Certificado Digital', 'href' => '/sii/configuracion/certificado'],
            ['title' => 'Datos del Emisor', 'href' => '/sii/configuracion/emisor'],
            ['title' => 'Stock de Folios', 'href' => '/sii/configuracion/folios'],
            ['title' => 'Ambiente Trabajo', 'href' => '/sii/configuracion/ambiente'],
        ],
    ],
    [
        'title' => 'Perfil Social', 'icon' => 'Users',
        'items' => [
            ['title' => 'Perfil', 'href' => '/settings/profile'],
            ['title' => 'Contraseña', 'href' => '/settings/password'],
            ['title' => 'Autenticación', 'href' => '/settings/two-factor'],
            ['title' => 'Notificaciones', 'href' => '/settings/notifications'],
            ['title' => 'Publicaciones de la Comunidad', 'href' => '/comunidad'],
        ],
    ],
    [
        'title' => 'Marketplace', 'icon' => 'ShoppingCart',
        'items' => [
            ['title' => 'Ver Marketplace', 'href' => '/tienda'],
            ['title' => 'Mis Pedidos', 'href' => '/mis-pedidos'],
            ['title' => 'Mis Ventas', 'href' => '/pedidos-recibidos', 'permission' => 'comercial.oportunidades.viewAny'],
            ['title' => 'Cupones de Descuento', 'href' => '/cupones', 'permission' => 'ventas.cupones.viewAny'],
        ],
    ],
    [
        'title' => 'Recompensas', 'icon' => 'Award',
        'items' => [
            ['title' => 'Recomienda', 'href' => '/afiliados/recomendar'],
            ['title' => 'Mis Afiliados', 'href' => '/afiliados/red'],
        ],
    ],
    ['title' => 'Automatiza tu negocio', 'href' => '/canales', 'icon' => 'Bot', 'permission' => 'sistema.automatizaciones.viewAny'],
    ['title' => 'Conexiones API', 'href' => '/integraciones-api', 'icon' => 'KeyRound', 'permission' => 'sistema.integraciones.viewAny'],
];
