import { Head, Link } from '@inertiajs/react';
import { Trophy, Gift, Users, PartyPopper } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useCountry } from '@/hooks/use-country';

interface Prize {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
    quantity: number;
    position: number;
}

interface Winner {
    id: number;
    name: string;
    email: string;
    won_at: string | null;
    prize: Prize;
}

interface Raffle {
    id: number;
    title: string;
    slug: string;
    image_url: string | null;
    show_winners: boolean;
    prizes: Prize[];
    winners: Winner[];
}

interface Props {
    raffle: Raffle;
}

export default function RaffleWinners({ raffle }: Props) {
    const { code: countryCode, currency } = useCountry();
    const bgColor = 'bg-gradient-to-br from-yellow-900 to-amber-900';

    return (
        <div className={`min-h-screen ${bgColor} text-white`}>
            <Head title={`Ganadores - ${raffle.title}`} />

            <div className="px-4 py-20">
                <div className="mx-auto max-w-4xl">
                    {/* Header */}
                    <div className="mb-16 text-center">
                        <div className="mb-6 inline-flex items-center gap-3">
                            <Trophy className="h-12 w-12 text-yellow-400" />
                            <PartyPopper className="h-12 w-12 text-yellow-400" />
                        </div>
                        <h1 className="mb-4 text-4xl font-extrabold lg:text-6xl">
                            ¡Felicitaciones a los Ganadores!
                        </h1>
                        <p className="text-xl text-yellow-200">
                            {raffle.title}
                        </p>
                    </div>

                    {/* Ganadores */}
                    {raffle.winners.length > 0 ? (
                        <div className="grid grid-cols-1 gap-8 md:grid-cols-2">
                            {raffle.winners.map((winner, index) => (
                                <Card
                                    key={winner.id}
                                    className="overflow-hidden border-white/20 bg-white/10 backdrop-blur"
                                >
                                    <div className="h-2 bg-gradient-to-r from-yellow-400 to-amber-500" />
                                    <CardContent className="p-8 text-center">
                                        <div className="mb-4 inline-flex h-20 w-20 items-center justify-center rounded-full bg-yellow-500/20">
                                            <span className="text-3xl font-bold text-yellow-400">
                                                {index + 1}
                                            </span>
                                        </div>

                                        {winner.prize && (
                                            <div className="mb-4">
                                                <Badge className="border-purple-500/30 bg-purple-500/20 text-purple-300">
                                                    {winner.prize.name}
                                                </Badge>
                                            </div>
                                        )}

                                        <h3 className="mb-2 text-2xl font-bold">
                                            {winner.name}
                                        </h3>
                                        <p className="text-sm text-zinc-400">
                                            {winner.email}
                                        </p>

                                        {winner.won_at && (
                                            <p className="mt-4 text-sm text-yellow-400">
                                                Ganador el{' '}
                                                {new Date(
                                                    winner.won_at,
                                                ).toLocaleDateString(currency.locale)}
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : (
                        <Card className="border-white/10 bg-white/10 backdrop-blur">
                            <CardContent className="p-12 text-center">
                                <Users className="mx-auto mb-4 h-16 w-16 text-zinc-400" />
                                <h3 className="mb-2 text-2xl font-bold">
                                    Aún no hay ganadores
                                </h3>
                                <p className="text-zinc-400">
                                    Los ganadores se publicarán una vez
                                    realizado el sorteo.
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Premios */}
                    <div className="mt-16">
                        <h2 className="mb-8 flex items-center justify-center gap-3 text-center text-2xl font-bold">
                            <Gift className="h-6 w-6" />
                            Premios Sorteados
                        </h2>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {raffle.prizes.map((prize) => (
                                <div
                                    key={prize.id}
                                    className="rounded-xl border border-white/10 bg-white/5 p-4 text-center"
                                >
                                    <Badge className="mb-2 bg-yellow-500/20 text-yellow-300">
                                        #{prize.position}
                                    </Badge>
                                    <h4 className="font-bold">{prize.name}</h4>
                                    {prize.quantity > 1 && (
                                        <p className="text-sm text-zinc-400">
                                            x{prize.quantity}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Volver a la rifa */}
                    <div className="mt-16 text-center">
                        <Link
                            href={`/rifa/${raffle.slug}`}
                            className="inline-flex items-center gap-2 rounded-full bg-white/10 px-6 py-3 transition-colors hover:bg-white/20"
                        >
                            Volver a la Rifas
                        </Link>
                    </div>
                </div>
            </div>

            {/* Footer */}
            <footer className="border-t border-white/10 px-4 py-8">
                <div className="mx-auto max-w-4xl text-center text-sm text-zinc-500">
                    <p>© 2026 {raffle.title}</p>
                </div>
            </footer>
        </div>
    );
}
