import { Head, Link, router } from '@inertiajs/react';
import { Plus, Edit, Trash2, Eye, Layout } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';

interface Course {
    id: number;
    title: string;
    description: string;
    price: string;
    is_published: boolean;
    slug: string;
    cover_image?: string;
}

export default function Index({ courses }: { courses: Course[] }) {
    return (
        <AppLayout
            breadcrumbs={[{ title: 'Mis Cursos', href: '/instructor/cursos' }]}
        >
            <Head title="Mis Cursos | Instructor" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {courses.map((course) => (
                        <div
                            key={course.id}
                            className="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                        >
                            {/* Imagen de Portada */}
                            <div className="relative h-40 w-full bg-slate-100 dark:bg-slate-800">
                                {course.cover_image ? (
                                    <img
                                        src={`/storage/${course.cover_image}`}
                                        alt={course.title}
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center">
                                        <Layout className="h-10 w-10 text-slate-300" />
                                    </div>
                                )}
                                <div className="absolute top-3 left-3">
                                    <div
                                        className={`rounded px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase ${course.is_published ? 'bg-green-500 text-white' : 'bg-amber-500 text-white'}`}
                                    >
                                        {course.is_published
                                            ? 'Publicado'
                                            : 'Borrador'}
                                    </div>
                                </div>
                            </div>

                            <div className="flex flex-1 flex-col p-5">
                                <div className="mb-2 flex items-start justify-between">
                                    <h3 className="line-clamp-1 text-lg font-bold text-slate-800 dark:text-slate-100">
                                        {course.title}
                                    </h3>
                                    <span className="text-sm font-bold text-slate-900 dark:text-white">
                                        ${course.price}
                                    </span>
                                </div>
                                <p className="mb-6 line-clamp-2 h-10 text-sm text-slate-500 dark:text-slate-400">
                                    {course.description}
                                </p>

                                <div className="mt-auto grid grid-cols-2 gap-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={`/instructor/cursos/${course.id}/edit`}
                                        >
                                            <Edit className="mr-1 h-3 w-3" />
                                            Editar
                                        </Link>
                                    </Button>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={`/cursos/${course.slug}`}
                                            className="text-blue-600 dark:text-blue-400"
                                        >
                                            <Eye className="mr-1 h-3 w-3" />
                                            Ver
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="col-span-2 text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
                                        onClick={() => {
                                            if (
                                                confirm(
                                                    '¿Estás seguro de que deseas eliminar este curso? Esta acción no se puede deshacer.',
                                                )
                                            ) {
                                                router.delete(
                                                    `/instructor/cursos/${course.id}`,
                                                );
                                            }
                                        }}
                                    >
                                        <Trash2 className="mr-1 h-3 w-3" />
                                        Eliminar Curso
                                    </Button>
                                </div>
                            </div>
                        </div>
                    ))}

                    {courses.length === 0 && (
                        <div className="col-span-full flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 py-20 dark:border-slate-800 dark:bg-slate-900/50">
                            <Layout className="mb-4 h-12 w-12 text-slate-300" />
                            <h3 className="font-bold text-slate-700 dark:text-slate-300">
                                No has creado cursos todavía
                            </h3>
                            <p className="mb-6 max-w-xs text-center text-sm text-slate-500">
                                ¡Comienza compartinedo tu conocimiento con el
                                mundo!
                            </p>
                            <Button asChild>
                                <Link href="/instructor/cursos/create">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Crear mi primer curso
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
