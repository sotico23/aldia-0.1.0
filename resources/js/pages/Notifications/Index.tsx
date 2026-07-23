import { Head, Link, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { AlertTriangle, Bell, ChevronLeft, ChevronRight, Clock, Heart, MessageSquare, Package, Tag, ThumbsUp, Trash2, User as UserIcon } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

interface Notification {
    id: string;
    type: string;
    data: {
        user_id?: number;
        user_name?: string;
        user_avatar?: string;
        message?: string;
        link?: string;
        tipo?: string;
        type?: string;
    };
    read_at: string | null;
    created_at: string;
}

interface PaginatedData {
    data: Notification[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export default function NotificationsIndex({ notifications }: { notifications: PaginatedData }) {
    const eliminarNotificacion = async (notificationId: string) => {
        const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const resp = await fetch(`/notifications/${notificationId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
        });
        if (resp.ok) {
            router.reload({ only: ['auth', 'notifications'] } as any);
        }
    };

    const markAllAsRead = () => {
        router.post('/notifications/mark-as-read', {} as any, { preserveScroll: true, only: ['auth', 'notifications'] });
    };

    const getIcon = (notif: Notification) => {
        const data = typeof notif.data === 'string' ? JSON.parse(notif.data) : notif.data || {};
        if (data.tipo === 'nuevo_pedido' || data.tipo === 'actualizacion_pedido') return <Bell className="h-4 w-4 text-amber-500" />;
        if (data.tipo === 'mensaje_chat_pedido') return <MessageSquare className="h-4 w-4 text-green-500" />;
        if (data.tipo === 'trial_expiry' || data.tipo === 'trial_warning') return <AlertTriangle className="h-4 w-4 text-red-500" />;
        if (data.tipo === 'stock_low') return <Package className="h-4 w-4 text-orange-500" />;
        if (data.tipo === 'cupon_limite') return <Tag className="h-4 w-4 text-purple-500" />;
        if (data.tipo === 'recordatorio_llamada') return <Clock className="h-4 w-4 text-blue-500" />;
        if (data.type === 'like') return <ThumbsUp className="h-4 w-4 text-primary" />;
        if (data.type === 'heart') return <Heart className="h-4 w-4 fill-rose-500 text-rose-500" />;
        return <Bell className="h-4 w-4 text-muted-foreground" />;
    };

    return (
        <>
            <Head title="Notificaciones" />

            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-8">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Bell className="h-6 w-6 text-primary" />
                        <h1 className="text-2xl font-bold tracking-tight">Notificaciones</h1>
                        <span className="rounded-full bg-muted px-2.5 py-0.5 text-xs text-muted-foreground">
                            {notifications.total} total
                        </span>
                    </div>
                    <Button variant="outline" size="sm" onClick={markAllAsRead}>
                        Marcar todo leído
                    </Button>
                </div>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base font-medium">Historial</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {notifications.data.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 py-16 text-center">
                                <Bell className="h-12 w-12 text-muted-foreground/40" />
                                <p className="text-sm text-muted-foreground">No tienes notificaciones</p>
                            </div>
                        ) : (
                            <div className="divide-y">
                                {notifications.data.map((notif) => {
                                    const data = typeof notif.data === 'string' ? JSON.parse(notif.data) : notif.data || {};
                                    return (
                                        <div
                                            key={notif.id}
                                            className={cn(
                                                'group relative flex items-start gap-3 px-4 py-3.5 transition-colors hover:bg-muted/50',
                                                !notif.read_at && 'bg-primary/[0.03]',
                                            )}
                                        >
                                            <Link
                                                href={data.link || '/comunidad'}
                                                className="absolute inset-0 z-0"
                                            />

                                            <Avatar className="relative z-10 h-9 w-9 shrink-0">
                                                <AvatarImage src={data.user_avatar} />
                                                <AvatarFallback>
                                                    <UserIcon className="h-4 w-4" />
                                                </AvatarFallback>
                                            </Avatar>

                                            <div className="relative z-10 min-w-0 flex-1">
                                                <div className="flex items-start gap-2">
                                                    <div className="mt-0.5 shrink-0">{getIcon(notif)}</div>
                                                    <div className="min-w-0 flex-1 space-y-0.5">
                                                        <p className="text-sm leading-snug">
                                                            {data.user_id ? (
                                                                <Link
                                                                    href={`/perfil/${data.user_id}`}
                                                                    className="font-semibold hover:text-primary hover:underline"
                                                                    onClick={(e) => e.stopPropagation()}
                                                                >
                                                                    {data.user_name}
                                                                </Link>
                                                            ) : (
                                                                <span className="font-semibold">{data.user_name || 'Usuario'}</span>
                                                            )}
                                                            {' '}
                                                            {data.message && data.user_name
                                                                ? (data.message.split(data.user_name)[1] || data.message)
                                                                : (data.message || '')}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {format(new Date(notif.created_at), "d 'de' MMM, HH:mm", { locale: es })}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            {!notif.read_at && (
                                                <div className="relative z-10 mt-2 h-2 w-2 shrink-0 rounded-full bg-primary" />
                                            )}

                                            <button
                                                onClick={(e) => {
                                                    e.preventDefault();
                                                    e.stopPropagation();
                                                    eliminarNotificacion(notif.id);
                                                }}
                                                className="relative z-20 shrink-0 rounded-full p-1.5 text-muted-foreground opacity-0 transition-colors group-hover:opacity-100 hover:bg-red-100 hover:text-red-600"
                                                title="Eliminar"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {notifications.last_page > 1 && (
                            <div className="flex items-center justify-between border-t px-4 py-3">
                                <p className="text-sm text-muted-foreground">
                                    Mostrando {notifications.from}-{notifications.to} de {notifications.total}
                                </p>
                                <div className="flex gap-1">
                                    {notifications.current_page > 1 && (
                                        <Link
                                            href={`/notifications?page=${notifications.current_page - 1}`}
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-md border hover:bg-muted"
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                        </Link>
                                    )}
                                    {notifications.current_page < notifications.last_page && (
                                        <Link
                                            href={`/notifications?page=${notifications.current_page + 1}`}
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-md border hover:bg-muted"
                                        >
                                            <ChevronRight className="h-4 w-4" />
                                        </Link>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
