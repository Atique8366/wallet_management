<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransferRequest;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    protected WalletService $service;

    public function __construct(WalletService $service)
    {
        $this->service = $service;
    }

    public function transfer(TransferRequest $req): JsonResponse
    {
        $payload = $req->validated();
        $source = (int)$payload['source_wallet_id'];
        $target = (int)$payload['target_wallet_id'];
        $amount = $payload['amount'];
        $metadata = $payload['metadata'] ?? [];
        $idemp = $req->header('Idempotency-Key') ?? null;

        try {
            $minor = $this->service->toMinorUnits($amount);
            $resp = $this->service->transfer($source, $target, $minor, $idemp, $metadata);
            if (isset($resp['idempotent_reuse']) && $resp['idempotent_reuse'] === true) {
                return response()->json($resp, 200);
            }
            return response()->json($resp, 200);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $status = stripos($msg, 'Insufficient') !== false ? 409 : 400;
            return response()->json(['error' => $msg], $status);
        }
    }
}
