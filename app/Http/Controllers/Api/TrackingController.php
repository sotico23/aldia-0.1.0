<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehiculo;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /**
     * Actualiza la ubicación del vehículo mediante su IMEI.
     * Endpoint: POST /api/v1/tracking/update
     */
    public function updateLocation(Request $request)
    {
        $validated = $request->validate([
            'imei' => 'required|string|exists:vehiculos,imei',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'velocidad' => 'nullable|integer',
        ]);

        $vehiculo = Vehiculo::where('imei', $validated['imei'])->first();

        if ($vehiculo) {
            $vehiculo->update([
                'lat' => $validated['lat'],
                'lng' => $validated['lng'],
                'velocidad' => $validated['velocidad'] ?? 0,
                'ultima_actualizacion' => now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Ubicación actualizada para '.$vehiculo->placa,
                'timestamp' => now(),
            ]);
        }

        return response()->json(['status' => 'error', 'message' => 'Vehículo no encontrado'], 404);
    }
}
