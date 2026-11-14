<?php

return [
    [
        'title' => 'Dashboard',
        'href' => '/dashboard',
        'icon' => 'Building2',
    ],
    [
        'title' => 'Gestión Comercial',
        'group' => 'COMERCIAL',
        'href' => '#comercial',
        'icon' => 'Users',
        'permission' => 'comercial.*',
        'items' => [
            [
                'title' => 'Fundamental',
                'href' => '#',
                'items' => [
                    [
                        'title' => 'Categorías',
                        'href' => '/categorias',
                        'permission' => 'comercial.categorias.viewAny',
                    ],
                    [
                        'title' => 'Productos',
                        'href' => '/productos',
                        'permission' => 'comercial.productos.viewAny',
                    ],
                    [
                        'title' => 'Clientes',
                        'href' => '/clientes',
                        'permission' => 'comercial.clientes.viewAny',
                    ],
                    [
                        'title' => 'Almacenes (WMS)',
                        'href' => '/almacenes',
                        'permission' => 'inventario.almacenes.viewAny',
                    ],
                ],
            ],
            [
                'title' => 'CRM & Ventas',
                'href' => '#',
                'items' => [
                    [
                        'title' => 'Leads & Pipeline',
                        'href' => '/prospectos',
                        'permission' => 'comercial.prospectos.viewAny',
                    ],
                    [
                        'title' => 'Oportunidades',
                        'href' => '/oportunidades',
                        'permission' => 'comercial.oportunidades.viewAny',
                    ],
                    [
                        'title' => 'Cotizaciones',
                        'href' => '/cotizaciones',
                        'permission' => 'comercial.cotizaciones.viewAny',
                    ],
                    [
                        'title' => 'Ventas (SFA)',
                        'href' => '/ventas',
                        'permission' => 'ventas.ventas.viewAny',
                    ],
                    [
                        'title' => 'Campañas',
                        'href' => '/campanas',
                        'permission' => 'comercial.campanas.viewAny',
                    ],
                    [
                        'title' => 'Call Center',
                        'href' => '/call-center',
                        'permission' => 'comercial.call-center.viewAny',
                    ],
                    [
                        'title' => 'Tickets Soporte',
                        'href' => '/tickets',
                        'permission' => 'comercial.tickets.viewAny',
                    ],
                ],
            ],
        ],
    ],
    [
        'title' => 'Operaciones e Inventario',
        'group' => 'OPERACIONES',
        'href' => '#operaciones',
        'icon' => 'Truck',
        'permission' => 'inventario.*',
        'items' => [
            [
                'title' => 'Inventario',
                'href' => '/inventarios',
                'permission' => 'inventario.inventarios.viewAny',
            ],
            [
                'title' => 'Movimientos',
                'href' => '/movimientos',
                'permission' => 'inventario.movimientos.viewAny',
            ],
            [
                'title' => 'Lotes y Series',
                'href' => '/lotes',
                'permission' => 'inventario.lotes.viewAny',
            ],
            [
                'title' => 'Proveedores',
                'href' => '/proveedors',
                'permission' => 'inventario.proveedores.viewAny',
            ],
            [
                'title' => 'Órdenes de Compra',
                'href' => '/compras',
                'permission' => 'inventario.compras.viewAny',
            ],
            [
                'title' => 'Vacíos',
                'href' => '/vacios',
                'permission' => 'inventario.vacios.viewAny',
            ],
        ],
    ],
    [
        'title' => 'Producción (MRP)',
        'group' => 'OPERACIONES',
        'href' => '#mrp',
        'icon' => 'Wrench',
        'permission' => 'mrp.*',
        'items' => [
            [
                'title' => 'BOM (Materiales)',
                'href' => '/boms',
                'permission' => 'mrp.boms.viewAny',
            ],
            [
                'title' => 'Órdenes Producción',
                'href' => '/ordenes-produccion',
                'permission' => 'mrp.produccion.viewAny',
            ],
            [
                'title' => 'Control Calidad',
                'href' => '/calidad',
                'permission' => 'mrp.calidad.viewAny',
            ],
            [
                'title' => 'Planificación',
                'href' => '/planificacion',
                'permission' => 'mrp.planificacion.viewAny',
            ],
        ],
    ],
    [
        'title' => 'Facturación',
        'group' => 'FACTURACIÓN',
        'href' => '#facturacion',
        'icon' => 'FileText',
        'permission' => 'finanzas.*',
        'items' => [
            [
                'title' => 'Facturación (AR)',
                'href' => '/facturacion',
                'permission' => 'finanzas.facturacion.viewAny',
            ],
            [
                'title' => 'Cobranzas',
                'href' => '/cobranzas',
                'permission' => 'finanzas.cobranzas.viewAny',
            ],
            [
                'title' => 'Pagos (AP)',
                'href' => '/pagos',
                'permission' => 'finanzas.pagos.viewAny',
            ],
            [
                'title' => 'Contabilidad (GL)',
                'href' => '/contabilidad',
                'permission' => 'finanzas.contabilidad.viewAny',
            ],
            [
                'title' => 'Impuestos',
                'href' => '/impuestos',
                'permission' => 'finanzas.impuestos.viewAny',
            ],
        ],
    ],
    [
        'title' => 'Pagos en Línea',
        'group' => 'FINANZAS',
        'href' => '#pagos-online',
        'icon' => 'CreditCard',
        'permission' => 'finanzas.*',
        'items' => [
            [
                'title' => 'Configuración Webpay',
                'href' => '/webpay/config',
                'permission' => 'admin.configuracion.viewAny',
            ],
            [
                'title' => 'Configuración PayPal',
                'href' => '/paypal/config',
                'permission' => 'admin.configuracion.viewAny',
            ],
            [
                'title' => 'Configuración MercadoPago',
                'href' => '/mercadopago/config',
                'permission' => 'admin.configuracion.viewAny',
            ],
            [
                'title' => 'Movimientos',
                'href' => '/webpay/movimientos',
                'permission' => 'finanzas.tesoreria.viewAny',
            ],
            [
                'title' => 'Pago Plataforma',
                'href' => '/pagos/plataforma',
                'permission' => 'admin.configuracion.viewAny',
            ],
            [
                'title' => 'Cupones de Descuento',
                'href' => '/cupones',
                'permission' => 'ventas.cupones.viewAny',
            ],
        ],
    ],
    [
        'title' => 'Gestión Humana',
        'group' => 'RRHH',
        'href' => '#rrhh',
        'icon' => 'UsersRound',
        'permission' => 'rrhh.*',
        'items' => [
            [
                'title' => 'Empleados',
                'href' => '/empleados',
                'permission' => 'rrhh.empleados.viewAny',
            ],
            [
                'title' => 'Nómina',
                'href' => '/nominas',
                'permission' => 'rrhh.nominas.viewAny',
            ],
            [
                'title' => 'Asistencia',
                'href' => '/asistencia',
                'permission' => 'rrhh.asistencia.viewAny',
            ],
            [
                'title' => 'Préstamos y Adelantos',
                'href' => '/prestamos',
                'permission' => 'rrhh.prestamos.viewAny',
            ],
            [
                'title' => 'Cuotas Pendientes',
                'href' => '/prestamos/cuotas-pendientes',
                'permission' => 'rrhh.prestamos.viewAny',
            ],
            [
                'title' => 'Reclutamiento',
                'href' => '/reclutamiento',
                'permission' => 'rrhh.reclutamiento.viewAny',
            ],
            [
                'title' => 'Evaluaciones',
                'href' => '/evaluaciones',
                'permission' => 'rrhh.evaluaciones.viewAny',
            ],
        ],
    ],
    [
        'title' => 'Proyectos (PMS)',
        'group' => 'PROYECTOS',
        'href' => '#pms',
        'icon' => 'ClipboardList',
        'permission' => 'proyectos.*',
        'items' => [
            [
                'title' => 'Proyectos',
                'href' => '/proyectos',
                'permission' => 'proyectos.proyectos.viewAny',
            ],
            [
                'title' => 'Hitos y Tareas',
                'href' => '/hitos',
                'permission' => 'proyectos.hitos.viewAny',
            ],
            [
                'title' => 'Timesheets',
                'href' => '/timesheets',
                'permission' => 'proyectos.timesheets.viewAny',
            ],
            [
                'title' => 'Gastos Proyecto',
                'href' => '/gastos-proyecto',
                'permission' => 'proyectos.gastos.viewAny',
            ],
        ],
    ],
    [
        'title' => 'Logística y Flota',
        'group' => 'LOGÍSTICA',
        'href' => '#logistica',
        'icon' => 'Truck',
        'permission' => 'flota.*',
        'items' => [
            [
                'title' => 'Vehículos',
                'href' => '/vehiculos',
                'permission' => 'flota.vehiculos.viewAny',
            ],
            [
                'title' => 'Conductores',
                'href' => '/conductores',
                'permission' => 'flota.conductores.viewAny',
            ],
            [
                'title' => 'Entregas',
                'href' => '/entregas',
                'permission' => 'flota.entregas.viewAny',
            ],
            [
                'title' => 'Cargas Diarias / Rutas',
                'href' => '/cargas-diarias',
                'permission' => 'flota.cargas.viewAny',
            ],
            [
                'title' => 'Grupos de Trabajo',
                'href' => '/grupos-trabajo',
                'permission' => 'flota.grupos-trabajo.viewAny',
            ],
            [
                'title' => 'Zonas de Reparto',
                'href' => '/zonas-reparto',
                'permission' => 'delivery.zonas-reparto.viewAny',
            ],
            [
                'title' => 'Repartidores',
                'href' => '/repartidores',
                'permission' => 'delivery.repartidores.viewAny',
            ],
        ],
    ],
    [
        'title' => 'Punto de Venta (POS)',
        'group' => 'TIENDA',
        'href' => '#tienda',
        'icon' => 'ShoppingCart',
        'permission' => [
            'ventas.pos.viewAny',
            'ventas.cierre-caja.viewAny',
            'ventas.facturacion-pos.viewAny',
            'ventas.pos-reportes.viewAny',
            'comercial.mensajes-marketplace.viewAny',
            'ventas.variantes.viewAny',
        ],
        'items' => [
            [
                'title' => 'Mi Caja',
                'href' => '/mi-caja',
                'permission' => 'ventas.pos.viewAny',
            ],
            [
                'title' => 'Terminal POS',
                'href' => '/pos',
                'permission' => 'ventas.pos.viewAny',
            ],
            [
                'title' => 'Mis Ventas',
                'href' => '/pos/mis-ventas',
                'permission' => 'ventas.pos.viewAny',
            ],
            [
                'title' => 'Cierre de Caja',
                'href' => '/pos/cierre',
                'permission' => 'ventas.cierre-caja.viewAny',
            ],
            [
                'title' => 'Facturación POS',
                'href' => '/pos/facturacion',
                'permission' => 'ventas.facturacion-pos.viewAny',
            ],
            [
                'title' => 'Reportes Ventas',
                'href' => '/pos/reportes',
                'permission' => 'ventas.pos-reportes.viewAny',
            ],
            [
                'title' => 'Mensajes Marketplace',
                'href' => '/chat',
                'permission' => 'comercial.mensajes-marketplace.viewAny',
            ],
            [
                'title' => 'Variantes / SKUs',
                'href' => '/pos/variantes',
                'permission' => 'ventas.variantes.viewAny',
            ],
        ],
    ],
    [
        'title' => 'Citas y Reservas',
        'group' => 'SERVICIOS',
        'href' => '#reservas',
        'icon' => 'Calendar',
        'permission' => 'citas.*',
        'items' => [
            [
                'title' => 'Dashboard',
                'href' => '/appointments/dashboard',
                'permission' => 'citas.citas.viewAny',
            ],
            [
                'title' => 'Calendario',
                'href' => '/appointments/calendar',
                'permission' => 'citas.citas.viewAny',
            ],
            [
                'title' => 'Mis Citas',
                'href' => '/appointments',
                'permission' => 'citas.citas.viewAny',
            ],
            [
                'title' => 'Servicios',
                'href' => '/services',
                'permission' => 'citas.servicios.viewAny',
            ],
        ],
    ],
    [
        'title' => 'Plataforma de Aprendizaje',
        'group' => 'EDUCACIÓN',
        'href' => '#lms',
        'icon' => 'GraduationCap',
        'permission' => 'lms.*',
        'items' => [
            [
                'title' => 'Catálogo de Cursos',
                'href' => '/cursos',
                'permission' => 'lms.cursos.viewAny',
            ],
            [
                'title' => 'Mis Cursos (Instructor)',
                'href' => '/instructor/cursos',
                'permission' => 'lms.cursos.create',
            ],
            [
                'title' => 'Cursos Inscritos',
                'href' => '/alumno/cursos',
                'permission' => 'lms.alumnos.viewAny',
            ],
            [
                'title' => 'Progreso y Notas',
                'href' => '/alumno/progreso',
                'permission' => 'lms.alumnos.viewAny',
            ],
        ],
    ],
    [
        'title' => 'Marketing',
        'group' => 'MARKETING',
        'href' => '#marketing',
        'icon' => 'Megaphone',
        'permission' => 'admin.*',
        'items' => [
            [
                'title' => 'Campañas',
                'href' => '/campanas',
                'permission' => 'comercial.campanas.viewAny',
            ],
            [
                'title' => 'Email Marketing',
                'href' => '/mail-templates',
                'permission' => 'admin.mail-templates.viewAny',
            ],
            [
                'title' => 'Config. Correo',
                'href' => '/marketing/email-config',
                'permission' => 'admin.email-config.viewAny',
            ],
        ],
    ],
    [
        'title' => 'Monitoreo',
        'group' => 'OPERACIONES',
        'href' => '#uptime',
        'icon' => 'Activity',
        'permission' => 'uptime.*',
        'items' => [
            [
                'title' => 'Monitores',
                'href' => '/uptime',
                'permission' => 'uptime.monitores.viewAny',
            ],
            [
                'title' => 'Alertas',
                'href' => '/uptime/alerts',
                'permission' => 'uptime.alertas.viewAny',
            ],
        ],
    ],
    [
        'title' => 'Rifas y Sorteos',
        'group' => 'MARKETING',
        'href' => '#raffles',
        'icon' => 'Gift',
        'permission' => 'rifas.*',
        'items' => [
            [
                'title' => 'Gestionar Rifas',
                'href' => '/raffles',
                'permission' => 'rifas.rifas.viewAny',
            ],
            [
                'title' => 'Sorteos',
                'href' => '/raffles/draws',
                'permission' => 'rifas.sorteos.viewAny',
            ],
        ],
    ],
    [
        'title' => 'Administración',
        'group' => 'SISTEMA',
        'href' => '#sistema',
        'icon' => 'Lock',
        'permission' => 'admin.*',
        'items' => [
            [
                'title' => 'Usuarios y Roles',
                'href' => '/usuarios-roles',
                'permission' => [
                    'admin.usuarios.viewAny',
                ],
            ],
            [
                'title' => 'Configuración Web',
                'href' => '/configuracion-web',
                'permission' => [
                    'admin.configuracion.viewAny',
                    'admin.web-settings.viewAny',
                ],
            ],
            [
                'title' => 'Países y Monedas',
                'href' => '/paises',
                'icon' => 'Globe',
                'permission' => [
                    'admin.countries.viewAny',
                ],
            ],
        ],
    ],
];
