import { Head, useForm, usePage, Link, router } from '@inertiajs/react';
import {
    ShoppingBag,
    FileText,
    User as UserIcon,
    Upload,
    Download,
    Trash2,
    Building,
    LogOut,
    ChevronDown,
    ChevronUp
} from 'lucide-react';
import React, { useState } from 'react';
import { Toaster, toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCountry } from '@/hooks/use-country';
import proveedorRoutes from '@/routes/proveedor';

interface DetalleCompraItem {
    id: number;
    producto_id: number;
    cantidad: number;
    precio_unitario: number;
    subtotal: number;
    producto?: {
        id: number;
        nombre: string;
        codigo: string;
    };
}

interface Compra {
    id: number;
    numero: string;
    fecha: string;
    subtotal: number;
    iva: number;
    total: number;
    estado: string;
    notas: string | null;
    created_at: string;
    detalle_compras: DetalleCompraItem[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Documento {
    id: number;
    titulo: string;
    archivo: string;
    descripcion: string | null;
    url: string;
    created_at: string;
}

interface ProveedorData {
    id: number;
    nombre: string;
    nit: string;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    activo: boolean;
}

interface DashboardProps {
    proveedor: ProveedorData;
    compras: {
        data: Compra[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
    };
    documentos: Documento[];
    business: {
        name: string;
        logo: string | null;
        primary_color: string;
        secondary_color: string;
        phone: string | null;
        email: string | null;
    };
}

const estadoBadge = (estado: string) => {
    const map: Record<string, { label: string; class: string }> = {
        pendiente: { label: 'Pendiente', class: 'bg-yellow-100 text-yellow-800' },
        aprobado: { label: 'Aprobada', class: 'bg-green-100 text-green-800' },
        completado: { label: 'Completada', class: 'bg-blue-100 text-blue-800' },
        cancelado: { label: 'Cancelada', class: 'bg-red-100 text-red-800' },
        anulado: { label: 'Anulada', class: 'bg-red-100 text-red-800' },
    };
    const entry = map[estado] ?? { label: estado, class: 'bg-gray-100 text-gray-800' };
    return <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${entry.class}`}>{entry.label}</span>;
};

export default function ProveedorDashboard({
    proveedor,
    compras,
    documentos,
    business,
}: DashboardProps) {
    const { auth } = usePage().props as any;
    const { code: countryCode, currency } = useCountry();
    const currentUser = auth.user;

    const [activeTab, setActiveTab] = useState<'compras' | 'perfil' | 'documentos'>('compras');
    const [expandedCompra, setExpandedCompra] = useState<number | null>(null);
    const [uploading, setUploading] = useState(false);

    const { data: perfilData, setData: setPerfilData, put: updatePerfil, processing: perfilProcessing, errors: perfilErrors, recentlySuccessful } = useForm({
        telefono: proveedor.telefono || '',
        email: proveedor.email || '',
        direccion: proveedor.direccion || '',
    });

    const { data: docData, setData: setDocData, post: uploadDoc, processing: docProcessing, errors: docErrors, reset: resetDoc } = useForm({
        titulo: '',
        archivo: null as File | null,
        descripcion: '',
    });

    const handleProfileSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        (updatePerfil as any)((proveedorRoutes as any).perfil.update().url, {
            onSuccess: () => {
                toast.success('Perfil actualizado correctamente');
            },
        });
    };

    const handleDocUpload = (e: React.FormEvent) => {
        e.preventDefault();
        setUploading(true);
        (uploadDoc as any)((proveedorRoutes as any).documentos.store().url, {
            forceFormData: true,
            onSuccess: () => {
                toast.success('Documento subido correctamente');
                resetDoc();
                setUploading(false);
            },
            onError: () => {
                setUploading(false);
            },
        });
    };

    const handleDeleteDoc = (docId: number) => {
        if (confirm('¿Eliminar este documento?')) {
            router.delete((proveedorRoutes as any).documentos.destroy(docId).url, {
                onSuccess: () => toast.success('Documento eliminado'),
            });
        }
    };

    const isActive = (tab: string) =>
        activeTab === tab
            ? 'border-b-2 border-indigo-600 text-indigo-600 font-medium'
            : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent';

    return (
        <>
            <Head title="Portal de Proveedor" />
            <Toaster position="top-right" richColors />

            <div className="min-h-screen bg-gray-50">
                <header className="bg-white shadow-sm border-b">
                    <div className="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            {business.logo ? (
                                <img src={business.logo} alt={business.name} className="h-8 w-auto" />
                            ) : (
                                <Building className="h-6 w-6 text-indigo-600" />
                            )}
                            <div>
                                <h1 className="text-lg font-bold text-gray-900">{business.name}</h1>
                                <p className="text-xs text-gray-500">Portal de Proveedor</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <span className="text-sm text-gray-600">{currentUser?.name}</span>
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="text-sm text-gray-500 hover:text-red-600 flex items-center gap-1"
                            >
                                <LogOut className="h-4 w-4" />
                                Salir
                            </Link>
                        </div>
                    </div>
                </header>

                <div className="max-w-6xl mx-auto px-4 py-6">
                    <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
                        <div className="border-b bg-gray-50">
                            <nav className="flex">
                                <button onClick={() => setActiveTab('compras')} className={`px-6 py-3 text-sm transition-colors ${isActive('compras')}`}>
                                    <ShoppingBag className="h-4 w-4 inline mr-2" />
                                    Órdenes de Compra
                                </button>
                                <button onClick={() => setActiveTab('perfil')} className={`px-6 py-3 text-sm transition-colors ${isActive('perfil')}`}>
                                    <UserIcon className="h-4 w-4 inline mr-2" />
                                    Mi Perfil
                                </button>
                                <button onClick={() => setActiveTab('documentos')} className={`px-6 py-3 text-sm transition-colors ${isActive('documentos')}`}>
                                    <FileText className="h-4 w-4 inline mr-2" />
                                    Documentos
                                </button>
                            </nav>
                        </div>

                        <div className="p-6">
                            {/* TAB: Órdenes de Compra */}
                            {activeTab === 'compras' && (
                                <div>
                                    <div className="flex items-center justify-between mb-4">
                                        <h2 className="text-lg font-semibold text-gray-900">Órdenes de Compra</h2>
                                    </div>

                                    {compras.data.length === 0 ? (
                                        <div className="text-center py-12 text-gray-500">
                                            <ShoppingBag className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                                            <p>No tienes órdenes de compra asignadas.</p>
                                        </div>
                                    ) : (
                                        <div className="space-y-3">
                                            {compras.data.map((compra) => (
                                                <Card key={compra.id} className="border">
                                                    <div
                                                        className="p-4 cursor-pointer hover:bg-gray-50 transition-colors"
                                                        onClick={() => setExpandedCompra(expandedCompra === compra.id ? null : compra.id)}
                                                    >
                                                        <div className="flex items-center justify-between">
                                                            <div className="flex items-center gap-4">
                                                                <div>
                                                                    <span className="font-medium text-gray-900">Orden #{compra.numero}</span>
                                                                    <span className="ml-3">{estadoBadge(compra.estado)}</span>
                                                                </div>
                                                                <span className="text-sm text-gray-500">
                                                                    {new Date(compra.created_at).toLocaleDateString(currency.locale, {
                                                                        year: 'numeric',
                                                                        month: 'short',
                                                                        day: 'numeric',
                                                                    })}
                                                                </span>
                                                            </div>
                                                            <div className="flex items-center gap-3">
                                                                <span className="font-semibold text-gray-900">
                                                                    ${Number(compra.total).toLocaleString(currency.locale)}
                                                                </span>
                                                                <a
                                                                    href={`/proveedor/compras/${compra.id}/pdf`}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="text-indigo-600 hover:text-indigo-800"
                                                                    onClick={(e) => e.stopPropagation()}
                                                                    title="Descargar PDF"
                                                                >
                                                                    <Download className="h-4 w-4" />
                                                                </a>
                                                                {expandedCompra === compra.id ? (
                                                                    <ChevronUp className="h-4 w-4 text-gray-400" />
                                                                ) : (
                                                                    <ChevronDown className="h-4 w-4 text-gray-400" />
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {expandedCompra === compra.id && (
                                                        <div className="border-t px-4 py-3 bg-gray-50">
                                                            <div className="overflow-x-auto">
                                                            <table className="w-full text-sm">
                                                                <thead>
                                                                    <tr className="border-b text-gray-600">
                                                                        <th className="pb-2 text-left font-medium">Producto</th>
                                                                        <th className="pb-2 text-center font-medium">Cant.</th>
                                                                        <th className="pb-2 text-right font-medium">Precio</th>
                                                                        <th className="pb-2 text-right font-medium">Subtotal</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    {compra.detalle_compras.map((item) => (
                                                                        <tr key={item.id} className="border-b last:border-0">
                                                                            <td className="py-2">{item.producto?.nombre ?? `Producto #${item.producto_id}`}</td>
                                                                            <td className="py-2 text-center">{item.cantidad}</td>
                                                                            <td className="py-2 text-right">${Number(item.precio_unitario).toLocaleString(currency.locale)}</td>
                                                                            <td className="py-2 text-right font-medium">${Number(item.subtotal).toLocaleString(currency.locale)}</td>
                                                                        </tr>
                                                                    ))}
                                                                </tbody>
                                                                <tfoot>
                                                                    <tr>
                                                                        <td colSpan={3} className="pt-2 text-right text-gray-600">Subtotal:</td>
                                                                        <td className="pt-2 text-right">${Number(compra.subtotal).toLocaleString(currency.locale)}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td colSpan={3} className="text-right text-gray-600">IVA (19%):</td>
                                                                        <td className="text-right">${Number(compra.iva).toLocaleString(currency.locale)}</td>
                                                                    </tr>
                                                                    <tr className="font-bold">
                                                                        <td colSpan={3} className="pt-1 text-right">Total:</td>
                                                                        <td className="pt-1 text-right">${Number(compra.total).toLocaleString(currency.locale)}</td>
                                                                    </tr>
                                                                </tfoot>
                                                             </table>
                                                             </div>
                                                             {compra.notas && (
                                                                 <div className="mt-3 text-sm text-gray-600 bg-white p-3 rounded border">
                                                                     <span className="font-medium">Notas:</span> {compra.notas}
                                                                 </div>
                                                             )}
                                                         </div>
                                                     )}
                                                 </Card>
                                            ))}
                                        </div>
                                    )}

                                    {compras.links && compras.links.length > 3 && (
                                        <div className="flex justify-center gap-2 mt-6">
                                            {compras.links.map((link, i) => (
                                                <Link
                                                    key={i}
                                                    href={link.url || '#'}
                                                    className={`px-3 py-1.5 text-sm rounded border ${
                                                        link.active
                                                            ? 'bg-indigo-600 text-white border-indigo-600'
                                                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                                    } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                                    preserveScroll
                                                >
                                                    {link.label}
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* TAB: Mi Perfil */}
                            {activeTab === 'perfil' && (
                                <div>
                                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Mi Perfil</h2>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="text-base">Información Actual</CardTitle>
                                            </CardHeader>
                                            <CardContent className="space-y-3">
                                                <div>
                                                    <span className="text-sm text-gray-500">Nombre</span>
                                                    <p className="font-medium">{proveedor.nombre}</p>
                                                </div>
                                                <div>
                                                    <span className="text-sm text-gray-500">NIT</span>
                                                    <p className="font-medium">{proveedor.nit || '—'}</p>
                                                </div>
                                                <div>
                                                    <span className="text-sm text-gray-500">Teléfono</span>
                                                    <p className="font-medium">{proveedor.telefono || '—'}</p>
                                                </div>
                                                <div>
                                                    <span className="text-sm text-gray-500">Email</span>
                                                    <p className="font-medium">{proveedor.email || '—'}</p>
                                                </div>
                                                <div>
                                                    <span className="text-sm text-gray-500">Dirección</span>
                                                    <p className="font-medium">{proveedor.direccion || '—'}</p>
                                                </div>
                                                <div>
                                                    <span className="text-sm text-gray-500">Estado</span>
                                                    <p>{proveedor.activo ? (
                                                        <Badge className="bg-green-100 text-green-800">Activo</Badge>
                                                    ) : (
                                                        <Badge className="bg-red-100 text-red-800">Inactivo</Badge>
                                                    )}</p>
                                                </div>
                                            </CardContent>
                                        </Card>

                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="text-base">Editar Información</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <form onSubmit={handleProfileSubmit} className="space-y-4">
                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                                        <Input
                                                            value={perfilData.telefono}
                                                            onChange={(e) => setPerfilData('telefono', e.target.value)}
                                                            placeholder="Teléfono de contacto"
                                                        />
                                                        {perfilErrors.telefono && (
                                                            <p className="text-sm text-red-600 mt-1">{perfilErrors.telefono}</p>
                                                        )}
                                                    </div>
                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                                        <Input
                                                            type="email"
                                                            value={perfilData.email}
                                                            onChange={(e) => setPerfilData('email', e.target.value)}
                                                            placeholder="Correo electrónico"
                                                        />
                                                        {perfilErrors.email && (
                                                            <p className="text-sm text-red-600 mt-1">{perfilErrors.email}</p>
                                                        )}
                                                    </div>
                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                                                        <Input
                                                            value={perfilData.direccion}
                                                            onChange={(e) => setPerfilData('direccion', e.target.value)}
                                                            placeholder="Dirección"
                                                        />
                                                        {perfilErrors.direccion && (
                                                            <p className="text-sm text-red-600 mt-1">{perfilErrors.direccion}</p>
                                                        )}
                                                    </div>
                                                    <Button type="submit" disabled={perfilProcessing} className="w-full">
                                                        {perfilProcessing ? 'Guardando...' : 'Guardar Cambios'}
                                                    </Button>
                                                    {recentlySuccessful && (
                                                        <p className="text-sm text-green-600 text-center">Perfil actualizado</p>
                                                    )}
                                                </form>
                                            </CardContent>
                                        </Card>
                                    </div>
                                </div>
                            )}

                            {/* TAB: Documentos */}
                            {activeTab === 'documentos' && (
                                <div>
                                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Documentos</h2>

                                    <Card className="mb-6">
                                        <CardHeader>
                                            <CardTitle className="text-base">Subir Documento</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <form onSubmit={handleDocUpload} className="space-y-4">
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-1">Título</label>
                                                    <Input
                                                        value={docData.titulo}
                                                        onChange={(e) => setDocData('titulo', e.target.value)}
                                                        placeholder="Nombre del documento"
                                                        required
                                                    />
                                                    {docErrors.titulo && (
                                                        <p className="text-sm text-red-600 mt-1">{docErrors.titulo}</p>
                                                    )}
                                                </div>
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-1">Archivo</label>
                                                    <Input
                                                        type="file"
                                                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                                        onChange={(e) => {
                                                            const file = e.target.files?.[0] || null;
                                                            setDocData('archivo', file);
                                                        }}
                                                        required
                                                    />
                                                    <p className="text-xs text-gray-500 mt-1">PDF, JPG, PNG, DOC hasta 10MB</p>
                                                    {docErrors.archivo && (
                                                        <p className="text-sm text-red-600 mt-1">{docErrors.archivo}</p>
                                                    )}
                                                </div>
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-1">Descripción (opcional)</label>
                                                    <Textarea
                                                        value={docData.descripcion}
                                                        onChange={(e) => setDocData('descripcion', e.target.value)}
                                                        placeholder="Breve descripción del documento"
                                                        rows={2}
                                                    />
                                                </div>
                                                <Button type="submit" disabled={docProcessing || uploading} className="w-full">
                                                    <Upload className="h-4 w-4 mr-2" />
                                                    {uploading ? 'Subiendo...' : 'Subir Documento'}
                                                </Button>
                                            </form>
                                        </CardContent>
                                    </Card>

                                    <h3 className="font-medium text-gray-800 mb-3">Documentos Subidos</h3>

                                    {documentos.length === 0 ? (
                                        <div className="text-center py-8 text-gray-500">
                                            <FileText className="h-10 w-10 mx-auto mb-2 text-gray-300" />
                                            <p>Aún no has subido documentos.</p>
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            {documentos.map((doc) => (
                                                <div key={doc.id} className="flex items-center justify-between p-3 bg-white border rounded-lg hover:bg-gray-50">
                                                    <div className="flex items-center gap-3 min-w-0">
                                                        <FileText className="h-5 w-5 text-indigo-500 shrink-0" />
                                                        <div className="min-w-0">
                                                            <p className="font-medium text-sm truncate">{doc.titulo}</p>
                                                            {doc.descripcion && (
                                                                <p className="text-xs text-gray-500 truncate">{doc.descripcion}</p>
                                                            )}
                                                            <p className="text-xs text-gray-400">
                                                                {new Date(doc.created_at).toLocaleDateString(currency.locale)}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2 shrink-0">
                                                        <a
                                                            href={doc.url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="p-2 text-indigo-600 hover:bg-indigo-50 rounded"
                                                            title="Descargar"
                                                        >
                                                            <Download className="h-4 w-4" />
                                                        </a>
                                                        <button
                                                            onClick={() => handleDeleteDoc(doc.id)}
                                                            className="p-2 text-red-500 hover:bg-red-50 rounded"
                                                            title="Eliminar"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
