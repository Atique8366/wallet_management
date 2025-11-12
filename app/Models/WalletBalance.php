<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletBalance extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $primaryKey = 'wallet_id';
    public $timestamps = false;
    protected $fillable = ['wallet_id','balance','updated_at'];
}
