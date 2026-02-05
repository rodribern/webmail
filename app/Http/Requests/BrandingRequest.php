<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return session('user.is_admin', false);
    }

    public function rules(): array
    {
        return [
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'custom_css' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'primary_color.required' => 'A cor primária é obrigatória.',
            'primary_color.regex' => 'A cor primária deve estar no formato hexadecimal (#RRGGBB).',
            'secondary_color.required' => 'A cor secundária é obrigatória.',
            'secondary_color.regex' => 'A cor secundária deve estar no formato hexadecimal (#RRGGBB).',
            'background_color.required' => 'A cor de fundo é obrigatória.',
            'background_color.regex' => 'A cor de fundo deve estar no formato hexadecimal (#RRGGBB).',
            'sidebar_color.required' => 'A cor da barra lateral é obrigatória.',
            'sidebar_color.regex' => 'A cor da barra lateral deve estar no formato hexadecimal (#RRGGBB).',
            'custom_css.max' => 'O CSS customizado não pode exceder 10.000 caracteres.',
        ];
    }

    /**
     * Sanitiza o CSS customizado removendo códigos potencialmente perigosos.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('custom_css') && $this->custom_css) {
            $css = $this->custom_css;

            // Remove @import
            $css = preg_replace('/@import\s+[^;]+;?/i', '', $css);

            // Remove url() com protocolos externos
            $css = preg_replace('/url\s*\(\s*["\']?\s*(https?:|ftp:|\/\/)[^)]+\)/i', 'url()', $css);

            // Remove expression() (IE)
            $css = preg_replace('/expression\s*\([^)]*\)/i', '', $css);

            // Remove javascript:
            $css = preg_replace('/javascript\s*:/i', '', $css);

            // Remove behavior (IE)
            $css = preg_replace('/behavior\s*:\s*[^;]+;?/i', '', $css);

            // Remove -moz-binding
            $css = preg_replace('/-moz-binding\s*:\s*[^;]+;?/i', '', $css);

            $this->merge(['custom_css' => trim($css)]);
        }
    }
}
