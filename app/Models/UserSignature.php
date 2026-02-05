<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSignature extends Model
{
    protected $fillable = [
        'email',
        'signature_html',
    ];

    public static function findByEmail(string $email): ?self
    {
        return static::where('email', $email)->first();
    }
}
