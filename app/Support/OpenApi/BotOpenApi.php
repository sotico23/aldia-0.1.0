<?php

namespace App\Support\OpenApi;

class BotOpenApi
{
    public static function schema(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Al Día · Bot API',
                'version' => '2.0.0',
                'description' => 'API REST multi-tenant para agentes de IA (n8n/App Flutter). Autenticación: Authorization Bearer (PAT de Sanctum o api_token legacy) + cabecera X-Owner-ID (id del tenant) + cabecera opcional X-User-ID (usuario del tenant que ejecuta la acción). Todas las operaciones están aisladas por tenant. Ola 2: recursos read-only por dominios + transversales.',
            ],
            'servers' => [
                ['url' => '/api/v1/bot'],
            ],
            'tags' => [
                ['name' => 'Transversales', 'description' => 'Contexto, resumen, reportes, búsqueda y dashboard'],
                ['name' => 'Clientes', 'description' => 'Gestión de clientes del tenant'],
                ['name' => 'Ventas', 'description' => 'Gestión de ventas del tenant'],
                ['name' => 'Comercial', 'description' => 'Productos, categorías, variantes, cotizaciones, pedidos, cupones'],
                ['name' => 'Inventario', 'description' => 'Inventarios, almacenes, lotes, movimientos, compras, proveedores'],
                ['name' => 'Finanzas', 'description' => 'Pagos, cobranzas, tesorería, asientos, impuestos, facturas'],
                ['name' => 'Logística', 'description' => 'Repartidores, zonas, entregas, cargas diarias, vehículos, conductores'],
                ['name' => 'Agenda', 'description' => 'Citas/appointments, servicios'],
                ['name' => 'CRM', 'description' => 'Prospectos, oportunidades, tickets, tareas'],
                ['name' => 'Conversaciones', 'description' => 'Mensajes y conversaciones'],
            ],
            'security' => [
                [
                    'BotAuth' => [],
                    'OwnerId' => [],
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'BotAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'api_token del usuario dueño del tenant.',
                    ],
                    'OwnerId' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-Owner-ID',
                        'description' => 'ID del tenant sobre el que opera la petición.',
                    ],
                    'UserId' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-User-ID',
                        'description' => 'Opcional: usuario del tenant que ejecuta la acción (auditoría).',
                    ],
                ],
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'required' => ['status', 'message', 'errors'],
                        'properties' => [
                            'status' => ['type' => 'string', 'enum' => ['success', 'error']],
                            'message' => ['type' => 'string'],
                            'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
                        ],
                    ],
                    'Cliente' => [
                        'type' => 'object',
                        'required' => ['id', 'nombre', 'activo'],
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'nombre' => ['type' => 'string'],
                            'rut' => ['type' => 'string', 'nullable' => true],
                            'nit' => ['type' => 'string', 'nullable' => true],
                            'email' => ['type' => 'string', 'format' => 'email', 'nullable' => true],
                            'telefono' => ['type' => 'string', 'nullable' => true],
                            'direccion' => ['type' => 'string', 'nullable' => true],
                            'ciudad' => ['type' => 'string', 'nullable' => true],
                            'region' => ['type' => 'string', 'nullable' => true],
                            'comuna' => ['type' => 'string', 'nullable' => true],
                            'giro' => ['type' => 'string', 'nullable' => true],
                            'contacto' => ['type' => 'string', 'nullable' => true],
                            'telefono_contacto' => ['type' => 'string', 'nullable' => true],
                            'categoria_id' => ['type' => 'integer', 'nullable' => true],
                            'activo' => ['type' => 'boolean'],
                        ],
                    ],
                    'ClientePayload' => [
                        'type' => 'object',
                        'required' => ['nombre'],
                        'properties' => [
                            'nombre' => ['type' => 'string', 'maxLength' => 255],
                            'rut' => ['type' => 'string', 'pattern' => '^\\d{1,2}\\.\\d{3}\\.\\d{3}-[\\dkK]$'],
                            'nit' => ['type' => 'string', 'maxLength' => 30],
                            'email' => ['type' => 'string', 'format' => 'email'],
                            'telefono' => ['type' => 'string', 'maxLength' => 30],
                            'direccion' => ['type' => 'string', 'maxLength' => 255],
                            'ciudad' => ['type' => 'string', 'maxLength' => 100],
                            'region' => ['type' => 'string', 'maxLength' => 100],
                            'comuna' => ['type' => 'string', 'maxLength' => 100],
                            'giro' => ['type' => 'string', 'maxLength' => 150],
                            'contacto' => ['type' => 'string', 'maxLength' => 150],
                            'telefono_contacto' => ['type' => 'string', 'maxLength' => 30],
                            'categoria_id' => ['type' => 'integer'],
                            'activo' => ['type' => 'boolean', 'default' => true],
                            'notas' => ['type' => 'string', 'maxLength' => 1000],
                        ],
                    ],
                    'ClientePaginated' => [
                        'type' => 'object',
                        'required' => ['items', 'total', 'limit', 'offset'],
                        'properties' => [
                            'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Cliente']],
                            'total' => ['type' => 'integer'],
                            'limit' => ['type' => 'integer'],
                            'offset' => ['type' => 'integer'],
                        ],
                    ],
                    'Venta' => [
                        'type' => 'object',
                        'required' => ['id', 'numero', 'total', 'estado'],
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'numero' => ['type' => 'string'],
                            'fecha' => ['type' => 'string', 'format' => 'date'],
                            'cliente_id' => ['type' => 'integer', 'nullable' => true],
                            'cliente_nombre' => ['type' => 'string', 'nullable' => true],
                            'subtotal' => ['type' => 'number'],
                            'iva' => ['type' => 'number'],
                            'descuento' => ['type' => 'number'],
                            'total' => ['type' => 'number'],
                            'metodo_pago' => ['type' => 'string'],
                            'tipo_documento' => ['type' => 'string', 'enum' => ['boleta', 'factura', 'nota_credito', 'cotizacion']],
                            'estado' => ['type' => 'string', 'enum' => ['pendiente', 'pagada', 'cancelada', 'completada']],
                            'es_pos' => ['type' => 'boolean'],
                            'notas' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                    'VentaItem' => [
                        'type' => 'object',
                        'required' => ['producto_id', 'cantidad', 'precio_unitario'],
                        'properties' => [
                            'producto_id' => ['type' => 'integer'],
                            'producto' => ['type' => 'string', 'nullable' => true],
                            'cantidad' => ['type' => 'number'],
                            'precio_unitario' => ['type' => 'number'],
                            'subtotal' => ['type' => 'number'],
                        ],
                    ],
                    'VentaPayload' => [
                        'type' => 'object',
                        'required' => ['detalles'],
                        'properties' => [
                            'cliente_id' => ['type' => 'integer', 'description' => 'Debe pertenecer al tenant.'],
                            'fecha' => ['type' => 'string', 'format' => 'date'],
                            'metodo_pago' => ['type' => 'string', 'maxLength' => 50],
                            'tipo_documento' => ['type' => 'string', 'enum' => ['boleta', 'factura', 'nota_credito', 'cotizacion']],
                            'es_pos' => ['type' => 'boolean', 'default' => false],
                            'estado' => ['type' => 'string', 'enum' => ['pendiente', 'pagada', 'cancelada', 'completada'], 'default' => 'pendiente'],
                            'notas' => ['type' => 'string', 'maxLength' => 2000],
                            'numero' => ['type' => 'string', 'maxLength' => 50, 'description' => 'Opcional; se genera automaticamente si se omite.'],
                            'detalles' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'object', 'required' => ['producto_id', 'cantidad', 'precio_unitario'], 'properties' => [
                                'producto_id' => ['type' => 'integer', 'description' => 'Debe pertenecer al tenant.'],
                                'cantidad' => ['type' => 'number', 'minimum' => 0.001],
                                'precio_unitario' => ['type' => 'number', 'minimum' => 0],
                            ]]],
                        ],
                    ],
                    'VentaDetallada' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/Venta'],
                            ['type' => 'object', 'properties' => [
                                'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/VentaItem']],
                            ]],
                        ],
                    ],
                    'VentaPaginated' => [
                        'type' => 'object',
                        'required' => ['items', 'total', 'limit', 'offset'],
                        'properties' => [
                            'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Venta']],
                            'total' => ['type' => 'integer'],
                            'limit' => ['type' => 'integer'],
                            'offset' => ['type' => 'integer'],
                        ],
                    ],
                    'SuccessEnvelope' => [
                        'type' => 'object',
                        'required' => ['status', 'message'],
                        'properties' => [
                            'status' => ['type' => 'string', 'enum' => ['success']],
                            'message' => ['type' => 'string'],
                            'data' => ['type' => 'object', 'nullable' => true],
                        ],
                    ],
                ],
            ],
            'paths' => [
                '/contexto' => [
                    'get' => self::operation('Obtener contexto del agente', [], null, 'Contexto del tenant para el agente.', 'SuccessEnvelope', ['tags' => ['Transversales']]),
                ],
                '/resumen' => [
                    'get' => self::operation('Resumen del tenant', [], null, 'Resumen operativo del tenant.', 'SuccessEnvelope', ['tags' => ['Transversales']]),
                ],
                '/reportes/{tipo}' => [
                    'get' => self::operation('Generar reporte', [
                        'tipo' => ['in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['ventas', 'inventario', 'cobranzas', 'finanzas', 'clientes', 'compras']]],
                        'reports' => ['in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        'desde' => ['in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                        'hasta' => ['in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                    ], null, 'Reporte generado.', 'SuccessEnvelope', ['tags' => ['Transversales']]),
                ],
                '/buscar' => [
                    'get' => self::operation('Búsqueda global', [
                        'q' => ['in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                    ], null, 'Resultados de búsqueda global.', 'SuccessEnvelope', ['tags' => ['Transversales']]),
                ],
                '/dashboard/resumen' => [
                    'get' => self::operation('Dashboard resumen', [], null, 'KPIs del dashboard.', 'SuccessEnvelope', ['tags' => ['Transversales']]),
                ],
                '/productos' => ['get' => self::operation('Listar productos', [['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'categoria_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'activo', 'schema' => ['type' => 'boolean']], ['in' => 'query', 'name' => 'stock_bajo', 'schema' => ['type' => 'boolean']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Comercial']])],
                '/categorias' => ['get' => self::operation('Listar categorías', [['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Comercial']])],
                '/variantes' => ['get' => self::operation('Listar variantes', [['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Comercial']])],
                '/skus' => ['get' => self::operation('Listar SKUs', [['in' => 'query', 'name' => 'producto_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'variante_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Comercial']])],
                '/cotizaciones' => ['get' => self::operation('Listar cotizaciones', [['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'cliente_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'desde', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'hasta', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Comercial']])],
                '/pedidos' => ['get' => self::operation('Listar pedidos', [['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'zona_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'desde', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'hasta', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Logística']])],
                '/proveedores' => ['get' => self::operation('Listar proveedores', [['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'activo', 'schema' => ['type' => 'boolean']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Inventario']])],
                '/compras' => ['get' => self::operation('Listar compras', [['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'proveedor_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Inventario']])],
                '/inventarios' => ['get' => self::operation('Listar inventarios', [['in' => 'query', 'name' => 'producto_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'almacen_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'stock_bajo', 'schema' => ['type' => 'boolean']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Inventario']])],
                '/almacenes' => ['get' => self::operation('Listar almacenes', [['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Inventario']])],
                '/lotes' => ['get' => self::operation('Listar lotes', [['in' => 'query', 'name' => 'producto_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Inventario']])],
                '/movimientos' => ['get' => self::operation('Listar movimientos', [['in' => 'query', 'name' => 'producto_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'tipo', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'desde', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'hasta', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Inventario']])],
                '/campanas' => ['get' => self::operation('Listar campañas', [['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Comercial']])],
                '/cupones' => ['get' => self::operation('Listar cupones', [['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Comercial']])],
                '/pagos' => ['get' => self::operation('Listar pagos', [['in' => 'query', 'name' => 'ventas_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'metodo', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'desde', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'hasta', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Finanzas']])],
                '/cobranzas' => ['get' => self::operation('Listar cobranzas', [['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'cliente_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'desde', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'hasta', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Finanzas']])],
                '/tesoreria' => ['get' => self::operation('Listar tesorería', [['in' => 'query', 'name' => 'tipo', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'desde', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'hasta', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Finanzas']])],
                '/asientos' => ['get' => self::operation('Listar asientos', [['in' => 'query', 'name' => 'tipo', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'cuenta', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'desde', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'hasta', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Finanzas']])],
                '/impuestos' => ['get' => self::operation('Listar impuestos', [['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Finanzas']])],
                '/facturas' => ['get' => self::operation('Listar facturas', [['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'cliente_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'folio', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'desde', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'hasta', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Finanzas']])],
                '/repartidores' => ['get' => self::operation('Listar repartidores', [['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'zona_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Logística']])],
                '/zonas' => ['get' => self::operation('Listar zonas', [['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Logística']])],
                '/entregas' => ['get' => self::operation('Listar entregas', [['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'repartidor_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Logística']])],
                '/cargas-diarias' => ['get' => self::operation('Listar cargas diarias', [['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'conductor_id', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Logística']])],
                '/vehiculos' => ['get' => self::operation('Listar vehículos', [['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Logística']])],
                '/conductores' => ['get' => self::operation('Listar conductores', [['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Logística']])],
                '/appointments' => ['get' => self::operation('Listar citas', [['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'desde', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'hasta', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Agenda']])],
                '/servicios' => ['get' => self::operation('Listar servicios', [['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Agenda']])],
                '/prospectos' => ['get' => self::operation('Listar prospectos', [['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['CRM']])],
                '/oportunidades' => ['get' => self::operation('Listar oportunidades', [['in' => 'query', 'name' => 'etapa', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['CRM']])],
                '/tickets' => ['get' => self::operation('Listar tickets', [['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'prioridad', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'desde', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'hasta', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['CRM']])],
                '/tareas' => ['get' => self::operation('Listar tareas', [['in' => 'query', 'name' => 'estado', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'asignado', 'schema' => ['type' => 'integer']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['CRM']])],
                '/mensajes' => ['get' => self::operation('Listar mensajes', [['in' => 'query', 'name' => 'canal', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'desde', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'hasta', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Conversaciones']])],
                '/conversaciones' => ['get' => self::operation('Listar conversaciones', [['in' => 'query', 'name' => 'canal', 'schema' => ['type' => 'string']], ['in' => 'query', 'name' => 'desde', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'hasta', 'schema' => ['type' => 'string', 'format' => 'date']], ['in' => 'query', 'name' => 'limit', 'schema' => ['type' => 'integer', 'default' => 50]], ['in' => 'query', 'name' => 'offset', 'schema' => ['type' => 'integer', 'default' => 0]]], null, 'Lista paginada.', 'SuccessEnvelope', ['tags' => ['Conversaciones']])],
                '/clientes' => [
                    'get' => self::operation('Listar clientes', [
                        'search' => ['in' => 'query', 'required' => false, 'description' => 'Busca por nombre, RUT, NIT, email o teléfono.', 'schema' => ['type' => 'string']],
                        'activo' => ['in' => 'query', 'required' => false, 'description' => 'Filtra por estado (true/false/1/0).', 'schema' => ['type' => 'boolean']],
                        'limit' => ['in' => 'query', 'required' => false, 'description' => 'Máximo de resultados (1-100).', 'schema' => ['type' => 'integer', 'default' => 50, 'maximum' => 100]],
                        'offset' => ['in' => 'query', 'required' => false, 'description' => 'Desplazamiento para paginación.', 'schema' => ['type' => 'integer', 'default' => 0]],
                    ], null, 'Lista de clientes paginada.', 'ClientePaginated', ['tags' => ['Clientes']]),
                    'post' => self::operation('Crear cliente', [], '#/components/schemas/ClientePayload', 'Cliente creado (201).', 'Cliente', ['tags' => ['Clientes']]),
                ],
                '/clientes/{cliente}' => [
                    'get' => self::operation('Obtener cliente', [
                        'cliente' => ['in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ], null, 'Detalle del cliente.', 'Cliente', ['tags' => ['Clientes']]),
                    'put' => self::operation('Actualizar cliente', [
                        'cliente' => ['in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ], '#/components/schemas/ClientePayload', 'Cliente actualizado. Todos los campos son opcionales.', 'Cliente', ['tags' => ['Clientes']]),
                    'delete' => self::operation('Desactivar cliente', [
                        'cliente' => ['in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ], null, 'Desactiva el cliente (activo=false, reversible).', 'SuccessEnvelope', ['tags' => ['Clientes']]),
                ],
                '/ventas' => [
                    'get' => self::operation('Listar ventas', [
                        'search' => ['in' => 'query', 'required' => false, 'description' => 'Busca por numero o nombre del cliente.', 'schema' => ['type' => 'string']],
                        'estado' => ['in' => 'query', 'required' => false, 'description' => 'Estado: pendiente, pagada, cancelada, completada.', 'schema' => ['type' => 'string', 'enum' => ['pendiente', 'pagada', 'cancelada', 'completada']]],
                        'fecha_desde' => ['in' => 'query', 'required' => false, 'description' => 'Rango inicial (Y-m-d).', 'schema' => ['type' => 'string', 'format' => 'date']],
                        'fecha_hasta' => ['in' => 'query', 'required' => false, 'description' => 'Rango final (Y-m-d).', 'schema' => ['type' => 'string', 'format' => 'date']],
                        'cliente_id' => ['in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                        'limit' => ['in' => 'query', 'required' => false, 'description' => 'Máximo de resultados (1-100).', 'schema' => ['type' => 'integer', 'default' => 50, 'maximum' => 100]],
                        'offset' => ['in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 0]],
                    ], null, 'Lista de ventas paginada.', 'VentaPaginated', ['tags' => ['Ventas']]),
                    'post' => self::operation('Crear venta', [], '#/components/schemas/VentaPayload', 'Crea la venta, sus items y descuenta stock (201).', 'VentaDetallada', ['tags' => ['Ventas']]),
                ],
                '/ventas/{venta}' => [
                    'get' => self::operation('Obtener venta', [
                        'venta' => ['in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ], null, 'Detalle de la venta con sus items.', 'VentaDetallada', ['tags' => ['Ventas']]),
                    'put' => self::operation('Actualizar venta', [
                        'venta' => ['in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ], '#/components/schemas/VentaPayload', 'Actualiza estado, notas, metodo de pago, fecha o tipo de documento (los items no se modifican).', 'VentaDetallada', ['tags' => ['Ventas']]),
                    'delete' => self::operation('Cancelar venta', [
                        'venta' => ['in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ], null, 'Marca la venta como cancelada (estado=cancelada).', 'SuccessEnvelope', ['tags' => ['Ventas']]),
                ],
            ],
        ];
    }

    protected static function operation(string $summary, array $parameters, ?string $bodyRef, string $description, string $dataSchema, array $extra = []): array
    {
        $operation = [
            'summary' => $summary,
            'parameters' => array_values($parameters),
            'responses' => [
                '200' => ['description' => $description, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string'], 'message' => ['type' => 'string'], 'data' => ['$ref' => '#/components/schemas/'.$dataSchema]]]]]],
                '201' => ['description' => 'Creado', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                '400' => ['description' => 'Solicitud inválida.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                '401' => ['description' => 'No autenticado o token/tenant inválido.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                '403' => ['description' => 'Sin permisos.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                '404' => ['description' => 'Recurso no encontrado.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                '422' => ['description' => 'Error de validación.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                '429' => ['description' => 'Límite de peticiones excedido.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
            ],
        ];

        if ($bodyRef !== null) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => ['application/json' => ['schema' => ['$ref' => $bodyRef]]],
            ];
        }

        return array_merge($operation, $extra);
    }
}
