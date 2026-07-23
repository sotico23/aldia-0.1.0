<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->string('operation_mode')->default('saas')->after('maintenance_mode');
            $table->string('default_currency')->default('PEN')->after('operation_mode');
            $table->json('allowed_currencies')->nullable()->after('default_currency');
            $table->decimal('default_vat', 5, 2)->default(0)->after('allowed_currencies');
            $table->boolean('auto_tax')->default(false)->after('default_vat');
            $table->string('financial_email')->nullable()->after('auto_tax');
            $table->string('billing_email')->nullable()->after('financial_email');

            $table->boolean('subscriptions_active')->default(false)->after('billing_email');
            $table->integer('trial_days')->default(0)->after('subscriptions_active');
            $table->integer('grace_days')->default(0)->after('trial_days');
            $table->boolean('auto_upgrade')->default(false)->after('grace_days');
            $table->boolean('downgrade_allowed')->default(false)->after('auto_upgrade');
            $table->boolean('cancel_non_payment')->default(false)->after('downgrade_allowed');
            $table->boolean('auto_renewal')->default(false)->after('cancel_non_payment');

            $table->string('invoice_prefix')->default('FAC-')->after('auto_renewal');
            $table->integer('invoice_start_number')->default(1)->after('invoice_prefix');
            $table->boolean('auto_invoicing')->default(false)->after('invoice_start_number');
            $table->boolean('auto_send_invoices')->default(false)->after('auto_invoicing');
            $table->boolean('auto_reminders')->default(false)->after('auto_send_invoices');

            $table->string('marketplace_commission_type')->default('percentage')->after('auto_reminders');
            $table->decimal('marketplace_commission_rate', 5, 2)->default(0)->after('marketplace_commission_type');
            $table->decimal('marketplace_fixed_amount', 12, 2)->default(0)->after('marketplace_commission_rate');
            $table->decimal('min_commission', 12, 2)->nullable()->after('marketplace_fixed_amount');
            $table->decimal('max_commission', 12, 2)->nullable()->after('min_commission');
            $table->decimal('min_withdrawal_amount', 12, 2)->default(0)->after('max_commission');

            $table->boolean('split_payment_active')->default(false)->after('min_withdrawal_amount');
            $table->string('split_payment_gateway')->nullable()->after('split_payment_active');
            $table->boolean('auto_hold_commission')->default(true)->after('split_payment_gateway');

            $table->string('fund_release_period')->default('immediate')->after('auto_hold_commission');

            $table->string('refund_policy')->default('platform_absorbs')->after('fund_release_period');
            $table->boolean('partial_refunds_allowed')->default(true)->after('refund_policy');

            $table->json('financial_automations')->nullable()->after('partial_refunds_allowed');
        });
    }

    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->dropColumn([
                'operation_mode',
                'default_currency',
                'allowed_currencies',
                'default_vat',
                'auto_tax',
                'financial_email',
                'billing_email',
                'subscriptions_active',
                'trial_days',
                'grace_days',
                'auto_upgrade',
                'downgrade_allowed',
                'cancel_non_payment',
                'auto_renewal',
                'invoice_prefix',
                'invoice_start_number',
                'auto_invoicing',
                'auto_send_invoices',
                'auto_reminders',
                'marketplace_commission_type',
                'marketplace_commission_rate',
                'marketplace_fixed_amount',
                'min_commission',
                'max_commission',
                'min_withdrawal_amount',
                'split_payment_active',
                'split_payment_gateway',
                'auto_hold_commission',
                'fund_release_period',
                'refund_policy',
                'partial_refunds_allowed',
                'financial_automations',
            ]);
        });
    }
};
