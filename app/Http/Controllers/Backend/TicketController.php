<?php

namespace App\Http\Controllers\Backend;

use App\Exports\TicketsExport;
use App\Helpers\NotificationHelper;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Imports\TicketsImport;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NuevoTicketNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class TicketController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:comercial.tickets.create', only: ['create', 'store']),
            new Middleware('permission:comercial.tickets.edit', only: ['edit', 'update']),
            new Middleware('permission:comercial.tickets.delete', only: ['destroy']),
            new Middleware('permission:comercial.tickets.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:comercial.tickets.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(Request $request): Response
    {
        $ownerId = Auth::user()->getOwnerId();
        $query = Ticket::with(['cliente', 'producto', 'assignedUser'])->where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%")
                    ->orWhere('asignado_a', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($q2) use ($search) {
                        $q2->where('nombre', 'like', "%{$search}%");
                    })
                    ->orWhereHas('producto', function ($q2) use ($search) {
                        $q2->where('nombre', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('prioridad')) {
            $query->where('prioridad', $request->input('prioridad'));
        }

        $tickets = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $clientes = Cliente::where('owner_id', $ownerId)->orderBy('nombre')->get(['id', 'nombre']);
        $productos = Producto::where('owner_id', $ownerId)->orderBy('nombre')->get(['id', 'nombre']);

        $employees = User::where(function ($q) use ($ownerId) {
            $q->where('id', $ownerId)
                ->orWhere('creator_id', $ownerId);
        })
            ->where(function ($q) {
                $q->whereHas('empleado', fn ($q2) => $q2->where('estado', 'activo'))
                    ->orWhereHas('roles', fn ($q2) => $q2->whereIn('name', ['Administrador', 'Super Admin', 'Master']));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Backend/Tickets/Index', [
            'tickets' => $tickets,
            'clientes' => $clientes,
            'productos' => $productos,
            'employees' => $employees,
            'filters' => $request->only(['search', 'estado', 'prioridad']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'cliente_id' => 'nullable|exists:clientes,id',
            'producto_id' => 'nullable|exists:productos,id',
            'prioridad' => 'required|string|max:50',
            'estado' => 'required|string|max:50',
            'categoria' => 'nullable|string|max:100',
            'asignado_a' => 'nullable|string|max:255',
            'assigned_user_id' => 'nullable|exists:users,id',
            'es_soporte' => 'boolean',
        ]);

        $user = Auth::user();

        if (! empty($validated['assigned_user_id'])) {
            $assigned = User::find($validated['assigned_user_id']);
            $validated['asignado_a'] = $assigned?->name;
        }

        if (! empty($validated['es_soporte'])) {
            $superAdmin = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Master']))
                ->orderBy('id')
                ->first();
            $validated['owner_id'] = $superAdmin?->id ?? $user->getOwnerId();
        } else {
            $validated['owner_id'] = $user->getOwnerId();
        }

        $ticket = Ticket::create($validated);

        $this->sendTicketNotifications($ticket);

        return redirect()->route('tickets.index')->with('success', 'Ticket creado correctamente.');
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeOwner($ticket);

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'cliente_id' => 'nullable|exists:clientes,id',
            'producto_id' => 'nullable|exists:productos,id',
            'prioridad' => 'required|string|max:50',
            'estado' => 'required|string|max:50',
            'categoria' => 'nullable|string|max:100',
            'asignado_a' => 'nullable|string|max:255',
            'assigned_user_id' => 'nullable|exists:users,id',
        ]);

        if (! empty($validated['assigned_user_id'])) {
            $assigned = User::find($validated['assigned_user_id']);
            $validated['asignado_a'] = $assigned?->name;
        }

        $ticket->update($validated);

        $this->sendTicketNotifications($ticket);

        return redirect()->route('tickets.index')->with('success', 'Ticket actualizado correctamente.');
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        $this->authorizeOwner($ticket);
        $ticket->delete();

        return redirect()->route('tickets.index')->with('success', 'Ticket eliminado correctamente.');
    }

    public function show(Ticket $ticket): Response
    {
        $this->authorizeOwner($ticket);
        $ticket->load(['cliente', 'producto', 'assignedUser']);

        return Inertia::render('Backend/Tickets/Show', [
            'ticket' => $ticket,
        ]);
    }

    public function exportCsv(Request $request)
    {
        $ownerId = Auth::user()->getOwnerId();
        $tickets = Ticket::with(['cliente', 'producto'])
            ->where('owner_id', $ownerId)
            ->orderBy('created_at', 'desc')
            ->get();

        return Excel::download(new TicketsExport($tickets), 'tickets_'.now()->format('Ymd_His').'.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportExcel(Request $request)
    {
        $ownerId = Auth::user()->getOwnerId();
        $tickets = Ticket::with(['cliente', 'producto'])
            ->where('owner_id', $ownerId)
            ->orderBy('created_at', 'desc')
            ->get();

        return Excel::download(new TicketsExport($tickets), 'tickets_'.now()->format('Ymd_His').'.xlsx');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        try {
            Excel::import(new TicketsImport, $request->file('archivo'));

            return redirect()->back()->with('success', 'Tickets importados correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error en el formato del archivo: '.$e->getMessage());
        }
    }

    public function importExcel(Request $request): RedirectResponse
    {
        return $this->importCsv($request);
    }

    protected function authorizeOwner(Ticket $ticket): void
    {
        if ($ticket->owner_id !== Auth::user()->getOwnerId()) {
            abort(403);
        }
    }

    protected function sendTicketNotifications(Ticket $ticket): void
    {
        if ($ticket->assigned_user_id) {
            $assigned = User::find($ticket->assigned_user_id);
            if ($assigned) {
                NotificationHelper::send($assigned, new NuevoTicketNotification($ticket));
            }
        }

        $owner = User::find($ticket->owner_id);
        if ($owner && $owner->id !== $ticket->assigned_user_id) {
            NotificationHelper::send($owner, new NuevoTicketNotification($ticket));
        }
    }
}
