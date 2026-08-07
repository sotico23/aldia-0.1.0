<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'telegram_id',
        'telegram_username',
        'country',
        'password',
        'creator_id',
        'owner_id',
        'profile_photo_path',
        'cover_photo_path',
        'job',
        'location',
        'rut',
        'razon_social',
        'giro',
        'telefono',
        'direccion',
        'ciudad',
        'region',
        'comuna',
        'fecha_nacimiento',
        'genero',
        'tipo_entidad',
        'show_onboarding',
        'referral_code',
        'referred_by',
        'points',
        'business_logo_path',
        'business_name',
        'business_cover_path',
        'primary_color',
        'secondary_color',
        'favicon_path',
        'dashboard_name',
        'dark_mode_preference',
        'trial_ends_at',
        'trial_starts_at',
        'has_explicit_role',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (User $user) {
            if (empty($user->referral_code)) {
                do {
                    $code = strtoupper(Str::random(8));
                } while (self::where('referral_code', $code)->exists());

                $user->referral_code = $code;
            }
        });

        static::created(function (User $user) {
            $isFirstUser = self::withoutGlobalScopes()->count() === 1;

            if ($isFirstUser) {
                $user->assignRole('Super Admin');
            } elseif (! $user->has_explicit_role) {
                $user->assignRole('Usuario');

                $trialDays = (int) (WebSetting::getSettings()->trial_days ?? 15);
                if ($trialDays < 1) {
                    $trialDays = 15;
                }

                if (is_null($user->trial_ends_at)) {
                    self::withoutEvents(function () use ($user, $trialDays) {
                        $user->update([
                            'trial_starts_at' => now(),
                            'trial_ends_at' => now()->addDays($trialDays),
                        ]);
                    });
                } elseif (is_null($user->trial_starts_at)) {
                    self::withoutEvents(function () use ($user) {
                        $user->update(['trial_starts_at' => $user->trial_ends_at->subDays(15)]);
                    });
                }

                $user->trialStateCache = null;
            }
        });

        static::updating(function (User $user) {
            if ($user->isDirty('trial_ends_at') || $user->isDirty('trial_starts_at')) {
                $user->trialStateCache = null;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'creator_id');
    }

    public function empleado(): HasOne
    {
        return $this->hasOne(Empleado::class);
    }

    public function cliente(): HasOne
    {
        return $this->hasOne(Cliente::class);
    }

    public function proveedor(): HasOne
    {
        return $this->hasOne(Proveedor::class);
    }

    public function countryModel(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country', 'code');
    }

    public function getCountryConfig(): Country
    {
        if ($this->relationLoaded('countryModel') && $this->countryModel instanceof Country) {
            return $this->countryModel;
        }

        return Country::findByCode($this->country) ?? Country::getDefault();
    }

    public function mensajesEnviados(): HasMany
    {
        return $this->hasMany(Mensaje::class, 'sender_id');
    }

    public function mensajesRecibidos(): HasMany
    {
        return $this->hasMany(Mensaje::class, 'receiver_id');
    }

    public function publicacion(): HasMany
    {
        return $this->hasMany(Publicacion::class);
    }

    public function followers(): HasMany
    {
        return $this->hasMany(Follower::class, 'followed_id');
    }

    public function following(): HasMany
    {
        return $this->hasMany(Follower::class, 'user_id');
    }

    public function publicProfile(): HasOne
    {
        return $this->hasOne(PublicProfile::class);
    }

    public function monitoredSites(): HasMany
    {
        return $this->hasMany(MonitoredSite::class);
    }

    public function uptimeAlerts(): HasMany
    {
        return $this->hasMany(UptimeAlert::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class);
    }

    public function wantsNotificationChannel(string $type, string $channel): bool
    {
        $preference = $this->notificationPreferences()
            ->where('type', $type)
            ->where('channel', $channel)
            ->first();

        if (! $preference) {
            return true;
        }

        return $preference->enabled;
    }

    public function getClienteActual(): ?Cliente
    {
        if ($this->hasRole('Cliente')) {
            return $this->cliente()->withoutGlobalScopes()->first();
        }

        return null;
    }

    public function getOwnerId(): int
    {
        if ($this->creator_id) {
            $visited = [];
            $nextId = $this->creator_id;
            $prevRowId = null;
            $currentRowId = null;

            do {
                if (isset($visited[$nextId])) {
                    return $prevRowId ?? $this->id;
                }
                $visited[$nextId] = true;

                $row = \DB::table('users')->find($nextId);
                if (! $row) {
                    return $this->creator_id;
                }
                $prevRowId = $currentRowId;
                $currentRowId = (int) $row->id;
                $nextId = $row->creator_id;
            } while ($nextId);

            return (int) $row->id;
        }

        return $this->id;
    }

    public function isCliente(): bool
    {
        return $this->hasRole('Cliente') && $this->cliente()->withoutGlobalScopes()->exists();
    }

    public function isProveedor(): bool
    {
        return $this->hasRole('Proveedor') && $this->proveedor()->withoutGlobalScopes()->exists();
    }

    public function getProveedorActual(): ?Proveedor
    {
        if ($this->hasRole('Proveedor')) {
            return $this->proveedor()->withoutGlobalScopes()->first();
        }

        return null;
    }

    /**
     * Cached trial state to avoid redundant hasRole queries per request.
     *
     * @var array{is_active: bool, is_expired: bool, days_remaining: int}|null
     */
    private ?array $trialStateCache = null;

    private function resolveTrialState(): array
    {
        if ($this->trialStateCache !== null) {
            return $this->trialStateCache;
        }

        $isUsuario = $this->hasRole('Usuario');
        $hasTrial = $isUsuario && $this->trial_ends_at !== null;

        $this->trialStateCache = [
            'is_active' => $hasTrial && now()->lessThan($this->trial_ends_at),
            'is_expired' => $hasTrial && now()->greaterThanOrEqualTo($this->trial_ends_at),
            'days_remaining' => $hasTrial ? max(0, (int) now()->diffInDays($this->trial_ends_at, false)) : 0,
        ];

        return $this->trialStateCache;
    }

    public function isTrialActive(): bool
    {
        return $this->resolveTrialState()['is_active'];
    }

    public function isTrialExpired(): bool
    {
        return $this->resolveTrialState()['is_expired'];
    }

    public function trialDaysRemaining(): int
    {
        return $this->resolveTrialState()['days_remaining'];
    }

    public function highestRoleLevel(): int
    {
        return $this->roles()->min('level') ?? 3;
    }

    public function currentWarehouseId(): ?int
    {
        return $this->empleado?->almacen_id;
    }

    public function scopeVisibles($query)
    {
        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        $userLevel = $user->highestRoleLevel();

        // Nivel 0 (Master) — bypass total, ve todo el sistema
        if ($userLevel === 0) {
            return $query;
        }

        $userId = $user->id;
        $ownerId = $user->owner_id;

        return $query
            // 1. Mismo tenant (owner_id)
            ->where('owner_id', $ownerId)
            // 2. Árbol de creación: hijos directos, nietos, o huérfanos del mismo tenant
            ->where(function ($q) use ($userId) {
                $q->where('creator_id', $userId)
                    ->orWhereIn('creator_id', function ($sub) use ($userId) {
                        $sub->select('id')->from('users')
                            ->where('creator_id', $userId);
                    })
                    ->orWhereNull('creator_id'); // registros públicos del mismo tenant
            })
            // 3. No puede ver ni gestionar usuarios de igual o mayor nivel
            ->whereDoesntHave('roles', function ($q) use ($userLevel) {
                $q->where('level', '<=', $userLevel);
            })
            // 4. No puede gestionarse a sí mismo
            ->where('id', '!=', $userId);
    }

    public function isFollowedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->followers()->whereUserId($user->id)->exists();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'api_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'trial_starts_at' => 'datetime',
            'is_active' => 'boolean',
            'banned_at' => 'datetime',
        ];
    }

    public function profilePhotoUrl(): string
    {
        if (! $this->profile_photo_path) {
            return $this->defaultProfilePhotoUrl();
        }

        if (filter_var($this->profile_photo_path, FILTER_VALIDATE_URL)) {
            return $this->profile_photo_path;
        }

        return asset('storage/'.$this->profile_photo_path);
    }

    public function defaultProfilePhotoUrl(): string
    {
        return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&color=random';
    }

    public function coverPhotoUrl(): string
    {
        if (! $this->cover_photo_path) {
            return '';
        }

        if (filter_var($this->cover_photo_path, FILTER_VALIDATE_URL)) {
            return $this->cover_photo_path;
        }

        return asset('storage/'.$this->cover_photo_path);
    }

    /**
     * Get the business logo URL, falling back to global app logo.
     */
    public function businessLogoUrl(): string
    {
        if (! $this->business_logo_path) {
            // Assuming WebSetting model provides global settings
            return class_exists(WebSetting::class) ? (WebSetting::getSettings()->app_logo ?? '') : '';
        }

        return filter_var($this->business_logo_path, FILTER_VALIDATE_URL)
            ? $this->business_logo_path
            : asset('storage/'.$this->business_logo_path);
    }

    /**
     * Get the business cover image URL, with global fallback.
     */
    public function businessCoverUrl(): string
    {
        if (! $this->business_cover_path) {
            return class_exists(WebSetting::class) ? WebSetting::getSettings()->app_cover ?? '' : '';
        }

        return filter_var($this->business_cover_path, FILTER_VALIDATE_URL)
            ? $this->business_cover_path
            : asset('storage/'.$this->business_cover_path);
    }

    /**
     * Get the business favicon URL.
     */
    public function faviconUrl(): string
    {
        if (! $this->favicon_path) {
            return class_exists(WebSetting::class) ? WebSetting::getSettings()->app_favicon ?? '' : '';
        }

        return filter_var($this->favicon_path, FILTER_VALIDATE_URL)
            ? $this->favicon_path
            : asset('storage/'.$this->favicon_path);
    }

    public function getPrimaryColor(): string
    {
        return $this->primary_color ?? '#3B82F6';
    }

    public function getSecondaryColor(): string
    {
        return $this->secondary_color ?? '#10B981';
    }

    public function getDashboardName(): string
    {
        return $this->dashboard_name ?? config('app.name');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'provider_id');
    }

    public function prefersDarkMode(): bool
    {
        return (bool) $this->dark_mode_preference;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'profile_photo_url' => $this->profilePhotoUrl(),
            'cover_photo_url' => $this->coverPhotoUrl(),
        ]);
    }
}
