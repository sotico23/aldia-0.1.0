import { Head, useForm } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

export default function Create({ categories }: { categories: any[] }) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        category_id: '',
        price: 0,
        cover_image: null as File | null,
        promo_video_url: '',
        features: ['', '', '', ''],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/instructor/cursos');
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'Mis Cursos', href: '/instructor/cursos' },
            { title: 'Nuevo Curso', href: '/instructor/cursos/create' }
        ]}>
            <Head title="Crear Curso | Instructor" />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6">
                    <Button variant="ghost" asChild className="pl-0 mb-4">
                        <Link href="/instructor/cursos">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Volver a la lista
                        </Link>
                    </Button>
                    <h1 className="text-3xl font-bold text-slate-900 dark:text-white">Crear Nuevo Curso</h1>
                    <p className="text-slate-500 dark:text-slate-400">Define los detalles básicos de tu curso inicial.</p>
                </div>

                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 sm:p-6 lg:p-8 shadow-sm">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-2">
                            <Label htmlFor="title">Título del Curso</Label>
                            <Input 
                                id="title"
                                placeholder="Ej: Mastering React and TypeScript"
                                value={data.title}
                                onChange={e => setData('title', e.target.value)}
                                required
                            />
                            {errors.title && <p className="text-red-500 text-xs">{errors.title}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="category">Categoría</Label>
                            <Select 
                                value={data.category_id} 
                                onValueChange={value => setData('category_id', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecciona una categoría" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map(cat => (
                                        <SelectItem key={cat.id} value={cat.id.toString()}>
                                            {cat.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.category_id && <p className="text-red-500 text-xs">{errors.category_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Descripción</Label>
                            <Textarea 
                                id="description"
                                placeholder="Describe de qué trata tu curso y qué aprenderán los alumnos..."
                                className="min-h-[150px]"
                                value={data.description}
                                onChange={e => setData('description', e.target.value)}
                            />
                            {errors.description && <p className="text-red-500 text-xs">{errors.description}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="price">Precio del Curso ($)</Label>
                            <Input 
                                id="price"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                value={data.price}
                                onChange={e => setData('price', parseFloat(e.target.value))}
                                required
                            />
                            {errors.price && <p className="text-red-500 text-xs">{errors.price}</p>}
                            <p className="text-xs text-slate-400">Deja en 0 si el curso es gratuito.</p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="cover_image">Imagen de Portada</Label>
                            <Input 
                                id="cover_image"
                                type="file"
                                accept="image/*"
                                onChange={e => setData('cover_image', e.target.files ? e.target.files[0] : null)}
                            />
                            {errors.cover_image && <p className="text-red-500 text-xs">{errors.cover_image}</p>}
                            <p className="text-xs text-slate-400">Recomendado: 1200x630px. Máximo 2MB.</p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="promo_video_url">URL del Video Promocional</Label>
                            <Input 
                                id="promo_video_url"
                                type="url"
                                placeholder="https://www.youtube.com/watch?v=..."
                                value={data.promo_video_url}
                                onChange={e => setData('promo_video_url', e.target.value)}
                            />
                            {errors.promo_video_url && <p className="text-red-500 text-xs">{errors.promo_video_url}</p>}
                            <p className="text-xs text-slate-400">Enlace a YouTube o Vimeo para el tráiler del curso.</p>
                        </div>

                        <div className="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                            <Label>¿Qué aprenderán los alumnos? (4 puntos clave)</Label>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {data.features.map((feature, index) => (
                                    <div key={index} className="space-y-1">
                                        <Input 
                                            placeholder={`Punto clave #${index + 1}`}
                                            value={feature}
                                            onChange={e => {
                                                const newFeatures = [...data.features];
                                                newFeatures[index] = e.target.value;
                                                setData('features', newFeatures);
                                            }}
                                        />
                                    </div>
                                ))}
                            </div>
                            <p className="text-xs text-slate-400">Estos puntos aparecerán destacados en la página pública del curso.</p>
                        </div>

                        <div className="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                            <Button type="submit" disabled={processing} size="lg">
                                <Save className="h-4 w-4 mr-2" />
                                Guardar y Continuar a Módulos
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
