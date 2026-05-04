<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'attributes',
        'price',
        'stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'attributes' => 'array',
    ];

    /**
     * Get the product this variant belongs to
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get cart items using this variant
     */
    public function cartItems()
    {
        return $this->hasMany(Cart::class, 'product_variant_id');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class, 'product_variant_id');
    }
}
