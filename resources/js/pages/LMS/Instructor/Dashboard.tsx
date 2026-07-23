import { Head, Link } from '@inertiajs/react';
import {
    BookOpen,
    Users,
    DollarSign,
    TrendingUp,
    Plus,
    BarChart3,
    GraduationCap,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

interface Stats {
    courses: number;
    students: number;
    revenue: number;
}

export default function InstructorDashboard({ stats }: { stats: Stats }) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Instructor', href: '#' },
                { title: 'Dashboard', href: '/instructor/dashboard' },
            ]}
        >
            <Head title="Dashboard del Instructor" />

            <div className="mx-auto max-w-7xl p-6">
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-zinc-900 dark:text-white">
                            Panel de Instructor
                        </h1>
                        <p className="mt-1 text-zinc-500 dark:text-zinc-400">
                            Gestiona tus cursos y visibilidad del contenido
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/instructor/cursos/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Crear Nuevo Curso
                        </Link>
                    </Button>
                </div>

                {/* Stats Grid */}
                <div className="mb-8 grid grid-cols-1 gap-6 md:grid-cols-3">
                    <Card className="border-none bg-gradient-to-br from-blue-500 to-blue-600 text-white">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-blue-100">
                                Cursos Activos
                            </CardTitle>
                            <BookOpen className="h-5 w-5 text-blue-200" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-4xl font-bold">
                                {stats.courses}
                            </div>
                            <p className="mt-1 text-xs text-blue-200">
                                Cursos publicados
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-none bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-emerald-100">
                                Total Alumnos
                            </CardTitle>
                            <Users className="h-5 w-5 text-emerald-200" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-4xl font-bold">
                                {stats.students}
                            </div>
                            <p className="mt-1 text-xs text-emerald-200">
                                Alumnos inscritos
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-none bg-gradient-to-br from-purple-500 to-purple-600 text-white">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-purple-100">
                                Ingresos Totales
                            </CardTitle>
                            <DollarSign className="h-5 w-5 text-purple-200" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-4xl font-bold">
                                ${stats.revenue.toLocaleString()}
                            </div>
                            <p className="mt-1 text-xs text-purple-200">
                                Ventas realizadas
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Quick Actions */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BarChart3 className="h-5 w-5" />
                                Acciones Rápidas
                            </CardTitle>
                            <CardDescription>
                                Gestión inmediata de tu contenido educativo
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Button
                                variant="outline"
                                className="w-full justify-start"
                                asChild
                            >
                                <Link href="/instructor/cursos">
                                    <BookOpen className="mr-2 h-4 w-4" />
                                    Ver Todos Mis Cursos
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                className="w-full justify-start"
                                asChild
                            >
                                <Link href="/instructor/cursos/create">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Crear Nuevo Curso
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                className="w-full justify-start"
                                asChild
                            >
                                <Link href="/cursos">
                                    <TrendingUp className="mr-2 h-4 w-4" />
                                    Ver Catálogo Público
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <GraduationCap className="h-5 w-5" />
                                Próximos Pasos
                            </CardTitle>
                            <CardDescription>
                                Recomendaciones para mejorar tu contenido
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {stats.courses === 0 ? (
                                <div className="py-6 text-center">
                                    <GraduationCap className="mx-auto mb-3 h-12 w-12 text-zinc-300 dark:text-zinc-600" />
                                    <p className="text-sm text-zinc-500">
                                        ¡Crea tu primer curso para comenzar a
                                        atraer estudiantes!
                                    </p>
                                    <Button className="mt-4" asChild>
                                        <Link href="/instructor/cursos/create">
                                            Crear Mi Primer Curso
                                        </Link>
                                    </Button>
                                </div>
                            ) : (
                                <ul className="space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                                    <li className="flex items-start gap-2">
                                        <span className="text-blue-500">•</span>
                                        Añade más módulos y lecciones a tus
                                        cursos
                                    </li>
                                    <li className="flex items-start gap-2">
                                        <span className="text-blue-500">•</span>
                                        Actualiza el precio de tus cursos según
                                        el mercado
                                    </li>
                                    <li className="flex items-start gap-2">
                                        <span className="text-blue-500">•</span>
                                        Revisa el progreso de tus estudiantes
                                    </li>
                                    <li className="flex items-start gap-2">
                                        <span className="text-blue-500">•</span>
                                        Publica cursos que estén en borrador
                                    </li>
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
