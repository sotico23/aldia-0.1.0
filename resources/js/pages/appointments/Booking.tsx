import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { Clock, Store, ChevronLeft, CheckCircle2, CheckCircle, MessageSquare, CreditCard, Wallet, User, Calendar as CalendarIcon } from 'lucide-react';
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

export default function Booking({ profile, services, paymentConfig }: { profile: any, services: any[], paymentConfig: PaymentConfig }) {
    const [step, setStep] = useState(1);
    const { data, setData, post, processing, errors, reset } = useForm({
        service_id: new URLSearchParams(window.location.search).get('service_id') || '',
        start_time: '',
        client_name: '',
        client_email: '',
        payment_method: '',
    });

    const selectedService = services.find(s => s.id.toString() === data.service_id);
    const servicePrice = selectedService?.precio_venta ?? 0;

    const handleNext = () => {
        if (!data.service_id || !data.start_time || !data.client_name || !data.client_email) {
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
            }
        });
    };

    if (step === 3) {
        const isMessage = data.payment_method === 'message';
        return (
            <div className="min-h-screen bg-slate-50 dark:bg-slate-950 flex items-center justify-center p-4">
                <div className="bg-white dark:bg-slate-900 rounded-3xl shadow-xl p-12 text-center max-w-md w-full border border-slate-200 dark:border-slate-800">
                    <div className={`h-20 w-20 ${isMessage ? 'bg-blue-100 text-blue-600' : 'bg-green-100 text-green-600'} rounded-full flex items-center justify-center mx-auto mb-6`}>
                        {isMessage ? <MessageSquare className="h-10 w-10" /> : <CheckCircle2 className="h-10 w-10" />}
                    </div>
                    <h2 className="text-3xl font-bold text-slate-900 dark:text-white mb-4">
                        {isMessage ? 'Solicitud Enviada' : '¡Pago Iniciado!'}
                    </h2>
                    <p className="text-slate-500 mb-8">
                        {isMessage
                            ? 'Tu solicitud fue enviada al vendedor. Te contactará pronto para coordinar el pago y confirmar la cita.'
                            : `Serás redirigido al procesador de pago para completar la transacción de $${servicePrice.toLocaleString()}.`}
                    </p>
                    <Button onClick={() => window.location.href = `/tienda/${profile.slug}`} className="w-full">
                        Volver a la Tienda
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 font-sans">
            <Head title={`Reservar en ${profile.title}`} />

            <header className="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 p-4 sticky top-0 z-50">
                <div className="mx-auto max-w-4xl flex items-center justify-between">
                    <div className="flex items-center gap-2 text-slate-900 dark:text-white">
                        {profile.logo ? (
                            <img src={profile.logo} alt="Logo" className="h-10 w-10 rounded-full object-cover" />
                        ) : (
                            <div className="h-10 w-10 bg-primary/10 text-primary flex items-center justify-center rounded-full">
                                <Store className="h-5 w-5" />
                            </div>
                        )}
                        <h1 className="text-xl font-bold">{profile.title}</h1>
                    </div>
                    <div className="flex gap-2 text-xs font-semibold text-slate-400">
                        <span className={step >= 1 ? 'text-primary' : ''}>1. Detalles</span>
                        <span>/</span>
                        <span className={step >= 2 ? 'text-primary' : ''}>2. Pago</span>
                    </div>
                </div>
            </header>

            <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                    {step === 1 ? (
                        <div className="p-8 space-y-8">
                            <div className="text-center">
                                <h2 className="text-3xl font-extrabold text-slate-900 dark:text-white">Escoge tu Servicio</h2>
                                <p className="mt-2 text-slate-500">Selecciona el servicio y la hora que más te acomode.</p>
                            </div>

                            <div className="space-y-6">
                                <div>
                                    <Label className="text-base font-bold">1. Selección de Servicio</Label>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
                                        {services.map(service => {
                                            const isSelected = data.service_id === service.id.toString();
                                            return (
                                            <label key={service.id} className={`relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all ${isSelected ? 'border-primary bg-primary/5 ring-4 ring-primary/10' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300'}`}>
                                                <input
                                                    type="radio"
                                                    name="service_id"
                                                    value={service.id}
                                                    className="sr-only"
                                                    onChange={e => setData('service_id', e.target.value)}
                                                />
                                                {isSelected && (
                                                    <CheckCircle className="absolute top-2 right-2 h-5 w-5 text-primary" />
                                                )}
                                                <span className="font-bold text-slate-900 dark:text-white">{service.nombre}</span>
                                                <span className="text-sm text-slate-500 mt-1 flex items-center gap-1"><Clock className="h-3 w-3" /> {service.duracion} min</span>
                                                <span className="mt-2 text-primary text-lg font-bold">${Number(service.precio_venta).toLocaleString()}</span>
                                                {service.providers && service.providers.length > 0 && (
                                                    <div className="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                                                        <p className="text-xs font-semibold text-slate-500 mb-2">Profesionales:</p>
                                                        <div className="flex -space-x-2">
                                                            {service.providers.map((provider: any) => (
                                                                provider.profile_photo_url ? (
                                                                    <img
                                                                        key={provider.id}
                                                                        src={provider.profile_photo_url}
                                                                        alt={provider.name}
                                                                        title={provider.name}
                                                                        className="h-8 w-8 rounded-full border-2 border-white object-cover"
                                                                    />
                                                                ) : (
                                                                    <div
                                                                        key={provider.id}
                                                                        title={provider.name}
                                                                        className="h-8 w-8 rounded-full border-2 border-white bg-slate-200 flex items-center justify-center"
                                                                    >
                                                                        <User className="h-4 w-4 text-slate-500" />
                                                                    </div>
                                                                )
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}
                                            </label>
                                            );
                                        })}
                                    </div>
                                    {errors.service_id && <p className="text-red-500 text-sm mt-1">{errors.service_id}</p>}
                                </div>

                                <div>
                                    <Label className="text-base font-bold flex items-center gap-2">
                                        <CalendarIcon className="h-4 w-4 text-primary" />
                                        2. Fecha y Hora
                                    </Label>
                                    <div className="mt-3">
                                        <DateTimePicker
                                            value={data.start_time}
                                            onChange={(v) => setData('start_time', v)}
                                            placeholder="Selecciona fecha y hora"
                                        />
                                    </div>
                                    {errors.start_time && <p className="text-red-500 text-sm mt-1">{errors.start_time}</p>}
                                </div>

                                <div className="pt-6 border-t border-slate-100 dark:border-slate-800">
                                    <Label className="text-base font-bold">3. Tus Datos para el Recordatorio</Label>
                                    <div className="grid gap-4 mt-3">
                                        <div>
                                            <Label>Nombre Completo</Label>
                                            <Input className="h-12" value={data.client_name} onChange={e => setData('client_name', e.target.value)} required />
                                            {errors.client_name && <p className="text-red-500 text-sm">{errors.client_name}</p>}
                                        </div>
                                        <div>
                                            <Label>Correo Electrónico</Label>
                                            <Input type="email" className="h-12" value={data.client_email} onChange={e => setData('client_email', e.target.value)} required />
                                            {errors.client_email && <p className="text-red-500 text-sm">{errors.client_email}</p>}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <Button onClick={handleNext} className="w-full h-14 text-lg font-bold mt-4 shadow-lg shadow-primary/20">
                                Continuar al Pago
                                <CreditCard className="ml-2 h-5 w-5" />
                            </Button>
                        </div>
                    ) : (
                        <div className="p-8">
                            <button onClick={() => setStep(1)} className="flex items-center text-slate-500 hover:text-slate-900 mb-6 transition-colors">
                                <ChevronLeft className="h-5 w-5" />
                                Volver a detalles
                            </button>

                            <div className="text-center mb-8">
                                <h2 className="text-2xl font-bold">Selecciona tu Método de Pago</h2>
                                <p className="text-slate-500">Elige cómo quieres pagar este servicio.</p>
                            </div>

                            <div className="bg-gradient-to-br from-primary/5 to-transparent border border-primary/10 p-5 rounded-xl mb-8 space-y-3">
                                <div className="flex justify-between items-center">
                                    <div className="flex items-center gap-2">
                                        <div className="h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                                            <Store className="h-4 w-4 text-primary" />
                                        </div>
                                        <span className="font-semibold text-slate-900 dark:text-white">{selectedService?.nombre}</span>
                                    </div>
                                    <span className="text-primary font-bold text-lg">${servicePrice.toLocaleString()}</span>
                                </div>
                                <div className="flex justify-between text-sm text-slate-500">
                                    <span className="flex items-center gap-1.5"><CalendarIcon className="h-4 w-4" /> Fecha</span>
                                    <span className="font-medium">{format(new Date(data.start_time), "PPPp", { locale: es })}</span>
                                </div>
                                <div className="border-t border-primary/10 pt-3 flex justify-between font-bold text-lg">
                                    <span>Total a Pagar</span>
                                    <span className="text-primary">${servicePrice.toLocaleString()}</span>
                                </div>
                            </div>

                            <form onSubmit={handleSubmit} className="space-y-4">
                                <button
                                    type="button"
                                    onClick={() => setData('payment_method', 'message')}
                                    className={`w-full flex items-center gap-4 p-4 rounded-xl border-2 text-left transition-all ${data.payment_method === 'message' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950 ring-4 ring-blue-100' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'}`}
                                >
                                    <div className="h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center shrink-0">
                                        <MessageSquare className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div className="flex-1">
                                        <p className="font-bold text-slate-900 dark:text-white">Continuar por Mensaje</p>
                                        <p className="text-sm text-slate-500">El vendedor te contactará para coordinar el pago</p>
                                    </div>
                                    <div className={`h-5 w-5 rounded-full border-2 flex items-center justify-center ${data.payment_method === 'message' ? 'border-blue-500' : 'border-slate-300'}`}>
                                        {data.payment_method === 'message' && <div className="h-3 w-3 rounded-full bg-blue-500" />}
                                    </div>
                                </button>

                                {paymentConfig.webpay_active && (
                                    <button
                                        type="button"
                                        onClick={() => setData('payment_method', 'webpay')}
                                        className={`w-full flex items-center gap-4 p-4 rounded-xl border-2 text-left transition-all ${data.payment_method === 'webpay' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950 ring-4 ring-indigo-100' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'}`}
                                    >
                                        <div className="h-12 w-12 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center shrink-0">
                                            <Wallet className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                                        </div>
                                        <div className="flex-1">
                                            <p className="font-bold text-slate-900 dark:text-white">Webpay Plus</p>
                                            <p className="text-sm text-slate-500">Paga con tarjeta de crédito, débito o saldo</p>
                                        </div>
                                        <div className={`h-5 w-5 rounded-full border-2 flex items-center justify-center ${data.payment_method === 'webpay' ? 'border-indigo-500' : 'border-slate-300'}`}>
                                            {data.payment_method === 'webpay' && <div className="h-3 w-3 rounded-full bg-indigo-500" />}
                                        </div>
                                    </button>
                                )}

                                {paymentConfig.paypal_active && (
                                    <button
                                        type="button"
                                        onClick={() => setData('payment_method', 'paypal')}
                                        className={`w-full flex items-center gap-4 p-4 rounded-xl border-2 text-left transition-all ${data.payment_method === 'paypal' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950 ring-4 ring-blue-100' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'}`}
                                    >
                                        <div className="h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center shrink-0">
                                            <CreditCard className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <div className="flex-1">
                                            <p className="font-bold text-slate-900 dark:text-white">PayPal</p>
                                            <p className="text-sm text-slate-500">Paga con tu cuenta de PayPal</p>
                                        </div>
                                        <div className={`h-5 w-5 rounded-full border-2 flex items-center justify-center ${data.payment_method === 'paypal' ? 'border-blue-500' : 'border-slate-300'}`}>
                                            {data.payment_method === 'paypal' && <div className="h-3 w-3 rounded-full bg-blue-500" />}
                                        </div>
                                    </button>
                                )}

                                {paymentConfig.mercadopago_active && (
                                    <button
                                        type="button"
                                        onClick={() => setData('payment_method', 'mercadopago')}
                                        className={`w-full flex items-center gap-4 p-4 rounded-xl border-2 text-left transition-all ${data.payment_method === 'mercadopago' ? 'border-sky-500 bg-sky-50 dark:bg-sky-950 ring-4 ring-sky-100' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'}`}
                                    >
                                        <div className="h-12 w-12 rounded-full bg-sky-100 dark:bg-sky-900 flex items-center justify-center shrink-0">
                                            <Wallet className="h-6 w-6 text-sky-600 dark:text-sky-400" />
                                        </div>
                                        <div className="flex-1">
                                            <p className="font-bold text-slate-900 dark:text-white">Mercado Pago</p>
                                            <p className="text-sm text-slate-500">Paga con tarjeta, efectivo o saldo de Mercado Pago</p>
                                        </div>
                                        <div className={`h-5 w-5 rounded-full border-2 flex items-center justify-center ${data.payment_method === 'mercadopago' ? 'border-sky-500' : 'border-slate-300'}`}>
                                            {data.payment_method === 'mercadopago' && <div className="h-3 w-3 rounded-full bg-sky-500" />}
                                        </div>
                                    </button>
                                )}

                                <Button
                                    type="submit"
                                    disabled={processing || !data.payment_method}
                                    className="w-full h-14 text-lg font-bold mt-6 shadow-lg shadow-primary/20"
                                >
                                    {processing
                                        ? 'Procesando...'
                                        : !data.payment_method
                                            ? 'Selecciona un método de pago'
                                            : data.payment_method === 'message'
                                                ? 'Enviar Solicitud'
                                                : `Pagar $${servicePrice.toLocaleString()}`
                                    }
                                </Button>
                            </form>
                        </div>
                    )}
                </div>
                <p className="mt-8 text-center text-slate-400 text-sm italic">
                    Términos y condiciones: Reembolsos disponibles hasta 24 horas antes de la cita.
                </p>
            </div>
        </div>
    );
}