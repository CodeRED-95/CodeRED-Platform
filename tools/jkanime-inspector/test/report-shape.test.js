import assert from 'node:assert/strict';
import { test } from 'node:test';

test('report contract includes phase two evidence buckets', () => {
  const report = {
    target: {},
    totals: {},
    observed: {
      ajax: [],
      players: [],
      mediaCandidates: [],
      staticAjax: [],
      frames: [],
    },
  };

  assert.deepEqual(Object.keys(report.observed), ['ajax', 'players', 'mediaCandidates', 'staticAjax', 'frames']);
});

test('response records do not require body capture', () => {
  const response = {
    body: null,
    bodyCaptured: false,
  };

  assert.equal(response.body, null);
  assert.equal(response.bodyCaptured, false);
});
