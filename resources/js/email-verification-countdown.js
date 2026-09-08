export function createEmailVerificationCountdown(initialSeconds, options = {}) {
  let seconds = Math.max(0, Number(initialSeconds) || 0);
  let timer = null;
  const setIntervalImpl = options.setIntervalImpl ?? ((callback, delay) => window.setInterval(callback, delay));
  const clearIntervalImpl = options.clearIntervalImpl ?? ((handle) => window.clearInterval(handle));
  const onTick = options.onTick ?? (() => {});
  const onComplete = options.onComplete ?? (() => window.location.reload());

  return {
    get seconds() {
      return seconds;
    },

    get formatted() {
      const minutes = Math.floor(seconds / 60).toString().padStart(2, "0");
      const remainder = (seconds % 60).toString().padStart(2, "0");

      return `${minutes}:${remainder}`;
    },

    start() {
      if (seconds <= 0 || timer !== null) return;

      timer = setIntervalImpl(() => {
        seconds = Math.max(0, seconds - 1);
        onTick(seconds);
        if (seconds === 0) {
          this.stop();
          onComplete();
        }
      }, 1000);
    },

    stop() {
      if (timer !== null) {
        clearIntervalImpl(timer);
        timer = null;
      }
    },
  };
}

export function initializeEmailVerificationCountdown(documentRef = document, windowRef = window) {
  documentRef.querySelectorAll("[data-email-verification-countdown]").forEach((element) => {
    const display = element.querySelector("[data-countdown-display]");
    let render = () => {};
    const countdown = createEmailVerificationCountdown(element.dataset.emailVerificationCountdown, {
      setIntervalImpl: (callback, delay) => windowRef.setInterval(callback, delay),
      clearIntervalImpl: (handle) => windowRef.clearInterval(handle),
      onTick: () => render(),
      onComplete: () => windowRef.location.reload(),
    });

    render = () => {
      if (display) display.textContent = countdown.formatted;
    };

    render();
    countdown.start();
  });
}
