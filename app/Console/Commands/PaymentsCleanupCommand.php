<?php

namespace App\Console\Commands;

use App\Models\PaymentSession;
use App\Models\Pedido;
use App\Scopes\OwnerScope;
use Illuminate\Console\Command;

class PaymentsCleanupCommand extends Command
{
    protected $signature = 'payments:cleanup
        {--days=7 : Expire payment sessions older than N days}
        {--dry-run : Run without deleting}';

    protected $description = 'Limpia sesiones de pago expiradas y pedidos huérfanos';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoff = now()->subDays($days);

        // 1. Clean up expired payment sessions
        $expiredSessions = PaymentSession::where('expires_at', '<', now())
            ->orWhere('created_at', '<', $cutoff)
            ->where('status', 'pending');

        $count = $expiredSessions->count();

        if (! $dryRun) {
            $expiredSessions->delete();
        }

        $this->line("Payment sessions cleaned: {$count}".($dryRun ? ' (dry-run)' : ''));

        // 2. Clean up orphan failed pedidos with no user
        $orphans = Pedido::withoutGlobalScope(OwnerScope::class)
            ->whereNull('cliente_id')
            ->where('payment_status', 'failed')
            ->where('created_at', '<', $cutoff)
            ->count();

        $this->line("Orphan failed pedidos found: {$orphans}");

        return Command::SUCCESS;
    }
}
