import { Camera, ScanLine } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { Html5Qrcode } from 'html5-qrcode';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ScannerStatus = 'starting' | 'ready' | 'error';

interface BarcodeScannerDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDetected: (code: string) => void;
    title?: string;
    description?: string;
}

function getCameraErrorMessage(error: unknown): string {
    const errorName = error instanceof DOMException ? error.name : '';
    const message = error instanceof Error ? error.message : String(error);
    const detail = `${errorName} ${message}`;

    if (/NotAllowedError|Permission|denied/i.test(detail)) {
        return 'Se denegó el permiso para usar la cámara. Habilítalo en la configuración del navegador e inténtalo nuevamente.';
    }

    if (
        /NotFoundError|OverconstrainedError|device not found|camera.*not found/i.test(
            detail,
        )
    ) {
        return 'No se encontró una cámara disponible en este dispositivo.';
    }

    if (/NotReadableError/i.test(detail)) {
        return 'No se pudo abrir la cámara porque está ocupada por otra aplicación. Ciérrala e inténtalo nuevamente.';
    }

    if (!window.isSecureContext) {
        return 'El acceso a la cámara requiere HTTPS o un entorno local seguro como localhost.';
    }

    return `No se pudo iniciar la cámara. ${message}`;
}

export function BarcodeScannerDialog({
    open,
    onOpenChange,
    onDetected,
    title = 'Escanear código de barras',
    description = 'Centra el código de barras o QR dentro del recuadro.',
}: BarcodeScannerDialogProps) {
    const elementId = `barcode-scanner-${useId().replace(/[^a-zA-Z0-9_-]/g, '')}`;
    const [status, setStatus] = useState<ScannerStatus>('starting');
    const [error, setError] = useState<string | null>(null);
    const scannerRef = useRef<Html5Qrcode | null>(null);
    const scanHandledRef = useRef(false);
    const onDetectedRef = useRef(onDetected);
    const onOpenChangeRef = useRef(onOpenChange);

    useEffect(() => {
        onDetectedRef.current = onDetected;
    }, [onDetected]);

    useEffect(() => {
        onOpenChangeRef.current = onOpenChange;
    }, [onOpenChange]);

    useEffect(() => {
        if (!open) {
            return;
        }

        scanHandledRef.current = false;
        setStatus('starting');
        setError(null);

        let disposed = false;
        let scanner: Html5Qrcode | null = null;
        let startPromise: Promise<null> | null = null;

        const iniciarEscaner = async () => {
            if (!window.isSecureContext) {
                setError(
                    'El acceso a la cámara requiere HTTPS o un entorno local seguro como localhost.',
                );
                setStatus('error');
                return;
            }

            if (!navigator.mediaDevices?.getUserMedia) {
                setError(
                    'Este navegador no permite acceder a la cámara. Prueba con un navegador actualizado en el dispositivo.',
                );
                setStatus('error');
                return;
            }

            try {
                const { Html5Qrcode, Html5QrcodeSupportedFormats } =
                    await import('html5-qrcode');

                if (disposed) {
                    return;
                }

                scanner = new Html5Qrcode(elementId, {
                    formatsToSupport: [
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.CODE_93,
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.EAN_8,
                        Html5QrcodeSupportedFormats.UPC_A,
                        Html5QrcodeSupportedFormats.UPC_E,
                        Html5QrcodeSupportedFormats.ITF,
                        Html5QrcodeSupportedFormats.CODABAR,
                        Html5QrcodeSupportedFormats.QR_CODE,
                        Html5QrcodeSupportedFormats.DATA_MATRIX,
                    ],
                    verbose: false,
                });
                scannerRef.current = scanner;

                startPromise = scanner.start(
                    { facingMode: 'environment' },
                    {
                        fps: 10,
                        qrbox: { width: 260, height: 160 },
                        aspectRatio: 16 / 9,
                    },
                    (codigoDetectado) => {
                        if (disposed || scanHandledRef.current) {
                            return;
                        }

                        scanHandledRef.current = true;
                        const codigo = codigoDetectado.trim();

                        void (async () => {
                            try {
                                if (scanner?.isScanning) {
                                    await scanner.stop();
                                }
                            } catch (stopError) {
                                console.error(
                                    'No se pudo detener el escáner después de leer el código.',
                                    stopError,
                                );
                            } finally {
                                if (!disposed) {
                                    scannerRef.current = null;
                                    navigator.vibrate?.(80);
                                    onOpenChangeRef.current(false);

                                    if (codigo !== '') {
                                        onDetectedRef.current(codigo);
                                    }
                                }
                            }
                        })();
                    },
                    () => {},
                );

                await startPromise;

                if (disposed) {
                    if (scanner.isScanning) {
                        await scanner.stop();
                    }

                    return;
                }

                setStatus('ready');
            } catch (startError) {
                if (disposed) {
                    return;
                }

                setError(getCameraErrorMessage(startError));
                setStatus('error');
            }
        };

        void iniciarEscaner();

        return () => {
            disposed = true;
            const scannerToStop = scanner;

            if (scannerRef.current === scannerToStop) {
                scannerRef.current = null;
            }

            void (async () => {
                try {
                    await startPromise?.catch(() => null);

                    if (scannerToStop?.isScanning) {
                        await scannerToStop.stop();
                    }
                } catch (stopError) {
                    console.error(
                        'No se pudo cerrar el escáner de cámara.',
                        stopError,
                    );
                }
            })();
        };
    }, [open, elementId]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                aria-describedby={undefined}
                className="w-[95vw] max-w-lg gap-4 p-4 sm:p-6"
            >
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Camera className="h-5 w-5" />
                        {title}
                    </DialogTitle>
                </DialogHeader>

                <div
                    id={elementId}
                    className="min-h-64 w-full overflow-hidden rounded-xl bg-black [&_video]:h-full [&_video]:w-full [&_video]:object-cover"
                />

                {error ? (
                    <div
                        role="alert"
                        className="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive"
                    >
                        {error}
                    </div>
                ) : (
                    <p
                        className="flex items-center justify-center gap-2 text-center text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        <ScanLine className="h-4 w-4" />
                        {status === 'starting'
                            ? 'Iniciando cámara…'
                            : description}
                    </p>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        className="w-full sm:w-auto"
                        onClick={() => onOpenChange(false)}
                    >
                        Cerrar cámara
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
