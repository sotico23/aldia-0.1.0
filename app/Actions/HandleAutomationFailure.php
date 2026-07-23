<?php

namespace App\Actions;

use App\Models\AutomationExecution;
use App\Notifications\AutomationFailureAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class HandleAutomationFailure
{
    public function __invoke(string $workflow, string $ownerId, string $errorMessage): void
    {
        $threshold = 3;

        $recentExecutions = AutomationExecution::where('workflow', $workflow)
            ->where('owner_id', $ownerId)
            ->orderBy('created_at', 'desc')
            ->limit($threshold)
            ->get();

        if ($recentExecutions->count() < $threshold) {
            Log::warning('HandleAutomationFailure: fewer executions than threshold', [
                'workflow' => $workflow,
                'owner_id' => $ownerId,
                'count' => $recentExecutions->count(),
                'threshold' => $threshold,
            ]);

            return;
        }

        $consecutiveFailures = 0;
        foreach ($recentExecutions as $execution) {
            if (in_array($execution->status, ['error', 'failed'], true)) {
                $consecutiveFailures++;
            } else {
                break;
            }
        }

        if ($consecutiveFailures < $threshold) {
            return;
        }

        $lastExecution = $recentExecutions->first();

        $notification = new AutomationFailureAlert(
            workflow: $workflow,
            consecutiveFailures: $consecutiveFailures,
            lastUuid: $lastExecution?->uuid ?? 'N/A',
            lastError: $errorMessage,
        );

        try {
            if (config('services.slack.notifications.bot_user_oauth_token') && config('services.slack.notifications.channel')) {
                Notification::route('slack', config('services.slack.notifications.channel'))
                    ->notify($notification);
            }

            if (config('mail.mailer') !== 'log' && config('mail.from.address')) {
                Notification::route('mail', config('mail.from.address'))
                    ->notify($notification);
            }
        } catch (\Throwable $e) {
            Log::error('AutomationFailureAlert dispatch failed: '.$e->getMessage(), [
                'workflow' => $workflow,
                'exception' => $e,
            ]);
        }

        Log::critical('Automation consecutive failure detected', [
            'workflow' => $workflow,
            'owner_id' => $ownerId,
            'consecutive_failures' => $consecutiveFailures,
            'last_uuid' => $lastExecution?->uuid,
            'error' => $errorMessage,
        ]);
    }
}
