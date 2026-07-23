<?php

namespace App\Console\Commands;

use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\Conversacion;
use App\Models\Conversation;
use App\Models\Mensaje;
use App\Models\User;
use App\Scopes\OwnerScope;
use Illuminate\Console\Command;

class ChatMigrateToUnified extends Command
{
    protected $signature = 'chat:migrate-to-unified {--force : Ejecutar sin confirmación}';

    protected $description = 'Migra datos de los 3 sistemas de chat legacy a las tablas unificadas';

    public function handle(): int
    {
        if (CommunicationConversation::count() > 0 && ! $this->option('force')) {
            $this->warn('Las tablas unificadas ya tienen datos. Usa --force para migrar de todas formas.');

            if (! $this->confirm('¿Continuar de todas formas? Se saltarán las conversaciones ya migradas por ID.')) {
                return Command::FAILURE;
            }
        }

        $this->info('Migrando conversaciones de pedidos (order)...');
        $this->migrateOrderConversations();

        $this->info('Migrando conversaciones de tienda (marketplace)...');
        $this->migrateMarketplaceConversations();

        $this->info('Migrando mensajes internos (internal)...');
        $this->migrateInternalConversations();

        $this->info('✅ Migración completada.');
        $this->newLine();
        $this->table(
            ['Tipo', 'Conversaciones', 'Mensajes'],
            [
                ['order', CommunicationConversation::where('type', 'order')->count(), CommunicationMessage::where('type', 'order')->count()],
                ['marketplace', CommunicationConversation::where('type', 'marketplace')->count(), CommunicationMessage::where('type', 'marketplace')->count()],
                ['internal', CommunicationConversation::where('type', 'internal')->count(), CommunicationMessage::where('type', 'internal')->count()],
            ]
        );

        return Command::SUCCESS;
    }

    private function migrateOrderConversations(): void
    {
        $bar = $this->output->createProgressBar(Conversacion::count());
        $bar->start();

        Conversacion::with('mensajes')->chunk(100, function ($conversaciones) use ($bar) {
            foreach ($conversaciones as $conv) {
                $existing = CommunicationConversation::where('type', 'order')
                    ->where('metadata->legacy_id', $conv->id)
                    ->where('metadata->table', 'conversaciones')
                    ->first();

                if ($existing) {
                    $bar->advance();

                    continue;
                }

                $unified = CommunicationConversation::create([
                    'type' => 'order',
                    'title' => $conv->titulo ?? 'Pedido #'.($conv->pedido_id ?? $conv->id),
                    'metadata' => [
                        'legacy_id' => $conv->id,
                        'table' => 'conversaciones',
                        'pedido_id' => $conv->pedido_id,
                        'public_profile_id' => $conv->public_profile_id,
                        'comprador_id' => $conv->comprador_id,
                        'vendedor_id' => $conv->vendedor_id,
                        'owner_id' => $conv->owner_id,
                    ],
                    'last_message_at' => $conv->ultimo_mensaje_at,
                ]);

                foreach ($conv->mensajes as $msg) {
                    CommunicationMessage::create([
                        'communication_conversation_id' => $unified->id,
                        'type' => 'order',
                        'sender_id' => $msg->sender_id,
                        'receiver_id' => $msg->receiver_id,
                        'content' => $msg->contenido ?? '',
                        'file_path' => $msg->file_path,
                        'read_at' => $msg->leido ? $msg->updated_at : null,
                        'created_at' => $msg->created_at,
                        'updated_at' => $msg->updated_at,
                    ]);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migrateMarketplaceConversations(): void
    {
        $bar = $this->output->createProgressBar(Conversation::count());
        $bar->start();

        Conversation::with('messages', 'store')->chunk(100, function ($conversations) use ($bar) {
            foreach ($conversations as $conv) {
                $existing = CommunicationConversation::where('type', 'marketplace')
                    ->where('metadata->legacy_id', $conv->id)
                    ->where('metadata->table', 'conversations')
                    ->first();

                if ($existing) {
                    $bar->advance();

                    continue;
                }

                $store = $conv->store()->withoutGlobalScope(OwnerScope::class)->first();

                $unified = CommunicationConversation::create([
                    'type' => 'marketplace',
                    'title' => 'Consulta con '.($store?->title ?? 'Tienda'),
                    'metadata' => [
                        'legacy_id' => $conv->id,
                        'table' => 'conversations',
                        'buyer_id' => $conv->buyer_id,
                        'store_profile_id' => $conv->store_profile_id,
                        'store_user_id' => $store?->user_id,
                        'owner_id' => $conv->owner_id,
                    ],
                ]);

                foreach ($conv->messages as $msg) {
                    $receiverId = $msg->sender_id === $conv->buyer_id
                        ? ($store?->user_id ?? $conv->owner_id)
                        : $conv->buyer_id;

                    CommunicationMessage::create([
                        'communication_conversation_id' => $unified->id,
                        'type' => 'marketplace',
                        'sender_id' => $msg->sender_id,
                        'receiver_id' => $receiverId,
                        'content' => $msg->body ?? '',
                        'file_path' => $msg->image_path,
                        'read_at' => $msg->read_at,
                        'created_at' => $msg->created_at,
                        'updated_at' => $msg->updated_at,
                    ]);
                }

                if ($last = $conv->messages()->latest()->first()) {
                    $unified->update(['last_message_at' => $last->created_at]);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migrateInternalConversations(): void
    {
        $pairs = Mensaje::selectRaw('LEAST(sender_id, receiver_id) as user_a, GREATEST(sender_id, receiver_id) as user_b')
            ->distinct()
            ->pluck('user_a', 'user_b');

        $uniquePairs = collect();
        Mensaje::selectRaw('LEAST(sender_id, receiver_id) as user_a, GREATEST(sender_id, receiver_id) as user_b')
            ->distinct()
            ->get()
            ->each(function ($pair) use ($uniquePairs) {
                $key = $pair->user_a.'-'.$pair->user_b;
                if (! $uniquePairs->has($key)) {
                    $uniquePairs->put($key, ['user_a' => $pair->user_a, 'user_b' => $pair->user_b]);
                }
            });

        $bar = $this->output->createProgressBar($uniquePairs->count());
        $bar->start();

        foreach ($uniquePairs as $pair) {
            $userA = (int) $pair['user_a'];
            $userB = (int) $pair['user_b'];

            $userIds = [$userA, $userB];
            sort($userIds);
            $pairKey = $userIds[0].'-'.$userIds[1];

            $existing = CommunicationConversation::where('type', 'internal')
                ->where('metadata->pair_key', $pairKey)
                ->first();

            if ($existing) {
                $bar->advance();

                continue;
            }

            $userModelA = User::find($userA);
            $userModelB = User::find($userB);

            $title = 'Chat: '.($userModelA?->name ?? 'Usuario #'.$userA)
                .' & '.($userModelB?->name ?? 'Usuario #'.$userB);

            $unified = CommunicationConversation::create([
                'type' => 'internal',
                'title' => $title,
                'metadata' => [
                    'user_a_id' => $userA,
                    'user_b_id' => $userB,
                    'pair_key' => $pairKey,
                ],
            ]);

            $messages = Mensaje::where(function ($q) use ($userA, $userB) {
                $q->where('sender_id', $userA)->where('receiver_id', $userB);
            })->orWhere(function ($q) use ($userA, $userB) {
                $q->where('sender_id', $userB)->where('receiver_id', $userA);
            })->orderBy('created_at')->get();

            foreach ($messages as $msg) {
                CommunicationMessage::create([
                    'communication_conversation_id' => $unified->id,
                    'type' => 'internal',
                    'sender_id' => $msg->sender_id,
                    'receiver_id' => $msg->receiver_id,
                    'content' => $msg->contenido ?? '',
                    'file_path' => $msg->archivo_path,
                    'file_name' => $msg->archivo_nombre,
                    'file_type' => $msg->archivo_tipo,
                    'read_at' => $msg->leido ? $msg->updated_at : null,
                    'created_at' => $msg->created_at,
                    'updated_at' => $msg->updated_at,
                ]);
            }

            if ($last = $messages->last()) {
                $unified->update(['last_message_at' => $last->created_at]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }
}
