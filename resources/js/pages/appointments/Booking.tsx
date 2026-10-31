import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    Clock,
    Store,
    ChevronLeft,
    CheckCircle2,
    CheckCircle,
    MessageSquare,
    CreditCard,
    Wallet,
    User,
    Calendar as CalendarIcon,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { DateTimePicker } from '@/components/ui/date-time-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface PaymentConfig {
    webpay_active: boolean;
    paypal_active: boolean;
    mercadopago_active: boolean;
}

export default function Booking({
    profile,
    services,
    paymentConfig,
}: {
    profile: any;
    services: any[];
    paymentConfig: PaymentConfig;
}) {
    const [step, setStep] = useState(1);
    const { data, setData, post, processing, errors, reset } = useForm({
        service_id:
            new URLSearchParams(window.location.search).get('service_id') || '',
        start_time: '',
        client_name: '',
        client_email: '',
        payment_method: '',
    });

    const selectedService = services.find(
        (s) => s.id.toString() === data.service_id,
    );
    const servicePrice = selectedService?.precio_venta ?? 0;

    const handleNext = () => {
        if (
            !data.service_id ||
            !data.start_time ||
            !data.client_name ||
            !data.client_email
        ) {
            alert('Por favor completa todos los campos.');
            return;
        }
        setStep(2);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.payment_method) {
            alert('Por favor selecciona un método de pago.');
            return;
        }
        post(`/booking/${profile.slug}`, {
            onSuccess: () => {
                reset();
                setStep(3);
            },
        });
    };

    if (step === 3) {
        const isMessage = data.payment_method === 'message';
        return (
            <div className="flex min-h-screen items-center justify-center bg-slate-50 p-4 dark:bg-slate-950">
                <div className="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-12 text-center shadow-xl dark:border-slate-800 dark:bg-slate-900">
                    <div
                        className={`h-20 w-20 ${isMessage ? 'bg-blue-100 text-blue-600' : 'bg-green-100 text-green-600'} mx-auto mb-6 flex items-center justify-center rounded-full`}
                    >
                        {isMessage ? (
                            <MessageSquare className="h-10 w-10" />
                        ) : (
                            <CheckCircle2 className="h-10 w-10" />
                        )}
                    </div>
                    <h2 className="mb-4 text-3xl font-bold text-slate-900 dark:text-white">
                        {isMessage ? 'Solicitud Enviada' : '¡Pago Iniciado!'}
                    </h2>
                    <p className="mb-8 text-slate-500">
                        {isMessage
                            ? 'Tu solicitud fue enviada al vendedor. Te contactará pronto para coordinar el pago y confirmar la cita.'
                            : `Serás redirigido al procesador de pago para completar la transacción de $${servicePrice.toLocaleString()}.`}
                    </p>
                    <Button
                        onClick={() =>
                            (window.location.href = `/tienda/${profile.slug}`)
                        }
                        className="w-full"
                    >
                        Volver a la Tienda
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-slate-50 font-sans dark:bg-slate-950">
            <Head title={`Reservar en ${profile.title}`} />

            <header className="sticky top-0 z-50 border-b border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div className="mx-auto flex max-w-4xl items-center justify-between">
                    <div className="flex items-center gap-2 text-slate-900 dark:text-white">
                        {profile.logo ? (
                            <img
                                src={profile.logo}
                                alt="Logo"
                                className="h-10 w-10 rounded-full object-cover"
                            />
                        ) : (
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <Store className="h-5 w-5" />
                            </div>
                        )}
                        <h1 className="text-xl font-bold">{profile.title}</h1>
                    </div>
                    <div className="flex gap-2 text-xs font-semibold text-slate-400">
                        <span className={step >= 1 ? 'text-primary' : ''}>
                            1. Detalles
                        </span>
                        <span>/</span>
                        <span className={step >= 2 ? 'text-primary' : ''}>
                            2. Pago
                        </span>
                    </div>
                </div>
            </header>

            <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-800 dark:bg-slate-900">
                    {step === 1 ? (
                        <div className="space-y-8 p-8">
                            <div className="text-center">
                                <h2 className="text-3xl font-extrabold text-slate-900 dark:text-white">
                                    Escoge tu Servicio
                                </h2>
                                <p className="mt-2 text-slate-500">
                                    Selecciona el servicio y la hora que más te
                                    acomode.
                                </p>
                            </div>

                            <div className="space-y-6">
                                <div>
                                    <Label className="text-base font-bold">
                                        1. Selección de Servicio
                                    </Label>
                                    <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        {services.map((service) => {
                                            const isSelected =
                                                data.service_id ===
                                                service.id.toString();
                                            return (
                                                <label
                                                    key={service.id}
                                                    className={`relative flex cursor-pointer flex-col rounded-xl border-2 p-4 transition-all ${isSelected ? 'border-primary bg-primary/5 ring-4 ring-primary/10' : 'border-slate-200 hover:border-slate-300 dark:border-slate-800'}`}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="service_id"
                                                        value={service.id}
                                                        className="sr-only"
                                                        onChange={(e) =>
                                                            setData(
                                                                'service_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                    {isSelected && (
                                                        <CheckCircle className="absolute top-2 right-2 h-5 w-5 text-primary" />
                                                    )}
                                                    <span className="font-bold text-slate-900 dark:text-white">
                                                        {service.nombre}
                                                    </span>
                                                    <span className="mt-1 flex items-center gap-1 text-sm text-slate-500">
                                                        <Clock className="h-3 w-3" />{' '}
                                                        {service.duracion} min
                                                    </span>
                                                    <span className="mt-2 text-lg font-bold text-primary">
                                                        $
                                                        {Number(
                                                            service.precio_venta,
                                                        ).toLocaleString()}
                                                    </span>
                                                    {service.providers &&
                                                        service.providers
                                                            .length > 0 && (
                                                            <div className="mt-3 border-t border-slate-100 pt-3 dark:border-slate-700">
                                                                <p className="mb-2 text-xs font-semibold text-slate-500">
                                                                    Profesionales:
                                                                </p>
                                                                <div className="flex -space-x-2">
                                                                    {service.providers.map(
                                                                        (
                                                                            provider: any,
                                                                        ) =>
                                                                            provider.profile_photo_url ? (
                                                                                <img
                                                                                    key={
                                                                                        provider.id
                                                                                    }
                                                                                    src={
                                                                                        provider.profile_photo_url
                                                                                    }
                                                                                    alt={
                                                                                        provider.name
                                                                                    }
                                                                                    title={
                                                                                        provider.name
                                                                                    }
                                                                                    className="h-8 w-8 rounded-full border-2 border-white object-cover"
                                                                                />
                                                                            ) : (
                                                                                <div
                                                                                    key={
                                                                                        provider.id
                                                                                    }
                                                                                    title={
                                                                                        provider.name
                                                                                    }
                                                                                    className="flex h-8 w-8 items-center justify-center rounded-full border-2 border-white bg-slate-200"
                                                                                >
                                                                                    <User className="h-4 w-4 text-slate-500" />
                                                                                </div>
                                                                            ),
                                                                    )}
                                                                </div>
                                                            </div>
                                                        )}
                                                </label>
                                            );
                                        })}
                                    </div>
                                    {errors.service_id && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {errors.service_id}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label className="flex items-center gap-2 text-base font-bold">
                                        <CalendarIcon className="h-4 w-4 text-primary" />
                                        2. Fecha y Hora
                                    </Label>
                                    <div className="mt-3">
                                        <DateTimePicker
                                            value={data.start_time}
                                            onChange={(v) =>
                                                setData('start_time', v)
                                            }
                                            placeholder="Selecciona fecha y hora"
                                        />
                                    </div>
                                    {errors.start_time && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {errors.start_time}
                                        </p>
                                    )}
                                </div>

                                <div className="border-t border-slate-100 pt-6 dark:border-slate-800">
                                    <Label className="text-base font-bold">
                                        3. Tus Datos para el Recordatorio
                                    </Label>
                                    <div className="mt-3 grid gap-4">
                                        <div>
                                            <Label>Nombre Completo</Label>
                                            <Input
                                                className="h-12"
                                                value={data.client_name}
                                                onChange={(e) =>
                                                    setData(
                                                        'client_name',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                            {errors.client_name && (
                                                <p className="text-sm text-red-500">
                                                    {errors.client_name}
                                                </p>
                                            )}
                                        </div>
                                        <div>
                                            <Label>Correo Electrónico</Label>
                                            <Input
                                                type="email"
                                                className="h-12"
                                                value={data.client_email}
                                                onChange={(e) =>
                                                    setData(
                                                        'client_email',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                            {errors.client_email && (
                                                <p className="text-sm text-red-500">
                                                    {errors.client_email}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <Button
                                onClick={handleNext}
                                className="mt-4 h-14 w-full text-lg font-bold shadow-lg shadow-primary/20"
                            >
                                Continuar al Pago
                                <CreditCard className="ml-2 h-5 w-5" />
                            </Button>
                        </div>
                    ) : (
                        <div className="p-8">
                            <button
                                onClick={() => setStep(1)}
                                className="mb-6 flex items-center text-slate-500 transition-colors hover:text-slate-900"
                            >
                                <ChevronLeft className="h-5 w-5" />
                                Volver a detalles
                            </button>

                            <div className="mb-8 text-center">
                                <h2 className="text-2xl font-bold">
                                    Selecciona tu Método de Pago
                                </h2>
                                <p className="text-slate-500">
                                    Elige cómo quieres pagar este servicio.
                                </p>
                            </div>

                            <div className="mb-8 space-y-3 rounded-xl border border-primary/10 bg-gradient-to-br from-primary/5 to-transparent p-5">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                                            <Store className="h-4 w-4 text-primary" />
                                        </div>
                                        <span className="font-semibold text-slate-900 dark:text-white">
                                            {selectedService?.nombre}
                                        </span>
                                    </div>
                                    <span className="text-lg font-bold text-primary">
                                        ${servicePrice.toLocaleString()}
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm text-slate-500">
                                    <span className="flex items-center gap-1.5">
                                        <CalendarIcon className="h-4 w-4" />{' '}
                                        Fecha
                                    </span>
                                    <span className="font-medium">
                                        {format(
                                            new Date(data.start_time),
                                            'PPPp',
                                            { locale: es },
                                        )}
                                    </span>
                                </div>
                                <div className="flex justify-between border-t border-primary/10 pt-3 text-lg font-bold">
                                    <span>Total a Pagar</span>
                                    <span className="text-primary">
                                        ${servicePrice.toLocaleString()}
                                    </span>
                                </div>
                            </div>

                            <form onSubmit={handleSubmit} className="space-y-4">
                                <button
                                    type="button"
                                    onClick={() =>
                                        setData('payment_method', 'message')
                                    }
                                    className={`flex w-full items-center gap-4 rounded-xl border-2 p-4 text-left transition-all ${data.payment_method === 'message' ? 'border-blue-500 bg-blue-50 ring-4 ring-blue-100 dark:bg-blue-950' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'}`}
                                >
                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                        <MessageSquare className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div className="flex-1">
                                        <p className="font-bold text-slate-900 dark:text-white">
                                            Continuar por Mensaje
                                        </p>
                                        <p className="text-sm text-slate-500">
                                            El vendedor te contactará para
                                            coordinar el pago
                                        </p>
                                    </div>
                                    <div
                                        className={`flex h-5 w-5 items-center justify-center rounded-full border-2 ${data.payment_method === 'message' ? 'border-blue-500' : 'border-slate-300'}`}
                                    >
                                        {data.payment_method === 'message' && (
                                            <div className="h-3 w-3 rounded-full bg-blue-500" />
                                        )}
                                    </div>
                                </button>

                                {paymentConfig.webpay_active && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData('payment_method', 'webpay')
                                        }
                                        className={`flex w-full items-center gap-4 rounded-xl border-2 p-4 text-left transition-all ${data.payment_method === 'webpay' ? 'border-indigo-500 bg-indigo-50 ring-4 ring-indigo-100 dark:bg-indigo-950' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'}`}
                                    >
                                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900">
                                            <Wallet className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                                        </div>
                                        <div className="flex-1">
                                            <p className="font-bold text-slate-900 dark:text-white">
                                                Webpay Plus
                                            </p>
                                            <p className="text-sm text-slate-500">
                                                Paga con tarjeta de crédito,
                                                débito o saldo
                                            </p>
                                        </div>
                                        <div
                                            className={`flex h-5 w-5 items-center justify-center rounded-full border-2 ${data.payment_method === 'webpay' ? 'border-indigo-500' : 'border-slate-300'}`}
                                        >
                                            {data.payment_method ===
                                                'webpay' && (
                                                <div className="h-3 w-3 rounded-full bg-indigo-500" />
                                            )}
                                        </div>
                                    </button>
                                )}

                                {paymentConfig.paypal_active && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData('payment_method', 'paypal')
                                        }
                                        className={`flex w-full items-center gap-4 rounded-xl border-2 p-4 text-left transition-all ${data.payment_method === 'paypal' ? 'border-blue-500 bg-blue-50 ring-4 ring-blue-100 dark:bg-blue-950' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'}`}
                                    >
                                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                            <CreditCard className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <div className="flex-1">
                                            <p className="font-bold text-slate-900 dark:text-white">
                                                PayPal
                                            </p>
                                            <p className="text-sm text-slate-500">
                                                Paga con tu cuenta de PayPal
                                            </p>
                                        </div>
                                        <div
                                            className={`flex h-5 w-5 items-center justify-center rounded-full border-2 ${data.payment_method === 'paypal' ? 'border-blue-500' : 'border-slate-300'}`}
                                        >
                                            {data.payment_method ===
                                                'paypal' && (
                                                <div className="h-3 w-3 rounded-full bg-blue-500" />
                                            )}
                                        </div>
                                    </button>
                                )}

                                {paymentConfig.mercadopago_active && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData(
                                                'payment_method',
                                                'mercadopago',
                                            )
                                        }
                                        className={`flex w-full items-center gap-4 rounded-xl border-2 p-4 text-left transition-all ${data.payment_method === 'mercadopago' ? 'border-sky-500 bg-sky-50 ring-4 ring-sky-100 dark:bg-sky-950' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'}`}
                                    >
                                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-sky-100 dark:bg-sky-900">
                                            <Wallet className="h-6 w-6 text-sky-600 dark:text-sky-400" />
                                        </div>
                                        <div className="flex-1">
                                            <p className="font-bold text-slate-900 dark:text-white">
                                                Mercado Pago
                                            </p>
                                            <p className="text-sm text-slate-500">
                                                Paga con tarjeta, efectivo o
                                                saldo de Mercado Pago
                                            </p>
                                        </div>
                                        <div
                                            className={`flex h-5 w-5 items-center justify-center rounded-full border-2 ${data.payment_method === 'mercadopago' ? 'border-sky-500' : 'border-slate-300'}`}
                                        >
                                            {data.payment_method ===
                                                'mercadopago' && (
                                                <div className="h-3 w-3 rounded-full bg-sky-500" />
                                            )}
                                        </div>
                                    </button>
                                )}

                                <Button
                                    type="submit"
                                    disabled={
                                        processing || !data.payment_method
                                    }
                                    className="mt-6 h-14 w-full text-lg font-bold shadow-lg shadow-primary/20"
                                >
                                    {processing
                                        ? 'Procesando...'
                                        : !data.payment_method
                                          ? 'Selecciona un método de pago'
                                          : data.payment_method === 'message'
                                            ? 'Enviar Solicitud'
                                            : `Pagar $${servicePrice.toLocaleString()}`}
                                </Button>
                            </form>
                        </div>
                    )}
                </div>
                <p className="mt-8 text-center text-sm text-slate-400 italic">
                    Términos y condiciones: Reembolsos disponibles hasta 24
                    horas antes de la cita.
                </p>
            </div>
        </div>
    );
}
