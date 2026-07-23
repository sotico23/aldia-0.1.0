import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2, Globe, CreditCard, ShieldCheck, Phone, Mail, MapPin, FileText, ShoppingCart } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Categoria {
    id: number;
    nombre: string;
}

interface Proveedor {
    id: number;
    nombre: string;
    nit: string | null;
    email: string | null;
    telefono: string | null;
    direccion: string | null;
    activo: boolean;
    notas: string | null;
    categoria_id: number | null;
    categoria?: Categoria;
    nombre_empresa: string | null;
    contacto_principal: string | null;
    sitio_web: string | null;
    terminos_pago: string | null;
    tiene_acceso: boolean;
    created_at?: string;
}

export default function Show({ proveedor }: { proveedor: Proveedor }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Proveedores', href: '/proveedors' },
        { title: proveedor.nombre, href: `/proveedors/${proveedor.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={proveedor.nombre} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Link href="/proveedors">
                            <Button variant="ghost" size="sm" className="rounded-xl">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Volver
                            </Button>
                        </Link>
                        <div>
                            <div className="mb-1 flex items-center gap-2">
                                <Building2 className="h-5 w-5 text-primary" />
                                <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                    Supply Chain Division
                                </span>
                            </div>
                            <h1 className="text-3xl font-black tracking-tight text-foreground">
                                {proveedor.nombre}
                            </h1>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Badge
                            variant="outline"
                            className={`rounded-full border-none px-3 py-1 text-[10px] font-black uppercase ${
                                proveedor.activo
                                    ? 'bg-green-500/10 text-green-600'
                                    : 'bg-red-500/10 text-red-600'
                            }`}
                        >
                            {proveedor.activo ? 'Activo' : 'Inactivo'}
                        </Badge>
                        {proveedor.categoria && (
                            <Badge
                                variant="outline"
                                className="rounded-full border-none bg-primary/10 px-3 py-1 text-[10px] font-black text-primary uppercase"
                            >
                                {proveedor.categoria.nombre}
                            </Badge>
                        )}
                        {proveedor.tiene_acceso && (
                            <Badge
                                variant="outline"
                                className="rounded-full border-none bg-blue-500/10 px-3 py-1 text-[10px] font-black text-blue-600 uppercase"
                            >
                                <ShieldCheck className="mr-1 h-3 w-3" />
                                Portal
                            </Badge>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card className="col-span-2 rounded-3xl border-none shadow-xl shadow-foreground/5">
                        <CardHeader className="pb-4">
                            <CardTitle className="flex items-center gap-2 text-lg font-black">
                                <Building2 className="h-5 w-5 text-primary" />
                                Información General
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div className="space-y-1.5">
                                    <p className="flex items-center gap-1.5 text-[11px] font-black tracking-widest text-muted-foreground uppercase">
                                        <Building2 className="h-3.5 w-3.5" />
                                        RUT / NIT
                                    </p>
                                    <p className="rounded-xl bg-muted/30 px-4 py-2.5 font-bold">
                                        {proveedor.nit || 'No registrado'}
                                    </p>
                                </div>
                                <div className="space-y-1.5">
                                    <p className="flex items-center gap-1.5 text-[11px] font-black tracking-widest text-muted-foreground uppercase">
                                        <Phone className="h-3.5 w-3.5" />
                                        Teléfono
                                    </p>
                                    <p className="rounded-xl bg-muted/30 px-4 py-2.5 font-bold">
                                        {proveedor.telefono || 'No registrado'}
                                    </p>
                                </div>
                                <div className="space-y-1.5">
                                    <p className="flex items-center gap-1.5 text-[11px] font-black tracking-widest text-muted-foreground uppercase">
                                        <Mail className="h-3.5 w-3.5" />
                                        Email
                                    </p>
                                    <p className="rounded-xl bg-muted/30 px-4 py-2.5 font-bold">
                                        {proveedor.email || 'No registrado'}
                                    </p>
                                </div>
                                <div className="space-y-1.5">
                                    <p className="flex items-center gap-1.5 text-[11px] font-black tracking-widest text-muted-foreground uppercase">
                                        <Globe className="h-3.5 w-3.5" />
                                        Sitio Web
                                    </p>
                                    <p className="rounded-xl bg-muted/30 px-4 py-2.5 font-bold">
                                        {proveedor.sitio_web ? (
                                            <a href={proveedor.sitio_web} target="_blank" rel="noopener noreferrer" className="text-primary underline underline-offset-2">
                                                {proveedor.sitio_web}
                                            </a>
                                        ) : 'No registrado'}
                                    </p>
                                </div>
                                <div className="space-y-1.5 md:col-span-2">
                                    <p className="flex items-center gap-1.5 text-[11px] font-black tracking-widest text-muted-foreground uppercase">
                                        <MapPin className="h-3.5 w-3.5" />
                                        Dirección
                                    </p>
                                    <p className="rounded-xl bg-muted/30 px-4 py-2.5 font-bold">
                                        {proveedor.direccion || 'No registrada'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="rounded-3xl border-none shadow-xl shadow-foreground/5">
                        <CardHeader className="pb-4">
                            <CardTitle className="flex items-center gap-2 text-lg font-black">
                                <CreditCard className="h-5 w-5 text-primary" />
                                Comercial
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="space-y-1.5">
                                <p className="text-[11px] font-black tracking-widest text-muted-foreground uppercase">
                                    Contacto Principal
                                </p>
                                <p className="rounded-xl bg-muted/30 px-4 py-2.5 font-bold">
                                    {proveedor.contacto_principal || 'No registrado'}
                                </p>
                            </div>
                            <div className="space-y-1.5">
                                <p className="text-[11px] font-black tracking-widest text-muted-foreground uppercase">
                                    Términos de Pago
                                </p>
                                <p className="rounded-xl bg-muted/30 px-4 py-2.5 font-bold">
                                    {proveedor.terminos_pago || 'No especificados'}
                                </p>
                            </div>
                            <div className="space-y-1.5">
                                <p className="flex items-center gap-1.5 text-[11px] font-black tracking-widest text-muted-foreground uppercase">
                                    <ShieldCheck className="h-3.5 w-3.5" />
                                    Acceso Portal
                                </p>
                                <Badge
                                    variant="outline"
                                    className={`rounded-full border-none px-3 py-1 text-[10px] font-black uppercase ${
                                        proveedor.tiene_acceso
                                            ? 'bg-blue-500/10 text-blue-600'
                                            : 'bg-gray-500/10 text-gray-500'
                                    }`}
                                >
                                    {proveedor.tiene_acceso ? 'Habilitado' : 'Deshabilitado'}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    {proveedor.notas && (
                        <Card className="col-span-1 rounded-3xl border-none shadow-xl shadow-foreground/5 lg:col-span-3">
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg font-black">
                                    <FileText className="h-5 w-5 text-primary" />
                                    Notas
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="whitespace-pre-wrap rounded-xl bg-muted/30 px-4 py-3 font-medium leading-relaxed">
                                    {proveedor.notas}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    <Card className="col-span-1 rounded-3xl border-2 border-dashed border-primary/20 bg-primary/5 shadow-xl shadow-foreground/5 lg:col-span-3">
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-3">
                                <div className="rounded-2xl bg-primary/10 p-3">
                                    <ShoppingCart className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <CardTitle className="text-lg font-black">
                                        Historial de Compras y Movimientos
                                    </CardTitle>
                                    <CardDescription className="font-medium">
                                        Registro de órdenes de compra, recibos y transacciones
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col items-center justify-center gap-3 rounded-2xl bg-background/50 py-12">
                                <ShoppingCart className="h-12 w-12 text-muted-foreground/40" />
                                <p className="text-lg font-black text-muted-foreground/60">
                                    Próximamente
                                </p>
                                <p className="text-sm font-medium text-muted-foreground/40">
                                    El historial de compras estará disponible en una próxima actualización.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
