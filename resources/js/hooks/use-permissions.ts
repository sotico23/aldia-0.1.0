import { usePage } from '@inertiajs/react';

export function usePermissions() {
    const { auth } = usePage().props as any;
    const user = auth.user;

    const hasRole = (role: string | string[]): boolean => {
        if (!user || !user.roles) return false;
        if (Array.isArray(role)) {
            return role.some((r: string) => user.roles.includes(r));
        }
        return user.roles.includes(role);
    };

    const hasPermission = (permission: string | string[]): boolean => {
        if (!user || !user.permissions) return false;

        if (Array.isArray(permission)) {
            return permission.some((p: string) => hasPermission(p));
        }

        if (user.permissions?.includes('*')) return true;

        if (permission.includes('*')) {
            const prefix = permission.replace('*', '');
            if (permission.endsWith('.*')) {
                return user.permissions.some((p: string) => p.startsWith(prefix));
            }
            const regex = new RegExp('^' + permission.replace(/\*/g, '.*') + '$');
            return user.permissions.some((p: string) => regex.test(p));
        }

        return user.permissions.includes(permission);
    };

    return { hasRole, hasPermission, user };
}
