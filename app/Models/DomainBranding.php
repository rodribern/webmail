<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainBranding extends Model
{
    protected $table = 'domain_branding';

    protected $fillable = [
        'domain_id',
        'logo_path',
        'favicon_path',
        'primary_color',
        'secondary_color',
        'background_color',
        'sidebar_color',
        'custom_css',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function toArray(): array
    {
        return [
            'logo' => $this->logo_path ? asset('storage/' . $this->logo_path) : null,
            'favicon' => $this->favicon_path ? asset('storage/' . $this->favicon_path) : null,
            'colors' => [
                'primary' => $this->primary_color,
                'secondary' => $this->secondary_color,
                'background' => $this->background_color,
                'sidebar' => $this->sidebar_color,
            ],
            'custom_css' => $this->custom_css,
        ];
    }

    public static function getDefault(): array
    {
        return [
            'logo' => null,
            'favicon' => null,
            'colors' => [
                'primary' => '#3B82F6',
                'secondary' => '#1E40AF',
                'background' => '#F9FAFB',
                'sidebar' => '#FFFFFF',
            ],
            'custom_css' => null,
        ];
    }
}
