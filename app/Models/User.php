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
    protected $attributes = ['is_student' => false];

    protected $fillable = [
        'is_student',
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
            'is_student' => 'boolean',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'verification_required_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check whether the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Determine whether contact verification is required.
     */
    public function requiresContactVerification(): bool
    {
        return $this->verification_required_at !== null;
    }

    /**
     * OTP verification records belonging to the user.
     */
    public function otpVerifications()
    {
        return $this->hasMany(OtpVerification::class);
    }

    /**
     * Roles available during public registration.
     */
    public static function registrationRoles(): array
    {
        return [
            self::ROLE_RENTER,
            self::ROLE_OWNER,
        ];
    }

    /**
     * Additional user role records.
     */
    public function userRoles()
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * Properties owned by the user.
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'owner_id');
    }

    /**
     * Properties saved by the renter.
     *
     * Existing local SpaceMatch feature.
     */
    public function savedProperties()
    {
        return $this->belongsToMany(
            Property::class,
            'saved_properties'
        )->withTimestamps();
    }

    /**
     * Rental requests submitted by the renter.
     *
     * Existing local SpaceMatch feature.
     */
    public function rentalRequests()
    {
        return $this->hasMany(
            RentalRequest::class,
            'renter_id'
        );
    }

    /**
     * Spaces saved by the renter.
     *
     * Coworker's SpaceMatch feature.
     */
    public function savedSpaces()
    {
        return $this->belongsToMany(
            Space::class,
            'saved_spaces'
        )->withTimestamps();
    }

    /**
     * Renter preferences.
     *
     * Coworker's SpaceMatch feature.
     */
    public function renterPreference()
    {
        return $this->hasOne(
            RenterPreference::class
        );
    }
}
