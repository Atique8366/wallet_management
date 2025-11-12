<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = ['source_wallet_id','target_wallet_id','amount','idempotency_key','created_at'];

}
