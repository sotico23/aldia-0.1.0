import { Head, useForm } from '@inertiajs/react';
import {
    Store,
    User as UserIcon,
    Building2,
    ArrowRight,
    ArrowLeft,
    CheckCircle2,
    Store as StoreIcon,
    Briefcase,
    Globe,
    Phone,
    MapPin
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export default function Onboarding({ user }: { user: any }) {
    const [step, setStep] = useState(1);

    const { data, setData, post, processing, errors } = useForm({
        tipo_entidad: '',
        name: user.name || '',
        email: user.email || '',
        telefono: user.telefono || '',
        direccion: user.direccion || '',
        ciudad: user.ciudad || '',
        store_name: '',
        store_description: '',
    });

    const steps = [
        { id: 1, title: 'Tipo de Cuenta' },
        { id: 2, title: 'Información Personal' },
        { id: 3, title: 'Perfil de Negocio' },
    ];

    const entityTypes = [
        {
            id: 'independiente',
            title: 'Vendedor Independiente',
            description:
                'Para personas naturales que venden productos o servicios de forma autónoma.',
            icon: UserIcon,
            color: 'bg-blue-100 text-blue-600',
        },
        {
            id: 'negocio',
            title: 'Pequeño Negocio / Empresa',
            description:
                'Para negocios establecidos, locales comerciales o PyMEs con equipo reducido.',
            icon: Store,
            color: 'bg-emerald-100 text-emerald-600',
        },
        {
            id: 'empresa',
            title: 'Corporación / Gran Empresa',
            description:
                'Para organizaciones de gran escala, múltiples sucursales y operaciones complejas.',
            icon: Building2,
            color: 'bg-indigo-100 text-indigo-600',
        },
    ];

    const nextStep = () => setStep((s) => s + 1);
    const prevStep = () => setStep((s) => s - 1);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/onboarding');
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-50 p-4 dark:bg-slate-950">
            <Head title="Bienvenido a GrowERP" />

            <div className="w-full max-w-2xl space-y-8">
                {/* Logo and Progress */}
                <div className="space-y-4 text-center">
                    <div className="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-white shadow-lg shadow-primary/20">
                        <Briefcase className="h-6 w-6" />
                    </div>
                    <h1 className="text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                        Configura tu Espacio de Trabajo
                    </h1>

                    <div className="flex items-center justify-center gap-2">
                        {steps.map((s) => (
                            <div
                                key={s.id}
                                className={`h-2 rounded-full transition-all duration-300 ${
                                    step >= s.id
                                        ? 'w-12 bg-primary'
                                        : 'w-4 bg-slate-200 dark:bg-slate-800'
                                }`}
                            />
                        ))}
                    </div>
                    <p className="text-sm font-bold tracking-widest text-primary uppercase">
                        Paso {step} de 3:{' '}
                        {steps.find((s) => s.id === step)?.title}
                    </p>
                </div>

                <Card className="border-none bg-white/80 shadow-2xl ring-1 shadow-primary/5 ring-slate-200 backdrop-blur-xl dark:bg-slate-900/80 dark:ring-slate-800">
                    <CardContent className="p-8">
                        <form onSubmit={submit}>
                            {/* Step 1: Entity Type */}
                            {step === 1 && (
                                <div className="animate-in space-y-6 duration-500 fade-in slide-in-from-bottom-4">
                                    <div className="space-y-4">
                                        {entityTypes.map((type) => (
                                            <button
                                                key={type.id}
                                                type="button"
                                                onClick={() =>
                                                    setData(
                                                        'tipo_entidad',
                                                        type.id,
                                                    )
                                                }
                                                className={`group flex w-full items-start gap-4 rounded-2xl border-2 p-4 text-left transition-all hover:scale-[1.01] active:scale-[0.99] ${
                                                    data.tipo_entidad ===
                                                    type.id
                                                        ? 'border-primary bg-primary/5 ring-4 ring-primary/10'
                                                        : 'border-transparent bg-slate-50 hover:border-slate-300 dark:bg-slate-800/50 dark:hover:border-slate-700'
                                                }`}
                                            >
                                                <div
                                                    className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${type.color}`}
                                                >
                                                    <type.icon className="h-6 w-6" />
                                                </div>
                                                <div className="space-y-1">
                                                    <p
                                                        className={`font-bold transition-colors ${data.tipo_entidad === type.id ? 'text-primary' : 'text-slate-900 dark:text-white'}`}
                                                    >
                                                        {type.title}
                                                    </p>
                                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                                        {type.description}
                                                    </p>
                                                </div>
                                                {data.tipo_entidad ===
                                                    type.id && (
                                                    <div className="ml-auto">
                                                        <CheckCircle2 className="h-6 w-6 text-primary" />
                                                    </div>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                    <InputError message={errors.tipo_entidad} />

                                    <Button
                                        type="button"
                                        disabled={!data.tipo_entidad}
                                        onClick={nextStep}
                                        className="h-12 w-full rounded-xl text-lg font-bold shadow-lg shadow-primary/25"
                                    >
                                        Continuar
                                        <ArrowRight className="ml-2 h-5 w-5" />
                                    </Button>
                                </div>
                            )}

                            {/* Step 2: Personal Info */}
                            {step === 2 && (
                                <div className="animate-in space-y-6 duration-500 fade-in slide-in-from-right-4">
                                    <div className="grid gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="name">
                                                Nombre Completo o Razón Social
                                            </Label>
                                            <div className="relative">
                                                <UserIcon className="absolute top-2.5 left-3 h-5 w-5 text-slate-400" />
                                                <Input
                                                    id="name"
                                                    value={data.name}
                                                    onChange={(e) =>
                                                        setData(
                                                            'name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-11 rounded-xl pl-11"
                                                    placeholder="Ej: Juan Pérez o Inversiones S.A."
                                                />
                                            </div>
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="email">
                                                Email de Contacto
                                            </Label>
                                            <div className="relative">
                                                <Globe className="absolute top-2.5 left-3 h-5 w-5 text-slate-400" />
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    value={data.email}
                                                    onChange={(e) =>
                                                        setData(
                                                            'email',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-11 rounded-xl pl-11"
                                                    placeholder="juan@ejemplo.com"
                                                />
                                            </div>
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>

                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="telefono">
                                                    Teléfono
                                                </Label>
                                                <div className="relative">
                                                    <Phone className="absolute top-2.5 left-3 h-5 w-5 text-slate-400" />
                                                    <Input
                                                        id="telefono"
                                                        value={data.telefono}
                                                        onChange={(e) =>
                                                            setData(
                                                                'telefono',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="h-11 rounded-xl pl-11"
                                                        placeholder="+56 9 ..."
                                                    />
                                                </div>
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="ciudad">
                                                    Ciudad
                                                </Label>
                                                <div className="relative">
                                                    <MapPin className="absolute top-2.5 left-3 h-5 w-5 text-slate-400" />
                                                    <Input
                                                        id="ciudad"
                                                        value={data.ciudad}
                                                        onChange={(e) =>
                                                            setData(
                                                                'ciudad',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="h-11 rounded-xl pl-11"
                                                        placeholder="Santiago"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="direccion">
                                                Dirección
                                            </Label>
                                            <Input
                                                id="direccion"
                                                value={data.direccion}
                                                onChange={(e) =>
                                                    setData(
                                                        'direccion',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-11 rounded-xl"
                                                placeholder="Ej: Av. Providencia 123"
                                            />
                                        </div>
                                    </div>

                                    <div className="flex gap-4">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={prevStep}
                                            className="h-12 flex-1 rounded-xl"
                                        >
                                            <ArrowLeft className="mr-2 h-5 w-5" />
                                            Atrás
                                        </Button>
                                        <Button
                                            type="button"
                                            onClick={nextStep}
                                            className="h-12 flex-[2] rounded-xl font-bold shadow-lg shadow-primary/25"
                                        >
                                            Continuar
                                            <ArrowRight className="ml-2 h-5 w-5" />
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {/* Step 3: Store Profile */}
                            {step === 3 && (
                                <div className="animate-in space-y-6 duration-500 fade-in slide-in-from-right-4">
                                    <div className="space-y-4">
                                        <div className="space-y-2 rounded-xl border border-primary/20 bg-primary/5 p-4">
                                            <div className="flex items-center gap-2 font-bold text-primary">
                                                <StoreIcon className="h-5 w-5" />
                                                ¿Por qué configurar tu tienda?
                                            </div>
                                            <p className="text-xs text-slate-600 dark:text-slate-400">
                                                Esto creará automáticamente tu
                                                perfil público en el
                                                Marketplace, permitiéndote
                                                recibir pedidos y cotizaciones
                                                desde hoy mismo.
                                            </p>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="store_name">
                                                Nombre de tu Tienda / Marca
                                            </Label>
                                            <Input
                                                id="store_name"
                                                value={data.store_name}
                                                onChange={(e) =>
                                                    setData(
                                                        'store_name',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-11 rounded-xl"
                                                placeholder="Ej: Bazar Central o Tecnología Pro"
                                            />
                                            <InputError
                                                message={errors.store_name}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="store_description">
                                                Breve descripción de lo que
                                                ofreces
                                            </Label>
                                            <Textarea
                                                id="store_description"
                                                value={data.store_description}
                                                onChange={(e) =>
                                                    setData(
                                                        'store_description',
                                                        e.target.value,
                                                    )
                                                }
                                                className="min-h-[120px] rounded-xl"
                                                placeholder="Cuéntanos un poco sobre tu negocio y tus productos principales..."
                                            />
                                            <InputError
                                                message={
                                                    errors.store_description
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="flex gap-4">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={prevStep}
                                            className="h-12 flex-1 rounded-xl"
                                        >
                                            <ArrowLeft className="mr-2 h-5 w-5" />
                                            Atrás
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="h-12 flex-[2] rounded-xl font-bold shadow-lg shadow-primary/25"
                                        >
                                            Finalizar Configuración
                                            <CheckCircle2 className="ml-2 h-5 w-5" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </form>
                    </CardContent>
                </Card>

                <p className="text-center text-xs text-slate-400">
                    Podrás modificar toda esta información más adelante en la
                    configuración de tu perfil.
                </p>
            </div>
        </div>
    );
}
