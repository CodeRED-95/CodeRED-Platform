import test from 'node:test';
import assert from 'node:assert/strict';
import { canonicalPayload, hmacSignature, stableJson } from '../nodes/CodeRED/GenericFunctions';

test('canonical payload and hmac are deterministic', () => {
  const body = stableJson({ b: 2, a: 1 });
  assert.equal(body, '{"a":1,"b":2}');
  const canonical = canonicalPayload('post', '/api/v1/x', '100', 'nonce', body);
  assert.equal(canonical, 'POST\n/api/v1/x\n100\nnonce\n43258cff783fe7036d8a43033f830adfc60ec037382473548ac742b888292777');
  assert.equal(hmacSignature('secret', canonical), 'c8db8fe60cd0321457a422a3428f52e83ca0ade911ddf8ecac632d2ef7966ac1');
});
