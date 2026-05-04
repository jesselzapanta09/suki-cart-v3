<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreVerificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'action',
        'previous_status',
        'new_status',
        'rejection_reason',
        'performed_by',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by', 'uuid');
    }
}
