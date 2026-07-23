<?php

namespace App\Mail;

use App\Models\MailTemplate;
use App\Services\MailConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class DynamicNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;

    public $messageBody;

    public array $variables;

    public ?int $ownerId = null;

    public function __construct(
        string $slug,
        string $toEmail,
        string $toName = '',
        array $variables = [],
        ?int $ownerId = null
    ) {
        $this->ownerId = $ownerId ?? (Auth::check() ? Auth::user()->getOwnerId() : null);

        $template = MailTemplate::where('slug', $slug)
            ->where('owner_id', $this->ownerId)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            $template = MailTemplate::where('slug', $slug)
                ->whereNull('owner_id')
                ->where('is_active', true)
                ->first();
        }

        if (! $template) {
            $defaults = [
                'bienvenida' => [
                    'subject' => '¡Bienvenido a {{business_name}}!',
                    'content' => '<h2>¡Bienvenido, {{user_name}}!</h2><p>Gracias por unirte a <strong>{{business_name}}</strong>. Estamos felices de tenerte con nosotros.</p><p>Ya puedes acceder a todas las funcionalidades de tu cuenta y comenzar a disfrutar de nuestros servicios.</p><p>Si tienes alguna pregunta, no dudes en contactarnos.</p><p>Saludos cordiales,<br><strong>{{business_name}}</strong></p>',
                ],
                'factura' => [
                    'subject' => 'Tu factura de {{business_name}}',
                    'content' => '<h2>Factura disponible</h2><p>Hola <strong>{{user_name}}</strong>,</p><p>Tu factura reciente ya está disponible en tu panel de control.</p><table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;margin:16px 0"><thead><tr style="background:#667eea;color:white"><th style="padding:10px;text-align:left">Detalle</th><th style="padding:10px;text-align:right">Valor</th></tr></thead><tbody><tr style="background:#f3f4f6"><td style="padding:10px">Empresa</td><td style="padding:10px;text-align:right"><strong>{{business_name}}</strong></td></tr><tr><td style="padding:10px">Fecha</td><td style="padding:10px;text-align:right">{{date}}</td></tr></tbody></table><p>Puedes descargar tu factura desde tu panel de usuario.</p>',
                ],
                'olvido-contrasena' => [
                    'subject' => 'Restablecer contraseña',
                    'content' => '<h2>Restablecer tu contraseña</h2><p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.</p><p>Haz clic en el siguiente botón para crear una nueva contraseña:</p><p style="text-align:center;margin:24px 0"><a href="{{reset_link}}" style="display:inline-block;padding:12px 28px;background:#667eea;color:white;text-decoration:none;border-radius:6px;font-weight:bold">Restablecer contraseña</a></p><p>Si no solicitaste este cambio, puedes ignorar este mensaje.</p><p>Este enlace expirará en 60 minutos.</p>',
                ],
                'cotizacion' => [
                    'subject' => 'Tu cotización de {{business_name}}',
                    'content' => '<h2>Nueva cotización disponible</h2><p>Hola <strong>{{user_name}}</strong>,</p><p>Hemos preparado una cotización especial para ti en <strong>{{business_name}}</strong>.</p><p>Puedes revisar los detalles y aceptarla desde tu panel de control.</p><p>Si tienes dudas o necesitas ajustes, estamos a tu disposición.</p><p>Saludos,<br><strong>{{business_name}}</strong></p>',
                ],
                'pedido-confirmado' => [
                    'subject' => 'Pedido confirmado en {{business_name}}',
                    'content' => '<h2>¡Pedido confirmado!</h2><p>Hola <strong>{{user_name}}</strong>,</p><p>Tu pedido ha sido confirmado y está siendo procesado en <strong>{{business_name}}</strong>.</p><p>Te enviaremos una notificación cuando tu pedido sea despachado.</p><p>Gracias por tu compra.</p>',
                ],
                'cuenta-activada' => [
                    'subject' => 'Cuenta activada en {{business_name}}',
                    'content' => '<h2>¡Cuenta activada!</h2><p>Hola <strong>{{user_name}}</strong>,</p><p>Tu cuenta en <strong>{{business_name}}</strong> ha sido activada exitosamente.</p><p>Ya puedes acceder con tus credenciales y disfrutar de todos los servicios disponibles.</p><p>Si tienes problemas para acceder, contáctanos.</p><p>Bienvenido,<br><strong>{{business_name}}</strong></p>',
                ],
                'nuevo_pedido' => [
                    'subject' => 'Nuevo pedido #{{numero_pedido}} recibido',
                    'content' => '<h2>¡Nuevo pedido recibido!</h2><p>Se ha registrado un nuevo pedido en tu tienda.</p><table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;margin:16px 0"><thead><tr style="background:#667eea;color:white"><th style="padding:10px;text-align:left">Detalle</th><th style="padding:10px;text-align:right">Valor</th></tr></thead><tbody><tr style="background:#f3f4f6"><td style="padding:10px">Número de pedido</td><td style="padding:10px;text-align:right"><strong>#{{numero_pedido}}</strong></td></tr><tr><td style="padding:10px">Cliente</td><td style="padding:10px;text-align:right">{{nombre_cliente}}</td></tr><tr style="background:#f3f4f6"><td style="padding:10px">Total</td><td style="padding:10px;text-align:right"><strong>${{total}}</strong></td></tr></tbody></table><p style="text-align:center;margin:24px 0"><a href="{{link}}" style="display:inline-block;padding:12px 28px;background:#667eea;color:white;text-decoration:none;border-radius:6px;font-weight:bold">Ver pedido</a></p>',
                ],
                'pedido_creado' => [
                    'subject' => 'Pedido #{{numero_pedido}} creado en {{business_name}}',
                    'content' => '<h2>¡Pedido creado exitosamente!</h2><p>Hola <strong>{{nombre_cliente}}</strong>,</p><p>Tu pedido <strong>#{{numero_pedido}}</strong> ha sido creado y se encuentra en estado <strong>{{estado}}</strong>.</p><p>Te mantendremos informado sobre cualquier cambio en el estado de tu pedido.</p><p style="text-align:center;margin:24px 0"><a href="{{link}}" style="display:inline-block;padding:12px 28px;background:#667eea;color:white;text-decoration:none;border-radius:6px;font-weight:bold">Seguir pedido</a></p>',
                ],
                'actualizacion_pedido' => [
                    'subject' => 'Pedido #{{numero_pedido}} actualizado',
                    'content' => '<h2>Actualización de pedido</h2><p>El estado de tu pedido <strong>#{{numero_pedido}}</strong> ha cambiado.</p><table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;margin:16px 0"><thead><tr style="background:#667eea;color:white"><th style="padding:10px;text-align:left">Estado anterior</th><th style="padding:10px;text-align:center">→</th><th style="padding:10px;text-align:right">Estado nuevo</th></tr></thead><tbody><tr style="background:#f3f4f6"><td style="padding:10px;text-align:left">{{estado_anterior}}</td><td style="padding:10px;text-align:center">→</td><td style="padding:10px;text-align:right"><strong>{{estado_nuevo}}</strong></td></tr></tbody></table><p>{{mensaje}}</p><p style="text-align:center;margin:24px 0"><a href="{{link}}" style="display:inline-block;padding:12px 28px;background:#667eea;color:white;text-decoration:none;border-radius:6px;font-weight:bold">Ir al pedido</a></p>',
                ],
                'mensaje_chat' => [
                    'subject' => 'Nuevo mensaje en pedido #{{numero_pedido}}',
                    'content' => '<h2>Nuevo mensaje en tu pedido</h2><p><strong>{{sender_name}}</strong> ha enviado un mensaje relacionado con el pedido <strong>#{{numero_pedido}}</strong>.</p><blockquote style="border-left:4px solid #667eea;padding:12px 16px;margin:16px 0;background:#f3f4f6;border-radius:4px"><p>{{mensaje}}</p></blockquote><p style="text-align:center;margin:24px 0"><a href="{{link}}" style="display:inline-block;padding:12px 28px;background:#667eea;color:white;text-decoration:none;border-radius:6px;font-weight:bold">Ver conversación</a></p>',
                ],
                'nuevo_ticket' => [
                    'subject' => 'Nuevo ticket de soporte: {{titulo}}',
                    'content' => '<h2>Nuevo ticket de soporte</h2><table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;margin:16px 0"><thead><tr style="background:#667eea;color:white"><th style="padding:10px;text-align:left">Campo</th><th style="padding:10px;text-align:right">Detalle</th></tr></thead><tbody><tr style="background:#f3f4f6"><td style="padding:10px">Título</td><td style="padding:10px;text-align:right"><strong>{{titulo}}</strong></td></tr><tr><td style="padding:10px">Prioridad</td><td style="padding:10px;text-align:right">{{prioridad}}</td></tr><tr style="background:#f3f4f6"><td style="padding:10px">Asignado a</td><td style="padding:10px;text-align:right">{{asignado_a}}</td></tr></tbody></table><p style="text-align:center;margin:24px 0"><a href="{{link}}" style="display:inline-block;padding:12px 28px;background:#667eea;color:white;text-decoration:none;border-radius:6px;font-weight:bold">Ver ticket</a></p>',
                ],
                'pago_recibido' => [
                    'subject' => 'Pago recibido por ${{monto}}',
                    'content' => '<h2>¡Pago recibido!</h2><p>Hemos confirmado el pago por un monto de <strong>${{monto}}</strong> asociado a la orden <strong>{{buy_order}}</strong>.</p><table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;margin:16px 0"><thead><tr style="background:#667eea;color:white"><th style="padding:10px;text-align:left">Detalle</th><th style="padding:10px;text-align:right">Valor</th></tr></thead><tbody><tr style="background:#f3f4f6"><td style="padding:10px">Monto</td><td style="padding:10px;text-align:right"><strong>${{monto}}</strong></td></tr><tr><td style="padding:10px">Orden de compra</td><td style="padding:10px;text-align:right">{{buy_order}}</td></tr></tbody></table><p>Gracias por tu compra.</p><p style="text-align:center;margin:24px 0"><a href="{{link}}" style="display:inline-block;padding:12px 28px;background:#667eea;color:white;text-decoration:none;border-radius:6px;font-weight:bold">Ver detalle</a></p>',
                ],
                'welcome_proveedor' => [
                    'subject' => '¡Bienvenido como proveedor de {{business_name}}!',
                    'content' => '<h2>¡Bienvenido, {{name}}!</h2><p>Te damos la bienvenida como proveedor de <strong>{{business_name}}</strong>.</p><p>Tus datos de acceso son:</p><table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;margin:16px 0"><thead><tr style="background:#667eea;color:white"><th style="padding:10px;text-align:left">Campo</th><th style="padding:10px;text-align:right">Valor</th></tr></thead><tbody><tr style="background:#f3f4f6"><td style="padding:10px">Email</td><td style="padding:10px;text-align:right"><strong>{{email}}</strong></td></tr></tbody></table><p>Desde tu panel de proveedor podrás gestionar tus productos, pedidos y facturación.</p><p style="text-align:center;margin:24px 0"><a href="{{link}}" style="display:inline-block;padding:12px 28px;background:#667eea;color:white;text-decoration:none;border-radius:6px;font-weight:bold">Acceder al portal</a></p>',
                ],
                'temp_password' => [
                    'subject' => 'Contraseña temporal para {{name}}',
                    'content' => '<h2>Contraseña temporal</h2><p>Hola <strong>{{name}}</strong>,</p><p>Se ha generado una contraseña temporal para tu cuenta en <strong>{{business_name}}</strong> a través de <strong>{{provider}}</strong>.</p><p>Por seguridad, te recomendamos cambiarla una vez que inicies sesión.</p><p style="text-align:center;margin:24px 0"><a href="{{link}}" style="display:inline-block;padding:12px 28px;background:#667eea;color:white;text-decoration:none;border-radius:6px;font-weight:bold">Iniciar sesión</a></p>',
                ],
            ];

            if (isset($defaults[$slug])) {
                $this->subject = $this->replaceVariables($defaults[$slug]['subject'], $variables);
                $this->messageBody = $this->replaceVariables($defaults[$slug]['content'], $variables);
            } else {
                $this->subject = 'Notificación';
                $this->messageBody = 'Plantilla no encontrada: '.$slug;
            }
        } else {
            $this->subject = $this->replaceVariables($template->subject, $variables);
            $this->messageBody = $this->replaceVariables($template->content, $variables);
        }

        $this->variables = $variables;
        $this->to($toEmail, $toName);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.dynamic',
            with: [
                'content' => $this->messageBody,
                'variables' => $this->variables,
            ],
        );
    }

    public function build($mailer = null)
    {
        $mailer = $mailer ?? $this;

        if ($this->ownerId) {
            $service = app(MailConfigurationService::class);
            $config = $service->getActiveConfig($this->ownerId);

            if ($config) {
                $service->applyConfiguration($config);

                return $mailer
                    ->mailer('tenant_smtp')
                    ->subject($this->subject)
                    ->view('emails.dynamic', [
                        'content' => $this->messageBody,
                        'variables' => $this->variables,
                    ]);
            }
        }

        return $mailer
            ->subject($this->subject)
            ->view('emails.dynamic', [
                'content' => $this->messageBody,
                'variables' => $this->variables,
            ]);
    }

    public function replaceVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $safe = e($value);
            $template = str_replace("{{{$key}}}", $safe, $template);
            $template = str_replace("{{ $key }}", $safe, $template);
        }

        return $template;
    }
}
