<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'subject',
        'body_html',
        'body_text',
        'available_variables',
        'category',
        'is_active',
    ];

    protected $casts = [
        'available_variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get template by code.
     */
    public static function getByCode(string $code): ?self
    {
        return self::where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Render template with variables.
     */
    public function render(array $variables): array
    {
        $subject = $this->subject;
        $bodyHtml = $this->body_html;
        $bodyText = $this->body_text;

        // Replace variables in subject and body
        foreach ($variables as $key => $value) {
            $placeholder = '{{ ' . $key . ' }}';
            $subject = str_replace($placeholder, $value, $subject);
            $bodyHtml = str_replace($placeholder, $value, $bodyHtml);
            $bodyText = str_replace($placeholder, $value, $bodyText);
        }

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ];
    }

    /**
     * Get all available variables for this template.
     */
    public function getVariables(): array
    {
        return $this->available_variables ?? [];
    }
}
