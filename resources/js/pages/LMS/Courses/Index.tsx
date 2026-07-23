import { Head, Link } from '@inertiajs/react';
import { BookOpen, Star, Users, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';

interface Course {
    id: number;
    title: string;
    slug: string;
    description: string;
    price: string;
    cover_image: string | null;
    instructor: {
        name: string;
    };
}

export default function Index({ courses }: { courses: Course[] }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Catálogo de Cursos', href: '/cursos' }]}>
            <Head title="Cursos de Programación | LMS" />

            <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div className="text-center mb-16">
                    <h1 className="text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-5xl">
                        Aprende de los mejores instructores
                    </h1>
                    <p className="mt-4 text-xl text-slate-500 dark:text-slate-400">
                        Cursos prácticos para desarrolladores modernos.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-y-10 gap-x-6 sm:grid-cols-2 lg:grid-cols-3 xl:gap-x-8">
                    {courses.map((course) => (
                        <div key={course.id} className="group relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm transition-all hover:shadow-md">
                            <div className="aspect-video bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                {course.cover_image ? (
                                    <img src={`/storage/${course.cover_image}`} alt={course.title} className="h-full w-full object-cover transition-transform group-hover:scale-105" />
                                ) : (
                                    <div className="flex items-center justify-center h-full">
                                        <BookOpen className="h-12 w-12 text-slate-300" />
                                    </div>
                                )}
                            </div>
                            <div className="flex flex-1 flex-col p-6">
                                <div className="flex items-center justify-between mb-3">
                                    <span className="inline-flex items-center rounded-lg bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 text-[10px] font-bold text-indigo-700 dark:text-indigo-400 uppercase tracking-tighter border border-indigo-100 dark:border-indigo-800">
                                        Desarrollo Web
                                    </span>
                                    <div className="text-sm font-bold text-slate-900 dark:text-white">
                                        {course.price === '0.00' ? (
                                            <span className="text-green-600 dark:text-green-400">Gratis</span>
                                        ) : (
                                            `$${course.price}`
                                        )}
                                    </div>
                                </div>
                                <h3 className="text-xl font-bold text-slate-900 dark:text-white mb-2 leading-tight group-hover:text-blue-600 transition-colors">
                                    <Link href={`/cursos/${course.slug}`}>
                                        {course.title}
                                    </Link>
                                </h3>
                                <p className="text-sm text-slate-500 dark:text-slate-400 line-clamp-2 mb-4">
                                    {course.description}
                                </p>
                                
                                <div className="mt-auto pt-4 flex flex-col gap-4">
                                    <Button asChild variant="outline" className="w-full rounded-xl border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                        <Link href={`/cursos/${course.slug}`}>
                                            Ver detalles
                                            <ChevronRight className="ml-2 h-4 w-4" />
                                        </Link>
                                    </Button>
                                    <div className="flex items-center space-x-2">
                                        <div className="h-6 w-6 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center">
                                            <span className="text-[10px] font-bold">{course.instructor.name[0]}</span>
                                        </div>
                                        <span className="text-xs text-slate-600 dark:text-slate-400">{course.instructor.name}</span>
                                    </div>
                                    <div className="flex items-center space-x-3 text-xs text-slate-500">
                                        <span className="flex items-center"><Star className="h-3 w-3 text-yellow-500 mr-1 fill-yellow-500" /> 4.9</span>
                                        <span className="flex items-center"><Users className="h-3 w-3 mr-1" /> 120</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {courses.length === 0 && (
                    <div className="text-center py-20 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-3xl">
                        <BookOpen className="mx-auto h-12 w-12 text-slate-400" />
                        <h3 className="mt-2 text-sm font-semibold text-slate-900 dark:text-white">Aún no hay cursos</h3>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">¡Vuelve pronto para ver las novedades!</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
