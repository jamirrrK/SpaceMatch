<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getReadableDescriptionAttribute(): string
    {
        $value = $this->description;

        if (empty($value)) {
            return 'No details recorded.';
        }

        $decoded = json_decode((string) $value, true);

        if (is_array($decoded)) {
            if (isset($decoded['property_id']) && array_key_exists('from', $decoded) && array_key_exists('to', $decoded)) {
                $parts = [
                    'Property #'.$decoded['property_id'],
                    'status changed',
                    'from '.($decoded['from'] ?? 'unknown'),
                    'to '.($decoded['to'] ?? 'unknown'),
                ];

                if (! empty($decoded['reason'])) {
                    $parts[] = 'Reason: '.(string) $decoded['reason'];
                }

                return implode(' ', $parts);
            }

            if (array_key_exists('reason', $decoded) && ! empty($decoded['reason'])) {
                return (string) $decoded['reason'];
            }

            return Str::limit(strip_tags((string) json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 120);
        }

        return Str::limit((string) $value, 120);
    }
}
