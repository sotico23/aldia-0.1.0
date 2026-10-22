<?php

namespace App\Http\Controllers\Api\Bot;

use App\Models\Almacen;
use App\Models\Appointment;
use App\Models\Asiento;
use App\Models\Campana;
use App\Models\CargaDiaria;
use App\Models\Categoria;
use App\Models\Cobranza;
use App\Models\Compra;
use App\Models\Conductor;
use App\Models\Conversacion;
use App\Models\Cotizacion;
use App\Models\Cupon;
use App\Models\Entrega;
use App\Models\Factura;
use App\Models\Impuesto;
use App\Models\Inventario;
use App\Models\Lote;
use App\Models\Mensaje;
use App\Models\Movimiento;
use App\Models\Oportunidad;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Prospecto;
use App\Models\Proveedor;
use App\Models\Repartidor;
use App\Models\Service;
use App\Models\SkuVariante;
use App\Models\Tarea;
use App\Models\Tesoreria;
use App\Models\Ticket;
use App\Models\Variante;
use App\Models\Vehiculo;
use App\Models\Zone;
use App\Services\Api\Bot\GenericBotResourceService;
use App\Support\BotContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceBotController extends BotBaseController
{
    public function __construct(private readonly GenericBotResourceService $service) {}

    public function index(Request $request, string $resource): JsonResponse
    {
        $ctx = $this->botContext($request);
        $config = $this->getResourceConfig($resource);

        if (! $config) {
            return $this->notFound('Recurso no encontrado.');
        }

        $page = $this->service->paginate($config['model'], $ctx->ownerId, $request->all(), $config['options'] ?? []);

        $fields = $config['fields'] ?? [];
        $items = $page['items']->map(fn ($item) => $this->service->serialize($item, $fields))->values();

        return $this->ok([
            'items' => $items,
            'total' => $page['total'],
            'limit' => $page['limit'],
            'offset' => $page['offset'],
        ], ucfirst($resource).' obtenidos.');
    }

    public function show(Request $request, string $resource, int|string $id): JsonResponse
    {
        $ctx = $this->botContext($request);
        $config = $this->getResourceConfig($resource);

        if (! $config) {
            return $this->notFound('Recurso no encontrado.');
        }

        $model = $this->service->find($config['model'], $ctx->ownerId, $id, $config['options'] ?? []);

        if (! $model) {
            return $this->notFound(ucfirst($resource).' no encontrado.');
        }

        $fields = $config['fields_detail'] ?? $config['fields'] ?? [];
        $data = $this->service->serialize($model, $fields);

        if (! empty($config['detail']) && is_callable($config['detail'])) {
            $data = $config['detail']($model, $data);
        }

        return $this->ok($data, ucfirst($resource).' obtenido.');
    }

    protected function botContext(Request $request): BotContext
    {
        return $request->attributes->get('bot_context');
    }

    protected function getResourceConfig(string $resource): ?array
    {
        $configs = [
            // 2.1 Comercial + Inventario
            'productos' => [
                'model' => Producto::class,
                'options' => [
                    'search_fields' => ['nombre', 'codigo'],
                    'exact_filters' => ['categoria_id'],
                    'bool_filters' => ['activo'],
                    'order_by' => 'id',
                ],
                'fields' => ['id', 'codigo', 'nombre', 'categoria_id', 'precio_compra', 'precio_venta', 'stock_minimo', 'activo', 'unidad_medida', 'is_service', 'tiene_variantes'],
            ],
            'categorias' => [
                'model' => Categoria::class,
                'options' => [
                    'search_fields' => ['nombre'],
                    'bool_filters' => ['activo'],
                ],
                'fields' => ['id', 'nombre', 'descripcion', 'tipo', 'activo', 'mostrar_en_perfil'],
            ],
            'variantes' => [
                'model' => Variante::class,
                'options' => [
                    'bool_filters' => ['activo'],
                ],
                'fields' => ['id', 'nombre', 'tipo', 'activo'],
            ],
            'skus' => [
                'model' => SkuVariante::class,
                'options' => [
                    'exact_filters' => ['producto_id'],
                    'bool_filters' => ['activo'],
                ],
                'fields' => ['id', 'producto_id', 'sku', 'precio_venta', 'precio_compra', 'stock', 'stock_minimo', 'activo'],
            ],
            'cotizaciones' => [
                'model' => Cotizacion::class,
                'options' => [
                    'search_fields' => ['numero'],
                    'exact_filters' => ['cliente_id', 'estado'],
                    'date_from' => 'fecha',
                    'date_to' => 'fecha',
                ],
                'fields' => ['id', 'numero', 'cliente_id', 'fecha', 'fecha_validez', 'subtotal', 'impuesto', 'total', 'estado'],
            ],
            'pedidos' => [
                'model' => Pedido::class,
                'options' => [
                    'search_fields' => ['numero_pedido', 'nombre_cliente'],
                    'exact_filters' => ['estado', 'zona_id', 'repartidor_id'],
                    'date_from' => 'fecha_confirmacion',
                    'date_to' => 'fecha_confirmacion',
                ],
                'fields' => ['id', 'numero_pedido', 'estado', 'cliente_id', 'nombre_cliente', 'telefono_cliente', 'subtotal', 'impuesto', 'total', 'fecha_confirmacion', 'fecha_entrega', 'repartidor_id', 'pool_bloqueado'],
            ],
            'proveedores' => [
                'model' => Proveedor::class,
                'options' => [
                    'search_fields' => ['nombre', 'nit', 'email'],
                    'bool_filters' => ['activo'],
                ],
                'fields' => ['id', 'nombre', 'nit', 'telefono', 'email', 'direccion', 'categoria_id', 'activo'],
            ],
            'compras' => [
                'model' => Compra::class,
                'options' => [
                    'exact_filters' => ['proveedor_id', 'estado', 'almacen_id'],
                    'date_from' => 'fecha',
                    'date_to' => 'fecha',
                ],
                'fields' => ['id', 'numero', 'proveedor_id', 'almacen_id', 'fecha', 'subtotal', 'iva', 'total', 'estado'],
            ],
            'inventarios' => [
                'model' => Inventario::class,
                'options' => [
                    'exact_filters' => ['producto_id', 'almacen_id'],
                ],
                'fields' => ['id', 'producto_id', 'almacen_id', 'cantidad', 'cantidad_minima', 'ubicacion'],
            ],
            'almacenes' => [
                'model' => Almacen::class,
                'options' => [
                    'search_fields' => ['nombre', 'codigo'],
                    'bool_filters' => ['activo'],
                ],
                'fields' => ['id', 'nombre', 'codigo', 'direccion', 'telefono', 'responsable', 'tipo', 'activo', 'capacidad'],
            ],
            'lotes' => [
                'model' => Lote::class,
                'options' => [
                    'exact_filters' => ['producto_id'],
                ],
                'fields' => ['id', 'numero_lote', 'producto_id', 'cantidad', 'fecha_vencimiento', 'estado'],
            ],
            'movimientos' => [
                'model' => Movimiento::class,
                'options' => [
                    'exact_filters' => ['producto_id', 'tipo'],
                    'date_from' => 'created_at',
                    'date_to' => 'created_at',
                ],
                'fields' => ['id', 'producto_id', 'producto', 'tipo', 'cantidad', 'almacen_origen_id', 'almacen_destino_id', 'referencia', 'created_at'],
            ],
            'campanas' => [
                'model' => Campana::class,
                'options' => [
                    'search_fields' => ['nombre'],
                    'bool_filters' => ['activo'],
                ],
                'fields' => ['id', 'nombre', 'descripcion', 'tipo', 'canal', 'fecha_inicio', 'fecha_fin', 'presupuesto', 'estado'],
            ],
            'cupones' => [
                'model' => Cupon::class,
                'options' => [
                    'search_fields' => ['codigo'],
                    'bool_filters' => ['activa'],
                ],
                'fields' => ['id', 'codigo', 'tipo', 'valor', 'max_usos', 'usos_actuales', 'fecha_inicio', 'fecha_fin', 'activa'],
            ],
            // 2.2 Finanzas + Facturación
            'pagos' => [
                'model' => Pago::class,
                'options' => [
                    'exact_filters' => ['factura_id', 'metodo_pago'],
                    'date_from' => 'fecha_pago',
                    'date_to' => 'fecha_pago',
                ],
                'fields' => ['id', 'factura_id', 'proveedor_id', 'monto', 'fecha_pago', 'metodo_pago', 'referencia', 'estado'],
            ],
            'cobranzas' => [
                'model' => Cobranza::class,
                'options' => [
                    'exact_filters' => ['factura_id', 'cliente_id', 'estado'],
                    'date_from' => 'fecha_pago',
                    'date_to' => 'fecha_pago',
                ],
                'fields' => ['id', 'factura_id', 'cliente_id', 'monto', 'fecha_pago', 'metodo_pago', 'referencia', 'estado'],
            ],
            'tesoreria' => [
                'model' => Tesoreria::class,
                'options' => [
                    'exact_filters' => ['tipo'],
                    'date_from' => 'fecha',
                    'date_to' => 'fecha',
                ],
                'fields' => ['id', 'tipo', 'monto', 'descripcion', 'fecha', 'categoria', 'estado'],
            ],
            'asientos' => [
                'model' => Asiento::class,
                'options' => [
                    'exact_filters' => ['tipo', 'estado'],
                    'date_from' => 'fecha',
                    'date_to' => 'fecha',
                ],
                'fields' => ['id', 'fecha', 'numero', 'descripcion', 'tipo', 'total_debe', 'total_haber', 'estado'],
            ],
            'impuestos' => [
                'model' => Impuesto::class,
                'options' => [
                    'search_fields' => ['nombre', 'codigo'],
                ],
                'fields' => ['id', 'nombre', 'codigo', 'tasa', 'tipo', 'estado'],
            ],
            'facturas' => [
                'model' => Factura::class,
                'options' => [
                    'search_fields' => ['numero'],
                    'exact_filters' => ['cliente_id', 'estado'],
                    'date_from' => 'fecha',
                    'date_to' => 'fecha',
                ],
                'fields' => ['id', 'numero', 'cliente_id', 'fecha', 'fecha_vencimiento', 'subtotal', 'impuesto', 'total', 'estado', 'tipo'],
            ],
            // 2.3 Logística / Delivery
            'repartidores' => [
                'model' => Repartidor::class,
                'options' => [
                    'exact_filters' => ['estado', 'vehiculo_id'],
                ],
                'fields' => ['id', 'user_id', 'estado', 'lat', 'lng', 'vehiculo_id', 'radio_km', 'capacidad_max', 'last_position_at'],
            ],
            'zonas' => [
                'model' => Zone::class,
                'options' => [
                    'search_fields' => ['name'],
                    'bool_filters' => ['activa'],
                ],
                'fields' => ['id', 'name', 'lat', 'lng', 'radio_km', 'capacidad_max', 'activa'],
            ],
            'entregas' => [
                'model' => Entrega::class,
                'options' => [
                    'exact_filters' => ['estado', 'conductor_id', 'vehiculo_id'],
                    'date_from' => 'fecha_entrega',
                    'date_to' => 'fecha_entrega',
                ],
                'fields' => ['id', 'venta_id', 'conductor_id', 'vehiculo_id', 'cliente_id', 'cliente', 'direccion', 'fecha_entrega', 'estado'],
            ],
            'cargas-diarias' => [
                'model' => CargaDiaria::class,
                'options' => [
                    'exact_filters' => ['estado', 'conductor_id', 'vehiculo_id'],
                    'date_from' => 'fecha',
                    'date_to' => 'fecha',
                ],
                'fields' => ['id', 'vehiculo_id', 'conductor_id', 'almacen_id', 'fecha', 'estado', 'ventas_totales', 'devoluciones_totales'],
            ],
            'vehiculos' => [
                'model' => Vehiculo::class,
                'options' => [
                    'search_fields' => ['placa', 'marca', 'modelo'],
                    'exact_filters' => ['estado'],
                ],
                'fields' => ['id', 'placa', 'marca', 'modelo', 'tipo', 'estado', 'lat', 'lng', 'ultima_actualizacion'],
            ],
            'conductores' => [
                'model' => Conductor::class,
                'options' => [
                    'search_fields' => ['nombre', 'rut'],
                    'exact_filters' => ['estado'],
                ],
                'fields' => ['id', 'nombre', 'rut', 'telefono', 'estado', 'licencia', 'fecha_vencimiento_licencia'],
            ],
            // 2.4 Agenda + CRM + Reportes
            'appointments' => [
                'model' => Appointment::class,
                'options' => [
                    'exact_filters' => ['status', 'client_id', 'provider_id'],
                    'date_from' => 'start_time',
                    'date_to' => 'start_time',
                ],
                'fields' => ['id', 'client_id', 'provider_id', 'producto_id', 'start_time', 'end_time', 'status', 'payment_status', 'amount_paid'],
            ],
            'servicios' => [
                'model' => Service::class,
                'options' => [
                    'search_fields' => ['name'],
                    'bool_filters' => ['is_active'],
                ],
                'fields' => ['id', 'name', 'description', 'duration', 'price', 'is_active'],
            ],
            'prospectos' => [
                'model' => Prospecto::class,
                'options' => [
                    'search_fields' => ['nombre', 'email', 'telefono'],
                    'exact_filters' => ['estado'],
                ],
                'fields' => ['id', 'nombre', 'email', 'telefono', 'empresa', 'estado', 'prioridad', 'valor_estimado', 'fecha_seguimiento'],
            ],
            'oportunidades' => [
                'model' => Oportunidad::class,
                'options' => [
                    'search_fields' => ['nombre'],
                    'exact_filters' => ['etapa', 'cliente_id'],
                ],
                'fields' => ['id', 'nombre', 'cliente_id', 'valor', 'etapa', 'probabilidad', 'fecha_cierre_estimada'],
            ],
            'tickets' => [
                'model' => Ticket::class,
                'options' => [
                    'search_fields' => ['titulo'],
                    'exact_filters' => ['estado', 'prioridad', 'asignado_a', 'assigned_user_id'],
                    'date_from' => 'created_at',
                    'date_to' => 'created_at',
                ],
                'fields' => ['id', 'titulo', 'cliente_id', 'prioridad', 'estado', 'categoria', 'asignado_a', 'created_at'],
            ],
            'tareas' => [
                'model' => Tarea::class,
                'options' => [
                    'exact_filters' => ['estado', 'prioridad', 'empleado_id'],
                    'date_from' => 'fecha_limite',
                    'date_to' => 'fecha_limite',
                ],
                'fields' => ['id', 'titulo', 'user_id', 'empleado_id', 'estado', 'prioridad', 'fecha_limite'],
            ],
            'mensajes' => [
                'model' => Mensaje::class,
                'options' => [
                    'exact_filters' => ['conversacion_id', 'leido'],
                    'date_from' => 'created_at',
                    'date_to' => 'created_at',
                ],
                'fields' => ['id', 'conversacion_id', 'autor', 'texto', 'tipo_mensaje', 'leido', 'created_at'],
            ],
            'conversaciones' => [
                'model' => Conversacion::class,
                'options' => [
                    'exact_filters' => ['pedido_id', 'vendedor_id'],
                    'date_from' => 'ultimo_mensaje_at',
                    'date_to' => 'ultimo_mensaje_at',
                ],
                'fields' => ['id', 'pedido_id', 'public_profile_id', 'comprador_id', 'vendedor_id', 'titulo', 'ultimo_mensaje_at'],
            ],
        ];

        return $configs[$resource] ?? null;
    }
}
