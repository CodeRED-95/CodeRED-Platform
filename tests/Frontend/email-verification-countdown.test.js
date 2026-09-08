import test from "node:test";
import assert from "node:assert/strict";
import { createEmailVerificationCountdown, initializeEmailVerificationCountdown } from "../../resources/js/email-verification-countdown.js";

test("email verification countdown decreases and formats remaining time", () => {
  let tick;
  const countdown = createEmailVerificationCountdown(61, {
    setIntervalImpl: (callback) => {
      tick = callback;
      return 1;
    },
    clearIntervalImpl: () => {},
    onComplete: () => {},
  });
  assert.equal(countdown.formatted, "01:01");

  countdown.start();
  tick();
  assert.equal(countdown.seconds, 60);
  assert.equal(countdown.formatted, "01:00");
});

test("email verification countdown reloads when cooldown ends", () => {
  let tick;
  let reloads = 0;
  const countdown = createEmailVerificationCountdown(1, {
    setIntervalImpl: (callback) => {
      tick = callback;
      return 1;
    },
    clearIntervalImpl: () => {},
    onComplete: () => { reloads += 1; },
  });

  countdown.start();
  tick();

  assert.equal(countdown.seconds, 0);
  assert.equal(reloads, 1);
});

test("email verification countdown initializes the rendered element", () => {
  let tick;
  const display = { textContent: "" };
  const element = {
    dataset: { emailVerificationCountdown: "2" },
    querySelector: () => display,
  };

  initializeEmailVerificationCountdown(
    { querySelectorAll: () => [element] },
    {
      setInterval: (callback) => {
        tick = callback;
        return 1;
      },
      clearInterval: () => {},
      location: { reload: () => {} },
    },
  );

  assert.equal(display.textContent, "00:02");
  tick();
  assert.equal(display.textContent, "00:01");
});
