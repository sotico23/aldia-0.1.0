<?php

namespace App\Http\Controllers\Backend;

use App\Events\CommunicationMessageSent;
use App\Events\MensajeEnviado;
use App\Events\MensajesLeidos;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Conversacion;
use App\Models\MensajeConversacion;
use App\Models\User;
use App\Notifications\NuevoMensajeChatPedidoNotification;
use App\Scopes\OwnerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ConversacionPedidoController extends Controller
{
    public function show(Conversacion $conversacion): InertiaResponse
    {
        if (Auth::user()->cannot('view', $conversacion)) {
            abort(403);
        }

        $conversacion->load([
            'pedido' => function ($query) {
                $query->withoutGlobalScope(OwnerScope::class);
            },
            'comprador:id,name',
            'vendedor:id,name',
            'publicProfile' => function ($query) {
                $query->withoutGlobalScope(OwnerScope::class);
            },
            'mensajes' => function ($query) {
                $query->with('sender:id,name,profile_photo_path')->orderBy('created_at', 'asc');
            },
        ]);

        if (! $conversacion->pedido) {
            abort(404, 'Pedido no encontrado');
        }

        $otroUsuario = Auth::id() === $conversacion->comprador_id
            ? $conversacion->vendedor
            : $conversacion->comprador;

        return Inertia::render('marketplace/ChatPedido', [
            'conversacion' => [
                'id' => $conversacion->id,
                'pedido' => [
                    'id' => $conversacion->pedido->id,
                    'numero_pedido' => $conversacion->pedido->numero_pedido,
                    'estado' => $conversacion->pedido->estado,
                    'total' => $conversacion->pedido->total,
                ],
                'comprador' => [
                    'id' => $conversacion->comprador->id,
                    'name' => $conversacion->comprador->name,
                ],
                'publicProfile' => [
                    'id' => $conversacion->publicProfile->id,
                    'title' => $conversacion->publicProfile->title,
                    'slug' => $conversacion->publicProfile->slug,
                    'user_id' => $conversacion->publicProfile->user_id,
                ],
                'otro_usuario' => $otroUsuario ? [
                    'id' => $otroUsuario->id,
                    'name' => $otroUsuario->name,
                ] : null,
            ],
            'mensajes' => $conversacion->mensajes->map(function ($msg) {
                $data = [
                    'id' => $msg->id,
                    'sender_id' => $msg->sender_id,
                    'contenido' => $msg->contenido,
                    'created_at' => $msg->created_at->toISOString(),
                    'sender' => [
                        'id' => $msg->sender->id,
                        'name' => $msg->sender->name,
                        'profile_photo_path' => $msg->sender->profile_photo_path,
                    ],
                ];
                if ($msg->file_path) {
                    $data['file_url'] = asset('storage/'.$msg->file_path);
                    $data['file_name'] = basename($msg->file_path);
                    $data['is_image'] = in_array(pathinfo($msg->file_path, PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                }

                return $data;
            }),
        ]);
    }

    public function getMensajes(Conversacion $conversacion): JsonResponse
    {
        // Verificar permisos
        if (Auth::user()->cannot('view', $conversacion)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $mensajes = $conversacion->mensajes()
            ->with('sender:id,name,profile_photo_path')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                if ($msg->file_path) {
                    $msg->file_url = asset('storage/'.$msg->file_path);
                    $msg->file_name = basename($msg->file_path);
                    $msg->is_image = in_array(pathinfo($msg->file_path, PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                }

                return $msg;
            });

        // Marcar mensajes como leídos
        $updated = $conversacion->mensajes()
            ->where(function ($query) {
                $query->where('receiver_id', Auth::id());
            })
            ->where(function ($query) {
                $query->where('leido', false);
            })
            ->update(['leido' => true]);

        if ($updated > 0) {
            broadcast(new MensajesLeidos($conversacion->id, Auth::id()))->toOthers();
        }

        return response()->json(['mensajes' => $mensajes]);
    }

    public function enviarMensaje(Request $request, Conversacion $conversacion): JsonResponse
    {
        // Verificar permisos
        if (Auth::user()->cannot('sendMessage', $conversacion)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'contenido' => 'nullable|string|max:5000',
            'archivo' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,txt|max:10240',
        ]);

        if (! $request->contenido && ! $request->hasFile('archivo')) {
            return response()->json(['error' => 'Debe enviar un mensaje o un archivo'], 422);
        }

        $senderId = Auth::id();
        $receiverId = $senderId === $conversacion->comprador_id ? $conversacion->vendedor_id : $conversacion->comprador_id;

        $filePath = null;
        if ($request->hasFile('archivo')) {
            $filePath = $request->file('archivo')->store('chat/conversacion_'.$conversacion->id, 'public');
        }

        $mensaje = MensajeConversacion::create([
            'conversacion_id' => $conversacion->id,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'contenido' => $request->contenido ?? '',
            'file_path' => $filePath,
        ]);

        $mensaje->load('sender:id,name,profile_photo_path');

        // Notificar al receptor del mensaje
        $receptor = User::find($receiverId);
        if ($receptor) {
            try {
                $conversacion->load('pedido');
                NotificationHelper::send($receptor, new NuevoMensajeChatPedidoNotification($conversacion, $mensaje));
            } catch (\Exception $e) {
                \Log::error('Error enviando notificación: '.$e->getMessage());
            }
        }

        if ($mensaje->file_path) {
            $mensaje->file_url = asset('storage/'.$mensaje->file_path);
            $mensaje->file_name = basename($mensaje->file_path);
            $mensaje->is_image = in_array(pathinfo($mensaje->file_path, PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        }

        broadcast(new MensajeEnviado($mensaje))->toOthers();
        broadcast(new CommunicationMessageSent($mensaje, 'order', $conversacion->id, Auth::id()))->toOthers();

        return response()->json(['mensaje' => $mensaje], 201);
    }
}
