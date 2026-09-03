<?php

namespace Tests\Feature;

use Tests\TestCase;

class RpcProxyTest extends TestCase
{
    public function test_unknown_rpc_method_is_rejected(): void
    {
        $this->postJson('/api/rpc', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getBalance',
            'params' => [],
        ])->assertOk()->assertJsonPath('error.code', -32601);
    }

    public function test_empty_payload_is_parse_error(): void
    {
        $this->postJson('/api/rpc', [])->assertStatus(400);
    }
}
