<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\WalletBalance;
use App\Models\Transaction;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = ['owner_name','currency'];

    public function balance()
    {
        return $this->hasOne(WalletBalance::class, 'wallet_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
