import { Head, Link } from '@inertiajs/react';
import {
    Package,
    MessageSquare,
    Send,
    ShoppingBag,
    ArrowLeft,
    Clock,
    Paperclip,
    FileIcon,
    X,
    Download,
    CheckCheck,
    AlertCircle,
    RefreshCw,
} from 'lucide-react';
import { useState, useRef, useEffect, useCallback } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
    CardFooter,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import echo from '@/echo';
import { useCountry } from '@/hooks/use-country';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';

interface PedidoItem {
    id: number;
    nombre_producto: string;
    cantidad: number;
    subtotal: number;
    precio_unitario: number;
}

interface Mensaje {
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

interface Pedido {
    id: number;
    numero_pedido: string;
    estado: string;
    nombre_cliente?: string;
    telefono_cliente?: string;
    direccion_cliente?: string;
    metodo_pago?: string;
    notas?: string;
    subtotal: number;
    impuesto: number;
    total: number;
    created_at: string;
    items: PedidoItem[];
    public_profile?: { id: number; title: string; slug: string };
    conversacion?: { id: number; mensajes: Mensaje[] };
}

export default function PedidoDetalle({
    pedido,
    auth,
}: {
    pedido: Pedido;
    auth: any;
}) {
    const { code: countryCode, currency } = useCountry();

    const [mensajes, setMensajes] = useState<Mensaje[]>(
        pedido.conversacion?.mensajes || [],
    );
    const [nuevoMensaje, setNuevoMensaje] = useState('');
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [isLoadingChat, setIsLoadingChat] = useState(false);
    const [isTyping, setIsTyping] = useState(false);
    const [typingUser, setTypingUser] = useState<string | null>(null);
    const [connected, setConnected] = useState(false);
    const typingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const mEndRef = useRef<HTMLDivElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const getEstadoColor = (estado: string) => {
        switch (estado) {
            case 'pendiente':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'confirmado':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'preparando':
                return 'bg-orange-100 text-orange-800 border-orange-200';
            case 'enviado':
                return 'bg-purple-100 text-purple-800 border-purple-200';
            case 'entregado':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'cancelado':
                return 'bg-red-100 text-red-800 border-red-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    const submitChatMessage = async (
        content: string,
        file: File | null,
        tempId: string,
    ) => {
        const formData = new FormData();
        formData.append('contenido', content);
        if (file) {
            formData.append('archivo', file);
        }

        try {
            const resp = await fetch(
                `/conversaciones-pedidos/${pedido.conversacion!.id}/mensajes`,
                {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN':
                            document.head
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                    },
                    body: formData,
                },
            );

            if (resp.ok) {
                const data = await resp.json();
                setMensajes((prev) => {
                    if (prev.some((m) => m.id === data.mensaje.id)) return prev;
                    const withoutTemp = prev.filter(
                        (m) => !(m as Mensaje)._temp,
                    );
                    return [...withoutTemp, data.mensaje];
                });
                setTimeout(() => scrollToBottom(), 100);
            } else {
                setMensajes((prev) =>
                    prev.map((m) =>
                        (m as Mensaje)._tempId === tempId
                            ? { ...m, _sending: false, _error: true }
                            : m,
                    ),
                );
            }
        } catch {
            setMensajes((prev) =>
                prev.map((m) =>
                    (m as Mensaje)._tempId === tempId
                        ? { ...m, _sending: false, _error: true }
                        : m,
                ),
            );
        }
    };

    const handleEnviarMensaje = (e: React.FormEvent) => {
        e.preventDefault();
        if ((!nuevoMensaje.trim() && !selectedFile) || !pedido.conversacion?.id)
            return;

        setIsLoadingChat(true);

        const tempId = `temp-${Date.now()}`;
        const tempMsg: Mensaje = {
            id: -Date.now(),
            sender_id: auth.user.id,
            contenido: nuevoMensaje.trim() || '',
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

        if (selectedFile) {
            tempMsg.file_url = URL.createObjectURL(selectedFile);
            tempMsg.file_name = selectedFile.name;
            tempMsg.is_image = selectedFile.type.startsWith('image/');
        }

        setMensajes((prev) => [...prev, tempMsg]);
        setNuevoMensaje('');

        const fileToSend = selectedFile;
        setSelectedFile(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
        setTimeout(() => scrollToBottom(), 100);

        const contentToSend = nuevoMensaje;
        submitChatMessage(contentToSend, fileToSend, tempId).finally(() => {
            setIsLoadingChat(false);
        });
    };

    const emitTyping = () => {
        if (!connected || !pedido.conversacion?.id) return;
        const channel = echo.private('conversacion.' + pedido.conversacion.id);
        channel.whisper('typing', {
            user_id: auth.user.id,
            name: auth.user.name,
        });
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            setSelectedFile(e.target.files[0]);
        }
    };

    const fetchMensajes = useCallback(async () => {
        if (!pedido.conversacion?.id) return;
        try {
            const resp = await fetch(
                `/conversaciones-pedidos/${pedido.conversacion.id}/mensajes`,
            );
            if (resp.ok) {
                const data = await resp.json();
                setMensajes((prev) => {
                    const temps = prev.filter((m) => (m as Mensaje)._temp);
                    if (temps.length === 0) return data.mensajes;
                    const merged = data.mensajes.filter(
                        (m: Mensaje) => !temps.some((t) => t.contenido === m.contenido && t.sender_id === m.sender_id),
                    );
                    return [...merged, ...temps];
                });
            }
        } catch (error) {
            console.error(error);
        }
    }, [pedido.conversacion]);

    const scrollToBottom = useCallback(() => {
        mEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, []);

    useEffect(() => {
        scrollToBottom();

        if (!pedido.conversacion?.id) return;

        const channel = echo.private('conversacion.' + pedido.conversacion.id);

        const addMensaje = (e: any) => {
            setMensajes((prev) => {
                if (prev.some((m) => m.id === e.id)) return prev;
                const withoutTemp = prev.filter(
                    (m) => !(m as Mensaje)._temp,
                );
                return [...withoutTemp, e];
            });
            setTimeout(scrollToBottom, 100);
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

        const connectTimer = setTimeout(() => setConnected(true), 0);

        const interval = setInterval(fetchMensajes, 30000);
        return () => {
            clearInterval(interval);
            clearTimeout(connectTimer);
            echo.leave('conversacion.' + pedido.conversacion!.id);
            if (typingTimeoutRef.current) clearTimeout(typingTimeoutRef.current);
        };
    }, [fetchMensajes, scrollToBottom, pedido.conversacion, auth.user.id]);

    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: `Pedido #${pedido.numero_pedido}`,
                    href: `/pedidos/${pedido.id}`,
                },
            ]}
        >
            <Head title={`Pedido #${pedido.numero_pedido}`} />

            <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="mb-8 flex flex-col justify-between border-b pb-4 md:flex-row md:items-center">
                    <div className="mb-4 flex items-center space-x-4 md:mb-0">
                        <Button
                            variant="outline"
                            size="icon"
                            asChild
                            className="rounded-full shadow-sm"
                        >
                            <Link href="/mis-pedidos">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="flex items-center space-x-3 text-2xl font-bold text-gray-900">
                                <span>Pedido #{pedido.numero_pedido}</span>
                            </h1>
                            <div className="mt-1 flex items-center gap-3 text-sm text-gray-500">
                                <span className="flex items-center">
                                    <Clock className="mr-1 h-3.5 w-3.5" />{' '}
                                    {new Date(pedido.created_at).toLocaleString(
                                        currency.locale,
                                    )}
                                </span>
                                <span className="text-gray-300">|</span>
                                <span className="flex items-center">
                                    <ShoppingBag className="mr-1 h-3.5 w-3.5 text-indigo-500" />
                                    Vendedor:{' '}
                                    <Link
                                        href={`/tienda/${pedido.public_profile?.slug}`}
                                        className="ml-1 font-medium text-indigo-600 hover:underline"
                                    >
                                        {pedido.public_profile?.title}
                                    </Link>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col items-end">
                        <Badge
                            className={`border px-3 py-1 text-sm shadow-sm ${getEstadoColor(pedido.estado)}`}
                        >
                            {pedido.estado.toUpperCase()}
                        </Badge>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    {/* Productos */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card className="overflow-hidden border-indigo-100 shadow-sm">
                            <CardHeader className="border-b border-indigo-50/50 bg-white pb-4">
                                <CardTitle className="flex items-center text-lg text-indigo-900">
                                    <Package className="mr-2 h-5 w-5 text-indigo-500" />{' '}
                                    Productos Comprados
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader className="bg-slate-50">
                                        <TableRow>
                                            <TableHead className="font-semibold text-slate-700">
                                                Producto
                                            </TableHead>
                                            <TableHead className="text-right font-semibold text-slate-700">
                                                Precio
                                            </TableHead>
                                            <TableHead className="text-center font-semibold text-slate-700">
                                                Cant.
                                            </TableHead>
                                            <TableHead className="text-right font-semibold text-slate-700">
                                                Subtotal
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {pedido.items.map((item) => (
                                            <TableRow
                                                key={item.id}
                                                className="hover:bg-slate-50/50"
                                            >
                                                <TableCell className="font-medium text-gray-900">
                                                    {item.nombre_producto}
                                                </TableCell>
                                                <TableCell className="text-right text-gray-600">
                                                    {formatCurrency(
                                                        item.precio_unitario,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-center text-gray-600">
                                                    {item.cantidad}
                                                </TableCell>
                                                <TableCell className="text-right font-medium text-gray-900">
                                                    {formatCurrency(
                                                        item.subtotal,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>

                                <div className="flex flex-col items-end space-y-3 border-t bg-slate-50 p-6">
                                    <div className="flex w-64 justify-between text-sm text-gray-600">
                                        <span>Subtotal:</span>
                                        <span className="font-medium">
                                            {formatCurrency(pedido.subtotal)}
                                        </span>
                                    </div>
                                    <div className="flex w-64 justify-between text-sm text-gray-600">
                                        <span>IVA (19%):</span>
                                        <span className="font-medium">
                                            {formatCurrency(pedido.impuesto)}
                                        </span>
                                    </div>
                                    <div className="flex w-64 justify-between border-t border-slate-200 pt-3 text-xl font-bold text-gray-900">
                                        <span>Total pagado:</span>
                                        <span className="text-indigo-700">
                                            {formatCurrency(pedido.total)}
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Chat */}
                    <div className="flex h-full max-h-[600px] flex-col lg:col-span-1">
                        <Card className="flex flex-1 flex-col overflow-hidden border-indigo-200 shadow-md">
                            <CardHeader className="bg-gradient-to-r from-indigo-500 to-indigo-600 pb-4 text-white">
                                <CardTitle className="flex items-center text-lg">
                                    <MessageSquare className="mr-2 h-5 w-5" />{' '}
                                    Chat con Vendedor
                                </CardTitle>
                                <CardDescription className="text-indigo-100">
                                    Contacta a{' '}
                                    {pedido.public_profile?.title || 'Vendedor'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-1 flex-col overflow-hidden bg-slate-50 p-0">
                                <div className="flex-1 space-y-4 overflow-y-auto p-4">
                                    {mensajes.length === 0 ? (
                                        <div className="flex h-full flex-col items-center justify-center p-6 text-center text-sm text-gray-400">
                                            <MessageSquare className="mb-3 h-10 w-10 text-indigo-200" />
                                            <p>
                                                Ningún mensaje enviado aún.
                                                Escríbele al vendedor ante dudas
                                                con tu pedido.
                                            </p>
                                        </div>
                                    ) : (
                                        mensajes.map((msg, idx) => {
                                            const isMe =
                                                msg.sender_id === auth.user.id;
                                            return (
                                                <div
                                                    key={msg.id || (msg as Mensaje)._tempId}
                                                    className={`flex flex-col ${isMe ? 'items-end' : 'items-start'}`}
                                                >
                                                    <span className="mb-1 px-1 text-[10px] font-semibold text-gray-400 uppercase">
                                                        {isMe
                                                            ? 'Tú'
                                                            : msg.sender
                                                                  ?.name ||
                                                              'Usuario'}
                                                    </span>
                                                    <div
                                                        className={`max-w-[90%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-sm ${
                                                            isMe
                                                                ? 'rounded-tr-sm bg-indigo-600 text-white'
                                                                : 'rounded-tl-sm border border-gray-100 bg-white text-gray-700'
                                                        } ${(msg as Mensaje)._sending ? 'animate-pulse opacity-70' : ''} ${(msg as Mensaje)._error ? 'ring-2 ring-red-400' : ''}`}
                                                    >
                                                        {msg.contenido && (
                                                            <p className="whitespace-pre-wrap">
                                                                {msg.contenido}
                                                            </p>
                                                        )}

                                                    {msg.file_url && !(msg as Mensaje)._sending && (
                                                        <div
                                                            className={`mt-2 ${msg.contenido ? 'border-t border-white/20 pt-2' : ''}`}
                                                        >
                                                            {msg.is_image ? (
                                                                <a
                                                                    href={
                                                                        msg.file_url
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="block overflow-hidden rounded-lg"
                                                                >
                                                                    <img
                                                                        src={
                                                                            msg.file_url
                                                                        }
                                                                        alt="Adjunto"
                                                                        className="h-auto max-w-full object-cover transition-opacity hover:opacity-90"
                                                                    />
                                                                </a>
                                                            ) : (
                                                                <a
                                                                    href={
                                                                        msg.file_url
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className={`flex items-center gap-2 rounded bg-black/5 p-2 transition-colors hover:bg-black/10 ${isMe ? 'text-white' : 'text-indigo-600'}`}
                                                                >
                                                                    <FileIcon className="h-4 w-4 shrink-0" />
                                                                    <span className="truncate text-xs underline">
                                                                        {
                                                                            msg.file_name
                                                                        }
                                                                    </span>
                                                                    <Download className="ml-auto h-3 w-3 shrink-0" />
                                                                </a>
                                                            )}
                                                        </div>
                                                    )}
                                                    {isMe && !(msg as Mensaje)._temp && !(msg as Mensaje)._error && (
                                                        <div className="mt-1 flex justify-end">
                                                            <CheckCheck className={`h-3 w-3 ${msg.leido ? 'text-blue-200' : 'opacity-50'}`} />
                                                        </div>
                                                    )}
                                                    {isMe && (msg as Mensaje)._sending && (
                                                        <div className="mt-1 flex justify-end">
                                                            <Clock className="h-3 w-3 animate-spin text-yellow-200" />
                                                        </div>
                                                    )}
                                                    {isMe && (msg as Mensaje)._error && (
                                                        <div className="mt-1 flex items-center justify-end gap-1">
                                                            <AlertCircle className="h-3 w-3 text-red-300" />
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    const tempId = (msg as Mensaje)._tempId;
                                                                    if (!tempId) return;
                                                                    const content = msg.contenido;
                                                                    setMensajes((prev) =>
                                                                        prev.filter((m) => m.id !== msg.id),
                                                                    );
                                                                }}
                                                                className="text-[10px] text-red-400 hover:text-red-300"
                                                            >
                                                                Descartar
                                                            </button>
                                                        </div>
                                                    )}
                                                    </div>
                                                </div>
                                            );
                                        })
                                    )}
                                    {isTyping && typingUser && (
                                        <div className="flex w-full justify-start">
                                            <div className="flex items-center gap-2 rounded-2xl rounded-bl-none border border-gray-100 bg-white px-4 py-2 text-xs text-gray-500 shadow-sm">
                                                <span className="flex gap-0.5">
                                                    <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400 [animation-delay:0ms]" />
                                                    <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400 [animation-delay:150ms]" />
                                                    <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400 [animation-delay:300ms]" />
                                                </span>
                                                <span>{typingUser} está escribiendo...</span>
                                            </div>
                                        </div>
                                    )}
                                    <div ref={mEndRef} />
                                </div>
                            </CardContent>
                            <CardFooter className="border-t bg-white p-3">
                                <form
                                    onSubmit={handleEnviarMensaje}
                                    className="flex w-full flex-col space-y-2"
                                >
                                    {selectedFile && (
                                        <div className="flex items-center justify-between rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-1.5 text-xs text-indigo-700">
                                            <div className="flex items-center gap-2 truncate">
                                                <Paperclip className="h-3 w-3" />
                                                <span className="truncate">
                                                    {selectedFile.name}
                                                </span>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setSelectedFile(null)
                                                }
                                                className="ml-2 text-indigo-400 hover:text-indigo-600"
                                            >
                                                <X className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    )}
                                    <div className="flex w-full items-center space-x-2">
                                        <input
                                            type="file"
                                            className="hidden"
                                            ref={fileInputRef}
                                            onChange={handleFileChange}
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="h-9 w-9 shrink-0 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600"
                                            onClick={() =>
                                                fileInputRef.current?.click()
                                            }
                                            disabled={isLoadingChat}
                                        >
                                            <Paperclip className="h-5 w-5" />
                                        </Button>
                                        <Input
                                            placeholder="Escribe tu mensaje..."
                                            className="h-10 flex-1 rounded-full border-slate-200 bg-slate-50 focus-visible:ring-indigo-500"
                                            value={nuevoMensaje}
                                            onChange={(e) => {
                                                setNuevoMensaje(e.target.value);
                                                emitTyping();
                                            }}
                                            disabled={isLoadingChat}
                                        />
                                        <Button
                                            type="submit"
                                            size="icon"
                                            disabled={
                                                (!nuevoMensaje.trim() &&
                                                    !selectedFile) ||
                                                isLoadingChat
                                            }
                                            className="h-10 w-10 shrink-0 rounded-full bg-indigo-600 shadow-sm transition-all hover:bg-indigo-700 active:scale-95"
                                        >
                                            <Send className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </form>
                            </CardFooter>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
