import { Link, usePage, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { BookOpen, Folder, LayoutGrid, Menu, Search, Bell, MessageSquare, ShoppingCart, User as UserIcon } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { cn, toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, NavItem } from '@/types';
import AppLogo from './app-logo';
import AppLogoIcon from './app-logo-icon';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const mainNavItems: NavItem[] = [
    {
        title: 'Panel',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const rightNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

const activeItemStyles =
    'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100';

export function AppHeader({ breadcrumbs = [] }: Props) {
    const page = usePage();
    const { auth } = page.props as any;
    const getInitials = useInitials();
    const { isCurrentUrl, whenCurrentUrl } = useCurrentUrl();
    const pollingRef = useRef<ReturnType<typeof setInterval> | undefined>(undefined);

    useEffect(() => {
        pollingRef.current = setInterval(() => {
            (router.reload as any)({ only: ['auth'] });
        }, 30000);

        return () => {
            if (pollingRef.current) clearInterval(pollingRef.current);
        };
    }, []);

    return (
        <>
            <div className="border-b border-sidebar-border/80">
                <div className="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                    {/* Mobile Menu */}
                    <div className="lg:hidden">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="mr-2 h-[34px] w-[34px]"
                                >
                                    <Menu className="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                className="flex h-full w-64 flex-col items-stretch justify-between bg-sidebar"
                            >
                                <SheetTitle className="sr-only">
                                    Navigation Menu
                                </SheetTitle>
                                <SheetHeader className="flex justify-start text-left">
                                    <AppLogoIcon className="h-6 w-6 fill-current text-black dark:text-white" />
                                </SheetHeader>
                                <div className="flex h-full flex-1 flex-col space-y-4 p-4">
                                    <div className="flex h-full flex-col justify-between text-sm">
                                        <div className="flex flex-col space-y-4">
                                            {mainNavItems.map((item) => (
                                                <Link
                                                    key={item.title}
                                                    href={item.href}
                                                    className="flex items-center space-x-2 font-medium"
                                                >
                                                    {item.icon && (
                                                        <item.icon className="h-5 w-5" />
                                                    )}
                                                    <span>{item.title}</span>
                                                </Link>
                                            ))}
                                        </div>

                                        <div className="flex flex-col space-y-4">
                                            {rightNavItems.map((item) => (
                                                <a
                                                    key={item.title}
                                                    href={toUrl(item.href)}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex items-center space-x-2 font-medium"
                                                >
                                                    {item.icon && (
                                                        <item.icon className="h-5 w-5" />
                                                    )}
                                                    <span>{item.title}</span>
                                                </a>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>

                    <Link
                        href={dashboard()}
                        prefetch
                        className="flex items-center space-x-2"
                    >
                        <AppLogo />
                    </Link>

                    {/* Desktop Navigation */}
                    <div className="ml-6 hidden h-full items-center space-x-6 lg:flex">
                        <NavigationMenu className="flex h-full items-stretch">
                            <NavigationMenuList className="flex h-full items-stretch space-x-2">
                                {mainNavItems.map((item, index) => (
                                    <NavigationMenuItem
                                        key={index}
                                        className="relative flex h-full items-center"
                                    >
                                        <Link
                                            href={item.href}
                                            className={cn(
                                                navigationMenuTriggerStyle(),
                                                whenCurrentUrl(
                                                    item.href,
                                                    activeItemStyles,
                                                ),
                                                'h-9 cursor-pointer px-3',
                                            )}
                                        >
                                            {item.icon && (
                                                <item.icon className="mr-2 h-4 w-4" />
                                            )}
                                            {item.title}
                                        </Link>
                                        {isCurrentUrl(item.href) && (
                                            <div className="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"></div>
                                        )}
                                    </NavigationMenuItem>
                                ))}
                            </NavigationMenuList>
                        </NavigationMenu>
                    </div>

                    <div className="ml-auto flex items-center space-x-2">
                        <div className="relative flex items-center space-x-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="group h-9 w-9 cursor-pointer"
                            >
                                <Search className="!size-5 opacity-80 group-hover:opacity-100" />
                            </Button>
                            <div className="ml-1 hidden gap-1 lg:flex">
                                {rightNavItems.map((item) => (
                                    <TooltipProvider
                                        key={item.title}
                                        delayDuration={0}
                                    >
                                        <Tooltip>
                                            <TooltipTrigger>
                                                <a
                                                    href={toUrl(item.href)}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="group inline-flex h-9 w-9 items-center justify-center rounded-md bg-transparent p-0 text-sm font-medium text-accent-foreground ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                                                >
                                                    <span className="sr-only">
                                                        {item.title}
                                                    </span>
                                                    {item.icon && (
                                                        <item.icon className="size-5 opacity-80 group-hover:opacity-100" />
                                                    )}
                                                </a>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{item.title}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                ))}
                            </div>
                        </div>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="relative size-10 rounded-full"
                                >
                                    <Bell className="size-5" />
                                    {((auth.user.unread_messages || 0) > 0 || (auth.user.pending_orders || 0) > 0 || (auth.user.unread_notifications || 0) > 0) && (
                                        <span className="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white ring-2 ring-background">
                                            {(auth.user.unread_messages || 0) + (auth.user.pending_orders || 0) + (auth.user.unread_notifications || 0)}
                                        </span>
                                    )}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-80 p-0" align="end">
                                <div className="flex items-center justify-between border-b px-3 py-2">
                                    <div className="text-sm font-semibold">Notificaciones</div>
                                    {(auth.user.unread_notifications || 0) > 0 && (
                                        <button 
                                            onClick={() => router.post('/notifications/mark-as-read', {}, { preserveScroll: true })}
                                            className="text-[11px] text-primary hover:underline"
                                        >
                                            Marcar todo como leído
                                        </button>
                                    )}
                                </div>
                                <div className="max-h-[400px] overflow-y-auto">
                                    {(auth.user.recent_notifications && auth.user.recent_notifications.length > 0) ? (
                                        auth.user.recent_notifications.map((notification: any) => (
                                            <div 
                                                key={notification.id} 
                                                className={cn(
                                                    "flex items-start gap-3 border-b p-3 last:border-0 hover:bg-muted/50 transition-colors relative",
                                                    !notification.read_at && "bg-primary/5"
                                                )}
                                            >
                                                {/* Background link for the whole notification */}
                                                <Link 
                                                    href={notification.data.link || '/comunidad'}
                                                    className="absolute inset-0 z-0"
                                                />

                                                <Avatar className="h-8 w-8 shrink-0 relative z-10 pointer-events-none">
                                                    <AvatarImage src={notification.data.user_avatar} />
                                                    <AvatarFallback>
                                                        <UserIcon className="h-4 w-4" />
                                                    </AvatarFallback>
                                                </Avatar>
                                                
                                                <div className="flex-1 space-y-1 relative z-10 pointer-events-none">
                                                    <p className="text-xs text-balance leading-relaxed">
                                                        {notification.data.user_id ? (
                                                            <Link 
                                                                href={`/perfil/${notification.data.user_id}`}
                                                                className="font-semibold hover:text-primary hover:underline pointer-events-auto"
                                                                onClick={(e) => e.stopPropagation()}
                                                            >
                                                                {notification.data.user_name}
                                                            </Link>
                                                        ) : (
                                                            <span className="font-semibold">{notification.data.user_name || 'Usuario'}</span>
                                                        )}
                                                        {" "}{(notification.data.message && notification.data.user_name) 
                                                            ? (notification.data.message.split(notification.data.user_name)[1] || notification.data.message)
                                                            : (notification.data.message || '')}
                                                    </p>
                                                    <p className="text-[10px] text-muted-foreground">
                                                        {format(new Date(notification.created_at), "d 'de' MMM, HH:mm", { locale: es })}
                                                    </p>
                                                </div>
                                                {!notification.read_at && (
                                                    <div className="mt-2 h-2 w-2 rounded-full bg-primary shrink-0 relative z-10" />
                                                )}
                                            </div>
                                        ))
                                    ) : (
                                        <div className="p-8 text-center text-sm text-muted-foreground">
                                            No tienes notificaciones
                                        </div>
                                    )}
                                </div>
                                
                                <div className="border-t p-2">
                                    <Link href="/notifications" className="flex items-center rounded-md p-2 hover:bg-accent hover:text-accent-foreground text-sm mb-1">
                                        <Bell className="mr-2 size-4" />
                                        <span>Ver todas las notificaciones</span>
                                    </Link>
                                    {((auth.user.unread_messages || 0) > 0) && (
                                        <Link href="/mensajes" className="flex items-center rounded-md p-2 hover:bg-accent hover:text-accent-foreground text-sm">
                                            <MessageSquare className="mr-2 size-4 text-violet-500" />
                                            <span>Tienes {auth.user.unread_messages} mensajes</span>
                                        </Link>
                                    )}
                                    {((auth.user.pending_orders || 0) > 0) && (
                                        <Link href="/pedidos-recibidos" className="flex items-center rounded-md p-2 hover:bg-accent hover:text-accent-foreground text-sm">
                                            <ShoppingCart className="mr-2 size-4 text-amber-500" />
                                            <span>Tienes {auth.user.pending_orders} pedidos</span>
                                        </Link>
                                    )}
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="size-10 rounded-full p-1"
                                >
                                    <Avatar className="size-8 overflow-hidden rounded-full">
                                        <AvatarImage
                                            src={auth.user.profile_photo_url}
                                            alt={auth.user.name}
                                        />
                                        <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                            {getInitials(auth.user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56" align="end">
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>
            {breadcrumbs.length > 1 && (
                <div className="flex w-full border-b border-sidebar-border/70">
                    <div className="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}
        </>
    );
}
