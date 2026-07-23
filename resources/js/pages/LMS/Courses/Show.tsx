import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle2, PlayCircle, Star, Users, Clock, Globe, Award, Sparkles, ChevronRight, BookOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';

interface Lesson {
    id: number;
    title: string;
}

interface Module {
    id: number;
    title: string;
    description: string;
    lessons: Lesson[];
}

interface Course {
    id: number;
    title: string;
    description: string;
    price: string;
    cover_image: string | null;
    promo_video_url: string | null;
    instructor: {
        name: string;
    };
    features: string[] | null;
    modules: Module[];
}

export default function Show({ course, isEnrolled }: { course: Course, isEnrolled: boolean }) {
    const { post, processing } = useForm();

    const handleEnroll = () => {
        post(`/cursos/${course.id}/enroll`);
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'Catálogo', href: '/cursos' },
            { title: course.title, href: '#' }
        ]}>
            <Head title={`${course.title} | LMS`} />

            {/* Hero Section */}
            <div className="bg-slate-900 border-b border-slate-800 py-12 lg:py-20 relative overflow-hidden">
                <div className="absolute inset-0 bg-grid-white/[0.02] bg-[size:32px_32px]" />
                <div className="absolute -top-24 -left-20 w-96 h-96 bg-blue-600/20 rounded-full blur-[120px]" />
                <div className="absolute -bottom-24 -right-20 w-96 h-96 bg-indigo-600/20 rounded-full blur-[120px]" />
                
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                        <div className="space-y-6">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-bold uppercase tracking-wider">
                                <Sparkles className="h-3 w-3" />
                                Curso Destacado
                            </div>
                            <h1 className="text-4xl lg:text-5xl font-extrabold text-white leading-tight">
                                {course.title}
                            </h1>
                            <p className="text-xl text-slate-400 leading-relaxed max-w-xl">
                                {course.description}
                            </p>
                            
                            <div className="flex flex-wrap items-center gap-6 text-slate-300">
                                <div className="flex items-center gap-2">
                                    <div className="h-10 w-10 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-blue-400">
                                        {course.instructor.name[0]}
                                    </div>
                                    <div>
                                        <p className="text-[10px] text-slate-500 uppercase font-bold tracking-tighter">Instructor</p>
                                        <p className="text-sm font-medium">{course.instructor.name}</p>
                                    </div>
                                </div>
                                <div className="h-8 w-px bg-slate-800 hidden sm:block" />
                                <div className="flex items-center gap-4 text-sm">
                                    <span className="flex items-center gap-1"><Star className="h-4 w-4 text-yellow-500 fill-yellow-500" /> 4.9 (2k+)</span>
                                    <span className="flex items-center gap-1"><Users className="h-4 w-4 text-slate-500" /> 15k alumnos</span>
                                </div>
                            </div>

                            <div className="pt-4 flex flex-col sm:flex-row gap-4">
                                {isEnrolled ? (
                                    <Button size="lg" className="h-14 px-8 text-lg font-bold shadow-xl shadow-blue-500/20" asChild>
                                        <Link href={`/mis-cursos`}>
                                            Ir al Curso
                                            <ChevronRight className="ml-2 h-5 w-5" />
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button 
                                        size="lg" 
                                        className="h-14 px-8 text-lg font-bold shadow-xl shadow-blue-500/20"
                                        onClick={handleEnroll}
                                        disabled={processing}
                                    >
                                        {course.price === '0.00' ? 'Inscribirse Gratis' : `Comprar por $${course.price}`}
                                        <ChevronRight className="ml-2 h-5 w-5" />
                                    </Button>
                                )}
                                <Button variant="outline" size="lg" className="h-14 bg-transparent text-white border-slate-700 hover:bg-slate-800 transition-colors">
                                    Ver Temario
                                </Button>
                            </div>
                        </div>

                        <div className="hidden lg:block relative">
                            <div className="aspect-video rounded-3xl overflow-hidden border border-slate-700 shadow-2xl relative group bg-slate-900">
                                {course.promo_video_url ? (
                                    <iframe 
                                        src={course.promo_video_url.replace('watch?v=', 'embed/').split('&')[0]} 
                                        className="w-full h-full"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowFullScreen
                                    ></iframe>
                                ) : course.cover_image ? (
                                    <img src={`/storage/${course.cover_image}`} alt={course.title} className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" />
                                ) : (
                                    <div className="w-full h-full bg-slate-800 flex items-center justify-center">
                                        <PlayCircle className="h-20 w-20 text-slate-700" />
                                    </div>
                                )}
                                {!course.promo_video_url && (
                                    <div className="absolute inset-0 bg-slate-900/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                        <div className="h-16 w-16 bg-white rounded-full flex items-center justify-center shadow-xl">
                                            <PlayCircle className="h-8 w-8 text-blue-600" />
                                        </div>
                                    </div>
                                )}
                            </div>
                            
                            {/* Floating Stats */}
                            <div className="absolute -bottom-6 -left-6 bg-white dark:bg-slate-900 p-4 rounded-2xl shadow-xl border border-slate-100 dark:border-slate-800 flex items-center gap-3">
                                <Award className="h-8 w-8 text-yellow-500" />
                                <div>
                                    <p className="text-xs font-bold text-slate-900 dark:text-white uppercase">Certificado</p>
                                    <p className="text-[10px] text-slate-500">Al completar el curso</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Content Section */}
            <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-12">
                    <div className="lg:col-span-2 space-y-12">
                        {/* What you'll learn */}
                        <section className="bg-white dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800 rounded-3xl p-8">
                            <h2 className="text-2xl font-bold mb-6 flex items-center gap-2">
                                <CheckCircle2 className="h-6 w-6 text-green-500" />
                                Lo que aprenderás
                            </h2>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {Array.isArray(course.features) && course.features.some(f => f && String(f).trim() !== '') ? (
                                    course.features.filter(f => f && String(f).trim() !== '').map((feature, i) => (
                                        <div key={i} className="flex items-start gap-3">
                                            <CheckCircle2 className="h-5 w-5 text-green-500 shrink-0 mt-0.5" />
                                            <span className="text-slate-600 dark:text-slate-400 text-sm italic line-clamp-2">{String(feature)}</span>
                                        </div>
                                    ))
                                ) : (
                                    <div className="col-span-full">
                                        <p className="text-slate-500 text-sm italic">El instructor aún no ha definido los puntos clave de este curso.</p>
                                    </div>
                                )}
                            </div>
                        </section>

                        {/* Curriculum */}
                        <section>
                            <h2 className="text-3xl font-bold mb-8 flex items-center gap-3">
                                <BookOpen className="h-8 w-8 text-blue-500" />
                                Temario del Curso
                            </h2>
                            <div className="space-y-4">
                                {course.modules.length > 0 ? (
                                    course.modules.map((module, mIdx) => (
                                        <div key={module.id} className="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/50">
                                            <div className="p-5 flex items-center justify-between bg-slate-50 dark:bg-slate-800/50">
                                                <div className="flex items-center gap-4">
                                                    <span className="h-8 w-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-sm">
                                                        {mIdx + 1}
                                                    </span>
                                                    <div>
                                                        <h3 className="font-bold text-slate-900 dark:text-white">{module.title}</h3>
                                                        <p className="text-xs text-slate-500 line-clamp-1">{module.description || 'Sin descripción'}</p>
                                                    </div>
                                                </div>
                                                <span className="text-xs font-medium text-slate-500">
                                                    {module.lessons.length} lecciones
                                                </span>
                                            </div>
                                            <div className="divide-y divide-slate-100 dark:divide-slate-800">
                                                {module.lessons.map((lesson) => (
                                                    <div key={lesson.id} className="p-4 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors group">
                                                        <div className="flex items-center gap-3 pl-12">
                                                            <PlayCircle className="h-4 w-4 text-slate-300 group-hover:text-blue-500 transition-colors" />
                                                            <span className="text-sm font-medium text-slate-600 dark:text-slate-400 group-hover:text-slate-900 dark:group-hover:text-white transition-colors">
                                                                {lesson.title}
                                                            </span>
                                                        </div>
                                                        <Clock className="h-4 w-4 text-slate-300" />
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="text-center py-12 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-3xl">
                                        <p className="text-slate-500">El instructor aún no ha publicado el temario detallado.</p>
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>

                    <div className="space-y-8">
                        {/* Course Stats Card */}
                        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-sm sticky top-8">
                            <h4 className="font-bold text-slate-900 dark:text-white mb-6 uppercase text-xs tracking-widest text-center">Este curso incluye</h4>
                            <div className="space-y-5">
                                <div className="flex items-center gap-4 text-sm text-slate-600 dark:text-slate-400">
                                    <Clock className="h-5 w-5 text-blue-500" />
                                    <span>24 horas de video bajo demanda</span>
                                </div>
                                <div className="flex items-center gap-4 text-sm text-slate-600 dark:text-slate-400">
                                    <Globe className="h-5 w-5 text-blue-500" />
                                    <span>Acceso en dispositivos móviles y TV</span>
                                </div>
                                <div className="flex items-center gap-4 text-sm text-slate-600 dark:text-slate-400">
                                    < Award className="h-5 w-5 text-blue-500" />
                                    <span>Certificado de finalización</span>
                                </div>
                                <div className="flex items-center gap-4 text-sm text-slate-600 dark:text-slate-400">
                                    <PlayCircle className="h-5 w-5 text-blue-500" />
                                    <span>Acceso de por vida</span>
                                </div>
                            </div>
                            <div className="mt-8 pt-8 border-t border-slate-100 dark:border-slate-800">
                                <div className="text-center mb-6">
                                    <p className="text-[10px] text-slate-500 uppercase font-bold tracking-widest mb-1">Inversión única</p>
                                    <p className="text-4xl font-extrabold text-slate-900 dark:text-white">${course.price}</p>
                                </div>
                                {!isEnrolled && (
                                    <Button className="w-full h-12 font-bold" onClick={handleEnroll} disabled={processing}>
                                        Inscribirme ahora
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
