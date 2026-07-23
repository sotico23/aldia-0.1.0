<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\PermissionHelper;
use App\Http\Controllers\Controller;
use App\Models\PublicProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UsuarioRolController extends Controller
{
    public function index(): Response
    {
        /** @var User $user */
        $user = auth()->user();

        $userLevel = $user->highestRoleLevel();
        $ownerId = $user->getOwnerId();
        $isMaster = $user->hasRole('Master');

        // Obtain visible users
        $usuarios = User::visibles()->with('roles', 'permissions')->orderBy('name')->get();

        // System roles (owner_id = null): filtered by level for non-Master
        $systemQuery = Role::with('permissions')
            ->whereNull('owner_id')
            ->orderBy('name');

        if ($userLevel > 0) {
            $systemQuery->where('level', '>', $userLevel);
        }

        // Custom roles (owner_id = $ownerId): no level filter, owner sees all
        $customRoles = Role::with('permissions')
            ->where('owner_id', $ownerId)
            ->orderBy('name')
            ->get();

        $roles = $systemQuery->get()->concat($customRoles);

        $permissions = Permission::orderBy('name')->get();

        // Map assignments
        $asignaciones = $usuarios->flatMap(function ($user) {
            return $user->roles->map(function ($role) use ($user) {
                return [
                    'id' => $user->id.'-'.$role->id,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_avatar' => $user->profilePhotoUrl(),
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                    'type' => 'role',
                    'permissions' => $role->permissions->map(function ($p) {
                        return ['id' => $p->id, 'name' => $p->name];
                    }),
                ];
            });
        });

        // Master: fetch all users across all tenants for the Master panel
        $masterData = null;
        if ($isMaster) {
            $allUsers = User::with('roles', 'permissions')
                ->orderBy('created_at', 'desc')
                ->get();

            $now = now();

            $newUsers7days = $allUsers->where('created_at', '>=', $now->copy()->subDays(7));
            $newUsers30days = $allUsers->filter(fn ($u) => $u->created_at && $u->created_at->between($now->copy()->subDays(30), $now->copy()->subDays(7), false)
            );

            $masterData = [
                'all_users' => $allUsers->values(),
                'new_users_7days' => $newUsers7days->values(),
                'new_users_7days_count' => $newUsers7days->count(),
                'new_users_30days' => $newUsers30days->values(),
                'new_users_30days_count' => $newUsers30days->count(),
            ];
        }

        return Inertia::render('Backend/UsuarioRol/Index', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'permisos' => $permissions,
            'grouped_permissions' => $isMaster
                ? PermissionHelper::getGroupedPermissions()
                : PermissionHelper::getGroupedPermissionsForUser($user),
            'usuariosRoles' => $asignaciones,
            'publicProfiles' => $isMaster
                ? PublicProfile::with('user')->orderBy('title')->get()
                : PublicProfile::whereHas('user', fn ($q) => $q->where('owner_id', $ownerId))
                    ->with('user')
                    ->orderBy('title')
                    ->get(),
            'is_master' => $isMaster,
            'masterData' => $masterData,
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $currentUser = auth()->user();

        if (! $currentUser->hasRole('Master')) {
            abort(403, 'Solo el Master puede crear usuarios globales.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $ownerId = $currentUser->getOwnerId();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'creator_id' => $currentUser->id,
            'owner_id' => $ownerId,
        ]);

        return redirect()->route('usuarios-roles.index')->with('success', "Usuario {$user->name} creado correctamente.");
    }

    public function toggleOfficial(PublicProfile $publicProfile): RedirectResponse
    {
        $ownerId = auth()->user()->getOwnerId();

        // For non-Master users, verify ownership
        if (! auth()->user()->hasRole('Master')) {
            $publicProfile = PublicProfile::whereHas('user', fn ($q) => $q->where('owner_id', $ownerId))
                ->findOrFail($publicProfile->id);
        }

        $publicProfile->update([
            'is_official' => ! $publicProfile->is_official,
        ]);

        return back()->with('success', 'Estado de insignia actualizado.');
    }

    public function toggleStatus(Request $request, PublicProfile $publicProfile): RedirectResponse
    {
        $ownerId = auth()->user()->getOwnerId();

        // For non-Master users, verify ownership
        if (! auth()->user()->hasRole('Master')) {
            $publicProfile = PublicProfile::whereHas('user', fn ($q) => $q->where('owner_id', $ownerId))
                ->findOrFail($publicProfile->id);
        }

        $validated = $request->validate([
            'field' => ['required', 'string', 'in:is_verified,is_official'],
            'value' => ['required', 'boolean'],
        ]);

        $publicProfile->update([
            $validated['field'] => $validated['value'],
        ]);

        $label = $validated['field'] === 'is_verified' ? 'Verificado' : 'Oficial';
        $status = $validated['value'] ? 'activado' : 'desactivado';

        return back()->with('success', "Insignia {$label} {$status} correctamente.");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'usuario_id' => 'required|exists:users,id',
            'rol_id' => 'nullable|exists:roles,id',
            'permiso_id' => 'nullable|exists:permissions,id',
        ]);

        $user = User::visibles()->findOrFail($validated['usuario_id']);
        $currentUser = auth()->user();
        $currentUserLevel = $currentUser->highestRoleLevel();
        $ownerId = $currentUser->getOwnerId();

        // Prevent editing users of equal or higher level
        if ($currentUserLevel > 0 && $user->highestRoleLevel() <= $currentUserLevel) {
            abort(403, 'No tienes permiso para modificar a un usuario de este nivel.');
        }

        // Only Master can manage users outside their owner_id
        if (! $currentUser->hasRole('Master') && $user->getOwnerId() !== $ownerId) {
            abort(403, 'No puedes modificar usuarios de otro negocio.');
        }

        if (! empty($validated['rol_id'])) {
            $role = Role::findById($validated['rol_id']);

            if ($currentUserLevel > 0 && $role->level <= $currentUserLevel) {
                abort(403, 'No tienes permiso para asignar este rol.');
            }

            // Block assigning roles from other tenants
            if (! $role->isSystem() && $role->owner_id !== $ownerId) {
                abort(403, 'Este rol pertenece a otro negocio.');
            }

            $user->assignRole($role);
        }

        if (! empty($validated['permiso_id'])) {
            $permission = Permission::findById($validated['permiso_id']);
            $user->givePermissionTo($permission);
        }

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Privilegios actualizados.']);
        }

        return to_route('usuarios-roles.index')->with('success', 'Privilegios actualizados.');
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $currentUser = auth()->user();

        if (! $currentUser->hasRole('Master') && ! $currentUser->can('admin.roles.custom')) {
            abort(403, 'No tienes permiso para crear roles.');
        }

        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'nullable|array',
        ]);

        // Non-Master: filter permissions to only what the user has
        $permissionsToAssign = $validated['permissions'] ?? [];
        if (! $currentUser->hasRole('Master')) {
            $userPermissionIds = $currentUser->getAllPermissions()->pluck('id')->toArray();
            $permissionsToAssign = array_values(array_intersect($permissionsToAssign, $userPermissionIds));
        }

        // Force level: at least the user's level + 1, minimum 3
        $userLevel = $currentUser->highestRoleLevel();
        $roleLevel = max($userLevel + 1, 3);

        $role = Role::create([
            'name' => $validated['name'],
            'owner_id' => $currentUser->getOwnerId(),
            'created_by' => $currentUser->id,
            'level' => $roleLevel,
        ]);

        if (! empty($permissionsToAssign)) {
            // Always include "ver dashboard" so users with this role can access the main layout
            $dashboardPermission = Permission::where('name', 'ver dashboard')->first();
            if ($dashboardPermission && ! in_array($dashboardPermission->id, $permissionsToAssign)) {
                $permissionsToAssign[] = $dashboardPermission->id;
            }

            $role->givePermissionTo($permissionsToAssign);
        }

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('usuarios-roles.index')->with('success', 'Rol creado correctamente.');
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        $currentUser = auth()->user();

        // System roles: only Master can edit
        if ($role->isSystem() && ! $currentUser->hasRole('Master')) {
            abort(403, 'No puedes modificar roles del sistema.');
        }

        // Custom roles: check permission + ownership
        if (! $role->isSystem()) {
            if (! $currentUser->hasRole('Master') && ! $currentUser->can('admin.roles.custom')) {
                abort(403, 'No tienes permiso para modificar roles.');
            }

            if ($role->owner_id !== $currentUser->getOwnerId()) {
                abort(403, 'Este rol pertenece a otro negocio.');
            }
        }

        $validated = $request->validate([
            'name' => 'nullable|string|unique:roles,name,'.$role->id,
            'permissions' => 'nullable|array',
        ]);

        if ($request->filled('name')) {
            $role->update(['name' => $validated['name']]);
        }
        if ($request->has('permissions')) {
            $permissionsToSync = $validated['permissions'] ?? [];

            // Non-Master: filter permissions to only what the user has
            if (! $currentUser->hasRole('Master')) {
                $userPermissionIds = $currentUser->getAllPermissions()->pluck('id')->toArray();
                $permissionsToSync = array_values(array_intersect($permissionsToSync, $userPermissionIds));
            }

            // Ensure "ver dashboard" is always included when there are permissions assigned
            if (! empty($permissionsToSync)) {
                $dashboardPermission = Permission::where('name', 'ver dashboard')->first();
                if ($dashboardPermission && ! in_array($dashboardPermission->id, $permissionsToSync)) {
                    $permissionsToSync[] = $dashboardPermission->id;
                }
            }

            $role->syncPermissions($permissionsToSync);
        }

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('usuarios-roles.index')->with('success', 'Rol actualizado.');
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        $currentUser = auth()->user();

        if ($role->isSystem() && ! $currentUser->hasRole('Master')) {
            abort(403, 'No puedes eliminar roles del sistema.');
        }

        if (! $role->isSystem()) {
            if (! $currentUser->hasRole('Master') && ! $currentUser->can('admin.roles.custom')) {
                abort(403, 'No tienes permiso para eliminar roles.');
            }

            if ($role->owner_id !== $currentUser->getOwnerId()) {
                abort(403, 'Este rol pertenece a otro negocio.');
            }
        }

        $role->delete();

        return redirect()->route('usuarios-roles.index')->with('success', 'Rol eliminado.');
    }

    public function storePermission(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:permissions,name',
        ]);

        Permission::create(['name' => $validated['name']]);

        return redirect()->route('usuarios-roles.index')->with('success', 'Permiso creado correctamente.');
    }

    public function updatePermission(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:permissions,name,'.$permission->id,
        ]);

        $permission->update(['name' => $validated['name']]);

        return redirect()->route('usuarios-roles.index')->with('success', 'Permiso actualizado.');
    }

    public function destroyPermission(Permission $permission): RedirectResponse
    {
        $permission->delete();

        return redirect()->route('usuarios-roles.index')->with('success', 'Permiso eliminado.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $parts = explode('-', $id);
        if (count($parts) === 2) {
            $user = User::visibles()->find($parts[0]);
            $role = Role::findById($parts[1]);
            $currentUser = auth()->user();
            $currentUserLevel = $currentUser->highestRoleLevel();

            if ($user && $role) {
                if ($currentUserLevel > 0 && ($user->highestRoleLevel() <= $currentUserLevel || $role->level <= $currentUserLevel)) {
                    abort(403, 'No tienes permiso para quitar este rol o modificar a este usuario.');
                }

                if (! $currentUser->hasRole('Master') && $user->getOwnerId() !== $currentUser->getOwnerId()) {
                    abort(403, 'No puedes modificar usuarios de otro negocio.');
                }

                if (! $role->isSystem() && $role->owner_id !== $currentUser->getOwnerId()) {
                    abort(403, 'Este rol pertenece a otro negocio.');
                }

                $user->removeRole($role);
            }
        }

        return redirect()->route('usuarios-roles.index')->with('success', 'Asignación eliminada.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $currentUser = auth()->user();
        $currentUserLevel = $currentUser->highestRoleLevel();
        $ownerId = $currentUser->getOwnerId();

        if ($currentUserLevel > 0 && $user->highestRoleLevel() <= $currentUserLevel) {
            abort(403, 'No tienes permiso para modificar a un usuario de este nivel.');
        }

        if (! $currentUser->hasRole('Master') && $user->getOwnerId() !== $ownerId) {
            abort(403, 'No puedes modificar usuarios de otro negocio.');
        }

        if (! $currentUser->hasRole('Master') && ! User::visibles()->where('id', $user->id)->exists()) {
            abort(403, 'No tienes permiso para modificar a este usuario.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'rut' => 'nullable|string|max:20',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
        ]);

        $user->update($validated);

        return redirect()->route('usuarios-roles.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function resetUserPassword(Request $request, User $user): RedirectResponse
    {
        $currentUser = auth()->user();
        $currentUserLevel = $currentUser->highestRoleLevel();
        $ownerId = $currentUser->getOwnerId();

        if ($currentUserLevel > 0 && $user->highestRoleLevel() <= $currentUserLevel) {
            abort(403, 'No tienes permiso para modificar a un usuario de este nivel.');
        }

        if (! $currentUser->hasRole('Master') && $user->getOwnerId() !== $ownerId) {
            abort(403, 'No puedes modificar usuarios de otro negocio.');
        }

        if (! $currentUser->hasRole('Master') && ! User::visibles()->where('id', $user->id)->exists()) {
            abort(403, 'No tienes permiso para modificar a este usuario.');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        return redirect()->route('usuarios-roles.index')->with('success', 'Contraseña restablecida correctamente.');
    }

    public function toggleBan(User $user): RedirectResponse
    {
        $currentUser = auth()->user();
        $currentUserLevel = $currentUser->highestRoleLevel();

        if ($currentUserLevel > 0 && $user->highestRoleLevel() <= $currentUserLevel) {
            abort(403, 'No tienes permiso para bloquear a un usuario de este nivel.');
        }

        if ($user->is($currentUser)) {
            abort(403, 'No puedes bloquearte a ti mismo.');
        }

        if (! $currentUser->hasRole('Master') && ! User::visibles()->where('id', $user->id)->exists()) {
            abort(403, 'No tienes permiso para bloquear a este usuario.');
        }

        $user->is_active = ! $user->is_active;
        $user->banned_at = $user->is_active ? null : now();
        $user->save();

        $action = $user->is_active ? 'desbloqueado' : 'bloqueado';

        return redirect()->route('usuarios-roles.index')->with('success', "Usuario {$action} correctamente.");
    }
}
