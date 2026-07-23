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
        <AppLayout breadcrumbs={[{ title: 'Mis Cursos', href: '/instructor/cursos' }]}>
            <Head title="Mis Cursos | Instructor" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">


                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {courses.map((course) => (
                        <div key={course.id} className="border rounded-xl overflow-hidden bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 shadow-sm flex flex-col">
                            {/* Imagen de Portada */}
                            <div className="relative h-40 w-full bg-slate-100 dark:bg-slate-800">
                                {course.cover_image ? (
                                    <img
                                        src={`/storage/${course.cover_image}`}
                                        alt={course.title}
                                        className="w-full h-full object-cover"
                                    />
                                ) : (
                                    <div className="w-full h-full flex items-center justify-center">
                                        <Layout className="h-10 w-10 text-slate-300" />
                                    </div>
                                )}
                                <div className="absolute top-3 left-3">
                                    <div className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${course.is_published ? 'bg-green-500 text-white' : 'bg-amber-500 text-white'}`}>
                                        {course.is_published ? 'Publicado' : 'Borrador'}
                                    </div>
                                </div>
                            </div>

                            <div className="p-5 flex-1 flex flex-col">
                                <div className="flex justify-between items-start mb-2">
                                    <h3 className="text-lg font-bold text-slate-800 dark:text-slate-100 line-clamp-1">{course.title}</h3>
                                    <span className="text-sm font-bold text-slate-900 dark:text-white">${course.price}</span>
                                </div>
                                <p className="text-sm text-slate-500 dark:text-slate-400 mb-6 line-clamp-2 h-10">{course.description}</p>

                                <div className="grid grid-cols-2 gap-2 mt-auto">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/instructor/cursos/${course.id}/edit`}>
                                            <Edit className="h-3 w-3 mr-1" />
                                            Editar
                                        </Link>
                                    </Button>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/cursos/${course.slug}`} className="text-blue-600 dark:text-blue-400">
                                            <Eye className="h-3 w-3 mr-1" />
                                            Ver
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 col-span-2"
                                        onClick={() => {
                                            if (confirm('¿Estás seguro de que deseas eliminar este curso? Esta acción no se puede deshacer.')) {
                                                router.delete(`/instructor/cursos/${course.id}`);
                                            }
                                        }}
                                    >
                                        <Trash2 className="h-3 w-3 mr-1" />
                                        Eliminar Curso
                                    </Button>
                                </div>
                            </div>
                        </div>
                    ))}

                    {courses.length === 0 && (
                        <div className="col-span-full py-20 bg-slate-50 dark:bg-slate-900/50 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 flex flex-col items-center justify-center">
                            <Layout className="h-12 w-12 text-slate-300 mb-4" />
                            <h3 className="font-bold text-slate-700 dark:text-slate-300">No has creado cursos todavía</h3>
                            <p className="text-slate-500 text-sm mb-6 text-center max-w-xs">¡Comienza compartinedo tu conocimiento con el mundo!</p>
                            <Button asChild>
                                <Link href="/instructor/cursos/create">
                                    <Plus className="h-4 w-4 mr-2" />
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
