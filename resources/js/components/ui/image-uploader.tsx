import { useRef, useState } from 'react';
import {
    Image as ImageIcon,
    Loader2,
    Plus,
    Upload,
    X,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface ImageUploaderImage {
    id: string;
    preview: string;
    file?: File;
    existingPath?: string;
    existingKey?: string;
}

interface ImageUploaderProps {
    images: ImageUploaderImage[];
    onChange: (images: ImageUploaderImage[]) => void;
    maxImages?: number;
    maxFileSizeMb?: number;
    acceptedMimeTypes?: string[];
    disabled?: boolean;
    isSubmitting?: boolean;
    error?: string | null;
    onError?: (message: string | null) => void;
}

const DEFAULT_ACCEPTED_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
];

const ACCEPTED_EXTENSION_PATTERN = /\.(jpe?g|png|webp)$/i;

function readFileAsDataUrl(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result));
        reader.onerror = () =>
            reject(reader.error ?? new Error('No se pudo leer el archivo.'));
        reader.readAsDataURL(file);
    });
}

function fileFingerprint(file: File): string {
    return `${file.name}|${file.size}|${file.lastModified}`;
}

export function ImageUploader({
    images,
    onChange,
    maxImages = 5,
    maxFileSizeMb = 2,
    acceptedMimeTypes = DEFAULT_ACCEPTED_MIME_TYPES,
    disabled = false,
    isSubmitting = false,
    error = null,
    onError,
}: ImageUploaderProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const nextId = useRef(0);
    const [isDragging, setIsDragging] = useState(false);
    const [isReading, setIsReading] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    const isBusy = isSubmitting || isReading;
    const canAddMore = images.length < maxImages && !disabled && !isSubmitting;

    const reportError = (message: string | null) => {
        setLocalError(message);
        onError?.(message);
    };

    const validateFile = (file: File): string | null => {
        const hasAcceptedMimeType = acceptedMimeTypes.includes(
            file.type.toLowerCase(),
        );
        const hasAcceptedExtension = ACCEPTED_EXTENSION_PATTERN.test(file.name);

        if (!hasAcceptedMimeType && !hasAcceptedExtension) {
            return `"${file.name}" no es una imagen JPG, PNG o WEBP válida.`;
        }

        if (file.size === 0) {
            return `"${file.name}" está vacío.`;
        }

        if (file.size > maxFileSizeMb * 1024 * 1024) {
            return `"${file.name}" supera el máximo de ${maxFileSizeMb} MB.`;
        }

        return null;
    };

    const handleFiles = async (fileList: FileList | null) => {
        if (!fileList || fileList.length === 0) {
            return;
        }

        if (!canAddMore) {
            reportError(`Solo puedes subir hasta ${maxImages} imágenes.`);
            return;
        }

        const incomingFiles = Array.from(fileList);
        const errors: string[] = [];
        const filesToAdd: File[] = [];
        const knownFingerprints = new Set(
            images
                .filter((image) => image.file)
                .map((image) => fileFingerprint(image.file as File)),
        );
        const remainingSlots = maxImages - images.length;

        for (const file of incomingFiles) {
            if (filesToAdd.length >= remainingSlots) {
                errors.push(
                    `Solo puedes subir hasta ${maxImages} imágenes en total.`,
                );
                break;
            }

            const validationError = validateFile(file);
            if (validationError) {
                errors.push(validationError);
                continue;
            }

            const fingerprint = fileFingerprint(file);
            if (knownFingerprints.has(fingerprint)) {
                errors.push(`"${file.name}" ya está seleccionada.`);
                continue;
            }

            knownFingerprints.add(fingerprint);
            filesToAdd.push(file);
        }

        if (filesToAdd.length === 0) {
            reportError(errors.join(' '));
            if (inputRef.current) {
                inputRef.current.value = '';
            }
            return;
        }

        setIsReading(true);

        try {
            const preparedImages = await Promise.all(
                filesToAdd.map(async (file) => ({
                    id: `image-${nextId.current++}`,
                    preview: await readFileAsDataUrl(file),
                    file,
                })),
            );

            onChange([...images, ...preparedImages]);
            reportError(errors.length > 0 ? errors.join(' ') : null);
        } catch {
            reportError(
                'No se pudieron procesar las imágenes seleccionadas. Intenta nuevamente.',
            );
        } finally {
            setIsReading(false);
            if (inputRef.current) {
                inputRef.current.value = '';
            }
        }
    };

    const handleDrop = (event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setIsDragging(false);

        if (!canAddMore) {
            return;
        }

        void handleFiles(event.dataTransfer.files);
    };

    const openFilePicker = () => {
        if (canAddMore) {
            inputRef.current?.click();
        }
    };

    const removeImage = (id: string) => {
        onChange(images.filter((image) => image.id !== id));
        reportError(null);
    };

    const displayedError = error || localError;
    const acceptAttribute = [
        ...acceptedMimeTypes,
        '.jpg',
        '.jpeg',
        '.png',
        '.webp',
    ].join(',');

    return (
        <div className="space-y-3">
            <input
                ref={inputRef}
                type="file"
                accept={acceptAttribute}
                multiple
                className="hidden"
                onChange={(event) => void handleFiles(event.target.files)}
                disabled={!canAddMore}
            />

            <div
                role="button"
                tabIndex={canAddMore ? 0 : -1}
                aria-disabled={!canAddMore}
                onClick={openFilePicker}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        openFilePicker();
                    }
                }}
                onDragOver={(event) => {
                    if (!canAddMore) {
                        return;
                    }

                    event.preventDefault();
                    setIsDragging(true);
                }}
                onDragLeave={() => setIsDragging(false)}
                onDrop={handleDrop}
                className={cn(
                    'cursor-pointer rounded-2xl border-2 border-dashed p-4 text-center transition-colors outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30',
                    isDragging
                        ? 'border-primary bg-primary/5'
                        : 'border-muted-foreground/20 bg-muted/10 hover:border-muted-foreground/40',
                    !canAddMore && 'cursor-not-allowed opacity-60',
                )}
            >
                <div className="flex flex-col items-center gap-2">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-background">
                        {isBusy ? (
                            <Loader2 className="h-5 w-5 animate-spin text-primary" />
                        ) : (
                            <Upload className="h-5 w-5 text-muted-foreground" />
                        )}
                    </div>
                    <div className="text-xs font-semibold">
                        {isReading
                            ? 'Procesando imágenes...'
                            : isSubmitting
                              ? 'Subiendo imágenes...'
                              : canAddMore
                                ? 'Haz clic o arrastra tus fotografías aquí'
                                : `Límite de ${maxImages} imágenes alcanzado`}
                    </div>
                    <p className="text-[11px] text-muted-foreground">
                        JPG, JPEG, PNG o WEBP · Máx. {maxFileSizeMb} MB por
                        imagen
                    </p>
                </div>
            </div>

            {images.length > 0 && (
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {images.map((image, index) => (
                        <div
                            key={image.id}
                            className="group relative aspect-square overflow-hidden rounded-2xl border border-muted-foreground/10 bg-muted/10"
                        >
                            <img
                                src={image.preview}
                                alt={`Imagen ${index + 1} del producto`}
                                className="h-full w-full object-cover"
                            />

                            <div className="absolute inset-x-0 top-0 flex items-start justify-between gap-1 p-1.5">
                                <span
                                    className={cn(
                                        'rounded-full px-2 py-0.5 text-[8px] font-black tracking-wide uppercase',
                                        index === 0
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-background/90 text-foreground',
                                    )}
                                >
                                    {index === 0
                                        ? 'Principal'
                                        : image.file
                                          ? 'Nueva'
                                          : 'Guardada'}
                                </span>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="icon-xs"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        removeImage(image.id);
                                    }}
                                    disabled={isBusy}
                                    aria-label={`Eliminar imagen ${index + 1}`}
                                >
                                    <X className="h-3 w-3" />
                                </Button>
                            </div>

                            {isSubmitting && (
                                <div className="absolute inset-0 flex items-center justify-center bg-black/40">
                                    <Loader2 className="h-5 w-5 animate-spin text-white" />
                                </div>
                            )}
                        </div>
                    ))}

                    {canAddMore && (
                        <button
                            type="button"
                            onClick={openFilePicker}
                            className="flex aspect-square flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-muted-foreground/20 bg-muted/10 text-muted-foreground transition-colors hover:border-muted-foreground/40 hover:text-foreground"
                        >
                            <Plus className="h-5 w-5" />
                            <span className="text-[10px] font-bold uppercase">
                                Agregar
                            </span>
                        </button>
                    )}
                </div>
            )}

            {displayedError && (
                <p
                    role="alert"
                    className="rounded-lg border border-destructive/20 bg-destructive/5 px-3 py-2 text-xs font-medium text-destructive"
                >
                    {displayedError}
                </p>
            )}

            <div className="flex flex-wrap items-center justify-between gap-2 text-[10px] font-medium text-muted-foreground">
                <span className="flex items-center gap-1">
                    <ImageIcon className="h-3 w-3" />
                    La primera imagen será la portada del producto.
                </span>
                <span>
                    {images.length} de {maxImages} imágenes
                </span>
            </div>
        </div>
    );
}
