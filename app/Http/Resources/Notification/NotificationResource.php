<?php
/**
 * Notification Resource - Handles user notifications
 */

namespace App\Http\Resources\Notification;

use App\Http\Resources\Base\BaseResource;

class NotificationResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get notification data
     */
    public function getNotificationData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal notification data (for nested relations)
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal notification fields for nested relations
     */
    protected function addMinimalFields(): void
    {
        $this->addCommonFields([
            'id' => null,
            'created_at' => null,
            'updated_at' => null,
            'data' => [
                'id' => $this->id,
                'tipo' => $this->tipo,
                'titulo' => $this->titulo,
                'contenido' => $this->contenido,
                'estado' => $this->estado,
                'leído' => $this->leido,
            ],
        ]);
    }
}
