import { AppContent } from '@/components/app-content';
import { AppRightSidebar } from '@/components/app-right-sidebar';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { FlashMessages } from '@/components/flash-messages';
import { Toaster } from '@/components/ui/sonner';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    viewport = false,
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className={
                    viewport
                        ? 'h-dvh overflow-hidden md:h-[calc(100dvh-1rem)]'
                        : 'overflow-x-hidden'
                }
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
            <AppRightSidebar />
            <Toaster position="top-right" closeButton richColors />

            <FlashMessages />
        </AppShell>
    );
}
