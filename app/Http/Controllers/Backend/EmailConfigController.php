<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmailConfigRequest;
use App\Models\MailConfig;
use App\Services\MailConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EmailConfigController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.email-config.create', only: ['create', 'store']),
            new Middleware('permission:admin.email-config.edit', only: ['edit', 'update']),
            new Middleware('permission:admin.email-config.delete', only: ['destroy']),
        ];
    }

    public function __construct(
        protected MailConfigurationService $mailConfigService
    ) {}

    public function index(): Response
    {
        $ownerId = $this->getOwnerId();

        $configs = $this->mailConfigService->getAllConfigs($ownerId);
        $recentLogs = $this->mailConfigService->getRecentLogs($ownerId, 20);
        $unreadNotifications = $this->mailConfigService->getUnreadNotifications($ownerId);

        return Inertia::render('Backend/Marketing/EmailConfig', [
            'configs' => $configs,
            'logs' => $recentLogs,
            'notifications' => $unreadNotifications,
            'drivers' => MailConfig::getDriverFields(),
        ]);
    }

    public function store(EmailConfigRequest $request): RedirectResponse
    {
        $ownerId = $this->getOwnerId();
        $data = $request->validated();

        if (isset($data['is_default']) && $data['is_default']) {
            MailConfig::where('owner_id', $ownerId)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        if (isset($data['is_active']) && $data['is_active']) {
            MailConfig::where('owner_id', $ownerId)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $data['owner_id'] = $ownerId;
        $config = MailConfig::create($data);

        return redirect()->back()->with('success', 'Configuración creada correctamente.');
    }

    public function update(EmailConfigRequest $request, MailConfig $emailConfig): RedirectResponse
    {
        if ($emailConfig->owner_id !== $this->getOwnerId()) {
            abort(403, 'No tienes permiso para editar esta configuración.');
        }

        $data = $request->validated();

        if (isset($data['is_default']) && $data['is_default']) {
            MailConfig::where('owner_id', $emailConfig->owner_id)
                ->where('is_default', true)
                ->where('id', '!=', $emailConfig->id)
                ->update(['is_default' => false]);
        }

        if (isset($data['is_active']) && $data['is_active']) {
            MailConfig::where('owner_id', $emailConfig->owner_id)
                ->where('is_active', true)
                ->where('id', '!=', $emailConfig->id)
                ->update(['is_active' => false]);
        }

        $emailConfig->update($data);

        return redirect()->back()->with('success', 'Configuración actualizada correctamente.');
    }

    public function destroy(MailConfig $emailConfig): RedirectResponse
    {
        if ($emailConfig->owner_id !== $this->getOwnerId()) {
            abort(403, 'No tienes permiso para eliminar esta configuración.');
        }

        $emailConfig->delete();

        return redirect()->back()->with('success', 'Configuración eliminada correctamente.');
    }

    public function test(Request $request, MailConfig $emailConfig): RedirectResponse
    {
        if ($emailConfig->owner_id !== $this->getOwnerId()) {
            abort(403, 'No tienes permiso para probar esta configuración.');
        }

        $request->validate([
            'test_email' => 'required|email',
        ]);

        $result = $this->mailConfigService->testConnection(
            $emailConfig,
            $request->input('test_email')
        );

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function setDefault(MailConfig $emailConfig): RedirectResponse
    {
        if ($emailConfig->owner_id !== $this->getOwnerId()) {
            abort(403, 'No tienes permiso para modificar esta configuración.');
        }

        $this->mailConfigService->setAsDefault($emailConfig);

        return redirect()->back()->with('success', 'Configuración establecida como predeterminada.');
    }

    public function setActive(MailConfig $emailConfig): RedirectResponse
    {
        if ($emailConfig->owner_id !== $this->getOwnerId()) {
            abort(403, 'No tienes permiso para modificar esta configuración.');
        }

        $this->mailConfigService->setAsActive($emailConfig);

        return redirect()->back()->with('success', 'Configuración activada.');
    }

    public function logs(MailConfig $emailConfig): Response
    {
        if ($emailConfig->owner_id !== $this->getOwnerId()) {
            abort(403, 'No tienes permiso para ver esta configuración.');
        }

        $logs = $emailConfig->logs()->orderByDesc('created_at')->paginate(20);

        return Inertia::render('Backend/Marketing/EmailConfigLogs', [
            'config' => $emailConfig,
            'logs' => $logs,
        ]);
    }

    public function markAsRead(Request $request): RedirectResponse
    {
        $request->validate([
            'notification_id' => 'required|integer',
        ]);

        $this->mailConfigService->markNotificationAsRead($request->input('notification_id'));

        return redirect()->back();
    }

    private function getOwnerId(): int
    {
        return (int) Auth::user()->getOwnerId();
    }
}
