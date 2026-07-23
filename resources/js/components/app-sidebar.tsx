import { Link, usePage } from '@inertiajs/react';
import { Settings, User } from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import {
    mainNavItems,
    adminNavItems,
    filterNavItems,
} from '@/config/navigation';
import { dashboard } from '@/routes';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage().props as {
        auth: { user: { permissions?: string[]; is_trial_active?: boolean } };
    };

    const canViewWebConfig = auth.user.permissions?.includes('admin.web-settings.viewAny')
        || auth.user.permissions?.includes('admin.configuracion.viewAny')
        || auth.user.permissions?.some(p => p === '*');

    const rawAllItems = [...mainNavItems, ...adminNavItems()];

    const filteredItems = filterNavItems(rawAllItems, auth.user.permissions);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={filteredItems} />
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild>
                            <Link href="/mi-informacion" prefetch>
                                <User className="size-4" />
                                <span>Mi Información</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    {canViewWebConfig && (
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/configuracion-web" prefetch>
                                    <Settings className="size-4" />
                                    <span>Configuración Web</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    )}
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
