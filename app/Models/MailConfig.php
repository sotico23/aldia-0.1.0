<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailConfig extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'driver',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'secret',
        'domain',
        'endpoint',
        'region',
        'from_address',
        'from_name',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'secret' => 'encrypted',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MailConfigLog::class)->orderByDesc('created_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(MailConfigNotification::class)->orderByDesc('created_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function buildMailerConfig(): array
    {
        $config = match ($this->driver) {
            'smtp' => [
                'transport' => 'smtp',
                'host' => $this->host,
                'port' => $this->port,
                'encryption' => $this->encryption,
                'username' => $this->username,
                'password' => $this->password,
                'timeout' => 30,
            ],
            'mailgun' => [
                'transport' => 'mailgun',
                'secret' => $this->secret,
                'domain' => $this->domain,
                'endpoint' => $this->endpoint ?? 'api.mailgun.net',
            ],
            'postmark' => [
                'transport' => 'postmark',
                'token' => $this->secret,
            ],
            'ses' => [
                'transport' => 'ses',
                'key' => $this->username,
                'secret' => $this->password,
                'region' => $this->region ?? 'us-east-1',
                'domain' => $this->domain,
            ],
            'sendmail' => [
                'transport' => 'sendmail',
                'path' => '/usr/sbin/sendmail -bs -i',
            ],
            default => [],
        };

        return $config;
    }

    public static function getDriverFields(): array
    {
        return [
            'smtp' => ['host', 'port', 'encryption', 'username', 'password'],
            'mailgun' => ['domain', 'secret', 'endpoint'],
            'postmark' => ['secret'],
            'ses' => ['username', 'password', 'region', 'domain'],
            'sendmail' => [],
        ];
    }

    public function getRequiredFields(): array
    {
        return self::getDriverFields()[$this->driver] ?? [];
    }

    public function getDriverIcon(): string
    {
        return match ($this->driver) {
            'smtp' => '📧',
            'mailgun' => '🔫',
            'postmark' => '📮',
            'ses' => '☁️',
            'sendmail' => '📤',
            default => '📧',
        };
    }

    public function getDriverLabel(): string
    {
        return match ($this->driver) {
            'smtp' => 'SMTP',
            'mailgun' => 'Mailgun',
            'postmark' => 'Postmark',
            'ses' => 'AWS SES',
            'sendmail' => 'Sendmail',
            default => 'SMTP',
        };
    }

    public function getConnectionString(): string
    {
        if ($this->driver === 'smtp') {
            return "{$this->host}:{$this->port}";
        }

        return ucfirst($this->driver);
    }
}
