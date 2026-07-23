import { useState, useRef, useEffect } from 'react';
import { Upload, X, Image as ImageIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface ImageUploaderProps {
    images?: string[];
    files?: File[];
    onImagesChange?: (images: string[]) => void;
    onFilesChange?: (files: File[]) => void;
    maxImages?: number;
    minImages?: number;
}

export function ImageUploader({ 
    images = [], 
    files = [],
    onImagesChange, 
    onFilesChange,
    maxImages = 6, 
    minImages = 1 
}: ImageUploaderProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [filePreviews, setFilePreviews] = useState<string[]>([]);

    useEffect(() => {
        if (files.length === 0) {
            setFilePreviews([]);
            return;
        }
        let cancelled = false;
        Promise.all(
            files.map(
                (file) =>
                    new Promise<string>((resolve) => {
                        const reader = new FileReader();
                        reader.onloadend = () => resolve(reader.result as string);
                        reader.readAsDataURL(file);
                    }),
            ),
        ).then((urls) => {
            if (!cancelled) setFilePreviews(urls);
        });
        return () => { cancelled = true; };
    }, [files]);

    const previewUrls = images.length > 0 ? images : filePreviews;

    const totalItems = images.length + files.length;

    const handleFileChange = async (newFiles: FileList | null) => {
        if (!newFiles) return;
        
        const remainingSlots = maxImages - totalItems;
        const filesToAdd = Array.from(newFiles).slice(0, remainingSlots);
        
        if (onFilesChange) {
            onFilesChange([...files, ...filesToAdd]);
        }
        
        if (onImagesChange) {
            const newUrls = await Promise.all(
                filesToAdd.map(
                    (file) =>
                        new Promise<string>((resolve) => {
                            const reader = new FileReader();
                            reader.onloadend = () => resolve(reader.result as string);
                            reader.readAsDataURL(file);
                        }),
                ),
            );
            onImagesChange([...images, ...newUrls]);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);
        handleFileChange(e.dataTransfer.files);
    };

    const removeImage = (index: number) => {
        if (index < images.length) {
            const newImages = [...images];
            newImages.splice(index, 1);
            onImagesChange?.(newImages);
        } else {
            const fileIndex = index - images.length;
            const newFiles = [...files];
            newFiles.splice(fileIndex, 1);
            onFilesChange?.(newFiles);
        }
    };

    return (
        <div className="space-y-4">
            <div
                className={cn(
                    "border-2 border-dashed rounded-xl p-6 text-center transition-colors cursor-pointer",
                    isDragging 
                        ? "border-blue-500 bg-blue-50" 
                        : "border-zinc-300 dark:border-zinc-700 hover:border-zinc-400",
                    totalItems >= maxImages && "opacity-50 cursor-not-allowed"
                )}
                onDragOver={(e) => { e.preventDefault(); setIsDragging(true); }}
                onDragLeave={() => setIsDragging(false)}
                onDrop={handleDrop}
                onClick={() => totalItems < maxImages && inputRef.current?.click()}
            >
                <input
                    ref={inputRef}
                    type="file"
                    accept="image/*"
                    multiple
                    className="hidden"
                    onChange={(e) => handleFileChange(e.target.files)}
                    disabled={totalItems >= maxImages}
                />
                
                <div className="flex flex-col items-center gap-2">
                    <div className="h-12 w-12 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                        <Upload className="h-6 w-6 text-zinc-500" />
                    </div>
                    <div className="text-sm">
                        <span className="font-medium text-blue-600">Haz clic para subir</span>
                        {" "}o arrastra y suelta
                    </div>
                    <p className="text-xs text-zinc-500">
                        PNG, JPG, GIF hasta 10MB • {minImages}-{maxImages} imágenes
                    </p>
                </div>
            </div>

            {previewUrls.length > 0 && (
                <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-4">
                    {previewUrls.map((url, index) => (
                        <div 
                            key={index} 
                            className="relative aspect-square rounded-lg overflow-hidden group border"
                        >
                            <img 
                                src={url} 
                                alt={`Imagen ${index + 1}`} 
                                className="w-full h-full object-cover"
                            />
                            <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <Button
                                    variant="destructive"
                                    size="icon"
                                    className="h-8 w-8"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        removeImage(index);
                                    }}
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            </div>
                            {index === 0 && (
                                <div className="absolute top-1 left-1 bg-blue-500 text-white text-xs px-2 py-0.5 rounded">
                                    Principal
                                </div>
                            )}
                        </div>
                    ))}
                    
                    {totalItems < maxImages && (
                        <div 
                            className="relative aspect-square rounded-lg border-2 border-dashed border-zinc-300 dark:border-zinc-700 flex items-center justify-center cursor-pointer hover:border-zinc-400"
                            onClick={() => inputRef.current?.click()}
                        >
                            <ImageIcon className="h-8 w-8 text-zinc-400" />
                            <input
                                ref={inputRef}
                                type="file"
                                accept="image/*"
                                multiple
                                className="hidden"
                                onChange={(e) => handleFileChange(e.target.files)}
                            />
                        </div>
                    )}
                </div>
            )}

            {totalItems < minImages && (
                <p className="text-sm text-red-500">
                    Sube al menos {minImages} imagen{minImages > 1 ? 'es' : ''}
                </p>
            )}
        </div>
    );
}