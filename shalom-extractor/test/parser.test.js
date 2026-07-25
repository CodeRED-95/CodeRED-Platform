'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');

test('basic runtime is available', () => {
  assert.equal(typeof String.prototype.trim, 'function');
});
