<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Configuracion;
use Google\Client as GoogleClient;
use Google\Service\Calendar;

class GoogleCalendarService
{
    protected ?GoogleClient $client = null;

    protected ?Calendar $calendarService = null;

    protected ?string $calendarId = null;

    public function __construct()
    {
        $this->client = new GoogleClient;
        $this->client->setApplicationName('Aldia Proyect');
        $this->client->setScopes([Calendar::CALENDAR_EVENTS]);
        $this->client->setAuthConfig([
            'web' => [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uris' => [route('appointments.calendar.google-callback')],
            ],
        ]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');

        $this->restoreToken();
    }

    protected function restoreToken(): void
    {
        $accessToken = Configuracion::where('clave', 'google_calendar_access_token')->value('valor');
        if ($accessToken) {
            $token = json_decode($accessToken, true);
            if ($token) {
                $this->client->setAccessToken($token);

                if ($this->client->isAccessTokenExpired()) {
                    $refreshToken = $this->client->getRefreshToken();
                    if ($refreshToken) {
                        $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                        $this->saveToken($this->client->getAccessToken());
                    }
                }
            }
        }

        if ($this->client->getAccessToken()) {
            $this->calendarService = new Calendar($this->client);
        }
    }

    protected function saveToken(array $token): void
    {
        Configuracion::updateOrCreate(
            ['clave' => 'google_calendar_access_token'],
            ['valor' => json_encode($token), 'descripcion' => 'Google Calendar Access Token', 'categoria' => 'integrations']
        );
        $this->calendarService = new Calendar($this->client);
    }

    public function isConnected(): bool
    {
        return $this->calendarService !== null;
    }

    public function getAuthUrl(): ?string
    {
        try {
            return $this->client->createAuthUrl();
        } catch (\Exception) {
            return null;
        }
    }

    public function handleCallback(string $authCode): void
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($authCode);
        $this->saveToken($token);
    }

    public function createEvent(Appointment $appointment): ?string
    {
        if (! $this->isConnected()) {
            return null;
        }

        try {
            $event = new Calendar\Event([
                'summary' => $appointment->producto?->nombre ?? 'Cita',
                'description' => $appointment->notes,
                'start' => new Calendar\EventDateTime([
                    'dateTime' => $appointment->start_time->format('c'),
                    'timeZone' => config('app.timezone', 'America/Santiago'),
                ]),
                'end' => new Calendar\EventDateTime([
                    'dateTime' => $appointment->end_time->format('c'),
                    'timeZone' => config('app.timezone', 'America/Santiago'),
                ]),
                'extendedProperties' => [
                    'private' => [
                        'appointment_id' => (string) $appointment->id,
                    ],
                ],
            ]);

            $createdEvent = $this->calendarService->events->insert('primary', $event);

            $appointment->forceFill(['google_event_id' => $createdEvent->id])->saveQuietly();

            return $createdEvent->id;
        } catch (\Exception $e) {
            report($e);

            return null;
        }
    }

    public function updateEvent(Appointment $appointment): bool
    {
        if (! $this->isConnected() || ! $appointment->google_event_id) {
            return false;
        }

        try {
            $event = $this->calendarService->events->get('primary', $appointment->google_event_id);

            $event->setSummary($appointment->producto?->nombre ?? 'Cita');
            $event->setDescription($appointment->notes);

            $event->getStart()->setDateTime($appointment->start_time->format('c'));
            $event->getEnd()->setDateTime($appointment->end_time->format('c'));

            $this->calendarService->events->update('primary', $appointment->google_event_id, $event);

            return true;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    public function deleteEvent(Appointment $appointment): bool
    {
        if (! $this->isConnected() || ! $appointment->google_event_id) {
            return false;
        }

        try {
            $this->calendarService->events->delete('primary', $appointment->google_event_id);

            return true;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    public function listEvents(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        if (! $this->isConnected()) {
            return [];
        }

        try {
            $optParams = [
                'timeMin' => $start->format('c'),
                'timeMax' => $end->format('c'),
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ];

            $results = $this->calendarService->events->listEvents('primary', $optParams);

            return array_map(function ($event) {
                $appointmentId = $event->getExtendedProperties()?->getPrivate()['appointment_id'] ?? null;

                return [
                    'id' => $event->id,
                    'summary' => $event->getSummary(),
                    'start' => $event->getStart()->getDateTime(),
                    'end' => $event->getEnd()->getDateTime(),
                    'appointment_id' => $appointmentId,
                ];
            }, $results->getItems());
        } catch (\Exception $e) {
            report($e);

            return [];
        }
    }
}
