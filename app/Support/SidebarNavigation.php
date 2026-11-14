<?php

namespace App\Support;

use App\Models\User;

class SidebarNavigation
{
    public static function allows(?User $user, string|array $required): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['Master', 'Super Admin'])) {
            return true;
        }

        $permissions = $user->getAllPermissions()->pluck('name')->all();

        foreach ((array) $required as $permission) {
            if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
                return true;
            }

            if (str_ends_with($permission, '.*')) {
                foreach ($permissions as $granted) {
                    if (str_starts_with($granted, substr($permission, 0, -1))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function filter(array $items, ?User $user): array
    {
        $visible = [];

        foreach ($items as $item) {
            if (isset($item['permission']) && ! self::allows($user, $item['permission'])) {
                continue;
            }

            if (isset($item['items'])) {
                $item['items'] = self::filter($item['items'], $user);

                if ($item['items'] === []) {
                    continue;
                }
            }

            $visible[] = $item;
        }

        return $visible;
    }
}
