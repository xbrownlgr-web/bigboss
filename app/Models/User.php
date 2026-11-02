<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'country',
        'is_vip',
        'is_active',
        'fcm_token',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'fcm_token',
    ];

    public const COUNTRIES = [
        'benin' => 'Bénin',
        'cote_ivoire' => 'Côte d’Ivoire',
        'senegal' => 'Sénégal',
        'burkina_faso' => 'Burkina Faso',
        'mali' => 'Mali',
        'niger' => 'Niger',
        'togo' => 'Togo',
        'gabon' => 'Gabon',
        'rdc' => 'RD Congo',
        'cameroun' => 'Cameroun',
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
            'is_vip' => 'boolean',
            'is_active' => 'boolean',
            'adult_confirmed_at' => 'datetime',
            'account_deleted_at' => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function vipSubscriptions(): HasMany
    {
        return $this->hasMany(VipSubscription::class);
    }

    /**
     * Niveaux VIP actuellement actifs (Silver, Gold, Platinum), chacun séparé des autres.
     *
     * @return array<int, string>
     */
    public function activeVipLevels(): array
    {
        return $this->vipSubscriptions()->active()->pluck('level')->all();
    }

    protected function countryLabel(): Attribute
    {
        return Attribute::get(fn () => self::COUNTRIES[$this->country] ?? ($this->country ?: '-'));
    }
}
