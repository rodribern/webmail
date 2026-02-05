<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Domain extends Model
{
    protected $fillable = [
        'name',
        'display_name',
    ];

    public function branding(): HasOne
    {
        return $this->hasOne(DomainBranding::class);
    }

    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    public static function extractFromEmail(string $email): string
    {
        return substr($email, strpos($email, '@') + 1);
    }
}
