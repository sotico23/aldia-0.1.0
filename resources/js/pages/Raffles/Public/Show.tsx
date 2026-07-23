import { Head, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    Gift,
    Users,
    Clock,
    Trophy,
    Share2,
    CheckCircle,
    Calendar,
    ShoppingCart,
    CreditCard,
    Ticket,
} from 'lucide-react';
import { useState } from 'react';
import { useCountry } from '@/hooks/use-country';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

interface Prize {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
    quantity: number;
    position: number;
    estimated_value: number | null;
}

interface Raffle {
    id: number;
    title: string;
    description: string | null;
    slug: string;
    cover_image: string | null;
    image_url: string | null;
    type: string;
    status: string;
    ticket_price: number;
    max_participants: number | null;
    min_participants: number | null;
    start_date: string | null;
    end_date: string | null;
    show_winners: boolean;
    background_color: string | null;
    text_color: string | null;
    allow_multiple_entries: boolean;
    max_entries_per_user: number;
    prizes: Prize[];
}

interface Props {
    raffle: Raffle;
    participantCount: number;
    isParticipating: boolean;
    purchasedNumbers?: number[];
}

export default function RafflePublicShow({
    raffle,
    participantCount,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    isParticipating,
    purchasedNumbers = [],
}: Props) {
    const { code: countryCode, currency } = useCountry();
    const { data, setData, post } = useForm({
        name: '',
        email: '',
        phone: '',
        numbers: [] as number[],
    });

    const [showBuyModal, setShowBuyModal] = useState(false);
    const [selectedNumbers, setSelectedNumbers] = useState<number[]>([]);
    const [isProcessingPayment, setIsProcessingPayment] = useState(false);

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { flash } = usePage().props as any;

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/rifa/${raffle.slug}/participate`, {
            onSuccess: () => {
                toast.success('¡Estás participando! Mucha suerte.');
            },
            onError: (errors) => {
                const errorMsg =
                    Object.values(errors)[0] || 'Error al participar';
                toast.error(errorMsg);
            },
        });
    };

    const handleBuyNumbers = async () => {
        if (selectedNumbers.length === 0) {
            toast.error('Selecciona al menos un número');
            return;
        }

        setIsProcessingPayment(true);

        try {
            const formData = new FormData();
            formData.append('name', data.name);
            formData.append('email', data.email);
            formData.append('phone', data.phone || '');
            formData.append('numbers', JSON.stringify(selectedNumbers));

            await axios.post(`/rifa/${raffle.slug}/buy-numbers`, formData);

            toast.success(
                `¡Compra exitosa! Has adquirido el número${selectedNumbers.length > 1 ? 's' : ''}: ${selectedNumbers.join(', ')}`,
            );
            setShowBuyModal(false);
            setSelectedNumbers([]);
            window.location.reload();
        } catch (error: any) {
            const message =
                error.response?.data?.message ||
                error.response?.data?.error ||
                'Error al comprar';
            toast.error(message);
        } finally {
            setIsProcessingPayment(false);
        }
    };

    const toggleNumber = (num: number) => {
        if (selectedNumbers.includes(num)) {
            setSelectedNumbers(selectedNumbers.filter((n) => n !== num));
        } else {
            setSelectedNumbers([...selectedNumbers, num]);
        }
    };

    const coverImage = raffle.cover_image || raffle.image_url;
    const bgColor = coverImage
        ? 'bg-zinc-900'
        : 'bg-gradient-to-br from-purple-900 via-pink-900 to-red-900';

    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return null;
        const date = new Date(dateStr);
        return date.toLocaleDateString(currency.locale, {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const isOpen = raffle.status === 'active';
    const isPublished = raffle.status === 'published';
    const canParticipate =
        (isOpen || isPublished) &&
        (!raffle.max_participants ||
            participantCount < raffle.max_participants);
    const totalCost = selectedNumbers.length * raffle.ticket_price;
    const maxNumbers = raffle.max_participants || 100;
    const availableNumbers = maxNumbers - participantCount;

    return (
        <div className={`min-h-screen ${bgColor} text-white`}>
            <Head title={raffle.title} />

            {/* Hero Section */}
            <div className="relative px-4 py-16 lg:py-24">
                <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-purple-500/20 via-transparent to-transparent" />

                <div className="relative z-10 mx-auto max-w-5xl">
                    {/* Badge tipo */}
                    <div className="mb-6 flex justify-center">
                        <Badge className="border-white/20 bg-white/10 px-4 py-1.5 text-sm backdrop-blur">
                            {raffle.type === 'raffle'
                                ? '🎯 RIFA'
                                : raffle.type === 'draw'
                                  ? '🎲 SORTEO'
                                  : '🏆 CONCURSO'}
                        </Badge>
                    </div>

                    {/* Título */}
                    <div className="mb-6 text-center">
                        <h1 className="mb-4 text-4xl font-extrabold tracking-tight lg:text-6xl">
                            {raffle.title}
                        </h1>
                        {raffle.description && (
                            <p className="mx-auto max-w-2xl text-lg text-zinc-300">
                                {raffle.description}
                            </p>
                        )}
                    </div>

                    {/* Imagen Principal / Portada */}
                    {coverImage && (
                        <div className="relative mb-10 overflow-hidden rounded-2xl shadow-2xl">
                            <img
                                src={coverImage}
                                alt={raffle.title}
                                className="h-72 w-full object-cover lg:h-96"
                            />
                            <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent" />

                            {/* Status Badge */}
                            <div className="absolute top-4 right-4">
                                <Badge
                                    className={
                                        isOpen
                                            ? 'border-none bg-green-500/90 text-white'
                                            : 'border-none bg-yellow-500/90 text-white'
                                    }
                                >
                                    {isOpen ? '🟢 Activa' : '🟡 Publicada'}
                                </Badge>
                            </div>
                        </div>
                    )}

                    {/* Botones de Acción */}
                    {canParticipate && (
                        <div className="mb-8 flex flex-wrap justify-center gap-4">
                            {raffle.ticket_price === 0 ? (
                                <Button
                                    onClick={() => setShowBuyModal(true)}
                                    className="h-12 bg-gradient-to-r from-green-500 to-emerald-500 px-8 text-lg hover:from-green-600 hover:to-emerald-600"
                                >
                                    <Gift className="mr-2 h-5 w-5" />
                                    Participar Gratis
                                </Button>
                            ) : (
                                <Button
                                    onClick={() => setShowBuyModal(true)}
                                    className="h-12 bg-gradient-to-r from-purple-600 to-pink-600 px-8 text-lg hover:from-purple-700 hover:to-pink-700"
                                >
                                    <ShoppingCart className="mr-2 h-5 w-5" />
                                    Comprar Números
                                </Button>
                            )}
                            <Button
                                onClick={() => {
                                    navigator.clipboard.writeText(
                                        window.location.href,
                                    );
                                    toast.success('Enlace copiado');
                                }}
                                className="h-12 border border-white/20 bg-white/10 px-8 text-white backdrop-blur hover:bg-white/20"
                            >
                                <Share2 className="mr-2 h-5 w-5" />
                                Compartir
                            </Button>
                        </div>
                    )}

                    {/* Números comprados */}
                    {purchasedNumbers.length > 0 && (
                        <div className="mb-8 flex justify-center">
                            <Card className="max-w-md border-green-500/30 bg-green-500/10">
                                <CardContent className="p-4 text-center">
                                    <div className="mb-2 flex items-center justify-center gap-2">
                                        <CheckCircle className="h-5 w-5 text-green-400" />
                                        <span className="font-semibold text-green-400">
                                            Tus números comprados
                                        </span>
                                    </div>
                                    <div className="flex flex-wrap justify-center gap-2">
                                        {purchasedNumbers.map((num) => (
                                            <Badge
                                                key={num}
                                                className="bg-green-600 px-3 py-1 text-white"
                                            >
                                                #{num}
                                            </Badge>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {/* Fechas */}
                    {(raffle.start_date || raffle.end_date) && (
                        <div className="mb-8 flex flex-wrap justify-center gap-6">
                            {raffle.start_date && (
                                <div className="flex items-center gap-3 rounded-xl border border-white/20 bg-white/10 px-5 py-3 backdrop-blur">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-purple-500/20">
                                        <Calendar className="h-5 w-5 text-purple-400" />
                                    </div>
                                    <div>
                                        <div className="text-xs text-purple-300">
                                            Inicio
                                        </div>
                                        <div className="font-semibold">
                                            {formatDate(raffle.start_date)}
                                        </div>
                                    </div>
                                </div>
                            )}
                            {raffle.end_date && (
                                <div className="flex items-center gap-3 rounded-xl border border-white/20 bg-white/10 px-5 py-3 backdrop-blur">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-pink-500/20">
                                        <Clock className="h-5 w-5 text-pink-400" />
                                    </div>
                                    <div>
                                        <div className="text-xs text-pink-300">
                                            Termina
                                        </div>
                                        <div className="font-semibold">
                                            {formatDate(raffle.end_date)}
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Stats Cards */}
                    <div className="mb-10 grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <div className="rounded-xl bg-white/10 p-4 text-center backdrop-blur">
                            <Users className="mx-auto mb-2 h-6 w-6 text-purple-400" />
                            <div className="text-2xl font-bold">
                                {participantCount}
                            </div>
                            <div className="text-xs text-zinc-400">
                                Participantes
                            </div>
                        </div>
                        <div className="rounded-xl bg-white/10 p-4 text-center backdrop-blur">
                            <Ticket className="mx-auto mb-2 h-6 w-6 text-pink-400" />
                            <div className="text-2xl font-bold">
                                {availableNumbers > 0 ? availableNumbers : 0}
                            </div>
                            <div className="text-xs text-zinc-400">
                                Disponibles
                            </div>
                        </div>
                        <div className="rounded-xl bg-white/10 p-4 text-center backdrop-blur">
                            <Trophy className="mx-auto mb-2 h-6 w-6 text-yellow-400" />
                            <div className="text-2xl font-bold">
                                {raffle.ticket_price === 0
                                    ? 'GRATIS'
                                    : `$${raffle.ticket_price.toLocaleString(currency.locale)}`}
                            </div>
                            <div className="text-xs text-zinc-400">
                                Por número
                            </div>
                        </div>
                        <div className="rounded-xl bg-white/10 p-4 text-center backdrop-blur">
                            <Gift className="mx-auto mb-2 h-6 w-6 text-emerald-400" />
                            <div className="text-2xl font-bold">
                                {raffle.prizes.length}
                            </div>
                            <div className="text-xs text-zinc-400">Premios</div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Sección de Premios */}
            <div className="bg-black/30 px-4 py-16">
                <div className="mx-auto max-w-5xl">
                    <div className="mb-10 text-center">
                        <h2 className="mb-2 flex items-center justify-center gap-3 text-3xl font-bold">
                            <Trophy className="h-8 w-8 text-yellow-400" />
                            Premios en Juego
                        </h2>
                        <p className="text-zinc-400">
                            {raffle.prizes.length} premio
                            {raffle.prizes.length !== 1 ? 's' : ''} disponibles
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {raffle.prizes.map((prize) => (
                            <Card
                                key={prize.id}
                                className="overflow-hidden border-white/10 bg-white/5 backdrop-blur"
                            >
                                {prize.image_url ? (
                                    <div className="relative">
                                        <img
                                            src={prize.image_url}
                                            alt={prize.name}
                                            className="h-48 w-full object-cover"
                                        />
                                        <div className="absolute top-3 left-3">
                                            <Badge className="border-none bg-yellow-500/90 text-white">
                                                #{prize.position}
                                            </Badge>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="relative bg-gradient-to-br from-purple-600 to-pink-600 p-8 text-center">
                                        <Badge className="absolute top-3 left-3 border-none bg-yellow-500/90 text-white">
                                            #{prize.position}
                                        </Badge>
                                        <Gift className="mx-auto h-16 w-16 text-white/50" />
                                    </div>
                                )}
                                <CardContent className="p-5">
                                    <h3 className="mb-2 text-lg font-bold">
                                        {prize.name}
                                    </h3>
                                    {prize.estimated_value && (
                                        <span className="font-semibold text-purple-400">
                                            $
                                            {prize.estimated_value.toLocaleString(
                                                currency.locale,
                                            )}
                                        </span>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>
            </div>

            {/* Footer */}
            <footer className="border-t border-white/10 bg-black/20 px-4 py-8">
                <div className="mx-auto max-w-5xl text-center">
                    <p className="text-sm text-zinc-500">
                        © {new Date().getFullYear()} {raffle.title}. Todos los
                        derechos reservados.
                    </p>
                </div>
            </footer>

            {/* Modal de Compra */}
            <Dialog open={showBuyModal} onOpenChange={setShowBuyModal}>
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto border-zinc-800 bg-zinc-900">
                    <DialogHeader>
                        <DialogTitle className="text-xl text-white">
                            {raffle.ticket_price === 0
                                ? 'Participar en la Rifa'
                                : 'Comprar Números'}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="space-y-6">
                        {/* Información del precio */}
                        <div className="rounded-lg bg-white/5 p-4 text-center">
                            <div className="text-3xl font-bold text-white">
                                {raffle.ticket_price === 0
                                    ? '¡GRATIS!'
                                    : `$${totalCost.toLocaleString(currency.locale)}`}
                            </div>
                            <div className="text-sm text-zinc-400">
                                {selectedNumbers.length > 0
                                    ? `${selectedNumbers.length} número${selectedNumbers.length > 1 ? 's' : ''} x $${raffle.ticket_price.toLocaleString(currency.locale)}`
                                    : raffle.ticket_price === 0
                                      ? 'Participación gratuita'
                                      : '$' +
                                        raffle.ticket_price.toLocaleString(
                                            currency.locale,
                                        ) +
                                        ' por número'}
                            </div>
                        </div>

                        {/* Formulario de datos */}
                        <div className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm text-zinc-400">
                                    Nombre completo *
                                </label>
                                <Input
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    className="border-zinc-700 bg-zinc-800 text-white placeholder:text-zinc-500"
                                    placeholder="Tu nombre"
                                    required
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm text-zinc-400">
                                    Email *
                                </label>
                                <Input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    className="border-zinc-700 bg-zinc-800 text-white placeholder:text-zinc-500"
                                    placeholder="tu@email.com"
                                    required
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm text-zinc-400">
                                    Teléfono (opcional)
                                </label>
                                <Input
                                    type="tel"
                                    value={data.phone}
                                    onChange={(e) =>
                                        setData('phone', e.target.value)
                                    }
                                    className="border-zinc-700 bg-zinc-800 text-white placeholder:text-zinc-500"
                                    placeholder="+56 9..."
                                />
                            </div>
                        </div>

                        {/* Selector de números (solo si no es gratis) */}
                        {raffle.ticket_price > 0 && (
                            <div>
                                <label className="mb-2 block text-sm text-zinc-400">
                                    Selecciona los números (
                                    {selectedNumbers.length} seleccionados)
                                </label>
                                <div className="flex max-h-48 flex-wrap gap-2 overflow-y-auto p-2">
                                    {Array.from(
                                        { length: Math.min(maxNumbers, 50) },
                                        (_, i) => i + 1,
                                    ).map((num) => (
                                        <button
                                            key={num}
                                            type="button"
                                            onClick={() => toggleNumber(num)}
                                            disabled={
                                                !availableNumbers &&
                                                !selectedNumbers.includes(num)
                                            }
                                            className={`h-10 w-10 rounded-lg text-sm font-bold transition-colors ${
                                                selectedNumbers.includes(num)
                                                    ? 'bg-purple-600 text-white'
                                                    : availableNumbers > 0
                                                      ? 'bg-zinc-700 text-white hover:bg-zinc-600'
                                                      : 'cursor-not-allowed bg-zinc-800 text-zinc-600'
                                            }`}
                                        >
                                            {num}
                                        </button>
                                    ))}
                                </div>
                                {maxNumbers > 50 && (
                                    <p className="mt-2 text-xs text-zinc-500">
                                        Mostrando 50 de {maxNumbers} números
                                        disponibles
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Botón de acción */}
                        <Button
                            onClick={handleBuyNumbers}
                            disabled={
                                isProcessingPayment ||
                                (raffle.ticket_price > 0 &&
                                    selectedNumbers.length === 0)
                            }
                            className="h-12 w-full bg-gradient-to-r from-purple-600 to-pink-600 text-lg"
                        >
                            {isProcessingPayment ? (
                                'Procesando...'
                            ) : raffle.ticket_price === 0 ? (
                                'Participar Ahora'
                            ) : (
                                <>
                                    <CreditCard className="mr-2 h-5 w-5" />
                                    Pagar ${totalCost.toLocaleString(currency.locale)}
                                </>
                            )}
                        </Button>

                        <p className="text-center text-xs text-zinc-500">
                            Al participar aceptas los términos y condiciones de
                            la rifa.
                        </p>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
