import * as React from 'react';
import { SidebarInset } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

type Props = React.ComponentProps<'main'> & {
    variant?: 'header' | 'sidebar';
};

export function AppContent({
    variant = 'header',
    children,
    className,
    ...props
}: Props) {
    if (variant === 'sidebar') {
        return (
            <SidebarInset
                {...props}
                className={cn('h-full overflow-x-hidden', className)}
            >
                {children}
            </SidebarInset>
        );
    }

    return (
        <main
            className={cn(
                'mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 overflow-x-hidden rounded-xl',
                className,
            )}
            {...props}
        >
            {children}
        </main>
    );
}
