import { Head } from '@inertiajs/react';
import { BookOpen, CheckCircle2, TrendingUp, Trophy } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';

interface Enrollment {
    id: number;
    progress_percentage: number;
    grade: number;
    course: {
        id: number;
        title: string;
        instructor: {
            name: string;
        };
    };
}

export default function StudentProgress({ enrollments }: { enrollments: Enrollment[] }) {
    // Calcular estadísticas generales
    const totalCourses = enrollments.length;
    const completedCourses = enrollments.filter(e => e.progress_percentage === 100).length;
    const averageProgress = totalCourses > 0 
        ? Math.round(enrollments.reduce((acc, e) => acc + e.progress_percentage, 0) / totalCourses) 
        : 0;

    return (
        <AppLayout breadcrumbs={[{ title: 'Progreso y Notas', href: '/alumno/progreso' }]}>
            <Head title="Mi Progreso y Notas" />

            <div className="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto">
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-zinc-900 dark:text-white">Mi Rendimiento Académico</h1>
                    <p className="text-zinc-500 dark:text-zinc-400">Sigue tu progreso y revisa tus calificaciones.</p>
                </div>

                {/* Dashboard de Stats */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Cursos en Curso</CardTitle>
                            <BookOpen className="h-4 w-4 text-zinc-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{totalCourses}</div>
                            <p className="text-xs text-zinc-500">Cursos registrados actualmente</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Progreso Promedio</CardTitle>
                            <TrendingUp className="h-4 w-4 text-zinc-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{averageProgress}%</div>
                            <Progress value={averageProgress} className="h-2 mt-2" />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Completados</CardTitle>
                            <Trophy className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{completedCourses}</div>
                            <p className="text-xs text-zinc-500">Certificados obtenidos</p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Detalle de Calificaciones y Avance</CardTitle>
                            <CardDescription>Resumen detallado por cada curso en el que estás inscrito.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[300px]">Curso</TableHead>
                                            <TableHead>Instructor</TableHead>
                                            <TableHead>Avance</TableHead>
                                            <TableHead>Nota Actual</TableHead>
                                            <TableHead className="text-right">Estado</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {enrollments.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={5} className="text-center py-10 text-zinc-500">
                                                    No hay datos de progreso disponibles.
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            enrollments.map((enrollment) => (
                                                <TableRow key={enrollment.id}>
                                                    <TableCell className="font-medium">{enrollment.course.title}</TableCell>
                                                    <TableCell>{enrollment.course.instructor.name}</TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-3">
                                                            <Progress value={enrollment.progress_percentage} className="h-2 w-full max-w-32" />
                                                            <span className="text-xs">{enrollment.progress_percentage}%</span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2">
                                                            <Badge variant={enrollment.grade >= 4 ? 'default' : 'destructive'} className="bg-emerald-100 text-emerald-800 hover:bg-emerald-200 border-none">
                                                                {enrollment.grade}
                                                            </Badge>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {enrollment.progress_percentage === 100 ? (
                                                            <Badge className="bg-emerald-500 text-white border-none flex items-center gap-1">
                                                                <CheckCircle2 className="h-3 w-3" />
                                                                Completado
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline">En curso</Badge>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
