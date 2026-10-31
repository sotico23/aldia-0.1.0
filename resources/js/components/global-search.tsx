import { router } from '@inertiajs/react';
import axios from 'axios';
import {
    Search,
    LayoutDashboard,
    Users,
    Tags,
    FileText,
    ShoppingCart,
    Truck,
    UserCheck,
    Car,
    Briefcase,
    Package,
    User,
    BadgeDollarSign,
    ReceiptText,
    BarChart3,
    Building2,
    Loader2,
} from 'lucide-react';
import { useState, useEffect, useCallback } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type SearchResult = {
    name: string;
    type: string;
    href: string;
    icon: string;
    subtitle?: string;
};

const iconMap: Record<string, any> = {
    LayoutDashboard,
    Users,
    Tags,
    FileText,
    ShoppingCart,
    Truck,
    UserCheck,
    Car,
    Briefcase,
    Package,
    User,
    BadgeDollarSign,
    ReceiptText,
    BarChart3,
    Building2,
};

export function GlobalSearch() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        const down = (e: KeyboardEvent) => {
            if (e.key === 'k' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                setOpen((open) => !open);
            }
        };

        document.addEventListener('keydown', down);
        return () => document.removeEventListener('keydown', down);
    }, []);

    const fetchResults = useCallback(async (searchQuery: string) => {
        if (searchQuery.length < 2) {
            setResults([]);
            return;
        }

        setIsLoading(true);
        try {
            const response = await axios.get(
                `/api/global-search?q=${searchQuery}`,
            );
            setResults(response.data);
        } catch (error) {
            console.error('Error in global search:', error);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (query) {
                fetchResults(query);
            } else {
                setResults([]);
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [query, fetchResults]);

    const navigate = (href: string) => {
        setOpen(false);
        setQuery('');
        router.visit(href);
    };

    return (
        <>
            <button
                onClick={() => setOpen(true)}
                className="group flex items-center gap-2 rounded-xl border border-transparent bg-muted/50 px-3 py-1.5 text-sm text-muted-foreground transition-all hover:border-border hover:bg-muted"
            >
                <Search className="h-4 w-4 transition-colors group-hover:text-primary" />
                <span className="hidden md:inline-block">Buscar...</span>
                <kbd className="pointer-events-none hidden h-5 items-center gap-1 rounded border bg-background px-1.5 font-mono text-[10px] font-medium opacity-100 select-none md:flex">
                    <span className="text-xs">⌘</span>K
                </kbd>
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-xl border-none bg-background/95 p-0 shadow-2xl backdrop-blur-xl">
                    <DialogHeader className="border-b p-4">
                        <DialogTitle className="sr-only">
                            Búsqueda Global
                        </DialogTitle>
                        <div className="flex items-center gap-3">
                            <Search className="h-5 w-5 text-primary" />
                            <Input
                                autoFocus
                                placeholder="Busca facturas, clientes, productos o módulos..."
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                className="h-10 border-none bg-transparent text-lg placeholder:text-muted-foreground/50 focus-visible:ring-0"
                            />
                            {isLoading && (
                                <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                            )}
                        </div>
                    </DialogHeader>
                    <div className="max-h-[400px] scrollbar-thin overflow-y-auto p-2">
                        {results.length > 0 ? (
                            <div className="grid gap-1">
                                {results.map((result, idx) => {
                                    const IconComponent =
                                        iconMap[result.icon] || FileText;
                                    return (
                                        <button
                                            key={`${result.href}-${idx}`}
                                            onClick={() =>
                                                navigate(result.href)
                                            }
                                            className="group flex w-full items-center gap-3 rounded-xl p-3 text-left transition-all hover:bg-primary/10"
                                        >
                                            <div className="transform rounded-lg bg-muted p-2.5 transition-all group-hover:scale-110 group-hover:bg-primary group-hover:text-white">
                                                <IconComponent className="h-4 w-4" />
                                            </div>
                                            <div className="flex flex-col">
                                                <span className="text-sm leading-tight font-semibold text-foreground transition-colors group-hover:text-primary">
                                                    {result.name}
                                                </span>
                                                <div className="mt-0.5 flex items-center gap-2">
                                                    <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] leading-none font-bold tracking-wider text-muted-foreground/60 uppercase transition-colors group-hover:bg-primary/20 group-hover:text-primary">
                                                        {result.type}
                                                    </span>
                                                    {result.subtitle && (
                                                        <span className="line-clamp-1 text-xs text-muted-foreground">
                                                            • {result.subtitle}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        ) : query.length >= 2 ? (
                            <div className="p-12 text-center text-muted-foreground">
                                <div className="mb-4 flex justify-center">
                                    <div className="rounded-full bg-muted p-4">
                                        <Search className="h-8 w-8 opacity-20" />
                                    </div>
                                </div>
                                <p className="text-sm font-medium">
                                    No se encontraron resultados para "{query}"
                                </p>
                                <p className="text-xs opacity-60">
                                    Intenta con términos más generales como
                                    "factura", "cliente" o "pos".
                                </p>
                            </div>
                        ) : (
                            <div className="p-12 text-center text-muted-foreground opacity-50">
                                <p className="text-sm">
                                    Empieza a escribir para buscar en todo el
                                    sistema...
                                </p>
                            </div>
                        )}
                    </div>
                    <div className="flex justify-between border-t bg-muted/50 p-3 px-4 text-[10px] text-muted-foreground">
                        <div className="flex gap-4">
                            <span className="flex items-center gap-1">
                                <kbd className="rounded border bg-background px-1">
                                    ⏎
                                </kbd>{' '}
                                seleccionar
                            </span>
                            <span className="flex items-center gap-1">
                                <kbd className="rounded border bg-background px-1">
                                    ↑↓
                                </kbd>{' '}
                                navegar
                            </span>
                        </div>
                        <span>ESC para cerrar</span>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
