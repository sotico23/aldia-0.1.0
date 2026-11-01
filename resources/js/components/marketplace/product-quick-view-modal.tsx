import { useEffect, useState } from 'react';
import { Minus, Package, Plus, ShoppingCart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

export interface QuickViewProduct {
    id: number;
    nombre: string;
    descripcion: string | null;
    precio_venta: number;
    imagen: string | null;
    unidad_medida: string;
    is_service: boolean;
    duracion: number | null;
}

interface ProductQuickViewModalProps {
    product: QuickViewProduct | null;
    onClose: () => void;
    onAdd: (quantity: number) => void;
}

export function ProductQuickViewModal({
    product,
    onClose,
    onAdd,
}: ProductQuickViewModalProps) {
    const [quantity, setQuantity] = useState(1);

    useEffect(() => {
        if (product) {
            setQuantity(1);
        }
    }, [product?.id]);

    return (
        <Dialog
            open={product !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                {product && (
                    <>
                        <DialogHeader>
                            <DialogTitle>{product.nombre}</DialogTitle>
                        </DialogHeader>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <div className="flex aspect-square items-center justify-center overflow-hidden rounded-xl bg-slate-100 dark:bg-slate-800">
                                {product.imagen ? (
                                    <img
                                        src={`/storage/${product.imagen}`}
                                        alt={product.nombre}
                                        className="h-full w-full object-contain"
                                    />
                                ) : (
                                    <Package className="h-16 w-16 text-slate-300" />
                                )}
                            </div>
                            <div className="flex flex-col gap-4">
                                <div>
                                    <p className="text-2xl font-bold text-primary">
                                        $
                                        {Number(
                                            product.precio_venta,
                                        ).toLocaleString()}
                                    </p>
                                    <p className="text-sm text-slate-500">
                                        /{' '}
                                        {product.is_service
                                            ? `${product.duracion ?? 0} min`
                                            : product.unidad_medida}
                                    </p>
                                </div>
                                <p className="whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">
                                    {product.descripcion ||
                                        'Sin descripción disponible.'}
                                </p>
                                <div className="mt-auto space-y-3">
                                    <div className="flex items-center justify-between rounded-lg border border-slate-200 p-1 dark:border-slate-700">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Disminuir cantidad"
                                            disabled={quantity <= 1}
                                            onClick={() =>
                                                setQuantity((current) =>
                                                    Math.max(1, current - 1),
                                                )
                                            }
                                        >
                                            <Minus className="h-4 w-4" />
                                        </Button>
                                        <Input
                                            type="number"
                                            min="1"
                                            step="1"
                                            aria-label="Cantidad"
                                            value={quantity}
                                            onChange={(event) => {
                                                const next = Number(
                                                    event.target.value,
                                                );
                                                setQuantity(
                                                    Number.isFinite(next)
                                                        ? Math.max(
                                                              1,
                                                              Math.floor(next),
                                                          )
                                                        : 1,
                                                );
                                            }}
                                            className="w-20 border-0 text-center [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Aumentar cantidad"
                                            onClick={() =>
                                                setQuantity(
                                                    (current) => current + 1,
                                                )
                                            }
                                        >
                                            <Plus className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <Button
                                        type="button"
                                        className="w-full"
                                        onClick={() => onAdd(quantity)}
                                    >
                                        <ShoppingCart className="mr-2 h-4 w-4" />
                                        Añadir al carrito
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
