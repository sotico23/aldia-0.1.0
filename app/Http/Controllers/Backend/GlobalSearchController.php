<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Producto;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q');
        if (! $query || strlen($query) < 2) {
            return response()->json([]);
        }

        $ownerId = auth()->user()->getOwnerId();
        $results = [];
        $safeQuery = SearchHelper::escapeLike($query);

        // 1. Acciones Rápidas (Estático e Inteligente)
        $quickActions = [
            ['name' => 'Crear Factura Electrónica', 'type' => 'Acción', 'href' => '/facturacion', 'icon' => 'PlusCircle', 'keywords' => ['factura', 'dte', 'emitir', 'crear']],
            ['name' => 'Ver Listado de Facturas', 'type' => 'Navegación', 'href' => '/facturacion', 'icon' => 'List', 'keywords' => ['factura', 'ver', 'listado']],
            ['name' => 'Configuración de Emisor SII', 'type' => 'Configuración', 'href' => '/sii', 'icon' => 'Settings', 'keywords' => ['sii', 'certificado', 'configuracion', 'rut']],
            ['name' => 'Subir archivo CAF (Folios)', 'type' => 'Acción', 'href' => '/sii', 'icon' => 'Upload', 'keywords' => ['caf', 'folios', 'sii', 'autorizar']],

            ['name' => 'Nuevo Prospecto (CRM)', 'type' => 'Acción', 'href' => '/prospectos', 'icon' => 'UserPlus', 'keywords' => ['prospecto', 'crm', 'nuevo', 'lead', 'venta']],
            ['name' => 'Ver Pipeline de Ventas', 'type' => 'Navegación', 'href' => '/prospectos', 'icon' => 'Kanban', 'keywords' => ['prospecto', 'crm', 'kanban', 'pipeline', 'ventas']],
            ['name' => 'Nuevo Cliente', 'type' => 'Acción', 'href' => '/clientes', 'icon' => 'UserPlus', 'keywords' => ['cliente', 'nuevo', 'crear']],

            ['name' => 'Nuevo Producto', 'type' => 'Acción', 'href' => '/productos', 'icon' => 'Plus', 'keywords' => ['producto', 'item', 'nuevo', 'crear']],
            ['name' => 'Movimientos de Stock', 'type' => 'Navegación', 'href' => '/inventarios', 'icon' => 'ArrowLeftRight', 'keywords' => ['stock', 'inventario', 'movimiento', 'ajuste']],

            ['name' => 'Ir al punto de venta (POS)', 'type' => 'Navegación', 'href' => '/pos', 'icon' => 'Calculator', 'keywords' => ['pos', 'venta', 'caja', 'vender']],
            ['name' => 'Cierre de Caja Diario', 'type' => 'Acción', 'href' => '/pos/cierre', 'icon' => 'Lock', 'keywords' => ['caja', 'cierre', 'dinero', 'arqueo']],
        ];

        // 2. Módulos y Navegación Completa (Basado en el Sidebar)
        $navigation = [
            // COMERCIAL
            ['name' => 'Leads & Pipeline (CRM)', 'type' => 'Navegación', 'href' => '/prospectos', 'icon' => 'Users', 'keywords' => ['crm', 'ventas', 'leads', 'prospectos', 'prospecto']],
            ['name' => 'Oportunidades', 'type' => 'Navegación', 'href' => '/oportunidades', 'icon' => 'Target', 'keywords' => ['ventas', 'crm']],
            ['name' => 'Categorías de Venta', 'type' => 'Navegación', 'href' => '/categorias', 'icon' => 'Tags', 'keywords' => ['categorias', 'ventas']],
            ['name' => 'Productos y Servicios', 'type' => 'Navegación', 'href' => '/productos', 'icon' => 'PackageSearch', 'keywords' => ['inventario', 'catalogo', 'productos']],
            ['name' => 'Clientes', 'type' => 'Navegación', 'href' => '/clientes', 'icon' => 'User', 'keywords' => ['crm', 'comercial', 'listado']],
            ['name' => 'Cotizaciones (Presupuestos)', 'type' => 'Navegación', 'href' => '/cotizaciones', 'icon' => 'FileText', 'keywords' => ['ventas', 'precio', 'presupuesto']],
            ['name' => 'Ventas (SFA)', 'type' => 'Navegación', 'href' => '/ventas', 'icon' => 'TrendingUp', 'keywords' => ['ventas', 'comercial', 'sfa']],
            ['name' => 'Campañas de Marketing', 'type' => 'Navegación', 'href' => '/campanas', 'icon' => 'Megaphone', 'keywords' => ['marketing', 'campaña']],
            ['name' => 'Tickets Soporte (Helpdesk)', 'type' => 'Navegación', 'href' => '/tickets', 'icon' => 'LifeBuoy', 'keywords' => ['ayuda', 'soporte', 'tickets']],

            // OPERACIONES
            ['name' => 'Gestión de Stock', 'type' => 'Navegación', 'href' => '/inventarios', 'icon' => 'Package', 'keywords' => ['stock', 'almacen', 'operaciones']],
            ['name' => 'Movimientos de Stock', 'type' => 'Navegación', 'href' => '/movimientos', 'icon' => 'ArrowLeftRight', 'keywords' => ['stock', 'ajuste', 'traslado']],
            ['name' => 'Bodegas (WMS)', 'type' => 'Navegación', 'href' => '/almacenes', 'icon' => 'Warehouse', 'keywords' => ['bodega', 'almacen', 'wms']],
            ['name' => 'Inventario Físico (MRP)', 'type' => 'Navegación', 'href' => '/planificacion', 'icon' => 'ClipboardList', 'keywords' => ['mrp', 'fisico', 'inventario']],
            ['name' => 'Órdenes de Compra', 'type' => 'Navegación', 'href' => '/compras', 'icon' => 'ShoppingCart', 'keywords' => ['compra', 'proveedor', 'gasto']],
            ['name' => 'Proveedores', 'type' => 'Navegación', 'href' => '/proveedors', 'icon' => 'Building2', 'keywords' => ['compras', 'proveedor']],
            ['name' => 'Recepción de Mercadería', 'type' => 'Navegación', 'href' => '/recepcion', 'icon' => 'Truck', 'keywords' => ['logistica', 'bodega', 'recepcion']],

            // CENTRO DE COSTOS / PROYECTOS
            ['name' => 'Proyectos (PMS)', 'type' => 'Navegación', 'href' => '/proyectos', 'icon' => 'Briefcase', 'keywords' => ['proyectos', 'pms', 'obras']],
            ['name' => 'Gastos de Obra', 'type' => 'Navegación', 'href' => '/obra/gastos', 'icon' => 'HardHat', 'keywords' => ['obras', 'gastos', 'proyectos']],
            ['name' => 'Gastos Generales', 'type' => 'Navegación', 'href' => '/gastos', 'icon' => 'Coins', 'keywords' => ['gastos', 'egresos']],
            ['name' => 'Liquidaciones de Proyecto', 'type' => 'Navegación', 'href' => '/proyectos/liquidaciones', 'icon' => 'CheckSquare', 'keywords' => ['liquidaciones', 'obra']],

            // FINANZAS
            ['name' => 'Tesorería (Caja)', 'type' => 'Navegación', 'href' => '/tesoreria', 'icon' => 'Wallet', 'keywords' => ['caja', 'dinero', 'tesoreria']],
            ['name' => 'Cobranzas (AR)', 'type' => 'Navegación', 'href' => '/cobranzas', 'icon' => 'BadgeDollarSign', 'keywords' => ['cobro', 'deuda', 'ar']],
            ['name' => 'Conciliación Bancaria', 'type' => 'Navegación', 'href' => '/conciliacion', 'icon' => 'University', 'keywords' => ['banco', 'conciliacion']],
            ['name' => 'Impuestos', 'type' => 'Navegación', 'href' => '/impuestos', 'icon' => 'Landmark', 'keywords' => ['iva', 'renta', 'tax']],
            ['name' => 'Contabilidad (GL)', 'type' => 'Navegación', 'href' => '/contabilidad', 'icon' => 'BarChart3', 'keywords' => ['libro', 'diario', 'mayor']],
            ['name' => 'Facturación Electrónica (DTE)', 'type' => 'Navegación', 'href' => '/facturacion', 'icon' => 'FileText', 'keywords' => ['factura', 'dte', 'sii']],
            ['name' => 'Reportes Financieros', 'type' => 'Navegación', 'href' => '/reportes', 'icon' => 'PieChart', 'keywords' => ['reportes', 'ganancia', 'finanzas']],

            // RRHH
            ['name' => 'Empleados', 'type' => 'Navegación', 'href' => '/empleados', 'icon' => 'UsersRound', 'keywords' => ['rrhh', 'personal', 'nomina']],
            ['name' => 'Asistencia (Reloj Control)', 'type' => 'Navegación', 'href' => '/asistencia', 'icon' => 'Clock', 'keywords' => ['reloj', 'asistencia', 'rrhh']],
            ['name' => 'Nóminas (Sueldos)', 'type' => 'Navegación', 'href' => '/nominas', 'icon' => 'Banknote', 'keywords' => ['sueldo', 'pago', 'remuneracion']],
            ['name' => 'Evaluaciones de Desempeño', 'type' => 'Navegación', 'href' => '/evaluaciones', 'icon' => 'Star', 'keywords' => ['evaluacion', 'desempeño']],

            // CONFIGURACIÓN
            ['name' => 'Configuración de Empresa', 'type' => 'Configuración', 'href' => '/empresa', 'icon' => 'Building', 'keywords' => ['empresa', 'ajustes', 'perfil']],
            ['name' => 'Usuarios y Accesos', 'type' => 'Configuración', 'href' => '/usuarios', 'icon' => 'ShieldCheck', 'keywords' => ['permisos', 'usuarios', 'roles']],
            ['name' => 'Ajustes del Sistema', 'type' => 'Configuración', 'href' => '/settings', 'icon' => 'Cog', 'keywords' => ['ajustes', 'configuracion']],
        ];

        foreach ($navigation as $item) {
            $matched = false;
            foreach ($item['keywords'] as $kw) {
                if (stripos($kw, $query) !== false) {
                    $matched = true;
                    break;
                }
            }
            if ($matched || stripos($item['name'], $query) !== false) {
                $results[] = [
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'href' => $item['href'],
                    'icon' => $item['icon'],
                    'subtitle' => 'Sección del sistema',
                ];
            }
        }

        // 3. Facturas
        $facturas = Factura::where('owner_id', $ownerId)
            ->where('numero', 'like', "%{$safeQuery}%")
            ->with('cliente')
            ->limit(5)
            ->get();

        foreach ($facturas as $f) {
            $results[] = [
                'name' => "Factura #{$f->numero}",
                'type' => 'Registro',
                'href' => '/facturacion',
                'icon' => 'FileText',
                'subtitle' => 'Cliente: '.($f->cliente?->nombre ?? 'Desconocido'),
            ];
        }

        // 4. Clientes
        $clientes = Cliente::where('owner_id', $ownerId)
            ->where(function ($q) use ($safeQuery) {
                $q->where('nombre', 'like', "%{$safeQuery}%")
                    ->orWhere('rut', 'like', "%{$safeQuery}%");
            })
            ->limit(5)
            ->get();

        foreach ($clientes as $c) {
            $results[] = [
                'name' => $c->nombre,
                'type' => 'Cliente',
                'href' => '/clientes',
                'icon' => 'User',
                'subtitle' => $c->rut,
            ];
        }

        // 5. Productos
        $productos = Producto::where('owner_id', $ownerId)
            ->where('nombre', 'like', "%{$safeQuery}%")
            ->limit(5)
            ->get();

        foreach ($productos as $p) {
            $results[] = [
                'name' => $p->nombre,
                'type' => 'Producto',
                'href' => '/productos',
                'icon' => 'Package',
                'subtitle' => 'Prec. Unit: $'.number_format((float) $p->precio, 0, ',', '.'),
            ];
        }

        return response()->json($results);
    }
}
