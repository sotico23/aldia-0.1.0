<?php

namespace App\Http\Controllers;

use App\Events\CommunicationMessageSent;
use App\Events\MensajesLeidosConversation;
use App\Events\MessageSent;
use App\Helpers\NotificationHelper;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Pedido as PedidoModel;
use App\Models\PublicProfile;
use App\Models\User;
use App\Notifications\NuevoMensajeChatNotification;
use App\Scopes\OwnerScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ChatController extends Controller
{
    /**
     * Display a listing of conversations for the current user.
     */
    public function index()
    {
        $user = Auth::user();

        // Mis compras: soy el comprador en pedidos
        $misPedidos = PedidoModel::query()
            ->with([
                'cliente:id,name',
                'publicProfile' => function ($q) {
                    $q->withoutGlobalScope(OwnerScope::class)->select('id', 'user_id', 'owner_id', 'title', 'slug');
                },
                'conversacion.mensajes' => function ($q) {
                    $q->latest()->limit(1);
                },
            ])
            ->where('cliente_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Mis ventas: soy el vendedor en pedidos
        $misVentas = PedidoModel::query()
            ->with([
                'cliente:id,name',
                'publicProfile' => function ($q) {
                    $q->withoutGlobalScope(OwnerScope::class)->select('id', 'user_id', 'owner_id', 'title', 'slug');
                },
                'conversacion.mensajes' => function ($q) {
                    $q->latest()->limit(1);
                },
            ])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Consultas: conversaciones directas como comprador (sin pedido)
        $misConsultas = Conversation::where('buyer_id', $user->id)
            ->with(['store' => function ($q) {
                $q->withoutGlobalScope(OwnerScope::class)->select('id', 'user_id', 'owner_id', 'title', 'slug');
            }, 'latestMessage'])
            ->latest()
            ->get();

        // Ventas y consultas: conversaciones directas como tienda
        $profileIds = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('user_id', $user->id)
            ->pluck('id');

        $ventasYConsultas = Conversation::whereIn('store_profile_id', $profileIds)
            ->with(['buyer:id,name', 'latestMessage'])
            ->latest()
            ->get();

        return Inertia::render('marketplace/ChatInbox', [
            'misPedidos' => $misPedidos,
            'misVentas' => $misVentas,
            'misConsultas' => $misConsultas,
            'ventasYConsultas' => $ventasYConsultas,
        ]);
    }

    /**
     * Start or get a conversation with a store.
     */
    public function start(Request $request, $slug)
    {
        $profile = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('slug', $slug)
            ->firstOrFail();

        $user = Auth::user();

        // Don't chat with yourself
        if ($profile->user_id === $user->id) {
            return back()->with('error', 'No puedes chatear con tu propia tienda.');
        }

        $conversation = Conversation::firstOrCreate([
            'buyer_id' => $user->id,
            'store_profile_id' => $profile->id,
        ]);

        return redirect()->route('chat.show', $conversation->id);
    }

    /**
     * Display a specific conversation.
     */
    public function show(Conversation $conversation)
    {
        $user = Auth::user();

        // Authorization via Policy
        if ($user->cannot('view', $conversation)) {
            abort(403);
        }

        $messages = $conversation->messages()
            ->with('sender')
            ->oldest()
            ->get();

        // Mark messages as read if receiver is viewing
        $updated = $conversation->messages()
            ->where(fn ($q) => $q->where('sender_id', '!=', $user->id))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated > 0) {
            broadcast(new MensajesLeidosConversation($conversation->id, $user->id))->toOthers();
        }

        return Inertia::render('marketplace/ChatShow', [
            'conversation' => $conversation->load(['buyer', 'store' => function ($q) {
                $q->withoutGlobalScope(OwnerScope::class);
            }]),
            'messages' => $messages,
        ]);
    }

    /**
     * Send a message.
     */
    public function sendMessage(Request $request, Conversation $conversation)
    {
        $request->validate([
            'body' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,gif,webp|max:5120',
        ]);

        $user = Auth::user();

        // Authorization via Policy
        if ($user->cannot('sendMessage', $conversation)) {
            abort(403);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('chat-images', 'public');
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $request->body ?? '',
            'image_path' => $imagePath,
        ]);

        $message->load('sender');

        $receiverId = $conversation->buyer_id === $user->id
            ? $profile->user_id
            : $conversation->buyer_id;

        $receiver = User::find($receiverId);
        if ($receiver) {
            NotificationHelper::send($receiver, new NuevoMensajeChatNotification($conversation, $message));
        }

        broadcast(new MessageSent($message))->toOthers();
        broadcast(new CommunicationMessageSent($message, 'marketplace', $conversation->id, $user->id))->toOthers();

        return back();
    }
}
