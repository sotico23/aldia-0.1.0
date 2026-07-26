import { Head, Link, useForm } from '@inertiajs/react';
import axios from 'axios';
import {
    LayoutGrid,
    List,
    Plus,
    Gift,
    Users,
    Trophy,
    Calendar,
    MoreVertical,
    Eye,
    Pencil,
    X,
    Upload
} from 'lucide-react';
import { useState, useRef } from 'react';
import { toast } from 'sonner';
import { BulkActions } from '@/components/shared/BulkActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DateTimePicker } from '@/components/ui/date-time-picker';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import '@/lib/utils';

interface Raffle {
    id: number;
    title: string;
    slug: string;
    type: string;
    status: string;
    image_url: string | null;
    cover_image: string | null;
    description: string | null;
    participants_count: number;
    prizes_count: number;
    max_participants: number | null;
    min_participants: number | null;
    ticket_price: number;
    start_date: string | null;
    end_date: string | null;
    created_at: string;
    prizes: any[];
    allow_multiple_entries: boolean;
    max_entries_per_user: number;
    show_winners: boolean;
}

interface Props {
    raffles: {
        data: Raffle[];
        links: any[];
    };
}

interface PrizeFormData {
    name: string;
    description: string;
    quantity: number;
    estimated_value: number | null;
    image: string | null;
    imageFile: File | null;
}

const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800',
    published: 'bg-blue-100 text-blue-800',
    active: 'bg-green-100 text-green-800',
    completed: 'bg-purple-100 text-purple-800',
    cancelled: 'bg-red-100 text-red-800',
};

const typeLabels: Record<string, string> = {
    raffle: 'Rifa',
    draw: 'Sorteo',
    competition: 'Concurso',
};

function ImageUploadButton({
    image,
    onChange,
    label = 'Subir imagen',
}: {
    image: string | null;
    onChange: (file: File | null) => void;
    label?: string;
}) {
    const inputRef = useRef<HTMLInputElement>(null);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            onChange(file);
        }
    };

    return (
        <div
            className="relative h-32 w-full cursor-pointer overflow-hidden rounded-lg border-2 border-dashed transition-colors hover:border-blue-400"
            onClick={() => inputRef.current?.click()}
        >
            {image ? (
                <>
                    <img
                        src={image}
                        alt="Preview"
                        className="h-full w-full object-cover"
                    />
                    <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity hover:opacity-100">
                        <span className="text-sm text-white">Cambiar</span>
                    </div>
                </>
            ) : (
                <div className="flex h-full flex-col items-center justify-center text-zinc-400">
                    <Upload className="mb-1 h-6 w-6" />
                    <span className="text-xs">{label}</span>
                </div>
            )}
            <input
                ref={inputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={handleFileChange}
            />
        </div>
    );
}

export default function RafflesIndex({ raffles }: Props) {
    const { code: countryCode, currency } = useCountry();
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('rifas.rifas.create');
    const canEdit = hasPermission('rifas.rifas.edit');
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const canDelete = hasPermission('rifas.rifas.delete');

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [editingRaffle, setEditingRaffle] = useState<Raffle | null>(null);
    const [coverImage, setCoverImage] = useState<string | null>(null);
    const [coverImageFile, setCoverImageFile] = useState<File | null>(null);
    const [prizes, setPrizes] = useState<PrizeFormData[]>([
        {
            name: '',
            description: '',
            quantity: 1,
            estimated_value: null,
            image: null,
            imageFile: null,
        },
    ]);
    const [coverImageEdit, setCoverImageEdit] = useState<string | null>(null);
    const [coverImageFileEdit, setCoverImageFileEdit] = useState<File | null>(
        null,
    );
    const [prizesEdit, setPrizesEdit] = useState<any[]>([]);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');


    const {
        data: createData,
        setData: setCreateData,
        reset: resetCreate,
    } = useForm({
        title: '',
        description: '',
        type: 'raffle',
        status: 'draft',
        max_participants: null as number | null,
        min_participants: null as number | null,
        ticket_price: 0,
        allow_multiple_entries: false,
        max_entries_per_user: 1,
        start_date: '',
        end_date: '',
        show_winners: true,
    });

    const [isCreating, setIsCreating] = useState(false);

    const addPrize = () => {
        if (prizes.length < 5) {
            setPrizes([
                ...prizes,
                {
                    name: '',
                    description: '',
                    quantity: 1,
                    estimated_value: null,
                    image: null,
                    imageFile: null,
                },
            ]);
        }
    };

    const removePrize = (index: number) => {
        if (prizes.length > 1) {
            setPrizes(prizes.filter((_, i) => i !== index));
        }
    };

    const updatePrize = (
        index: number,
        field: keyof PrizeFormData,
        value: any,
    ) => {
        const newPrizes = [...prizes];
        (newPrizes[index] as any)[field] = value;
        setPrizes(newPrizes);
    };

    const handleCreate = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsCreating(true);

        const formData = new FormData();
        formData.append('title', createData.title);
        formData.append('description', createData.description);
        formData.append('type', createData.type);
        formData.append('status', createData.status);
        formData.append(
            'max_participants',
            createData.max_participants?.toString() || '',
        );
        formData.append(
            'min_participants',
            createData.min_participants?.toString() || '',
        );
        formData.append('ticket_price', createData.ticket_price.toString());
        formData.append(
            'allow_multiple_entries',
            createData.allow_multiple_entries ? '1' : '0',
        );
        formData.append(
            'max_entries_per_user',
            createData.max_entries_per_user.toString(),
        );
        formData.append('start_date', createData.start_date);
        formData.append('end_date', createData.end_date);
        formData.append('show_winners', createData.show_winners ? '1' : '0');

        if (coverImageFile) {
            formData.append('cover_image', coverImageFile);
        }

        prizes.forEach((prize, index) => {
            formData.append(`prizes[${index}][name]`, prize.name);
            formData.append(`prizes[${index}][description]`, prize.description);
            formData.append(
                `prizes[${index}][quantity]`,
                prize.quantity.toString(),
            );
            formData.append(
                `prizes[${index}][estimated_value]`,
                prize.estimated_value?.toString() || '',
            );
            if (prize.imageFile) {
                formData.append(`prizes[${index}][image]`, prize.imageFile);
            }
        });

        try {
            await axios.post('/raffles', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            toast.success('Rifa creada exitosamente');
            setIsCreateOpen(false);
            resetCreate();
            setCoverImage(null);
            setCoverImageFile(null);
            setPrizes([
                {
                    name: '',
                    description: '',
                    quantity: 1,
                    estimated_value: null,
                    image: null,
                    imageFile: null,
                },
            ]);
            window.location.reload();
        } catch (error: any) {
            console.error('Error creating raffle:', error);
            const message =
                error.response?.data?.message ||
                error.response?.data?.error ||
                'Error al crear la rifa';
            toast.error(message);
        } finally {
            setIsCreating(false);
        }
    };

    const openEditModal = (raffle: Raffle) => {
        setEditingRaffle(raffle);
        setCoverImageEdit(raffle.cover_image);
        setPrizesEdit(
            raffle.prizes?.map((p: any) => ({
                id: p.id,
                name: p.name,
                description: p.description || '',
                quantity: p.quantity,
                estimated_value: p.estimated_value,
                image: p.image_url,
                imageFile: null,
            })) || [],
        );
        setCreateData({
            title: raffle.title || '',
            description: raffle.description || '',
            type: raffle.type || 'raffle',
            status: raffle.status || 'draft',
            max_participants: raffle.max_participants,
            min_participants: raffle.min_participants,
            ticket_price: raffle.ticket_price || 0,
            allow_multiple_entries: raffle.allow_multiple_entries || false,
            max_entries_per_user: raffle.max_entries_per_user || 1,
            start_date: raffle.start_date || '',
            end_date: raffle.end_date || '',
            show_winners: raffle.show_winners ?? true,
        });
        setIsEditOpen(true);
    };

    const handleEdit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingRaffle) return;

        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('title', createData.title);
        formData.append('description', createData.description);
        formData.append('type', createData.type);
        formData.append('status', createData.status);
        formData.append(
            'max_participants',
            createData.max_participants?.toString() || '',
        );
        formData.append(
            'min_participants',
            createData.min_participants?.toString() || '',
        );
        formData.append('ticket_price', createData.ticket_price.toString());
        formData.append(
            'allow_multiple_entries',
            createData.allow_multiple_entries ? '1' : '0',
        );
        formData.append(
            'max_entries_per_user',
            createData.max_entries_per_user.toString(),
        );
        formData.append('start_date', createData.start_date);
        formData.append('end_date', createData.end_date);
        formData.append('show_winners', createData.show_winners ? '1' : '0');

        if (coverImageFileEdit) {
            formData.append('cover_image', coverImageFileEdit);
        }

        prizesEdit.forEach((prize, index) => {
            formData.append(`prizes[${index}][id]`, prize.id?.toString() || '');
            formData.append(`prizes[${index}][name]`, prize.name);
            formData.append(
                `prizes[${index}][description]`,
                prize.description || '',
            );
            formData.append(
                `prizes[${index}][quantity]`,
                prize.quantity?.toString() || '1',
            );
            formData.append(
                `prizes[${index}][estimated_value]`,
                prize.estimated_value?.toString() || '',
            );
            if (prize.imageFile) {
                formData.append(`prizes[${index}][image]`, prize.imageFile);
            }
        });

        try {
            await axios.post(`/raffles/${editingRaffle.id}`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setIsEditOpen(false);
            setEditingRaffle(null);
            window.location.reload();
        } catch (error) {
            console.error('Error updating raffle:', error);
        }
    };

    return (
        <AppLayout
            breadcrumbs={[{ title: 'Rifas y Sorteos', href: '/raffles' }]}
        >
            <Head title="Rifas y Sorteos" />

            <div className="mx-auto max-w-7xl p-4 sm:p-6">
                <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-zinc-900 dark:text-white">
                            Rifas y Sorteos
                        </h1>
                        <p className="mt-1 text-zinc-500 dark:text-zinc-400">
                            Crea y gestiona tus rifas, sorteos y concursos
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 items-center">
                        <BulkActions
                            baseUrl="/raffles"
                            modelName="Rifas"
                        />
                        {canCreate && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Nueva Rifa
                            </Button>
                        )}
                    </div>
                </div>

                <div className="mb-8 grid grid-cols-1 gap-6 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Rifas
                            </CardTitle>
                            <Gift className="h-4 w-4 text-zinc-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {raffles.data.length}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Activas
                            </CardTitle>
                            <Trophy className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {
                                    raffles.data.filter(
                                        (r) => r.status === 'active',
                                    ).length
                                }
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Completadas
                            </CardTitle>
                            <Users className="h-4 w-4 text-purple-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {
                                    raffles.data.filter(
                                        (r) => r.status === 'completed',
                                    ).length
                                }
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Borradores
                            </CardTitle>
                            <Calendar className="h-4 w-4 text-zinc-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {
                                    raffles.data.filter(
                                        (r) => r.status === 'draft',
                                    ).length
                                }
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Mis Rifas</CardTitle>
                                <CardDescription>
                                    Lista de todas las rifas y sorteos creados
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-1 rounded-lg border p-1">
                                <Button
                                    variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                                    size="sm"
                                    onClick={() => setViewMode('table')}
                                >
                                    <List className="h-4 w-4" />
                                </Button>
                                <Button
                                    variant={viewMode === 'cards' ? 'secondary' : 'ghost'}
                                    size="sm"
                                    onClick={() => setViewMode('cards')}
                                >
                                    <LayoutGrid className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {viewMode === 'table' ? (
                            <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-[300px]">
                                            Título
                                        </TableHead>
                                        <TableHead>Tipo</TableHead>
                                        <TableHead>Estado</TableHead>
                                        <TableHead>Participantes</TableHead>
                                        <TableHead>Premios</TableHead>
                                        <TableHead>Duración</TableHead>
                                        <TableHead className="text-right">
                                            Acciones
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {raffles.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="py-10 text-center text-zinc-500"
                                            >
                                                No hay rifas creadas. ¡Crea tu
                                                primera rifa!
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        raffles.data.map((raffle) => (
                                            <TableRow key={raffle.id}>
                                                <TableCell className="font-medium">
                                                    <div className="flex items-center gap-3">
                                                        {raffle.cover_image ? (
                                                            <img
                                                                src={
                                                                    raffle.cover_image
                                                                }
                                                                alt={raffle.title}
                                                                className="h-10 w-10 rounded-lg object-cover"
                                                            />
                                                        ) : (
                                                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                                                <Gift className="h-5 w-5 text-zinc-400" />
                                                            </div>
                                                        )}
                                                        <div>
                                                            <div className="font-medium">
                                                                {raffle.title}
                                                            </div>
                                                            <div className="text-xs text-zinc-500">
                                                                /{raffle.slug}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="text-sm">
                                                        {typeLabels[raffle.type]}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        className={
                                                            statusColors[
                                                                raffle.status
                                                            ]
                                                        }
                                                    >
                                                        {raffle.status}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {raffle.participants_count}
                                                </TableCell>
                                                <TableCell>
                                                    {raffle.prizes_count}
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-500">
                                                    {raffle.start_date &&
                                                    raffle.end_date ? (
                                                        <>
                                                            {new Date(
                                                                raffle.start_date,
                                                            ).toLocaleDateString(
                                                                currency.locale,
                                                            )}{' '}
                                                            -
                                                            {new Date(
                                                                raffle.end_date,
                                                            ).toLocaleDateString(
                                                                currency.locale,
                                                            )}
                                                        </>
                                                    ) : (
                                                        'Sin fecha'
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                <MoreVertical className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            {canEdit && (
                                                                <DropdownMenuItem
                                                                    onClick={() =>
                                                                        openEditModal(
                                                                            raffle,
                                                                        )
                                                                    }
                                                                >
                                                                    <Pencil className="mr-2 h-4 w-4" />
                                                                    Editar
                                                                </DropdownMenuItem>
                                                            )}
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={`/rifa/${raffle.slug}`}
                                                                    target="_blank"
                                                                >
                                                                    <Eye className="mr-2 h-4 w-4" />
                                                                    Ver Pública
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={`/rifa/${raffle.slug}/ganadores`}
                                                                    target="_blank"
                                                                >
                                                                    <Trophy className="mr-2 h-4 w-4" />
                                                                    Ver Ganadores
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {raffles.data.length === 0 ? (
                                    <div className="col-span-full flex flex-col items-center justify-center py-12 text-center text-zinc-500">
                                        <Gift className="mb-2 h-12 w-12 text-zinc-300" />
                                        <p>No hay rifas creadas. ¡Crea tu primera rifa!</p>
                                    </div>
                                ) : (
                                    raffles.data.map((raffle) => (
                                        <Card key={raffle.id} className="overflow-hidden">
                                            <CardContent className="p-0">
                                                {raffle.cover_image ? (
                                                    <div className="aspect-video w-full overflow-hidden">
                                                        <img src={raffle.cover_image} alt={raffle.title} className="h-full w-full object-cover" />
                                                    </div>
                                                ) : (
                                                    <div className="flex aspect-video w-full items-center justify-center bg-zinc-100 dark:bg-zinc-800">
                                                        <Gift className="h-10 w-10 text-zinc-300" />
                                                    </div>
                                                )}
                                                <div className="space-y-3 p-4">
                                                    <div>
                                                        <h3 className="font-semibold">{raffle.title}</h3>
                                                        <p className="text-xs text-zinc-500">/{raffle.slug}</p>
                                                    </div>
                                                    <div className="flex flex-wrap gap-2">
                                                        <span className="text-sm">{typeLabels[raffle.type]}</span>
                                                        <Badge className={statusColors[raffle.status]}>
                                                            {raffle.status}
                                                        </Badge>
                                                    </div>
                                                    <div className="flex items-center justify-between text-sm text-zinc-500">
                                                        <span>{raffle.participants_count} participantes</span>
                                                        <span>{raffle.prizes_count} premios</span>
                                                    </div>
                                                    <div className="text-xs text-zinc-400">
                                                        {raffle.start_date && raffle.end_date
                                                            ? `${new Date(raffle.start_date).toLocaleDateString(currency.locale)} - ${new Date(raffle.end_date).toLocaleDateString(currency.locale)}`
                                                            : 'Sin fecha'}
                                                    </div>
                                                    <div className="flex justify-end">
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button variant="ghost" size="sm">
                                                                    <MoreVertical className="h-4 w-4" />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end">
                                                                {canEdit && (
                                                                    <DropdownMenuItem onClick={() => openEditModal(raffle)}>
                                                                        <Pencil className="mr-2 h-4 w-4" />
                                                                        Editar
                                                                    </DropdownMenuItem>
                                                                )}
                                                                <DropdownMenuItem asChild>
                                                                    <Link href={`/rifa/${raffle.slug}`} target="_blank">
                                                                        <Eye className="mr-2 h-4 w-4" />
                                                                        Ver Pública
                                                                    </Link>
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem asChild>
                                                                    <Link href={`/rifa/${raffle.slug}/ganadores`} target="_blank">
                                                                        <Trophy className="mr-2 h-4 w-4" />
                                                                        Ver Ganadores
                                                                    </Link>
                                                                </DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Modal Crear Rifa */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="w-[95vw] max-w-3xl max-h-[95dvh] overflow-y-auto p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Gift className="h-5 w-5" />
                            Nueva Rifa
                        </DialogTitle>
                        <DialogDescription>
                            Configura los detalles de tu rifa o sorteo
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleCreate} className="space-y-6">
                        {/* Portada */}
                        <div>
                            <Label>Foto de Portada *</Label>
                            <div className="mt-2">
                                <ImageUploadButton
                                    image={coverImage}
                                    onChange={(file) => {
                                        if (file) {
                                            const reader = new FileReader();
                                            reader.onloadend = () => {
                                                setCoverImage(reader.result as string);
                                            };
                                            reader.readAsDataURL(file);
                                            setCoverImageFile(file);
                                        }
                                    }}
                                    label="Subir portada"
                                />
                            </div>
                        </div>

                        {/* Información Principal */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label htmlFor="create-title">Título *</Label>
                                <Input
                                    id="create-title"
                                    value={createData.title}
                                    onChange={(e) =>
                                        setCreateData('title', e.target.value)
                                    }
                                    placeholder="Ej: Sorteo de Casa Nueva"
                                    className="mt-1"
                                    required
                                />
                            </div>
                            <div>
                                <Label>Tipo *</Label>
                                <Select
                                    value={createData.type}
                                    onValueChange={(value) =>
                                        setCreateData('type', value)
                                    }
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="raffle">
                                            Rifa
                                        </SelectItem>
                                        <SelectItem value="draw">
                                            Sorteo
                                        </SelectItem>
                                        <SelectItem value="competition">
                                            Concurso
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="create-description">
                                Descripción
                            </Label>
                            <Textarea
                                id="create-description"
                                value={createData.description}
                                onChange={(e) =>
                                    setCreateData('description', e.target.value)
                                }
                                placeholder="Describe los detalles de tu rifa..."
                                rows={3}
                                className="mt-1"
                            />
                        </div>

                        {/* Premios */}
                        <div className="rounded-lg border p-4">
                            <div className="mb-4 flex items-center justify-between">
                                <Label className="text-base font-semibold">
                                    Premios * (mínimo 1)
                                </Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addPrize}
                                    disabled={prizes.length >= 5}
                                >
                                    <Plus className="mr-1 h-4 w-4" />
                                    Añadir
                                </Button>
                            </div>
                            <div className="space-y-4">
                                {prizes.map((prize, index) => (
                                    <div
                                        key={index}
                                        className="flex items-start gap-4 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900"
                                    >
                                        <div className="w-24 shrink-0">
                                            <ImageUploadButton
                                                image={prize.image}
                                                onChange={(file) => {
                                                    if (file) {
                                                        const reader = new FileReader();
                                                        reader.onloadend = () => {
                                                            const newPrizes = [
                                                                ...prizes,
                                                            ];
                                                            newPrizes[index].image =
                                                                reader.result as string;
                                                            newPrizes[
                                                                index
                                                            ].imageFile = file;
                                                            setPrizes(newPrizes);
                                                        };
                                                        reader.readAsDataURL(file);
                                                    }
                                                }}
                                                label="Premio"
                                            />
                                        </div>
                                        <div className="grid flex-1 grid-cols-2 gap-3">
                                            <div>
                                                <Label className="text-xs">
                                                    Nombre *
                                                </Label>
                                                <Input
                                                    value={prize.name}
                                                    onChange={(e) =>
                                                        updatePrize(
                                                            index,
                                                            'name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Premio 1"
                                                    required
                                                    className="mt-1"
                                                />
                                            </div>
                                            <div>
                                                <Label className="text-xs">
                                                    Cantidad
                                                </Label>
                                                <Input
                                                    type="number"
                                                    min="1"
                                                    value={prize.quantity}
                                                    onChange={(e) =>
                                                        updatePrize(
                                                            index,
                                                            'quantity',
                                                            parseInt(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="mt-1"
                                                />
                                            </div>
                                            <div className="col-span-2">
                                                <Label className="text-xs">
                                                    Descripción
                                                </Label>
                                                <Input
                                                    value={prize.description}
                                                    onChange={(e) =>
                                                        updatePrize(
                                                            index,
                                                            'description',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Descripción del premio..."
                                                    className="mt-1"
                                                />
                                            </div>
                                            <div>
                                                <Label className="text-xs">
                                                    Valor estimado
                                                </Label>
                                                <Input
                                                    type="number"
                                                    value={
                                                        prize.estimated_value ??
                                                        ''
                                                    }
                                                    onChange={(e) =>
                                                        updatePrize(
                                                            index,
                                                            'estimated_value',
                                                            e.target.value
                                                                ? parseFloat(
                                                                      e.target
                                                                          .value,
                                                                  )
                                                                : null,
                                                        )
                                                    }
                                                    placeholder="$0"
                                                    className="mt-1"
                                                />
                                            </div>
                                        </div>
                                        {prizes.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    removePrize(index)
                                                }
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Fechas */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label>Fecha de Inicio</Label>
                                <div className="mt-1">
                                    <DateTimePicker
                                        value={createData.start_date}
                                        onChange={(value) =>
                                            setCreateData('start_date', value)
                                        }
                                        placeholder="Seleccionar fecha"
                                    />
                                </div>
                            </div>
                            <div>
                                <Label>Fecha de Fin</Label>
                                <div className="mt-1">
                                    <DateTimePicker
                                        value={createData.end_date}
                                        onChange={(value) =>
                                            setCreateData('end_date', value)
                                        }
                                        placeholder="Seleccionar fecha"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Participantes */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <Label>Participantes Máx.</Label>
                                <Input
                                    type="number"
                                    value={createData.max_participants ?? ''}
                                    onChange={(e) =>
                                        setCreateData(
                                            'max_participants',
                                            e.target.value
                                                ? parseInt(e.target.value)
                                                : null,
                                        )
                                    }
                                    placeholder="Sin límite"
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <Label>Participantes Mín.</Label>
                                <Input
                                    type="number"
                                    value={createData.min_participants ?? ''}
                                    onChange={(e) =>
                                        setCreateData(
                                            'min_participants',
                                            e.target.value
                                                ? parseInt(e.target.value)
                                                : null,
                                        )
                                    }
                                    placeholder="1"
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <Label>Precio Ticket</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={createData.ticket_price}
                                    onChange={(e) =>
                                        setCreateData(
                                            'ticket_price',
                                            parseFloat(e.target.value),
                                        )
                                    }
                                    className="mt-1"
                                />
                            </div>
                        </div>

                        {/* Opciones */}
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <Switch
                                    checked={createData.allow_multiple_entries}
                                    onCheckedChange={(checked) =>
                                        setCreateData(
                                            'allow_multiple_entries',
                                            checked,
                                        )
                                    }
                                />
                                <Label className="text-sm">
                                    Múltiples entradas
                                </Label>
                            </div>
                            <div className="flex items-center gap-2">
                                <Switch
                                    checked={createData.show_winners}
                                    onCheckedChange={(checked) =>
                                        setCreateData('show_winners', checked)
                                    }
                                />
                                <Label className="text-sm">
                                    Mostrar ganadores
                                </Label>
                            </div>
                        </div>

                        {/* Estado */}
                        <div>
                            <Label>Estado Inicial</Label>
                            <Select
                                value={createData.status}
                                onValueChange={(value) =>
                                    setCreateData('status', value)
                                }
                            >
                                <SelectTrigger className="mt-1 w-full sm:w-48">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="draft">
                                        Borrador
                                    </SelectItem>
                                    <SelectItem value="published">
                                        Publicada
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsCreateOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={isCreating}>
                                {isCreating ? 'Creando...' : 'Crear Rifa'}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Editar Rifa */}
            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                <DialogContent className="w-[95vw] max-w-3xl max-h-[95dvh] overflow-y-auto p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Pencil className="h-5 w-5" />
                            Editar Rifa
                        </DialogTitle>
                    </DialogHeader>

                    {editingRaffle && (
                        <form onSubmit={handleEdit} className="space-y-6">
                            {/* Portada */}
                            <div>
                                <Label>Foto de Portada</Label>
                                <div className="mt-2">
                                    <ImageUploadButton
                                        image={coverImageEdit}
                                        onChange={(file) => {
                                            if (file) {
                                                const reader = new FileReader();
                                                reader.onloadend = () => {
                                                    setCoverImageEdit(
                                                        reader.result as string,
                                                    );
                                                };
                                                reader.readAsDataURL(file);
                                                setCoverImageFileEdit(file);
                                            }
                                        }}
                                        label="Cambiar portada"
                                    />
                                </div>
                            </div>

                            {/* Información Principal */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="edit-title">Título *</Label>
                                    <Input
                                        id="edit-title"
                                        value={
                                            createData.title ||
                                            editingRaffle.title
                                        }
                                        onChange={(e) =>
                                            setCreateData(
                                                'title',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1"
                                        required
                                    />
                                </div>
                                <div>
                                    <Label>Tipo *</Label>
                                    <Select
                                        value={
                                            createData.type ||
                                            editingRaffle.type
                                        }
                                        onValueChange={(value) =>
                                            setCreateData('type', value)
                                        }
                                    >
                                        <SelectTrigger className="mt-1">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="raffle">
                                                Rifa
                                            </SelectItem>
                                            <SelectItem value="draw">
                                                Sorteo
                                            </SelectItem>
                                            <SelectItem value="competition">
                                                Concurso
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="edit-description">
                                    Descripción
                                </Label>
                                <Textarea
                                    id="edit-description"
                                    value={
                                        createData.description ||
                                        editingRaffle.description ||
                                        ''
                                    }
                                    onChange={(e) =>
                                        setCreateData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Descripción de la rifa..."
                                    rows={3}
                                    className="mt-1"
                                />
                            </div>

                            {/* Fechas */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label>Fecha de Inicio</Label>
                                    <div className="mt-1">
                                        <DateTimePicker
                                            value={
                                                createData.start_date ||
                                                editingRaffle.start_date ||
                                                ''
                                            }
                                            onChange={(value) =>
                                                setCreateData(
                                                    'start_date',
                                                    value,
                                                )
                                            }
                                            placeholder="Seleccionar fecha"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <Label>Fecha de Fin</Label>
                                    <div className="mt-1">
                                        <DateTimePicker
                                            value={
                                                createData.end_date ||
                                                editingRaffle.end_date ||
                                                ''
                                            }
                                            onChange={(value) =>
                                                setCreateData('end_date', value)
                                            }
                                            placeholder="Seleccionar fecha"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Participantes */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <Label>Participantes Máx.</Label>
                                    <Input
                                        type="number"
                                        value={
                                            createData.max_participants ??
                                            editingRaffle.max_participants ??
                                            ''
                                        }
                                        onChange={(e) =>
                                            setCreateData(
                                                'max_participants',
                                                e.target.value
                                                    ? parseInt(e.target.value)
                                                    : null,
                                            )
                                        }
                                        placeholder="Sin límite"
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label>Participantes Mín.</Label>
                                    <Input
                                        type="number"
                                        value={
                                            createData.min_participants ??
                                            editingRaffle.min_participants ??
                                            ''
                                        }
                                        onChange={(e) =>
                                            setCreateData(
                                                'min_participants',
                                                e.target.value
                                                    ? parseInt(e.target.value)
                                                    : null,
                                            )
                                        }
                                        placeholder="1"
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label>Precio Ticket</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={
                                            createData.ticket_price ||
                                            editingRaffle.ticket_price
                                        }
                                        onChange={(e) =>
                                            setCreateData(
                                                'ticket_price',
                                                parseFloat(e.target.value),
                                            )
                                        }
                                        className="mt-1"
                                    />
                                </div>
                            </div>

                            {/* Opciones */}
                            <div className="flex flex-wrap items-center gap-6">
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={
                                            createData.allow_multiple_entries ??
                                            (editingRaffle as any)
                                                .allow_multiple_entries ??
                                            false
                                        }
                                        onCheckedChange={(checked) =>
                                            setCreateData(
                                                'allow_multiple_entries',
                                                checked,
                                            )
                                        }
                                    />
                                    <Label className="text-sm">
                                        Múltiples entradas por usuario
                                    </Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={
                                            createData.show_winners ??
                                            (editingRaffle as any)
                                                .show_winners ??
                                            true
                                        }
                                        onCheckedChange={(checked) =>
                                            setCreateData(
                                                'show_winners',
                                                checked,
                                            )
                                        }
                                    />
                                    <Label className="text-sm">
                                        Mostrar ganadores
                                    </Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Label className="text-sm">
                                        Máx. entradas por usuario:
                                    </Label>
                                    <Input
                                        type="number"
                                        min="1"
                                        className="h-8 w-20"
                                        value={
                                            createData.max_entries_per_user ??
                                            (editingRaffle as any)
                                                .max_entries_per_user ??
                                            1
                                        }
                                        onChange={(e) =>
                                            setCreateData(
                                                'max_entries_per_user',
                                                parseInt(e.target.value) || 1,
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            {/* Estado */}
                            <div>
                                <Label>Estado</Label>
                                <Select
                                    value={
                                        createData.status ||
                                        editingRaffle.status
                                    }
                                    onValueChange={(value) =>
                                        setCreateData('status', value)
                                    }
                                >
                                    <SelectTrigger className="mt-1 w-full sm:w-48">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="draft">
                                            Borrador
                                        </SelectItem>
                                        <SelectItem value="published">
                                            Publicada
                                        </SelectItem>
                                        <SelectItem value="active">
                                            Activa
                                        </SelectItem>
                                        <SelectItem value="completed">
                                            Completada
                                        </SelectItem>
                                        <SelectItem value="cancelled">
                                            Cancelada
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Premios Actuales */}
                            <div className="rounded-lg border p-4">
                                <div className="mb-3 flex items-center justify-between">
                                    <Label className="text-base font-semibold">
                                        Premios ({prizesEdit.length})
                                    </Label>
                                    {prizesEdit.length < 5 && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setPrizesEdit([
                                                    ...prizesEdit,
                                                    {
                                                        name: '',
                                                        description: '',
                                                        quantity: 1,
                                                        estimated_value: null,
                                                        image: null,
                                                        imageFile: null,
                                                    },
                                                ])
                                            }
                                        >
                                            <Plus className="mr-1 h-4 w-4" />
                                            Añadir
                                        </Button>
                                    )}
                                </div>
                                {prizesEdit.length > 0 ? (
                                    <div className="max-h-64 space-y-4 overflow-y-auto">
                                        {prizesEdit.map(
                                            (prize: any, index: number) => (
                                                <div
                                                    key={index}
                                                    className="flex items-start gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900"
                                                >
                                                    <div className="h-16 w-16 shrink-0">
                                                        <ImageUploadButton
                                                            image={prize.image}
                                                            onChange={(
                                                                file,
                                                            ) => {
                                                                if (file) {
                                                                    const reader = new FileReader();
                                                                    reader.onloadend = () => {
                                                                        const newPrizes =
                                                                            [
                                                                                ...prizesEdit,
                                                                            ];
                                                                        newPrizes[
                                                                            index
                                                                        ].image =
                                                                            reader.result as string;
                                                                        newPrizes[
                                                                            index
                                                                        ].imageFile =
                                                                            file;
                                                                        setPrizesEdit(
                                                                            newPrizes,
                                                                        );
                                                                    };
                                                                    reader.readAsDataURL(file);
                                                                }
                                                            }}
                                                            label="Premio"
                                                        />
                                                    </div>
                                                    <div className="grid flex-1 grid-cols-2 gap-2">
                                                        <div className="col-span-2">
                                                            <Input
                                                                placeholder="Nombre del premio *"
                                                                value={
                                                                    prize.name
                                                                }
                                                                onChange={(
                                                                    e,
                                                                ) => {
                                                                    const newPrizes =
                                                                        [
                                                                            ...prizesEdit,
                                                                        ];
                                                                    newPrizes[
                                                                        index
                                                                    ].name =
                                                                        e.target.value;
                                                                    setPrizesEdit(
                                                                        newPrizes,
                                                                    );
                                                                }}
                                                                className="h-8"
                                                            />
                                                        </div>
                                                        <Input
                                                            type="number"
                                                            placeholder="Cantidad"
                                                            value={
                                                                prize.quantity
                                                            }
                                                            onChange={(e) => {
                                                                const newPrizes =
                                                                    [
                                                                        ...prizesEdit,
                                                                    ];
                                                                newPrizes[
                                                                    index
                                                                ].quantity =
                                                                    parseInt(
                                                                        e.target
                                                                            .value,
                                                                    ) || 1;
                                                                setPrizesEdit(
                                                                    newPrizes,
                                                                );
                                                            }}
                                                            className="h-8"
                                                        />
                                                        <Input
                                                            type="number"
                                                            placeholder="Valor $"
                                                            value={
                                                                prize.estimated_value ||
                                                                ''
                                                            }
                                                            onChange={(e) => {
                                                                const newPrizes =
                                                                    [
                                                                        ...prizesEdit,
                                                                    ];
                                                                newPrizes[
                                                                    index
                                                                ].estimated_value =
                                                                    e.target
                                                                        .value
                                                                        ? parseFloat(
                                                                              e
                                                                                  .target
                                                                                  .value,
                                                                          )
                                                                        : null;
                                                                setPrizesEdit(
                                                                    newPrizes,
                                                                );
                                                            }}
                                                            className="h-8"
                                                        />
                                                    </div>
                                                    {prizesEdit.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                setPrizesEdit(
                                                                    prizesEdit.filter(
                                                                        (
                                                                            _: any,
                                                                            i: number,
                                                                        ) =>
                                                                            i !==
                                                                            index,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <X className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-zinc-500">
                                        No hay premios. Añade al menos uno.
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:justify-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsEditOpen(false)}
                                >
                                    Cancelar
                                </Button>
                                <Button type="submit">Guardar Cambios</Button>
                            </div>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
