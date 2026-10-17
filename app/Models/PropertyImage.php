<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyImage extends Model
{
    protected $fillable = ['property_id', 'space_id', 'image_path', 'is_primary', 'display_order'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'display_order' => 'integer'];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function space()
    {
        return $this->belongsTo(Space::class);
    }

    public function getUrlAttribute(): string
    {
        return route('property-images.show', $this);
    }
}
