<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Option extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'price',
        'price_per_participant',
        'is_active',
        'is_upsellable',
        'max_quantity',
        'sort_order',
        'icon',
        'image_url',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_per_participant' => 'decimal:2',
        'is_active' => 'boolean',
        'is_upsellable' => 'boolean',
        'max_quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'reservation_options')
            ->withPivot(['quantity', 'unit_price', 'total_price', 'added_at_stage', 'added_at', 'is_delivered', 'delivered_at', 'delivery_notes'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeUpsellable($query)
    {
        return $query->where('is_upsellable', true);
    }

    public function calculatePrice(int $participants = 1): float
    {
        $total = $this->price;
        
        if ($this->price_per_participant) {
            $total += $this->price_per_participant * $participants;
        }

        return $total;
    }
}
