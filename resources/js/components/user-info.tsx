import { Link, router } from '@inertiajs/react';
import { ShoppingBag, Store } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const getInitials = useInitials();
    const publicProfile = user.public_profile;
    const hasPublicProfile =
        publicProfile && (publicProfile.slug || publicProfile.title);
    const isActive = publicProfile?.is_active ?? false;

    function handleToggle(e: React.MouseEvent) {
        e.preventDefault();
        e.stopPropagation();
        router.post('/settings/public-profile/toggle-active');
    }

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                <AvatarImage src={user.profile_photo_url} alt={user.name} />
                <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {showEmail && (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                )}
                {hasPublicProfile && (
                    <div className="mt-1 flex items-center gap-2">
                        <button
                            onClick={handleToggle}
                            className="flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <Store className="h-3 w-3" />
                            {isActive ? (
                                <span className="text-green-600 dark:text-green-400">
                                    Tienda activa
                                </span>
                            ) : (
                                <span>Activar tienda</span>
                            )}
                        </button>
                        {isActive && publicProfile?.slug && (
                            <Link
                                href={`/tienda/${publicProfile.slug}`}
                                target="_blank"
                                className="flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
                            >
                                <ShoppingBag className="h-3 w-3" />
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
