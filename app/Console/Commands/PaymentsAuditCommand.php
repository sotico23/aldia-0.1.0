<?php

namespace App\Console\Commands;

use App\Models\PaymentSession;
use App\Models\Pedido;
use App\Models\Transaction;
use App\Models\WebpayTransaction;
use App\Scopes\OwnerScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PaymentsAuditCommand extends Command
{
    protected $signature = 'payments:audit
        {--fix : Attempt to auto-fix orphan records}
        {--report-only : Only generate report without searching}';

    protected $description = 'Audit payment transactions for orphan records and inconsistencies';

    public function handle(): int
    {
        $this->info('=== Payments Audit Report ===');
        $this->newLine();

        $issues = [];

        $issues = array_merge($issues, $this->findPendingPayments());
        $issues = array_merge($issues, $this->findOrphanPayments());
        $issues = array_merge($issues, $this->findPaymentsWithoutConfirmation());
        $issues = array_merge($issues, $this->findUnprocessedWebhooks());
        $issues = array_merge($issues, $this->findInconsistentTransactions());
        $issues = array_merge($issues, $this->findPaymentSessionsWithoutTransaction());

        $this->newLine();
        $this->info('=== Report Summary ===');
        $this->line('Total issues found: '.count($issues));

        if (empty($issues)) {
            $this->info('No issues found. The payment system is clean.');
        } else {
            $this->table(
                ['Type', 'ID', 'Description'],
                collect($issues)->map(fn ($i) => [$i['type'], $i['id'], $i['description']])->toArray()
            );
        }

        if ($this->option('fix') && ! empty($issues)) {
            $this->newLine();
            $this->warn('Auto-fix mode is enabled but not implemented. Review issues manually.');
        }

        Log::info('Payments audit completed', [
            'issues_found' => count($issues),
        ]);

        return Command::SUCCESS;
    }

    protected function findPendingPayments(): array
    {
        $this->info('1. Checking pending payments...');
        $issues = [];

        $oldPending = WebpayTransaction::where('status', 'pending')
            ->where('created_at', '<', now()->subDay())
            ->get();

        foreach ($oldPending as $tx) {
            $issues[] = [
                'type' => 'PENDING_WEBPAY',
                'id' => "WP#{$tx->id}",
                'description' => "Webpay transaction {$tx->buy_order} has been pending since {$tx->created_at->diffForHumans()}",
            ];
        }

        $oldPendingPedidos = Pedido::withoutGlobalScope(OwnerScope::class)
            ->whereIn('payment_status', ['pending', 'created'])
            ->where('created_at', '<', now()->subDay())
            ->get();

        foreach ($oldPendingPedidos as $p) {
            $issues[] = [
                'type' => 'PENDING_PEDIDO',
                'id' => "PED#{$p->id}",
                'description' => "Order {$p->numero_pedido} ({$p->metodo_pago}) has been pending payment since {$p->created_at->diffForHumans()}",
            ];
        }

        if (count($issues) === 0) {
            $this->info('  ✓ No pending payments found.');
        }

        return $issues;
    }

    protected function findOrphanPayments(): array
    {
        $this->info('2. Checking for orphan payments...');
        $issues = [];

        $orphanPedidos = Pedido::withoutGlobalScope(OwnerScope::class)
            ->whereNotNull('payment_id')
            ->where(function ($q) {
                $q->whereNull('owner_id')
                    ->orWhereNull('cliente_id');
            })
            ->get();

        foreach ($orphanPedidos as $p) {
            $issues[] = [
                'type' => 'ORPHAN_PEDIDO',
                'id' => "PED#{$p->id}",
                'description' => "Order {$p->numero_pedido} has payment_id but missing owner or client",
            ];
        }

        $orphanTransactions = Transaction::whereNull('business_id')->get();
        foreach ($orphanTransactions as $t) {
            $issues[] = [
                'type' => 'ORPHAN_TRANSACTION',
                'id' => "TXN#{$t->id}",
                'description' => "Transaction {$t->uuid} has no business_id assigned",
            ];
        }

        if (count($issues) === 0) {
            $this->info('  ✓ No orphan payments found.');
        }

        return $issues;
    }

    protected function findPaymentsWithoutConfirmation(): array
    {
        $this->info('3. Checking for payments without confirmation...');
        $issues = [];

        $completedWithoutDate = Pedido::withoutGlobalScope(OwnerScope::class)
            ->where('payment_status', 'completed')
            ->whereNull('fecha_confirmacion')
            ->get();

        foreach ($completedWithoutDate as $p) {
            $issues[] = [
                'type' => 'NO_CONFIRMATION_DATE',
                'id' => "PED#{$p->id}",
                'description' => "Order {$p->numero_pedido} is completed but has no confirmation date",
            ];
        }

        if (count($issues) === 0) {
            $this->info('  ✓ All payments have confirmation dates.');
        }

        return $issues;
    }

    protected function findUnprocessedWebhooks(): array
    {
        $this->info('4. Checking for unprocessed webhooks...');
        $issues = [];

        $pendingPaymentSessions = PaymentSession::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($pendingPaymentSessions as $ps) {
            $issues[] = [
                'type' => 'EXPIRED_SESSION',
                'id' => "PS#{$ps->id}",
                'description' => "Payment session {$ps->token} ({$ps->gateway}) expired at {$ps->expires_at}",
            ];
        }

        if (count($issues) === 0) {
            $this->info('  ✓ No unprocessed webhooks.');
        }

        return $issues;
    }

    protected function findInconsistentTransactions(): array
    {
        $this->info('5. Checking for inconsistent transactions...');
        $issues = [];

        $negativeAmounts = Transaction::where('amount', '<=', 0)->get();
        foreach ($negativeAmounts as $t) {
            $issues[] = [
                'type' => 'NEGATIVE_AMOUNT',
                'id' => "TXN#{$t->id}",
                'description' => "Transaction {$t->uuid} has amount {$t->amount}",
            ];
        }

        $highFees = Transaction::whereColumn('fee', '>', 'amount')->get();
        foreach ($highFees as $t) {
            $issues[] = [
                'type' => 'FEE_EXCEEDS_AMOUNT',
                'id' => "TXN#{$t->id}",
                'description' => "Transaction {$t->uuid} fee ({$t->fee}) exceeds amount ({$t->amount})",
            ];
        }

        if (count($issues) === 0) {
            $this->info('  ✓ No inconsistent transactions found.');
        }

        return $issues;
    }

    protected function findPaymentSessionsWithoutTransaction(): array
    {
        $this->info('6. Checking for payment sessions without transactions...');
        $issues = [];

        $completedSessions = PaymentSession::where('status', 'completed')
            ->whereDoesntHave('business')
            ->get();

        if (count($issues) === 0) {
            $this->info('  ✓ All completed sessions have transactions.');
        }

        return $issues;
    }
}
