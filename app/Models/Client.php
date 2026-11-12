<?php

namespace App\Models;

use App\Notifications\ClientResetPassword;
use App\Notifications\ClientVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Cliente. Si tiene contraseña puede entrar en la web de clientes (guard «client»). */
class Client extends Authenticatable implements MustVerifyEmail
{
    use MustVerifyEmailTrait, Notifiable, SoftDeletes;

    public const TYPES = ['company' => 'Empresa', 'individual' => 'Particular'];

    protected $fillable = [
        'type', 'client_category_id', 'name', 'trade_name', 'tax_id', 'contact_name', 'email', 'phone',
        'address', 'postal_code', 'city', 'province', 'notes',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ClientCategory::class, 'client_category_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function discountPercent(): float
    {
        return (float) ($this->category?->discount_percent ?? 0);
    }

    public function hasPortalAccess(): bool
    {
        return filled($this->password);
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null && $this->hasVerifiedEmail();
    }

    /** Estado del acceso web para mostrar en el backoffice. @return array{0: string, 1: string} [texto, color] */
    public function portalStatus(): array
    {
        return match (true) {
            ! $this->hasPortalAccess() => ['Sin acceso web', 'grey'],
            ! $this->hasVerifiedEmail() => ['Email sin confirmar', 'amber'],
            $this->approved_at === null => ['Pendiente de aprobar', 'amber'],
            default => ['Acceso aprobado', 'green'],
        };
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new ClientVerifyEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ClientResetPassword($token));
    }
}
