<?php

namespace App\Traits;

use App\Mail\DynamicNotification;
use App\Models\MailTemplate;

trait SendsViaMailTemplate
{
    abstract public function templateSlug(): string;

    abstract public function templateVariables(object $notifiable): array;

    public function sendViaTemplate(object $notifiable, ?int $ownerId = null): ?DynamicNotification
    {
        $query = MailTemplate::where('slug', $this->templateSlug())
            ->where('is_active', true);

        if ($ownerId) {
            $query->where(function ($q) use ($ownerId) {
                $q->where('owner_id', $ownerId)
                    ->orWhereNull('owner_id');
            });
        } else {
            $query->whereNull('owner_id');
        }

        $template = $query->first();

        if (! $template) {
            return null;
        }

        return new DynamicNotification(
            $this->templateSlug(),
            $notifiable->email,
            $notifiable->name ?? '',
            $this->templateVariables($notifiable),
            $ownerId,
        );
    }
}
