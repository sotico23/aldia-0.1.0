import { Head, Link } from '@inertiajs/react';
import { BookOpen, Clock, PlayCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
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

export default function MyCourses({ enrollments }: { enrollments: Enrollment[] }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Mis Cursos', href: '/alumno/cursos' }]}>
            <Head title="Mis Cursos Inscritos" />

            <div className="p-6 max-w-7xl mx-auto">
                <div className="flex justify-between items-center mb-8">
                    <div>
                        <h1 className="text-3xl font-bold text-zinc-900 dark:text-white">Mi Aprendizaje</h1>
                        <p className="text-zinc-500 dark:text-zinc-400">Continúa donde lo dejaste y alcanza tus metas.</p>
                    </div>
                </div>

                {enrollments.length === 0 ? (
                    <Card className="text-center p-12">
                        <CardHeader>
                            <BookOpen className="h-12 w-12 mx-auto text-zinc-300 mb-4" />
                            <CardTitle>Aún no tienes cursos</CardTitle>
                            <CardDescription>
                                Explora nuestro catálogo y comienza a aprender hoy mismo.
                            </CardDescription>
                        </CardHeader>
                        <CardFooter className="justify-center">
                            <Button asChild>
                                <Link href="/cursos">Ver Catálogo de Cursos</Link>
                            </Button>
                        </CardFooter>
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {enrollments.map((enrollment) => (
                            <Card key={enrollment.id} className="overflow-hidden flex flex-col h-full group hover:shadow-lg transition-all duration-300 border-zinc-200 dark:border-zinc-800">
                                <div className="aspect-video relative overflow-hidden bg-zinc-100 dark:bg-zinc-900">
                                    {enrollment.course.cover_image ? (
                                        <img 
                                            src={`/storage/${enrollment.course.cover_image}`} 
                                            alt={enrollment.course.title} 
                                            className="object-cover w-full h-full group-hover:scale-105 transition-transform duration-500"
                                        />
                                    ) : (
                                        <div className="flex items-center justify-center h-full">
                                            <BookOpen className="h-12 w-12 text-zinc-300" />
                                        </div>
                                    )}
                                    <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <PlayCircle className="text-white h-12 w-12" />
                                    </div>
                                </div>
                                
                                <CardHeader className="pb-2">
                                    <CardTitle className="line-clamp-1">{enrollment.course.title}</CardTitle>
                                    <CardDescription>Por {enrollment.course.instructor.name}</CardDescription>
                                </CardHeader>

                                <CardContent className="flex-grow pb-4">
                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <div className="flex justify-between text-xs font-medium">
                                                <span className="text-zinc-600 dark:text-zinc-400">Progreso</span>
                                                <span className="text-primary">{enrollment.progress_percentage}%</span>
                                            </div>
                                            <Progress value={enrollment.progress_percentage} className="h-2" />
                                        </div>
                                        
                                        <div className="flex items-center gap-4 text-xs text-zinc-500">
                                            <div className="flex items-center gap-1">
                                                <Clock className="h-3 w-3" />
                                                <span>Actualizado recientemente</span>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>

                                <CardFooter className="pt-0">
                                    <Button asChild className="w-full">
                                        <Link href={`/cursos/${enrollment.course.slug}`}>
                                            {enrollment.progress_percentage > 0 ? 'Continuar aprendiendo' : 'Empezar curso'}
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
