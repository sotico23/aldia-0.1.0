import { Breadcrumbs } from '@/components/breadcrumbs';
import { GlobalSearch } from '@/components/global-search';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex min-h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-3 py-2 md:px-4">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex shrink-0 items-center gap-1 sm:gap-2">
                <GlobalSearch />
                <SidebarTrigger side="right" className="xl:hidden" />
            </div>
        </header>
    );
}
