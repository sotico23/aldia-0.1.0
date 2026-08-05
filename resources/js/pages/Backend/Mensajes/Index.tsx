import { Head, usePage } from '@inertiajs/react';
import { Send, X, Paperclip, FileText as FileIcon, Clock, AlertCircle, RefreshCw, LayoutGrid, List } from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import echo from '@/echo';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface User {
    id: number;
    name: string;
    email?: string;
    profile_photo_path: string | null;
}

interface Mensaje {
    id: number;
    sender_id: number;
    contenido: string;
    archivo_path: string | null;
    archivo_nombre: string | null;
    archivo_tipo: string | null;
    created_at: string;
    sender: User;
    receiver: User;
    leido: boolean;
    _temp?: boolean;
    _sending?: boolean;
    _error?: boolean;
    _tempId?: string;
}

interface Conversacion {
    usuario_id: number;
    usuario_nombre: string;
    usuario_foto: string | null;
    ultimo_mensaje: string;
    fecha_ultimo: string;
    sin_leer: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Mensajes', href: '/mensajes' },
];

export default function MensajesIndex() {
    const { code: countryCode, currency } = useCountry();
    const { hasPermission } = usePermissions();
    const { auth } = usePage().props as any;
    const [conversaciones, setConversaciones] = useState<Conversacion[]>([]);
    const [usuarios, setUsuarios] = useState<User[]>([]);
    const [mensajes, setMensajes] = useState<Mensaje[]>([]);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [vista, setVista] = useState<'lista' | 'chat' | 'nuevo'>('lista');
    const [nuevoMensaje, setNuevoMensaje] = useState('');
    const [archivo, setArchivo] = useState<File | null>(null);
    const fileInputRef = React.useRef<HTMLInputElement>(null);

    const canAccess = hasPermission('general.comunidad.edit');

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const [pollInterval, setPollInterval] = useState<number | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const fetchConversaciones = async () => {
        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');
            const res = await fetch('/mensajes', {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
            });
            const data = await res.json();
            setConversaciones(data.conversaciones || []);
        } catch (e) {
            console.error(e);
        }
    };

    const fetchUsuarios = async () => {
        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');
            const res = await fetch('/mensajes/usuarios', {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
            });
            const data = await res.json();
            setUsuarios(data.usuarios || []);
        } catch (e) {
            console.error(e);
        }
    };

    const mensajesRef = useRef(mensajes);

    useEffect(() => {
        mensajesRef.current = mensajes;
    }, [mensajes]);

    const fetchConversacion = async (usuarioId: number, isPolling = false) => {
        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');
            const res = await fetch(`/mensajes/${usuarioId}`, {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
            });
            const data = await res.json();
            
            // Only update messages if they changed or it's NOT a poll
            if (!isPolling || JSON.stringify(data.mensajes) !== JSON.stringify(mensajesRef.current)) {
                setMensajes(data.mensajes || []);
            }
            
            if (!isPolling) {
                setSelectedUser(data.otro_usuario);
                setVista('chat');
            }
        } catch (e) {
            console.error(e);
        }
    };

    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        fetchConversaciones();
        if (vista === 'nuevo') {
            fetchUsuarios();
        }
    }, [vista]);

    // Polling effect + Echo listen for internal messages
    useEffect(() => {
        const interval = window.setInterval(() => {
            fetchConversaciones();
            if (vista === 'chat' && selectedUser) {
                fetchConversacion(selectedUser.id, true);
            }
        }, 10000);

        let channel: any = null;
        let channelName = '';

        if (auth?.user?.id) {
            channelName = 'user.' + auth.user.id;
            channel = echo.private(channelName);

            const addMensaje = (e: any) => {
                if (vista !== 'chat' || !selectedUser) return;
                const msg = e.mensaje || e;
                const senderId = msg.sender_id ?? msg.sender?.id;
                if (!senderId) return;
                if (senderId !== selectedUser.id && senderId !== auth.user.id) return;

                const msgData: Mensaje = {
                    id: msg.id,
                    sender_id: senderId,
                    contenido: msg.contenido || msg.content || '',
                    archivo_path: msg.archivo_path || msg.file_path || null,
                    archivo_nombre: msg.archivo_nombre || msg.file_name || null,
                    archivo_tipo: msg.archivo_tipo || msg.file_type || null,
                    created_at: msg.created_at,
                    sender: msg.sender || { id: senderId, name: '', profile_photo_path: null },
                    receiver: msg.receiver || { id: msg.receiver_id ?? auth.user.id, name: '', profile_photo_path: null },
                    leido: msg.leido ?? !!msg.read_at,
                };

                setMensajes((prev) => {
                    if (prev.some((m) => m.id === msgData.id)) return prev;
                    const withoutTemp = prev.filter((m) => !m._temp);
                    return [...withoutTemp, msgData];
                });
            };

            channel.listen('.MensajeInternoEnviado', addMensaje);
        }

        return () => {
            window.clearInterval(interval);
            if (channel) {
                echo.leave(channelName);
            }
        };
    }, [vista, selectedUser, auth?.user?.id]);

    // Auto-scroll effect
    useEffect(() => {
        const chatContainer = document.getElementById('chat-messages');
        if (chatContainer) {
            chatContainer.scrollTo({
                top: chatContainer.scrollHeight,
                behavior: 'smooth',
            });
        }
    }, [mensajes]);

    const submitMensaje = async (content: string, file: File | null, tempId: string) => {
        const formData = new FormData();
        formData.append('receiver_id', selectedUser!.id.toString());
        formData.append('contenido', content);
        if (file) {
            formData.append('archivo', file);
        }

        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');
            const res = await fetch('/mensajes', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: formData,
            });

            if (res.ok) {
                setNuevoMensaje('');
                setArchivo(null);
                if (fileInputRef.current) fileInputRef.current.value = '';
                fetchConversacion(selectedUser!.id);
            } else {
                setMensajes((prev) =>
                    prev.map((m) =>
                        m._tempId === tempId
                            ? { ...m, _sending: false, _error: true }
                            : m,
                    ),
                );
            }
        } catch {
            setMensajes((prev) =>
                prev.map((m) =>
                    m._tempId === tempId
                        ? { ...m, _sending: false, _error: true }
                        : m,
                ),
            );
        }
    };

    const enviarMensaje = (e: React.FormEvent) => {
        e.preventDefault();
        if (!nuevoMensaje.trim() && !archivo || !selectedUser) return;

        const tempId = `temp-${Date.now()}`;
        const tempMsg: Mensaje = {
            id: -Date.now(),
            sender_id: auth.user.id,
            contenido: nuevoMensaje.trim() || '',
            archivo_path: null,
            archivo_nombre: archivo?.name || null,
            archivo_tipo: archivo?.type || null,
            created_at: new Date().toISOString(),
            sender: { id: auth.user.id, name: auth.user.name, profile_photo_path: auth.user.profile_photo_path },
            receiver: selectedUser,
            leido: true,
            _temp: true,
            _sending: true,
            _tempId: tempId,
        };

        setMensajes((prev) => [...prev, tempMsg]);
        const contentToSend = nuevoMensaje;
        setNuevoMensaje('');

        const fileToSend = archivo;
        setArchivo(null);
        if (fileInputRef.current) fileInputRef.current.value = '';

        submitMensaje(contentToSend, fileToSend, tempId);
    };

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

    const getFotoUrl = (foto: string | null) => {
        if (!foto) return null;
        if (foto.startsWith('http')) return foto;
        return `/storage/${foto}`;
    };

    if (!canAccess) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Mensajes" />
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">No tienes permiso para acceder a esta página.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mensajes" />
            <div className="flex flex-col gap-4 lg:flex-row lg:h-[calc(100vh-8rem)]">
                {/* Lista de conversaciones */}
                <div
                    className={`flex w-full flex-col rounded-lg border bg-card md:w-1/3 ${vista !== 'lista' ? 'hidden md:flex' : ''}`}
                >
                    <div className="flex items-center justify-between border-b p-4">
                        <h1 className="text-xl font-bold">Mensajes</h1>
                        <div className="flex items-center gap-1 rounded-lg border bg-background/50 p-0.5">
                            <Button
                                variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                                size="sm"
                                onClick={() => setViewMode('table')}
                                className="h-7 w-7 p-0"
                            >
                                <List className="h-3.5 w-3.5" />
                            </Button>
                            <Button
                                variant={viewMode === 'cards' ? 'secondary' : 'ghost'}
                                size="sm"
                                onClick={() => setViewMode('cards')}
                                className="h-7 w-7 p-0"
                            >
                                <LayoutGrid className="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                    <div className="p-2">
                        <Button
                            variant="outline"
                            className="w-full"
                            onClick={() => setVista('nuevo')}
                        >
                            <Send className="mr-2 h-4 w-4" />
                            Nuevo Mensaje
                        </Button>
                    </div>
                    <div className="flex-1 overflow-y-auto">
                        {viewMode === 'table' ? (
                            conversaciones.length === 0 ? (
                                <div className="p-4 text-center text-muted-foreground">
                                    No tienes conversaciones
                                </div>
                            ) : (
                                conversaciones.map((conv) => (
                                    <div
                                        key={conv.usuario_id}
                                        className="flex cursor-pointer items-center gap-3 rounded-lg p-3 transition-colors hover:bg-muted"
                                        onClick={() =>
                                            fetchConversacion(conv.usuario_id)
                                        }
                                    >
                                        <div className="relative">
                                            {getFotoUrl(conv.usuario_foto) ? (
                                                <img
                                                    src={
                                                        getFotoUrl(
                                                            conv.usuario_foto,
                                                        )!
                                                    }
                                                    alt={conv.usuario_nombre}
                                                    className="h-12 w-12 rounded-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary text-lg text-white">
                                                    {conv.usuario_nombre.charAt(0)}
                                                </div>
                                            )}
                                            {conv.sin_leer > 0 && (
                                                <span className="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs text-white">
                                                    {conv.sin_leer}
                                                </span>
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex justify-between">
                                                <span className="truncate font-medium">
                                                    {conv.usuario_nombre}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {formatFecha(conv.fecha_ultimo)}
                                                </span>
                                            </div>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {conv.ultimo_mensaje}
                                            </p>
                                        </div>
                                    </div>
                                ))
                            )
                        ) : (
                            conversaciones.length === 0 ? (
                                <div className="p-4 text-center text-muted-foreground">
                                    No tienes conversaciones
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-2 p-2 sm:grid-cols-2">
                                    {conversaciones.map((conv) => (
                                        <div
                                            key={conv.usuario_id}
                                            className="flex cursor-pointer items-center gap-3 rounded-lg border bg-card p-4 transition-colors hover:bg-muted"
                                            onClick={() =>
                                                fetchConversacion(conv.usuario_id)
                                            }
                                        >
                                            <div className="relative">
                                                {getFotoUrl(conv.usuario_foto) ? (
                                                    <img
                                                        src={getFotoUrl(conv.usuario_foto)!}
                                                        alt={conv.usuario_nombre}
                                                        className="h-12 w-12 rounded-full object-cover"
                                                    />
                                                ) : (
                                                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary text-lg text-white">
                                                        {conv.usuario_nombre.charAt(0)}
                                                    </div>
                                                )}
                                                {conv.sin_leer > 0 && (
                                                    <span className="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs text-white">
                                                        {conv.sin_leer}
                                                    </span>
                                                )}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="font-medium">
                                                    {conv.usuario_nombre}
                                                </div>
                                                <p className="truncate text-sm text-muted-foreground">
                                                    {conv.ultimo_mensaje}
                                                </p>
                                                <span className="text-xs text-muted-foreground">
                                                    {formatFecha(conv.fecha_ultimo)}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )
                        )}
                    </div>
                </div>

                {/* Vista de nuevo mensaje */}
                {vista === 'nuevo' && (
                    <div className="flex w-full flex-col rounded-lg border bg-card md:w-2/3">
                        <div className="flex items-center gap-2 border-b p-4">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setVista('lista')}
                            >
                                <X className="h-4 w-4" />
                            </Button>
                            <h2 className="font-semibold">Nuevo Mensaje</h2>
                        </div>
                        <div className="flex-1 overflow-y-auto p-4">
                            {usuarios.length === 0 ? (
                                <div className="text-center text-muted-foreground">
                                    No hay usuarios disponibles
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {usuarios.map((user) => (
                                        <div
                                            key={user.id}
                                            className="flex cursor-pointer items-center gap-3 rounded-lg p-3 transition-colors hover:bg-muted"
                                            onClick={() =>
                                                fetchConversacion(user.id)
                                            }
                                        >
                                            {getFotoUrl(
                                                user.profile_photo_path,
                                            ) ? (
                                                <img
                                                    src={
                                                        getFotoUrl(
                                                            user.profile_photo_path,
                                                        )!
                                                    }
                                                    alt={user.name}
                                                    className="h-10 w-10 rounded-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-white">
                                                    {user.name.charAt(0)}
                                                </div>
                                            )}
                                            <div>
                                                <div className="font-medium">
                                                    {user.name}
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    {user.email}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Chat */}
                {vista === 'chat' && selectedUser && (
                    <div className="flex w-full flex-col rounded-lg border bg-card md:w-2/3">
                        <div className="flex items-center gap-3 border-b p-4">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setVista('lista')}
                            >
                                <X className="h-4 w-4" />
                            </Button>
                            {getFotoUrl(selectedUser.profile_photo_path) ? (
                                <img
                                    src={
                                        getFotoUrl(
                                            selectedUser.profile_photo_path,
                                        )!
                                    }
                                    alt={selectedUser.name}
                                    className="h-10 w-10 rounded-full object-cover"
                                />
                            ) : (
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-white">
                                    {selectedUser.name.charAt(0)}
                                </div>
                            )}
                            <h2 className="font-semibold">
                                {selectedUser.name}
                            </h2>
                        </div>
                        <div 
                            id="chat-messages"
                            className="flex-1 space-y-4 overflow-y-auto p-4 scroll-smooth"
                        >
                            {mensajes.map((msg) => {
                                const isSender =
                                    msg.sender.id === auth?.user?.id;
                                return (
                                    <div
                                        key={msg.id || msg._tempId}
                                        className={`flex ${isSender ? 'justify-end' : 'justify-start'}`}
                                    >
                                        <div
                                            className={`max-w-[85%] rounded-lg p-3 sm:max-w-[70%] ${
                                                isSender
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'bg-muted'
                                            } ${msg._sending ? 'animate-pulse opacity-70' : ''} ${msg._error ? 'ring-2 ring-red-400' : ''}`}
                                        >
                                            {msg.archivo_path && (
                                                <div className="mb-2">
                                                    {msg.archivo_tipo?.startsWith('image/') ? (
                                                        <a href={`/storage/${msg.archivo_path}`} target="_blank" rel="noopener noreferrer">
                                                            <img 
                                                                src={`/storage/${msg.archivo_path}`} 
                                                                alt={msg.archivo_nombre || 'Imagen'} 
                                                                className="max-h-60 w-full rounded-md object-cover hover:opacity-90"
                                                            />
                                                        </a>
                                                    ) : (
                                                        <a 
                                                            href={`/storage/${msg.archivo_path}`} 
                                                            target="_blank" 
                                                            rel="noopener noreferrer"
                                                            className={`flex items-center gap-2 rounded border p-2 text-xs transition-colors ${
                                                                isSender ? 'border-primary-foreground/20 bg-primary-foreground/10 hover:bg-primary-foreground/20' : 'border-border bg-background hover:bg-muted'
                                                            }`}
                                                        >
                                                            <FileIcon className="h-4 w-4" />
                                                            <span className="truncate max-w-[150px]">{msg.archivo_nombre}</span>
                                                        </a>
                                                    )}
                                                </div>
                                            )}
                                            {msg.contenido && (
                                                <p className="text-sm">
                                                    {msg.contenido}
                                                </p>
                                            )}
                                            <p
                                                className={`mt-1 flex items-center justify-end gap-1 text-xs ${isSender ? 'text-primary-foreground/70' : 'text-muted-foreground'}`}
                                            >
                                                <span>{formatFecha(msg.created_at)}</span>
                                                {isSender && msg._sending && (
                                                    <Clock className="h-3 w-3 animate-spin text-yellow-300" />
                                                )}
                                                {isSender && msg._error && (
                                                    <AlertCircle className="h-3 w-3 text-red-300" />
                                                )}
                                            </p>
                                            {isSender && msg._error && (
                                                <div className="mt-1 flex gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            if (!msg._tempId) return;
                                                            setMensajes((prev) =>
                                                                prev.map((m) =>
                                                                    m._tempId === msg._tempId
                                                                        ? { ...m, _sending: true, _error: false }
                                                                        : m,
                                                                ),
                                                            );
                                                            submitMensaje(msg.contenido, null, msg._tempId!);
                                                        }}
                                                        className="flex items-center gap-1 text-[10px] text-red-400 hover:text-red-300"
                                                    >
                                                        <RefreshCw className="h-3 w-3" />
                                                        Reintentar
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        {archivo && (
                            <div className="flex items-center justify-between border-t bg-muted/30 px-4 py-2 text-xs">
                                <div className="flex items-center gap-2 truncate">
                                    <FileIcon className="h-3 w-3" />
                                    <span className="truncate">{archivo.name}</span>
                                </div>
                                <Button 
                                    type="button" 
                                    variant="ghost" 
                                    size="sm" 
                                    className="h-6 w-6 p-0"
                                    onClick={() => {
                                        setArchivo(null);
                                        if (fileInputRef.current) fileInputRef.current.value = '';
                                    }}
                                >
                                    <X className="h-3 w-3" />
                                </Button>
                            </div>
                        )}
                        <form
                            onSubmit={enviarMensaje}
                            className="flex items-center gap-2 border-t p-4"
                        >
                            <input 
                                type="file" 
                                className="hidden" 
                                ref={fileInputRef}
                                onChange={(e) => setArchivo(e.target.files?.[0] || null)}
                            />
                            <Button 
                                type="button" 
                                variant="ghost" 
                                size="icon"
                                onClick={() => fileInputRef.current?.click()}
                            >
                                <Paperclip className="h-4 w-4" />
                            </Button>
                            <Input
                                placeholder="Escribe un mensaje..."
                                value={nuevoMensaje}
                                onChange={(e) =>
                                    setNuevoMensaje(e.target.value)
                                }
                                className="flex-1"
                            />
                            <Button type="submit" size="icon">
                                <Send className="h-4 w-4" />
                            </Button>
                        </form>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
