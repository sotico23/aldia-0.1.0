<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionSii;
use App\Models\DteDocumento;
use App\Services\Sii\CafManager;
use App\Services\SiiService;
use App\Support\ChileanRut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SiiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:finanzas.sii.create', only: ['create', 'store']),
            new Middleware('permission:finanzas.sii.edit', only: ['edit', 'update']),
            new Middleware('permission:finanzas.sii.delete', only: ['destroy']),
        ];
    }

    public function __construct(
        private CafManager $cafManager,
        private SiiService $siiService
    ) {}

    // ─── CAF ────────────────────────────────────────────────────────────────

    /** Página principal – lista CAFs y documentos recientes. */
    public function index(): Response
    {
        $ownerId = auth()->user()->getOwnerId();

        $cafs = $this->cafManager->listarPorOwner($ownerId);

        $documentosRecientes = DteDocumento::where('owner_id', $ownerId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return Inertia::render('Backend/Sii/Index', [
            'cafs' => $cafs,
            'documentos' => $documentosRecientes,
            'ambiente' => config('sii.semilla_url') ? 'certificacion' : 'produccion',
        ]);
    }

    /** Formulario para subir CAF. */
    public function createCaf(): Response
    {
        return Inertia::render('Backend/Sii/SubirCaf');
    }

    /** Procesa la subida del archivo XML de CAF. */
    public function storeCaf(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo_caf' => ['required', 'file', 'mimes:xml,txt', 'max:512'],
        ]);

        $xmlCaf = $request->file('archivo_caf')->get();

        DB::transaction(function () use ($xmlCaf) {
            $this->cafManager->importar($xmlCaf, auth()->user()->getOwnerId());
        });

        return redirect()->route('sii.index')
            ->with('success', 'CAF importado correctamente.');
    }

    // ─── Documentos DTE ─────────────────────────────────────────────────────

    /** Lista de DTEs emitidos con filtros. */
    public function documentos(Request $request): Response
    {
        $ownerId = auth()->user()->getOwnerId();

        $documentos = DteDocumento::where('owner_id', $ownerId)
            ->when($request->filled('estado') && $request->estado !== 'all', fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('tipo') && $request->tipo !== 'all', fn ($q) => $q->where('tipo_documento', $request->tipo))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Backend/Sii/Documentos', [
            'documentos' => $documentos,
            'filtros' => $request->only('estado', 'tipo'),
        ]);
    }

    /** Muestra el formulario de configuración del emisor. */
    public function configuracion(): Response
    {
        $config = ConfiguracionSii::where('owner_id', auth()->user()->getOwnerId())->first();

        return Inertia::render('Backend/Sii/Configuracion', [
            'config' => $config,
        ]);
    }

    /** Guarda o actualiza la configuración del emisor. */
    public function saveConfiguracion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rut' => 'required|string|max:15',
            'razon_social' => 'required|string|max:150',
            'giro' => 'required|string|max:150',
            'acteco' => 'required|integer',
            'direccion' => 'required|string|max:150',
            'comuna' => 'required|string|max:60',
            'ciudad' => 'required|string|max:60',
            'resolucion_numero' => 'nullable|integer',
            'resolucion_fecha' => 'nullable|date',
            'ambiente' => 'required|string|in:certificacion,produccion',
            'email_dte' => 'nullable|email|max:100',
            'telefono' => 'nullable|string|max:20',
        ]);

        ConfiguracionSii::updateOrCreate(
            ['owner_id' => auth()->user()->getOwnerId()],
            $validated
        );

        return redirect()->route('sii.index')
            ->with('success', 'Configuración del emisor actualizada.');
    }

    /**
     * Valida un RUT y retorna metadatos reales consultados al SII.
     */
    public function validarRut(Request $request, ?string $rut = null): JsonResponse
    {
        $rut = $rut ?? $request->input('rut');

        if (! ChileanRut::validate($rut)) {
            return response()->json([
                'success' => false,
                'message' => 'RUT inválido según el algoritmo Módulo 11',
            ], 422);
        }

        $siiService = new SiiService;
        $realData = $siiService->consultarDatosReales($rut);

        if ($realData['success']) {
            return response()->json([
                'success' => true,
                'is_contribuyente' => $realData['is_contribuyente'] ?? true,
                'rut' => $realData['rut'] ?? ChileanRut::format($rut),
                'razon_social' => $realData['razon_social'],
                'giro' => $realData['giro'],
                'giros' => $realData['giros'] ?? [],
                'comuna' => $realData['comuna'],
                'direccion' => $realData['direccion'],
                'message' => ($realData['is_contribuyente'] ?? true) ? null : 'RUT válido, pero sin actividades económicas registradas.',
            ]);
        }

        // Si falla la consulta real (e.g. no existe en la base de datos pública)
        return response()->json([
            'success' => true, // Lo enviamos como success para que se formatee el RUT aunque no traiga datos
            'is_contribuyente' => false,
            'rut' => ChileanRut::format($rut),
            'message' => 'RUT válido, pero no figura en los registros públicos de empresas. Deberás completar el giro y razón social manualmente.',
        ]);
    }

    public function refrescarToken(): RedirectResponse
    {
        try {
            $token = $this->siiService->refrescarToken();

            return back()->with('success', 'Token del SII actualizado correctamente.');
        } catch (\Exception $e) {
            Log::error('Error al refrescar token SII: '.$e->getMessage());

            return back()->with('error', 'Error al obtener token del SII: '.$e->getMessage());
        }
    }

    public function editCertificado(): Response
    {
        $config = ConfiguracionSii::where('owner_id', auth()->user()->getOwnerId())->first();

        return Inertia::render('Backend/Sii/Configuracion/Certificado', [
            'config' => $config,
        ]);
    }

    public function updateCertificado(Request $request): RedirectResponse
    {
        $request->validate([
            'certificado' => 'nullable|file|mimes:pfx,p12|max:2048',
            'password' => 'nullable|string',
        ]);

        $ownerId = auth()->user()->getOwnerId();
        $config = ConfiguracionSii::firstOrCreate(['owner_id' => $ownerId]);

        if ($request->hasFile('certificado')) {
            $file = $request->file('certificado');

            // Validar que el archivo sea un PKCS#12 real antes de guardar
            $pfxContent = file_get_contents($file->getRealPath());
            $certs = [];
            if (! openssl_pkcs12_read($pfxContent, $certs, $request->password ?? '')) {
                return back()->withErrors(['certificado' => 'El archivo no es un certificado PKCS#12 válido.']);
            }

            $path = $file->store('sii/certificados');
            $config->certificado_path = $path;

            // Extraer fecha de vencimiento
            try {
                $certData = openssl_x509_parse($certs['cert']);
                if (isset($certData['validTo_time_t'])) {
                    $config->certificado_vencimiento = date('Y-m-d', $certData['validTo_time_t']);
                }
            } catch (\Exception $e) {
                Log::warning('No se pudo extraer fecha de vencimiento del certificado: '.$e->getMessage());
            }
        }

        if ($request->password) {
            $config->certificado_password = $request->password;
        }

        $config->save();

        return back()->with('success', 'Certificado digital actualizado.');
    }

    public function probarCertificado(Request $request): JsonResponse
    {
        $request->validate([
            'certificado' => 'required|file|max:2048',
            'password' => 'required|string',
        ]);

        try {
            $pfxContent = file_get_contents($request->file('certificado')->getRealPath());
            $certs = [];

            if (! openssl_pkcs12_read($pfxContent, $certs, $request->password)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo abrir el archivo. Verifica que sea un archivo .pfx o .p12 válido y que la contraseña sea correcta.',
                ]);
            }

            $certData = openssl_x509_parse($certs['cert']);

            return response()->json([
                'success' => true,
                'vencimiento' => isset($certData['validTo_time_t'])
                    ? date('Y-m-d', $certData['validTo_time_t'])
                    : null,
                'subject' => $certData['subject'] ?? [],
                'issuer' => $certData['issuer'] ?? [],
                'validFrom' => isset($certData['validFrom_time_t'])
                    ? date('Y-m-d', $certData['validFrom_time_t'])
                    : null,
                'serialNumber' => $certData['serialNumber'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al procesar el certificado: '.$e->getMessage(),
            ]);
        }
    }

    public function editEmisor(): Response
    {
        $config = ConfiguracionSii::where('owner_id', auth()->user()->getOwnerId())->first();

        return Inertia::render('Backend/Sii/Configuracion/Emisor', [
            'config' => $config,
        ]);
    }

    public function updateEmisor(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rut' => 'required|string|max:15',
            'razon_social' => 'required|string|max:150',
            'giro' => 'required|string|max:150',
            'acteco' => 'required|integer',
            'direccion' => 'required|string|max:150',
            'comuna' => 'required|string|max:60',
            'ciudad' => 'required|string|max:60',
            'resolucion_numero' => 'nullable|integer',
            'resolucion_fecha' => 'nullable|date',
        ]);

        ConfiguracionSii::updateOrCreate(
            ['owner_id' => auth()->user()->getOwnerId()],
            $validated
        );

        return back()->with('success', 'Datos del emisor actualizados.');
    }

    public function editFolios(): Response
    {
        $ownerId = auth()->user()->getOwnerId();
        $cafs = $this->cafManager->listarPorOwner($ownerId);

        return Inertia::render('Backend/Sii/Configuracion/Folios', [
            'cafs' => $cafs,
        ]);
    }

    public function editAmbiente(): Response
    {
        $config = ConfiguracionSii::where('owner_id', auth()->user()->getOwnerId())->first();

        return Inertia::render('Backend/Sii/Configuracion/Ambiente', [
            'config' => $config,
        ]);
    }

    public function updateAmbiente(Request $request): RedirectResponse
    {
        $request->validate([
            'ambiente' => 'required|string|in:certificacion,produccion',
        ]);

        ConfiguracionSii::updateOrCreate(
            ['owner_id' => auth()->user()->getOwnerId()],
            ['ambiente' => $request->ambiente]
        );

        return back()->with('success', 'Ambiente de trabajo actualizado.');
    }
}
