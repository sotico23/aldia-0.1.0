import { Head, usePage, router, Link } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { Heart, MessageCircle, Share2, MoreHorizontal, Pencil, Trash2, Copy, Send, Link2, Image, Camera, ThumbsUp, X as XIcon, LayoutGrid, List, MessageSquare } from 'lucide-react';
import { useState, useRef } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';

interface User {
    id: number;
    name: string;
    profile_photo_url: string | null;
}

interface Comentario {
    id: number;
    user_id: number;
    publicacion_id: number;
    parent_id: number | null;
    content: string;
    image_url: string | null;
    likes_count: number;
    hearts_count: number;
    created_at: string;
    user?: User;
    replies?: Comentario[];
}

interface Publicacion {
    id: number;
    user_id: number;
    content: string;
    image_path: string | null;
    image_url: string | null;
    likes_count: number;
    hearts_count: number;
    comments_count: number;
    created_at: string;
    user?: User;
    comentarios?: Comentario[];
}

interface Props {
    publicaciones: {
        data: Publicacion[];
        current_page: number;
        last_page: number;
        total: number;
    };
}

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Comunidad', href: '/comunidad' },
];

export default function ComunidadIndex({ publicaciones }: Props) {
    const { auth } = usePage().props as { auth: { user: User | null } };
    const { hasPermission } = usePermissions();
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const canCreate = hasPermission('general.comunidad.create');
    const canEdit = hasPermission('general.comunidad.edit');
    const canDelete = hasPermission('general.comunidad.delete');
    const [comentariosAbiertos, setComentariosAbiertos] = useState<number[]>(
        [],
    );
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [editingPublicacion, setEditingPublicacion] = useState<Publicacion | null>(null);
    const [editContent, setEditContent] = useState('');
    const [editImage, setEditImage] = useState<File | null>(null);
    const [editImagePreview, setEditImagePreview] = useState<string | null>(null);
    const [procesando, setProcesando] = useState(false);
    const [replyingTo, setReplyingTo] = useState<number | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const editImageInputRef = useRef<HTMLInputElement>(null);

    const handleEdit = (publicacion: Publicacion) => {
        setEditingPublicacion(publicacion);
        setEditContent(publicacion.content);
        setEditImage(null);
        setEditImagePreview(publicacion.image_url);
        setEditDialogOpen(true);
    };

    const handleEditImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setEditImage(file);
            const reader = new FileReader();
            reader.onloadend = () => {
                setEditImagePreview(reader.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const updatePublicacion = () => {
        if (!editingPublicacion) return;
        setProcesando(true);
        
        const data = new FormData();
        data.append('content', editContent);
        if (editImage) {
            data.append('image', editImage);
        }
        data.append('_method', 'PUT');

        router.post(`/publicaciones/${editingPublicacion.id}`, data, {
            forceFormData: true,
            onSuccess: () => {
                setEditDialogOpen(false);
                setProcesando(false);
                setEditImage(null);
                setEditImagePreview(null);
            },
            onError: () => setProcesando(false)
        });
    };

    const toggleComentarios = (publicacionId: number) => {
        setComentariosAbiertos((prev) =>
            prev.includes(publicacionId)
                ? prev.filter((id) => id !== publicacionId)
                : [...prev, publicacionId],
        );
    };

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    const CommentForm = ({ 
        publicacionId, 
        parentId = null, 
        onSuccess,
        placeholder = "Escribe un comentario..."
    }: { 
        publicacionId: number; 
        parentId?: number | null; 
        onSuccess?: () => void;
        placeholder?: string;
    }) => {
        const [content, setContent] = useState('');
        const [image, setImage] = useState<File | null>(null);
        const [imagePreview, setImagePreview] = useState<string | null>(null);
        const [submitting, setSubmitting] = useState(false);
        const fileInputRef = useRef<HTMLInputElement>(null);

        const handleSubmit = () => {
            if (!content.trim() && !image) return;
            setSubmitting(true);

            const formData = new FormData();
            formData.append('content', content);
            if (image) formData.append('image', image);
            if (parentId) formData.append('parent_id', parentId.toString());

            router.post(`/publicaciones/${publicacionId}/comment`, formData, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setContent('');
                    setImage(null);
                    setImagePreview(null);
                    setSubmitting(false);
                    if (onSuccess) onSuccess();
                },
                onError: () => setSubmitting(false)
            });
        };

        const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
            const file = e.target.files?.[0];
            if (file) {
                setImage(file);
                const reader = new FileReader();
                reader.onloadend = () => setImagePreview(reader.result as string);
                reader.readAsDataURL(file);
            }
        };

        return (
            <div className="flex gap-2 p-3 bg-muted/30 rounded-2xl border border-border/50">
                <Avatar className="h-9 w-9 shrink-0">
                    <AvatarImage src={auth.user?.profile_photo_url || ''} />
                    <AvatarFallback className="text-xs">{getInitials(auth.user?.name || 'U')}</AvatarFallback>
                </Avatar>
                <div className="flex-1 space-y-3">
                    <div className="flex flex-col gap-2">
                        <Textarea
                            value={content}
                            onChange={(e) => setContent(e.target.value)}
                            placeholder={placeholder}
                            className="min-h-[50px] resize-none rounded-xl bg-background border-border/50 focus-visible:ring-primary shadow-sm"
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    handleSubmit();
                                }
                            }}
                        />
                        
                        {imagePreview && (
                            <div className="relative group w-32 aspect-square rounded-xl overflow-hidden border bg-muted/20">
                                <img src={imagePreview} className="h-full w-full object-contain" />
                                <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                    <Button 
                                        variant="destructive" 
                                        size="sm" 
                                        className="h-8 w-8 p-0 rounded-full"
                                        onClick={() => { setImage(null); setImagePreview(null); }}
                                    >
                                        <XIcon className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="flex items-center justify-between">
                        <div className="flex gap-1">
                            <input 
                                type="file" 
                                ref={fileInputRef} 
                                className="hidden" 
                                accept="image/*" 
                                onChange={handleImageChange}
                            />
                            <Button 
                                type="button" 
                                variant="ghost" 
                                size="sm" 
                                className="h-9 gap-2 text-muted-foreground hover:text-primary transition-colors"
                                onClick={() => fileInputRef.current?.click()}
                            >
                                <Camera className="h-4 w-4" />
                                <span className="text-xs font-medium">Añadir foto</span>
                            </Button>
                        </div>
                        
                        <Button 
                            size="sm" 
                            className="h-9 px-4 rounded-xl font-semibold shadow-sm"
                            disabled={submitting || (!content.trim() && !image)}
                            onClick={handleSubmit}
                        >
                            <Send className="mr-2 h-4 w-4" />
                            {submitting ? 'Publicando...' : 'Publicar'}
                        </Button>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Publicaciones de la Comunidad" />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b pb-4">
                    <h1 className="text-2xl font-bold">
                        Publicaciones de la Comunidad
                    </h1>
                    <div className="flex items-center gap-2">
                        <div className="flex items-center gap-1 rounded-lg border p-0.5">
                            <Button
                                variant={viewMode === 'table' ? 'default' : 'ghost'}
                                size="sm"
                                className="h-8 w-8 p-0"
                                onClick={() => setViewMode('table')}
                            >
                                <List className="h-4 w-4" />
                            </Button>
                            <Button
                                variant={viewMode === 'cards' ? 'default' : 'ghost'}
                                size="sm"
                                className="h-8 w-8 p-0"
                                onClick={() => setViewMode('cards')}
                            >
                                <LayoutGrid className="h-4 w-4" />
                            </Button>
                        </div>
                        <span className="text-sm text-muted-foreground bg-muted px-2 py-1 rounded-full">
                            {publicaciones.total} publicaciones
                        </span>
                    </div>
                </div>

                {viewMode === 'table' ? (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-muted/5 border-b text-[11px] font-black uppercase tracking-wider text-muted-foreground">
                                    <th className="px-4 py-3 text-left">Autor</th>
                                    <th className="px-4 py-3 text-left">Contenido</th>
                                    <th className="px-4 py-3 text-center">Reacciones</th>
                                    <th className="px-4 py-3 text-center">Comentarios</th>
                                    <th className="px-4 py-3 text-center">Fecha</th>
                                    <th className="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-muted/50 font-medium">
                                {publicaciones.data.map((publicacion) => (
                                    <tr key={publicacion.id} className="group transition-colors hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <Avatar className="h-7 w-7">
                                                    <AvatarImage src={publicacion.user?.profile_photo_url || ''} />
                                                    <AvatarFallback className="text-[10px]">{publicacion.user?.name?.charAt(0) || 'U'}</AvatarFallback>
                                                </Avatar>
                                                <span className="text-xs font-semibold">{publicacion.user?.name || 'Usuario'}</span>
                                            </div>
                                        </td>
                                        <td className="max-w-xs truncate px-4 py-3 text-xs">{publicacion.content}</td>
                                        <td className="px-4 py-3 text-center">
                                            <span className="text-xs">{publicacion.likes_count + publicacion.hearts_count}</span>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <span className="text-xs">{publicacion.comments_count}</span>
                                        </td>
                                        <td className="px-4 py-3 text-center text-xs">
                                            {format(new Date(publicacion.created_at), "d MMM yyyy", { locale: es })}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                {canEdit && auth.user?.id === publicacion.user_id && (
                                                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => handleEdit(publicacion)}>
                                                        <Pencil className="h-3.5 w-3.5" />
                                                    </Button>
                                                )}
                                                {canDelete && auth.user?.id === publicacion.user_id && (
                                                    <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive" onClick={() => { if (confirm('¿Eliminar?')) router.delete(`/publicaciones/${publicacion.id}`); }}>
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {publicaciones.data.length === 0 && (
                            <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
                                <MessageSquare className="mb-3 h-12 w-12 opacity-20" />
                                <p>No hay publicaciones en la comunidad todavía.</p>
                            </div>
                        )}
                    </div>
                ) : (
                publicaciones.data.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-muted-foreground">
                            No hay publicaciones en la comunidad todavía.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-6">
                        {publicaciones.data.map((publicacion) => (
                            <Card key={publicacion.id} id={`publicacion-${publicacion.id}`}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-3">
                                            <Link href={`/perfil/${publicacion.user?.id}`}>
                                                <Avatar>
                                                    <AvatarImage
                                                        src={publicacion.user?.profile_photo_url || ''}
                                                        alt={publicacion.user?.name || 'Usuario'}
                                                    />
                                                    <AvatarFallback>
                                                        {publicacion.user?.name?.charAt(0) || 'U'}
                                                    </AvatarFallback>
                                                </Avatar>
                                            </Link>
                                            <div className="flex flex-col">
                                                <Link href={`/perfil/${publicacion.user?.id}`} className="text-sm font-semibold text-foreground hover:underline">
                                                    {publicacion.user?.name || 'Usuario'}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    {format(
                                                        new Date(
                                                            publicacion.created_at,
                                                        ),
                                                        "d 'de' MMMM 'de' yyyy",
                                                        { locale: es },
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        {auth.user?.id === publicacion.user_id && (
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-8 w-8 p-0"
                                                    >
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    {canEdit && (
                                                        <DropdownMenuItem onClick={() => handleEdit(publicacion)}>
                                                            <Pencil className="mr-2 h-4 w-4" />
                                                            Editar
                                                        </DropdownMenuItem>
                                                    )}
                                                    {canDelete && (
                                                        <DropdownMenuItem 
                                                            className="text-destructive"
                                                            onClick={() => {
                                                                if (confirm('¿Estás seguro de eliminar esta publicación?')) {
                                                                    router.delete(`/publicaciones/${publicacion.id}`);
                                                                }
                                                            }}
                                                        >
                                                            <Trash2 className="mr-2 h-4 w-4" />
                                                            Eliminar
                                                        </DropdownMenuItem>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <p className="whitespace-pre-wrap">
                                        {publicacion.content}
                                    </p>

                                    {publicacion.image_url && (
                                        <div className="overflow-hidden rounded-lg border">
                                            <img
                                                src={publicacion.image_url}
                                                alt="Publicación"
                                                className="h-auto max-h-96 w-full object-cover"
                                            />
                                        </div>
                                    )}

                                    <div className="flex items-center gap-4 pt-2">
                                        <div className="flex items-center gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="gap-1 px-2"
                                                onClick={() => router.post(`/publicaciones/${publicacion.id}/react`, { type: 'like' }, { preserveScroll: true })}
                                            >
                                                <ThumbsUp className="h-4 w-4" />
                                                <span>{publicacion.likes_count}</span>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="gap-1 px-2 text-rose-500"
                                                onClick={() => router.post(`/publicaciones/${publicacion.id}/react`, { type: 'heart' }, { preserveScroll: true })}
                                            >
                                                <Heart className="h-4 w-4 fill-current" />
                                                <span>{publicacion.hearts_count}</span>
                                            </Button>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="gap-1"
                                            onClick={() => {
                                                if (!auth.user) {
                                                    router.visit('/login');
                                                    return;
                                                }
                                                toggleComentarios(publicacion.id);
                                            }}
                                        >
                                            <MessageCircle className="h-4 w-4" />
                                            <span>
                                                {publicacion.comments_count}
                                            </span>
                                        </Button>

                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="gap-1"
                                                >
                                                    <Share2 className="h-4 w-4" />
                                                    <span>Compartir</span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="start" className="w-56">
                                                <DropdownMenuItem
                                                    onClick={() => {
                                                        const url = `${window.location.origin}/comunidad?id=${publicacion.id}`;
                                                        navigator.clipboard.writeText(url);
                                                    }}
                                                >
                                                    <Copy className="h-4 w-4" />
                                                    <span>Copiar enlace</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <a
                                                        href={`https://wa.me/?text=${encodeURIComponent(
                                                            `Mira esta publicación en la comunidad: ${window.location.origin}/comunidad?id=${publicacion.id}`,
                                                        )}`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        <Send className="h-4 w-4" />
                                                        <span>WhatsApp</span>
                                                    </a>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <a
                                                        href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(
                                                            `${window.location.origin}/comunidad?id=${publicacion.id}`,
                                                        )}`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        <Link2 className="h-4 w-4" />
                                                        <span>Facebook</span>
                                                    </a>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>

                                    {comentariosAbiertos.includes(publicacion.id) && (
                                        <div className="space-y-4 border-t pt-4">
                                            {/* Formulario para nuevo comentario principal */}
                                            <CommentForm publicacionId={publicacion.id} />

                                            {publicacion.comentarios && publicacion.comentarios.length > 0 && (
                                                <div className="space-y-4">
                                                    {publicacion.comentarios.map((comentario) => (
                                                        <div key={comentario.id} className="space-y-2">
                                                            <div className="flex gap-2 text-sm">
                                                                <Avatar className="h-8 w-8 shrink-0">
                                                                    <AvatarImage src={comentario.user?.profile_photo_url ?? undefined} />
                                                                    <AvatarFallback className="text-xs">
                                                                        {getInitials(comentario.user?.name || 'U')}
                                                                    </AvatarFallback>
                                                                </Avatar>
                                                                <div className="flex-1 space-y-1">
                                                                    <div className="rounded-2xl bg-muted px-3 py-2">
                                                                        <p className="text-xs font-bold">
                                                                            {comentario.user?.name || 'Usuario'}
                                                                        </p>
                                                                        <p className="text-sm">
                                                                            {comentario.content}
                                                                        </p>
                                                                        {comentario.image_url && (
                                                                            <div className="mt-2 overflow-hidden rounded-lg border w-fit">
                                                                                <img 
                                                                                    src={comentario.image_url} 
                                                                                    alt="Imagen de comentario" 
                                                                                    className="max-h-64 max-w-full object-contain"
                                                                                />
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                    <div className="flex items-center gap-4 px-2 text-xs font-bold text-muted-foreground">
                                                                        <div className="flex items-center gap-2">
                                                                            <button 
                                                                                className="flex items-center gap-1 hover:text-primary transition-colors"
                                                                                onClick={() => router.post(`/comentarios/${comentario.id}/react`, { type: 'like' }, { preserveScroll: true })}
                                                                            >
                                                                                Me gusta 
                                                                                {comentario.likes_count > 0 && (
                                                                                    <span className="flex items-center gap-0.5 text-primary">
                                                                                        <ThumbsUp className="h-3 w-3" />
                                                                                        {comentario.likes_count}
                                                                                    </span>
                                                                                )}
                                                                            </button>
                                                                            <button 
                                                                                className="flex items-center gap-1 hover:text-rose-500 transition-colors"
                                                                                onClick={() => router.post(`/comentarios/${comentario.id}/react`, { type: 'heart' }, { preserveScroll: true })}
                                                                            >
                                                                                <Heart className={`h-3 w-3 ${comentario.hearts_count > 0 ? 'fill-rose-500 text-rose-500' : ''}`} />
                                                                                {comentario.hearts_count > 0 && (
                                                                                    <span className="text-rose-500">
                                                                                        {comentario.hearts_count}
                                                                                    </span>
                                                                                )}
                                                                            </button>
                                                                        </div>
                                                                        <button 
                                                                            className="hover:underline"
                                                                            onClick={() => setReplyingTo(replyingTo === comentario.id ? null : comentario.id)}
                                                                        >
                                                                            Responder
                                                                        </button>
                                                                        <span className="font-normal opacity-60">
                                                                            {format(new Date(comentario.created_at), "d 'de' MMM", { locale: es })}
                                                                        </span>
                                                                    </div>

                                                                    {/* Formulario de respuesta */}
                                                                    {replyingTo === comentario.id && (
                                                                        <div className="mt-2">
                                                                            <CommentForm 
                                                                                publicacionId={publicacion.id} 
                                                                                parentId={comentario.id}
                                                                                placeholder={`Responde a ${comentario.user?.name.split(' ')[0]}...`}
                                                                                onSuccess={() => setReplyingTo(null)}
                                                                            />
                                                                        </div>
                                                                    )}

                                                                    {/* Respuestas anidadas */}
                                                                    {comentario.replies && comentario.replies.length > 0 && (
                                                                        <div className="mt-2 space-y-3 border-l-2 pl-4">
                                                                            {comentario.replies.map((reply) => (
                                                                                <div key={reply.id} className="flex gap-2 text-sm">
                                                                                    <Avatar className="h-6 w-6 shrink-0">
                                                                                        <AvatarImage src={reply.user?.profile_photo_url ?? undefined} />
                                                                                        <AvatarFallback className="text-xs">
                                                                                            {getInitials(reply.user?.name || 'U')}
                                                                                        </AvatarFallback>
                                                                                    </Avatar>
                                                                                    <div className="flex-1 space-y-1">
                                                                                        <div className="rounded-2xl bg-muted px-3 py-2">
                                                                                            <p className="text-xs font-bold">
                                                                                                {reply.user?.name || 'Usuario'}
                                                                                            </p>
                                                                                            <p className="text-sm">
                                                                                                {reply.content}
                                                                                            </p>
                                                                                            {reply.image_url && (
                                                                                                <div className="mt-2 overflow-hidden rounded-lg border w-fit">
                                                                                                    <img 
                                                                                                        src={reply.image_url} 
                                                                                                        alt="Imagen de respuesta" 
                                                                                                        className="max-h-48 max-w-full object-contain"
                                                                                                    />
                                                                                                </div>
                                                                                            )}
                                                                                        </div>
                                                                                        <div className="flex items-center gap-4 px-2 text-xs font-bold text-muted-foreground">
                                                                                            <div className="flex items-center gap-2">
                                                                                                <button 
                                                                                                    className="flex items-center gap-1 hover:text-primary transition-colors"
                                                                                                    onClick={() => router.post(`/comentarios/${reply.id}/react`, { type: 'like' }, { preserveScroll: true })}
                                                                                                >
                                                                                                    Me gusta
                                                                                                    {reply.likes_count > 0 && (
                                                                                                        <span className="flex items-center gap-0.5 text-primary">
                                                                                                            <ThumbsUp className="h-3 w-3" />
                                                                                                            {reply.likes_count}
                                                                                                        </span>
                                                                                                    )}
                                                                                                </button>
                                                                                                <button 
                                                                                                    className="flex items-center gap-1 hover:text-rose-500 transition-colors"
                                                                                                    onClick={() => router.post(`/comentarios/${reply.id}/react`, { type: 'heart' }, { preserveScroll: true })}
                                                                                                >
                                                                                                    <Heart className={`h-3 w-3 ${reply.hearts_count > 0 ? 'fill-rose-500 text-rose-500' : ''}`} />
                                                                                                    {reply.hearts_count > 0 && (
                                                                                                        <span className="text-rose-500">
                                                                                                            {reply.hearts_count}
                                                                                                        </span>
                                                                                                    )}
                                                                                                </button>
                                                                                            </div>
                                                                                            <span className="font-normal opacity-60">
                                                                                                {format(new Date(reply.created_at), "d 'de' MMM", { locale: es })}
                                                                                            </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            ))}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ))}
            </div>

            {/* Modal de Edición */}
            <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
                <DialogContent className="overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Editar Publicación</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="content">Contenido</Label>
                            <Textarea
                                id="content"
                                value={editContent}
                                onChange={(e) => setEditContent(e.target.value)}
                                placeholder="Escribe algo..."
                                className="min-h-[120px]"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label>Imagen</Label>
                            <input
                                type="file"
                                ref={editImageInputRef}
                                className="hidden"
                                accept="image/*"
                                onChange={handleEditImageChange}
                            />
                            
                            {editImagePreview ? (
                                <div className="relative group aspect-video w-full overflow-hidden rounded-lg bg-muted/40 border">
                                    <img
                                        src={editImagePreview}
                                        alt="Vista previa"
                                        className="h-full w-full object-contain"
                                    />
                                    <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => editImageInputRef.current?.click()}
                                        >
                                            <Camera className="mr-2 h-4 w-4" />
                                            Cambiar foto
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-full h-24 border-dashed"
                                    onClick={() => editImageInputRef.current?.click()}
                                >
                                    <Image className="mr-2 h-5 w-5" />
                                    Añadir imagen
                                </Button>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setEditDialogOpen(false)}
                            disabled={procesando}
                        >
                            Cancelar
                        </Button>
                        <Button onClick={updatePublicacion} disabled={procesando}>
                            {procesando ? 'Guardando...' : 'Guardar Cambios'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
