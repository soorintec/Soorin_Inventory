<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory, UsesTenantConnection;

    protected $fillable = ['name', 'country', 'phone', 'email', 'address', 'notes'];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
