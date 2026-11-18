<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CommissionRule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WalletSettingsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.web-settings.edit'),
        ];
    }

    /**
     * Regla general de comisión del comercio (sin cajero asignado).
     */
    public function show(Request $request): JsonResponse
    {
        $rule = $this->generalRule($request);

        return response()->json([
            'data' => [
                'commission_type' => $rule?->commission_type ?? 'percentage',
                'commission_value' => (float) ($rule?->commission_value ?? 0),
                'min_sale_amount' => (float) ($rule?->min_sale_amount ?? 0),
                'is_active' => (bool) ($rule?->is_active ?? false),
                'exists' => $rule !== null,
            ],
        ]);
    }

    /**
     * Crea o actualiza la regla general de comisión del comercio.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commission_type' => ['required', 'in:percentage,fixed'],
            'commission_value' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'min_sale_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'is_active' => ['required', 'boolean'],
        ]);

        $tenantId = $this->tenantId($request);
        $rule = $this->generalRule($request);

        if ($rule === null) {
            $rule = new CommissionRule([
                'tenant_id' => $tenantId,
                'user_id' => null,
            ]);
        }

        $rule->fill([
            'commission_type' => $validated['commission_type'],
            'commission_value' => (float) $validated['commission_value'],
            'min_sale_amount' => (float) ($validated['min_sale_amount'] ?? 0),
            'is_active' => (bool) $validated['is_active'],
        ]);

        $rule->save();

        return response()->json([
            'success' => true,
            'message' => 'Configuración de billeteras guardada correctamente.',
            'data' => [
                'commission_type' => $rule->commission_type,
                'commission_value' => (float) $rule->commission_value,
                'min_sale_amount' => (float) $rule->min_sale_amount,
                'is_active' => (bool) $rule->is_active,
                'exists' => true,
            ],
        ]);
    }

    /**
     * Obtiene la regla general (user_id nulo) del tenant autenticado.
     */
    private function generalRule(Request $request): ?CommissionRule
    {
        return CommissionRule::query()
            ->where('tenant_id', $this->tenantId($request))
            ->whereNull('user_id')
            ->latest('id')
            ->first();
    }

    /**
     * Tenant al que pertenece el usuario autenticado.
     */
    private function tenantId(Request $request): int
    {
        /** @var User $user */
        $user = $request->user();

        return (int) $user->getOwnerId();
    }
}
