<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Master and Super Admin bypass all permission checks
        if ($user->hasRole('Master') || $user->hasRole('Super Admin')) {
            return $next($request);
        }

        // Check if user has any of the required permissions
        $hasPermission = false;
        foreach ($permissions as $permissionGroup) {
            $permissionList = explode('|', $permissionGroup);
            foreach ($permissionList as $permission) {
                if ($user->can(trim($permission))) {
                    $hasPermission = true;
                    break 2;
                }
            }
        }

        // User does NOT have the permission — always deny with 403
        if (! $hasPermission) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        // User HAS the permission — now check trial restrictions for write actions
        if ($user->isTrialActive()) {
            return $next($request);
        }

        if ($user->isTrialExpired() && $this->isWriteAction($permissions)) {
            return $this->trialExpiredResponse($request);
        }

        return $next($request);
    }

    private function isWriteAction(array $permissions): bool
    {
        $writeActions = ['create', 'edit', 'delete', 'import', 'export'];

        foreach ($permissions as $permissionGroup) {
            $permissionList = explode('|', $permissionGroup);
            foreach ($permissionList as $permission) {
                $parts = explode('.', trim($permission));
                $action = end($parts);
                if (in_array($action, $writeActions)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function trialExpiredResponse(Request $request): Response
    {
        if ($request->header('X-Inertia')) {
            return response()->json([
                'trial_expired' => true,
                'message' => 'Tu período de prueba ha finalizado. Actualiza tu plan para seguir editando.',
            ], 403);
        }

        return redirect()->route('planes.index')->with('warning', 'Tu período de prueba ha finalizado. Actualiza tu plan para seguir editando.');
    }
}
