<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\MoneyOperationRequest;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    protected WalletService $service;

    public function __construct(WalletService $service)
    {
        $this->service = $service;
    }

    // create wallet
    public function store(StoreWalletRequest $req): JsonResponse
    {
        $data = $req->validated();
        $wallet = Wallet::create($data);
        // create balance row
        $wallet->balance()->create(['wallet_id' => $wallet->id, 'balance' => 0]);
        return response()->json($wallet, 201);
    }

    // list wallets
    public function index(Request $req): JsonResponse
    {
        $q = Wallet::query();
        if ($owner = $req->query('owner')) {
            $q->where('owner_name', 'like', "%$owner%");
        }
        if ($currency = $req->query('currency')) {
            $q->where('currency', $currency);
        }
        return response()->json($q->paginate(20));
    }

    public function show($id): JsonResponse
    {
        $wallet = Wallet::with('balance')->findOrFail($id);
        return response()->json($wallet);
    }

    public function balance($id): JsonResponse
    {
        $balance = $this->service->getBalance((int)$id);
        // return response()->json(['wallet_id' => (int)$id, 'balance_minor' => $minor]);
        return response()->json(['wallet_id' => (int)$id, 'balance' => $balance]);
    }

    public function transactions(Request $req, $id): JsonResponse
{
    $query = Transaction::where('wallet_id', $id);
    if ($type = $req->query('type')) $query->where('type', $type);
    if ($from = $req->query('from')) $query->where('created_at', '>=', $from);
    if ($to = $req->query('to')) $query->where('created_at', '<=', $to);

    $per = (int)$req->query('per_page', 20);
    $txs = $query->orderBy('created_at', 'desc')->paginate($per);

    // 🔹 Convert amounts to formatted decimals using the service
    $txs->getCollection()->transform(function ($tx) {
        $service = app(\App\Services\WalletService::class);
        $tx->amount = $service->fromMinorUnits($tx->amount);
        if ($tx->related_wallet_id) {
            $tx->related_wallet = \App\Models\Wallet::find($tx->related_wallet_id)?->owner_name;
        }
        return $tx;
    });

    return response()->json($txs);
}


    public function deposit(MoneyOperationRequest $req, $id): JsonResponse
    {
        $payload = $req->validated();
        $amount = $payload['amount'];
        $metadata = $payload['metadata'] ?? [];
        $idemp = $req->header('Idempotency-Key') ?? null;

        try {
            $minor = $this->service->toMinorUnits($amount);
            $resp = $this->service->deposit((int)$id, $minor, $idemp, $metadata);
            if (isset($resp['idempotent_reuse']) && $resp['idempotent_reuse'] === true) {
                return response()->json($resp, 200);
            }
            return response()->json($resp, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function withdraw(MoneyOperationRequest $req, $id): JsonResponse
    {
        $payload = $req->validated();
        $amount = $payload['amount'];
        $metadata = $payload['metadata'] ?? [];
        $idemp = $req->header('Idempotency-Key') ?? null;

        try {
            $minor = $this->service->toMinorUnits($amount);
            $resp = $this->service->withdraw((int)$id, $minor, $idemp, $metadata);
            if (isset($resp['idempotent_reuse']) && $resp['idempotent_reuse'] === true) {
                return response()->json($resp, 200);
            }
            return response()->json($resp, 200);

        } catch (\Exception $e) {
            // differentiate insufficient funds (409)
            $msg = $e->getMessage();
            $status = stripos($msg, 'Insufficient') !== false ? 409 : 400;
            return response()->json(['error' => $msg], $status);
        }
    }
}
