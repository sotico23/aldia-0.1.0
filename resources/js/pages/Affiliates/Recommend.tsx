import { Head } from '@inertiajs/react';
import { Award, Copy, Share2, Facebook, Twitter, Mail } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';

interface Props {
    referralCode: string;
    points: number;
    referralLink: string;
}

export default function Recommend({
    referralCode,
    points,
    referralLink,
}: Props) {
    const handleCopy = (text: string) => {
        navigator.clipboard.writeText(text);
        toast.success('¡Enlace copiado al portapapeles!');
    };

    const handleShare = (platform: string) => {
        const text = encodeURIComponent(
            '¡Únete a la plataforma usando mi código de referido!',
        );
        const url = encodeURIComponent(referralLink);

        switch (platform) {
            case 'facebook':
                window.open(
                    `https://www.facebook.com/sharer/sharer.php?u=${url}`,
                    '_blank',
                );
                break;
            case 'twitter':
                window.open(
                    `https://twitter.com/intent/tweet?text=${text}&url=${url}`,
                    '_blank',
                );
                break;
            case 'email':
                window.location.href = `mailto:?subject=${text}&body=Regístrate aquí: ${url}`;
                break;
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Recomienda y Gana', href: '/afiliados/recomendar' },
            ]}
        >
            <Head title="Recomienda y Gana" />

            <div className="mx-auto max-w-4xl p-6">
                <div className="mb-8">
                    <h1 className="flex items-center gap-2 text-3xl font-bold text-zinc-900 dark:text-white">
                        <Award className="h-8 w-8 text-primary" />
                        Recomienda y Gana
                    </h1>
                    <p className="mt-2 text-zinc-500">
                        Invita a tus amigos a unirse a la plataforma y gana
                        puntos por cada registro.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card className="border-primary/20 bg-gradient-to-br from-primary/10 to-transparent">
                        <CardHeader>
                            <CardTitle>Tus Puntos</CardTitle>
                            <CardDescription>
                                Puntos acumulados por referencias
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="text-5xl font-bold text-primary">
                                {points}{' '}
                                <span className="text-xl font-normal text-muted-foreground">
                                    pts
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Tu Código</CardTitle>
                            <CardDescription>
                                Comparte este código o tu enlace personal
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex gap-2">
                                <Input
                                    value={referralCode}
                                    readOnly
                                    className="text-center font-mono text-lg font-bold tracking-widest"
                                />
                                <Button
                                    variant="outline"
                                    onClick={() => handleCopy(referralCode)}
                                    title="Copiar código"
                                >
                                    <Copy className="h-4 w-4" />
                                </Button>
                            </div>

                            <div className="flex gap-2">
                                <Input
                                    value={referralLink}
                                    readOnly
                                    className="text-xs"
                                />
                                <Button
                                    variant="outline"
                                    onClick={() => handleCopy(referralLink)}
                                    title="Copiar enlace"
                                >
                                    <Share2 className="h-4 w-4" />
                                </Button>
                            </div>

                            <div className="flex justify-center gap-4 border-t border-border pt-4">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => handleShare('facebook')}
                                >
                                    <Facebook className="h-5 w-5 text-[#1877F2]" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => handleShare('twitter')}
                                >
                                    <Twitter className="h-5 w-5 text-emerald-500" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => handleShare('email')}
                                >
                                    <Mail className="h-5 w-5 text-zinc-600 dark:text-zinc-400" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
