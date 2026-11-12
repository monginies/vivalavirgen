<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    public const TYPES = ['company' => 'Empresa', 'individual' => 'Particular'];

    protected $fillable = [
        'type', 'name', 'tax_id', 'contact_name', 'email', 'phone',
        'address', 'postal_code', 'city', 'province', 'notes',
    ];

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
