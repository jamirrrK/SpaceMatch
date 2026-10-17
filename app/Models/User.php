<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_OWNER = 'owner';
    public const ROLE_RENTER = 'renter';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
        'phone_number',
        'phone_verified_at',
        'verification_required_at',
        'verification_method',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'phone_number',
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
            'phone_verified_at' => 'datetime',
            'verification_required_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function requiresContactVerification(): bool
    {
        return $this->verification_required_at !== null;
    }

    public function otpVerifications()
    {
        return $this->hasMany(OtpVerification::class);
    }

    public static function registrationRoles(): array
    {
        return [self::ROLE_RENTER, self::ROLE_OWNER];
    }

    public function userRoles()
    {
        return $this->hasMany(UserRole::class);
    }

    public function properties()
    {
        return $this->hasMany(Property::class, 'owner_id');
    }

    public function savedSpaces()
    {
        return $this->belongsToMany(Space::class, 'saved_spaces')->withTimestamps();
    }

    public function renterPreference()
    {
        return $this->hasOne(RenterPreference::class);
    }
}
