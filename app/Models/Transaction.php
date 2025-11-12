<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = [
        'wallet_id','type','amount','related_wallet_id','reference_id','metadata','created_at'
    ];
    protected $casts = [
        'metadata' => 'array'
    ];
    
}
