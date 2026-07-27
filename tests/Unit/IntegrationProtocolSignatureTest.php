<?php

namespace Tests\Unit;

use App\Services\Integrations\IntegrationProtocolService;
use Tests\TestCase;

class IntegrationProtocolSignatureTest extends TestCase
{
    public function test_canonical_payload_matches_connector_contract(): void
    {
        $service = new IntegrationProtocolService;
        $body = '{"a":1,"b":2}';

        $canonical = $service->canonicalPayload('post', '/api/v1/x', '100', 'nonce', $body);

        $this->assertSame('POST
/api/v1/x
100
nonce
43258cff783fe7036d8a43033f830adfc60ec037382473548ac742b888292777', $canonical);
        $this->assertSame('c8db8fe60cd0321457a422a3428f52e83ca0ade911ddf8ecac632d2ef7966ac1', hash_hmac('sha256', $canonical, 'secret'));
    }
}
