import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import {
LayoutGrid,
    List,
    Upload,
    Link as LinkIcon,
    X,
    Image as ImageIcon,
    Plus,
    Trash2,
    RefreshCw,
    Check,
    Users,
    MessageSquare,
    Phone,
} from 'lucide-react';
import { lazy, Suspense, useState, useRef, useEffect } from 'react';
import { toast } from 'sonner';
import RichEditor from '@/components/rich-editor';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import '@/components/ui/textarea';
import ErrorBoundary from '@/components/error-boundary';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const EmailMarketingPage = lazy(() => import('./EmailMarketing'));
const GlobalPayments = lazy(() => import('./GlobalPayments'));
const Gateways = lazy(() => import('./Gateways'));
const Marketplace = lazy(() => import('./Marketplace'));
const WebhooksPage = lazy(() => import('./Webhooks'));
const FinancialAutomations = lazy(() => import('./FinancialAutomations'));

interface Caracteristica {
    icono: string;
    titulo: string;
    descripcion: string;
}

interface Plan {
    nombre: string;
    precio: string;
    periodo: string;
    descripcion: string;
    popular: boolean;
    caracteristicas: string[];
}

interface WebSetting {
    id: number;
    app_name: string;
    app_logo: string | null;
    app_favicon: string | null;
    app_title: string;
    app_description: string | null;
    app_keywords: string | null;
    app_author: string | null;
    timezone: string;
    locale: string;
    currency: string;
    currency_symbol: string;
    maintenance_mode: boolean;
    hero_titulo?: string;
    hero_subtitulo?: string;
    hero_boton_principal?: string;
    hero_boton_secundario?: string;
    hero_badge?: string;
    caracteristicas?: Caracteristica[];
    planes?: Plan[];
    cta_titulo?: string;
    cta_descripcion?: string;
    cta_boton?: string;
    nav_app_brand_visible?: boolean;
    nav_quienes_somos_visible?: boolean;
    nav_quienes_somos_label?: string;
    nav_quienes_somos_subtitle?: string;
    nav_quienes_somos_content?: string;
    nav_quienes_somos_image?: string;
    nav_feedback_visible?: boolean;
    nav_feedback_label?: string;
    nav_feedback_subtitle?: string;
    nav_feedback_content?: string;
    nav_feedback_image?: string;
    nav_fundacion_visible?: boolean;
    nav_fundacion_label?: string;
    nav_fundacion_subtitle?: string;
    nav_fundacion_content?: string;
    nav_fundacion_image?: string;
    google_client_id?: string;
    google_client_secret?: string;
    google_redirect_uri?: string;
    facebook_client_id?: string;
    facebook_client_secret?: string;
    facebook_redirect_uri?: string;
    global_telegram_bot_username?: string;
    global_telegram_bot_token?: string;
    whatsapp_webhook_url?: string;
    whatsapp_phone_number_id?: string;
    whatsapp_access_token?: string;
    whatsapp_business_id?: string;
    whatsapp_api_version?: string;
}

interface FormFields {
    _method: string;
    app_name: string;
    app_logo: string;
    app_logo_file: File | null;
    app_favicon: string;
    app_favicon_file: File | null;
    app_title: string;
    app_description: string;
    app_keywords: string;
    app_author: string;
    timezone: string;
    locale: string;
    currency: string;
    currency_symbol: string;
    maintenance_mode: boolean;
    hero: {
        titulo: string;
        subtitulo: string;
        boton_principal: string;
        boton_secundario: string;
        badge: string;
    };
    caracteristicas: Caracteristica[];
    planes: Plan[];
    cta: {
        titulo: string;
        descripcion: string;
        boton: string;
    };
    nav: {
        app_brand_visible: boolean;
        quienes_somos_visible: boolean;
        quienes_somos_label: string;
        quienes_somos_subtitle: string;
        quienes_somos_content: string;
        quienes_somos_image: string;
        quienes_somos_image_file: File | null;
        feedback_visible: boolean;
        feedback_label: string;
        feedback_subtitle: string;
        feedback_content: string;
        feedback_image: string;
        feedback_image_file: File | null;
        fundacion_visible: boolean;
        fundacion_label: string;
        fundacion_subtitle: string;
        fundacion_content: string;
        fundacion_image: string;
        fundacion_image_file: File | null;
    };
    google_client_id: string;
    google_client_secret: string;
    google_redirect_uri: string;
    facebook_client_id: string;
    facebook_client_secret: string;
    facebook_redirect_uri: string;
global_telegram_bot_username: string;
     global_telegram_bot_token: string;
     whatsapp_webhook_url: string;
     whatsapp_phone_number_id: string;
     whatsapp_access_token: string;
     whatsapp_business_id: string;
     whatsapp_api_version: string;
}

const defaultHero = {
    titulo: 'Gestiona tu negocio como un experto',
    subtitulo:
        'La plataforma todo-en-uno que necesitas para hacer crecer tu empresa. Desde inventario hasta facturación, todo en un solo lugar.',
    boton_principal: 'Comenzar gratis',
    boton_secundario: 'Ver demo',
    badge: '¡Nuevo! IA integrada para predicción de inventario',
};

const defaultCaracteristicas: Caracteristica[] = [
    {
        icono: '📊',
        titulo: 'Dashboard Inteligente',
        descripcion:
            'Visualiza tus métricas en tiempo real con gráficos interactivos',
    },
    {
        icono: '👥',
        titulo: 'Gestión de Clientes',
        descripcion:
            'CRM completo para gestionar prospectos, oportunidades y clientes',
    },
    {
        icono: '📦',
        titulo: 'Control de Inventario',
        descripcion:
            'Gestiona tu stock con alertas automáticas y múltiples almacenes',
    },
    {
        icono: '💰',
        titulo: 'Facturación Electrónica',
        descripcion: 'Emite facturas, cotizaciones y gestiona tu tesorería',
    },
    {
        icono: '📈',
        titulo: 'Reportes Avanzados',
        descripcion: 'Toma decisiones basadas en datos con análisis detallados',
    },
    {
        icono: '🔗',
        titulo: 'Integraciones',
        descripcion: 'Conecta con pasarelas de pago, envíos y más',
    },
];

const defaultPlanes: Plan[] = [
    {
        nombre: 'Gratuito',
        precio: '$0',
        periodo: '/mes',
        descripcion: 'Perfecto para comenzar',
        popular: false,
        caracteristicas: [
            'Hasta 10 clientes',
            'Gestión básica de inventario',
            '1 usuario administrador',
            'Reportes simples',
            'Soporte por email',
        ],
    },
    {
        nombre: 'Vendedor Independiente',
        precio: '$29',
        periodo: '/mes',
        descripcion: 'Para vendedores individuales',
        popular: false,
        caracteristicas: [
            'Hasta 100 clientes',
            'Gestión completa de inventario',
            '3 usuarios',
            'Facturación electrónica',
            'Reportes avanzados',
            'Soporte prioritario',
        ],
    },
    {
        nombre: 'Premium',
        precio: '$99',
        periodo: '/mes',
        descripcion: 'Para pequeñas empresas',
        popular: true,
        caracteristicas: [
            'Clientes ilimitados',
            'Gestión de proveedores',
            '10 usuarios',
            'Facturación electrónica completa',
            'Reportes detallados',
            'Múltiples almacenes',
            'Integraciones',
            'Soporte por chat',
        ],
    },
    {
        nombre: 'Enterprise',
        precio: '$299',
        periodo: '/mes',
        descripcion: 'Para empresas en crecimiento',
        popular: false,
        caracteristicas: [
            'Todo del plan Premium',
            'Usuarios ilimitados',
            'Múltiples sucursales',
            'Gestión de empleados',
            'Control de acceso avanzado',
            'Auditoría completa',
            'Personalización completa',
            'Soporte telefónico',
        ],
    },
    {
        nombre: 'Corporativo',
        precio: 'Custom',
        periodo: '',
        descripcion: 'Para grandes organizaciones',
        popular: false,
        caracteristicas: [
            'Todo del plan Enterprise',
            'Servidor dedicado',
            '部署 local',
            'Personalización de marca',
            'Capacitación dedicada',
            'Gerente de cuenta',
            'SLA garantizado',
            'Soporte 24/7',
        ],
    },
];

const defaultCta = {
    titulo: '¿Listo para transformar tu negocio?',
    descripcion: 'Únete a miles de empresas que ya están creciendo con GrowERP',
    boton: 'Crear cuenta gratis',
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Configuración Web', href: '/configuracion-web' },
];

const iconos = [
    '📊',
    '👥',
    '📦',
    '💰',
    '📈',
    '🔗',
    '🏭',
    '🚚',
    '🛒',
    '⚙️',
    '📋',
    '💼',
    '🎯',
    '✅',
    '⭐',
    '🚀',
];

function ImageUploadField({
    label,
    currentUrl,
    urlValue,
    onUrlChange,
    onFileChange,
    accept = 'image/*',
    hint,
}: {
    label: string;
    currentUrl: string | null;
    urlValue: string;
    onUrlChange: (v: string) => void;
    onFileChange: (file: File | null) => void;
    accept?: string;
    hint?: string;
}) {
    const [mode, setMode] = useState<'url' | 'file'>('url');
    const [preview, setPreview] = useState<string | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);
    const displaySrc = preview || urlValue || currentUrl || null;

    const handleFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        if (file) {
            const reader = new FileReader();
            reader.onloadend = () => {
                setPreview(reader.result as string);
            };
            reader.readAsDataURL(file);
            onFileChange(file);
        }
    };

    const clearFile = () => {
        setPreview(null);
        onFileChange(null);
        if (fileRef.current) fileRef.current.value = '';
    };

    return (
        <div className="space-y-3">
            <Label>{label}</Label>
            {displaySrc && (
                <div className="relative w-fit rounded-xl border bg-muted/30 p-2">
                    <img
                        src={displaySrc}
                        alt={label}
                        className="h-16 max-w-[200px] rounded-lg object-contain"
                        onError={(e) => {
                            (e.target as HTMLImageElement).style.display =
                                'none';
                        }}
                    />
                    {preview && (
                        <button
                            type="button"
                            onClick={clearFile}
                            className="absolute -top-2 -right-2 rounded-full bg-destructive p-0.5 text-white shadow-md hover:bg-destructive/80"
                        >
                            <X className="h-3 w-3" />
                        </button>
                    )}
                </div>
            )}
            <div className="flex w-fit overflow-hidden rounded-xl border bg-muted/30">
                <button
                    type="button"
                    onClick={() => setMode('url')}
                    className={`flex items-center gap-1.5 px-4 py-2 text-xs font-semibold transition-all ${mode === 'url' ? 'bg-background text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                >
                    <LinkIcon className="h-3 w-3" /> URL
                </button>
                <button
                    type="button"
                    onClick={() => setMode('file')}
                    className={`flex items-center gap-1.5 px-4 py-2 text-xs font-semibold transition-all ${mode === 'file' ? 'bg-background text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                >
                    <Upload className="h-3 w-3" /> Archivo
                </button>
            </div>
            {mode === 'url' ? (
                <div className="space-y-1">
                    <Input
                        value={urlValue}
                        onChange={(e) => onUrlChange(e.target.value)}
                        placeholder="https://ejemplo.com/logo.png o /storage/..."
                    />
                    {hint && (
                        <p className="text-xs text-muted-foreground">{hint}</p>
                    )}
                </div>
            ) : (
                <div>
                    <label
                        htmlFor={`file-${label}`}
                        className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border bg-muted/20 px-6 py-8 text-center transition-all hover:border-primary/50 hover:bg-primary/5"
                    >
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <ImageIcon className="h-5 w-5" />
                        </div>
                        <div>
                            <p className="text-sm font-semibold text-foreground">
                                {preview
                                    ? 'Cambiar archivo'
                                    : 'Haz clic para subir'}
                            </p>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {accept.includes('ico')
                                    ? 'PNG, ICO, SVG · máx 512 KB'
                                    : 'PNG, JPG, SVG, WebP · máx 2 MB'}
                            </p>
                        </div>
                        <input
                            id={`file-${label}`}
                            ref={fileRef}
                            type="file"
                            accept={accept}
                            className="hidden"
                            onChange={handleFile}
                        />
                    </label>
                </div>
            )}
        </div>
    );
}

import CountriesTab, { Country } from './CountriesTab';

export default function Index({
    settings,
    templates,
    logs = [],
    onlineUsers = [],
    countries = [],
}: {
    settings: WebSetting;
    templates?: any[];
    logs?: any[];
    onlineUsers?: any[];
    countries?: Country[];
}) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('admin.web-settings.viewAny');
    const isN8nAllowed = hasPermission(['admin.web-settings.viewAny', 'admin.web-settings.edit']);
    const { data, setData, post, processing } = useForm<FormFields>({
        _method: 'PUT',
        app_name: settings.app_name || '',
        app_logo: settings.app_logo || '',
        app_logo_file: null as File | null,
        app_favicon: settings.app_favicon || '',
        app_favicon_file: null as File | null,
        app_title: settings.app_title || '',
        app_description: settings.app_description || '',
        app_keywords: settings.app_keywords || '',
        app_author: settings.app_author || '',
        timezone: settings.timezone || 'America/Lima',
        locale: settings.locale || 'es',
        currency: settings.currency || 'PEN',
        currency_symbol: settings.currency_symbol || 'S/',
        maintenance_mode: settings.maintenance_mode || false,
        hero: settings.hero_titulo
            ? {
                titulo: settings.hero_titulo,
                subtitulo: settings.hero_subtitulo || '',
                boton_principal: settings.hero_boton_principal || '',
                boton_secundario: settings.hero_boton_secundario || '',
                badge: settings.hero_boton_principal
                    ? settings.hero_badge || ''
                    : '',
            }
            : defaultHero,
        caracteristicas: settings.caracteristicas || defaultCaracteristicas,
        planes: settings.planes || defaultPlanes,
        cta: settings.cta_titulo
            ? {
                titulo: settings.cta_titulo,
                descripcion: settings.cta_descripcion || '',
                boton: settings.cta_boton || '',
            }
            : defaultCta,
        nav: {
            app_brand_visible: settings.nav_app_brand_visible ?? true,
            quienes_somos_visible: settings.nav_quienes_somos_visible ?? true,
            quienes_somos_label: settings.nav_quienes_somos_label || '',
            quienes_somos_subtitle: settings.nav_quienes_somos_subtitle || '',
            quienes_somos_content: settings.nav_quienes_somos_content || '',
            quienes_somos_image: settings.nav_quienes_somos_image || '',
            quienes_somos_image_file: null as File | null,
            feedback_visible: settings.nav_feedback_visible ?? true,
            feedback_label: settings.nav_feedback_label || '',
            feedback_subtitle: settings.nav_feedback_subtitle || '',
            feedback_content: settings.nav_feedback_content || '',
            feedback_image: settings.nav_feedback_image || '',
            feedback_image_file: null as File | null,
            fundacion_visible: settings.nav_fundacion_visible ?? true,
            fundacion_label: settings.nav_fundacion_label || '',
            fundacion_subtitle: settings.nav_fundacion_subtitle || '',
            fundacion_content: settings.nav_fundacion_content || '',
            fundacion_image: settings.nav_fundacion_image || '',
            fundacion_image_file: null as File | null,
        },
        google_client_id: settings.google_client_id || '',
        google_client_secret: settings.google_client_secret || '',
        google_redirect_uri: settings.google_redirect_uri || '',
        facebook_client_id: settings.facebook_client_id || '',
        facebook_client_secret: settings.facebook_client_secret || '',
        facebook_redirect_uri: settings.facebook_redirect_uri || '',
global_telegram_bot_username: settings.global_telegram_bot_username || '',
         global_telegram_bot_token: settings.global_telegram_bot_token || '',
         whatsapp_webhook_url: settings.whatsapp_webhook_url || '',
         whatsapp_phone_number_id: settings.whatsapp_phone_number_id || '',
         whatsapp_access_token: settings.whatsapp_access_token || '',
         whatsapp_business_id: settings.whatsapp_business_id || '',
         whatsapp_api_version: settings.whatsapp_api_version || 'v22.0',
    });

    const [activeSection, setActiveSection] = useState('general');
const [testingGoogle, setTestingGoogle] = useState(false);
     const [testingFacebook, setTestingFacebook] = useState(false);
     const [testingTelegram, setTestingTelegram] = useState(false);
     const [testingWhatsApp, setTestingWhatsApp] = useState(false);
     const [isTelegramTested, setIsTelegramTested] = useState(false);
     const [isWhatsAppTested, setIsWhatsAppTested] = useState(false);
     const [settingTelegramWebhook, setSettingTelegramWebhook] = useState(false);
     const [settingWhatsAppWebhook, setSettingWhatsAppWebhook] = useState(false);

const [n8nConfig, setN8nConfig] = useState({
         provider: 'n8n',
         base_url: '',
         webhook_url: '',
         telegram_proxy_url: '',
         api_key: '',
         is_active: true,
     });
    const [n8nLoaded, setN8nLoaded] = useState(false);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
const [testingN8n, setTestingN8n] = useState(false);
     const [savingN8n, setSavingN8n] = useState(false);

     useEffect(() => {
        if (activeSection === 'n8n' && !n8nLoaded) {
            axios.get('/api/system-integrations/n8n').then((res) => {
                const data = res.data?.data ?? res.data;
setN8nConfig({
                     provider: 'n8n',
                     base_url: data.base_url || '',
                     webhook_url: data.webhook_url || '',
                     telegram_proxy_url: data.telegram_proxy_url || '',
                     api_key: '',
                     is_active: data.is_active ?? true,
                 });
                setN8nLoaded(true);
            }).catch(() => {
                setN8nLoaded(true);
            });
        }
    }, [activeSection, n8nLoaded]);

const testSocialConnection = async (provider: 'google' | 'facebook') => {
         const setTesting = provider === 'google' ? setTestingGoogle : setTestingFacebook;
         setTesting(true);

         const clientId = provider === 'google' ? data.google_client_id : data.facebook_client_id;
         const clientSecret = provider === 'google' ? data.google_client_secret : data.facebook_client_secret;
         const redirectUri = provider === 'google' ? data.google_redirect_uri : data.facebook_redirect_uri;

         if (!clientId || !clientSecret || !redirectUri) {
             toast.error('Completa todos los campos de ' + (provider === 'google' ? 'Google' : 'Facebook') + ' antes de probar.');
             setTesting(false);
             return;
         }

         try {
             const response = await axios.post('/configuracion-web/test-social', {
                 provider,
                 client_id: clientId,
                 client_secret: clientSecret,
                 redirect_uri: redirectUri,
             });

             if (response.data.success) {
                 toast.success(response.data.message);
             } else {
                 toast.error(response.data.message);
             }
         } catch (error: any) {
             const msg = error.response?.data?.message || 'Error al conectar con ' + (provider === 'google' ? 'Google' : 'Facebook');
             toast.error(msg);
         } finally {
             setTesting(false);
         }
     };

     const testTelegramConnection = async () => {
         setTestingTelegram(true);

         if (!data.global_telegram_bot_token || !data.global_telegram_bot_username) {
             toast.error('Completa el token y el username del bot de Telegram antes de probar.');
             setTestingTelegram(false);
             return;
         }

         try {
             const response = await axios.post('/configuracion-web/test-telegram', {
                 bot_token: data.global_telegram_bot_token,
                 bot_username: data.global_telegram_bot_username,
             });

              if (response.data.success) {
                  setIsTelegramTested(true);
                  toast.success(response.data.message);
              } else {
                  setIsTelegramTested(false);
                  toast.error(response.data.message);
              }
          } catch (error: any) {
              setIsTelegramTested(false);
             const msg = error.response?.data?.message || 'Error al conectar con Telegram';
             toast.error(msg);
         } finally {
             setTestingTelegram(false);
         }
     };

     const testWhatsAppConnection = async () => {
         setTestingWhatsApp(true);

         if (!data.whatsapp_webhook_url) {
             toast.error('Completa la URL del webhook de WhatsApp antes de probar.');
             setTestingWhatsApp(false);
             return;
         }

         try {
             const response = await axios.post('/configuracion-web/test-whatsapp', {
                 webhook_url: data.whatsapp_webhook_url,
             });

              if (response.data.success) {
                  setIsWhatsAppTested(true);
                  toast.success(response.data.message);
              } else {
                  setIsWhatsAppTested(false);
                  toast.error(response.data.message);
              }
          } catch (error: any) {
              setIsWhatsAppTested(false);
             const msg = error.response?.data?.message || 'Error al conectar con WhatsApp';
             toast.error(msg);
         } finally {
             setTestingWhatsApp(false);
         }
     };

     const openTelegramChat = () => {
         if (!data.global_telegram_bot_username) {
             toast.error('Configura primero el username del bot de Telegram.');
             return;
         }
         window.open(`https://t.me/${data.global_telegram_bot_username.replace(/^@/, '')}`, '_blank');
     };

     const openWhatsAppChat = () => {
         if (!data.whatsapp_webhook_url) {
             toast.error('Configura primero la URL del webhook de WhatsApp.');
             return;
         }
         window.open(data.whatsapp_webhook_url, '_blank');
     };

     const setTelegramWebhook = async () => {
         if (!data.global_telegram_bot_token) {
             toast.error('Completa el token del bot de Telegram antes de establecer el webhook.');
             return;
         }

         setSettingTelegramWebhook(true);

         try {
             const response = await axios.post('/configuracion-web/set-telegram-webhook', {
                 bot_token: data.global_telegram_bot_token,
             });

             if (response.data.success) {
                 toast.success(response.data.message);
             } else {
                 toast.error(response.data.message);
             }
         } catch (error: any) {
             const msg = error.response?.data?.message || 'Error al establecer el webhook de Telegram';
             toast.error(msg);
         } finally {
             setSettingTelegramWebhook(false);
         }
     };

     const setWhatsAppWebhook = async () => {
         if (!data.whatsapp_access_token || !data.whatsapp_business_id) {
             toast.error('Completa el Access Token y el Business ID de WhatsApp antes de establecer el webhook.');
             return;
         }

         setSettingWhatsAppWebhook(true);

         try {
             const response = await axios.post('/configuracion-web/set-whatsapp-webhook', {
                 webhook_url: data.whatsapp_webhook_url,
                 phone_number_id: data.whatsapp_phone_number_id,
                 access_token: data.whatsapp_access_token,
                 business_id: data.whatsapp_business_id,
                 api_version: data.whatsapp_api_version,
             });

             if (response.data.success) {
                 toast.success(response.data.message);
             } else {
                 toast.error(response.data.message);
             }
         } catch (error: any) {
             const msg = error.response?.data?.message || 'Error al establecer el webhook de WhatsApp';
             toast.error(msg);
         } finally {
             setSettingWhatsAppWebhook(false);
         }
     };

     const handleTelegramChange = (
         field: 'global_telegram_bot_token' | 'global_telegram_bot_username',
         value: string,
     ) => {
         setData(field, value);
         setIsTelegramTested(false);
     };

     const handleWhatsAppChange = (
         field:
             | 'whatsapp_webhook_url'
             | 'whatsapp_phone_number_id'
             | 'whatsapp_access_token'
             | 'whatsapp_business_id'
             | 'whatsapp_api_version',
         value: string,
     ) => {
         setData(field, value);
         setIsWhatsAppTested(false);
     };

    const addCaracteristica = () => {
        setData('caracteristicas', [
            ...data.caracteristicas!,
            {
                icono: '📊',
                titulo: 'Nueva característica',
                descripcion: 'Descripción',
            },
        ]);
    };

    const removeCaracteristica = (index: number) => {
        const newCaract = [...data.caracteristicas!];
        newCaract.splice(index, 1);
        setData('caracteristicas', newCaract);
    };

    const updateCaracteristica = (
        index: number,
        field: keyof Caracteristica,
        value: string,
    ) => {
        const newCaract = [...data.caracteristicas!];
        newCaract[index] = { ...newCaract[index], [field]: value };
        setData('caracteristicas', newCaract);
    };

    const addPlanCaracteristica = (planIndex: number) => {
        const newPlanes = [...data.planes!];
        newPlanes[planIndex].caracteristicas.push('Nueva característica');
        setData('planes', newPlanes);
    };

    const removePlanCaracteristica = (
        planIndex: number,
        caractIndex: number,
    ) => {
        const newPlanes = [...data.planes!];
        newPlanes[planIndex].caracteristicas.splice(caractIndex, 1);
        setData('planes', newPlanes);
    };

    const updatePlanCaracteristica = (
        planIndex: number,
        caractIndex: number,
        value: string,
    ) => {
        const newPlanes = [...data.planes!];
        newPlanes[planIndex].caracteristicas[caractIndex] = value;
        setData('planes', newPlanes);
    };

    const addPlan = () => {
        setData('planes', [
            ...data.planes!,
            {
                nombre: 'Nuevo Plan',
                precio: '$0',
                periodo: '/mes',
                descripcion: 'Descripción del plan',
                popular: false,
                caracteristicas: ['Característica 1'],
            },
        ]);
    };

    const removePlan = (index: number) => {
        const newPlanes = [...data.planes!];
        newPlanes.splice(index, 1);
        setData('planes', newPlanes);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/configuracion-web/${settings.id}`, { forceFormData: true });
    };

    const sections = [
        { id: 'general', label: 'Configuración', icon: '⚙️' },
        { id: 'hero', label: 'Hero', icon: '🎯' },
        { id: 'caracteristicas', label: 'Características', icon: '✨' },
        { id: 'planes', label: 'Planes', icon: '💰' },
        { id: 'global-payments', label: 'Pagos Globales', icon: '💳' },
        { id: 'gateways', label: 'Gateways', icon: '🏦' },
        { id: 'marketplace', label: 'Marketplace', icon: '💰' },
        { id: 'webhooks', label: 'Webhooks', icon: '🔔' },
        { id: 'cta', label: 'CTA Final', icon: '📢' },
        { id: 'pages', label: 'Páginas', icon: '📄' },
        { id: 'email-marketing', label: 'Email Marketing', icon: '📧' },
        { id: 'social', label: 'Redes Sociales', icon: '🔗' },
        { id: 'financial-automations', label: 'Auto. Financieras', icon: '⚡' },
        { id: 'countries', label: 'Países', icon: '🌎' },
        { id: 'logs', label: 'Logs', icon: '📋' },
        ...(isN8nAllowed ? [{ id: 'n8n', label: 'n8n', icon: '⚡' }] : []),
    ];

    if (!canAccess) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">No tienes permiso para acceder a esta página.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <>
            <Head title="Configuración Web" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="mx-auto flex max-w-6xl flex-col gap-6 p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                Configuración de Página de Inicio
                            </h1>
                            <p className="text-muted-foreground">
                                Personaliza el contenido de la página de inicio
                            </p>
                        </div>
                        <Button
                            type="submit"
                            form="config-form"
                            disabled={processing}
                            className="px-8"
                        >
                            {processing ? 'Guardando...' : 'Guardar Cambios'}
                        </Button>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {sections.map((s) => (
                            <Button
                                key={s.id}
                                variant={
                                    activeSection === s.id
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                                onClick={() => setActiveSection(s.id)}
                            >
                                <span className="mr-1">{s.icon}</span> {s.label}
                            </Button>
                        ))}
                    </div>

                    <form id="config-form" onSubmit={handleSubmit}>
                        {activeSection === 'general' && (
                            <div className="grid gap-6 md:grid-cols-2">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Configuración SEO</CardTitle>
                                        <CardDescription>
                                            Datos para SEO y Configuracion web
                                        </CardDescription>
                                        <CardDescription>
                                            Nombre y descripción
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="space-y-2">
                                            <Label>Nombre del Sitio *</Label>
                                            <Input
                                                value={data.app_name}
                                                onChange={(e) =>
                                                    setData(
                                                        'app_name',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Título (Meta) *</Label>
                                            <Input
                                                value={data.app_title}
                                                onChange={(e) =>
                                                    setData(
                                                        'app_title',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Descripción (Meta)</Label>
                                            <Input
                                                value={data.app_description}
                                                onChange={(e) =>
                                                    setData(
                                                        'app_description',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Palabras Clave</Label>
                                            <Input
                                                value={data.app_keywords}
                                                onChange={(e) =>
                                                    setData(
                                                        'app_keywords',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="erp, gestión, empresa"
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Recursos</CardTitle>
                                        <CardDescription>
                                            Logo y favicon
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <ImageUploadField
                                            label="Logo"
                                            currentUrl={settings.app_logo}
                                            urlValue={data.app_logo}
                                            onUrlChange={(v) =>
                                                setData('app_logo', v)
                                            }
                                            onFileChange={(f) =>
                                                setData('app_logo_file', f)
                                            }
                                            accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                        />
                                        <ImageUploadField
                                            label="Favicon"
                                            currentUrl={settings.app_favicon}
                                            urlValue={data.app_favicon}
                                            onUrlChange={(v) =>
                                                setData('app_favicon', v)
                                            }
                                            onFileChange={(f) =>
                                                setData('app_favicon_file', f)
                                            }
                                            accept="image/x-icon,image/png"
                                            hint="Se muestra en la pestaña del navegador"
                                        />
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        {activeSection === 'hero' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Sección Hero</CardTitle>
                                    <CardDescription>
                                        La sección principal de la página de
                                        inicio
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-2">
                                        <Label>
                                            Badge (texto pequeño arriba del
                                            título)
                                        </Label>
                                        <Input
                                            value={data.hero?.badge}
                                            onChange={(e) =>
                                                setData('hero', {
                                                    ...data.hero!,
                                                    badge: e.target.value,
                                                })
                                            }
                                            placeholder="¡Nuevo! IA integrada..."
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Título Principal *</Label>
                                        <Input
                                            value={data.hero?.titulo}
                                            onChange={(e) =>
                                                setData('hero', {
                                                    ...data.hero!,
                                                    titulo: e.target.value,
                                                })
                                            }
                                            placeholder="Gestiona tu negocio..."
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Subtítulo</Label>
                                        <Input
                                            value={data.hero?.subtitulo}
                                            onChange={(e) =>
                                                setData('hero', {
                                                    ...data.hero!,
                                                    subtitulo: e.target.value,
                                                })
                                            }
                                            placeholder="La plataforma todo-en-uno..."
                                        />
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                        <div className="space-y-2">
                                            <Label>Botón Principal</Label>
                                            <Input
                                                value={
                                                    data.hero?.boton_principal
                                                }
                                                onChange={(e) =>
                                                    setData('hero', {
                                                        ...data.hero!,
                                                        boton_principal:
                                                            e.target.value,
                                                    })
                                                }
                                                placeholder="Comenzar gratis"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Botón Secundario</Label>
                                            <Input
                                                value={
                                                    data.hero?.boton_secundario
                                                }
                                                onChange={(e) =>
                                                    setData('hero', {
                                                        ...data.hero!,
                                                        boton_secundario:
                                                            e.target.value,
                                                    })
                                                }
                                                placeholder="Ver demo"
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {activeSection === 'caracteristicas' && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <div>
                                        <CardTitle>Características</CardTitle>
                                        <CardDescription>
                                            Las características que se muestran
                                            en la página
                                        </CardDescription>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addCaracteristica}
                                    >
                                        <Plus className="mr-1 h-4 w-4" />{' '}
                                        Agregar
                                    </Button>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {data.caracteristicas?.map(
                                        (caract, index) => (
                                            <div
                                                key={index}
                                                className="flex items-start gap-3 rounded-lg border bg-muted/20 p-4"
                                            >
                                                <div className="flex flex-col gap-2">
                                                    <Label className="text-xs">
                                                        Icono
                                                    </Label>
                                                    <select
                                                        value={caract.icono}
                                                        onChange={(e) =>
                                                            updateCaracteristica(
                                                                index,
                                                                'icono',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="h-10 rounded-md border bg-background px-2 text-lg"
                                                    >
                                                        {iconos.map((icon) => (
                                                            <option
                                                                key={icon}
                                                                value={icon}
                                                            >
                                                                {icon}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div className="flex-1 space-y-2">
                                                    <Label className="text-xs">
                                                        Título
                                                    </Label>
                                                    <Input
                                                        value={caract.titulo}
                                                        onChange={(e) =>
                                                            updateCaracteristica(
                                                                index,
                                                                'titulo',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="flex-1 space-y-2">
                                                    <Label className="text-xs">
                                                        Descripción
                                                    </Label>
                                                    <Input
                                                        value={
                                                            caract.descripcion
                                                        }
                                                        onChange={(e) =>
                                                            updateCaracteristica(
                                                                index,
                                                                'descripcion',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-destructive"
                                                    onClick={() =>
                                                        removeCaracteristica(
                                                            index,
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        ),
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {activeSection === 'planes' && (
                            <div className="space-y-4">
                                <div className="flex justify-end">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addPlan}
                                    >
                                        <Plus className="mr-1 h-4 w-4" />{' '}
                                        Agregar Plan
                                    </Button>
                                </div>
                                {data.planes?.map((plan, planIndex) => (
                                    <Card key={planIndex}>
                                        <CardHeader className="pb-4">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-3">
                                                    <Input
                                                        value={plan.nombre}
                                                        onChange={(e) => {
                                                            const newPlanes =
                                                                data.planes!.map(
                                                                    (p, i) =>
                                                                        i ===
                                                                            planIndex
                                                                            ? {
                                                                                ...p,
                                                                                nombre: e
                                                                                    .target
                                                                                    .value,
                                                                            }
                                                                            : p,
                                                                );
                                                            setData(
                                                                'planes',
                                                                newPlanes,
                                                            );
                                                        }}
                                                        className="w-48 font-bold"
                                                        placeholder="Nombre del plan"
                                                    />
                                                    <Checkbox
                                                        checked={plan.popular}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) => {
                                                            const newPlanes =
                                                                data.planes!.map(
                                                                    (p, i) =>
                                                                        i ===
                                                                            planIndex
                                                                            ? {
                                                                                ...p,
                                                                                popular:
                                                                                    Boolean(
                                                                                        checked,
                                                                                    ),
                                                                            }
                                                                            : p,
                                                                );
                                                            setData(
                                                                'planes',
                                                                newPlanes,
                                                            );
                                                        }}
                                                        id={`popular-${planIndex}`}
                                                    />
                                                    <Label
                                                        htmlFor={`popular-${planIndex}`}
                                                        className="text-sm"
                                                    >
                                                        Popular
                                                    </Label>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-destructive"
                                                    onClick={() =>
                                                        removePlan(planIndex)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                                <div className="space-y-2">
                                                    <Label className="text-xs">
                                                        Precio
                                                    </Label>
                                                    <Input
                                                        value={plan.precio}
                                                        onChange={(e) => {
                                                            const newPlanes =
                                                                data.planes!.map(
                                                                    (p, i) =>
                                                                        i ===
                                                                            planIndex
                                                                            ? {
                                                                                ...p,
                                                                                precio: e
                                                                                    .target
                                                                                    .value,
                                                                            }
                                                                            : p,
                                                                );
                                                            setData(
                                                                'planes',
                                                                newPlanes,
                                                            );
                                                        }}
                                                        placeholder="$99"
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label className="text-xs">
                                                        Período
                                                    </Label>
                                                    <Input
                                                        value={plan.periodo}
                                                        onChange={(e) => {
                                                            const newPlanes =
                                                                data.planes!.map(
                                                                    (p, i) =>
                                                                        i ===
                                                                            planIndex
                                                                            ? {
                                                                                ...p,
                                                                                periodo:
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                            }
                                                                            : p,
                                                                );
                                                            setData(
                                                                'planes',
                                                                newPlanes,
                                                            );
                                                        }}
                                                        placeholder="/mes"
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label className="text-xs">
                                                        Descripción
                                                    </Label>
                                                    <Input
                                                        value={plan.descripcion}
                                                        onChange={(e) => {
                                                            const newPlanes =
                                                                data.planes!.map(
                                                                    (p, i) =>
                                                                        i ===
                                                                            planIndex
                                                                            ? {
                                                                                ...p,
                                                                                descripcion:
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                            }
                                                                            : p,
                                                                );
                                                            setData(
                                                                'planes',
                                                                newPlanes,
                                                            );
                                                        }}
                                                        placeholder="Para pequeñas empresas"
                                                    />
                                                </div>
                                            </div>
                                            <div>
                                                <div className="mb-2 flex items-center justify-between">
                                                    <Label className="text-xs">
                                                        Características
                                                    </Label>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            addPlanCaracteristica(
                                                                planIndex,
                                                            )
                                                        }
                                                    >
                                                        <Plus className="mr-1 h-3 w-3" />{' '}
                                                        Agregar
                                                    </Button>
                                                </div>
                                                <div className="space-y-2">
                                                    {plan.caracteristicas.map(
                                                        (
                                                            caract,
                                                            caractIndex,
                                                        ) => (
                                                            <div
                                                                key={
                                                                    caractIndex
                                                                }
                                                                className="flex gap-2"
                                                            >
                                                                <Input
                                                                    value={
                                                                        caract
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        updatePlanCaracteristica(
                                                                            planIndex,
                                                                            caractIndex,
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="Característica"
                                                                />
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="text-destructive"
                                                                    onClick={() =>
                                                                        removePlanCaracteristica(
                                                                            planIndex,
                                                                            caractIndex,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}

                        {activeSection === 'global-payments' && (
                            <Suspense
                                fallback={
                                    <Card>
                                        <CardContent className="flex h-64 items-center justify-center">
                                            <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                                        </CardContent>
                                    </Card>
                                }
                            >
                                <GlobalPayments />
                            </Suspense>
                        )}

                        {activeSection === 'gateways' && (
                            <Suspense
                                fallback={
                                    <Card>
                                        <CardContent className="flex h-64 items-center justify-center">
                                            <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                                        </CardContent>
                                    </Card>
                                }
                            >
                                <Gateways />
                            </Suspense>
                        )}

                        {activeSection === 'marketplace' && (
                            <ErrorBoundary>
                                <Suspense
                                    fallback={
                                        <Card>
                                            <CardContent className="flex h-64 items-center justify-center">
                                                <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                                            </CardContent>
                                        </Card>
                                    }
                                >
                                    <Marketplace />
                                </Suspense>
                            </ErrorBoundary>
                        )}

                        {activeSection === 'webhooks' && (
                            <Suspense
                                fallback={
                                    <Card>
                                        <CardContent className="flex h-64 items-center justify-center">
                                            <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                                        </CardContent>
                                    </Card>
                                }
                            >
                                <WebhooksPage />
                            </Suspense>
                        )}

                        {activeSection === 'financial-automations' && (
                            <Suspense
                                fallback={
                                    <Card>
                                        <CardContent className="flex h-64 items-center justify-center">
                                            <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                                        </CardContent>
                                    </Card>
                                }
                            >
                                <FinancialAutomations />
                            </Suspense>
                        )}

                        {activeSection === 'cta' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Sección CTA Final</CardTitle>
                                    <CardDescription>
                                        El llamado a la acción antes del footer
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-2">
                                        <Label>Título</Label>
                                        <Input
                                            value={data.cta?.titulo}
                                            onChange={(e) =>
                                                setData('cta', {
                                                    ...data.cta!,
                                                    titulo: e.target.value,
                                                })
                                            }
                                            placeholder="¿Listo para transformar tu negocio?"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Descripción</Label>
                                        <Input
                                            value={data.cta?.descripcion}
                                            onChange={(e) =>
                                                setData('cta', {
                                                    ...data.cta!,
                                                    descripcion: e.target.value,
                                                })
                                            }
                                            placeholder="Únete a miles de empresas..."
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Texto del Botón</Label>
                                        <Input
                                            value={data.cta?.boton}
                                            onChange={(e) =>
                                                setData('cta', {
                                                    ...data.cta!,
                                                    boton: e.target.value,
                                                })
                                            }
                                            placeholder="Crear cuenta gratis"
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {activeSection === 'pages' && (
                            <div className="grid gap-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <span>📄</span> Páginas de Navegación
                                        </CardTitle>
                                        <CardDescription>
                                            Controla la visibilidad y edita el contenido de cada página
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-8">
                                        {/* Marca */}
                                        <div className="rounded-xl border bg-muted/30 p-4">
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <h4 className="font-semibold">Nombre / Logo de la aplicación</h4>
                                                    <p className="text-sm text-muted-foreground">Muestra u oculta el logo y nombre en el header del sitio público</p>
                                                </div>
                                                <Switch
                                                    checked={data.nav?.app_brand_visible ?? true}
                                                    onCheckedChange={(v) =>
                                                        setData('nav', { ...data.nav!, app_brand_visible: v })
                                                    }
                                                />
                                            </div>
                                        </div>

                                        {/* Quiénes Somos */}
                                        <div className="rounded-xl border p-4">
                                            <div className="flex items-center justify-between mb-4">
                                                <div>
                                                    <h4 className="font-semibold">Quiénes Somos</h4>
                                                    <p className="text-sm text-muted-foreground">/{'quienes-somos'}</p>
                                                </div>
                                                <Switch
                                                    checked={data.nav?.quienes_somos_visible ?? true}
                                                    onCheckedChange={(v) =>
                                                        setData('nav', { ...data.nav!, quienes_somos_visible: v })
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-4">
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="space-y-1.5">
                                                        <Label className="text-xs">Etiqueta del enlace</Label>
                                                        <Input
                                                            value={data.nav?.quienes_somos_label || ''}
                                                            onChange={(e) =>
                                                                setData('nav', { ...data.nav!, quienes_somos_label: e.target.value })
                                                            }
                                                            placeholder="Quiénes Somos"
                                                        />
                                                    </div>
                                                    <div className="space-y-1.5">
                                                        <Label className="text-xs">Subtítulo</Label>
                                                        <Input
                                                            value={data.nav?.quienes_somos_subtitle || ''}
                                                            onChange={(e) =>
                                                                setData('nav', { ...data.nav!, quienes_somos_subtitle: e.target.value })
                                                            }
                                                            placeholder="Conoce nuestra historia"
                                                        />
                                                    </div>
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs">Imagen de portada</Label>
                                                    <ImageUploadField
                                                        label="Imagen - Quiénes Somos"
                                                        currentUrl={settings.nav_quienes_somos_image || null}
                                                        urlValue={data.nav?.quienes_somos_image || ''}
                                                        onUrlChange={(v) => setData('nav', { ...data.nav!, quienes_somos_image: v })}
                                                        onFileChange={(f) => setData('nav', { ...data.nav!, quienes_somos_image_file: f })}
                                                    />
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs">Contenido de la página</Label>
                                                    <RichEditor
                                                        value={data.nav?.quienes_somos_content || ''}
                                                        onChange={(html) => setData('nav', { ...data.nav!, quienes_somos_content: html })}
                                                        placeholder="Escribe el contenido de la página Quiénes Somos..."
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        {/* Feedback */}
                                        <div className="rounded-xl border p-4">
                                            <div className="flex items-center justify-between mb-4">
                                                <div>
                                                    <h4 className="font-semibold">Feedback</h4>
                                                    <p className="text-sm text-muted-foreground">/{'feedback'}</p>
                                                </div>
                                                <Switch
                                                    checked={data.nav?.feedback_visible ?? true}
                                                    onCheckedChange={(v) =>
                                                        setData('nav', { ...data.nav!, feedback_visible: v })
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-4">
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="space-y-1.5">
                                                        <Label className="text-xs">Etiqueta del enlace</Label>
                                                        <Input
                                                            value={data.nav?.feedback_label || ''}
                                                            onChange={(e) =>
                                                                setData('nav', { ...data.nav!, feedback_label: e.target.value })
                                                            }
                                                            placeholder="Feedback"
                                                        />
                                                    </div>
                                                    <div className="space-y-1.5">
                                                        <Label className="text-xs">Subtítulo</Label>
                                                        <Input
                                                            value={data.nav?.feedback_subtitle || ''}
                                                            onChange={(e) =>
                                                                setData('nav', { ...data.nav!, feedback_subtitle: e.target.value })
                                                            }
                                                            placeholder="Tu opinión importa"
                                                        />
                                                    </div>
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs">Imagen de portada</Label>
                                                    <ImageUploadField
                                                        label="Imagen - Feedback"
                                                        currentUrl={settings.nav_feedback_image || null}
                                                        urlValue={data.nav?.feedback_image || ''}
                                                        onUrlChange={(v) => setData('nav', { ...data.nav!, feedback_image: v })}
                                                        onFileChange={(f) => setData('nav', { ...data.nav!, feedback_image_file: f })}
                                                    />
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs">Contenido de la página</Label>
                                                    <RichEditor
                                                        value={data.nav?.feedback_content || ''}
                                                        onChange={(html) => setData('nav', { ...data.nav!, feedback_content: html })}
                                                        placeholder="Escribe el contenido de la página Feedback..."
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        {/* Nuestra Fundación */}
                                        <div className="rounded-xl border p-4">
                                            <div className="flex items-center justify-between mb-4">
                                                <div>
                                                    <h4 className="font-semibold">Nuestra Fundación</h4>
                                                    <p className="text-sm text-muted-foreground">/{'fundacion'}</p>
                                                </div>
                                                <Switch
                                                    checked={data.nav?.fundacion_visible ?? true}
                                                    onCheckedChange={(v) =>
                                                        setData('nav', { ...data.nav!, fundacion_visible: v })
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-4">
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="space-y-1.5">
                                                        <Label className="text-xs">Etiqueta del enlace</Label>
                                                        <Input
                                                            value={data.nav?.fundacion_label || ''}
                                                            onChange={(e) =>
                                                                setData('nav', { ...data.nav!, fundacion_label: e.target.value })
                                                            }
                                                            placeholder="Nuestra Fundación"
                                                        />
                                                    </div>
                                                    <div className="space-y-1.5">
                                                        <Label className="text-xs">Subtítulo</Label>
                                                        <Input
                                                            value={data.nav?.fundacion_subtitle || ''}
                                                            onChange={(e) =>
                                                                setData('nav', { ...data.nav!, fundacion_subtitle: e.target.value })
                                                            }
                                                            placeholder="Transformando vidas"
                                                        />
                                                    </div>
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs">Imagen de portada</Label>
                                                    <ImageUploadField
                                                        label="Imagen - Fundación"
                                                        currentUrl={settings.nav_fundacion_image || null}
                                                        urlValue={data.nav?.fundacion_image || ''}
                                                        onUrlChange={(v) => setData('nav', { ...data.nav!, fundacion_image: v })}
                                                        onFileChange={(f) => setData('nav', { ...data.nav!, fundacion_image_file: f })}
                                                    />
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs">Contenido de la página</Label>
                                                    <RichEditor
                                                        value={data.nav?.fundacion_content || ''}
                                                        onChange={(html) => setData('nav', { ...data.nav!, fundacion_content: html })}
                                                        placeholder="Escribe el contenido de la página Fundación..."
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        {activeSection === 'email-marketing' && (
                            <Suspense
                                fallback={
                                    <Card>
                                        <CardContent className="flex h-64 items-center justify-center">
                                            <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                                        </CardContent>
                                    </Card>
                                }
                            >
                                <EmailMarketingPage templates={templates} />
                            </Suspense>
                        )}

                        {activeSection === 'social' && (
                            <div>
                                <div className="grid gap-6 md:grid-cols-2">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <span>🔵 Google</span>
                                            </CardTitle>
                                            <CardDescription>
                                                Credenciales de Google OAuth para inicio de sesión con Google
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div className="space-y-1.5">
                                                <Label htmlFor="google_client_id">Google Client ID</Label>
                                                <Input
                                                    id="google_client_id"
                                                    value={data.google_client_id}
                                                    onChange={(e) => setData('google_client_id', e.target.value)}
                                                    placeholder="515936358278-xxxxx.apps.googleusercontent.com"
                                                />
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label htmlFor="google_client_secret">Google Client Secret</Label>
                                                <Input
                                                    id="google_client_secret"
                                                    value={data.google_client_secret}
                                                    onChange={(e) => setData('google_client_secret', e.target.value)}
                                                    placeholder="GOCSPX-xxxxx"
                                                />
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label htmlFor="google_redirect_uri">Google Redirect URI</Label>
                                                <Input
                                                    id="google_redirect_uri"
                                                    value={data.google_redirect_uri}
                                                    onChange={(e) => setData('google_redirect_uri', e.target.value)}
                                                    placeholder="http://localhost:8000/auth/google/callback"
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => testSocialConnection('google')}
                                                disabled={testingGoogle}
                                                className="w-full gap-2"
                                            >
                                                {testingGoogle ? (
                                                    <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                ) : (
                                                    <RefreshCw className="h-4 w-4" />
                                                )}
                                                {testingGoogle ? 'Probando conexión...' : 'Probar Conexión con Google'}
                                            </Button>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <span>📘 Facebook</span>
                                            </CardTitle>
                                            <CardDescription>
                                                Credenciales de Facebook OAuth para inicio de sesión con Facebook
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div className="space-y-1.5">
                                                <Label htmlFor="facebook_client_id">Facebook Client ID</Label>
                                                <Input
                                                    id="facebook_client_id"
                                                    value={data.facebook_client_id}
                                                    onChange={(e) => setData('facebook_client_id', e.target.value)}
                                                    placeholder="1320052013408272"
                                                />
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label htmlFor="facebook_client_secret">Facebook Client Secret</Label>
                                                <Input
                                                    id="facebook_client_secret"
                                                    value={data.facebook_client_secret}
                                                    onChange={(e) => setData('facebook_client_secret', e.target.value)}
                                                    placeholder="910fb902e5c544a6ed152e2906048266"
                                                />
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label htmlFor="facebook_redirect_uri">Facebook Redirect URI</Label>
                                                <Input
                                                    id="facebook_redirect_uri"
                                                    value={data.facebook_redirect_uri}
                                                    onChange={(e) => setData('facebook_redirect_uri', e.target.value)}
                                                    placeholder="http://localhost:8000/auth/facebook/callback"
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => testSocialConnection('facebook')}
                                                disabled={testingFacebook}
                                                className="w-full gap-2"
                                            >
                                                {testingFacebook ? (
                                                    <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                ) : (
                                                    <RefreshCw className="h-4 w-4" />
                                                )}
                                                {testingFacebook ? 'Probando conexión...' : 'Probar Conexión con Facebook'}
                                            </Button>
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="mt-6 space-y-6">
                                    <div className="grid gap-6 md:grid-cols-2">
<Card>
                                             <CardHeader>
                                                 <CardTitle className="flex items-center gap-2">
                                                     <MessageSquare className="h-4 w-4" />
                                                     Telegram Global
                                                 </CardTitle>
                                                 <CardDescription>
                                                     Configura el bot oficial de Telegram para la plataforma
                                                 </CardDescription>
                                             </CardHeader>
                                             <CardContent className="space-y-4">
                                                 <div className="space-y-1.5">
                                                     <Label htmlFor="global_telegram_bot_token">Token del Bot</Label>
                                                     <Input
                                                         id="global_telegram_bot_token"
                                                         type="password"
                                                         value={data.global_telegram_bot_token}
                                                          onChange={(e) => handleTelegramChange('global_telegram_bot_token', e.target.value)}
                                                         placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                                                     />
                                                     <p className="text-[11px] text-muted-foreground">
                                                         El token del bot de Telegram que se usará para enviar notificaciones.
                                                     </p>
                                                 </div>
                                                 <div className="space-y-1.5">
                                                     <Label htmlFor="global_telegram_bot_username">Username del Bot Oficial</Label>
                                                     <Input
                                                         id="global_telegram_bot_username"
                                                         value={data.global_telegram_bot_username}
                                                          onChange={(e) => handleTelegramChange('global_telegram_bot_username', e.target.value)}
                                                         placeholder="@aldia_bot"
                                                     />
                                                     <p className="text-[11px] text-muted-foreground">
                                                         El username del bot oficial de Telegram que todos los negocios usarán para recibir notificaciones.
                                                     </p>
                                                 </div>
                                                 <div className="flex gap-2">
                                                     <Button
                                                         type="button"
                                                         variant="outline"
                                                         onClick={testTelegramConnection}
                                                         disabled={testingTelegram}
                                                         className="flex-1 gap-2"
                                                     >
                                                         {testingTelegram ? (
                                                             <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                         ) : (
                                                             <RefreshCw className="h-4 w-4" />
                                                         )}
                                                         {testingTelegram ? 'Probando...' : 'Probar Conexión'}
                                                     </Button>
                                                      {isTelegramTested && (
                                                         <Button
                                                             type="button"
                                                             variant="default"
                                                             className="gap-2"
                                                         >
                                                             <Check className="h-4 w-4" />
                                                             Conectado
                                                         </Button>
                                                     )}
                                                      <Button
                                                          type="button"
                                                          variant="outline"
                                                          onClick={openTelegramChat}
                                                          disabled={!isTelegramTested}
                                                          className="gap-2"
                                                      >
                                                           <MessageSquare className="h-4 w-4" />
                                                           Abrir Chat
                                                       </Button>
                                                  </div>
                                                  <Button
                                                      type="button"
                                                      variant="default"
                                                      onClick={setTelegramWebhook}
                                                      disabled={settingTelegramWebhook}
                                                      className="w-full gap-2"
                                                  >
                                                      {settingTelegramWebhook ? (
                                                          <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                      ) : (
                                                          <LinkIcon className="h-4 w-4" />
                                                      )}
                                                      {settingTelegramWebhook ? 'Estableciendo webhook...' : 'Establecer Webhook Global'}
                                                  </Button>
                                              </CardContent>
                                         </Card>
<Card>
                                             <CardHeader>
                                                 <CardTitle className="flex items-center gap-2">
                                                     <Phone className="h-4 w-4" />
                                                     WhatsApp Global
                                                 </CardTitle>
                                                 <CardDescription>
                                                     Configura el webhook global de WhatsApp para la plataforma
                                                 </CardDescription>
                                             </CardHeader>
                                             <CardContent className="space-y-4">
                                                 <div className="space-y-1.5">
                                                     <Label htmlFor="whatsapp_webhook_url">Webhook URL de WhatsApp</Label>
                                                     <Input
                                                         id="whatsapp_webhook_url"
                                                         value={data.whatsapp_webhook_url}
                                                          onChange={(e) => handleWhatsAppChange('whatsapp_webhook_url', e.target.value)}
                                                         placeholder="https://tu-dominio.com/webhooks/whatsapp"
                                                     />
                                                     <p className="text-[11px] text-muted-foreground">
                                                         La URL del webhook donde WhatsApp Cloud enviará los eventos de mensajes.
                                                     </p>
                                                 </div>
                                                 <div className="space-y-1.5">
                                                     <Label htmlFor="whatsapp_phone_number_id">WhatsApp Phone Number ID</Label>
                                                     <Input
                                                         id="whatsapp_phone_number_id"
                                                         value={data.whatsapp_phone_number_id}
                                                          onChange={(e) => handleWhatsAppChange('whatsapp_phone_number_id', e.target.value)}
                                                         placeholder="1234567890"
                                                     />
                                                 </div>
                                                 <div className="space-y-1.5">
                                                     <Label htmlFor="whatsapp_access_token">WhatsApp Access Token</Label>
                                                     <Input
                                                         id="whatsapp_access_token"
                                                         type="password"
                                                         value={data.whatsapp_access_token}
                                                          onChange={(e) => handleWhatsAppChange('whatsapp_access_token', e.target.value)}
                                                         placeholder="Ingresa el Access Token"
                                                     />
                                                 </div>
                                                 <div className="space-y-1.5">
                                                     <Label htmlFor="whatsapp_business_id">WhatsApp Business ID</Label>
                                                     <Input
                                                         id="whatsapp_business_id"
                                                         value={data.whatsapp_business_id}
                                                          onChange={(e) => handleWhatsAppChange('whatsapp_business_id', e.target.value)}
                                                         placeholder="123456789012345"
                                                     />
                                                 </div>
                                                 <div className="space-y-1.5">
                                                     <Label htmlFor="whatsapp_api_version">WhatsApp API Version</Label>
                                                     <Input
                                                         id="whatsapp_api_version"
                                                         value={data.whatsapp_api_version}
                                                          onChange={(e) => handleWhatsAppChange('whatsapp_api_version', e.target.value)}
                                                         placeholder="v22.0"
                                                     />
                                                 </div>
                                                 <div className="flex gap-2">
                                                     <Button
                                                         type="button"
                                                         variant="outline"
                                                         onClick={testWhatsAppConnection}
                                                         disabled={testingWhatsApp}
                                                         className="flex-1 gap-2"
                                                     >
                                                         {testingWhatsApp ? (
                                                             <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                         ) : (
                                                             <RefreshCw className="h-4 w-4" />
                                                         )}
                                                         {testingWhatsApp ? 'Probando...' : 'Probar Conexión'}
                                                     </Button>
                                                      {isWhatsAppTested && (
                                                         <Button
                                                             type="button"
                                                             variant="default"
                                                             className="gap-2"
                                                         >
                                                             <Check className="h-4 w-4" />
                                                             Conectado
                                                         </Button>
                                                     )}
                                                      <Button
                                                          type="button"
                                                          variant="outline"
                                                          onClick={openWhatsAppChat}
                                                          disabled={!isWhatsAppTested}
                                                          className="gap-2"
                                                      >
                                                           <Phone className="h-4 w-4" />
                                                           Abrir Chat
                                                       </Button>
                                                  </div>
                                                  <Button
                                                      type="button"
                                                      variant="default"
                                                      onClick={setWhatsAppWebhook}
                                                      disabled={settingWhatsAppWebhook}
                                                      className="w-full gap-2"
                                                  >
                                                      {settingWhatsAppWebhook ? (
                                                          <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                      ) : (
                                                          <LinkIcon className="h-4 w-4" />
                                                      )}
                                                      {settingWhatsAppWebhook ? 'Estableciendo webhook...' : 'Establecer Webhook Global'}
                                                  </Button>
                                              </CardContent>
                                         </Card>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeSection === 'n8n' && isN8nAllowed && (
                             <div className="grid gap-6 md:grid-cols-2">
                                 <Card className="md:col-span-2">
                                     <CardHeader>
                                         <CardTitle className="flex items-center gap-2">
                                             <span>⚡ n8n</span>
                                         </CardTitle>
                                         <CardDescription>
                                             Configuración global de n8n para automatización de reportes.
                                             Esta integración es utilizada por todos los negocios del sistema.
                                         </CardDescription>
                                     </CardHeader>
                                     <CardContent className="space-y-4">
                                         <div className="space-y-1.5">
                                             <Label htmlFor="n8n_base_url">Base URL *</Label>
                                             <Input
                                                 id="n8n_base_url"
                                                 value={n8nConfig.base_url}
                                                 onChange={(e) => setN8nConfig((prev) => ({ ...prev, base_url: e.target.value }))}
                                                 placeholder="https://n8n.tudominio.com"
                                             />
                                         </div>
                                         <div className="space-y-1.5">
                                             <Label htmlFor="n8n_webhook_url">Webhook URL</Label>
                                             <Input
                                                 id="n8n_webhook_url"
                                                 value={n8nConfig.webhook_url}
                                                 onChange={(e) => setN8nConfig((prev) => ({ ...prev, webhook_url: e.target.value }))}
                                                 placeholder="https://n8n.tudominio.com/webhook/reporte-diario"
                                             />
                                         </div>
                                         <div className="space-y-1.5">
                                             <Label htmlFor="n8n_telegram_proxy_url">Telegram Proxy URL</Label>
                                             <Input
                                                 id="n8n_telegram_proxy_url"
                                                 value={n8nConfig.telegram_proxy_url}
                                                 onChange={(e) => setN8nConfig((prev) => ({ ...prev, telegram_proxy_url: e.target.value }))}
                                                 placeholder="https://n8n.redcliente.cl/webhook/telegram-proxy"
                                             />
                                             <p className="text-xs text-muted-foreground">
                                                 URL del webhook de n8n para el proxy de Telegram. Se usa para notificar la configuración de canales.
                                             </p>
                                         </div>
                                         <div className="space-y-1.5">
                                             <Label htmlFor="n8n_api_key">API Key</Label>
                                             <Input
                                                 id="n8n_api_key"
                                                 type="password"
                                                 value={n8nConfig.api_key}
                                                 onChange={(e) => setN8nConfig((prev) => ({ ...prev, api_key: e.target.value }))}
                                                 placeholder={n8nLoaded && !n8nConfig.api_key ? '•••••••••••••••• (ya configurada)' : 'Ingresa la API Key'}
                                             />
<p className="text-xs text-muted-foreground">
                                                  La API Key se almacena encriptada. Si se deja vacía, se mantendrá la existente.
                                              </p>
                                          </div>
                                          <div className="flex items-center gap-2">
                                             <Switch
                                                 id="n8n_is_active"
                                                 checked={n8nConfig.is_active}
                                                 onCheckedChange={(checked) => setN8nConfig((prev) => ({ ...prev, is_active: checked }))}
                                             />
                                             <Label htmlFor="n8n_is_active">Activo</Label>
                                         </div>
                                         <div className="flex gap-3">
                                             <Button
                                                 type="button"
                                                 onClick={async () => {
                                                     setSavingN8n(true);
                                                     try {
await axios.put('/api/system-integrations/n8n', {
                                                              base_url: n8nConfig.base_url || null,
                                                              webhook_url: n8nConfig.webhook_url || null,
                                                              telegram_proxy_url: n8nConfig.telegram_proxy_url || null,
                                                              api_key: n8nConfig.api_key || undefined,
                                                              is_active: n8nConfig.is_active,
                                                          });
                                                         toast.success('Configuración guardada correctamente');
                                                     } catch {
                                                         toast.error('Error al guardar la configuración');
                                                     } finally {
                                                         setSavingN8n(false);
                                                     }
                                                 }}
                                                 disabled={savingN8n}
                                                 className="gap-2"
                                             >
                                                 {savingN8n ? (
                                                     <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                 ) : null}
                                                 {savingN8n ? 'Guardando...' : 'Guardar Configuración'}
                                             </Button>
                                             <Button
                                                 type="button"
                                                 variant="outline"
                                                 onClick={async () => {
                                                     setTestingN8n(true);
                                                     try {
                                                         const res = await axios.post('/api/system-integrations/n8n/test');
                                                         if (res.data.success) {
                                                             toast.success(res.data.message || 'Conexión exitosa');
                                                         } else {
                                                             toast.error(res.data.message || 'No se pudo conectar');
                                                         }
                                                     } catch (error: any) {
                                                         toast.error(error.response?.data?.message || 'Error al probar la conexión');
                                                     } finally {
                                                         setTestingN8n(false);
                                                     }
                                                 }}
                                                 disabled={testingN8n}
                                                 className="gap-2"
                                             >
                                                 {testingN8n ? (
                                                     <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                 ) : (
                                                     <RefreshCw className="h-4 w-4" />
                                                 )}
{testingN8n ? 'Probando conexión...' : 'Probar Conexión n8n'}
                                              </Button>
                                          </div>
                                      </CardContent>
                                  </Card>
                             </div>
                         )}

                        {activeSection === 'countries' && (
                            <CountriesTab countries={countries || []} />
                        )}

                        {activeSection === 'logs' && (
                            <div className="grid gap-6 lg:grid-cols-4">
                                <div className="lg:col-span-3">
                                    <Card className="overflow-hidden border-border/50 shadow-sm">
                                        <CardHeader className="border-b border-border/50 bg-gradient-to-r from-muted/50 to-muted/20 pb-4">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                                        <span className="text-lg">
                                                            📋
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <CardTitle className="text-lg">
                                                            Registro de Logs
                                                        </CardTitle>
                                                        <CardDescription>
                                                            Últimos 100 eventos
                                                            del sistema
                                                        </CardDescription>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <div className="flex items-center gap-1 rounded-lg border p-0.5">
                                                        <Button
                                                            variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                                                            size="sm"
                                                            onClick={() => setViewMode('table')}
                                                            className="h-8 w-8 p-0"
                                                        >
                                                            <List className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant={viewMode === 'cards' ? 'secondary' : 'ghost'}
                                                            size="sm"
                                                            onClick={() => setViewMode('cards')}
                                                            className="h-8 w-8 p-0"
                                                        >
                                                            <LayoutGrid className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="gap-2"
                                                        onClick={() =>
                                                            window.location.reload()
                                                        }
                                                    >
                                                        <RefreshCw className="h-4 w-4" />
                                                        Actualizar
                                                    </Button>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="p-0">
                                            {!logs || logs.length === 0 ? (
                                                <div className="flex flex-col items-center justify-center p-12 text-center">
                                                    <div className="mb-4 rounded-full bg-green-100 p-4 dark:bg-green-900/30">
                                                        <Check className="h-8 w-8 text-green-600 dark:text-green-400" />
                                                    </div>
                                                    <p className="mb-1 text-lg font-medium">
                                                        Sin registros
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        No hay eventos en el log
                                                        del sistema
                                                    </p>
                                                </div>
                                            ) : viewMode === 'table' ? (
                                                <div className="max-h-[600px] overflow-auto">
                                                    <table className="w-full text-sm">
                                                        <thead className="bg-muted/50 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                                            <tr>
                                                                <th className="px-4 py-3 text-left">Nivel</th>
                                                                <th className="px-4 py-3 text-left">Entorno</th>
                                                                <th className="px-4 py-3 text-left">Fecha</th>
                                                                <th className="px-4 py-3 text-left">Mensaje</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y divide-border/30">
                                                            {logs.map((log: any, index: number) => (
                                                                <tr key={index} className="group transition-colors hover:bg-muted/30">
                                                                    <td className="px-4 py-3">
                                                                        <span className={`inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-bold tracking-wider uppercase shadow-sm ${log.level === 'ERROR' || log.level === 'EMERGENCY' || log.level === 'CRITICAL' || log.level === 'ALERT' ? 'border border-red-200 bg-red-100 text-red-700' : log.level === 'WARNING' || log.level === 'NOTICE' ? 'border border-amber-200 bg-amber-100 text-amber-700' : log.level === 'DEBUG' ? 'border border-gray-200 bg-gray-100 text-gray-700' : 'border border-blue-200 bg-blue-100 text-blue-700'}`}>
                                                                            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-current" />
                                                                            {log.level}
                                                                        </span>
                                                                    </td>
                                                                    <td className="px-4 py-3">
                                                                        <span className="rounded-md border border-border bg-muted/50 px-2 py-0.5 text-[11px] font-medium text-muted-foreground">{log.environment}</span>
                                                                    </td>
                                                                    <td className="px-4 py-3 font-mono text-[11px] text-muted-foreground">{log.date}</td>
                                                                    <td className="px-4 py-3">
                                                                        <p className="font-mono text-xs leading-relaxed break-words whitespace-pre-wrap text-foreground/80 line-clamp-2">{log.message}</p>
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            ) : (
                                                <div className="max-h-[600px] overflow-y-auto">
                                                    <div className="divide-y divide-border/30">
                                                        {logs.map(
                                                            (
                                                                log: any,
                                                                index: number,
                                                            ) => (
                                                                <div
                                                                    key={index}
                                                                    className="group p-4 transition-all hover:bg-muted/30"
                                                                >
                                                                    <div className="flex items-start justify-between gap-4">
                                                                        <div className="flex flex-wrap items-center gap-2">
                                                                            <span
                                                                                className={`inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-bold tracking-wider uppercase shadow-sm ${log.level ===
                                                                                        'ERROR' ||
                                                                                        log.level ===
                                                                                        'EMERGENCY' ||
                                                                                        log.level ===
                                                                                        'CRITICAL' ||
                                                                                        log.level ===
                                                                                        'ALERT'
                                                                                        ? 'border border-red-200 bg-red-100 text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400'
                                                                                        : log.level ===
                                                                                            'WARNING' ||
                                                                                            log.level ===
                                                                                            'NOTICE'
                                                                                            ? 'border border-amber-200 bg-amber-100 text-amber-700 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
                                                                                            : log.level ===
                                                                                                'DEBUG'
                                                                                                ? 'border border-gray-200 bg-gray-100 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400'
                                                                                                : 'border border-blue-200 bg-blue-100 text-blue-700 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
                                                                                    }`}
                                                                            >
                                                                                <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-current" />
                                                                                {
                                                                                    log.level
                                                                                }
                                                                            </span>
                                                                            <span className="rounded-md border border-border bg-muted/50 px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                                                                                {
                                                                                    log.environment
                                                                                }
                                                                            </span>
                                                                        </div>
                                                                        <div className="flex items-center gap-2 font-mono text-[11px] text-muted-foreground/70">
                                                                            <span className="hidden sm:inline">
                                                                                <span className="mr-1">
                                                                                    🕒
                                                                                </span>
                                                                                {
                                                                                    log.date
                                                                                }
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                    <div className="mt-3 rounded-lg border-l-2 border-muted-foreground/20 bg-muted/30 p-3">
                                                                        <p className="font-mono text-xs leading-relaxed break-words whitespace-pre-wrap text-foreground/80">
                                                                            {
                                                                                log.message
                                                                            }
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>
                                <div className="lg:col-span-1">
                                    <Card className="sticky top-6 overflow-hidden border-border/50 shadow-sm">
                                        <CardHeader className="border-b border-border/50 bg-gradient-to-r from-muted/50 to-muted/20 pb-4">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-green-500/10 text-green-600">
                                                    <span className="text-lg">
                                                        👥
                                                    </span>
                                                </div>
                                                <div>
                                                    <CardTitle className="text-base">
                                                        En Línea
                                                    </CardTitle>
                                                    <CardDescription>
                                                        {onlineUsers.length}{' '}
                                                        usuario
                                                        {onlineUsers.length !==
                                                            1
                                                            ? 's'
                                                            : ''}{' '}
                                                        activo
                                                        {onlineUsers.length !==
                                                            1
                                                            ? 's'
                                                            : ''}
                                                    </CardDescription>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="p-3">
                                            {!onlineUsers ||
                                                onlineUsers.length === 0 ? (
                                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                                    <div className="mb-3 rounded-full bg-muted p-3">
                                                        <Users className="h-5 w-5 text-muted-foreground" />
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        Sin usuarios en línea
                                                    </p>
                                                </div>
                                            ) : (
                                                <div className="space-y-2">
                                                    {onlineUsers.map(
                                                        (user: any) => (
                                                            <div
                                                                key={user.id}
                                                                className="flex items-center gap-3 rounded-lg border bg-card p-2.5 transition-all hover:border-primary/20 hover:shadow-sm"
                                                            >
                                                                <div className="relative">
                                                                    <div className="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-primary/20 to-primary/10 text-sm font-bold text-primary">
                                                                        {user.name
                                                                            .charAt(
                                                                                0,
                                                                            )
                                                                            .toUpperCase()}
                                                                    </div>
                                                                    <span className="absolute -right-0.5 -bottom-0.5 h-3 w-3 animate-pulse rounded-full border-2 border-card bg-green-500" />
                                                                </div>
                                                                <div className="min-w-0 flex-1">
                                                                    <p className="truncate text-sm font-medium">
                                                                        {
                                                                            user.name
                                                                        }
                                                                    </p>
                                                                    <p className="truncate text-[10px] text-muted-foreground">
                                                                        {
                                                                            user.email
                                                                        }
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>
                            </div>
                        )}
                    </form>
                </div>
            </AppLayout>
        </>
    );
}
