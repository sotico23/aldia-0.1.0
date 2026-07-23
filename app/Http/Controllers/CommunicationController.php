<?php

namespace App\Http\Controllers;

use App\Contracts\HasMessageThread;
use App\Events\CommunicationMessageSent;
use App\Events\MensajeEnviado;
use App\Events\MensajeInternoEnviado;
use App\Events\MensajesLeidos;
use App\Events\MensajesLeidosConversation;
use App\Events\MessageSent;
use App\Helpers\NotificationHelper;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\Conversacion;
use App\Models\Conversation;
use App\Models\Mensaje;
use App\Models\MensajeConversacion;
use App\Models\Message;
use App\Models\PublicProfile;
use App\Models\User;
use App\Notifications\NuevoMensajeChatNotification;
use App\Notifications\NuevoMensajeChatPedidoNotification;
use App\Notifications\NuevoMensajeInternoNotification;
use App\Scopes\OwnerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CommunicationController extends Controller
{
    public function inbox()
    {
        $user = Auth::user();

        $unifiedConversations = CommunicationConversation::where(function ($q) use ($user) {
            $q->where('metadata->comprador_id', $user->id)
                ->orWhere('metadata->vendedor_id', $user->id)
                ->orWhere('metadata->buyer_id', $user->id)
                ->orWhere('metadata->store_user_id', $user->id)
                ->orWhere('metadata->user_a_id', $user->id)
                ->orWhere('metadata->user_b_id', $user->id);
        })->get();

        $misPedidos = $unifiedConversations->where('type', 'order')
            ->filter(fn ($c) => in_array($user->id, [
                $c->metadata['comprador_id'] ?? null,
                $c->metadata['vendedor_id'] ?? null,
            ]))
            ->values();

        $profileIds = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('user_id', $user->id)
            ->pluck('id');

        $misConsultas = $unifiedConversations->where('type', 'marketplace')
            ->filter(fn ($c) => ($c->metadata['buyer_id'] ?? null) === $user->id)
            ->values();

        $ventasYConsultas = $unifiedConversations->where('type', 'marketplace')
            ->filter(fn ($c) => in_array($c->metadata['store_profile_id'] ?? null, $profileIds->toArray()))
            ->values();

        $internal = $unifiedConversations->where('type', 'internal')
            ->filter(fn ($c) => in_array($user->id, [
                $c->metadata['user_a_id'] ?? null,
                $c->metadata['user_b_id'] ?? null,
            ]))
            ->values()
            ->map(fn ($c) => $this->formatConversation($c, $user));

        return Inertia::render('marketplace/ChatInbox', [
            'misPedidos' => $misPedidos->map(fn ($c) => $this->formatConversation($c, $user)),
            'misVentas' => $misPedidos, // Same data, filtered differently
            'misConsultas' => $misConsultas->map(fn ($c) => $this->formatConversation($c, $user)),
            'ventasYConsultas' => $ventasYConsultas->map(fn ($c) => $this->formatConversation($c, $user)),
            'internal' => $internal,
        ]);
    }

    public function messages(string $type, int $id): JsonResponse
    {
        $user = Auth::user();

        $thread = $this->resolveThread($type, $id);

        if (! $thread) {
            return response()->json(['error' => 'Conversación no encontrada'], 404);
        }

        if ($user->cannot('view', $thread)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $messages = $this->getMessagesForThread($type, $thread);
        $updated = $thread->markAsRead($user);

        if ($updated > 0) {
            $this->broadcastReadReceipt($type, $thread, $user);
        }

        return response()->json([
            'messages' => $messages,
            'thread' => $this->formatConversation($thread, $user),
        ]);
    }

    public function send(Request $request, string $type, int $id): JsonResponse
    {
        $user = Auth::user();

        $thread = $this->resolveThread($type, $id);

        if (! $thread) {
            return response()->json(['error' => 'Conversación no encontrada'], 404);
        }

        if ($user->cannot('sendMessage', $thread)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $message = match ($type) {
            'order' => $this->sendOrderMessage($request, $id, $user),
            'marketplace' => $this->sendMarketplaceMessage($request, $id, $user),
            'internal' => $this->sendInternalMessage($request, $id, $user),
            default => null,
        };

        if (! $message) {
            return response()->json(['error' => 'No se pudo enviar el mensaje'], 500);
        }

        return response()->json(['message' => $message], 201);
    }

    public function markRead(string $type, int $id): JsonResponse
    {
        $user = Auth::user();

        $thread = $this->resolveThread($type, $id);

        if (! $thread) {
            return response()->json(['error' => 'Conversación no encontrada'], 404);
        }

        if ($user->cannot('view', $thread)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $updated = $thread->markAsRead($user);

        if ($updated > 0) {
            $this->broadcastReadReceipt($type, $thread, $user);
        }

        return response()->json(['marked_read' => $updated]);
    }

    private function resolveThread(string $type, int $id): ?HasMessageThread
    {
        if (in_array($type, ['order', 'marketplace', 'internal'])) {
            $query = CommunicationConversation::where('type', $type);

            if ($type === 'internal') {
                $query->where(function ($q) use ($id) {
                    $q->where('metadata->user_a_id', $id)
                        ->orWhere('metadata->user_b_id', $id);
                });
            } else {
                $query->where('metadata->legacy_id', $id);
            }

            $unified = $query->first();

            if ($unified) {
                return $unified;
            }
        }

        return match ($type) {
            'order' => Conversacion::find($id),
            'marketplace' => Conversation::find($id),
            'internal' => User::find($id),
            default => null,
        };
    }

    private function getMessagesForThread(string $type, HasMessageThread $thread): array
    {
        if ($thread instanceof CommunicationConversation) {
            return $thread->messages()
                ->with('sender:id,name,profile_photo_path')
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn ($m) => $this->formatMessage($m, $type))
                ->toArray();
        }

        return match ($type) {
            'order' => $thread->mensajes()
                ->with('sender:id,name,profile_photo_path')
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn ($m) => $this->formatMessage($m, $type))
                ->toArray(),
            'marketplace' => Conversation::find($thread->id)?->messages()
                ->with('sender:id,name,profile_photo_path')
                ->oldest()
                ->get()
                ->map(fn ($m) => $this->formatMessage($m, $type))
                ->toArray() ?? [],
            'internal' => Mensaje::where(function ($q) use ($thread) {
                $q->where('sender_id', $thread->id)->where('receiver_id', Auth::id());
            })->orWhere(function ($q) use ($thread) {
                $q->where('sender_id', Auth::id())->where('receiver_id', $thread->id);
            })
                ->with(['sender:id,name,profile_photo_path', 'receiver:id,name,profile_photo_path'])
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn ($m) => $this->formatMessage($m, 'internal'))
                ->toArray(),
            default => [],
        };
    }

    private function broadcastReadReceipt(string $type, HasMessageThread $thread, User $user): void
    {
        $legacyId = $thread instanceof CommunicationConversation
            ? ($thread->metadata['legacy_id'] ?? $thread->id)
            : $thread->id;

        $event = match ($type) {
            'order' => new MensajesLeidos($legacyId, $user->id),
            'marketplace' => new MensajesLeidosConversation($legacyId, $user->id),
            'internal' => new MensajesLeidos(0, $user->id),
        };

        broadcast($event)->toOthers();
    }

    private function sendOrderMessage(Request $request, int $conversacionId, User $user): array
    {
        $request->validate([
            'contenido' => 'nullable|string|max:5000',
            'archivo' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,txt|max:10240',
        ]);

        if (! $request->contenido && ! $request->hasFile('archivo')) {
            abort(422, 'Debe enviar un mensaje o un archivo');
        }

        $conversacion = Conversacion::find($conversacionId);

        if (! $conversacion) {
            abort(404, 'Conversación no encontrada');
        }

        $receiverId = $user->id === $conversacion->comprador_id
            ? $conversacion->vendedor_id
            : $conversacion->comprador_id;

        $filePath = null;
        if ($request->hasFile('archivo')) {
            $filePath = $request->file('archivo')->store('chat/conversacion_'.$conversacionId, 'public');
        }

        $mensaje = MensajeConversacion::create([
            'conversacion_id' => $conversacionId,
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'contenido' => $request->contenido ?? '',
            'file_path' => $filePath,
        ]);

        $mensaje->load('sender:id,name,profile_photo_path');

        $receptor = User::find($receiverId);
        if ($receptor) {
            $conversacion->load('pedido');
            NotificationHelper::send($receptor, new NuevoMensajeChatPedidoNotification($conversacion, $mensaje));
        }

        broadcast(new MensajeEnviado($mensaje))->toOthers();
        broadcast(new CommunicationMessageSent($mensaje, 'order', $conversacionId, $user->id))->toOthers();

        return $this->formatMessage($mensaje, 'order');
    }

    private function sendMarketplaceMessage(Request $request, int $conversationId, User $user): array
    {
        $request->validate([
            'body' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,gif,webp|max:5120',
        ]);

        $conversation = Conversation::find($conversationId);

        if (! $conversation) {
            abort(404, 'Conversación no encontrada');
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('chat-images', 'public');
        }

        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_id' => $user->id,
            'body' => $request->body ?? '',
            'image_path' => $imagePath,
        ]);

        $message->load('sender');

        $profile = $conversation->store()->withoutGlobalScope(OwnerScope::class)->first();
        $receiverId = $conversation->buyer_id === $user->id
            ? ($profile?->user_id ?? $conversation->owner_id)
            : $conversation->buyer_id;

        $receiver = User::find($receiverId);
        if ($receiver) {
            NotificationHelper::send($receiver, new NuevoMensajeChatNotification($conversation, $message));
        }

        broadcast(new MessageSent($message))->toOthers();
        broadcast(new CommunicationMessageSent($message, 'marketplace', $conversationId, $user->id))->toOthers();

        return $this->formatMessage($message, 'marketplace');
    }

    private function sendInternalMessage(Request $request, int $receiverId, User $user): array
    {
        $request->validate([
            'contenido' => 'nullable|string|max:5000',
            'archivo' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,txt|max:10240',
        ]);

        if (! $request->contenido && ! $request->hasFile('archivo')) {
            abort(422, 'Debe enviar un mensaje o un archivo');
        }

        $receiver = User::find($receiverId);

        if (! $receiver) {
            abort(404, 'Usuario no encontrado');
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

        $mensaje = Mensaje::create(array_merge([
            'sender_id' => $user->id,
            'receiver_id' => $receiver->id,
            'contenido' => $request->contenido ?? '',
        ], $archivoData));

        $mensaje->load(['sender:id,name,profile_photo_path', 'receiver:id,name,profile_photo_path']);

        NotificationHelper::send($receiver, new NuevoMensajeInternoNotification($mensaje));
        broadcast(new MensajeInternoEnviado($mensaje))->toOthers();

        return $this->formatMessage($mensaje, 'internal');
    }

    private function formatConversation(HasMessageThread $thread, User $user): array
    {
        if ($thread instanceof CommunicationConversation) {
            $title = $thread->title ?? $this->inferConversationTitle($thread, $user);
            $other = $this->getUnifiedOtherParticipant($thread, $user);

            return [
                'id' => $thread->metadata['legacy_id'] ?? $thread->id,
                'type' => $thread->type,
                'title' => $title,
                'last_message' => $thread->latestMessageContent(),
                'last_message_at' => $thread->lastMessageAt(),
                'unread_count' => $thread->unreadCount($user),
                'other_user' => $other ? ['id' => $other->id, 'name' => $other->name] : null,
            ];
        }

        $data = [
            'id' => $thread->id,
            'type' => $thread->type(),
            'title' => method_exists($thread, 'titulo') ? $thread->titulo : null,
            'last_message' => $thread->latestMessageContent(),
            'last_message_at' => $thread->lastMessageAt(),
            'unread_count' => $thread->unreadCount($user),
            'other_user' => null,
        ];

        $other = $thread->otherParticipant($user);
        if ($other) {
            $data['other_user'] = [
                'id' => $other->id,
                'name' => $other->name,
            ];
        }

        return $data;
    }

    private function inferConversationTitle(CommunicationConversation $conversation, User $user): string
    {
        $meta = $conversation->metadata ?? [];

        return match ($conversation->type) {
            'order' => 'Pedido #'.($meta['pedido_id'] ?? $conversation->id),
            'marketplace' => 'Consulta con tienda',
            'internal' => 'Chat interno',
            default => 'Conversación',
        };
    }

    private function getUnifiedOtherParticipant(CommunicationConversation $conversation, User $user): ?User
    {
        $meta = $conversation->metadata ?? [];

        $otherIds = match ($conversation->type) {
            'order' => [$meta['comprador_id'] ?? null, $meta['vendedor_id'] ?? null],
            'marketplace' => [$meta['buyer_id'] ?? null, $meta['store_user_id'] ?? null],
            'internal' => [$meta['user_a_id'] ?? null, $meta['user_b_id'] ?? null],
            default => [],
        };

        foreach ($otherIds as $id) {
            if ($id && (int) $id !== $user->id) {
                return User::find($id);
            }
        }

        return null;
    }

    private function formatMessage(mixed $message, string $type): array
    {
        if ($message instanceof CommunicationMessage) {
            return [
                'id' => $message->id,
                'type' => $message->type,
                'sender_id' => $message->sender_id,
                'sender' => $message->sender ? [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'profile_photo_path' => $message->sender->profile_photo_path,
                ] : null,
                'receiver_id' => $message->receiver_id,
                'content' => $message->content,
                'file_url' => $message->file_path ? asset('storage/'.$message->file_path) : null,
                'file_name' => $message->file_name,
                'is_image' => $message->file_type ? str_starts_with($message->file_type, 'image/') : false,
                'read_at' => $message->read_at,
                'created_at' => $message->created_at,
            ];
        }

        return match ($type) {
            'order' => [
                'id' => $message->id,
                'type' => 'order',
                'sender_id' => $message->sender_id,
                'sender' => $message->sender ? [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'profile_photo_path' => $message->sender->profile_photo_path,
                ] : null,
                'content' => $message->contenido,
                'file_url' => $message->file_path ? asset('storage/'.$message->file_path) : null,
                'file_name' => $message->file_path ? basename($message->file_path) : null,
                'is_image' => $message->file_path ? in_array(pathinfo($message->file_path, PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'gif', 'webp']) : null,
                'created_at' => $message->created_at,
            ],
            'marketplace' => [
                'id' => $message->id,
                'type' => 'marketplace',
                'sender_id' => $message->sender_id,
                'sender' => $message->sender ? [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'profile_photo_path' => $message->sender->profile_photo_path,
                ] : null,
                'content' => $message->body ?? '',
                'file_url' => $message->image_path ? asset('storage/'.$message->image_path) : null,
                'file_name' => $message->image_path ? basename($message->image_path) : null,
                'is_image' => (bool) $message->image_path,
                'read_at' => $message->read_at,
                'created_at' => $message->created_at,
            ],
            'internal' => [
                'id' => $message->id,
                'type' => 'internal',
                'sender_id' => $message->sender_id,
                'sender' => $message->sender ? [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'profile_photo_path' => $message->sender->profile_photo_path,
                ] : null,
                'receiver_id' => $message->receiver_id,
                'receiver' => $message->receiver ? [
                    'id' => $message->receiver->id,
                    'name' => $message->receiver->name,
                    'profile_photo_path' => $message->receiver->profile_photo_path,
                ] : null,
                'content' => $message->contenido,
                'file_url' => $message->archivo_path ? asset('storage/'.$message->archivo_path) : null,
                'file_name' => $message->archivo_nombre,
                'is_image' => $message->archivo_tipo ? str_starts_with($message->archivo_tipo, 'image/') : false,
                'leido' => $message->leido,
                'created_at' => $message->created_at,
            ],
            default => [],
        };
    }
}
