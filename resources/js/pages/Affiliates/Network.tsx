import { Head } from '@inertiajs/react';
import { Users, Calendar, Award } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';

interface Affiliate {
    id: number;
    name: string;
    email: string;
    created_at: string;
}

interface Props {
    affiliates: Affiliate[];
    points: number;
}

export default function Network({ affiliates, points }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Mis Afiliados', href: '/afiliados/red' }]}>
            <Head title="Mis Afiliados" />
            
            <div className="mx-auto max-w-6xl p-6">
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                        <Users className="h-8 w-8 text-primary" />
                        Mi Red de Afiliados
                    </h1>
                    <p className="mt-2 text-zinc-500">
                        Gestiona y visualiza a los usuarios que se han registrado con tu código.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3 mb-8">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total de Afiliados
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{affiliates.length}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Puntos Generados
                            </CardTitle>
                            <Award className="h-4 w-4 text-primary" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{points} pts</div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Historial de Referidos</CardTitle>
                        <CardDescription>
                            Usuarios que han ingresado gracias a tu recomendación.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {affiliates.length === 0 ? (
                            <div className="text-center py-12 text-muted-foreground">
                                <Users className="h-12 w-12 mx-auto mb-4 opacity-20" />
                                <p>Aún no tienes afiliados.</p>
                                <p className="text-sm mt-1">Comparte tu código para empezar a ganar puntos.</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nombre</TableHead>
                                        <TableHead>Correo</TableHead>
                                        <TableHead className="text-right">Fecha de Registro</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {affiliates.map((affiliate) => (
                                        <TableRow key={affiliate.id}>
                                            <TableCell className="font-medium">{affiliate.name}</TableCell>
                                            <TableCell>{affiliate.email}</TableCell>
                                            <TableCell className="text-right flex items-center justify-end gap-2 text-muted-foreground">
                                                <Calendar className="h-3 w-3" />
                                                {new Date(affiliate.created_at).toLocaleDateString()}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
