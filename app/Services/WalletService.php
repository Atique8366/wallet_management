<?php
namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class WalletService
{
    // precision (number of decimal places for minor units). default 2.
    protected int $precision = 2;

    public function __construct()
    {
        $this->precision = config('wallet.precision', 2);
    }

    // Convert decimal string/float to minor units (int)
    public function toMinorUnits($amount): int
    {
        if (!is_numeric($amount)) {
            throw new Exception('Invalid amount value');
        }
        // Use bcmul if available to avoid float problems
        $mult = pow(10, $this->precision);
        // round to nearest integer
        $minor = (int) round(floatval($amount) * $mult);
        return $minor;
    }

    public function fromMinorUnits(int $amountMinor): string
    {
        $divisor = pow(10, $this->precision);
        return number_format($amountMinor / $divisor, $this->precision, '.', '');
    }

    // Deposit
    public function deposit(int $walletId, int $amountMinor, ?string $idempotencyKey = null, array $metadata = [])
    {
        if ($amountMinor <= 0) throw new Exception('Amount must be positive');

        return DB::transaction(function () use ($walletId, $amountMinor, $idempotencyKey, $metadata) {
            if ($idempotencyKey) {
                $existing = IdempotencyKey::where('idempotency_key', $idempotencyKey)
                    ->where('resource_type', 'deposit')
                    ->first();

                if ($existing) {
                    return [
                        'idempotent_reuse' => true,
                        'status' => 'success',
                        'message' => 'Idempotent replay – original transaction reused.',
                        'data' => $existing->response
                    ];
                }
            }

            $wallet = Wallet::findOrFail($walletId);

            // Lock balance row
            $balance = WalletBalance::where('wallet_id', $walletId)->lockForUpdate()->first();
            if (!$balance) {
                $balance = WalletBalance::create(['wallet_id' => $walletId, 'balance' => 0, 'updated_at' => Carbon::now()]);
            }

            $balance->balance += $amountMinor;
            $balance->updated_at = Carbon::now();
            $balance->save();

            $tx = Transaction::create([
                'wallet_id' => $walletId,
                'type' => 'deposit',
                'amount' => $amountMinor,
                'metadata' => $metadata,
                'created_at' => Carbon::now()
            ]);

            $resp = [
                'transaction_id' => $tx->id,
                'wallet_id' => $walletId,
                'amount' => $this->fromMinorUnits($amountMinor),
                'new_balance' => $this->fromMinorUnits($balance->balance)
            ];



            if ($idempotencyKey) {
                IdempotencyKey::create([
                    'idempotency_key' => $idempotencyKey,
                    'resource_type' => 'deposit',
                    'resource_id' => $tx->id,
                    'response' => $resp,
                    'created_at' => Carbon::now()
                ]);
            }

            return $resp;
        });
    }

    // Withdraw
    public function withdraw(int $walletId, int $amountMinor, ?string $idempotencyKey = null, array $metadata = [])
    {
        if ($amountMinor <= 0) throw new Exception('Amount must be positive');

        return DB::transaction(function () use ($walletId, $amountMinor, $idempotencyKey, $metadata) {
            if ($idempotencyKey) {
                $existing = IdempotencyKey::where('idempotency_key', $idempotencyKey)
                    ->where('resource_type', 'withdraw')
                    ->first();
                if ($existing) {
                        return [
                            'idempotent_reuse' => true,
                            'status' => 'success',
                            'message' => 'Idempotent replay – original transaction reused.',
                            'data' => $existing->response
                        ];
                }
            }

            $wallet = Wallet::findOrFail($walletId);

            $balance = WalletBalance::where('wallet_id', $walletId)->lockForUpdate()->first();
            if (!$balance) throw new Exception('Wallet balance record not found');

            if ($balance->balance < $amountMinor) throw new Exception('Insufficient funds');

            $balance->balance -= $amountMinor;
            $balance->updated_at = Carbon::now();
            $balance->save();

            $tx = Transaction::create([
                'wallet_id' => $walletId,
                'type' => 'withdrawal',
                'amount' => -$amountMinor, // negative to indicate debit
                'metadata' => $metadata,
                'created_at' => Carbon::now()
            ]);

            // $resp = [
            //     'transaction_id' => $tx->id,
            //     'wallet_id' => $walletId,
            //     'new_balance' => $balance->balance
            // ];
            $resp = [
                'transaction_id' => $tx->id,
                'wallet_id' => $walletId,
                'amount' => $this->fromMinorUnits($amountMinor),
                'new_balance' => $this->fromMinorUnits($balance->balance)
            ];

            if ($idempotencyKey) {
                IdempotencyKey::create([
                    'idempotency_key' => $idempotencyKey,
                    'resource_type' => 'withdraw',
                    'resource_id' => $tx->id,
                    'response' => $resp,
                    'created_at' => Carbon::now()
                ]);
            }

            return $resp;
        });
    }

    // Transfer (double-entry)
    public function transfer(int $sourceId, int $targetId, int $amountMinor, ?string $idempotencyKey = null, array $metadata = [])
    {
        if ($amountMinor <= 0) throw new Exception('Amount must be positive');
        if ($sourceId == $targetId) throw new Exception('Cannot transfer to same wallet');

        return DB::transaction(function () use ($sourceId, $targetId, $amountMinor, $idempotencyKey, $metadata) {
            if ($idempotencyKey) {
                $existing = IdempotencyKey::where('idempotency_key', $idempotencyKey)
                    ->where('resource_type', 'transfer')
                    ->first();
                if ($existing) {
                    return [
                        'idempotent_reuse' => true,
                        'status' => 'success',
                        'message' => 'Idempotent replay – original transfer reused.',
                        'data' => $existing->response
                    ];
                }
            }

            $source = Wallet::findOrFail($sourceId);
            $target = Wallet::findOrFail($targetId);

            if ($source->currency !== $target->currency) {
                throw new Exception('Currency mismatch between wallets');
            }

            // Lock balances in deterministic order to avoid deadlocks
            $firstId = min($sourceId, $targetId);
            $secondId = max($sourceId, $targetId);

            $firstBalance = WalletBalance::where('wallet_id', $firstId)->lockForUpdate()->first();
            $secondBalance = WalletBalance::where('wallet_id', $secondId)->lockForUpdate()->first();

            if (!$firstBalance) $firstBalance = WalletBalance::create(['wallet_id' => $firstId, 'balance' => 0, 'updated_at' => Carbon::now()]);
            if (!$secondBalance) $secondBalance = WalletBalance::create(['wallet_id' => $secondId, 'balance' => 0, 'updated_at' => Carbon::now()]);

            $sourceBalance = ($sourceId == $firstId) ? $firstBalance : $secondBalance;
            $targetBalance = ($targetId == $firstId) ? $firstBalance : $secondBalance;

            if ($sourceBalance->balance < $amountMinor) throw new Exception('Insufficient funds in source wallet');

            // perform debit and credit
            $sourceBalance->balance -= $amountMinor;
            $sourceBalance->updated_at = Carbon::now();
            $sourceBalance->save();

            $targetBalance->balance += $amountMinor;
            $targetBalance->updated_at = Carbon::now();
            $targetBalance->save();

            // create transactions
            $txDebit = Transaction::create([
                'wallet_id' => $sourceId,
                'type' => 'transfer_debit',
                'amount' => -$amountMinor,
                'related_wallet_id' => $targetId,
                'metadata' => $metadata,
                'created_at' => Carbon::now()
            ]);

            $txCredit = Transaction::create([
                'wallet_id' => $targetId,
                'type' => 'transfer_credit',
                'amount' => $amountMinor,
                'related_wallet_id' => $sourceId,
                'metadata' => $metadata,
                'created_at' => Carbon::now()
            ]);

            $transfer = Transfer::create([
                'source_wallet_id' => $sourceId,
                'target_wallet_id' => $targetId,
                'amount' => $amountMinor,
                'idempotency_key' => $idempotencyKey,
                'created_at' => Carbon::now()
            ]);

            $resp = [
                'transfer_id' => $transfer->id,
                'amount' => $this->fromMinorUnits($amountMinor),
                'debit_tx' => $txDebit->id,
                'credit_tx' => $txCredit->id,
                'source_new_balance' => $this->fromMinorUnits($sourceBalance->balance),
                'target_new_balance' => $this->fromMinorUnits($targetBalance->balance)
            ];

            if ($idempotencyKey) {
                IdempotencyKey::create([
                    'idempotency_key' => $idempotencyKey,
                    'resource_type' => 'transfer',
                    'resource_id' => $transfer->id,
                    'response' => $resp,
                    'created_at' => Carbon::now()
                ]);
            }

            return $resp;
        });
    }

    // get balance in minor units
    public function getBalance(int $walletId): int
    {
        $balance = WalletBalance::where('wallet_id', $walletId)->first();
       return $balance ? $this->fromMinorUnits($balance->balance) : "0.00";
    }

   
}
