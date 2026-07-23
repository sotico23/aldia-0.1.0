<?php

namespace App\Listeners;

use App\Events\PaymentSuccessful;
use App\Helpers\NotificationHelper;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;

class SendPaymentSuccessfulNotification
{
    public function handle(PaymentSuccessful $event): void
    {
        $transaction = $event->transaction;
        $owner = User::find($transaction->business_id);

        if ($owner) {
            NotificationHelper::send($owner, new PaymentReceivedNotification($transaction));
        }
    }
}
