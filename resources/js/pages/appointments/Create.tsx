import { Head, router, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import 'date-fns/locale';
import {
    Calendar,
    ArrowLeft,
    Loader2
} from 'lucide-react';
import React from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';

export default function Create({
    services,
    clients,
}: {
    services: any[];
    clients: any[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        client_id: '',
        producto_id: '',
        start_time: '',
        end_time: '',
        notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/appointments');
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Gestión de Citas', href: '/appointments' },
                { title: 'Nueva Cita', href: '/appointments/create' },
            ]}
        >
            <Head title="Nueva Cita" />

            <div className="mx-auto max-w-2xl px-4 py-6 sm:px-6 lg:px-8">
                <Button
                    variant="ghost"
                    size="sm"
                    className="mb-4 gap-2 text-slate-500"
                    onClick={() => router.visit('/appointments')}
                >
                    <ArrowLeft className="h-4 w-4" />
                    Volver
                </Button>

                <Card className="rounded-2xl border-slate-200/70">
                    <CardHeader className="border-b border-slate-100 px-6 py-5">
                        <CardTitle className="flex items-center gap-3 text-lg font-black text-slate-900">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                <Calendar className="h-5 w-5" />
                            </div>
                            Nueva Cita
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-6">
                        <form onSubmit={submit} className="space-y-5">
                            <div className="space-y-2">
                                <Label className="text-sm font-bold text-slate-700">
                                    Cliente
                                </Label>
                                <select
                                    value={data.client_id}
                                    onChange={(e) => setData('client_id', e.target.value)}
                                    className="flex h-10 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                    required
                                >
                                    <option value="">Seleccionar cliente...</option>
                                    {clients.map((c) => (
                                        <option key={c.id} value={c.user_id}>
                                            {c.nombre}{c.rut ? ` (${c.rut})` : ''} — {c.telefono || c.email}
                                        </option>
                                    ))}
                                </select>
                                {errors.client_id && (
                                    <p className="text-xs text-red-500">{errors.client_id}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label className="text-sm font-bold text-slate-700">
                                    Servicio
                                </Label>
                                <select
                                    value={data.producto_id}
                                    onChange={(e) => setData('producto_id', e.target.value)}
                                    className="flex h-10 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                    required
                                >
                                    <option value="">Seleccionar servicio...</option>
                                    {services.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.nombre} — ${Number(s.precio_venta).toLocaleString()}
                                            {s.duracion ? ` (${s.duracion} min)` : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.producto_id && (
                                    <p className="text-xs text-red-500">{errors.producto_id}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                <div className="space-y-2">
                                    <Label className="text-sm font-bold text-slate-700">
                                        Inicio
                                    </Label>
                                    <Input
                                        type="datetime-local"
                                        value={data.start_time}
                                        onChange={(e) => {
                                            setData('start_time', e.target.value);
                                            if (!data.end_time || data.end_time <= e.target.value) {
                                                const end = new Date(new Date(e.target.value).getTime() + 60 * 60 * 1000);
                                                setData('end_time', format(end, "yyyy-MM-dd'T'HH:mm"));
                                            }
                                        }}
                                        className="h-10 rounded-xl"
                                        required
                                    />
                                    {errors.start_time && (
                                        <p className="text-xs text-red-500">{errors.start_time}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-sm font-bold text-slate-700">
                                        Fin
                                    </Label>
                                    <Input
                                        type="datetime-local"
                                        value={data.end_time}
                                        onChange={(e) => setData('end_time', e.target.value)}
                                        className="h-10 rounded-xl"
                                        required
                                    />
                                    {errors.end_time && (
                                        <p className="text-xs text-red-500">{errors.end_time}</p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label className="text-sm font-bold text-slate-700">
                                    Notas (opcional)
                                </Label>
                                <textarea
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    className="flex h-20 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 resize-none"
                                    placeholder="Notas adicionales..."
                                />
                            </div>

                            <div className="flex gap-3 pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="flex-1 h-11 rounded-xl"
                                    onClick={() => router.visit('/appointments')}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold"
                                >
                                    {processing ? (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <>
                                            <Calendar className="h-4 w-4" />
                                            Crear Cita
                                        </>
                                    )}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
