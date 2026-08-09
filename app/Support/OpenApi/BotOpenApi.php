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
                'version' => '1.0.0',
                'description' => 'API REST multi-tenant para agentes de IA (n8n). Autenticación: Authorization Bearer (api_token del tenant) + cabecera X-Owner-ID (id del tenant) + cabecera opcional X-User-ID (usuario del tenant que ejecuta la acción). Todas las operaciones están aisladas por tenant.',
            ],
            'servers' => [
                ['url' => '/api/v1/bot'],
            ],
            'tags' => [
                ['name' => 'Clientes', 'description' => 'Gestión de clientes del tenant'],
                ['name' => 'Ventas', 'description' => 'Gestión de ventas del tenant'],
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
