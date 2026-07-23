import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import type { CSSProperties } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

const groupColors: Record<string, string> = {
    COMERCIAL: '#3b82f6',
    OPERACIONES: '#f97316',
    FACTURACIÓN: '#f43f5e',
    FINANZAS: '#6366f1',
    RRHH: '#ec4899',
    PROYECTOS: '#14b8a6',
    LOGÍSTICA: '#06b6d4',
    TIENDA: '#10b981',
    SERVICIOS: '#8b5cf6',
    EDUCACIÓN: '#a855f7',
    MARKETING: '#d946ef',
    SISTEMA: '#6b7280',
};

function hasActiveDescendant(items: NavItem[], isCurrentUrl: (href: string) => boolean): boolean {
    return items.some(sub => {
        const href = typeof sub.href === 'string' ? sub.href : sub.href?.url;
        if (href && isCurrentUrl(href)) return true;
        if (sub.items) return hasActiveDescendant(sub.items, isCurrentUrl);
        return false;
    });
}

function NavSubItem({ item, isCurrentUrl, depth }: { item: NavItem; isCurrentUrl: (href: string) => boolean; depth: number }) {
    const hasChildren = item.items && item.items.length > 0;
    const href = typeof item.href === 'string' ? item.href : item.href?.url;
    const isActive = !!(href && isCurrentUrl(href));
    const hasActiveChild = hasChildren && hasActiveDescendant(item.items!, isCurrentUrl);

    if (!hasChildren) {
        return (
            <SidebarMenuSubItem key={item.title}>
                <SidebarMenuSubButton
                    asChild
                    isActive={isActive}
                    className={`transition-colors duration-200 ${isActive ? 'bg-primary/5 text-primary font-bold' : 'text-muted-foreground'}`}
                >
                    <Link href={(item.href as any)} prefetch className="flex items-center gap-2">
                        {isActive && <div className="size-1.5 rounded-full bg-primary animate-pulse" />}
                        <span>{item.title}</span>
                    </Link>
                </SidebarMenuSubButton>
            </SidebarMenuSubItem>
        );
    }

    return (
        <Collapsible
            key={item.title}
            asChild
            defaultOpen={hasActiveChild}
            className="group/collapsible"
        >
            <SidebarMenuSubItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuSubButton
                        className={`transition-colors duration-200 ${hasActiveChild ? 'bg-primary/5 text-primary font-semibold' : 'text-muted-foreground'}`}
                    >
                        <span className="flex-1">{item.title}</span>
                        <ChevronDown className="h-3 w-3 transition-transform group-data-[state=open]/collapsible:rotate-180" />
                    </SidebarMenuSubButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub className="ml-0 border-l border-muted/20 pl-2 mt-0.5 space-y-0.5">
                        {item.items!.map(sub => (
                            <NavSubItem key={sub.title} item={sub} isCurrentUrl={isCurrentUrl} depth={depth + 1} />
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuSubItem>
        </Collapsible>
    );
}

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    const groupedItems = items.reduce((acc, item) => {
        const groupName = item.group || 'GENERAL';
        if (!acc[groupName]) acc[groupName] = [];
        acc[groupName].push(item);
        return acc;
    }, {} as Record<string, NavItem[]>);

    return (
        <div className="space-y-4">
            {Object.entries(groupedItems).map(([groupName, groupItems]) => {
                const groupColor = groupColors[groupName];
                const style = groupColor ? { '--sidebar-accent': `${groupColor}1a` } as CSSProperties : undefined;

                return (
                <SidebarGroup key={groupName} className="px-2 py-0" style={style}>
                    <SidebarGroupLabel className="text-[10px] font-black uppercase tracking-widest opacity-50 px-2 py-3">
                        {groupName}
                    </SidebarGroupLabel>
                    <SidebarMenu>
                        {groupItems.map((item) => {
                            const isParentActive = hasActiveDescendant(item.items || [], isCurrentUrl) || !!(item.href && isCurrentUrl(item.href));

                            if (!item.items || item.items.length === 0) {
                                return (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            tooltip={{ children: item.title }}
                                            isActive={isCurrentUrl(item.href)}
                                            asChild
                                            className={`relative overflow-hidden transition-all duration-300 ${isCurrentUrl(item.href) ? 'bg-primary/10 font-bold text-primary shadow-sm' : ''}`}
                                        >
                                            <Link href={item.href} prefetch className="flex items-center gap-3">
                                                {isCurrentUrl(item.href) && (
                                                    <div className="absolute left-0 top-1/2 -translate-y-1/2 h-6 w-1 rounded-r-full bg-primary animate-in slide-in-from-left duration-300" />
                                                )}
                                                {item.icon && <item.icon className={`size-4 ${isCurrentUrl(item.href) ? 'text-primary' : ''}`} />}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            }

                            return (
                                <Collapsible
                                    key={item.title}
                                    asChild
                                    defaultOpen={isParentActive}
                                    className="group/collapsible"
                                >
                                    <SidebarMenuItem>
                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuButton
                                                tooltip={{ children: item.title }}
                                                className={`relative transition-all duration-300 ${isParentActive ? 'bg-muted/30 font-semibold' : ''}`}
                                            >
                                                {isParentActive && (
                                                    <div className="absolute left-0 top-1/2 -translate-y-1/2 h-5 w-1 rounded-r-full bg-primary/40 animate-in slide-in-from-left duration-300" />
                                                )}
                                                {item.icon && <item.icon className="size-4" />}
                                                <span>{item.title}</span>
                                                <ChevronDown className="ml-auto h-4 w-4 transition-transform group-data-[state=open]/collapsible:rotate-180" />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarMenuSub className="ml-3 border-l-2 border-muted/30 pl-2 mt-1 space-y-1">
                                                {item.items.map((subItem) => (
                                                    <NavSubItem key={subItem.title} item={subItem} isCurrentUrl={isCurrentUrl} depth={1} />
                                                ))}
                                            </SidebarMenuSub>
                                        </CollapsibleContent>
                                    </SidebarMenuItem>
                                </Collapsible>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
                );
            })}
        </div>
    );
}
