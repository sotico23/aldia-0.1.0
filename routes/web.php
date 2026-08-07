<?php

use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Backend\ApiIntegrationController;
use App\Http\Controllers\Backend\AutomationController;
use App\Http\Controllers\Backend\AutomationExecutionController;
use App\Http\Controllers\Backend\ChannelCredentialController;
use App\Http\Controllers\Backend\ConversacionPedidoController;
use App\Http\Controllers\Backend\CotizacionController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\FollowerController;
use App\Http\Controllers\Backend\GlobalSearchController;
use App\Http\Controllers\Backend\MensajeController;
use App\Http\Controllers\Backend\MercadoPagoConfigController;
use App\Http\Controllers\Backend\OnboardingController;
use App\Http\Controllers\Backend\PayPalConfigController;
use App\Http\Controllers\Backend\PedidoRecibidoController;
use App\Http\Controllers\Backend\PlanController;
use App\Http\Controllers\Backend\PlatformPaymentConfigController;
use App\Http\Controllers\Backend\ProductoController;
use App\Http\Controllers\Backend\PublicacionController;
use App\Http\Controllers\Backend\SiiController;
use App\Http\Controllers\Backend\SystemIntegrationController;
use App\Http\Controllers\Backend\TareaController;
use App\Http\Controllers\Backend\TelegramCallbackController;
use App\Http\Controllers\Backend\TelegramLinkingController;
use App\Http\Controllers\Backend\TenantChannelConfigController;
use App\Http\Controllers\Backend\VentaController;
use App\Http\Controllers\Backend\WebpayConfigController;
use App\Http\Controllers\Backend\WebpayController;
use App\Http\Controllers\Backend\WebpayTransactionController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\PayPalController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\Proveedor\ProveedorDashboardController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\StatusPageController;
use App\Http\Controllers\Webhooks\MercadoPagoWebhookController;
use App\Http\Controllers\Webhooks\PaypalWebhookController;
use App\Http\Controllers\Webhooks\TelegramWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WelcomeController::class, 'index'])->name('home');
Route::get('/quienes-somos', [WelcomeController::class, 'quienesSomos'])->name('quienes-somos');
Route::get('/feedback', [WelcomeController::class, 'feedback'])->name('feedback');
Route::get('/fundacion', [WelcomeController::class, 'fundacion'])->name('fundacion');
Route::get('/status', [StatusPageController::class, 'index'])->name('status');
Route::get('/status/embed', [StatusPageController::class, 'embed'])->name('status.embed');

Route::middleware(['auth'])->group(function () {
    Route::post('publicaciones', [PublicacionController::class, 'store'])->name('publicaciones.store')->middleware('permission:general.comunidad.edit');
    Route::post('/publicaciones/{publicacion}/share', [PublicacionController::class, 'share'])->name('publicaciones.share')->middleware('permission:general.comunidad.edit');
    Route::post('/publicaciones/{publicacion}/react', [PublicacionController::class, 'react'])->name('publicaciones.react')->middleware('ownership:publicacion');
    Route::post('/comentarios/{comentario}/react', [PublicacionController::class, 'reactComment'])->name('comentarios.react')->middleware('ownership:comentario');
    Route::post('/publicaciones/{publicacion}/comment', [PublicacionController::class, 'comment'])->name('publicaciones.comment')->middleware('ownership:publicacion');
    Route::put('/publicaciones/{publicacion}', [PublicacionController::class, 'update'])->name('publicaciones.update')->middleware('ownership:publicacion');
    Route::delete('/publicaciones/{publicacion}', [PublicacionController::class, 'destroy'])->name('publicaciones.destroy')->middleware('ownership:publicacion');
    Route::get('/notifications', function () {
        $user = auth()->user();
        $notifications = $user->notifications()->latest()->paginate(20);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    })->name('notifications.index');

    Route::post('/notifications/mark-as-read', function () {
        auth()->user()->unreadNotifications->markAsRead();

        return back();
    })->name('notifications.mark-as-read');

    Route::delete('/notifications/{id}', function (string $id) {
        $notification = auth()->user()->notifications()->find($id);
        if ($notification) {
            $notification->delete();
        }

        return response()->json(['success' => true]);
    })->name('notifications.destroy');

    // Seguidores
    Route::post('/usuarios/{user}/follow', [FollowerController::class, 'follow'])->name('usuarios.follow');
    Route::delete('/usuarios/{user}/unfollow', [FollowerController::class, 'unfollow'])->name('usuarios.unfollow');

    Route::get('/perfil/{user}', [ProfileController::class, 'show'])->name('profile.show');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware(['permission:ver dashboard', 'throttle:dashboard']);
    Route::post('dashboard/config', [DashboardController::class, 'saveConfig'])->name('dashboard.config')->middleware('permission:ver dashboard');
    Route::post('dashboard/widgets/toggle', [DashboardController::class, 'toggleWidget'])->name('dashboard.widgets.toggle')->middleware('permission:ver dashboard');
    Route::post('dashboard/widgets/reorder', [DashboardController::class, 'reorderWidgets'])->name('dashboard.widgets.reorder')->middleware('permission:ver dashboard');

    // System Integrations (global n8n config) — only Master/Super Admin
    Route::prefix('api/system-integrations')->name('system-integrations.')->middleware(['permission:admin.web-settings.edit'])->group(function () {
        Route::get('n8n', [SystemIntegrationController::class, 'show'])->name('show');
        Route::match(['put', 'post'], 'n8n', [SystemIntegrationController::class, 'update'])->name('update');
        Route::post('n8n/test', [SystemIntegrationController::class, 'testConnection'])->name('test');
        Route::post('n8n/test-whatsapp', [SystemIntegrationController::class, 'testWhatsAppConnection'])->name('test-whatsapp');
    });

    Route::middleware(['permission:sistema.automatizaciones.viewAny'])->group(function () {
        Route::get('canales', [ChannelCredentialController::class, 'index'])->name('channel-credentials.index');
        Route::put('canales', [ChannelCredentialController::class, 'update'])->name('channel-credentials.update');
        Route::post('canales/test-telegram', [ChannelCredentialController::class, 'testTelegram'])
            ->name('channel-credentials.test-telegram')
            ->middleware('throttle:10,1');
        Route::post('canales/send-test-message', [ChannelCredentialController::class, 'sendTestMessage'])
            ->name('channel-credentials.send-test-message')
            ->middleware('throttle:10,1');
        Route::post('canales/test-whatsapp', [ChannelCredentialController::class, 'testWhatsApp'])
            ->name('channel-credentials.test-whatsapp')
            ->middleware('throttle:10,1');
        Route::post('canales/send-whatsapp-test-message', [ChannelCredentialController::class, 'sendWhatsAppTestMessage'])
            ->name('channel-credentials.send-whatsapp-test-message')
            ->middleware('throttle:10,1');
        Route::post('canales/automation', [AutomationController::class, 'store'])->name('automation.store');
        Route::post('canales/automation/test', [AutomationController::class, 'runTest'])
            ->name('automation.test')
            ->middleware('throttle:10,1');

        Route::get('canales/n8n-config', [TenantChannelConfigController::class, 'show'])
            ->name('channel-credentials.n8n-config');
        Route::put('canales/n8n-config', [TenantChannelConfigController::class, 'update'])
            ->name('channel-credentials.n8n-config.update');
        Route::post('canales/n8n-config/test', [TenantChannelConfigController::class, 'testConnection'])
            ->name('channel-credentials.n8n-config.test')
            ->middleware('throttle:10,1');

        Route::get('automatizaciones/historial', [AutomationExecutionController::class, 'index'])
            ->name('automation.history');
        Route::get('automatizaciones/historial/{id}', [AutomationExecutionController::class, 'show'])
            ->name('automation.history.show');

        Route::post('telegram/login-callback', [TelegramCallbackController::class, 'handle'])
            ->name('telegram.login-callback');
        Route::post('canales/telegram/generate-link', [TelegramLinkingController::class, 'generateLink'])
            ->name('telegram.generate-link');
        Route::post('canales/telegram/unlink', [TelegramLinkingController::class, 'unlinkTelegram'])
            ->name('telegram.unlink');
    });

    Route::middleware(['permission:sistema.integraciones.viewAny'])->group(function () {
        Route::get('integraciones-api', [ApiIntegrationController::class, 'index'])
            ->name('integraciones-api.index');
        Route::post('integraciones-api/save', [ApiIntegrationController::class, 'save'])
            ->name('integraciones-api.save');
        Route::post('integraciones-api/test/{provider}', [ApiIntegrationController::class, 'test'])
            ->name('integraciones-api.test')
            ->middleware('throttle:10,1');
        Route::get('api/v1/tenant-credentials/autocomplete', [ApiIntegrationController::class, 'autocomplete'])
            ->name('tenant-credentials.autocomplete');
    });

    Route::middleware(['permission:comercial.cotizaciones.viewAny'])->group(function () {
        Route::get('cotizaciones/export', [CotizacionController::class, 'exportCsv'])->name('cotizaciones.export');
        Route::get('cotizaciones/export-excel', [CotizacionController::class, 'exportExcel'])->name('cotizaciones.export_excel');
        Route::get('cotizaciones/{cotizacion}/pdf', [CotizacionController::class, 'downloadPdf'])->name('cotizaciones.pdf');
        Route::get('cotizaciones/{cotizacion}/preview', [CotizacionController::class, 'previewPdf'])->name('cotizaciones.preview');
        Route::resource('cotizaciones', CotizacionController::class)->except(['create', 'show', 'edit'])->parameters(['cotizaciones' => 'cotizacion']);
    });

    Route::middleware('throttle')->group(function () {
        Route::post('cotizaciones/import', [CotizacionController::class, 'importCsv'])->name('cotizaciones.import')->middleware('permission:comercial.cotizaciones.viewAny');
        Route::post('cotizaciones/import-excel', [CotizacionController::class, 'importExcel'])->name('cotizaciones.import_excel')->middleware('permission:comercial.cotizaciones.viewAny');
        Route::post('ventas/import', [VentaController::class, 'importCsv'])->name('ventas.import')->middleware('permission:ventas.ventas.viewAny');
        Route::post('productos/import-excel', [ProductoController::class, 'importExcel'])->name('productos.import_excel')->middleware('permission:comercial.productos.viewAny');
    });

    Route::middleware(['permission:ventas.ventas.viewAny'])->group(function () {
        Route::get('ventas/export', [VentaController::class, 'exportCsv'])->name('ventas.export');
        Route::get('ventas/export-excel', [VentaController::class, 'exportExcel'])->name('ventas.export_excel');
        Route::get('ventas/{venta}/download', [VentaController::class, 'downloadPdf'])->name('ventas.download');
        Route::get('ventas/{venta}/download-informal', [VentaController::class, 'downloadPdfInformal'])->name('ventas.download-informal');
    });

    Route::middleware(['permission:comercial.productos.viewAny'])->group(function () {
        Route::get('productos/export', [ProductoController::class, 'exportCsv'])->name('productos.export');
        Route::get('productos/export-excel', [ProductoController::class, 'exportExcel'])->name('productos.export_excel');
    });

    require __DIR__.'/modules/crm.php';
    require __DIR__.'/modules/ventas.php';
    require __DIR__.'/modules/inventario.php';
    require __DIR__.'/modules/mrp.php';
    require __DIR__.'/modules/finanzas.php';
    require __DIR__.'/modules/rrhh.php';
    require __DIR__.'/modules/proyectos.php';
    require __DIR__.'/modules/uptime.php';
    require __DIR__.'/modules/flota.php';
    // Admin system health
    Route::middleware(['permission:admin.web-settings.edit'])->group(function () {
        Route::get('/admin/system/health', [SystemHealthController::class, 'dashboard'])
            ->name('admin.system.health');
    });

    require __DIR__.'/modules/admin.php';
    require __DIR__.'/modules/lms.php';
    require __DIR__.'/modules/raffles.php';

    Route::get('onboarding', [OnboardingController::class, 'index'])->name('onboarding');
    Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::get('mensajes', [MensajeController::class, 'index'])->name('mensajes.index')->middleware('permission:general.comunidad.edit');
    Route::get('mensajes/usuarios', [MensajeController::class, 'usuarios'])->name('mensajes.usuarios')->middleware('permission:general.comunidad.edit');
    Route::get('mensajes/{usuarioId}', [MensajeController::class, 'conversacion'])->name('mensajes.conversacion')->middleware('permission:general.comunidad.edit');
    Route::post('mensajes', [MensajeController::class, 'enviar'])->name('mensajes.enviar')->middleware('permission:general.comunidad.edit', 'throttle:messages');

    Route::get('planes', PlanController::class)->name('planes.index');

    Route::get('tareas', [TareaController::class, 'index'])->name('tareas.index')->middleware('permission:general.tareas.create|general.tareas.edit|general.tareas.delete');
    Route::post('tareas', [TareaController::class, 'store'])->name('tareas.store')->middleware('permission:general.tareas.create');
    Route::put('tareas/{tarea}', [TareaController::class, 'update'])->name('tareas.update')->middleware('permission:general.tareas.edit', 'ownership:tarea');
    Route::delete('tareas/{tarea}', [TareaController::class, 'destroy'])->name('tareas.destroy')->middleware('permission:general.tareas.delete', 'ownership:tarea');

    Route::middleware(['permission:comercial.oportunidades.viewAny'])->group(function () {
        Route::get('pedidos-recibidos', [PedidoRecibidoController::class, 'index'])->name('pedidos-recibidos.index');
        Route::get('pedidos-recibidos/export', [PedidoRecibidoController::class, 'export'])->name('pedidos-recibidos.export');
        Route::get('pedidos-recibidos/{pedido}', [PedidoRecibidoController::class, 'show'])->name('pedidos-recibidos.show');
        Route::put('/pedidos-recibidos/{pedido}/estado', [PedidoRecibidoController::class, 'actualizarEstado'])->name('pedidos-recibidos.estado');
        Route::post('/pedidos-recibidos/{pedido}/venta', [PedidoRecibidoController::class, 'generarVenta'])->name('pedidos-recibidos.generar-venta');

        // Chat de Pedidos (Marketplace) - Vista
        Route::get('/conversaciones-pedidos/{conversacion}/chat', [ConversacionPedidoController::class, 'show'])->name('conversaciones-pedidos.show');

        // Chat de Pedidos - API
        Route::get('/conversaciones-pedidos/{conversacion}/mensajes', [ConversacionPedidoController::class, 'getMensajes'])->name('conversaciones-pedidos.mensajes');
        Route::post('/conversaciones-pedidos/{conversacion}/mensajes', [ConversacionPedidoController::class, 'enviarMensaje'])->name('conversaciones-pedidos.enviar')->middleware('throttle:messages');
    });

    Route::prefix('afiliados')->name('afiliados.')->group(function () {
        Route::get('/recomendar', [AffiliateController::class, 'recommend'])->name('recomendar');
        Route::get('/red', [AffiliateController::class, 'network'])->name('red');
    });

    // Marketplace Chat (requiere al menos poder ver oportunidades)
    Route::middleware(['permission:comercial.oportunidades.viewAny'])->group(function () {
        Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
        Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
        Route::post('/chat/{conversation}/messages', [ChatController::class, 'sendMessage'])->name('chat.send')->middleware('throttle:messages');
    });

    // Communication API unificada
    Route::prefix('communication')->name('communication.')->middleware(['permission:comercial.oportunidades.viewAny'])->group(function () {
        Route::get('/inbox', [CommunicationController::class, 'inbox'])->name('inbox');
        Route::get('/{type}/{id}/messages', [CommunicationController::class, 'messages'])->name('messages');
        Route::post('/{type}/{id}/messages', [CommunicationController::class, 'send'])->name('send')->middleware('throttle:messages');
        Route::post('/{type}/{id}/read', [CommunicationController::class, 'markRead'])->name('read');
    });

    // Citas y Reservas
    Route::middleware(['permission:citas.citas.viewAny'])->group(function () {
        Route::get('/appointments/export', [AppointmentController::class, 'exportCsv'])->name('appointments.export');
        Route::get('/appointments/exportar', [AppointmentController::class, 'exportar'])->name('appointments.exportar');
        Route::post('/appointments/importar', [AppointmentController::class, 'importar'])->name('appointments.importar');
        Route::get('/appointments/dashboard', [AppointmentController::class, 'dashboard'])->name('appointments.dashboard');
        Route::get('/appointments/calendar', [AppointmentController::class, 'calendar'])->name('appointments.calendar');
        Route::post('/appointments/calendar/google-config', [AppointmentController::class, 'updateGoogleConfig'])->name('appointments.calendar.google-config');
        Route::get('/appointments/calendar/google-auth', [AppointmentController::class, 'redirectToGoogle'])->name('appointments.calendar.google-auth');
        Route::get('/appointments/calendar/google-callback', [AppointmentController::class, 'handleGoogleCallback'])->name('appointments.calendar.google-callback');

        Route::post('/appointments/calendar/sync', [AppointmentController::class, 'syncGoogleEvents'])->name('appointments.calendar.sync');
        Route::resource('appointments', AppointmentController::class)->except(['edit'])->middleware('ownership:appointment');
    });

    Route::middleware(['permission:citas.servicios.viewAny'])->group(function () {
        Route::resource('services', ServiceController::class)->except(['create', 'show', 'edit']);
    });

    // Búsqueda Global
    Route::get('/api/global-search', [GlobalSearchController::class, 'search'])->name('api.global-search')->middleware('throttle:search');
    Route::get('/api/sii/consultar/{rut}', [SiiController::class, 'validarRut'])->name('api.sii.consultar');
    Route::post('/api/sii/validar-rut', [SiiController::class, 'validarRut'])->name('api.sii.validar-rut');

    // Pagos Webpay Plus CONFIG
    Route::middleware(['permission:admin.webpay-config.viewAny|admin.configuracion.viewAny'])->group(function () {
        Route::get('/webpay/config', [WebpayConfigController::class, 'index'])->name('webpay.config');
        Route::post('/webpay/config', [WebpayConfigController::class, 'update'])->name('webpay.config.update')->middleware('permission:admin.webpay-config.edit|admin.configuracion.edit');
    });
    Route::get('/webpay/movimientos', [WebpayTransactionController::class, 'index'])->name('webpay.movimientos')->middleware('permission:admin.transactions.viewAny|finanzas.tesoreria.viewAny');

    // Pagos PayPal CONFIG
    Route::middleware(['permission:admin.paypal-config.viewAny|admin.configuracion.viewAny'])->group(function () {
        Route::get('/paypal/config', [PayPalConfigController::class, 'index'])->name('paypal.config');
        Route::post('/paypal/config', [PayPalConfigController::class, 'update'])->name('paypal.config.update')->middleware('permission:admin.paypal-config.edit|admin.configuracion.edit');
        Route::post('/paypal/test', [PayPalConfigController::class, 'testConnection'])->name('paypal.test')->middleware('permission:admin.paypal-config.edit|admin.configuracion.edit');
    });

    Route::get('/paypal/pay/{pedidoId}', [PayPalController::class, 'pay'])->name('paypal.pay');
    Route::get('/paypal/success/{pedidoId}', [PayPalController::class, 'success'])->name('paypal.success');
    Route::get('/paypal/cancel/{pedidoId}', [PayPalController::class, 'cancel'])->name('paypal.cancel');

    // Pagos MercadoPago CONFIG
    Route::middleware(['permission:admin.mercadopago-config.viewAny|admin.configuracion.viewAny'])->group(function () {
        Route::get('/mercadopago/config', [MercadoPagoConfigController::class, 'index'])->name('mercadopago.config');
        Route::post('/mercadopago/config', [MercadoPagoConfigController::class, 'update'])->name('mercadopago.config.update')->middleware('permission:admin.mercadopago-config.edit|admin.configuracion.edit');
        Route::post('/mercadopago/test', [MercadoPagoConfigController::class, 'testConnection'])->name('mercadopago.test')->middleware('permission:admin.mercadopago-config.edit|admin.configuracion.edit');
    });

    // Pago Plataforma (usar config del Master como fallback)
    Route::middleware(['permission:admin.finanzas.viewAny|admin.configuracion.viewAny'])->group(function () {
        Route::get('/pagos/plataforma', [PlatformPaymentConfigController::class, 'index'])->name('pagos.plataforma');
        Route::post('/pagos/plataforma', [PlatformPaymentConfigController::class, 'update'])->name('pagos.plataforma.update');
    });

    // Pagos MercadoPago CHECKOUT
    Route::middleware(['throttle:10,1'])->group(function () {
        Route::get('/mercadopago/pay/{pedidoId}', [MercadoPagoController::class, 'pay'])->name('mercadopago.pay');
        Route::get('/mercadopago/success/{pedidoId}', [MercadoPagoController::class, 'success'])->name('mercadopago.success');
        Route::get('/mercadopago/failure/{pedidoId}', [MercadoPagoController::class, 'failure'])->name('mercadopago.failure');
        Route::get('/mercadopago/pending/{pedidoId}', [MercadoPagoController::class, 'pending'])->name('mercadopago.pending');
    });

    // Transbank Checkout & Callbacks
    Route::middleware(['throttle:10,1'])->group(function () {
        Route::post('/webpay/pay', [WebpayController::class, 'pay'])->name('webpay.pay');
    });
    Route::post('/webpay/return', [WebpayController::class, 'callback'])->name('webpay.callback');
    Route::get('/webpay/error', [WebpayConfigController::class, 'error'])->name('webpay.error');

    // Pagos PayPal CHECKOUT
    Route::middleware(['throttle:10,1'])->group(function () {
        Route::get('/paypal/pay/{pedidoId}', [PayPalController::class, 'pay'])->name('paypal.pay');
        Route::get('/paypal/success/{pedidoId}', [PayPalController::class, 'success'])->name('paypal.success');
        Route::get('/paypal/cancel/{pedidoId}', [PayPalController::class, 'cancel'])->name('paypal.cancel');
    });

    // Rutas exclusivas para Clientes (Fase 2, 3, 4, 7)
    Route::prefix('cliente')->name('cliente.')->middleware('role:Cliente')->group(function () {
        Route::get('/', [ClientDashboardController::class, 'index'])->name('dashboard');
        Route::post('/pedidos', [ClientDashboardController::class, 'storeOrder'])->name('pedidos.store');
        Route::post('/pedidos/{pedido}/cancelar', [ClientDashboardController::class, 'cancelOrder'])->name('pedidos.cancelar');
        Route::post('/citas', [ClientDashboardController::class, 'storeAppointment'])->name('citas.store');
        Route::post('/tickets', [ClientDashboardController::class, 'storeTicket'])->name('tickets.store');
    });

    // Rutas exclusivas para Proveedores
    Route::prefix('proveedor')->name('proveedor.')->middleware('role:Proveedor')->group(function () {
        Route::get('/', [ProveedorDashboardController::class, 'index'])->name('dashboard');
        Route::post('/perfil', [ProveedorDashboardController::class, 'updateProfile'])->name('perfil.update');
        Route::get('/compras/{compra}/pdf', [ProveedorDashboardController::class, 'downloadCompraPdf'])->name('compras.pdf');
        Route::post('/documentos', [ProveedorDashboardController::class, 'uploadDocument'])->name('documentos.store');
        Route::delete('/documentos/{documento}', [ProveedorDashboardController::class, 'deleteDocument'])->name('documentos.destroy');
    });
});

Route::group(['prefix' => 'auth/{provider}'], function () {
    Route::get('/redirect', [SocialiteController::class, 'redirect'])->name('socialite.redirect');
    Route::get('/callback', [SocialiteController::class, 'callback'])->name('socialite.callback');
});

Route::get('/auth/telegram/email', [SocialiteController::class, 'showTelegramEmailForm'])->name('socialite.telegram.email-form')->middleware('guest');
Route::post('/auth/telegram/email', [SocialiteController::class, 'completeTelegramEmail'])->name('socialite.telegram.email-store')->middleware('guest');

Route::get('/tienda', [MarketplaceController::class, 'index'])->name('marketplace.index');
Route::get('/tienda/{slug}', [MarketplaceController::class, 'show'])->name('marketplace.show');
Route::post('/tienda/{slug}/react', [MarketplaceController::class, 'react'])->name('marketplace.react')->middleware('auth');
Route::post('/tienda/{slug}/opinion', [MarketplaceController::class, 'storeReview'])->name('marketplace.review')->middleware('auth');
Route::get('/tienda/{slug}/categoria/{categoria}', [MarketplaceController::class, 'category'])->name('marketplace.category');
Route::post('/tienda/{slug}/checkout', [PedidoController::class, 'crear'])->name('tienda.checkout')->middleware('auth');
Route::get('/tienda/{slug}/confirmacion/{pedidoId}', [PedidoController::class, 'confirmacion'])->name('tienda.confirmacion')->middleware('auth');
Route::get('/tienda/{slug}/chat', [ChatController::class, 'start'])->name('chat.start')->middleware('auth');
Route::get('/mis-pedidos', [PedidoController::class, 'misPedidos'])->name('pedidos.mios')->middleware('auth');
Route::get('/pedidos/{pedido}', [PedidoController::class, 'verPedido'])->name('pedidos.ver')->middleware('auth');
Route::get('/pedidos/{pedido}/estado', [PedidoController::class, 'estado'])->name('pedidos.estado')->middleware('auth');
Route::get('/booking/{slug}', [BookingController::class, 'show'])->name('booking.show');
Route::post('/booking/{slug}', [BookingController::class, 'store'])->name('booking.store');
Route::get('/booking/{slug}/webpay/{pedido}', [BookingController::class, 'webpayPay'])->name('booking.webpay-pay');

require __DIR__.'/settings.php';

// Webhooks (sin autenticación — verificados por firma)
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('paypal', [PaypalWebhookController::class, 'handle'])->name('paypal');
    Route::post('mercadopago', [MercadoPagoWebhookController::class, 'handle'])->name('mercadopago');
    Route::post('whatsapp', [WhatsAppWebhookController::class, 'handle'])->name('whatsapp');
    Route::post('telegram', [TelegramWebhookController::class, 'handle'])->name('telegram');
});

// Alias del webhook de Telegram sin prefijo /api (compatibilidad con flujos n8n existentes)
Route::post('canales/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('canales.telegram.webhook');

// Página web del enlace de vinculación: nunca deja una pestaña en blanco.
// El token es la credencial, por lo que la ruta es pública y solo redirige con flash.
Route::get('canales/telegram/vincular/{token}', [TelegramLinkingController::class, 'confirmLink'])
    ->name('telegram.vincular');
