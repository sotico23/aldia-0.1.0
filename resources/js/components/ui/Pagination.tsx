import { Link } from '@inertiajs/react';
import { sanitize } from '@/lib/sanitize';

interface PaginationProps {
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    meta?: {
        from: number;
        to: number;
        total: number;
    };
}

export default function Pagination({ links, meta }: PaginationProps) {
    if (links.length <= 3) return null;

    return (
        <div className="flex flex-col items-center justify-center gap-4 py-3 mt-4 border-t md:flex-row">
            {meta && (
                <div className="text-xs text-muted-foreground order-2 md:order-1">
                    <span className="font-medium text-foreground">{meta.from}</span>-
                    <span className="font-medium text-foreground">{meta.to}</span> de{' '}
                    <span className="font-medium text-foreground">{meta.total}</span>
                </div>
            )}
            <nav className="flex items-center gap-1 order-1 md:order-2" aria-label="Paginación">
                {links.map((link, key) => (
                    link.url === null ? (
                        <div
                            key={key}
                            className="px-2.5 py-1 text-xs text-muted-foreground/50 border rounded-md bg-muted/30 dark:border-gray-800"
                            style={{ minWidth: '32px', textAlign: 'center' }}
                            dangerouslySetInnerHTML={{ __html: sanitize(link.label) }}
                        />
                    ) : (
                        <Link
                            key={key}
                            className={`px-2.5 py-1 text-xs border rounded-md transition-all hover:bg-accent hover:text-accent-foreground focus:outline-none focus:ring-1 focus:ring-ring dark:border-gray-800 ${
                                link.active 
                                    ? 'bg-primary text-primary-foreground border-primary font-medium pointer-events-none' 
                                    : 'bg-background text-foreground'
                            }`}
                            style={{ minWidth: '32px', textAlign: 'center' }}
                            href={link.url}
                            preserveState
                            preserveScroll
                            dangerouslySetInnerHTML={{ __html: sanitize(link.label) }}
                        />
                    )
                ))}
            </nav>
        </div>
    );
}
