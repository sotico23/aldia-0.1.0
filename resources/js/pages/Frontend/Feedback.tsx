import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Send, MessageSquare, Star } from 'lucide-react';
import React, { useState } from 'react';
import { PublicHeader } from '@/components/public-header';

interface Props {
    title: string;
    subtitle?: string;
    content: string;
    image?: string;
    nav_extra?: Record<string, string>;
    web_settings: {
        app_name: string;
        app_logo: string | null;
    };
    nav?: any;
    auth: {
        user: any;
    };
}

export default function Feedback({
    title,
    subtitle,
    image,
    nav_extra,
    web_settings,
    nav,
}: Props) {
    const { auth } = usePage<any>().props;
    const appName = web_settings?.app_name || 'GrowERP';
    const appLogo = web_settings?.app_logo || null;

    const [isSubmitted, setIsSubmitted] = useState(false);
    const { data, setData, processing, reset } = useForm({
        mensaje: '',
        calificacion: 5,
    });

    const submitFeedback = (e: React.FormEvent) => {
        e.preventDefault();
        // Here you would normally post to a feedback endpoint.
        // For demonstration, we simply show a success message.
        setIsSubmitted(true);
        reset();
    };

    return (
        <div className="flex min-h-screen flex-col bg-[#f8f9fc] font-sans">
            <Head title={title} />

            {/* Header */}
            <PublicHeader
                appName={appName}
                appLogo={appLogo}
                nav={nav}
                activePage="feedback"
                tone="emerald"
                showLogin={false}
            />

            <main className="relative flex flex-grow flex-col lg:flex-row">
                {/* Visual Section */}
                <div className="relative flex min-h-72 min-w-0 items-center justify-center overflow-hidden border-emerald-800 bg-emerald-900 p-6 sm:min-h-[400px] sm:p-10 lg:w-1/2 lg:border-r lg:p-12">
                    <div className="absolute inset-0 z-10 bg-gradient-to-br from-emerald-900/90 via-purple-900/80 to-emerald-900/90 mix-blend-multiply" />

                    <img
                        src={image || '/images/feedback_hero.png'}
                        alt="Feedback y Comunidad"
                        className="absolute inset-0 h-full w-full object-cover opacity-80"
                    />

                    <div className="relative z-20 max-w-lg min-w-0 break-words text-white">
                        <div className="mb-8 inline-flex items-center justify-center rounded-2xl border border-white/20 bg-white/10 p-3 backdrop-blur-md">
                            <MessageSquare className="h-8 w-8 text-emerald-300" />
                        </div>
                        <h1 className="mb-4 text-3xl leading-tight font-extrabold tracking-tight sm:mb-6 sm:text-5xl">
                            {title || (
                                <>
                                    <span className="bg-gradient-to-r from-emerald-400 to-purple-300 bg-clip-text text-transparent">
                                        moldea el futuro
                                    </span>
                                </>
                            )}
                        </h1>
                        <div className="text-base leading-relaxed font-light whitespace-pre-wrap text-emerald-100/90 sm:text-lg">
                            {subtitle ||
                                'Te escuchamos. Cuéntanos qué podemos mejorar, qué te encanta y qué esperas ver en el futuro.'}
                        </div>
                    </div>
                </div>

                {/* Form Section */}
                <div className="flex min-w-0 flex-col justify-center bg-white px-4 py-10 sm:px-8 sm:py-16 lg:w-1/2 lg:px-12 xl:px-24">
                    <div className="mx-auto w-full max-w-md">
                        <div className="mb-10 text-center md:text-left">
                            <h2 className="mb-2 text-2xl font-bold text-gray-900">
                                {nav_extra?.feedback_form_title ||
                                    'Comentarios y Sugerencias'}
                            </h2>
                            <p className="text-gray-500">
                                {auth.user ? (
                                    <>
                                        Hola{' '}
                                        <span className="font-semibold text-emerald-600">
                                            {auth.user.name}
                                        </span>
                                        , ¡gracias por tomarte el tiempo!
                                    </>
                                ) : (
                                    'Inicia sesión o regístrate para ayudarnos a construir una mejor plataforma.'
                                )}
                            </p>
                        </div>

                        {auth.user ? (
                            isSubmitted ? (
                                <div className="mt-8 rounded-2xl border border-emerald-100 bg-emerald-50 p-8 text-center">
                                    <div className="mb-6 inline-flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                        <Star className="h-8 w-8 fill-current" />
                                    </div>
                                    <h3 className="mb-2 text-xl font-bold text-gray-900">
                                        ¡Gracias por tu mensaje!
                                    </h3>
                                    <p className="mb-6 text-emerald-600/80">
                                        Hemos recibido tus comentarios y los
                                        revisaremos con atención.
                                    </p>
                                    <button
                                        onClick={() => setIsSubmitted(false)}
                                        className="text-sm font-semibold text-emerald-600 underline underline-offset-4 hover:text-emerald-700"
                                    >
                                        Enviar otro comentario
                                    </button>
                                </div>
                            ) : (
                                <form
                                    onSubmit={submitFeedback}
                                    className="space-y-6"
                                >
                                    <div className="space-y-3">
                                        <label className="block text-sm font-semibold text-gray-700">
                                            ¿Cómo calificarías tu experiencia?
                                        </label>
                                        <div className="flex items-center gap-2">
                                            {[1, 2, 3, 4, 5].map((star) => (
                                                <button
                                                    type="button"
                                                    key={star}
                                                    onClick={() =>
                                                        setData(
                                                            'calificacion',
                                                            star,
                                                        )
                                                    }
                                                    className={`rounded-lg p-2 transition-transform hover:scale-110 ${data.calificacion >= star ? 'text-amber-400' : 'text-gray-200'}`}
                                                >
                                                    <Star className="h-8 w-8 fill-current" />
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <label
                                            htmlFor="mensaje"
                                            className="block text-sm font-semibold text-gray-700"
                                        >
                                            Tu mensaje o sugerencia
                                        </label>
                                        <textarea
                                            id="mensaje"
                                            value={data.mensaje}
                                            onChange={(e) =>
                                                setData(
                                                    'mensaje',
                                                    e.target.value,
                                                )
                                            }
                                            rows={5}
                                            required
                                            className="w-full resize-none rounded-xl border-gray-200 bg-gray-50 p-4 text-gray-900 transition-colors focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20"
                                            placeholder="Detalla lo que te gustaría compartir con nosotros..."
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={
                                            processing || !data.mensaje.trim()
                                        }
                                        className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-6 py-4 text-sm font-bold text-white shadow-lg shadow-emerald-600/20 transition-all hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Enviar feedback{' '}
                                        <Send className="ml-1 h-4 w-4" />
                                    </button>
                                </form>
                            )
                        ) : (
                            <div className="mt-8 rounded-2xl border border-gray-100 bg-gray-50 p-8 text-center">
                                <div className="mb-6 inline-flex h-16 w-16 items-center justify-center rounded-full bg-white text-gray-400 shadow-sm">
                                    <svg
                                        className="h-8 w-8"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={1.5}
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8V7a4 4 0 00-8 0v4h8z"
                                        />
                                    </svg>
                                </div>
                                <h3 className="mb-4 text-xl font-bold text-gray-900">
                                    Solo usuarios registrados
                                </h3>
                                <p className="mb-8 text-gray-500">
                                    Debes iniciar sesión para asegurarnos de
                                    brindarte un seguimiento personalizado sobre
                                    tus sugerencias.
                                </p>

                                <div className="flex flex-col gap-3">
                                    <Link
                                        href="/login"
                                        className="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-emerald-700"
                                    >
                                        Iniciar sesión
                                    </Link>
                                    <Link
                                        href="/register"
                                        className="inline-flex w-full items-center justify-center rounded-xl border border-gray-200 bg-white px-6 py-3 text-sm font-bold text-gray-700 transition hover:bg-gray-50"
                                    >
                                        Crear una cuenta
                                    </Link>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </main>
        </div>
    );
}
