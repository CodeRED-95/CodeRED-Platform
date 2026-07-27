"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const node_test_1 = __importDefault(require("node:test"));
const strict_1 = __importDefault(require("node:assert/strict"));
const GenericFunctions_1 = require("../nodes/CodeRED/GenericFunctions");
(0, node_test_1.default)('canonical payload and hmac are deterministic', () => {
    const body = (0, GenericFunctions_1.stableJson)({ b: 2, a: 1 });
    strict_1.default.equal(body, '{"a":1,"b":2}');
    const canonical = (0, GenericFunctions_1.canonicalPayload)('post', '/api/v1/x', '100', 'nonce', body);
    strict_1.default.equal(canonical, 'POST\n/api/v1/x\n100\nnonce\n43258cff783fe7036d8a43033f830adfc60ec037382473548ac742b888292777');
    strict_1.default.equal((0, GenericFunctions_1.hmacSignature)('secret', canonical), 'c8db8fe60cd0321457a422a3428f52e83ca0ade911ddf8ecac632d2ef7966ac1');
});
