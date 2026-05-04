<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    protected $fillable = [
        'checkout_no',
        'user_id',
        'location_id',
        'address_extra',
        'message',
        'product_id',
        'product_variant_id',
        'quantity',
        'price',
        'shipping_cost',
        'status',
        'courier_name',
        'tracking_number',
        'cancelled_by',
        'cancellation_reason',
        'cancelled_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the user that owns this order item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the delivery location for this order item.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the product in this order item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the product variant in this order item (if applicable).
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Get the review submitted for this order item.
     */
    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class);
    }
}
