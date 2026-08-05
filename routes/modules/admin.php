<?php

use App\Http\Controllers\Backend\ConfiguracionController;
use App\Http\Controllers\Backend\EmailConfigController;
use App\Http\Controllers\Backend\FinancialAutomationController;
use App\Http\Controllers\Backend\FinancialSettingsController;
use App\Http\Controllers\Backend\GatewaySettingsController;
use App\Http\Controllers\Backend\MailTemplateController;
use App\Http\Controllers\Backend\MarketplaceSettingsController;
use App\Http\Controllers\Backend\PaisController;
use App\Http\Controllers\Backend\ReporteController;
use App\Http\Controllers\Backend\UsuarioRolController;
use App\Http\Controllers\Backend\WebhookSettingsController;
use App\Http\Controllers\Backend\WebSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:admin.reportes.viewAny'])->group(function () {
    Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
});

Route::middleware(['permission:admin.configuracion.viewAny'])->group(function () {
    Route::resource('configuracion', ConfiguracionController::class)->except(['create', 'show', 'edit'])->middleware('ownership:configuracion');
});

Route::middleware(['permission:admin.countries.viewAny'])->group(function () {
    Route::resource('paises', PaisController::class)->except(['create', 'show', 'edit']);
    Route::patch('paises/{country}/toggle', [PaisController::class, 'toggle'])->name('paises.toggle');
});

Route::middleware(['permission:admin.web-settings.viewAny'])->group(function () {
    Route::post('configuracion-web/test-social', [WebSettingController::class, 'testSocialConnection'])->name('configuracion-web.test-social');
    Route::post('configuracion-web/test-telegram', [WebSettingController::class, 'testTelegramConnection'])->name('configuracion-web.test-telegram');
    Route::post('configuracion-web/test-whatsapp', [WebSettingController::class, 'testWhatsAppConnection'])->name('configuracion-web.test-whatsapp');
    Route::post('configuracion-web/set-telegram-webhook', [WebSettingController::class, 'setTelegramWebhook'])->name('configuracion-web.set-telegram-webhook');
    Route::post('configuracion-web/set-whatsapp-webhook', [WebSettingController::class, 'setWhatsAppWebhook'])->name('configuracion-web.set-whatsapp-webhook');

    Route::middleware('throttle:60,1')->group(function () {
        Route::get('configuracion-web/financial-settings', [FinancialSettingsController::class, 'show'])->name('configuracion-web.financial-settings');
        Route::put('configuracion-web/financial-settings', [FinancialSettingsController::class, 'update'])->name('configuracion-web.financial-settings.update');

        Route::get('configuracion-web/gateway-settings', [GatewaySettingsController::class, 'show'])->name('configuracion-web.gateway-settings');
        Route::put('configuracion-web/gateway-settings/webpay', [GatewaySettingsController::class, 'updateWebpay'])->name('configuracion-web.gateway-settings.webpay');
        Route::put('configuracion-web/gateway-settings/paypal', [GatewaySettingsController::class, 'updatePaypal'])->name('configuracion-web.gateway-settings.paypal');
        Route::put('configuracion-web/gateway-settings/mercadopago', [GatewaySettingsController::class, 'updateMercadopago'])->name('configuracion-web.gateway-settings.mercadopago');

        Route::get('configuracion-web/marketplace-settings', [MarketplaceSettingsController::class, 'show'])->name('configuracion-web.marketplace-settings');
        Route::put('configuracion-web/marketplace-settings', [MarketplaceSettingsController::class, 'update'])->name('configuracion-web.marketplace-settings.update');

        Route::get('configuracion-web/webhook-settings', [WebhookSettingsController::class, 'show'])->name('configuracion-web.webhook-settings');

        Route::get('configuracion-web/financial-automations', [FinancialAutomationController::class, 'show'])->name('configuracion-web.financial-automations');
        Route::put('configuracion-web/financial-automations', [FinancialAutomationController::class, 'update'])->name('configuracion-web.financial-automations.update');
    });

    Route::resource('configuracion-web', WebSettingController::class)->only(['index', 'update']);
});

Route::middleware(['permission:admin.mail-templates.viewAny'])->group(function () {
    Route::resource('mail-templates', MailTemplateController::class)->except(['create', 'show', 'edit'])->middleware('ownership:mailTemplate');
    Route::post('mail-templates/test', [MailTemplateController::class, 'test'])->name('mail-templates.test');
    Route::patch('mail-templates/{mailTemplate}/toggle', [MailTemplateController::class, 'toggle'])->name('mail-templates.toggle')->middleware('ownership:mailTemplate');
});

Route::middleware(['permission:admin.email-config.viewAny'])->group(function () {
    Route::prefix('marketing')->group(function () {
        Route::resource('email-config', EmailConfigController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('ownership:emailConfig');
        Route::post('email-config/{emailConfig}/test', [EmailConfigController::class, 'test'])->name('email-config.test')->middleware('ownership:emailConfig');
        Route::post('email-config/{emailConfig}/set-default', [EmailConfigController::class, 'setDefault'])->name('email-config.set-default')->middleware('ownership:emailConfig');
        Route::post('email-config/{emailConfig}/set-active', [EmailConfigController::class, 'setActive'])->name('email-config.set-active')->middleware('ownership:emailConfig');
        Route::get('email-config/{emailConfig}/logs', [EmailConfigController::class, 'logs'])->name('email-config.logs')->middleware('ownership:emailConfig');
    });
});

Route::middleware(['permission:admin.usuarios.viewAny'])->group(function () {
    Route::post('usuarios-roles/user/create', [UsuarioRolController::class, 'storeUser'])->name('usuarios-roles.user.store')->middleware('role:Master');
    Route::post('usuarios-roles/role', [UsuarioRolController::class, 'storeRole'])->name('usuarios-roles.role.store');
    Route::put('usuarios-roles/role/{role}', [UsuarioRolController::class, 'updateRole'])->name('usuarios-roles.role.update');
    Route::delete('usuarios-roles/role/{role}', [UsuarioRolController::class, 'destroyRole'])->name('usuarios-roles.role.destroy');

    Route::post('usuarios-roles/permission', [UsuarioRolController::class, 'storePermission'])->name('usuarios-roles.permission.store');
    Route::put('usuarios-roles/permission/{permission}', [UsuarioRolController::class, 'updatePermission'])->name('usuarios-roles.permission.update');
    Route::delete('usuarios-roles/permission/{permission}', [UsuarioRolController::class, 'destroyPermission'])->name('usuarios-roles.permission.destroy');

    Route::patch('usuarios-roles/user/{user}', [UsuarioRolController::class, 'updateUser'])->name('usuarios-roles.user.update')->middleware('ownership:user');
    Route::post('usuarios-roles/user/{user}/reset-password', [UsuarioRolController::class, 'resetUserPassword'])->name('usuarios-roles.user.reset-password')->middleware('ownership:user');
    Route::post('usuarios-roles/user/{user}/toggle-ban', [UsuarioRolController::class, 'toggleBan'])->name('usuarios-roles.user.toggle-ban')->middleware('ownership:user');

    Route::patch('usuarios-roles/public-profile/{publicProfile}/toggle-official', [UsuarioRolController::class, 'toggleOfficial'])->name('usuarios-roles.toggle-official')->middleware('ownership:publicProfile');
    Route::patch('usuarios-roles/public-profile/{publicProfile}/toggle-status', [UsuarioRolController::class, 'toggleStatus'])->name('usuarios-roles.toggle-status')->middleware('ownership:publicProfile');
    Route::resource('usuarios-roles', UsuarioRolController::class)->except(['create', 'show', 'edit', 'update'])->middleware('ownership:usuarioRol');
});
