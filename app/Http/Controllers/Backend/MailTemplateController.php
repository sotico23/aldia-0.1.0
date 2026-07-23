<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Mail\DynamicNotification;
use App\Models\MailTemplate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class MailTemplateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.mail-templates.create', only: ['create', 'store']),
            new Middleware('permission:admin.mail-templates.edit', only: ['edit', 'update']),
            new Middleware('permission:admin.mail-templates.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $ownerId = $this->getOwnerId();

        $templates = MailTemplate::where('owner_id', $ownerId)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $systemSlugs = MailTemplate::getAvailableSlugs();

        $allTemplates = $templates->map(function ($template) use ($systemSlugs) {
            return [
                'id' => $template->id,
                'slug' => $template->slug,
                'name' => $template->name,
                'subject' => $template->subject,
                'content' => $template->content,
                'is_active' => $template->is_active,
                'is_default' => isset($systemSlugs[$template->slug]),
                'type' => $template->type,
            ];
        })->values();

        return Inertia::render('Backend/MailTemplate/Index', [
            'templates' => $allTemplates,
            'type' => request()->query('type', 'system'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $request->input('type', 'marketing');

        $validated = $request->validate([
            'slug' => 'required|string|max:100|unique:mail_templates,slug,NULL,id,owner_id,'.$this->getOwnerId().',type,'.$type,
            'name' => 'required|string|max:150',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'boolean',
            'type' => 'required|string|in:system,marketing',
        ]);

        $ownerId = $this->getOwnerId();

        $template = MailTemplate::where('slug', $validated['slug'])
            ->where('owner_id', $ownerId)
            ->where('type', $type)
            ->first();

        if ($template) {
            $template->update($validated);
        } else {
            $validated['owner_id'] = $ownerId;
            MailTemplate::create($validated);
        }

        return redirect()->back()->with('success', 'Plantilla guardada correctamente.');
    }

    public function update(Request $request, MailTemplate $mailTemplate): RedirectResponse
    {
        if ($mailTemplate->owner_id !== $this->getOwnerId()) {
            throw new AuthorizationException('No tienes permiso para editar esta plantilla.');
        }

        $validated = $request->validate([
            'slug' => 'required|string|max:100|unique:mail_templates,slug,'.$mailTemplate->id.',id,owner_id,'.$this->getOwnerId(),
            'name' => 'required|string|max:150',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $mailTemplate->update($validated);

        return redirect()->back()->with('success', 'Plantilla actualizada correctamente.');
    }

    public function destroy(MailTemplate $mailTemplate): RedirectResponse
    {
        if ($mailTemplate->owner_id !== $this->getOwnerId()) {
            throw new AuthorizationException('No tienes permiso para eliminar esta plantilla.');
        }

        $mailTemplate->delete();

        return redirect()->back()->with('success', 'Plantilla eliminada correctamente.');
    }

    public function toggle(MailTemplate $mailTemplate): RedirectResponse
    {
        if ($mailTemplate->owner_id !== $this->getOwnerId()) {
            throw new AuthorizationException('No tienes permiso para modificar esta plantilla.');
        }

        $mailTemplate->update([
            'is_active' => ! $mailTemplate->is_active,
        ]);

        return redirect()->back()->with('success', $mailTemplate->is_active ? 'Plantilla activada.' : 'Plantilla desactivada.');
    }

    public function test(Request $request): RedirectResponse
    {
        $request->validate([
            'slug' => 'required|string',
            'email' => 'required|email',
            'subject' => 'nullable|string',
            'content' => 'nullable|string',
        ]);

        $slug = $request->input('slug');
        $email = $request->input('email');
        $customSubject = $request->input('subject');
        $customContent = $request->input('content');

        $baseVars = [
            'user_name' => 'Usuario de Prueba',
            'business_name' => config('app.name'),
            'date' => now()->format('d/m/Y'),
            'reset_link' => url('/password/reset/token-ejemplo'),
            'numero_pedido' => 'PED-001',
            'nombre_cliente' => 'Cliente Ejemplo',
            'total' => '25.990',
            'estado' => 'Pendiente',
            'estado_anterior' => 'Pendiente',
            'estado_nuevo' => 'En proceso',
            'mensaje' => 'Tu pedido está siendo preparado.',
            'sender_name' => 'Juan Pérez',
            'titulo' => 'Problema con mi pedido',
            'prioridad' => 'Alta',
            'asignado_a' => 'Soporte Técnico',
            'monto' => '50.000',
            'buy_order' => 'ORD-2024-001',
            'email' => $email,
            'name' => 'Proveedor Ejemplo',
            'provider' => 'Google',
            'link' => url('/'),
        ];

        $mail = new DynamicNotification($slug, $email, 'Usuario de Prueba', $baseVars);

        // Si se proveen datos personalizados desde el frontend, sobrescribimos los de la plantilla o defaults
        if ($customSubject) {
            $mail->subject = $mail->replaceVariables($customSubject, $mail->variables);
        }
        if ($customContent) {
            $mail->messageBody = $mail->replaceVariables($customContent, $mail->variables);
        }

        Mail::send($mail);

        return back()->with('success', 'Email de prueba enviado a '.$email);
    }

    private function getOwnerId(): int
    {
        return (int) Auth::user()->getOwnerId();
    }
}
