<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletPaymentRequest;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;
use Brick\Math\BigDecimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletTransferService
{
    public function __construct(private readonly WalletService $walletService) {}

    public function recipient(User $actor, string $accountNumber): Wallet
    {
        $this->authorize($actor);

        return $this->findRecipient($actor, 'account_number', $accountNumber);
    }

    public function recipientByUsername(User $actor, string $username): Wallet
    {
        $this->authorize($actor);

        return $this->findRecipient($actor, 'username', strtolower(ltrim($username, '@')));
    }

    private function findRecipient(User $actor, string $field, string $value): Wallet
    {
        $wallet = Wallet::query()->with('user:id,name,is_active')->where($field, $value)->first();
        if (! $wallet || ! $wallet->user?->is_active || $wallet->status !== 'active' || (int) $wallet->user_id === (int) $actor->id) {
            throw ValidationException::withMessages([$field => 'Selecciona otra billetera activa válida.']);
        }

        return $wallet;
    }

    public function transfer(User $actor, string $accountNumber, string $amount, string $requestKey, ?string $note = null): WalletTransfer
    {
        $this->authorize($actor);
        $amount = $this->validateOperation($amount, $requestKey, $note);
        $sender = $this->walletService->walletFor($actor);
        $recipient = $this->recipient($actor, $accountNumber);

        return DB::transaction(function () use ($actor, $sender, $recipient, $amount, $requestKey, $note): WalletTransfer {
            $wallets = Wallet::query()->whereIn('id', [$sender->id, $recipient->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $sender = $wallets->get($sender->id);
            $recipient = $wallets->get($recipient->id);
            $existing = WalletTransfer::query()->where('sender_wallet_id', $sender->id)->where('request_key', $requestKey)->first();
            if ($existing) {
                $this->assertSameOperation((int) $existing->recipient_wallet_id, (int) $recipient->id, $existing->amount, $amount, $existing->note, $note);

                return $existing;
            }
            $this->assertCompatible($sender, $recipient);
            $transfer = WalletTransfer::create([
                'sender_wallet_id' => $sender->id, 'recipient_wallet_id' => $recipient->id,
                'request_key' => $requestKey, 'amount' => $amount, 'currency' => $sender->currency, 'note' => $note,
            ]);
            $this->moveBalance($sender, $recipient, $transfer, $actor);

            return $transfer;
        }, 3);
    }

    public function requestPayment(User $actor, ?string $accountNumber, string $amount, string $requestKey, ?string $note = null): WalletPaymentRequest
    {
        $this->authorize($actor);
        $amount = $this->validateOperation($amount, $requestKey, $note);
        $requester = $this->walletService->walletFor($actor);
        $payer = $accountNumber === null ? null : $this->recipient($actor, $accountNumber);

        return DB::transaction(function () use ($requester, $payer, $amount, $requestKey, $note): WalletPaymentRequest {
            $wallets = Wallet::query()->whereIn('id', array_filter([$requester->id, $payer?->id]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $existing = WalletPaymentRequest::query()->where('requester_wallet_id', $requester->id)->where('request_key', $requestKey)->first();
            if ($existing) {
                $this->assertSameOperation((int) $existing->payer_wallet_id, (int) $payer?->id, $existing->amount, $amount, $existing->note, $note);

                return $existing;
            }
            $this->assertCompatible($wallets->get($requester->id), $wallets->get($payer?->id ?? $requester->id));

            return WalletPaymentRequest::create([
                'requester_wallet_id' => $requester->id, 'payer_wallet_id' => $payer?->id,
                'request_key' => $requestKey, 'amount' => $amount, 'currency' => $requester->currency, 'note' => $note,
            ]);
        }, 3);
    }

    public function payLink(User $actor, WalletPaymentRequest $paymentRequest): WalletPaymentRequest
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $paymentRequest): WalletPaymentRequest {
            $payment = WalletPaymentRequest::query()->lockForUpdate()->findOrFail($paymentRequest->id);
            if ($payment->payer_wallet_id === null && $payment->status === 'pending') {
                $payer = $this->walletService->walletFor($actor);
                if ((int) $payer->id === (int) $payment->requester_wallet_id) {
                    throw ValidationException::withMessages(['payment_request' => 'No puedes pagar tu propio enlace.']);
                }
                $payment->update(['payer_wallet_id' => $payer->id]);
            }

            return $this->resolvePayment($actor, $payment, 'accept');
        }, 3);
    }

    public function resolvePayment(User $actor, WalletPaymentRequest $paymentRequest, string $decision): WalletPaymentRequest
    {
        $this->authorize($actor);
        Validator::make(['decision' => $decision], ['decision' => ['required', 'in:accept,reject,cancel']])->validate();

        return DB::transaction(function () use ($actor, $paymentRequest, $decision): WalletPaymentRequest {
            $payment = WalletPaymentRequest::query()->lockForUpdate()->findOrFail($paymentRequest->id);
            $actingWalletId = $decision === 'cancel' ? $payment->requester_wallet_id : $payment->payer_wallet_id;
            if (! Wallet::query()->whereKey($actingWalletId)->where('user_id', $actor->id)->exists()) {
                throw new AuthorizationException('No puedes resolver esta solicitud de pago.');
            }
            $status = match ($decision) {
                'accept' => 'paid', 'reject' => 'rejected', 'cancel' => 'cancelled'
            };
            if ($payment->status === $status) {
                return $payment;
            }
            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages(['payment_request' => 'Esta solicitud ya fue resuelta.']);
            }
            if ($decision === 'accept') {
                $requester = Wallet::query()->findOrFail($payment->requester_wallet_id);
                if ($requester->currency !== $payment->currency) {
                    throw ValidationException::withMessages(['payment_request' => 'La moneda de la billetera cambió desde que se creó la solicitud.']);
                }
                $transfer = $this->transfer($actor, $requester->account_number, $payment->amount, (string) Str::uuid(), $payment->note);
                $payment->transfer_id = $transfer->id;
            }
            $payment->status = $status;
            $payment->resolved_at = now();
            $payment->save();

            return $payment;
        }, 3);
    }

    public function requestRefund(User $actor, WalletTransfer $transfer, string $reason): void
    {
        $this->authorize($actor);

        DB::transaction(function () use ($actor, $transfer, $reason): void {
            $transfer = WalletTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if ((int) $transfer->senderWallet->user_id !== (int) $actor->id) {
                throw new AuthorizationException;
            }
            if ($transfer->refund_status === 'pending' && $transfer->refund_reason === $reason) {
                return;
            }
            if ($transfer->refund_status !== null) {
                throw ValidationException::withMessages(['reason' => 'Este pago ya tiene una solicitud de reembolso.']);
            }

            $transfer->forceFill([
                'refund_status' => 'pending',
                'refund_reason' => $reason,
                'refund_requested_at' => now(),
            ])->save();
        }, 3);
    }

    public function resolveRefund(User $actor, WalletTransfer $transfer, string $decision, ?string $response): void
    {
        $this->authorize($actor);

        DB::transaction(function () use ($actor, $transfer, $decision, $response): void {
            $transfer = WalletTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $wallets = Wallet::query()->whereIn('id', [$transfer->sender_wallet_id, $transfer->recipient_wallet_id])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $sender = $wallets->get($transfer->sender_wallet_id);
            $recipient = $wallets->get($transfer->recipient_wallet_id);

            $isSender = (int) $sender->user_id === (int) $actor->id;
            $isRecipient = (int) $recipient->user_id === (int) $actor->id;
            $isSupport = ! $isSender && ! $isRecipient && $actor->hasAnyRole(['Master', 'Super Admin']);

            [$expected, $status] = match (true) {
                $isSender && $decision === 'escalate' => [$transfer->canEscalateRefund() ? $transfer->refund_status : 'rejected', 'escalated'],
                $isRecipient && $decision === 'accept' => ['pending', 'refunded'],
                $isRecipient && $decision === 'reject' => ['pending', 'rejected'],
                $isSupport && $decision === 'accept' => ['escalated', 'refunded'],
                $isSupport && $decision === 'deny' => ['escalated', 'denied'],
                default => throw new AuthorizationException,
            };

            if ($transfer->refund_status === $status) {
                return;
            }
            if ($transfer->refund_status !== $expected) {
                throw ValidationException::withMessages(['decision' => 'La solicitud cambió de estado. Actualiza la billetera.']);
            }

            if ($status === 'refunded') {
                $this->assertCompatible($recipient, $sender);
                if ($recipient->currency !== $transfer->currency) {
                    throw ValidationException::withMessages(['decision' => 'La moneda del pago original ya no coincide con la de las billeteras.']);
                }
                $this->moveBalance($recipient, $sender, $transfer, $actor, 'refund');
            }

            $transfer->refund_status = $status;
            if ($response !== null && in_array($status, ['rejected', 'denied'], true)) {
                $transfer->refund_response = $response;
            }
            if (in_array($status, ['refunded', 'denied'], true)) {
                $transfer->refund_resolved_at = now();
                $transfer->refund_resolved_by = $actor->id;
            }
            $transfer->save();
        }, 3);
    }

    private function moveBalance(Wallet $from, Wallet $to, WalletTransfer $transfer, User $actor, string $category = 'transfer'): void
    {
        $before = BigDecimal::of($from->balance);
        if ($before->isLessThan($transfer->amount)) {
            throw ValidationException::withMessages(['amount' => 'Saldo disponible insuficiente para realizar el movimiento.']);
        }
        $after = BigDecimal::of($to->balance)->plus($transfer->amount);
        if ($after->isGreaterThan('9999999999.99')) {
            throw ValidationException::withMessages(['amount' => 'El monto excede el límite de la billetera de destino.']);
        }

        $description = $category === 'refund' ? 'Reembolso' : 'Transferencia';
        $this->recordMovement($from, 'debit', (string) $before->minus($transfer->amount), $transfer, $actor, $description.' a '.$to->account_number, $category);
        $this->recordMovement($to, 'credit', (string) $after, $transfer, $actor, $description.' de '.$from->account_number, $category);
    }

    private function authorize(User $actor): void
    {
        if (! $actor->canUsePersonalWallet()) {
            throw new AuthorizationException('No tienes permiso para operar la billetera.');
        }
    }

    private function validateOperation(string $amount, string $requestKey, ?string $note): string
    {
        Validator::make(['amount' => $amount, 'request_key' => $requestKey, 'note' => $note], [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'request_key' => ['required', 'uuid'], 'note' => ['nullable', 'string', 'max:255'],
        ])->validate();

        return (string) BigDecimal::of($amount)->toScale(2);
    }

    private function assertCompatible(Wallet $first, Wallet $second): void
    {
        if ($first->status !== 'active' || $second->status !== 'active') {
            throw ValidationException::withMessages(['account_number' => 'Ambas billeteras deben estar activas.']);
        }
        if ($first->currency !== $second->currency) {
            throw ValidationException::withMessages(['account_number' => 'Las billeteras deben utilizar la misma moneda.']);
        }
    }

    private function assertSameOperation(int $existingWalletId, int $walletId, string $existingAmount, string $amount, ?string $existingNote, ?string $note): void
    {
        if ($existingWalletId !== $walletId || $existingAmount !== $amount || $existingNote !== $note) {
            throw ValidationException::withMessages(['request_key' => 'Esta operación ya fue utilizada con otros datos.']);
        }
    }

    private function recordMovement(Wallet $wallet, string $type, string $balanceAfter, WalletTransfer $transfer, User $actor, string $description, string $category): void
    {
        WalletTransaction::create([
            'wallet_id' => $wallet->id, 'type' => $type, 'category' => $category,
            'amount' => $transfer->amount, 'balance_before' => $wallet->balance, 'balance_after' => $balanceAfter,
            'reference_type' => $transfer->getMorphClass(), 'reference_id' => $transfer->id,
            'description' => $description.($transfer->note ? ': '.$transfer->note : ''), 'created_by' => $actor->id,
        ]);
        $wallet->update(['balance' => $balanceAfter]);
    }
}
