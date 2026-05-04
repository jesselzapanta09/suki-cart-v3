<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'store_name',
        'category_id',
        'description',
        'banner',
        'verified_at',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($store) {
            if (empty($store->uuid)) {
                $store->uuid = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the route key for implicit model binding
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function verification()
    {
        return $this->hasOne(StoreVerification::class);
    }

    public function verificationLogs()
    {
        return $this->hasMany(StoreVerificationLog::class);
    }

    public function latestVerificationLog()
    {
        return $this->hasOne(StoreVerificationLog::class)->latestOfMany();
    }
}
