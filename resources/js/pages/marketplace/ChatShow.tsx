import { Head, useForm, router, Link } from '@inertiajs/react';
import { format } from 'date-fns';
import 'date-fns/locale';
import {
    Send,
    Store,
    User,
    ArrowLeft,
    RefreshCw,
    ImagePlus,
    X,
    Clock,
    AlertCircle,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import echo from '@/echo';
import { useChatViewport } from '@/hooks/use-chat-viewport';
import AppLayout from '@/layouts/app-layout';
import chat from '@/routes/chat';

interface Message {
    id: number;
    body: string;
    image_path: string | null;
    sender_id: number;
    sender: { id: number; name: string };
    created_at: string;
    _temp?: boolean;
    _sending?: boolean;
    _error?: boolean;
    _tempId?: string;
}

interface Conversation {
    id: number;
    buyer: { id: number; name: string };
    store: { id: number; title: string; user_id: number };
}

interface Props {
    conversation: Conversation;
    messages: Message[];
    auth: { user: any };
}

export default function ChatShow({ conversation, messages, auth }: Props) {
    const viewportRef = useChatViewport();
    const scrollRef = useRef<HTMLDivElement>(null);
    const typingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [localMessages, setLocalMessages] = useState<Message[]>(messages);
    const [isTyping, setIsTyping] = useState(false);
    const [typingUser, setTypingUser] = useState<string | null>(null);
    const [connected, setConnected] = useState(false);
    const [isPolling, setIsPolling] = useState(false);

    const fileInputRef = useRef<HTMLInputElement>(null);
    const [previewImage, setPreviewImage] = useState<string | null>(null);

    const { data, setData, post, processing, reset } = useForm({
        body: '',
        image: null as File | null,
    });

    const handleImageSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('image', file);
            const reader = new FileReader();
            reader.onloadend = () => setPreviewImage(reader.result as string);
            reader.readAsDataURL(file);
        }
    };

    const clearImage = () => {
        setData('image', null);
        setPreviewImage(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const scrollToBottom = () => {
        requestAnimationFrame(() => {
            if (scrollRef.current) {
                scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
            }
        });
    };

    useEffect(() => {
        scrollToBottom();
    }, [localMessages.length]);

    useEffect(() => {
        const timer = setTimeout(() => setLocalMessages(messages), 0);
        return () => clearTimeout(timer);
    }, [messages]);

    useEffect(() => {
        const channel = echo.private('conversation.' + conversation.id);

        const addMensaje = (e: any) => {
            setLocalMessages((prev) => {
                if (prev.some((m) => m.id === e.id)) return prev;
                const withoutTemp = prev.filter((m) => !(m as Message)._temp);
                return [...withoutTemp, e];
            });
            setTimeout(scrollToBottom, 100);
        };

        channel.listen('.MessageSent', addMensaje);

        channel.listen('.CommunicationMessageSent', (e: any) => {
            if (e.senderId === auth.user.id) return;
            addMensaje({
                id: e.message.id,
                content: e.message.content,
                sender_id: e.message.sender_id,
                body: e.message.content,
                created_at: e.message.created_at,
                sender: e.message.sender,
                image_path: e.message.file_path,
                leido: false,
            });
        });

        channel.listenForWhisper('typing', (e: any) => {
            if (e.user_id === auth.user.id) return;
            setTypingUser(e.name);
            setIsTyping(true);
            if (typingTimeoutRef.current)
                clearTimeout(typingTimeoutRef.current);
            typingTimeoutRef.current = setTimeout(() => {
                setIsTyping(false);
                setTypingUser(null);
            }, 3000);
        });

        const connectTimer = setTimeout(() => setConnected(true), 0);

        const interval = setInterval(() => {
            router.reload({
                only: ['messages'],
                preserveUrl: true,
            });
        }, 30000);

        return () => {
            clearInterval(interval);
            clearTimeout(connectTimer);
            echo.leave('conversation.' + conversation.id);
            if (typingTimeoutRef.current)
                clearTimeout(typingTimeoutRef.current);
        };
    }, [conversation.id, auth.user.id]);

    const emitTyping = () => {
        if (!connected) return;
        const channel = echo.private('conversation.' + conversation.id);
        channel.whisper('typing', {
            user_id: auth.user.id,
            name: auth.user.name,
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.body.trim() && !data.image) return;

        const tempId = `temp-${Date.now()}`;
        const tempMsg: Message = {
            id: -Date.now(),
            sender_id: auth.user.id,
            body: data.body.trim(),
            image_path: null,
            created_at: new Date().toISOString(),
            sender: { id: auth.user.id, name: auth.user.name },
            _temp: true,
            _sending: true,
            _tempId: tempId,
        };

        if (data.image) {
            tempMsg.image_path = URL.createObjectURL(data.image) as any;
        }

        setLocalMessages((prev) => [...prev, tempMsg]);

        post(chat.send(conversation.id).url, {
            forceFormData: true,
            onSuccess: () => {
                reset();
                clearImage();
                scrollToBottom();
            },
            onError: () => {
                setLocalMessages((prev) =>
                    prev.map((m) =>
                        (m as Message)._tempId === tempId
                            ? { ...m, _sending: false, _error: true }
                            : m,
                    ),
                );
            },
        });
    };

    const isStoreOwner = auth.user.id === conversation.store.user_id;
    const chatTitle = isStoreOwner
        ? conversation.buyer.name
        : conversation.store.title;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Marketplace', href: '/tienda' },
                { title: 'Mensajes', href: '/chat' },
                { title: chatTitle, href: '#' },
            ]}
        >
            <Head title={`Chat con ${chatTitle} | Marketplace`} />

            <div
                ref={viewportRef}
                className="mx-auto flex h-[var(--chat-height,calc(100dvh-5rem))] min-h-0 w-full max-w-4xl flex-col p-2 sm:p-4"
            >
                <Card className="flex min-h-0 flex-1 flex-col gap-0 overflow-hidden border-slate-200 py-0 shadow-lg dark:border-slate-800">
                    <CardHeader className="z-10 shrink-0 border-b border-slate-100 bg-white px-3 py-3 sm:px-6 dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex min-w-0 items-center justify-between gap-2">
                            <div className="flex min-w-0 items-center gap-2 sm:gap-3">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    asChild
                                    className="size-11 shrink-0 rounded-full"
                                >
                                    <Link
                                        href={chat.index().url}
                                        aria-label="Volver a mensajes"
                                    >
                                        <ArrowLeft className="h-5 w-5" />
                                    </Link>
                                </Button>
                                <div className="hidden h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 sm:flex dark:bg-slate-800">
                                    {isStoreOwner ? (
                                        <User className="h-6 w-6" />
                                    ) : (
                                        <Store className="h-5 w-5" />
                                    )}
                                </div>
                                <div className="min-w-0">
                                    <h2
                                        className="truncate leading-tight font-bold text-slate-900 dark:text-white"
                                        title={chatTitle}
                                    >
                                        {chatTitle}
                                    </h2>
                                    <p className="flex items-center gap-1 text-xs text-slate-500">
                                        <span
                                            className={`h-2 w-2 rounded-full ${connected ? 'bg-green-500' : 'bg-red-500'}`}
                                        />
                                        {isStoreOwner ? 'Cliente' : 'Tienda'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-11 rounded-full"
                                    onClick={() => setIsPolling(!isPolling)}
                                    title={
                                        isPolling
                                            ? 'Pausar actualización'
                                            : 'Reanudar actualización'
                                    }
                                >
                                    <RefreshCw
                                        className={`h-4 w-4 ${isPolling ? 'animate-spin-slow text-primary' : 'text-slate-400'}`}
                                    />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent
                        className="min-h-0 flex-1 space-y-4 overflow-x-hidden overflow-y-auto bg-slate-50/50 p-3 sm:p-4 dark:bg-slate-950/20"
                        ref={scrollRef}
                    >
                        {localMessages.length > 0 ? (
                            localMessages.map((msg) => {
                                const isMe = msg.sender_id === auth.user.id;
                                return (
                                    <div
                                        key={msg.id || (msg as Message)._tempId}
                                        className={`flex w-full ${isMe ? 'justify-end' : 'justify-start'}`}
                                    >
                                        <div
                                            className={`max-w-[75%] rounded-2xl px-4 py-2.5 shadow-sm ${isMe ? 'rounded-tr-none bg-primary text-primary-foreground' : 'rounded-tl-none border border-slate-100 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white'} ${(msg as Message)._sending ? 'animate-pulse opacity-70' : ''} ${(msg as Message)._error ? 'ring-2 ring-red-400' : ''}`}
                                        >
                                            {msg.image_path &&
                                                !(msg as Message)._temp && (
                                                    <img
                                                        src={`/storage/${msg.image_path}`}
                                                        alt="Imagen"
                                                        className="mb-2 max-h-[300px] w-full rounded-lg object-cover"
                                                    />
                                                )}
                                            {(msg as Message)._temp &&
                                                (msg as Message).image_path && (
                                                    <img
                                                        src={
                                                            (msg as Message)
                                                                .image_path as string
                                                        }
                                                        alt="Preview"
                                                        className="mb-2 max-h-[300px] w-full rounded-lg object-cover opacity-80"
                                                    />
                                                )}
                                            {msg.body && (
                                                <p className="prose dark:prose-invert text-sm break-words">
                                                    {msg.body}
                                                </p>
                                            )}
                                            <p
                                                className={`mt-1 flex items-center justify-end gap-1 text-[10px] ${isMe ? 'text-emerald-100' : 'text-slate-400'}`}
                                            >
                                                <span>
                                                    {format(
                                                        new Date(
                                                            msg.created_at,
                                                        ),
                                                        'HH:mm',
                                                    )}
                                                </span>
                                                {isMe &&
                                                    (msg as Message)
                                                        ._sending && (
                                                        <Clock className="h-3 w-3 animate-spin text-yellow-300" />
                                                    )}
                                                {isMe &&
                                                    (msg as Message)._error && (
                                                        <AlertCircle className="h-3 w-3 text-red-300" />
                                                    )}
                                            </p>
                                            {(msg as Message)._error && (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setLocalMessages(
                                                            (prev) =>
                                                                prev.filter(
                                                                    (m) =>
                                                                        m.id !==
                                                                        msg.id,
                                                                ),
                                                        );
                                                    }}
                                                    className="mt-1 text-[10px] text-red-400 hover:text-red-300"
                                                >
                                                    Descartar
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
                                <h3 className="font-semibold text-slate-700 dark:text-slate-300">
                                    Comienza la conversación
                                </h3>
                                <p className="max-w-[200px] text-sm text-slate-500">
                                    Envía un mensaje para ponerte en contacto
                                    con {chatTitle}.
                                </p>
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
                                    <span>
                                        {typingUser} está escribiendo...
                                    </span>
                                </div>
                            </div>
                        )}
                    </CardContent>

                    <div className="shrink-0 border-t border-slate-100 bg-white p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:p-4 dark:border-slate-800 dark:bg-slate-900">
                        <form
                            onSubmit={handleSubmit}
                            encType="multipart/form-data"
                            className="flex flex-col gap-2"
                        >
                            {previewImage && (
                                <div className="relative max-w-full self-start overflow-hidden rounded-lg border border-slate-200">
                                    <img
                                        src={previewImage}
                                        alt="Preview"
                                        className="h-16 w-auto max-w-full object-cover sm:h-24"
                                    />
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="icon"
                                        className="absolute top-1 right-1 size-11 rounded-full"
                                        aria-label="Quitar imagen adjunta"
                                        onClick={clearImage}
                                    >
                                        <X className="h-3 w-3" />
                                    </Button>
                                </div>
                            )}
                            <div className="flex min-w-0 items-center gap-2">
                                <input
                                    type="file"
                                    accept="image/*"
                                    className="hidden"
                                    ref={fileInputRef}
                                    onChange={handleImageSelect}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-11 shrink-0 rounded-full"
                                    aria-label="Adjuntar imagen"
                                    onClick={() =>
                                        fileInputRef.current?.click()
                                    }
                                >
                                    <ImagePlus className="h-5 w-5" />
                                </Button>
                                <Input
                                    value={data.body}
                                    onChange={(e) => {
                                        setData('body', e.target.value);
                                        emitTyping();
                                    }}
                                    placeholder="Escribe un mensaje..."
                                    className="h-11 min-w-0 flex-1 rounded-full border-slate-200 px-3 text-base focus-visible:ring-primary dark:border-slate-800"
                                    aria-label="Mensaje"
                                    autoComplete="off"
                                    disabled={processing}
                                />
                                <Button
                                    type="submit"
                                    size="icon"
                                    className="size-11 shrink-0 rounded-full shadow-lg transition-transform active:scale-95"
                                    aria-label="Enviar mensaje"
                                    disabled={
                                        processing ||
                                        (!data.body.trim() && !data.image)
                                    }
                                >
                                    <Send className="h-5 w-5" />
                                </Button>
                            </div>
                        </form>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
