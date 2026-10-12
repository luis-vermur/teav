<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Color extends Model
{
    protected $table = 'colores';   // Laravel pluralizaría a "colors"

    protected $fillable = ['nombre', 'hex'];

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class);
    }
}
