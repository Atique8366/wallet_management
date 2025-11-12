<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = ['idempotency_key','resource_type','resource_id','response','created_at'];
    protected $casts = ['response' => 'array'];

}
