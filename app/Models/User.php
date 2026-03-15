<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    // Constantes de rol
    const ROLE_ADMIN     = 'ADMIN';
    const ROLE_REMITENTE = 'REMITENTE';

    // Constantes de estado
    const STATUS_ACTIVE   = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_FIRED    = 'fired';

    /**
     * `role` y `status` se excluyen del fillable intencionalmente.
     *
     * Son campos sensibles que nunca deben asignarse desde input
     * del usuario — siempre se asignan explícitamente en el código.
     * Tenerlos en $fillable permitiría mass assignment attacks.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'first_name',
        'last_name',
        'cellphone',
        'brand_name',
        'brand_slug',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ─────────────────────────────────────────────
    // Helpers de rol y estado
    // ─────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isRemitente(): bool
    {
        return $this->role === self::ROLE_REMITENTE;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    // ─────────────────────────────────────────────
    // Relaciones
    // ─────────────────────────────────────────────

    public function clinicalImages()
    {
        return $this->hasMany(ClinicalImage::class);
    }
}