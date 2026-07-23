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
    Loader2
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
    Building2
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
            const response = await axios.get(`/api/global-search?q=${searchQuery}`);
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
                className="flex items-center gap-2 rounded-xl bg-muted/50 px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted transition-all border border-transparent hover:border-border group"
            >
                <Search className="h-4 w-4 group-hover:text-primary transition-colors" />
                <span className="hidden md:inline-block">Buscar...</span>
                <kbd className="pointer-events-none hidden h-5 select-none items-center gap-1 rounded border bg-background px-1.5 font-mono text-[10px] font-medium opacity-100 md:flex">
                    <span className="text-xs">⌘</span>K
                </kbd>
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="p-0 border-none shadow-2xl max-w-xl bg-background/95 backdrop-blur-xl">
                    <DialogHeader className="p-4 border-b">
                        <DialogTitle className="sr-only">Búsqueda Global</DialogTitle>
                        <div className="flex items-center gap-3">
                            <Search className="h-5 w-5 text-primary" />
                            <Input
                                autoFocus
                                placeholder="Busca facturas, clientes, productos o módulos..."
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                className="border-none focus-visible:ring-0 text-lg h-10 bg-transparent placeholder:text-muted-foreground/50"
                            />
                            {isLoading && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
                        </div>
                    </DialogHeader>
                    <div className="max-h-[400px] overflow-y-auto p-2 scrollbar-thin">
                        {results.length > 0 ? (
                            <div className="grid gap-1">
                                {results.map((result, idx) => {
                                    const IconComponent = iconMap[result.icon] || FileText;
                                    return (
                                        <button
                                            key={`${result.href}-${idx}`}
                                            onClick={() => navigate(result.href)}
                                            className="flex items-center gap-3 w-full p-3 rounded-xl hover:bg-primary/10 transition-all text-left group"
                                        >
                                            <div className="p-2.5 rounded-lg bg-muted group-hover:bg-primary group-hover:text-white transition-all transform group-hover:scale-110">
                                                <IconComponent className="h-4 w-4" />
                                            </div>
                                            <div className="flex flex-col">
                                                <span className="font-semibold text-sm leading-tight text-foreground group-hover:text-primary transition-colors">
                                                    {result.name}
                                                </span>
                                                <div className="flex items-center gap-2 mt-0.5">
                                                    <span className="text-[10px] uppercase tracking-wider font-bold text-muted-foreground/60 bg-muted px-1.5 py-0.5 rounded leading-none group-hover:bg-primary/20 group-hover:text-primary transition-colors">
                                                        {result.type}
                                                    </span>
                                                    {result.subtitle && (
                                                        <span className="text-xs text-muted-foreground line-clamp-1">
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
                                <div className="flex justify-center mb-4">
                                    <div className="bg-muted p-4 rounded-full">
                                        <Search className="h-8 w-8 opacity-20" />
                                    </div>
                                </div>
                                <p className="text-sm font-medium">No se encontraron resultados para "{query}"</p>
                                <p className="text-xs opacity-60">Intenta con términos más generales como "factura", "cliente" o "pos".</p>
                            </div>
                        ) : (
                            <div className="p-12 text-center text-muted-foreground opacity-50">
                                <p className="text-sm">Empieza a escribir para buscar en todo el sistema...</p>
                            </div>
                        )}
                    </div>
                    <div className="p-3 border-t bg-muted/50 text-[10px] text-muted-foreground flex justify-between px-4">
                        <div className="flex gap-4">
                            <span className="flex items-center gap-1"><kbd className="bg-background px-1 rounded border">⏎</kbd> seleccionar</span>
                            <span className="flex items-center gap-1"><kbd className="bg-background px-1 rounded border">↑↓</kbd> navegar</span>
                        </div>
                        <span>ESC para cerrar</span>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
