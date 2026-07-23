import { useForm, router, usePage } from '@inertiajs/react';
import {
    Send,
    Plus,
    Pencil,
    Eye,
    X,
    Check,
    Mail
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePermissions } from '@/hooks/use-permissions';

import { sanitize } from '@/lib/sanitize';


interface Template {
    id?: number;
    slug: string;
    name: string;
    subject: string;
    content: string;
    type: 'system' | 'marketing';
    is_active: boolean;
    is_default?: boolean;
}

interface Props {
    templates?: Template[];
    type?: 'system' | 'marketing';
}

const variableHelp = `Variables disponibles:
{{user_name}} - Nombre del usuario
{{business_name}} - Nombre del negocio
{{date}} - Fecha actual
{{email}} - Correo del usuario
{{reset_link}} - Enlace de recuperación
{{invoice_number}} - Número de factura
{{invoice_total}} - Total de factura`;

const defaultTemplates: Record<
    string,
    { name: string; subject: string; content: string }
> = {
    bienvenida: {
        name: 'Email de Bienvenida',
        subject: '¡Bienvenido a {{business_name}}! 🎉',
        content: `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: white; margin: 0; font-size: 28px;">¡Bienvenido/a {{user_name}}!</h1>
            </div>
            <div style="background: #ffffff; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Hola <strong>{{user_name}}</strong>,
                </p>
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    ¡Gracias por unirte a <strong>{{business_name}}</strong>! Estamos emocionados de tenerte con nosotros.
                </p>
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Ahora puedes acceder a todos nuestros servicios y comenzar a disfrutar de todo lo que tenemos para ti.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="#" style="background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Comenzar Ahora</a>
                </div>
                <p style="font-size: 14px; color: #666; text-align: center;">
                    Fecha de registro: {{date}}
                </p>
            </div>
        </div>`,
    },
    factura: {
        name: 'Email de Factura',
        subject: 'Tu factura #{{invoice_number}} de {{business_name}}',
        content: `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #2c3e50; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: white; margin: 0; font-size: 24px;">📄 Factura #{{invoice_number}}</h1>
            </div>
            <div style="background: #ffffff; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Hola <strong>{{user_name}}</strong>,
                </p>
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Se ha generado tu factura con los siguientes detalles:
                </p>
                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Número:</strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">{{invoice_number}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Fecha:</strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">{{date}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-size: 18px;"><strong>Total:</strong></td>
                        <td style="padding: 10px; font-size: 18px; color: #2c3e50;"><strong>{{invoice_total}}</strong></td>
                    </tr>
                </table>
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Gracias por tu preferencia. Si tienes alguna consulta, no dudes en contactarnos.
                </p>
            </div>
        </div>`,
    },
    'olvido-contrasena': {
        name: 'Restablecer Contraseña',
        subject: 'Restablece tu contraseña en {{business_name}}',
        content: `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #e74c3c; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: white; margin: 0; font-size: 24px;">🔐 ¿Olvidaste tu contraseña?</h1>
            </div>
            <div style="background: #ffffff; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Hola <strong>{{user_name}}</strong>,
                </p>
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Recibimos una solicitud para restablecer tu contraseña. Haz clic en el siguiente botón para crear una nueva contraseña:
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{reset_link}}" style="background: #e74c3c; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Restablecer Contraseña</a>
                </div>
                <p style="font-size: 14px; color: #666; line-height: 1.6;">
                    Si no solicitaste este cambio, puedes ignorar este correo. Este enlace expira en <strong>60 minutos</strong>.
                </p>
            </div>
        </div>`,
    },
    cotizacion: {
        name: 'Envío de Cotización',
        subject: 'Tu cotización de {{business_name}}',
        content: `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #27ae60; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: white; margin: 0; font-size: 24px;">📋 Tu Cotización</h1>
            </div>
            <div style="background: #ffffff; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Hola <strong>{{user_name}}</strong>,
                </p>
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Hemos preparado una cotización especialmente para ti. Encuentra los detalles adjuntos en este correo.
                </p>
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Esta cotización tiene validez hasta el <strong>{{date}}</strong>.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="#" style="background: #27ae60; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Aceptar Cotización</a>
                </div>
                <p style="font-size: 14px; color: #666; line-height: 1.6;">
                    Si tienes alguna pregunta o necesitas más información, estamos aquí para ayudarte.
                </p>
            </div>
        </div>`,
    },
    'pedido-confirmado': {
        name: 'Pedido Confirmado',
        subject: '¡Pedido confirmado! - {{business_name}}',
        content: `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #f39c12; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: white; margin: 0; font-size: 24px;">✅ ¡Pedido Confirmado!</h1>
            </div>
            <div style="background: #ffffff; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Hola <strong>{{user_name}}</strong>,
                </p>
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    ¡Great news! Tu pedido ha sido confirmado y está en proceso de preparación.
                </p>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <p style="font-size: 14px; color: #666; margin: 0;"><strong>Estado:</strong> Confirmado</p>
                    <p style="font-size: 14px; color: #666; margin: 5px 0 0;"><strong>Fecha:</strong> {{date}}</p>
                </div>
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Te notificaremos por correo cuando tu pedido esté listo para envío.
                </p>
                <p style="font-size: 14px; color: #666; line-height: 1.6;">
                    Gracias por confiar en <strong>{{business_name}}</strong>.
                </p>
            </div>
        </div>`,
    },
    'cuenta-activada': {
        name: 'Cuenta Activada',
        subject: '¡Tu cuenta ha sido activada! - {{business_name}}',
        content: `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #3498db; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: white; margin: 0; font-size: 24px;">🎉 ¡Cuenta Activada!</h1>
            </div>
            <div style="background: #ffffff; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Hola <strong>{{user_name}}</strong>,
                </p>
                <p style="font-size: 16px; color: #333; line-height: 1.6;">
                    Tu cuenta ha sido activada exitosamente. Ahora puedes acceder a todos los servicios de <strong>{{business_name}}</strong>.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="#" style="background: #3498db; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Ir a mi Cuenta</a>
                </div>
                <p style="font-size: 14px; color: #666; line-height: 1.6;">
                    Si tienes alguna pregunta, nuestro equipo está disponible para ayudarte.
                </p>
            </div>
        </div>`,
    },
};

export default function EmailMarketingPage(props: Props) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('admin.web-settings.viewAny');
    const pageProps = usePage().props as {
        flash?: { success?: string };
        templates?: Template[];
        type?: 'system' | 'marketing';
    };

    const currentType = props.type ?? pageProps.type ?? 'marketing';
    const templates = props.templates ?? pageProps.templates ?? [];

    const [activeSlug, setActiveSlug] = useState(templates[0]?.slug || '');
    const [showModal, setShowModal] = useState(false);
    const [editingTemplate, setEditingTemplate] = useState<Template | null>(
        null,
    );
    const [showPreview, setShowPreview] = useState(false);
    const [previewContent, setPreviewContent] = useState('');

    const activeTemplate =
        templates.find((t) => t.slug === activeSlug) || templates[0];

    const getTemplateContent = (template: Template | undefined) => {
        if (!template) return '';
        if (template.content) return template.content;
        return defaultTemplates[template.slug]?.content || '';
    };

    const getTemplateSubject = (template: Template | undefined) => {
        if (!template) return 'Sin plantilla';
        if (template.subject) return template.subject;
        return defaultTemplates[template.slug]?.subject || 'Sin asunto';
    };

    const getTemplateName = (template: Template | undefined) => {
        if (!template) return 'Nueva Plantilla';
        if (template.name) return template.name;
        return defaultTemplates[template.slug]?.name || template.slug;
    };

    const { data, setData, post, patch, processing, reset } = useForm({
        id: undefined as number | undefined,
        slug: '',
        name: '',
        subject: '',
        content: '',
        is_active: true,
        type: currentType,
    });

    const handleTemplateChange = (slug: string) => {
        setActiveSlug(slug);
        setEditingTemplate(null);
        reset();
    };

    const openCreateModal = () => {
        setEditingTemplate(null);
        reset();
        setData('type', currentType);
        setShowModal(true);
    };

    const openEditModal = (template: Template | undefined) => {
        if (!template) return;
        setEditingTemplate(template);
        setData({
            id: template.id,
            slug: template.slug,
            name: template.name,
            subject: template.subject,
            content: template.content,
            is_active: template.is_active,
            type: template.type || currentType,
        });
        setShowModal(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (data.id) {
            patch(`/mail-templates/${data.id}`, {
                onSuccess: () => {
                    toast.success('Plantilla actualizada correctamente');
                    setShowModal(false);
                    reset();
                    setEditingTemplate(null);
                    router.reload({ only: ['templates'] });
                },
                onError: () => {
                    toast.error('Error al actualizar la plantilla');
                },
            });
        } else {
            post('/mail-templates', {
                onSuccess: () => {
                    toast.success('Plantilla creada correctamente');
                    setShowModal(false);
                    reset();
                    router.reload({ only: ['templates'] });
                },
                onError: () => {
                    toast.error('Error al crear la plantilla');
                },
            });
        }
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleDelete = (template: Template) => {
        if (!template.id) return;
        if (!confirm(`¿Eliminar la plantilla "${template.name}"?`)) return;

        router.delete(`/mail-templates/${template.id}`, {
            onSuccess: () => {
                toast.success('Plantilla eliminada');
                router.reload({ only: ['templates'] });
            },
            onError: () => {
                toast.error('Error al eliminar');
            },
        });
    };

    const handleToggle = (template: Template) => {
        if (!template.id) return;
        router.patch(`/mail-templates/${template.id}/toggle`);
    };

    const handleTest = (template: Template | undefined) => {
        if (!template) return;
        const email = prompt(
            'Ingresa el email para enviar la prueba:',
            'test@example.com',
        );
        if (!email) return;

        router.post(
            '/mail-templates/test',
            {
                slug: template.slug,
                email,
                subject: getTemplateSubject(template),
                content: getTemplateContent(template),
            },
            {
                onSuccess: () => toast.success('Email de prueba enviado'),
                onError: () => toast.error('Error al enviar la prueba'),
            },
        );
    };

    const handlePreview = () => {
        let content = data.content || getTemplateContent(activeTemplate);
        content = content
            .replace(/\{\{user_name\}\}/g, 'Juan Pérez')
            .replace(/\{\{business_name\}\}/g, 'Mi Empresa SA')
            .replace(/\{\{date\}\}/g, new Date().toLocaleDateString('es-ES'))
            .replace(/\{\{email\}\}/g, 'cliente@ejemplo.com')
            .replace(/\{\{reset_link\}\}/g, '#')
            .replace(/\{\{invoice_number\}\}/g, 'F001-001')
            .replace(/\{\{invoice_total\}\}/g, '$100.000');

        setPreviewContent(content);
        setShowPreview(true);
    };

    const handleApplyTemplate = (slug: string) => {
        const defaults = defaultTemplates[slug];
        if (defaults) {
            setData({
                ...data,
                subject: defaults.subject,
                content: defaults.content,
            });
            toast.success('Contenido de ejemplo aplicado');
        }
    };

    if (!canAccess) {
        return (
            <div className="flex items-center justify-center py-12">
                <p className="text-muted-foreground">No tienes permiso para acceder a esta página.</p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-xl font-bold tracking-tight md:text-2xl">
                        {currentType === 'system'
                            ? 'Emails de Sistema'
                            : 'Email Marketing'}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {currentType === 'system'
                            ? 'Configura las notificaciones globales automáticas'
                            : 'Personaliza la comunicación comercial con tus clientes'}
                    </p>
                </div>
                <Button
                    type="button"
                    onClick={openCreateModal}
                    className="group relative overflow-hidden bg-primary px-6 transition-all hover:pr-10"
                >
                    <Plus className="mr-2 h-4 w-4" />
                    <span>Nueva Plantilla</span>
                    <Plus className="absolute right-3 h-4 w-4 translate-x-8 opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100" />
                </Button>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[320px_1fr]">
                {/* Sidebar: Plantillas */}
                <Card className="h-fit border-none bg-muted/30 shadow-none backdrop-blur-sm lg:sticky lg:top-4">
                    <CardHeader className="pb-4">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-lg">
                                Plantillas
                            </CardTitle>
                            <div className="rounded-full bg-primary/10 px-2.5 py-0.5 text-[10px] font-bold text-primary">
                                {templates.length}
                            </div>
                        </div>
                        <CardDescription className="text-xs">
                            Selecciona una para editar
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-2 pb-4">
                        <div className="flex gap-2 overflow-x-auto pb-2 sm:grid sm:grid-cols-2 sm:overflow-visible lg:flex lg:flex-col lg:gap-1.5 lg:pb-0">
                            {templates.map((template) => (
                                <button
                                    key={template.slug}
                                    onClick={() =>
                                        handleTemplateChange(template.slug)
                                    }
                                    className={`relative flex min-w-[160px] flex-col rounded-xl p-3 text-left transition-all sm:min-w-0 ${
                                        activeSlug === template.slug
                                            ? 'bg-background shadow-sm ring-1 ring-primary/20'
                                            : 'hover:bg-background/50'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1">
                                            <div
                                                className={`truncate text-xs font-bold ${activeSlug === template.slug ? 'text-primary' : 'text-foreground'}`}
                                            >
                                                {getTemplateName(template)}
                                            </div>
                                            <div className="truncate text-[10px] text-muted-foreground">
                                                {template.slug}
                                            </div>
                                        </div>
                                        <div
                                            className={`mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full ${
                                                template.is_active
                                                    ? 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.5)]'
                                                    : 'bg-muted-foreground/30'
                                            }`}
                                        />
                                    </div>
                                    {activeSlug === template.slug && (
                                        <div className="absolute top-1/2 -left-2 h-8 w-1 -translate-y-1/2 rounded-full bg-primary lg:block" />
                                    )}
                                </button>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Main Content: Detalle */}
                <div className="min-w-0 space-y-6">
                    {!activeTemplate ? (
                        <Card className="flex h-[400px] flex-col items-center justify-center border-none bg-muted/20 text-center shadow-none ring-1 ring-muted">
                            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-primary/10 text-primary">
                                <Mail className="h-8 w-8" />
                            </div>
                            <CardTitle className="mb-2">
                                No hay plantillas
                            </CardTitle>
                            <CardDescription className="max-w-[280px]">
                                Comienza creando tu primera plantilla de correo
                                para este módulo.
                            </CardDescription>
                            <Button
                                onClick={openCreateModal}
                                variant="outline"
                                className="mt-6"
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Crear mi primera plantilla
                            </Button>
                        </Card>
                    ) : (
                        <Card className="overflow-hidden border-none shadow-sm ring-1 ring-muted">
                        <CardHeader className="bg-muted/10 pb-6">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <CardTitle className="text-xl">
                                            {getTemplateName(activeTemplate)}
                                        </CardTitle>
                                        {activeTemplate?.is_default && (
                                            <span className="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                                Sistema
                                            </span>
                                        )}
                                    </div>
                                    <CardDescription className="flex items-center gap-2 text-xs">
                                        <span className="font-mono">
                                            {activeTemplate?.slug}
                                        </span>
                                        <span className="h-1 w-1 rounded-full bg-muted-foreground/30" />
                                        <span
                                            className={
                                                activeTemplate?.is_active
                                                    ? 'text-green-600'
                                                    : 'text-muted-foreground'
                                            }
                                        >
                                            {activeTemplate?.is_active
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </span>
                                    </CardDescription>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    size="sm"
                                                    className="h-9 w-9 p-0"
                                                    onClick={() =>
                                                        handleTest(
                                                            activeTemplate,
                                                        )
                                                    }
                                                >
                                                    <Send className="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                Enviar prueba
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-9 gap-2"
                                        onClick={() =>
                                            openEditModal(activeTemplate)
                                        }
                                    >
                                        <Pencil className="h-4 w-4" />
                                        <span>Editar</span>
                                    </Button>

                                    {activeTemplate?.id && (
                                        <Button
                                            type="button"
                                            variant={
                                                activeTemplate.is_active
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                            size="sm"
                                            className="h-9 gap-2"
                                            onClick={() =>
                                                handleToggle(activeTemplate)
                                            }
                                        >
                                            {activeTemplate.is_active ? (
                                                <X className="h-4 w-4" />
                                            ) : (
                                                <Check className="h-4 w-4" />
                                            )}
                                            <span>
                                                {activeTemplate.is_active
                                                    ? 'Desactivar'
                                                    : 'Activar'}
                                            </span>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6 pt-6">
                            <div className="space-y-2">
                                <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                    Asunto del correo
                                </Label>
                                <div className="rounded-xl border bg-muted/5 p-4 transition-colors hover:bg-muted/10">
                                    <p className="text-sm leading-relaxed font-medium">
                                        {getTemplateSubject(activeTemplate)}
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Cuerpo del mensaje
                                    </Label>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-8 gap-2 text-xs hover:bg-primary/10 hover:text-primary"
                                        onClick={handlePreview}
                                    >
                                        <Eye className="h-3.5 w-3.5" />
                                        Vista previa
                                    </Button>
                                </div>
                                <div className="group relative rounded-xl border bg-muted/5 transition-all focus-within:ring-2 focus-within:ring-primary/20">
                                    <div className="max-h-[500px] overflow-y-auto p-6">
                                        <div
                                            className="prose prose-sm dark:prose-invert max-w-none"
                                            dangerouslySetInnerHTML={{
                                                __html: sanitize(
                                                    getTemplateContent(
                                                        activeTemplate,
                                                    ) ||
                                                        '<p class="text-muted-foreground italic">Sin contenido configurado</p>',
                                                ),
                                            }}
                                        />
                                    </div>
                                    <div className="pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-background/50 to-transparent transition-opacity group-hover:opacity-0" />
                                </div>
                            </div>

                            {/* Variables Ayuda Card */}
                            <Card className="border-none bg-primary/5">
                                <CardContent className="p-4">
                                    <div className="mb-3 flex items-center gap-2">
                                        <div className="flex h-6 w-6 items-center justify-center rounded-lg bg-primary/20 text-primary">
                                            <Mail className="h-3.5 w-3.5" />
                                        </div>
                                        <h4 className="text-xs font-bold tracking-wider text-primary uppercase">
                                            Variables dinámicas
                                        </h4>
                                    </div>
                                    <div className="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-3 md:grid-cols-4">
                                        {variableHelp
                                            .split('\n')
                                            .slice(1)
                                            .map((v: string, i: number) => {
                                                const [tag, desc] =
                                                    v.split(' - ');
                                                return (
                                                    <div
                                                        key={i}
                                                        className="group flex flex-col gap-0.5"
                                                    >
                                                        <code className="w-fit rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary transition-colors group-hover:bg-primary group-hover:text-white">
                                                            {tag}
                                                        </code>
                                                        <span className="text-[10px] text-muted-foreground transition-colors group-hover:text-foreground">
                                                            {desc}
                                                        </span>
                                                    </div>
                                                );
                                            })}
                                    </div>
                                </CardContent>
                            </Card>
                        </CardContent>
                        </Card>
                    )}
                </div>
            </div>

            {/* Modal de Edición/Creación */}
            <Dialog open={showModal} onOpenChange={setShowModal}>
                <DialogContent className="max-h-[95vh] w-[95vw] max-w-4xl overflow-y-auto rounded-3xl p-0 sm:w-full">
                    <DialogHeader className="p-6 pb-0">
                        <DialogTitle className="text-2xl">
                            {editingTemplate?.id
                                ? 'Editar Plantilla'
                                : 'Nueva Plantilla'}
                        </DialogTitle>
                        <DialogDescription>
                            Configura los detalles de la plantilla de correo
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-6 p-6">
                        <div className="grid gap-6 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label className="text-sm font-semibold">
                                    Slug Identificador
                                </Label>
                                <Input
                                    value={data.slug}
                                    onChange={(e) =>
                                        setData('slug', e.target.value)
                                    }
                                    placeholder="ej: bienvenida-usuario"
                                    disabled={!!editingTemplate?.is_default}
                                    className="h-11 rounded-xl border-none bg-muted/20 focus-visible:ring-2 focus-visible:ring-primary/30"
                                />
                                {editingTemplate?.is_default && (
                                    <p className="ml-1 text-[10px] text-muted-foreground">
                                        Las plantillas de sistema no permiten
                                        cambiar el slug.
                                    </p>
                                )}
                                {defaultTemplates[data.slug] && (
                                    <Button
                                        type="button"
                                        variant="link"
                                        size="sm"
                                        className="h-6 p-0 text-xs text-primary"
                                        onClick={() =>
                                            handleApplyTemplate(data.slug)
                                        }
                                    >
                                        Usar contenido de ejemplo
                                    </Button>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label className="text-sm font-semibold">
                                    Nombre Público
                                </Label>
                                <Input
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="ej: Email de Bienvenida"
                                    className="h-11 rounded-xl border-none bg-muted/20 focus-visible:ring-2 focus-visible:ring-primary/30"
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label className="text-sm font-semibold">
                                Asunto del Correo
                            </Label>
                            <Input
                                value={data.subject}
                                onChange={(e) =>
                                    setData('subject', e.target.value)
                                }
                                placeholder="¡Hola {{user_name}}! Bienvenido a..."
                                className="h-11 rounded-xl border-none bg-muted/20 focus-visible:ring-2 focus-visible:ring-primary/30"
                            />
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between pb-1">
                                <Label className="text-sm font-semibold">
                                    Contenido (Soporta HTML)
                                </Label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 gap-2 rounded-lg text-xs"
                                    onClick={handlePreview}
                                >
                                    <Eye className="h-3.5 w-3.5" />
                                    Ver Preview
                                </Button>
                            </div>
                            <Textarea
                                value={data.content}
                                onChange={(e) =>
                                    setData('content', e.target.value)
                                }
                                className="min-h-[250px] rounded-2xl border-none bg-muted/20 font-mono text-sm leading-relaxed focus-visible:ring-2 focus-visible:ring-primary/30"
                                placeholder="<h1>Hola {{user_name}}</h1>..."
                            />
                        </div>

                        <div className="flex items-center justify-between rounded-2xl border bg-muted/10 p-4 transition-all hover:bg-muted/20">
                            <div className="space-y-0.5">
                                <Label className="text-sm font-bold">
                                    Estado de la Plantilla
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Determina si la plantilla se usará en los
                                    envíos automáticos
                                </p>
                            </div>
                            <Switch
                                checked={data.is_active}
                                onCheckedChange={(c) => setData('is_active', c)}
                            />
                        </div>

                        <DialogFooter className="flex-col gap-3 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowModal(false)}
                                className="h-12 rounded-xl px-8"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="h-12 rounded-xl px-12"
                            >
                                {processing
                                    ? 'Guardando...'
                                    : 'Guardar Cambios'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal de Preview Mejorado */}
            <Dialog open={showPreview} onOpenChange={setShowPreview}>
                <DialogContent className="max-h-[90vh] w-[95vw] max-w-2xl overflow-hidden rounded-3xl p-0 sm:w-full">
                    <DialogHeader className="p-6 pb-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <Mail className="h-5 w-5" />
                            </div>
                            <div>
                                <DialogTitle>Previsualización</DialogTitle>
                                <DialogDescription>
                                    Simulación del correo final
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>
                    <div className="bg-muted/30 p-4 sm:p-8">
                        <div className="overflow-hidden rounded-2xl border bg-white shadow-2xl ring-1 ring-black/5 dark:bg-zinc-950">
                            <div className="border-b bg-muted/40 p-4">
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <div className="h-3 w-3 rounded-full bg-red-400/50" />
                                    <div className="h-3 w-3 rounded-full bg-yellow-400/50" />
                                    <div className="h-3 w-3 rounded-full bg-green-400/50" />
                                    <span className="ml-2 font-medium">
                                        Nuevo mensaje
                                    </span>
                                </div>
                            </div>
                            <div className="max-h-[60vh] overflow-y-auto p-4 sm:p-10">
                                <div
                                    className="prose prose-sm dark:prose-invert max-w-none"
                                    dangerouslySetInnerHTML={{
                                        __html: sanitize(previewContent),
                                    }}
                                />
                            </div>
                        </div>
                    </div>
                    <DialogFooter className="p-4 pt-0">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setShowPreview(false)}
                            className="w-full rounded-xl sm:w-auto"
                        >
                            Cerrar Vista Previa
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
