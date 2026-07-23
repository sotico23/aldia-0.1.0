import { MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { getWhatsAppLink } from '@/lib/whatsapp-utils';

interface WhatsAppButtonProps {
    phone: string | null | undefined;
    nombre?: string;
    className?: string;
    appName?: string;
}

export function WhatsAppButton({
    phone,
    nombre,
    className,
    appName = 'AlDia', // ✔️ valor real por defecto
}: WhatsAppButtonProps) {
    if (!phone) return null;

    const message = nombre
        ? `Hola ${nombre}, te contacto desde ${appName} y quiero más información sobre tus productos`
        : `Hola, te contacto desde ${appName}`;

    const link = getWhatsAppLink(phone, message);

    return (
        <Button
            variant="ghost"
            size="icon"
            className={`h-8 w-8 text-green-600 hover:bg-green-50 hover:text-green-700 ${className ?? ''}`}
            onClick={() => window.open(link, '_blank')}
            title="Contactar por WhatsApp"
        >
            <MessageCircle className="h-4 w-4" />
        </Button>
    );
}