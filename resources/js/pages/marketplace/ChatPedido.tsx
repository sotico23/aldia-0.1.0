import { Head, Link } from '@inertiajs/react';
import { format } from 'date-fns';
import 'date-fns/locale';
import {
    Send,
    Store,
    User,
    ArrowLeft,
    Paperclip,
    FileIcon,
    X,
    Package,
    CheckCheck,
    Clock,
    AlertCircle,
    RefreshCw,
} from 'lucide-react';
import { useEffect, useRef, useState, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/react';
import echo from '@/echo';
import AppLayout from '@/layouts/app-layout';

interface Message {
    id: number;
    sender_id: number;
    contenido: string;
    created_at: string;
    sender: { id: number; name: string; profile_photo_path?: string };
    file_url?: string;
    file_name?: string;
    is_image?: boolean;
    leido?: boolean;
    _temp?: boolean;
    _sending?: boolean;
    _error?: boolean;
    _tempId?: string;
}

interface Conversacion {
    id: number;
    pedido: {
        id: number;
        numero_pedido: string;
        estado: string;
        total: number;
    };
    comprador: { id: number; name: string };
    publicProfile: { id: number; title: string; slug: string; user_id: number };
    mensajes: Message[];
    otro_usuario?: { id: number; name: string };
}

interface Props {
    conversacion: Conversacion;
    mensajes: Message[];
    auth: { user: any };
}

export default function ChatPedido({
    conversacion,
    mensajes: initialMensajes,
    auth,
}: Props) {
    const scrollRef = useRef<HTMLDivElement>(null);
    const typingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [mensajes, setMensajes] = useState<Message[]>(initialMensajes);
    const [contenido, setContenido] = useState('');
    const [isTyping, setIsTyping] = useState(false);
    const [typingUser, setTypingUser] = useState<string | null>(null);
    const [connected, setConnected] = useState(false);

    const fileInputRef = useRef<HTMLInputElement>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [previewImage, setPreviewImage] = useState<string | null>(null);
    const [lightboxUrl, setLightboxUrl] = useState<string | null>(null);

    const isVendedor = auth.user.id === conversacion.publicProfile.user_id;
    const chatTitle = isVendedor
        ? conversacion.comprador.name
        : conversacion.publicProfile.title;
    const [isSending, setIsSending] = useState(false);

    const scrollToBottom = useCallback(() => {
        requestAnimationFrame(() => {
            if (scrollRef.current) {
                scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
            }
        });
    }, []);

    useEffect(() => {
        scrollToBottom();
    }, [mensajes.length, scrollToBottom]);

    useEffect(() => {
        const channelName = `conversacion.${conversacion.id}`;
        const channel = echo.private(channelName);

        const addMensaje = (e: any) => {
            setMensajes((prev) => {
                if (prev.some((m) => m.id === e.id)) return prev;
                const withoutTemp = prev.filter(
                    (m) => !(m as Message)._temp,
                );
                return [...withoutTemp, e];
            });
        };

        channel.listen('.MensajeEnviado', addMensaje);

        channel.listen('.CommunicationMessageSent', (e: any) => {
            if (e.senderId === auth.user.id) return;
            addMensaje({
                id: e.message.id,
                sender_id: e.message.sender_id,
                contenido: e.message.content,
                created_at: e.message.created_at,
                sender: e.message.sender,
                file_url: e.message.file_url ?? (e.message.file_path ? `/storage/${e.message.file_path}` : undefined),
                file_name: e.message.file_name,
                is_image: e.message.is_image ?? (e.message.file_type?.startsWith?.('image/') || false),
                leido: false,
            });
        });

        channel.listen('.MensajesLeidos', (e: any) => {
            if (e.leido_por === auth.user.id) return;
            setMensajes((prev) =>
                prev.map((m) =>
                    m.sender_id === auth.user.id ? { ...m, leido: true } : m,
                ),
            );
        });

        channel.listenForWhisper('typing', (e: any) => {
            if (e.user_id === auth.user.id) return;
            setTypingUser(e.name);
            setIsTyping(true);
            if (typingTimeoutRef.current) clearTimeout(typingTimeoutRef.current);
            typingTimeoutRef.current = setTimeout(() => {
                setIsTyping(false);
                setTypingUser(null);
            }, 3000);
        });

        const timer = setTimeout(() => setConnected(true), 0);

        // Fallback polling para consistencia (cada 30s)
        const pollingInterval = setInterval(() => {
            router.reload({
                only: ['mensajes'],
                preserveUrl: true,
                preserveScroll: true,
            });
        }, 30000);

        return () => {
            clearTimeout(timer);
            clearInterval(pollingInterval);
            echo.leave(channelName);
            if (typingTimeoutRef.current) clearTimeout(typingTimeoutRef.current);
        };
    }, [conversacion.id, auth.user.id]);

    const handleTyping = () => {
        if (!connected) return;
        const channel = echo.private('conversacion.' + conversacion.id);
        channel.whisper('typing', {
            user_id: auth.user.id,
            name: auth.user.name,
        });
    };

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setSelectedFile(file);
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onloadend = () => setPreviewImage(reader.result as string);
                reader.readAsDataURL(file);
            }
        }
    };

    const clearFile = () => {
        setSelectedFile(null);
        setPreviewImage(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const submitMessage = async (
        messageContent: string,
        file: File | null,
        tempId: string,
    ) => {
        const formData = new FormData();
        formData.append('contenido', messageContent);
        if (file) {
            formData.append('archivo', file);
        }

        const csrfToken =
            document.head
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') || '';

        try {
            const resp = await fetch(
                `/conversaciones-pedidos/${conversacion.id}/mensajes`,
                {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                    body: formData,
                },
            );

            if (resp.ok) {
                const result = await resp.json();
                setMensajes((prev) => {
                    if (prev.some((m) => m.id === result.mensaje.id)) return prev;
                    const withoutTemp = prev.filter(
                        (m) => !(m as Message)._temp,
                    );
                    return [...withoutTemp, result.mensaje];
                });
            } else {
                setMensajes((prev) =>
                    prev.map((m) =>
                        (m as Message)._tempId === tempId
                            ? { ...m, _sending: false, _error: true }
                            : m,
                    ),
                );
            }
        } catch {
            setMensajes((prev) =>
                prev.map((m) =>
                    (m as Message)._tempId === tempId
                        ? { ...m, _sending: false, _error: true }
                        : m,
                ),
            );
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if ((!contenido.trim() && !selectedFile) || isSending) return;

        setIsSending(true);

        const tempId = `temp-${Date.now()}`;
        const tempMsg: Message = {
            id: -Date.now(),
            sender_id: auth.user.id,
            contenido: contenido.trim() || '',
            created_at: new Date().toISOString(),
            sender: {
                id: auth.user.id,
                name: auth.user.name,
                profile_photo_path: auth.user.profile_photo_path,
            },
            _temp: true,
            _sending: true,
            _tempId: tempId,
            leido: false,
        };

        if (selectedFile && selectedFile.type.startsWith('image/')) {
            tempMsg.file_url = URL.createObjectURL(selectedFile);
            tempMsg.is_image = true;
            tempMsg.file_name = selectedFile.name;
        } else if (selectedFile) {
            tempMsg.file_url = URL.createObjectURL(selectedFile);
            tempMsg.file_name = selectedFile.name;
            tempMsg.is_image = false;
        }

        setMensajes((prev) => [...prev, tempMsg]);
        setContenido('');

        const fileToSend = selectedFile;
        clearFile();

        const contentToSend = contenido;
        submitMessage(contentToSend, fileToSend, tempId).finally(() => {
            setIsSending(false);
        });
    };

    const estadoColor = (estado: string) => {
        switch (estado) {
            case 'pendiente': return 'bg-yellow-100 text-yellow-800';
            case 'confirmado': return 'bg-blue-100 text-blue-800';
            case 'preparando': return 'bg-orange-100 text-orange-800';
            case 'enviado': return 'bg-purple-100 text-purple-800';
            case 'entregado': return 'bg-green-100 text-green-800';
            case 'cancelado': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Mis Pedidos', href: '/mis-pedidos' },
                {
                    title: `Pedido ${conversacion.pedido.numero_pedido}`,
                    href: `/pedidos/${conversacion.pedido.id}`,
                },
                { title: 'Chat', href: '#' },
            ]}
        >
            <Head
                title={`Chat Pedido ${conversacion.pedido.numero_pedido} | Marketplace`}
            />

            <div className="mx-auto flex h-[calc(100vh-14rem)] max-w-4xl flex-col">
                <Card className="flex flex-1 flex-col overflow-hidden border-slate-200 shadow-lg dark:border-slate-800">
                    <CardHeader className="z-10 shrink-0 border-b border-slate-100 bg-white py-4 dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <Button variant="ghost" size="icon" asChild className="rounded-full">
                                    <Link href="/mis-pedidos" className="h-5 w-5">
                                        <ArrowLeft className="h-5 w-5" />
                                    </Link>
                                </Button>
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800">
                                    {isVendedor ? (
                                        <User className="h-6 w-6" />
                                    ) : (
                                        <Store className="h-5 w-5" />
                                    )}
                                </div>
                                <div>
                                    <h2 className="leading-tight font-bold text-slate-900 dark:text-white">
                                        {chatTitle}
                                    </h2>
                                    <div className="flex items-center gap-2 text-xs text-slate-500">
                                        <Package className="h-3 w-3" />
                                        <span>{conversacion.pedido.numero_pedido}</span>
                                        <span className={`rounded px-1.5 py-0.5 ${estadoColor(conversacion.pedido.estado)}`}>
                                            {conversacion.pedido.estado}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <span className={`inline-block h-2 w-2 rounded-full ${connected ? 'bg-green-500' : 'bg-red-500'}`} title={connected ? 'Conectado' : 'Desconectado'} />
                        </div>
                    </CardHeader>

                    <CardContent
                        className="flex-1 space-y-4 overflow-y-auto overflow-x-hidden bg-slate-50/50 p-4 dark:bg-slate-950/20"
                        ref={scrollRef}
                    >
                        {mensajes.length > 0 ? (
                            mensajes.map((msg) => {
                                const isMe = msg.sender_id === auth.user.id;

                                return (
                                    <div key={msg.id || (msg as Message)._tempId} className={`flex w-full ${isMe ? 'justify-end' : 'justify-start'}`}>
                                        <div className={`max-w-[75%] rounded-2xl px-4 py-2.5 shadow-sm ${isMe ? 'rounded-tr-none bg-primary text-primary-foreground' : 'rounded-tl-none border border-slate-100 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white'} ${(msg as Message)._sending ? 'animate-pulse opacity-70' : ''} ${(msg as Message)._error ? 'ring-2 ring-red-400' : ''}`}>
                                            {msg.file_url && (
                                                <div className="mb-2">
                                                    {msg.is_image ? (
                                                        <img
                                                            src={msg.file_url}
                                                            alt={msg.file_name}
                                                            className="max-h-48 cursor-pointer rounded-lg object-cover transition-opacity hover:opacity-80"
                                                            onClick={() => !(msg as Message)._temp && setLightboxUrl(msg.file_url!)}
                                                        />
                                                    ) : (
                                                        <a href={msg.file_url} download={msg.file_name} className="flex items-center gap-2 text-xs text-blue-600 hover:underline">
                                                            <FileIcon className="h-4 w-4" />
                                                            {msg.file_name}
                                                        </a>
                                                    )}
                                                </div>
                                            )}
                                            <p className="prose dark:prose-invert text-sm break-words">{msg.contenido}</p>
                                            <div className={`mt-1 flex items-center justify-end gap-1 ${isMe ? 'text-blue-100' : 'text-slate-400'}`}>
                                                <span className="text-[10px]">{format(new Date(msg.created_at), 'HH:mm')}</span>
                                                {isMe && !(msg as Message)._temp && !(msg as Message)._error && (
                                                    <CheckCheck className={`h-3 w-3 ${msg.leido ? 'text-blue-300' : 'opacity-50'}`} />
                                                )}
                                                {isMe && (msg as Message)._sending && (
                                                    <Clock className="h-3 w-3 animate-spin text-yellow-300" />
                                                )}
                                                {isMe && (msg as Message)._error && (
                                                    <AlertCircle className="h-3 w-3 text-red-300" />
                                                )}
                                            </div>
                                            {(msg as Message)._error && (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        const tempId = (msg as Message)._tempId;
                                                        if (!tempId) return;
                                                        setMensajes((prev) =>
                                                            prev.map((m) =>
                                                                (m as Message)._tempId === tempId
                                                                    ? { ...m, _sending: true, _error: false }
                                                                    : m,
                                                            ),
                                                        );
                                                        const content = msg.contenido;
                                                        submitMessage(
                                                            content,
                                                            null,
                                                            tempId,
                                                        );
                                                    }}
                                                    className="mt-1 flex items-center gap-1 text-[10px] text-red-400 hover:text-red-300"
                                                >
                                                    <RefreshCw className="h-3 w-3" />
                                                    Reintentar
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })
                        ) : (
                            <div className="flex h-full flex-col items-center justify-center p-8 text-center opacity-50">
                                <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                    <Send className="h-8 w-8 -rotate-45 text-slate-300" />
                                </div>
                                <h3 className="font-semibold text-slate-700 dark:text-slate-300">Comienza la conversación</h3>
                                <p className="max-w-[200px] text-sm text-slate-500">Envía un mensaje para coordinar con {chatTitle}.</p>
                            </div>
                        )}

                        {isTyping && typingUser && (
                            <div className="flex w-full justify-start">
                                <div className="flex items-center gap-2 rounded-2xl rounded-tl-none border border-slate-100 bg-white px-4 py-2 text-xs text-slate-500 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                    <span className="flex gap-0.5">
                                        <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400 [animation-delay:0ms]" />
                                        <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400 [animation-delay:150ms]" />
                                        <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400 [animation-delay:300ms]" />
                                    </span>
                                    <span>{typingUser} está escribiendo...</span>
                                </div>
                            </div>
                        )}
                    </CardContent>

                    <div className="shrink-0 border-t border-slate-100 bg-white p-4 sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                        <form onSubmit={handleSubmit} encType="multipart/form-data" className="flex flex-col gap-2">
                            {selectedFile && (
                                <div className="flex items-center justify-between rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2">
                                    <div className="flex items-center gap-2 truncate">
                                        {previewImage && (
                                            <img src={previewImage} alt="Preview" className="h-10 w-10 rounded object-cover" />
                                        )}
                                        {!previewImage && (
                                            <Paperclip className="h-4 w-4 text-indigo-500" />
                                        )}
                                        <span className="truncate text-sm text-indigo-700">{selectedFile.name}</span>
                                    </div>
                                    <button type="button" onClick={clearFile} className="text-indigo-400 hover:text-indigo-600">
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>
                            )}
                            <div className="flex flex-col sm:flex-row items-center gap-2">
                                <input type="file" className="hidden" ref={fileInputRef} onChange={handleFileSelect} />
                                <Button type="button" variant="ghost" size="icon" className="shrink-0 rounded-full" onClick={() => fileInputRef.current?.click()}>
                                    <Paperclip className="h-5 w-5" />
                                </Button>
                                <Input
                                    value={contenido}
                                    onChange={(e) => {
                                        setContenido(e.target.value);
                                        handleTyping();
                                    }}
                                    placeholder="Escribe un mensaje..."
                                    className="w-full flex-1 rounded-full border-slate-200 px-4 py-6 focus-visible:ring-primary dark:border-slate-800"
                                    autoComplete="off"
                                />
                                <Button
                                    type="submit"
                                    size="icon"
                                    className="h-12 w-12 rounded-full shadow-lg transition-transform active:scale-95"
                                    disabled={isSending || (!contenido.trim() && !selectedFile)}
                                >
                                    <Send className="h-5 w-5" />
                                </Button>
                            </div>
                        </form>
                    </div>
                </Card>
            </div>

            {lightboxUrl && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
                    onClick={() => setLightboxUrl(null)}
                >
                    <button
                        className="absolute top-4 right-4 z-10 rounded-full bg-black/50 p-2 text-white transition-colors hover:bg-black/70"
                        onClick={() => setLightboxUrl(null)}
                    >
                        <X className="h-6 w-6" />
                    </button>
                    <img
                        src={lightboxUrl}
                        alt="Imagen ampliada"
                        className="max-h-[90vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
                        onClick={(e) => e.stopPropagation()}
                    />
                </div>
            )}
        </AppLayout>
    );
}
