<?php

namespace App\Http\Controllers;

use App\Http\Exceptions\CancellationDeniedException;
use App\Http\Exceptions\SlotUnavailableException;
use App\Mail\AppointmentBooked;
use App\Models\Appointment;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Scopes\OwnerScope;
use App\Services\AppointmentService;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AppointmentController extends Controller
{
    public function dashboard()
    {
        $today = now()->startOfDay();
        $startWeek = now()->startOfWeek();
        $startMonth = now()->startOfMonth();

        $allAppointments = Appointment::with(['client', 'producto', 'provider'])
            ->where('start_time', '>=', now()->subMonth())
            ->get();

        $citasHoy = $allAppointments->filter(fn ($a) => $a->start_time?->startOfDay()->eq($today));
        $citasSemana = $allAppointments->filter(fn ($a) => $a->start_time?->gte($startWeek));
        $citasMes = $allAppointments->filter(fn ($a) => $a->start_time?->gte($startMonth));

        $ingresosHoy = $citasHoy->where('payment_status', 'pagado')->sum(fn ($a) => $a->producto?->precio_venta ?? 0);
        $ingresosSemana = $citasSemana->where('payment_status', 'pagado')->sum(fn ($a) => $a->producto?->precio_venta ?? 0);
        $ingresosMes = $citasMes->where('payment_status', 'pagado')->sum(fn ($a) => $a->producto?->precio_venta ?? 0);

        $totalClientesHoy = $citasHoy->pluck('client_id')->unique()->count();

        $ocupacion = $citasHoy->count() > 0
            ? round(($citasHoy->count() / 8) * 100)
            : 0;

        $last7Days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
        $appointmentsByDay = $last7Days->map(fn ($date) => [
            'date' => $date,
            'total' => $allAppointments->filter(fn ($a) => $a->start_time?->format('Y-m-d') === $date)->count(),
            'ingresos' => $allAppointments
                ->filter(fn ($a) => $a->start_time?->format('Y-m-d') === $date && $a->payment_status === 'pagado')
                ->sum(fn ($a) => $a->producto?->precio_venta ?? 0),
        ]);

        $statusDistribution = collect(['pendiente', 'confirmada', 'completada', 'cancelada'])
            ->map(fn ($s) => [
                'name' => match ($s) {
                    'pendiente' => 'Pendientes',
                    'confirmada' => 'Confirmadas',
                    'completada' => 'Completadas',
                    'cancelada' => 'Canceladas',
                    default => $s,
                },
                'value' => $allAppointments->where('status', $s)->count(),
                'color' => match ($s) {
                    'pendiente' => '#f59e0b',
                    'confirmada' => '#3b82f6',
                    'completada' => '#22c55e',
                    'cancelada' => '#ef4444',
                    default => '#6b7280',
                },
            ]);

        $topServices = Producto::whereHas('appointments', fn ($q) => $q->where('payment_status', 'pagado'))
            ->withCount(['appointments as reservas' => fn ($q) => $q->where('payment_status', 'pagado')])
            ->orderByDesc('reservas')
            ->take(5)
            ->get(['id', 'nombre'])
            ->map(fn ($s) => [
                'name' => $s->nombre,
                'value' => (int) $s->reservas,
            ]);

        return Inertia::render('appointments/Dashboard', [
            'citasHoy' => $citasHoy->values(),
            'stats' => [
                'hoy' => $citasHoy->count(),
                'semana' => $citasSemana->count(),
                'mes' => $citasMes->count(),
                'ingresosHoy' => $ingresosHoy,
                'ingresosSemana' => $ingresosSemana,
                'ingresosMes' => $ingresosMes,
                'clientesHoy' => $totalClientesHoy,
                'ocupacion' => $ocupacion,
            ],
            'appointmentsByDay' => $appointmentsByDay,
            'statusDistribution' => $statusDistribution,
            'topServices' => $topServices,
        ]);
    }

    public function index()
    {
        $appointments = Appointment::with(['client', 'producto', 'provider'])
            ->latest('start_time')
            ->get();

        $stats = [
            'total' => $appointments->count(),
            'pendiente' => $appointments->where('status', 'pendiente')->count(),
            'confirmada' => $appointments->where('status', 'confirmada')->count(),
            'completada' => $appointments->where('status', 'completada')->count(),
            'cancelada' => $appointments->where('status', 'cancelada')->count(),
            'ingresos_estimados' => $appointments->sum(fn ($a) => $a->producto?->precio_venta ?? 0),
        ];

        $last30Days = collect(range(30, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
        $appointmentsByDay = $last30Days->map(fn ($date) => [
            'date' => $date,
            'total' => $appointments->filter(fn ($a) => $a->start_time?->format('Y-m-d') === $date)->count(),
        ]);

        $statusDistribution = collect(['pendiente', 'confirmada', 'completada', 'cancelada'])
            ->map(fn ($s) => [
                'name' => match ($s) {
                    'pendiente' => 'Pendientes',
                    'confirmada' => 'Confirmadas',
                    'completada' => 'Completadas',
                    'cancelada' => 'Canceladas',
                    default => $s,
                },
                'value' => $stats[$s],
                'color' => match ($s) {
                    'pendiente' => '#f59e0b',
                    'confirmada' => '#3b82f6',
                    'completada' => '#22c55e',
                    'cancelada' => '#ef4444',
                    default => '#6b7280',
                },
            ]);

        $revenueByDay = $last30Days->map(fn ($date) => [
            'date' => $date,
            'ingresos' => $appointments
                ->filter(fn ($a) => $a->start_time?->format('Y-m-d') === $date && $a->payment_status === 'pagado')
                ->sum(fn ($a) => $a->producto?->precio_venta ?? 0),
        ]);

        $pagados = $appointments->where('payment_status', 'pagado');
        $ingresosReales = $pagados->sum(fn ($a) => $a->producto?->precio_venta ?? 0);
        $tasaConversion = $stats['total'] > 0
            ? round(($pagados->count() / $stats['total']) * 100)
            : 0;

        $proyeccionDiaria = $stats['total'] > 0
            ? round($stats['ingresos_estimados'] / max($stats['total'], 1), 2)
            : 0;
        $last7Days = $appointments->filter(fn ($a) => $a->start_time?->gte(now()->subDays(7)));
        $promedioSemanal = $last7Days->count() > 0
            ? round($last7Days->sum(fn ($a) => $a->producto?->precio_venta ?? 0) / 7, 2)
            : 0;

        $topServices = Producto::whereHas('appointments', fn ($q) => $q->where('payment_status', 'pagado'))
            ->withCount(['appointments as reservas' => fn ($q) => $q->where('payment_status', 'pagado')])
            ->orderByDesc('reservas')
            ->take(5)
            ->get(['id', 'nombre', 'precio_venta'])
            ->map(fn ($s) => [
                'name' => $s->nombre,
                'reservas' => (int) $s->reservas,
                'ingresos' => (float) $s->reservas * (float) $s->precio_venta,
            ]);

        $ownerId = Auth::user()->getOwnerId();
        $appointmentUserIds = Appointment::where('owner_id', $ownerId)->pluck('client_id')
            ->merge(Appointment::where('owner_id', $ownerId)->pluck('provider_id'))
            ->unique();
        $providerStats = User::whereIn('id', $appointmentUserIds)->whereHas('appointments')
            ->withCount(['appointments as total_citas'])
            ->withSum('appointments as ingresos', 'amount_paid')
            ->orderByDesc('total_citas')
            ->take(5)
            ->get(['id', 'name', 'profile_photo_path'])
            ->map(fn ($u) => [
                'name' => $u->name,
                'photo' => $u->profilePhotoUrl(),
                'total' => (int) $u->total_citas,
                'ingresos' => (float) ($u->ingresos ?? 0),
            ]);

        return Inertia::render('appointments/Index', [
            'appointments' => $appointments,
            'stats' => $stats,
            'appointmentsByDay' => $appointmentsByDay,
            'statusDistribution' => $statusDistribution,
            'revenueByDay' => $revenueByDay,
            'ingresosReales' => $ingresosReales,
            'tasaConversion' => $tasaConversion,
            'proyeccionDiaria' => $proyeccionDiaria,
            'promedioSemanal' => $promedioSemanal,
            'topServices' => $topServices,
            'providerStats' => $providerStats,
        ]);
    }

    public function calendar()
    {
        $appointments = Appointment::with(['client', 'producto', 'provider'])->get();
        $googleConnected = Configuracion::where('clave', 'google_calendar_access_token')->exists();
        $googleConnectedEmail = Configuracion::where('clave', 'google_calendar_connected_email')->value('valor');
        $googleCalendarId = Configuracion::where('clave', 'google_calendar_id')->value('valor');

        $startMonth = now()->startOfMonth();
        $citasMes = $appointments->filter(fn ($a) => $a->start_time?->gte($startMonth));
        $ingresosMes = $citasMes->where('payment_status', 'pagado')->sum(fn ($a) => $a->producto?->precio_venta ?? 0);
        $completadasMes = $citasMes->where('status', 'completada')->count();
        $canceladasMes = $citasMes->where('status', 'cancelada')->count();

        $monthlyStats = [
            'total' => $citasMes->count(),
            'completadas' => $completadasMes,
            'canceladas' => $canceladasMes,
            'ingresos' => $ingresosMes,
            'pendientes' => $citasMes->where('status', 'pendiente')->count(),
            'tasaCompletitud' => $citasMes->count() > 0
                ? round(($completadasMes / $citasMes->count()) * 100)
                : 0,
        ];

        $monthDays = collect(range(1, now()->daysInMonth))->map(fn ($d) => now()->startOfMonth()->addDays($d - 1)->format('Y-m-d'));
        $appointmentsByDayMonth = $monthDays->map(fn ($date) => [
            'date' => (int) now()->parse($date)->format('d'),
            'total' => $citasMes->filter(fn ($a) => $a->start_time?->format('Y-m-d') === $date)->count(),
        ]);

        $statusDistributionMonth = collect(['pendiente', 'confirmada', 'completada', 'cancelada'])
            ->map(fn ($s) => [
                'name' => match ($s) {
                    'pendiente' => 'Pendientes',
                    'confirmada' => 'Confirmadas',
                    'completada' => 'Completadas',
                    'cancelada' => 'Canceladas',
                    default => $s,
                },
                'value' => $citasMes->where('status', $s)->count(),
                'color' => match ($s) {
                    'pendiente' => '#f59e0b',
                    'confirmada' => '#3b82f6',
                    'completada' => '#22c55e',
                    'cancelada' => '#ef4444',
                    default => '#6b7280',
                },
            ]);

        $monthServices = Producto::whereHas('appointments', fn ($q) => $q->where('start_time', '>=', $startMonth))
            ->withCount(['appointments as reservas' => fn ($q) => $q->where('start_time', '>=', $startMonth)])
            ->orderByDesc('reservas')
            ->take(5)
            ->get(['id', 'nombre'])
            ->map(fn ($s) => ['name' => $s->nombre, 'value' => (int) $s->reservas]);

        $providerWorkload = User::whereHas('appointments', fn ($q) => $q->where('start_time', '>=', $startMonth))
            ->withCount(['appointments as total' => fn ($q) => $q->where('start_time', '>=', $startMonth)])
            ->orderByDesc('total')
            ->take(5)
            ->get(['id', 'name', 'profile_photo_path'])
            ->map(fn ($u) => ['name' => $u->name, 'photo' => $u->profilePhotoUrl(), 'total' => (int) $u->total]);

        $ownerId = Auth::user()->getOwnerId();
        $clients = Cliente::where('owner_id', $ownerId)
            ->where('activo', true)
            ->whereNotNull('user_id')
            ->with('user:id,name,email')
            ->orderBy('nombre')
            ->get(['id', 'user_id', 'nombre', 'rut', 'telefono', 'email']);

        return Inertia::render('appointments/Calendar', [
            'appointments' => $appointments,
            'services' => Producto::where('is_service', true)->where('activo', true)->get(),
            'clients' => $clients,
            'googleConnected' => $googleConnected,
            'googleConnectedEmail' => $googleConnectedEmail,
            'googleAuthUrl' => app(GoogleCalendarService::class)->getAuthUrl(),
            'googleCalendarId' => $googleCalendarId,
            'monthlyStats' => $monthlyStats,
            'appointmentsByDayMonth' => $appointmentsByDayMonth,
            'statusDistributionMonth' => $statusDistributionMonth,
            'monthServices' => $monthServices,
            'providerWorkload' => $providerWorkload,
        ]);
    }

    public function updateGoogleConfig(Request $request)
    {
        if ($request->boolean('disconnect_oauth')) {
            Configuracion::whereIn('clave', [
                'google_calendar_access_token',
                'google_calendar_connected_email',
            ])->delete();

            return back()->with('success', 'Google Calendar desconectado.');
        }

        $validated = $request->validate([
            'google_calendar_id' => 'nullable|string|max:500',
        ]);

        if (isset($validated['google_calendar_id'])) {
            Configuracion::updateOrCreate(
                ['clave' => 'google_calendar_id'],
                ['valor' => $validated['google_calendar_id'], 'descripcion' => 'Google Calendar ID', 'categoria' => 'integrations']
            );
        }

        return back()->with('success', 'Configuración de Google Calendar guardada.');
    }

    public function redirectToGoogle()
    {
        return redirect(app(GoogleCalendarService::class)->getAuthUrl());
    }

    public function handleGoogleCallback(Request $request)
    {
        $code = $request->input('code');
        if (! $code) {
            return redirect()->route('appointments.calendar')->with('error', 'Autorización cancelada.');
        }

        app(GoogleCalendarService::class)->handleCallback($code);

        $token = json_decode(Configuracion::where('clave', 'google_calendar_access_token')->value('valor'), true);
        $email = $token['email'] ?? null;
        if ($email) {
            Configuracion::updateOrCreate(
                ['clave' => 'google_calendar_connected_email'],
                ['valor' => $email, 'descripcion' => 'Google Calendar connected email', 'categoria' => 'integrations']
            );
        }

        return redirect()->route('appointments.calendar')->with('success', 'Google Calendar sincronizado correctamente.');
    }

    public function create()
    {
        $services = Producto::where('is_service', true)->where('activo', true)->get();
        $ownerId = Auth::user()->getOwnerId();
        $clients = Cliente::where('owner_id', $ownerId)
            ->where('activo', true)
            ->whereNotNull('user_id')
            ->with('user:id,name,email')
            ->orderBy('nombre')
            ->get(['id', 'user_id', 'nombre', 'rut', 'telefono', 'email']);

        return Inertia::render('appointments/Create', [
            'services' => $services,
            'clients' => $clients,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:users,id',
            'producto_id' => 'required|exists:productos,id',
            'provider_id' => 'nullable|exists:users,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'notes' => 'nullable|string',
        ]);

        $validated['provider_id'] = $validated['provider_id'] ?? Auth::id();

        $startTime = Carbon::parse($validated['start_time']);
        $endTime = Carbon::parse($validated['end_time']);

        $appointmentService = app(AppointmentService::class);

        $appointment = DB::transaction(function () use ($validated, $startTime, $endTime, $appointmentService) {
            if ($appointmentService->hasConflictLocked(
                $validated['provider_id'],
                $startTime,
                $endTime
            )) {
                throw new SlotUnavailableException(
                    'El profesional ya tiene una cita agendada en ese horario.'
                );
            }

            return Appointment::create($validated);
        });

        // Sync to Google Calendar
        app(GoogleCalendarService::class)->createEvent($appointment);

        // Send confirmation email
        try {
            Mail::to($appointment->client->email)->send(new AppointmentBooked($appointment));
        } catch (\Exception $e) {
            // Log error
        }

        return back()->with('success', 'Cita agendada.');
    }

    public function update(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'status' => 'nullable|in:pendiente,confirmada,completada,cancelada',
            'payment_status' => 'nullable|in:pendiente,pagado',
            'amount_paid' => 'nullable|numeric',
        ]);

        // Enforce 24-hour cancellation policy
        if (($validated['status'] ?? null) === 'cancelada') {
            $appointmentService = app(AppointmentService::class);
            if ($appointmentService->canCancel($appointment)) {
                throw new CancellationDeniedException(
                    'No se puede cancelar una cita con menos de 24 horas de anticipación.'
                );
            }
        }

        $oldPaymentStatus = $appointment->payment_status;
        $appointment->update($validated);

        // Sync to Google Calendar
        app(GoogleCalendarService::class)->updateEvent($appointment);

        // Handle Venta creation/cancellation on payment status change
        if ($validated['payment_status'] ?? null) {
            $newPaymentStatus = $validated['payment_status'];

            if ($newPaymentStatus === 'pagado' && $oldPaymentStatus !== 'pagado') {
                $this->createVentaFromAppointment($appointment);
            } elseif ($newPaymentStatus === 'pendiente' && $oldPaymentStatus === 'pagado') {
                $this->cancelVentaFromAppointment($appointment);
            }
        }

        return back()->with('success', 'Cita actualizada.');
    }

    protected function createVentaFromAppointment(Appointment $appointment): void
    {
        if (Venta::where('appointment_id', $appointment->id)->exists()) {
            return;
        }

        $producto = $appointment->producto;
        $price = (float) ($producto?->precio_venta ?? 0);
        $iva = round($price * config('taxes.iva_rate'), 2);
        $subtotal = round($price - $iva, 2);

        $ventaNumero = 'CITA-'.$appointment->id.'-'.now()->format('Ymd');

        $cliente = Cliente::withoutGlobalScope(OwnerScope::class)
            ->where('user_id', $appointment->client_id)
            ->first();

        $ownerId = $appointment->owner_id ?? $appointment->provider_id ?? Auth::id();

        $venta = Venta::create([
            'owner_id' => $ownerId,
            'user_id' => $ownerId,
            'cliente_id' => $cliente?->id,
            'appointment_id' => $appointment->id,
            'numero' => $ventaNumero,
            'fecha' => now(),
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $price,
            'metodo_pago' => 'otro',
            'tipo_documento' => 'boleta',
            'es_pos' => false,
            'estado' => 'pagada',
            'notas' => 'Venta generada desde Cita #'.$appointment->id.' - '.($producto?->nombre ?: ''),
            'incluye_iva' => true,
        ]);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto?->id,
            'cantidad' => 1,
            'precio_unitario' => $price,
            'subtotal' => $price,
        ]);
    }

    protected function cancelVentaFromAppointment(Appointment $appointment): void
    {
        Venta::where('appointment_id', $appointment->id)
            ->where('estado', 'pagada')
            ->update(['estado' => 'cancelada']);
    }

    public function show(Appointment $appointment)
    {
        return redirect()->route('appointments.index');
    }

    public function destroy(Appointment $appointment)
    {
        // Delete from Google Calendar
        app(GoogleCalendarService::class)->deleteEvent($appointment);

        $appointment->delete();

        return back()->with('success', 'Cita cancelada.');
    }

    public function exportCsv()
    {
        $appointments = Appointment::with(['client', 'producto', 'provider'])->get();
        $filename = 'reporte-citas-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($appointments) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Cliente', 'Servicio', 'Proveedor', 'Inicio', 'Fin', 'Estado', 'Pago', 'Monto']);

            foreach ($appointments as $app) {
                fputcsv($handle, [
                    $app->id,
                    $app->client?->name,
                    $app->producto?->nombre,
                    $app->provider?->name,
                    $app->start_time,
                    $app->end_time,
                    $app->status,
                    $app->payment_status,
                    $app->amount_paid,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportar(Request $request)
    {
        $appointments = Appointment::with(['client', 'producto', 'provider'])->get();
        $format = $request->query('format', 'csv');

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="citas.csv"',
            ];

            $csvContent = "ID,Cliente,Servicio,Proveedor,Inicio,Fin,Estado,Pago,Monto\n";
            foreach ($appointments as $app) {
                $csvContent .= "{$app->id},{$app->client?->name},{$app->producto?->nombre},{$app->provider?->name},{$app->start_time},{$app->end_time},{$app->status},{$app->payment_status},{$app->amount_paid}\n";
            }

            return response($csvContent, 200, $headers);
        }

        if ($format === 'excel') {
            $headers = [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => 'attachment; filename="citas.csv"',
            ];

            $csvContent = "ID,Cliente,Servicio,Proveedor,Inicio,Fin,Estado,Pago,Monto\n";
            foreach ($appointments as $app) {
                $csvContent .= "{$app->id},{$app->client?->name},{$app->producto?->nombre},{$app->provider?->name},{$app->start_time},{$app->end_time},{$app->status},{$app->payment_status},{$app->amount_paid}\n";
            }

            return response($csvContent, 200, $headers);
        }

        return response()->json($appointments);
    }

    public function syncGoogleEvents()
    {
        $service = app(GoogleCalendarService::class);

        if (! $service->isConnected()) {
            return response()->json(['success' => false, 'error' => 'No hay conexión OAuth con Google Calendar. Conéctate desde la configuración.']);
        }

        try {
            $timeMin = now()->subMonth();
            $timeMax = now()->addMonth();

            $googleEvents = $service->listEvents($timeMin, $timeMax);

            $events = collect($googleEvents)->map(fn ($item) => [
                'id' => 'google-'.$item['id'],
                'title' => $item['summary'] ?? 'Sin título',
                'start' => $item['start'],
                'end' => $item['end'],
                'backgroundColor' => '#8b5cf6',
                'borderColor' => '#7c3aed',
                'textColor' => '#fff',
                'className' => 'google-event',
                'extendedProps' => [
                    'source' => 'google',
                    'description' => $item['description'] ?? '',
                ],
            ])->values();

            return response()->json(['success' => true, 'events' => $events]);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['success' => false, 'error' => 'Error al sincronizar con Google Calendar.']);
        }
    }

    public function importar(Request $request)
    {
        return back()->with('success', 'Importación de citas procesada correctamente');
    }
}
