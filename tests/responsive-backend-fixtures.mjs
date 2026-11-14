const today = new Date().toISOString().slice(0, 10);
const dateTime = `${today}T09:00:00`;
const almacen = { id: 1, nombre: 'Almacén principal de pruebas' };
const cliente = { id: 1, nombre: 'Cliente de prueba', name: 'Cliente de prueba', rut: null, direccion: null, comuna: null, ciudad: null, region: null };
const producto = {
    id: 1, codigo: 'PRUEBA-001', nombre: 'Producto de prueba para pantallas pequeñas',
    descripcion: 'Producto sintético para las pruebas de interfaz.',
    precio_venta: 14990, precio_con_variantes: 14990, stock: 25, stock_minimo: 2,
    total_stock: 25, unidad_medida: 'unidad', peso_base: 1, contenido_por_unidad: 1,
    medida_pesable: false, imagen: null, tiene_variantes: false, skus: [],
    inventarios: [{ almacen_id: 1, almacen_nombre: almacen.nombre, cantidad: 25, cantidad_minima: 2 }],
};
const paginate = (data) => ({
    data,
    total: data.length,
    current_page: 1,
    last_page: 1,
    per_page: 20,
    from: data.length ? 1 : null,
    to: data.length || null,
    links: [
        { label: '&laquo; Anterior', url: null, active: false },
        { label: '1', url: '#', active: true },
        { label: 'Siguiente &raquo;', url: null, active: false },
    ],
    meta: { total: data.length, current_page: 1, last_page: 1, per_page: 20, from: 1, to: data.length },
});
const prospecto = {
    id: 1, nombre: 'Prospecto de prueba', rut: null, email: 'prospecto@example.test',
    telefono: null, empresa: 'Empresa de prueba', giro: null, cargo: null,
    direccion: null, comuna: null, region: null, descripcion: null,
    fuente: 'web', estado: 'nuevo', prioridad: 'media', valor_estimado: 250000,
    fecha_seguimiento: null, notas: null,
};

export default [
    {
        path: '/inventarios',
        component: 'Backend/Inventarios/Index',
        props: {
            inventarios: paginate([{
                id: 1, codigo: producto.codigo, nombre: producto.nombre,
                categoria: 'Categoría de prueba', total_stock: 25, stock_minimo: 2,
                avg_daily_sales: 1, days_remaining: 25, status: 'optimal', imagen: null,
                inventarios: [{ almacen: almacen.nombre, cantidad: 25, cantidad_minima: 2, ubicacion: 'Pasillo A' }],
                projection: [{ name: 'Hoy', stock: 25 }, { name: 'Mañana', stock: 24 }],
            }]),
            productos: [producto], almacenes: [almacen], filters: {},
            stockPorBodega: [{ nombre: almacen.nombre, stock: 25 }],
            productosStockBajo: [], productosTopStock: [{ nombre: producto.nombre, stock: 25 }],
            ventasPorFecha: [{ fecha: today, ventas: 10000 }], ventasPorAlmacen: [],
        },
    },
    {
        path: '/prospectos',
        component: 'Backend/Prospectos/Index',
        props: { prospectos: paginate([prospecto]), filters: {} },
    },
    {
        path: '/oportunidades',
        component: 'Backend/Oportunidades/Index',
        props: {
            oportunidades: paginate([{
                id: 1, nombre: 'Oportunidad de prueba', cliente_id: 1, cliente,
                valor: 250000, etapa: 'prospeccion', probabilidad: 20,
                fecha_cierre_estimada: null, descripcion: null,
            }]),
            clientes: [cliente], filters: {},
        },
    },
    {
        path: '/pos',
        component: 'Backend/Pos/Index',
        ready: '!!document.querySelector(\'input[placeholder="Buscar producto o escanear..."]\')',
        props: {
            productos: [producto, { ...producto, id: 2, codigo: 'PRUEBA-002', nombre: 'Segundo producto de prueba' }],
            clientes: [cliente], almacenes: [almacen], empleados: [], empleado_id: null,
            puede_seleccionar_cajero: false, cajero_actual: { id: null, nombre: 'Cajero de prueba' },
            promociones: [], delivery_rates: [], iva_tasa: 0.19,
        },
    },
    {
        path: '/appointments/calendar',
        component: 'appointments/Calendar',
        props: {
            appointments: [{
                id: 1, title: 'Cita de prueba', start_time: dateTime,
                end_time: `${today}T10:00:00`, status: 'pendiente',
                client: cliente, producto, notes: 'Cita sintética',
            }],
            services: [producto], clients: [cliente], googleConnected: false,
            googleAuthUrl: null, googleConnectedEmail: null, googleCalendarId: null,
            monthlyStats: { total: 1, completadas: 0, canceladas: 0, ingresos: 14990, pendientes: 1, tasaCompletitud: 0 },
            appointmentsByDayMonth: [{ date: new Date().getDate(), total: 1 }],
            statusDistributionMonth: [{ name: 'Pendiente', value: 1, color: '#f59e0b' }],
            monthServices: [{ name: 'Servicio de prueba', value: 1 }],
            providerWorkload: [],
        },
    },
    {
        path: '/call-center',
        component: 'Backend/CallCenter/Index',
        props: {
            llamadas: paginate([{
                id: 1, user_id: 1, cliente_id: 1, prospecto_id: null,
                tipo: 'saliente', numero_telefono: '+56912345678', estado: 'completada',
                duracion: 120, fecha: dateTime, notas: null, cliente, gestiones: [],
            }]),
            contactos: [{ id: 1, nombre: cliente.nombre, telefono: '+56912345678', tipo: 'cliente' }],
            stats: { total_llamadas: 1, llamadas_hoy: 1, gestiones_hoy: 1, promedio_duracion: 120 },
            filters: {}, programaciones: [],
        },
    },
    {
        path: '/tickets',
        component: 'Backend/Tickets/Index',
        props: {
            tickets: paginate([{
                id: 1, titulo: 'Solicitud de soporte de prueba', descripcion: 'Ejemplo de soporte.',
                cliente_id: 1, cliente, producto_id: 1, producto, prioridad: 'media',
                estado: 'abierto', categoria: 'General', asignado_a: null,
                assigned_user_id: null, assigned_user: null, created_at: dateTime,
            }]),
            clientes: [cliente], productos: [producto], employees: [], filters: {},
        },
    },
    {
        path: '/campanas',
        component: 'Backend/Campanas/Index',
        props: {
            campanas: paginate([{
                id: 1, nombre: 'Campaña de prueba', descripcion: 'Contenido sintético.',
                tipo: 'digital', canal: 'Email Marketing', objetivo: 'Captar clientes',
                fecha_inicio: today, fecha_fin: today, presupuesto: 100000, presupuesto_real: 10000,
                visitas: 10, leads: 2, conversiones: 1, alcance: 100, roi: 10, estado: 'activa',
            }]),
            filters: {},
        },
    },
    {
        path: '/pos/reportes',
        component: 'Backend/Pos/Reportes',
        props: {
            ranking: [{ nombre: producto.nombre, total_vendidos: 2, total_ingresos: 29980, total_ganancia: 10000 }],
            utilidad: 10000, totalMes: 29980, almacenes: [almacen], almacenId: null,
            cajeros: [], empleadoId: null, ventasPorCajero: [], desgloseProductos: [],
            totales: { monto_total: 29980, ganancia_total: 10000, total_ventas: 2 },
            fechaDesde: today, fechaHasta: today,
        },
    },
    {
        path: '/inventario-cierre',
        component: 'Backend/Inventarios/Cierre/Index',
        props: { cierres: paginate([]), almacenes: [almacen], filters: {} },
    },
    {
        path: '/pedidos-recibidos',
        component: 'Backend/PedidosRecibidos/Index',
        props: {
            pedidos: paginate([{
                id: 1, numero_pedido: 'PRUEBA-001', cliente,
                nombre_cliente: cliente.nombre, estado: 'pendiente', total: 14990,
                created_at: dateTime,
                items: [{ id: 1, nombre_producto: producto.nombre, cantidad: 1, subtotal: 14990 }],
            }]),
            filtros: {},
        },
    },
    {
        path: '/sii/configuracion/folios',
        component: 'Backend/Sii/Configuracion/Folios',
        props: { cafs: [{ id: 1, tipo: 33, desde: 1, hasta: 100, disponibles: 75, created_at: dateTime }] },
    },
    {
        path: '/instructor/cursos/1/edit',
        component: 'LMS/Instructor/Courses/Edit',
        props: {
            course: {
                id: 1, title: 'Curso de prueba', description: 'Contenido sintético del curso.',
                price: 14990, is_published: false, features: ['Contenido de prueba'],
                category_id: 1, certificado_html: '',
                modules: [{
                    id: 1, title: 'Módulo de prueba', order: 1,
                    lessons: [{ id: 1, title: 'Lección de prueba', description: 'Contenido sintético.', order: 1, video_url: '' }],
                }],
            },
            categories: [{ id: 1, name: 'Categoría de prueba', nombre: 'Categoría de prueba' }],
        },
    },
    {
        path: '/chat/1',
        component: 'marketplace/ChatShow',
        ready: '!!document.querySelector(\'input[placeholder="Escribe un mensaje..."]\')',
        props: {
            conversation: {
                id: 1, buyer: { id: 2, name: 'Comprador de prueba' },
                store: { id: 1, title: 'Tienda de prueba', user_id: 1 },
            },
            messages: [{
                id: 1, body: 'Mensaje sintético para comprobar la conversación en móvil.',
                image_path: null, sender_id: 2, sender: { id: 2, name: 'Comprador de prueba' },
                created_at: dateTime,
            }],
        },
    },
    {
        path: '/conversaciones-pedidos/1/chat',
        component: 'marketplace/ChatPedido',
        ready: '!!document.querySelector(\'input[placeholder="Escribe un mensaje..."]\')',
        props: {
            conversacion: {
                id: 1,
                pedido: { id: 1, numero_pedido: 'PRUEBA-001', estado: 'pendiente', total: 14990 },
                comprador: { id: 2, name: 'Comprador de prueba' },
                publicProfile: { id: 1, title: 'Tienda de prueba', slug: 'tienda-de-prueba', user_id: 1 },
                mensajes: [], otro_usuario: { id: 2, name: 'Comprador de prueba' },
            },
            mensajes: [{
                id: 1, sender_id: 2, contenido: 'Mensaje sintético asociado al pedido.',
                created_at: dateTime, sender: { id: 2, name: 'Comprador de prueba' },
                leido: false,
            }],
        },
    },
];
