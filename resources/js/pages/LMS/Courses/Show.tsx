import { Head, Link, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    PlayCircle,
    Star,
    Users,
    Clock,
    Globe,
    Award,
    Sparkles,
    ChevronRight,
    BookOpen,
} from 'lucide-react';
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

export default function Show({
    course,
    isEnrolled,
}: {
    course: Course;
    isEnrolled: boolean;
}) {
    const { post, processing } = useForm();

    const handleEnroll = () => {
        post(`/cursos/${course.id}/enroll`);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Catálogo', href: '/cursos' },
                { title: course.title, href: '#' },
            ]}
        >
            <Head title={`${course.title} | LMS`} />

            {/* Hero Section */}
            <div className="relative overflow-hidden border-b border-slate-800 bg-slate-900 py-12 lg:py-20">
                <div className="bg-grid-white/[0.02] absolute inset-0 bg-[size:32px_32px]" />
                <div className="absolute -top-24 -left-20 h-96 w-96 rounded-full bg-emerald-600/20 blur-[120px]" />
                <div className="absolute -right-20 -bottom-24 h-96 w-96 rounded-full bg-emerald-600/20 blur-[120px]" />

                <div className="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
                        <div className="space-y-6">
                            <div className="inline-flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-xs font-bold tracking-wider text-emerald-400 uppercase">
                                <Sparkles className="h-3 w-3" />
                                Curso Destacado
                            </div>
                            <h1 className="text-4xl leading-tight font-extrabold text-white lg:text-5xl">
                                {course.title}
                            </h1>
                            <p className="max-w-xl text-xl leading-relaxed text-slate-400">
                                {course.description}
                            </p>

                            <div className="flex flex-wrap items-center gap-6 text-slate-300">
                                <div className="flex items-center gap-2">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full border border-slate-700 bg-slate-800 font-bold text-emerald-400">
                                        {course.instructor.name[0]}
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-bold tracking-tighter text-slate-500 uppercase">
                                            Instructor
                                        </p>
                                        <p className="text-sm font-medium">
                                            {course.instructor.name}
                                        </p>
                                    </div>
                                </div>
                                <div className="hidden h-8 w-px bg-slate-800 sm:block" />
                                <div className="flex items-center gap-4 text-sm">
                                    <span className="flex items-center gap-1">
                                        <Star className="h-4 w-4 fill-yellow-500 text-yellow-500" />{' '}
                                        4.9 (2k+)
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <Users className="h-4 w-4 text-slate-500" />{' '}
                                        15k alumnos
                                    </span>
                                </div>
                            </div>

                            <div className="flex flex-col gap-4 pt-4 sm:flex-row">
                                {isEnrolled ? (
                                    <Button
                                        size="lg"
                                        className="h-14 px-8 text-lg font-bold shadow-xl shadow-emerald-500/20"
                                        asChild
                                    >
                                        <Link href={`/mis-cursos`}>
                                            Ir al Curso
                                            <ChevronRight className="ml-2 h-5 w-5" />
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        size="lg"
                                        className="h-14 px-8 text-lg font-bold shadow-xl shadow-emerald-500/20"
                                        onClick={handleEnroll}
                                        disabled={processing}
                                    >
                                        {course.price === '0.00'
                                            ? 'Inscribirse Gratis'
                                            : `Comprar por $${course.price}`}
                                        <ChevronRight className="ml-2 h-5 w-5" />
                                    </Button>
                                )}
                                <Button
                                    variant="outline"
                                    size="lg"
                                    className="h-14 border-slate-700 bg-transparent text-white transition-colors hover:bg-slate-800"
                                >
                                    Ver Temario
                                </Button>
                            </div>
                        </div>

                        <div className="relative hidden lg:block">
                            <div className="group relative aspect-video overflow-hidden rounded-3xl border border-slate-700 bg-slate-900 shadow-2xl">
                                {course.promo_video_url ? (
                                    <iframe
                                        src={
                                            course.promo_video_url
                                                .replace('watch?v=', 'embed/')
                                                .split('&')[0]
                                        }
                                        className="h-full w-full"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowFullScreen
                                    ></iframe>
                                ) : course.cover_image ? (
                                    <img
                                        src={`/storage/${course.cover_image}`}
                                        alt={course.title}
                                        className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                                    />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center bg-slate-800">
                                        <PlayCircle className="h-20 w-20 text-slate-700" />
                                    </div>
                                )}
                                {!course.promo_video_url && (
                                    <div className="absolute inset-0 flex items-center justify-center bg-slate-900/40 opacity-0 transition-opacity group-hover:opacity-100">
                                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-white shadow-xl">
                                            <PlayCircle className="h-8 w-8 text-emerald-600" />
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Floating Stats */}
                            <div className="absolute -bottom-6 -left-6 flex items-center gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                                <Award className="h-8 w-8 text-yellow-500" />
                                <div>
                                    <p className="text-xs font-bold text-slate-900 uppercase dark:text-white">
                                        Certificado
                                    </p>
                                    <p className="text-[10px] text-slate-500">
                                        Al completar el curso
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Content Section */}
            <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 gap-12 lg:grid-cols-3">
                    <div className="space-y-12 lg:col-span-2">
                        {/* What you'll learn */}
                        <section className="rounded-3xl border border-slate-100 bg-white p-8 dark:border-slate-800 dark:bg-slate-900/50">
                            <h2 className="mb-6 flex items-center gap-2 text-2xl font-bold">
                                <CheckCircle2 className="h-6 w-6 text-green-500" />
                                Lo que aprenderás
                            </h2>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                {Array.isArray(course.features) &&
                                course.features.some(
                                    (f) => f && String(f).trim() !== '',
                                ) ? (
                                    course.features
                                        .filter(
                                            (f) => f && String(f).trim() !== '',
                                        )
                                        .map((feature, i) => (
                                            <div
                                                key={i}
                                                className="flex items-start gap-3"
                                            >
                                                <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-green-500" />
                                                <span className="line-clamp-2 text-sm text-slate-600 italic dark:text-slate-400">
                                                    {String(feature)}
                                                </span>
                                            </div>
                                        ))
                                ) : (
                                    <div className="col-span-full">
                                        <p className="text-sm text-slate-500 italic">
                                            El instructor aún no ha definido los
                                            puntos clave de este curso.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </section>

                        {/* Curriculum */}
                        <section>
                            <h2 className="mb-8 flex items-center gap-3 text-3xl font-bold">
                                <BookOpen className="h-8 w-8 text-emerald-500" />
                                Temario del Curso
                            </h2>
                            <div className="space-y-4">
                                {course.modules.length > 0 ? (
                                    course.modules.map((module, mIdx) => (
                                        <div
                                            key={module.id}
                                            className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900/50"
                                        >
                                            <div className="flex items-center justify-between bg-slate-50 p-5 dark:bg-slate-800/50">
                                                <div className="flex items-center gap-4">
                                                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-sm font-bold text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                        {mIdx + 1}
                                                    </span>
                                                    <div>
                                                        <h3 className="font-bold text-slate-900 dark:text-white">
                                                            {module.title}
                                                        </h3>
                                                        <p className="line-clamp-1 text-xs text-slate-500">
                                                            {module.description ||
                                                                'Sin descripción'}
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="text-xs font-medium text-slate-500">
                                                    {module.lessons.length}{' '}
                                                    lecciones
                                                </span>
                                            </div>
                                            <div className="divide-y divide-slate-100 dark:divide-slate-800">
                                                {module.lessons.map(
                                                    (lesson) => (
                                                        <div
                                                            key={lesson.id}
                                                            className="group flex items-center justify-between p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800"
                                                        >
                                                            <div className="flex items-center gap-3 pl-12">
                                                                <PlayCircle className="h-4 w-4 text-slate-300 transition-colors group-hover:text-emerald-500" />
                                                                <span className="text-sm font-medium text-slate-600 transition-colors group-hover:text-slate-900 dark:text-slate-400 dark:group-hover:text-white">
                                                                    {
                                                                        lesson.title
                                                                    }
                                                                </span>
                                                            </div>
                                                            <Clock className="h-4 w-4 text-slate-300" />
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="rounded-3xl border-2 border-dashed border-slate-200 py-12 text-center dark:border-slate-800">
                                        <p className="text-slate-500">
                                            El instructor aún no ha publicado el
                                            temario detallado.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>

                    <div className="space-y-8">
                        {/* Course Stats Card */}
                        <div className="sticky top-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h4 className="mb-6 text-center text-xs font-bold tracking-widest text-slate-900 uppercase dark:text-white">
                                Este curso incluye
                            </h4>
                            <div className="space-y-5">
                                <div className="flex items-center gap-4 text-sm text-slate-600 dark:text-slate-400">
                                    <Clock className="h-5 w-5 text-emerald-500" />
                                    <span>24 horas de video bajo demanda</span>
                                </div>
                                <div className="flex items-center gap-4 text-sm text-slate-600 dark:text-slate-400">
                                    <Globe className="h-5 w-5 text-emerald-500" />
                                    <span>
                                        Acceso en dispositivos móviles y TV
                                    </span>
                                </div>
                                <div className="flex items-center gap-4 text-sm text-slate-600 dark:text-slate-400">
                                    <Award className="h-5 w-5 text-emerald-500" />
                                    <span>Certificado de finalización</span>
                                </div>
                                <div className="flex items-center gap-4 text-sm text-slate-600 dark:text-slate-400">
                                    <PlayCircle className="h-5 w-5 text-emerald-500" />
                                    <span>Acceso de por vida</span>
                                </div>
                            </div>
                            <div className="mt-8 border-t border-slate-100 pt-8 dark:border-slate-800">
                                <div className="mb-6 text-center">
                                    <p className="mb-1 text-[10px] font-bold tracking-widest text-slate-500 uppercase">
                                        Inversión única
                                    </p>
                                    <p className="text-4xl font-extrabold text-slate-900 dark:text-white">
                                        ${course.price}
                                    </p>
                                </div>
                                {!isEnrolled && (
                                    <Button
                                        className="h-12 w-full font-bold"
                                        onClick={handleEnroll}
                                        disabled={processing}
                                    >
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
