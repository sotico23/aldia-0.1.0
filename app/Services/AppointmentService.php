<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    /**
     * Check if a provider has a scheduling conflict for the given time range.
     * Excludes cancelled appointments and optionally excludes a specific appointment (for updates).
     */
    public function hasConflict(
        int $providerId,
        CarbonInterface $startTime,
        CarbonInterface $endTime,
        ?int $excludeAppointmentId = null,
    ): bool {
        $query = Appointment::where('provider_id', $providerId)
            ->whereIn('status', ['pendiente', 'confirmada'])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->exists();
    }

    /**
     * Check for conflicts within a DB transaction with pessimistic locking.
     * This prevents race conditions where two concurrent requests both pass
     * the conflict check and create overlapping appointments.
     *
     * Usage: wrap the appointment creation inside the callback.
     */
    public function hasConflictLocked(
        int $providerId,
        CarbonInterface $startTime,
        CarbonInterface $endTime,
        ?int $excludeAppointmentId = null,
    ): bool {
        return DB::transaction(function () use ($providerId, $startTime, $endTime, $excludeAppointmentId) {
            // Lock existing appointments for this provider in the overlapping time range
            $existing = Appointment::where('provider_id', $providerId)
                ->whereIn('status', ['pendiente', 'confirmada'])
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->lockForUpdate()
                ->get();

            if ($existing->isEmpty()) {
                return false;
            }

            if ($excludeAppointmentId) {
                return $existing->contains('id', $excludeAppointmentId);
            }

            return true;
        });
    }

    /**
     * Validate that a cancellation is allowed (at least 24 hours before start_time).
     * Returns true if cancellation IS allowed, false if denied.
     */
    public function canCancel(Appointment $appointment): bool
    {
        if ($appointment->status === 'cancelada') {
            return false;
        }

        // Cancellation is DENIED when less than 24 hours remain
        return $appointment->start_time->isAfter(now()->addHours(24));
    }

    /**
     * Get the hours remaining until the appointment starts.
     */
    public function hoursUntilStart(Appointment $appointment): int
    {
        return (int) $appointment->start_time->diffInHours(now());
    }
}
