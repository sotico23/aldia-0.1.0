import { Head } from '@inertiajs/react';
import {
    Gift,
    Users,
    Trophy,
    Play,
    RefreshCw,
    CheckCircle,
    XCircle,
    Crown,
    ArrowLeft
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCountry } from '@/hooks/use-country';
import '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';

interface Raffle {
    id: number;
    title: string;
    slug: string;
    status: string;
    cover_image: string | null;
    description: string | null;
    min_participants: number | null;
    ticket_price: number;
    participants: Participant[];
    prizes: Prize[];
    winners: Winner[];
}

interface Participant {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    entries: number;
    purchased_numbers: string | null;
}

interface Prize {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
    quantity: number;
    position: number;
    estimated_value: number | null;
    status: string;
}

interface Winner {
    id: number;
    name: string;
    email: string;
    prize: Prize | null;
    won_at: string | null;
}

interface Props {
    raffle: Raffle;
}

export default function DrawRoom({ raffle }: Props) {
    const { code: countryCode, currency } = useCountry();
    const [isDrawing, setIsDrawing] = useState(false);
    const [showResults, setShowResults] = useState(false);
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const [selectedPrize, setSelectedPrize] = useState<number | null>(null);
    const [drawnWinners, setDrawnWinners] = useState<Winner[]>([]);

    const participantCount = raffle.participants.length;
    const minParticipants = raffle.min_participants || 1;
    const canDraw =
        raffle.status === 'active' && participantCount >= minParticipants;
    const hasPrizes = raffle.prizes.length > 0;
    const allWinnersDrawn =
        raffle.winners.length > 0 || drawnWinners.length > 0;

    const handleDraw = async () => {
        if (!canDraw) return;

        setIsDrawing(true);
        setShowResults(false);

        try {
            const response = await fetch(`/raffles/${raffle.id}/draw`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                    'Content-Type': 'application/json',
                },
            });

            const data = await response.json();

            if (response.ok) {
                setDrawnWinners(data.winners || []);
                setShowResults(true);
                toast.success('¡Sorteo realizado con éxito!');
            } else {
                toast.error(data.message || 'Error al realizar el sorteo');
            }
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        } catch (error) {
            toast.error('Error al realizar el sorteo');
        } finally {
            setIsDrawing(false);
        }
    };

    const refreshPage = () => {
        window.location.reload();
    };

    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString(currency.locale, {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AppLayout>
            <Head title={`Sorteo: ${raffle.title}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => window.history.back()}
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                {raffle.title}
                            </h1>
                            <p className="text-muted-foreground">
                                Sala de sorteos
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={refreshPage}>
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Actualizar
                        </Button>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Participantes
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {participantCount}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Mínimo requerido: {minParticipants}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Premios
                            </CardTitle>
                            <Gift className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {raffle.prizes.length}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {raffle.prizes.reduce(
                                    (sum, p) => sum + p.quantity,
                                    0,
                                )}{' '}
                                unidades
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Ganadores
                            </CardTitle>
                            <Trophy className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {raffle.winners.length + drawnWinners.length}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {allWinnersDrawn
                                    ? 'Sorteo completado'
                                    : 'Pendiente'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Estado
                            </CardTitle>
                            <Crown className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <Badge
                                className={
                                    raffle.status === 'active'
                                        ? 'bg-green-500'
                                        : 'bg-blue-500'
                                }
                            >
                                {raffle.status === 'active'
                                    ? 'Activa'
                                    : raffle.status}
                            </Badge>
                        </CardContent>
                    </Card>
                </div>

                {/* Draw Section */}
                <Card className="border-2 border-dashed border-amber-500/50 bg-amber-500/5">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Play className="h-5 w-5 text-amber-500" />
                            Realizar Sorteo
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {!canDraw ? (
                            <div className="flex flex-col items-center justify-center py-6 text-center">
                                <XCircle className="mb-4 h-12 w-12 text-red-500" />
                                <h3 className="text-lg font-semibold">
                                    No se puede realizar el sorteo
                                </h3>
                                <p className="max-w-md text-muted-foreground">
                                    {participantCount < minParticipants
                                        ? `Necesitas al menos ${minParticipants} participantes para realizar el sorteo. Actualmente hay ${participantCount} participantes.`
                                        : 'La rifa debe estar activa para realizar el sorteo.'}
                                </p>
                            </div>
                        ) : !hasPrizes ? (
                            <div className="flex flex-col items-center justify-center py-6 text-center">
                                <Gift className="mb-4 h-12 w-12 text-orange-500" />
                                <h3 className="text-lg font-semibold">
                                    Sin premios registrados
                                </h3>
                                <p className="text-muted-foreground">
                                    Agrega premios a la rifa antes de realizar
                                    el sorteo.
                                </p>
                            </div>
                        ) : (
                            <>
                                <div className="text-center">
                                    <p className="mb-4 text-muted-foreground">
                                        ¿Estás seguro de realizar el sorte0? Se
                                        seleccionarán los ganadores de forma
                                        aleatoria.
                                    </p>
                                    <Button
                                        size="lg"
                                        onClick={handleDraw}
                                        disabled={isDrawing}
                                        className="bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600"
                                    >
                                        {isDrawing ? (
                                            <>
                                                <RefreshCw className="mr-2 h-5 w-5 animate-spin" />
                                                Realizando Sorteo...
                                            </>
                                        ) : (
                                            <>
                                                <Play className="mr-2 h-5 w-5" />
                                                ¡REALIZAR SORTEO!
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>

                {/* Winners Section */}
                {(raffle.winners.length > 0 || showResults) && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Crown className="h-5 w-5 text-yellow-500" />
                                Ganadores del Sorteo
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {(showResults
                                    ? drawnWinners
                                    : raffle.winners
                                ).map((winner, index) => (
                                    <div
                                        key={winner.id || index}
                                        className="flex items-center justify-between rounded-lg border bg-yellow-500/10 p-4"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-500/20">
                                                <Crown className="h-6 w-6 text-yellow-500" />
                                            </div>
                                            <div>
                                                <div className="font-semibold">
                                                    {winner.name}
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    {winner.email}
                                                </div>
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <Badge className="bg-yellow-500">
                                                {winner.prize?.name || 'Premio'}
                                            </Badge>
                                            {winner.won_at && (
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    {formatDate(winner.won_at)}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                                {(showResults ? drawnWinners : raffle.winners)
                                    .length === 0 && (
                                    <p className="py-4 text-center text-muted-foreground">
                                        No hay ganadores registrados
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Prizes Section */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Gift className="h-5 w-5" />
                            Premios de la Rifa
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {raffle.prizes.map((prize) => {
                                const prizeWinners = (
                                    showResults ? drawnWinners : raffle.winners
                                ).filter((w) => w.prize?.id === prize.id);
                                const isAwarded =
                                    prize.status === 'awarded' ||
                                    prizeWinners.length > 0;

                                return (
                                    <div
                                        key={prize.id}
                                        className={`rounded-lg border p-4 ${isAwarded ? 'border-green-500/50 bg-green-500/10' : ''}`}
                                    >
                                        <div className="flex items-start gap-3">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                                {prize.image_url ? (
                                                    <img
                                                        src={prize.image_url}
                                                        alt={prize.name}
                                                        className="h-full w-full rounded-lg object-cover"
                                                    />
                                                ) : (
                                                    <Trophy className="h-5 w-5 text-zinc-400" />
                                                )}
                                            </div>
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-semibold">
                                                        {prize.name}
                                                    </span>
                                                    <Badge variant="outline">
                                                        #{prize.position}
                                                    </Badge>
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    Cantidad: {prize.quantity}
                                                    {prize.estimated_value && (
                                                        <>
                                                            {' '}
                                                            • $
                                                            {prize.estimated_value.toLocaleString(
                                                                currency.locale,
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                                {isAwarded && (
                                                    <div className="mt-2 flex items-center gap-1 text-sm text-green-600">
                                                        <CheckCircle className="h-4 w-4" />
                                                        <span>Entregado</span>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                {/* Participants Section */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="h-5 w-5" />
                            Participantes ({participantCount})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {participantCount === 0 ? (
                            <p className="py-8 text-center text-muted-foreground">
                                No hay participantes registrados
                            </p>
                        ) : (
                            <div className="max-h-96 overflow-y-auto">
                                <div className="space-y-2">
                                    {raffle.participants.map((participant) => (
                                        <div
                                            key={participant.id}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <div>
                                                <div className="font-medium">
                                                    {participant.name}
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    {participant.email}
                                                </div>
                                            </div>
                                            <div className="text-right text-sm">
                                                {participant.phone && (
                                                    <div className="text-muted-foreground">
                                                        {participant.phone}
                                                    </div>
                                                )}
                                                <Badge variant="outline">
                                                    {participant.entries}{' '}
                                                    participación
                                                    {participant.entries > 1
                                                        ? 'es'
                                                        : ''}
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
