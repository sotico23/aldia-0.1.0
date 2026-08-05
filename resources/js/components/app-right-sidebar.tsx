import { Link, router, usePage } from '@inertiajs/react';
import {
    LogOut,
    Bell,
    MessageSquare,
    User,
    Key,
    Smartphone,
    Palette,
    Store,
    ShoppingCart as CartIcon,
    Users,
    Heart,
    ThumbsUp,
    X,
    ChevronDown,
    ChevronRight,
    PieChart,
    FileText,
    Plus,
    FileCode,
    Settings2,
    Globe,
    AlertCircle,
    ShieldCheck,
    RefreshCw,
    Package,
    MessageCircle,
    Trash2,
    Award,
    Share2,
    Bot,
    Crown,
    KeyRound,
    Tag,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { usePermissions } from '@/hooks/use-permissions';
import { useCountry } from '@/hooks/use-country';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { show as showMarketplace } from '@/routes/marketplace';
import { edit } from '@/routes/profile';
import { edit as editPublicProfile } from '@/routes/public-profile';
import { show as showTwoFactor } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';

interface Conversacion {
    usuario_id: number;
    usuario_nombre: string;
    ultimo_mensaje: string;
    fecha_ultimo: string;
    sin_leer: number;
}

export function AppRightSidebar() {
    const { code: countryCode, currency } = useCountry();
    const { auth } = usePage().props as any;
    const { hasPermission } = usePermissions();

    const settingsNav = [
        {
            title: 'Perfil',
            href: edit(),
            icon: User,
        },
        {
            title: 'Contraseña',
            href: editPassword(),
            icon: Key,
        },
        {
            title: 'Autenticación',
            href: showTwoFactor(),
            icon: Smartphone,
        },
        {
            title: 'Apariencia',
            href: editAppearance(),
            icon: Palette,
        },
        {
            title: 'Notificaciones',
            href: '/settings/notifications',
            icon: Bell,
        },
    ];
    const [conversaciones, setConversaciones] = useState<Conversacion[]>([]);
    const [totalSinLeer, setTotalSinLeer] = useState(0);
    const [siiMenuOpen, setSiiMenuOpen] = useState(false);
    const [socialMenuOpen, setSocialMenuOpen] = useState(false);
    const [marketplaceMenuOpen, setMarketplaceMenuOpen] = useState(false);
    const [referralMenuOpen, setReferralMenuOpen] = useState(false);
    const [deletingIds, setDeletingIds] = useState<Set<string>>(new Set());

    const fetchNotificaciones = async () => {
        try {
            const res = await fetch('/mensajes', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const text = await res.text();
            try {
                const data = JSON.parse(text);
                setConversaciones(data.conversaciones?.slice(0, 5) || []);
                setTotalSinLeer(data.total_sin_leer || 0);
            } catch {
                console.error(
                    'Error parsing JSON from /mensajes:',
                    text.substring(0, 100),
                );
            }
        } catch (e) {
            console.error(e);
        }
    };

    const eliminarNotificacion = async (notificationId: string) => {
        try {
            const csrfToken =
                document.head
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') || '';
            const resp = await fetch(`/notifications/${notificationId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
            });
            if (resp.ok) {
                fetchNotificaciones();
                setDeletingIds((prev) => {
                    const next = new Set(prev);
                    next.delete(notificationId);
                    return next;
                });
                router.reload({ only: ['auth'] });
            } else {
                setDeletingIds((prev) => {
                    const next = new Set(prev);
                    next.delete(notificationId);
                    return next;
                });
                console.error(
                    'Error deleting:',
                    resp.status,
                    await resp.text(),
                );
                alert('Error al eliminar notificación: ' + resp.status);
            }
        } catch (e) {
            setDeletingIds((prev) => {
                const next = new Set(prev);
                next.delete(notificationId);
                return next;
            });
            console.error('Error eliminando notificacion:', e);
            alert('Error de conexión');
        }
    };

    const eliminarConAnimacion = (notificationId: string) => {
        setDeletingIds((prev) => new Set(prev).add(notificationId));
        setTimeout(() => {
            eliminarNotificacion(notificationId);
        }, 300);
    };

    // Auto-borrar notificaciones después de 5 minutos
    useEffect(() => {
        const autoDeleteTimeout = setTimeout(
            () => {
                const markAllAsRead = async () => {
                    try {
                        const csrfToken =
                            document.head
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '';
                        await fetch('/notifications/mark-as-read', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrfToken },
                        });
                        router.reload({ only: ['auth'] });
                    } catch (e) {
                        console.error('Error auto marking as read:', e);
                    }
                };
                markAllAsRead();
            },
            5 * 60 * 1000,
        ); // 5 minutos

        return () => clearTimeout(autoDeleteTimeout);
    }, []);

    const formatFecha = (fecha: string) => {
        const d = new Date(fecha);
        const now = new Date();
        const diff = now.getTime() - d.getTime();
        const min = Math.floor(diff / 60000);
        if (min < 1) return 'Ahora';
        if (min < 60) return `${min} min`;
        const hours = Math.floor(min / 60);
        if (hours < 24) return `${hours}h`;
        return d.toLocaleDateString(currency.locale);
    };

    const handleLogout = () => {
        router.flushAll();
    };

    return (
        <Sidebar side="right" variant="inset">
            <SidebarContent className="gap-4">
                <SidebarGroup className="tour-profile px-2 py-0">
                    <SidebarGroupLabel>Usuario</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <div className="rounded-lg bg-sidebar-accent/50 p-3">
                            <UserInfo user={auth.user} showEmail={true} />
                            <div className="mt-3">
                                <Link
                                    href={editPublicProfile()}
                                    className="flex items-center gap-1 text-xs text-primary hover:underline"
                                >
                                    <Store className="h-3 w-3" />
                                    Configurar Tienda
                                </Link>
                            </div>
                        </div>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarSeparator />

                {(hasPermission('finanzas.sii.viewAny') || auth.user.permissions?.some((p: string) => p.startsWith('finanzas.'))) && (
                    <Collapsible
                        className="mb-2 px-2"
                        open={siiMenuOpen}
                        onOpenChange={setSiiMenuOpen}
                    >
                        <CollapsibleTrigger className="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-sm font-medium hover:bg-sidebar-accent">
                            <span className="flex items-center gap-2">
                                <ShieldCheck className="h-4 w-4 text-primary" />
                                Gestión SII (DTE)
                            </span>
                            {siiMenuOpen ? (
                                <ChevronDown className="h-4 w-4" />
                            ) : (
                                <ChevronRight className="h-4 w-4" />
                            )}
                        </CollapsibleTrigger>
                        <CollapsibleContent className="space-y-1 pt-2">
                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: 'Panel General SII' }}
                            >
                                <Link href="/sii" prefetch>
                                    <PieChart className="h-4 w-4" />
                                    <span>Panel General DTE</span>
                                </Link>
                            </SidebarMenuButton>

                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: 'Documentos Emitidos' }}
                            >
                                <Link href="/sii/documentos" prefetch>
                                    <FileText className="h-4 w-4" />
                                    <span>Documentos Emitidos</span>
                                </Link>
                            </SidebarMenuButton>

                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: 'Cargar Folios (CAF)' }}
                            >
                                <Link href="/sii/caf/subir" prefetch>
                                    <Plus className="h-4 w-4" />
                                    <span>Subir Archivo CAF</span>
                                </Link>
                            </SidebarMenuButton>

                            <div className="my-2 border-t border-zinc-100 dark:border-zinc-800" />

                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: 'Certificado Digital' }}
                            >
                                <Link
                                    href="/sii/configuracion/certificado"
                                    prefetch
                                >
                                    <Key className="h-4 w-4" />
                                    <span>Certificado Digital</span>
                                </Link>
                            </SidebarMenuButton>

                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: 'Datos del Emisor' }}
                            >
                                <Link href="/sii/configuracion/emisor" prefetch>
                                    <Settings2 className="h-4 w-4" />
                                    <span>Datos del Emisor</span>
                                </Link>
                            </SidebarMenuButton>

                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: 'Stock de Folios' }}
                            >
                                <Link
                                    href="/sii/configuracion/folios"
                                    prefetch
                                    className="flex w-full items-center justify-between"
                                >
                                    <div className="flex items-center gap-2">
                                        <FileCode className="h-4 w-4" />
                                        <span>Stock de Folios</span>
                                    </div>
                                    <AlertCircle className="hidden h-3 w-3 text-rose-500" />
                                </Link>
                            </SidebarMenuButton>

                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: 'Ambiente de Trabajo' }}
                            >
                                <Link
                                    href="/sii/configuracion/ambiente"
                                    prefetch
                                >
                                    <Globe className="h-4 w-4" />
                                    <span>Ambiente Trabajo</span>
                                </Link>
                            </SidebarMenuButton>
                        </CollapsibleContent>
                    </Collapsible>
                )}

                <Collapsible
                    className="mb-2 px-2"
                    open={socialMenuOpen}
                    onOpenChange={setSocialMenuOpen}
                >
                    <CollapsibleTrigger className="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-sm font-medium hover:bg-sidebar-accent">
                        <span className="flex items-center gap-2">
                            <Users className="h-4 w-4" />
                            Perfil Social
                        </span>
                        {socialMenuOpen ? (
                            <ChevronDown className="h-4 w-4" />
                        ) : (
                            <ChevronRight className="h-4 w-4" />
                        )}
                    </CollapsibleTrigger>
                    <CollapsibleContent className="space-y-1 pt-2">
                        {settingsNav.map((item) => (
                            <SidebarMenuButton
                                key={item.title}
                                asChild
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href} prefetch>
                                    <item.icon className="h-4 w-4" />
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        ))}

                        <SidebarMenuButton
                            asChild
                            tooltip={{
                                children: 'Publicaciones de la Comunidad',
                            }}
                        >
                            <Link href="/comunidad" prefetch>
                                <Users className="h-4 w-4" />
                                <span>Publicaciones de la Comunidad</span>
                            </Link>
                        </SidebarMenuButton>
                    </CollapsibleContent>
                </Collapsible>

                <Collapsible
                    className="tour-marketplace px-2"
                    open={marketplaceMenuOpen}
                    onOpenChange={setMarketplaceMenuOpen}
                >
                    <CollapsibleTrigger className="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-sm font-medium hover:bg-sidebar-accent">
                        <span className="flex items-center gap-2">
                            <CartIcon className="h-4 w-4" />
                            Marketplace
                        </span>
                        {marketplaceMenuOpen ? (
                            <ChevronDown className="h-4 w-4" />
                        ) : (
                            <ChevronRight className="h-4 w-4" />
                        )}
                    </CollapsibleTrigger>
                    <CollapsibleContent className="space-y-1 pt-2">
                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: 'Ver Marketplace' }}
                        >
                            <Link href="/tienda" prefetch>
                                <Store className="h-4 w-4" />
                                <span>Ver Marketplace</span>
                            </Link>
                        </SidebarMenuButton>

                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: 'Mis Pedidos' }}
                        >
                            <Link href="/mis-pedidos" prefetch>
                                <Package className="h-4 w-4" />
                                <span>Mis Pedidos</span>
                            </Link>
                        </SidebarMenuButton>

                        {hasPermission('comercial.oportunidades.viewAny') && (
                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: 'Mis Ventas' }}
                            >
                                <Link href="/pedidos-recibidos" prefetch>
                                    <CartIcon className="h-4 w-4" />
                                    <span>Mis Ventas</span>
                                </Link>
                            </SidebarMenuButton>
                        )}

                        {hasPermission('ventas.cupones.viewAny') && (
                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: 'Cupones de Descuento' }}
                            >
                                <Link href="/cupones" prefetch>
                                    <Tag className="h-4 w-4" />
                                    <span>Cupones de Descuento</span>
                                </Link>
                            </SidebarMenuButton>
                        )}

                        {auth.user.public_profile?.slug &&
                            auth.user.public_profile.is_active && (
                                <SidebarMenuButton
                                    asChild
                                    tooltip={{
                                        children: 'Ver mi Perfil Público',
                                    }}
                                >
                                    <Link
                                        href={showMarketplace({
                                            slug: auth.user.public_profile.slug,
                                        })}
                                        prefetch
                                    >
                                        <Store className="h-4 w-4" />
                                        <span>Ver mi Perfil Público</span>
                                    </Link>
                                </SidebarMenuButton>
                            )}
                    </CollapsibleContent>
                </Collapsible>

                <Collapsible
                    className="mb-2 px-2"
                    open={referralMenuOpen}
                    onOpenChange={setReferralMenuOpen}
                >
                    <CollapsibleTrigger className="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-sm font-medium hover:bg-sidebar-accent">
                        <span className="flex items-center gap-2">
                            <Award className="h-4 w-4 text-primary" />
                            Recompensas
                        </span>
                        {referralMenuOpen ? (
                            <ChevronDown className="h-4 w-4" />
                        ) : (
                            <ChevronRight className="h-4 w-4" />
                        )}
                    </CollapsibleTrigger>
                    <CollapsibleContent className="space-y-1 pt-2">
                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: 'Recomienda' }}
                        >
                            <Link href="/afiliados/recomendar" prefetch>
                                <Share2 className="h-4 w-4" />
                                <span>Recomienda</span>
                            </Link>
                        </SidebarMenuButton>

                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: 'Mis Afiliados' }}
                        >
                            <Link href="/afiliados/red" prefetch>
                                <Users className="h-4 w-4" />
                                <span>Mis Afiliados</span>
                            </Link>
                        </SidebarMenuButton>
                    </CollapsibleContent>
                </Collapsible>

                {hasPermission('sistema.automatizaciones.viewAny') && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton asChild tooltip={{ children: 'Automatiza tu negocio' }}>
                                    <Link href="/canales" prefetch>
                                        <Bot className="h-4 w-4" />
                                        <Crown className="h-3.5 w-3.5 text-amber-500" />
                                        <span>Automatiza tu negocio</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {hasPermission('sistema.integraciones.viewAny') && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton asChild tooltip={{ children: 'Conexiones API' }}>
                                    <Link href="/integraciones-api" prefetch>
                                        <KeyRound className="h-4 w-4" />
                                        <span>Conexiones API</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {hasPermission('comercial.oportunidades.viewAny') && (auth.user.pending_orders || 0) > 0 && (
                    <>
                        <SidebarSeparator />
                        <SidebarGroup className="px-2 py-0">
                            <SidebarGroupLabel>
                                <CartIcon className="mr-2 inline h-4 w-4 text-amber-500" />
                                Pedidos Pendientes
                                <span className="ml-2 rounded-full bg-amber-500 px-2 py-0.5 text-[10px] text-white">
                                    {auth.user.pending_orders}
                                </span>
                            </SidebarGroupLabel>
                            <SidebarGroupContent>
                                <div className="space-y-2">
                                    <Link
                                        href="/pedidos-recibidos"
                                        className="flex items-center gap-2 rounded-lg bg-amber-50 p-3 text-xs font-medium text-amber-700 transition-colors hover:bg-amber-100 dark:bg-amber-900/10 dark:text-amber-400"
                                    >
                                        <CartIcon className="h-4 w-4" />
                                        <span>
                                            Tienes {auth.user.pending_orders}{' '}
                                            pedidos nuevos en espera
                                        </span>
                                    </Link>

                                    {/* Notificaciones de Pedidos */}
                                    {(() => {
                                        const orderNotifs = (
                                            auth.user.recent_notifications || []
                                        ).filter((n: any) => {
                                            const data =
                                                typeof n.data === 'string'
                                                    ? JSON.parse(n.data)
                                                    : n.data || {};
                                            return (
                                                data.tipo === 'nuevo_pedido' ||
                                                data.tipo ===
                                                    'actualizacion_pedido'
                                            );
                                        });

                                        if (orderNotifs.length === 0) {
                                            return null;
                                        }

                                        return orderNotifs
                                            .slice(0, 3)
                                            .map((notification: any) => {
                                                const data =
                                                    typeof notification.data ===
                                                    'string'
                                                        ? JSON.parse(
                                                              notification.data,
                                                          )
                                                        : notification.data ||
                                                          {};
                                                return (
                                                    <Link
                                                        key={notification.id}
                                                        href={
                                                            data.pedido_id
                                                                ? `/pedidos/${data.pedido_id}`
                                                                : '/pedidos-recibidos'
                                                        }
                                                        className={cn(
                                                            'group relative block overflow-hidden rounded-lg border border-border/50 p-2 text-xs transition-colors hover:bg-sidebar-accent/50',
                                                            !notification.read_at &&
                                                                'border-primary/20 bg-primary/5',
                                                        )}
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            {data.tipo ===
                                                            'nuevo_pedido' ? (
                                                                <Package className="h-3 w-3 text-amber-500" />
                                                            ) : (
                                                                <RefreshCw className="h-3 w-3 text-blue-500" />
                                                            )}
                                                            <span className="truncate font-medium">
                                                                {data.titulo ||
                                                                    'Actualización de pedido'}
                                                            </span>
                                                            <button
                                                                onClick={(
                                                                    e,
                                                                ) => {
                                                                    e.preventDefault();
                                                                    e.stopPropagation();
                                                                    eliminarNotificacion(
                                                                        notification.id,
                                                                    );
                                                                }}
                                                                className="ml-auto text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-red-500"
                                                            >
                                                                <Trash2 className="h-3 w-3" />
                                                            </button>
                                                        </div>
                                                        <p className="mt-1 line-clamp-2 text-muted-foreground">
                                                            {data.message || ''}
                                                        </p>
                                                    </Link>
                                                );
                                            });
                                    })()}
                                </div>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    </>
                )}

                {/* Mensajes del Chat — always visible when user has chat notifications */}
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel className="flex items-center justify-between">
                        <div className="flex items-center">
                            <MessageSquare className="mr-2 inline h-4 w-4" />
                            Mensajes del Chat
                        </div>
                        {(() => {
                            const chatNotifs = (
                                auth.user.recent_notifications || []
                            ).filter((n: any) => {
                                const data =
                                    typeof n.data === 'string'
                                        ? JSON.parse(n.data)
                                        : n.data || {};
                                return (
                                    data.tipo === 'mensaje_chat_pedido' ||
                                    data.tipo === 'mensaje_chat'
                                );
                            });
                            const unreadCount = chatNotifs.filter(
                                (n: any) => !n.read_at,
                            ).length;
                            return (
                                unreadCount > 0 && (
                                    <span className="rounded-full bg-green-500 px-2 py-0.5 text-[10px] text-white">
                                        {unreadCount}
                                    </span>
                                )
                            );
                        })()}
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <div className="space-y-2">
                            {(() => {
                                const chatNotifs = (
                                    auth.user.recent_notifications || []
                                ).filter((n: any) => {
                                    const data =
                                        typeof n.data === 'string'
                                            ? JSON.parse(n.data)
                                            : n.data || {};
                                    return (
                                        data.tipo ===
                                            'mensaje_chat_pedido' ||
                                        data.tipo === 'mensaje_chat'
                                    );
                                });

                                if (chatNotifs.length === 0) {
                                    return (
                                        <div className="py-2 text-center text-xs text-muted-foreground">
                                            No hay mensajes nuevos
                                        </div>
                                    );
                                }

                                return chatNotifs
                                    .slice(0, 5)
                                    .map((notification: any) => {
                                        const data =
                                            typeof notification.data ===
                                            'string'
                                                ? JSON.parse(
                                                      notification.data,
                                                  )
                                                : notification.data ||
                                                  {};
                                        return (
                                            <Link
                                                key={notification.id}
                                                href={
                                                    data.link ||
                                                    (data.conversacion_id
                                                        ? `/conversaciones-pedidos/${data.conversacion_id}/chat`
                                                        : data.conversation_id
                                                          ? `/chat/${data.conversation_id}`
                                                          : '/mis-pedidos')
                                                }
                                                className={cn(
                                                    'group relative block overflow-hidden rounded-lg border border-border/50 p-2 text-xs transition-colors hover:bg-sidebar-accent/50',
                                                    !notification.read_at &&
                                                        'border-green-500/20 bg-green-500/5',
                                                )}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <MessageCircle className="h-3 w-3 text-green-500" />
                                                    <span className="truncate font-medium">
                                                        {data.titulo ||
                                                            'Nuevo mensaje'}
                                                    </span>
                                                    <button
                                                        onClick={(
                                                            e,
                                                        ) => {
                                                            e.preventDefault();
                                                            e.stopPropagation();
                                                            eliminarNotificacion(
                                                                notification.id,
                                                            );
                                                        }}
                                                        className="ml-auto text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-red-500"
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                    </button>
                                                </div>
                                                <p className="mt-1 line-clamp-2 text-muted-foreground">
                                                    {data.message || ''}
                                                </p>
                                            </Link>
                                        );
                                    });
                            })()}
                        </div>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarSeparator />

                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel className="flex items-center justify-between">
                        <div className="flex items-center">
                            <Bell className="mr-2 inline h-4 w-4" />
                            Notificaciones sociales
                        </div>
                        {(() => {
                            const socialNotifs = (
                                auth.user.recent_notifications || []
                            ).filter((n: any) => {
                                const data =
                                    typeof n.data === 'string'
                                        ? JSON.parse(n.data)
                                        : n.data || {};
                                return (
                                    data.tipo !== 'nuevo_pedido' &&
                                    data.tipo !== 'actualizacion_pedido' &&
                                    data.tipo !== 'mensaje_chat_pedido' &&
                                    data.tipo !== 'mensaje_chat'
                                );
                            });
                            const unreadCount = socialNotifs.filter(
                                (n: any) => !n.read_at,
                            ).length;
                            return (
                                unreadCount > 0 && (
                                    <span className="rounded-full bg-red-500 px-2 py-0.5 text-[10px] text-white">
                                        {unreadCount}
                                    </span>
                                )
                            );
                        })()}
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <div className="space-y-2">
                            {(() => {
                                const socialNotifs = (
                                    auth.user.recent_notifications || []
                                ).filter((n: any) => {
                                    const data =
                                        typeof n.data === 'string'
                                            ? JSON.parse(n.data)
                                            : n.data || {};
                                    return (
                                        data.tipo !== 'nuevo_pedido' &&
                                        data.tipo !== 'actualizacion_pedido' &&
                                        data.tipo !== 'mensaje_chat_pedido' &&
                                        data.tipo !== 'mensaje_chat'
                                    );
                                });

                                if (socialNotifs.length === 0) {
                                    return (
                                        <div className="py-4 text-center text-xs text-muted-foreground">
                                            No tienes notificaciones
                                        </div>
                                    );
                                }

                                return (
                                    <>
                                        {socialNotifs
                                            .slice(0, 5)
                                            .map((notification: any) => {
                                                const data =
                                                    typeof notification.data ===
                                                    'string'
                                                        ? JSON.parse(
                                                              notification.data,
                                                          )
                                                        : notification.data ||
                                                          {};

                                                return (
                                                    <div
                                                        key={notification.id}
                                                        className={cn(
                                                            'group relative overflow-hidden rounded-lg border border-border/50 text-xs transition-all duration-300 ease-in-out',
                                                            deletingIds.has(
                                                                notification.id,
                                                            ) && 'opacity-0 scale-95',
                                                            !notification.read_at &&
                                                                'border-primary/20 bg-primary/5',
                                                        )}
                                                    >
                                                        {/* Main notification link - absolute background */}
                                                        <Link
                                                            href={
                                                                data.link ||
                                                                '/comunidad'
                                                            }
                                                            className="absolute inset-0 z-0"
                                                        />

                                                        {/* Foreground content with interaction areas */}
                                                        <div className="pointer-events-none relative z-10 flex items-start gap-2 p-2">
                                                            <div className="mt-0.5 shrink-0">
                                                                {data.type ===
                                                                    'like' && (
                                                                    <ThumbsUp className="h-3 w-3 text-primary" />
                                                                )}
                                                                {data.type ===
                                                                    'heart' && (
                                                                    <Heart className="h-3 w-3 fill-rose-500 text-rose-500" />
                                                                )}
                                                                {data.tipo ===
                                                                    'comentario' && (
                                                                    <MessageSquare className="h-3 w-3 text-violet-500" />
                                                                )}
                                                                {data.tipo !== 'comentario' &&
                                                                    data.type !== 'like' &&
                                                                    data.type !== 'heart' && (
                                                                    <MessageSquare className="h-3 w-3 text-violet-500" />
                                                                )}
                                                            </div>
                                                            <div className="min-w-0 flex-1">
                                                                <p className="text-[11px] leading-snug">
                                                                    {data.user_id ? (
                                                                        <Link
                                                                            href={`/perfil/${data.user_id}`}
                                                                            className="pointer-events-auto font-semibold hover:text-primary hover:underline"
                                                                            onClick={(
                                                                                e,
                                                                            ) =>
                                                                                e.stopPropagation()
                                                                            }
                                                                        >
                                                                            {
                                                                                data.user_name
                                                                            }
                                                                        </Link>
                                                                    ) : (
                                                                        <span className="font-semibold">
                                                                            {data.user_name ||
                                                                                'Usuario'}
                                                                        </span>
                                                                    )}{' '}
                                                                {data.message || ''}
                                                                </p>
                                                                <span className="text-[9px] text-muted-foreground opacity-70">
                                                                    {formatFecha(
                                                                        notification.created_at,
                                                                    )}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <button
                                                            onClick={(e) => {
                                                                e.preventDefault();
                                                                e.stopPropagation();
                                                                eliminarConAnimacion(notification.id);
                                                            }}
                                                            className="absolute top-1 right-1 z-20 rounded-full p-1 text-muted-foreground opacity-0 transition-colors group-hover:opacity-100 hover:bg-red-100 hover:text-red-600"
                                                            title="Eliminar notificación"
                                                        >
                                                            <X className="h-3 w-3" />
                                                        </button>
                                                    </div>
                                                );
                                            })}
                                        {(auth.user.unread_notifications || 0) >
                                            0 && (
                                            <button
                                                onClick={() =>
                                                    router.post(
                                                        '/notifications/mark-as-read',
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                className="mt-1 w-full text-center text-[10px] text-primary hover:underline"
                                            >
                                                Marcar todas como leídas
                                            </button>
                                        )}
                                    </>
                                );
                            })()}
                        </div>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarSeparator />

                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>
                        <MessageSquare className="mr-2 inline h-4 w-4" />
                        Conversaciones
                        {totalSinLeer > 0 && (
                            <span className="ml-2 rounded-full bg-red-500 px-2 py-0.5 text-xs text-white">
                                {totalSinLeer}
                            </span>
                        )}
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <div className="space-y-2">
                            {conversaciones.length === 0 ? (
                                <div className="py-2 text-center text-xs text-muted-foreground italic">
                                    Sin conversaciones activas
                                </div>
                            ) : (
                                conversaciones.map((conv) => (
                                    <div
                                        key={conv.usuario_id}
                                        className="cursor-pointer rounded-lg bg-sidebar-accent/30 p-2.5 transition-colors hover:bg-sidebar-accent/50"
                                    >
                                        <div className="flex items-start gap-2">
                                            <MessageSquare className="mt-0.5 h-3.5 w-3.5 text-muted-foreground" />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center justify-between">
                                                    <span className="truncate text-[11px] font-medium">
                                                        {conv.usuario_nombre}
                                                    </span>
                                                    {conv.sin_leer > 0 && (
                                                        <span className="h-2 w-2 rounded-full bg-primary" />
                                                    )}
                                                </div>
                                                <p className="truncate text-[10px] leading-tight text-muted-foreground">
                                                    {conv.ultimo_mensaje}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                        <Link
                            href="/chat"
                            className="mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg bg-sidebar-accent px-3 py-1.5 text-xs font-medium transition-colors hover:bg-sidebar-accent/80"
                        >
                            <MessageSquare className="h-3.5 w-3.5" />
                            Ir al Chat
                        </Link>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarSeparator />

                <SidebarGroup className="px-2 py-0">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: 'Cerrar sesión' }}
                            >
                                <Link
                                    href={logout()}
                                    as="button"
                                    onClick={handleLogout}
                                >
                                    <LogOut className="h-4 w-4" />
                                    <span>Cerrar sesión</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>
        </Sidebar>
    );
}
