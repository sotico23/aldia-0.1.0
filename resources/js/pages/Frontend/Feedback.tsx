import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Send, MessageSquare, Star } from 'lucide-react';
import React, { useState } from 'react';

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

// eslint-disable-next-line @typescript-eslint/no-unused-vars
export default function Feedback({ title, subtitle, content, image, nav_extra, web_settings, nav }: Props) {
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
        <div className="min-h-screen bg-[#f8f9fc] flex flex-col font-sans">
            <Head title={title} />

            {/* Header */}
            <header className="sticky top-0 z-50 border-b border-gray-100 bg-white/70 backdrop-blur-xl">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
                    {(nav?.app_brand_visible ?? true) ? (
                        <Link href="/" className="flex items-center gap-2">
                            {appLogo ? (
                                <img src={appLogo} alt={appName} className="h-10 w-10 rounded-lg object-contain" />
                            ) : (
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-2xl font-black text-white">
                                    {appName.charAt(0)}
                                </div>
                            )}
                            <span className="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-purple-600">
                                {appName}
                            </span>
                        </Link>
                    ) : (
                        <Link href="/" className="flex items-center">
                            <span className="text-xl font-bold text-transparent sr-only">{appName}</span>
                        </Link>
                    )}
                    <nav className="flex items-center gap-6">
                        <div className="hidden md:flex items-center gap-6 mr-4">
                            {(nav?.quienes_somos_visible ?? true) && (
                                <Link href="/quienes-somos" className="text-sm font-medium text-gray-500 hover:text-indigo-600 transition-colors">
                                    {nav?.quienes_somos || 'Quiénes Somos'}
                                </Link>
                            )}
                            <Link href="/feedback" className="text-sm font-semibold text-indigo-600 transition-colors">
                                {nav?.feedback || 'Feedback'}
                            </Link>
                            {(nav?.fundacion_visible ?? true) && (
                                <Link href="/fundacion" className="text-sm font-medium text-gray-500 hover:text-indigo-600 transition-colors">
                                    {nav?.fundacion || 'Nuestra Fundación'}
                                </Link>
                            )}
                        </div>
                    </nav>
                </div>
            </header>

            <main className="flex-grow flex flex-col md:flex-row relative">
                {/* Visual Section */}
                <div className="md:w-1/2 relative bg-indigo-900 border-r border-indigo-800 overflow-hidden min-h-[400px] md:min-h-auto flex items-center justify-center p-12">
                    <div className="absolute inset-0 bg-gradient-to-br from-indigo-900/90 via-purple-900/80 to-blue-900/90 mix-blend-multiply z-10" />
                    
                    <img 
                        src={image || "/images/feedback_hero.png"}
                        alt="Feedback y Comunidad"
                        className="absolute inset-0 w-full h-full object-cover opacity-80"
                    />

                    <div className="relative z-20 text-white max-w-lg">
                        <div className="inline-flex items-center justify-center p-3 bg-white/10 rounded-2xl backdrop-blur-md mb-8 border border-white/20">
                            <MessageSquare className="h-8 w-8 text-indigo-300" />
                        </div>
                        <h1 className="text-5xl font-extrabold tracking-tight mb-6 leading-tight">
                            {title || <><span className="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-300">moldea el futuro</span></>}
                        </h1>
                        <div className="text-lg text-indigo-100/90 leading-relaxed font-light whitespace-pre-wrap">
                            {subtitle || 'Te escuchamos. Cuéntanos qué podemos mejorar, qué te encanta y qué esperas ver en el futuro.'}
                        </div>
                    </div>
                </div>

                {/* Form Section */}
                <div className="md:w-1/2 bg-white flex flex-col justify-center px-8 md:px-16 lg:px-24 py-16">
                    <div className="max-w-md w-full mx-auto">
                        
                        <div className="mb-10 text-center md:text-left">
                            <h2 className="text-2xl font-bold text-gray-900 mb-2">
                                {nav_extra?.feedback_form_title || 'Comentarios y Sugerencias'}
                            </h2>
                            <p className="text-gray-500">
                                {auth.user ? (
                                    <>Hola <span className="font-semibold text-indigo-600">{auth.user.name}</span>, ¡gracias por tomarte el tiempo!</>
                                ) : (
                                    'Inicia sesión o regístrate para ayudarnos a construir una mejor plataforma.'
                                )}
                            </p>
                        </div>

                        {auth.user ? (
                            isSubmitted ? (
                                <div className="bg-indigo-50 border border-indigo-100 rounded-2xl p-8 text-center mt-8">
                                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-100 text-indigo-600 mb-6">
                                        <Star className="w-8 h-8 fill-current" />
                                    </div>
                                    <h3 className="text-xl font-bold text-gray-900 mb-2">¡Gracias por tu mensaje!</h3>
                                    <p className="text-indigo-600/80 mb-6">Hemos recibido tus comentarios y los revisaremos con atención.</p>
                                    <button 
                                        onClick={() => setIsSubmitted(false)}
                                        className="text-sm font-semibold text-indigo-600 hover:text-indigo-700 underline underline-offset-4"
                                    >
                                        Enviar otro comentario
                                    </button>
                                </div>
                            ) : (
                                <form onSubmit={submitFeedback} className="space-y-6">
                                    <div className="space-y-3">
                                        <label className="block text-sm font-semibold text-gray-700">
                                            ¿Cómo calificarías tu experiencia?
                                        </label>
                                        <div className="flex items-center gap-2">
                                            {[1, 2, 3, 4, 5].map((star) => (
                                                <button
                                                    type="button"
                                                    key={star}
                                                    onClick={() => setData('calificacion', star)}
                                                    className={`p-2 rounded-lg transition-transform hover:scale-110 ${data.calificacion >= star ? 'text-amber-400' : 'text-gray-200'}`}
                                                >
                                                    <Star className="w-8 h-8 fill-current" />
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                    
                                    <div className="space-y-2">
                                        <label htmlFor="mensaje" className="block text-sm font-semibold text-gray-700">
                                            Tu mensaje o sugerencia
                                        </label>
                                        <textarea
                                            id="mensaje"
                                            value={data.mensaje}
                                            onChange={e => setData('mensaje', e.target.value)}
                                            rows={5}
                                            required
                                            className="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors resize-none p-4 text-gray-900"
                                            placeholder="Detalla lo que te gustaría compartir con nosotros..."
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={processing || !data.mensaje.trim()}
                                        className="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-4 text-sm font-bold text-white transition-all hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-indigo-600/20"
                                    >
                                        Enviar feedback <Send className="w-4 h-4 ml-1" />
                                    </button>
                                </form>
                            )
                        ) : (
                            <div className="bg-gray-50 border border-gray-100 rounded-2xl p-8 text-center mt-8">
                                <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white shadow-sm text-gray-400 mb-6">
                                    <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <h3 className="text-xl font-bold text-gray-900 mb-4">Solo usuarios registrados</h3>
                                <p className="text-gray-500 mb-8">Debes iniciar sesión para asegurarnos de brindarte un seguimiento personalizado sobre tus sugerencias.</p>
                                
                                <div className="flex flex-col gap-3">
                                    <Link 
                                        href="/login" 
                                        className="w-full inline-flex items-center justify-center rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white hover:bg-indigo-700 transition"
                                    >
                                        Iniciar sesión
                                    </Link>
                                    <Link 
                                        href="/register" 
                                        className="w-full inline-flex items-center justify-center rounded-xl bg-white border border-gray-200 px-6 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 transition"
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
