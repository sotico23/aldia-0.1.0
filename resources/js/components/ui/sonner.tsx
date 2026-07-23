'use client';

import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner } from 'sonner';

type ToasterProps = React.ComponentProps<typeof Sonner>;

const Toaster = ({ ...props }: ToasterProps) => {
    const { appearance } = useAppearance();

    return (
        <Sonner
            theme={appearance as ToasterProps['theme']}
            className="toaster group"
            toastOptions={{
                classNames: {
                    toast: 'group toast group-[.toaster]:bg-background group-[.toaster]:text-foreground group-[.toaster]:border-border group-[.toaster]:shadow-lg',
                    description: 'group-[.toast]:text-muted-foreground',
                    actionButton:
                        'group-[.toast]:bg-primary group-[.toast]:text-primary-foreground',
                    cancelButton:
                        'group-[.toast]:bg-muted group-[.toast]:text-muted-foreground',
                    success: 'group-[.toast]:bg-green-600 group-[.toast]:text-white',
                    error: 'group-[.toast]:bg-red-600 group-[.toast]:text-white',
                    info: 'group-[.toast]:bg-blue-600 group-[.toast]:text-white',
                    warning: 'group-[.toast]:bg-yellow-600 group-[.toast]:text-white',
                },
            }}
            {...props}
        />
    );
};

export { Toaster };
