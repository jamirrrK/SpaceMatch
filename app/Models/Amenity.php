<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Amenity extends Model
{
    protected $fillable = ['name', 'category'];

    public function spaces()
    {
        return $this->belongsToMany(Space::class);
    }
}
