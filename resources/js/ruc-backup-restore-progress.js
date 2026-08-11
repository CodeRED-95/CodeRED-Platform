export function createRucRestoreProgress(statusUrl, initialState) {
  const terminal = ["completed", "failed", "cancelled"];

  return {
    statusUrl,
    operation: initialState,
    progress: initialState.progress || 0,
    message: initialState.message || "",
    errorMessage: initialState.error_message || "",
    pollInterval: null,

    startPolling() {
      if (terminal.includes(this.operation?.status)) return;
      this.pollInterval = setInterval(() => this.fetchStatus(), 2000);
    },

    stopPolling() {
      if (this.pollInterval === null) return;
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    },

    destroy() {
      this.stopPolling();
    },

    async fetchStatus() {
      try {
        const res = await fetch(this.statusUrl, { headers: { Accept: "application/json" } });

        if (res.status === 404 || res.status === 410) {
          this.stopPolling();
          return;
        }

        if (!res.ok) return;

        const data = await res.json();
        this.operation = data;
        this.progress = data.progress || 0;
        this.message = data.message || "";
        this.errorMessage = data.error_message || "";

        if (!terminal.includes(data.status)) return;

        this.stopPolling();

        if (data.status === "completed") {
          setTimeout(() => window.location.reload(), 2000);
        }
      } catch (error) {
        console.error("Error fetching restore status:", error);
      }
    },
  };
}
