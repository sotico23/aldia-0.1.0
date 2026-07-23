<?php

namespace App\Http\Controllers\Backend;

use App\Events\CommunicationMessageSent;
use App\Events\MensajeInternoEnviado;
use App\Events\MensajesLeidos;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Mensaje;
use App\Models\User;
use App\Notifications\NuevoMensajeInternoNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MensajeController extends Controller
{
    public function index()
    {
        // Si es una petición Inertia, mostrar la página
        if (request()->header('X-Inertia') || request()->wantsJson() === false) {
            return Inertia::render('Backend/Mensajes/Index');
        }

        return $this->getConversaciones();
    }

    private function getConversaciones()
    {
        $userId = Auth::id();

        $conversaciones = Mensaje::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->with(['sender:id,name,profile_photo_path', 'receiver:id,name,profile_photo_path'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($msg) use ($userId) {
                $otherUserId = $msg->sender_id === $userId ? $msg->receiver_id : $msg->sender_id;

                return $otherUserId;
            })
            ->map(function ($mensajes, $otroUsuarioId) use ($userId) {
                $ultimoMensaje = $mensajes->first();
                $sinLeer = $mensajes->where('receiver_id', $userId)->where('leido', false)->count();

                return [
                    'usuario_id' => $otroUsuarioId,
                    'usuario_nombre' => $ultimoMensaje->sender_id === $userId
                        ? $ultimoMensaje->receiver->name
                        : $ultimoMensaje->sender->name,
                    'usuario_foto' => $ultimoMensaje->sender_id === $userId
                        ? $ultimoMensaje->receiver->profile_photo_path
                        : $ultimoMensaje->sender->profile_photo_path,
                    'ultimo_mensaje' => $ultimoMensaje->contenido,
                    'fecha_ultimo' => $ultimoMensaje->created_at,
                    'sin_leer' => $sinLeer,
                ];
            })
            ->values();

        $mensajesSinLeer = Mensaje::where('receiver_id', $userId)
            ->where('leido', false)
            ->count();

        return response()->json([
            'conversaciones' => $conversaciones,
            'total_sin_leer' => $mensajesSinLeer,
        ]);
    }

    public function conversacion(int $usuarioId): JsonResponse
    {
        $userId = Auth::id();
        $ownerId = Auth::user()->getOwnerId();

        // Solo puede ver mensajes de usuarios de su empresa
        $otroUsuario = User::find($usuarioId);
        if (! $otroUsuario || $otroUsuario->getOwnerId() !== $ownerId) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $mensajes = Mensaje::where(function ($query) use ($userId, $usuarioId) {
            $query->where('sender_id', $userId)->where('receiver_id', $usuarioId);
        })
            ->orWhere(function ($query) use ($userId, $usuarioId) {
                $query->where('sender_id', $usuarioId)->where('receiver_id', $userId);
            })
            ->with(['sender:id,name,profile_photo_path', 'receiver:id,name,profile_photo_path'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Marcar como leídos
        $updated = Mensaje::where('sender_id', $usuarioId)
            ->where('receiver_id', $userId)
            ->where('leido', false)
            ->update(['leido' => true]);

        if ($updated > 0) {
            broadcast(new MensajesLeidos(0, $userId))->toOthers();
        }

        return response()->json([
            'mensajes' => $mensajes,
            'otro_usuario' => [
                'id' => $otroUsuario->id,
                'name' => $otroUsuario->name,
                'profile_photo_path' => $otroUsuario->profile_photo_path,
            ],
        ]);
    }

    public function usuarios(): JsonResponse
    {
        $userId = Auth::id();
        $ownerId = Auth::user()->getOwnerId();

        // Obtener usuarios de la misma empresa (excluyendo el actual)
        $usuarios = User::where('id', '!=', $userId)
            ->get()
            ->filter(function ($u) use ($ownerId) {
                return $u->getOwnerId() === $ownerId;
            })
            ->values();

        return response()->json(['usuarios' => $usuarios]);
    }

    public function enviar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'receiver_id' => 'required|exists:users,id',
                'contenido' => 'nullable|string|max:5000',
                'archivo' => 'nullable|file|max:10240', // 10MB max
            ]);

            if (! $request->contenido && ! $request->hasFile('archivo')) {
                return response()->json(['error' => 'Debe enviar un mensaje o un archivo'], 422);
            }

            $senderId = Auth::id();
            $receiverId = $request->receiver_id;
            $receiver = User::find($receiverId);

            // Verificar que el receptor existe
            if (! $receiver) {
                return response()->json(['error' => 'Usuario receptor no encontrado'], 404);
            }

            $archivoData = [];
            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $path = $file->store('mensajes/adjuntos', 'public');
                $archivoData = [
                    'archivo_path' => $path,
                    'archivo_nombre' => $file->getClientOriginalName(),
                    'archivo_tipo' => $file->getClientMimeType(),
                ];
            }

            // Crear el mensaje
            $mensaje = Mensaje::create(array_merge([
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'contenido' => $request->contenido ?? '',
            ], $archivoData));

            $mensaje->load(['sender:id,name,profile_photo_path', 'receiver:id,name,profile_photo_path']);

            NotificationHelper::send($receiver, new NuevoMensajeInternoNotification($mensaje));

            broadcast(new MensajeInternoEnviado($mensaje))->toOthers();
            broadcast(new CommunicationMessageSent($mensaje, 'internal', $mensaje->id, Auth::id()))->toOthers();

            return response()->json(['mensaje' => $mensaje], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
