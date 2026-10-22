<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Notificacion persistida en la tabla `notifications`.
 *
 * La tabla es la de Laravel estandar (uuid, type, notifiable morphs, data,
 * read_at), por lo que el modelo extends `DatabaseNotification`.
 */
class Notification extends DatabaseNotification
{
    protected $table = 'notifications';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
