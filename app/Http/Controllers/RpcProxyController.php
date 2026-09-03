<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\HeliusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RpcProxyController extends Controller
{
    public function __construct(private readonly HeliusService $helius) {}

    public function proxy(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        if (empty($payload)) {
            return response()->json(['jsonrpc' => '2.0', 'error' => ['code' => -32700, 'message' => 'Parse error'], 'id' => null], 400);
        }

        $result = $this->helius->proxyRpc($payload);

        return response()->json($result);
    }
}
