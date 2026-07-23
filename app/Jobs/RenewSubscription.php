<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RenewSubscription implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription
    ) {}

    public function handle(): void
    {
        if (! $this->subscription->isActive()) {
            Log::info("RenewSubscription: Subscription #{$this->subscription->id} is not active, skipping.");

            return;
        }

        $plan = $this->subscription->plan;

        if (! $plan) {
            Log::warning("RenewSubscription: Subscription #{$this->subscription->id} has no plan.");

            return;
        }

        $startDate = now();
        $endDate = $plan->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth();

        Transaction::create([
            'business_id' => $this->subscription->business_id,
            'subscription_id' => $this->subscription->id,
            'gateway' => $this->subscription->gateway,
            'gateway_transaction_id' => $this->subscription->gateway_subscription_id,
            'type' => 'subscription_payment',
            'status' => 'approved',
            'currency' => 'CLP',
            'amount' => (float) $plan->price,
            'fee' => 0,
            'net_amount' => (float) $plan->price,
            'metadata' => [
                'plan_slug' => $plan->slug,
                'billing_cycle' => $plan->billing_cycle,
            ],
            'processed_at' => now(),
        ]);

        $this->subscription->update([
            'starts_at' => $startDate,
            'expires_at' => $endDate,
            'price' => $plan->price,
        ]);

        $this->subscription->recordHistory('renewed', [
            'plan_slug' => $plan->slug,
            'amount' => $plan->price,
            'expires_at' => $endDate->toDateTimeString(),
        ]);

        Log::info("RenewSubscription: Subscription #{$this->subscription->id} renewed for {$plan->slug}.");
    }
}
