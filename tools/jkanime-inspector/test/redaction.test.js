import assert from 'node:assert/strict';
import { test } from 'node:test';
import { redactBody, redactHeaders, sanitizeUrl } from '../src/redaction.js';

test('redacts sensitive headers', () => {
  const headers = redactHeaders({
    authorization: 'Bearer abc.def.ghi',
    cookie: 'session=secret',
    accept: 'text/html',
  });

  assert.equal(headers.authorization, '[REDACTED]');
  assert.equal(headers.cookie, '[REDACTED]');
  assert.equal(headers.accept, 'text/html');
});

test('redacts sensitive query parameters', () => {
  const url = sanitizeUrl('https://example.test/player?token=abc&name=demo&expires=123');

  assert.match(url, /token=/);
  assert.match(url, /expires=/);
  assert.match(url, /\[REDACTED\]|%5BREDACTED%5D/);
  assert.match(url, /name=demo/);
  assert.doesNotMatch(url, /abc|123/);
});

test('redacts media urls by default', () => {
  const url = sanitizeUrl('https://cdn.example.test/path/video.m3u8?token=abc');

  assert.equal(url, 'https://cdn.example.test/[media-url-redacted]');
});

test('redacts sensitive body values and marks truncation', () => {
  const body = redactBody('csrf=abc&session=def&ok=true', 10);

  assert.equal(body.truncated, true);
  assert.doesNotMatch(body.text, /abc|def/);
});
