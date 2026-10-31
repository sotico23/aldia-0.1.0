import { Head, Link } from '@inertiajs/react';
import { BookOpen, Clock, PlayCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';

interface Enrollment {
    id: number;
    progress_percentage: number;
    course: {
        id: number;
        title: string;
        slug: string;
        description: string;
        cover_image: string;
        instructor: {
            name: string;
        };
    };
}

export default function MyCourses({
    enrollments,
}: {
    enrollments: Enrollment[];
}) {
    return (
        <AppLayout
            breadcrumbs={[{ title: 'Mis Cursos', href: '/alumno/cursos' }]}
        >
            <Head title="Mis Cursos Inscritos" />

            <div className="mx-auto max-w-7xl p-6">
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-zinc-900 dark:text-white">
                            Mi Aprendizaje
                        </h1>
                        <p className="text-zinc-500 dark:text-zinc-400">
                            Continúa donde lo dejaste y alcanza tus metas.
                        </p>
                    </div>
                </div>

                {enrollments.length === 0 ? (
                    <Card className="p-12 text-center">
                        <CardHeader>
                            <BookOpen className="mx-auto mb-4 h-12 w-12 text-zinc-300" />
                            <CardTitle>Aún no tienes cursos</CardTitle>
                            <CardDescription>
                                Explora nuestro catálogo y comienza a aprender
                                hoy mismo.
                            </CardDescription>
                        </CardHeader>
                        <CardFooter className="justify-center">
                            <Button asChild>
                                <Link href="/cursos">
                                    Ver Catálogo de Cursos
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {enrollments.map((enrollment) => (
                            <Card
                                key={enrollment.id}
                                className="group flex h-full flex-col overflow-hidden border-zinc-200 transition-all duration-300 hover:shadow-lg dark:border-zinc-800"
                            >
                                <div className="relative aspect-video overflow-hidden bg-zinc-100 dark:bg-zinc-900">
                                    {enrollment.course.cover_image ? (
                                        <img
                                            src={`/storage/${enrollment.course.cover_image}`}
                                            alt={enrollment.course.title}
                                            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                        />
                                    ) : (
                                        <div className="flex h-full items-center justify-center">
                                            <BookOpen className="h-12 w-12 text-zinc-300" />
                                        </div>
                                    )}
                                    <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                                        <PlayCircle className="h-12 w-12 text-white" />
                                    </div>
                                </div>

                                <CardHeader className="pb-2">
                                    <CardTitle className="line-clamp-1">
                                        {enrollment.course.title}
                                    </CardTitle>
                                    <CardDescription>
                                        Por {enrollment.course.instructor.name}
                                    </CardDescription>
                                </CardHeader>

                                <CardContent className="flex-grow pb-4">
                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <div className="flex justify-between text-xs font-medium">
                                                <span className="text-zinc-600 dark:text-zinc-400">
                                                    Progreso
                                                </span>
                                                <span className="text-primary">
                                                    {
                                                        enrollment.progress_percentage
                                                    }
                                                    %
                                                </span>
                                            </div>
                                            <Progress
                                                value={
                                                    enrollment.progress_percentage
                                                }
                                                className="h-2"
                                            />
                                        </div>

                                        <div className="flex items-center gap-4 text-xs text-zinc-500">
                                            <div className="flex items-center gap-1">
                                                <Clock className="h-3 w-3" />
                                                <span>
                                                    Actualizado recientemente
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>

                                <CardFooter className="pt-0">
                                    <Button asChild className="w-full">
                                        <Link
                                            href={`/cursos/${enrollment.course.slug}`}
                                        >
                                            {enrollment.progress_percentage > 0
                                                ? 'Continuar aprendiendo'
                                                : 'Empezar curso'}
                                        </Link>
                                    </Button>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
