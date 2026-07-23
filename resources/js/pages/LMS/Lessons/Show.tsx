import { Head, Link, useForm } from '@inertiajs/react';
import {
    PlayCircle,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    FileText,
    HelpCircle,
    BookOpen
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

interface Lesson {
    id: number;
    title: string;
    description: string;
    content_text: string;
    video_url: string | null;
    slug: string;
    order: number;
    module: {
        id: number;
        title: string;
        course: {
            id: number;
            title: string;
            slug: string;
        };
    };
    quiz: {
        id: number;
        title: string;
    } | null;
}

interface CourseModule {
    id: number;
    title: string;
    order: number;
    lessons: {
        id: number;
        title: string;
        slug: string;
        order: number;
    }[];
}

interface Props {
    lesson: Lesson;
    courseModules: CourseModule[];
    nextLesson: { id: number; title: string; slug: string } | null;
    previousLesson: { id: number; title: string; slug: string } | null;
    viewToken: string;
}

export default function LessonShow({
    lesson,
    courseModules,
    nextLesson,
    previousLesson,
    viewToken,
}: Props) {
    const { data, setData, post, processing } = useForm({
        view_token: viewToken,
    });

    const handleComplete = () => {
        post(`/lecciones/${lesson.slug}/complete`);
    };

    const currentModuleIndex = courseModules.findIndex(
        (m) => m.id === lesson.module.id,
    );
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const currentLessonIndex =
        courseModules[currentModuleIndex]?.lessons.findIndex(
            (l) => l.id === lesson.id,
        ) ?? -1;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Mis Cursos', href: '/alumno/cursos' },
                {
                    title: lesson.module.course.title,
                    href: `/cursos/${lesson.module.course.slug}`,
                },
                { title: lesson.title, href: '#' },
            ]}
        >
            <Head title={`${lesson.title} | ${lesson.module.course.title}`} />

            <div className="mx-auto flex max-w-7xl flex-col gap-6 p-6 lg:flex-row">
                {/* Contenido Principal */}
                <div className="flex-1 space-y-6">
                    {/* Video Player */}
                    <div className="relative aspect-video overflow-hidden rounded-2xl bg-slate-900">
                        {lesson.video_url ? (
                            <iframe
                                src={
                                    lesson.video_url
                                        .replace('watch?v=', 'embed/')
                                        .split('&')[0]
                                }
                                className="h-full w-full"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowFullScreen
                            />
                        ) : (
                            <div className="flex h-full w-full items-center justify-center">
                                <div className="text-center">
                                    <PlayCircle className="mx-auto mb-4 h-20 w-20 text-slate-600" />
                                    <p className="text-slate-500">
                                        No hay video disponible para esta
                                        lección
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Lesson Info */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                        <div className="mb-4 flex items-start justify-between">
                            <div>
                                <Badge variant="outline" className="mb-2">
                                    Módulo: {lesson.module.title}
                                </Badge>
                                <h1 className="text-2xl font-bold text-zinc-900 dark:text-white">
                                    {lesson.title}
                                </h1>
                            </div>
                            <Button
                                onClick={handleComplete}
                                disabled={processing}
                            >
                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                Marcar como Completada
                            </Button>
                        </div>

                        {lesson.description && (
                            <p className="mb-6 text-zinc-600 dark:text-zinc-400">
                                {lesson.description}
                            </p>
                        )}

                        {/* Contenido de Texto */}
                        {lesson.content_text && (
                            <div className="prose prose-zinc dark:prose-invert max-w-none">
                                <div className="rounded-xl bg-slate-50 p-6 dark:bg-slate-800/50">
                                    <div className="mb-4 flex items-center gap-2">
                                        <FileText className="h-5 w-5 text-blue-500" />
                                        <h3 className="font-semibold">
                                            Contenido de la Lección
                                        </h3>
                                    </div>
                                    <div className="whitespace-pre-wrap text-zinc-700 dark:text-zinc-300">
                                        {lesson.content_text}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Quiz */}
                        {lesson.quiz && (
                            <div className="mt-6">
                                <Card className="border-amber-200 bg-amber-50 dark:bg-amber-900/20">
                                    <CardHeader className="pb-3">
                                        <CardTitle className="flex items-center gap-2 text-amber-800 dark:text-amber-200">
                                            <HelpCircle className="h-5 w-5" />
                                            Examen Disponibles
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="mb-3 text-amber-700 dark:text-amber-300">
                                            {lesson.quiz.title}
                                        </p>
                                        <Button
                                            variant="outline"
                                            className="bg-white dark:bg-slate-800"
                                        >
                                            Realizar Examen
                                        </Button>
                                    </CardContent>
                                </Card>
                            </div>
                        )}
                    </div>

                    {/* Navigation */}
                    <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        {previousLesson ? (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/lecciones/${previousLesson.slug}`}
                                >
                                    <ChevronLeft className="mr-2 h-4 w-4" />
                                    {previousLesson.title}
                                </Link>
                            </Button>
                        ) : (
                            <div />
                        )}

                        <Button variant="ghost" asChild>
                            <Link href={`/cursos/${lesson.module.course.slug}`}>
                                <BookOpen className="mr-2 h-4 w-4" />
                                Volver al Curso
                            </Link>
                        </Button>

                        {nextLesson ? (
                            <Button asChild>
                                <Link href={`/lecciones/${nextLesson.slug}`}>
                                    {nextLesson.title}
                                    <ChevronRight className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                        ) : (
                            <Button asChild>
                                <Link href={`/alumno/progreso`}>
                                    <CheckCircle2 className="mr-2 h-4 w-4" />
                                    Finalizar Curso
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Sidebar - Curriculum */}
                <div className="w-full shrink-0 lg:w-80">
                    <Card className="sticky top-6">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-lg">
                                Temario del Curso
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="max-h-[600px] overflow-y-auto">
                            <div className="space-y-4">
                                {courseModules.map((module, moduleIndex) => (
                                    <div key={module.id}>
                                        <div className="mb-2 px-2 text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                                            Módulo {moduleIndex + 1}:{' '}
                                            {module.title}
                                        </div>
                                        <div className="space-y-1">
                                            {module.lessons.map((l) => (
                                                <Link
                                                    key={l.id}
                                                    href={`/lecciones/${l.slug}`}
                                                    className={`block rounded-lg px-3 py-2 text-sm transition-colors ${
                                                        l.id === lesson.id
                                                            ? 'bg-blue-100 font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                                            : 'text-zinc-600 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-slate-800'
                                                    }`}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <PlayCircle className="h-3 w-3 shrink-0" />
                                                        <span className="line-clamp-1">
                                                            {l.title}
                                                        </span>
                                                    </div>
                                                </Link>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
