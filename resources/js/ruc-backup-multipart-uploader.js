// Sube un backup RUC dividido en partes (manifest.json + *.partNNNN,
// generado por packages/ruc-tools) sin reconstruir el archivo en el
// navegador: cada parte se envía en un request HTTP independiente
// (~90 MiB), justo lo que evita el 413 de Cloudflare. Usa XMLHttpRequest
// (no fetch) porque necesita xhr.upload.onprogress para progreso real
// dentro de cada parte en curso.
//
// No hace nada con el resultado más que reportarlo vía el estado de Alpine
// — el ensamblado, la validación de checksums y el registro del backup
// final ocurren siempre en el servidor (RucBackupMultipartUploadService).
//
// Máquina de estados — exactamente uno a la vez, nunca combinados:
//   idle -> manifest_selected -> ready -> uploading -> assembling -> completed
//                                                                  -> failed
//   (cualquier estado no terminal) -> cancelled
//
// Nota: "assembling" cubre tanto el ensamblado como la validación del lado
// servidor (checksum final, pg_restore --list, registro del RucBackup).
// Esas sub-etapas ocurren dentro de UNA sola respuesta HTTP (la de la
// última parte) — el cliente no tiene forma honesta de mostrar progreso
// granular ahí sin inventar temporización falsa, así que se muestra un
// progreso indeterminado + lo único que el cliente sabe con certeza en ese
// punto (todas las partes ya fueron verificadas individualmente).

const RETRY_DELAYS_MS = [1000, 3000, 5000];
const PART_FILENAME_PATTERN = /\.part(\d{4,})$/i;
const RESUME_STORAGE_PREFIX = "codered:ruc-backup-multipart:";
const SPEED_SAMPLE_WINDOW = 5;

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? "";
}

function formatBytes(bytes) {
  if (!bytes || bytes <= 0) return "0 B";
  const units = ["B", "KiB", "MiB", "GiB"];
  const exponent = Math.min(
    Math.floor(Math.log(bytes) / Math.log(1024)),
    units.length - 1,
  );
  const value = bytes / Math.pow(1024, exponent);

  return `${exponent === 0 ? value : value.toFixed(1)} ${units[exponent]}`;
}

function formatDuration(totalSeconds) {
  const seconds = Math.max(0, Math.round(totalSeconds));
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;

  return m > 0 ? `${m}m ${s}s` : `${s}s`;
}

function formatClock(totalSeconds) {
  const seconds = Math.max(0, Math.floor(totalSeconds));
  const m = Math.floor(seconds / 60).toString().padStart(2, "0");
  const s = (seconds % 60).toString().padStart(2, "0");

  return `${m}:${s}`;
}

function partIndexFromFilename(filename) {
  const match = filename.match(PART_FILENAME_PATTERN);

  return match ? parseInt(match[1], 10) : null;
}

/** POST/GET/DELETE JSON vía XHR, con soporte opcional de onUploadProgress. */
function xhrJson(method, url, { body, file, onUploadProgress } = {}) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader("X-CSRF-TOKEN", csrfToken());
    xhr.setRequestHeader("Accept", "application/json");

    if (file) {
      const form = new FormData();
      form.append("part", file);
      if (onUploadProgress) {
        xhr.upload.addEventListener("progress", (event) => {
          if (event.lengthComputable) onUploadProgress(event.loaded, event.total);
        });
      }
      xhr.send(form);
    } else if (body !== undefined) {
      xhr.setRequestHeader("Content-Type", "application/json");
      xhr.send(JSON.stringify(body));
    } else {
      xhr.send();
    }

    xhr.onload = () => {
      let data = null;
      try {
        data = xhr.responseText ? JSON.parse(xhr.responseText) : null;
      } catch {
        // respuesta no-JSON (p. ej. 419/500 con página HTML): se maneja abajo
      }

      if (xhr.status >= 200 && xhr.status < 300) {
        resolve(data);
      } else {
        reject({
          status: xhr.status,
          message: data?.message || `Error HTTP ${xhr.status}.`,
        });
      }
    };

    xhr.onerror = () => reject({ status: 0, message: "Error de red. Verifica tu conexión." });
  });
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export function createRucBackupMultipartUploader(routes) {
  return {
    // --- estado expuesto a la vista: SIEMPRE uno de estos, nunca combinados ---
    stage: "idle",
    manifest: null,
    manifestError: null,
    partFiles: [],
    partsError: null,
    uploadUuid: null,
    completedParts: [],
    currentPartIndex: null,
    currentPartLoadedBytes: 0,
    completedBytes: 0,
    errorMessage: null,
    rucBackupId: null,
    cancelling: false,

    // --- velocidad / tiempo transcurrido ---
    startedAt: null,
    nowTick: 0,
    _tickTimer: null,
    _speedSamples: [],

    get totalParts() {
      return this.manifest?.total_parts ?? 0;
    },
    get totalSizeBytes() {
      return this.manifest?.total_size_bytes ?? 0;
    },
    get overallProgressBytes() {
      return this.completedBytes + this.currentPartLoadedBytes;
    },
    get overallProgressPercent() {
      if (!this.totalSizeBytes) return 0;

      return Math.min(100, Math.round((this.overallProgressBytes / this.totalSizeBytes) * 100));
    },
    get formattedProgress() {
      return `${formatBytes(this.overallProgressBytes)} / ${formatBytes(this.totalSizeBytes)}`;
    },
    get elapsedLabel() {
      if (!this.startedAt) return "00:00";

      return formatClock(((this.nowTick || Date.now()) - this.startedAt) / 1000);
    },
    get speedBytesPerSecond() {
      const samples = this._speedSamples;
      if (samples.length < 2) return 0;
      const first = samples[0];
      const last = samples[samples.length - 1];
      const deltaSeconds = (last.t - first.t) / 1000;
      if (deltaSeconds <= 0) return 0;

      return Math.max(0, (last.bytes - first.bytes) / deltaSeconds);
    },
    get speedLabel() {
      const speed = this.speedBytesPerSecond;

      return speed > 0 ? `${formatBytes(speed)}/s` : null;
    },
    get etaLabel() {
      const speed = this.speedBytesPerSecond;
      if (speed <= 0) return null;
      const remaining = Math.max(0, this.totalSizeBytes - this.overallProgressBytes);

      return `~${formatDuration(remaining / speed)} restantes`;
    },
    formatBytes,

    startTicking() {
      this.startedAt = this.startedAt || Date.now();
      this.nowTick = Date.now();
      this._tickTimer = window.setInterval(() => {
        this.nowTick = Date.now();
      }, 1000);
    },
    stopTicking() {
      if (this._tickTimer) {
        window.clearInterval(this._tickTimer);
        this._tickTimer = null;
      }
    },
    recordProgressSample(bytes) {
      const samples = this._speedSamples;
      samples.push({ t: Date.now(), bytes });
      if (samples.length > SPEED_SAMPLE_WINDOW) samples.shift();
    },

    async onManifestSelected(event) {
      this.manifestError = null;
      this.manifest = null;
      const file = event.target.files?.[0];
      if (!file) return;

      try {
        const text = await file.text();
        const parsed = JSON.parse(text);
        this.assertManifestLooksValid(parsed);
        this.manifest = parsed;
        this.stage = "manifest_selected";
        this.tryResumePreviousSession();
      } catch (error) {
        this.manifestError =
          error?.message || "No se pudo leer el manifest (¿es un JSON válido?).";
      }
    },

    // Validación básica en el cliente — el servidor SIEMPRE revalida todo
    // (nunca se confía en esto por seguridad, es solo para feedback rápido).
    assertManifestLooksValid(manifest) {
      if (!manifest || typeof manifest !== "object") {
        throw new Error("El manifest no es un objeto JSON válido.");
      }
      for (const key of ["format_version", "backup_type", "total_parts", "total_size_bytes", "sha256", "parts", "original_filename"]) {
        if (!(key in manifest)) {
          throw new Error(`El manifest no contiene "${key}".`);
        }
      }
      if (manifest.backup_type !== "ruc_records") {
        throw new Error('El manifest no es de tipo "ruc_records".');
      }
      if (!Array.isArray(manifest.parts) || manifest.parts.length !== manifest.total_parts) {
        throw new Error("El manifest está incompleto: el número de partes no coincide.");
      }
    },

    onPartsSelected(event) {
      this.partsError = null;
      const files = Array.from(event.target.files || []);
      if (!files.length) return;

      const withIndex = files.map((file) => ({ file, index: partIndexFromFilename(file.name) }));
      const missingIndex = withIndex.find((entry) => entry.index === null);
      if (missingIndex) {
        this.partsError = `"${missingIndex.file.name}" no parece una parte válida (se esperaba un nombre terminado en .partNNNN).`;
        return;
      }

      withIndex.sort((a, b) => a.index - b.index);

      const manifestFilenames = new Set(this.manifest.parts.map((p) => p.filename));
      const unexpected = withIndex.find((entry) => !manifestFilenames.has(entry.file.name));
      if (unexpected) {
        this.partsError = `"${unexpected.file.name}" no pertenece a este backup (no aparece en el manifest).`;
        return;
      }

      this.partFiles = withIndex.map((entry) => entry.file);
      if (this.partFiles.length === this.totalParts) {
        this.stage = "ready";
      } else {
        this.stage = "manifest_selected";
        this.partsError = `Seleccionaste ${this.partFiles.length} de ${this.totalParts} partes. Selecciónalas todas juntas.`;
      }
    },

    get selectedPartsSummary() {
      return `${this.partFiles.length} de ${this.totalParts} partes listas`;
    },
    get selectedPartsSizeLabel() {
      return formatBytes(this.partFiles.reduce((sum, f) => sum + f.size, 0));
    },

    resumeStorageKey() {
      return this.manifest ? RESUME_STORAGE_PREFIX + this.manifest.sha256 : null;
    },

    tryResumePreviousSession() {
      const key = this.resumeStorageKey();
      const savedUuid = key ? window.localStorage.getItem(key) : null;
      if (savedUuid) {
        this.uploadUuid = savedUuid;
      }
    },

    async startUpload() {
      if (this.stage !== "ready" || this.cancelling) return;

      this.errorMessage = null;
      this.stage = "uploading";
      this._speedSamples = [];
      this.startTicking();

      try {
        if (this.uploadUuid) {
          await this.resumeSession();
        } else {
          await this.createSession();
        }

        for (let i = 0; i < this.partFiles.length; i++) {
          const index = partIndexFromFilename(this.partFiles[i].name);
          if (this.completedParts.includes(index)) continue; // ya subida (resume)

          this.currentPartIndex = index;
          this.currentPartLoadedBytes = 0;
          await this.uploadPartWithRetries(index, this.partFiles[i]);
          this.completedBytes += this.partFiles[i].size;
          this.completedParts.push(index);
          this.currentPartLoadedBytes = 0;
        }

        this.forgetResumeSession();

        if (this.rucBackupId) {
          this.finishSuccessfully();
        } else {
          // Última parte ya enviada por completo: el servidor está
          // ensamblando + validando dentro de esa misma respuesta.
          this.stage = "assembling";
        }
      } catch (error) {
        this.stopTicking();
        this.stage = "failed";
        this.errorMessage = error?.message || "La importación falló.";
      }
    },

    finishSuccessfully() {
      this.stopTicking();
      this.stage = "completed";
    },

    async createSession() {
      const response = await xhrJson("POST", routes.store, { body: { manifest: this.manifest } });
      this.uploadUuid = response.upload_uuid;
      this.completedParts = response.already_uploaded_parts || [];
      const key = this.resumeStorageKey();
      if (key) window.localStorage.setItem(key, this.uploadUuid);
    },

    async resumeSession() {
      try {
        const response = await xhrJson("GET", routes.show.replace(":upload", this.uploadUuid));
        this.completedParts = response.uploaded_parts || [];
        this.completedBytes = response.received_bytes || 0;
        if (response.status === "completed") {
          this.rucBackupId = response.ruc_backup_id;
        }
      } catch {
        // La sesión guardada ya no existe (expiró/fue cancelada): empezar de nuevo.
        this.forgetResumeSession();
        this.uploadUuid = null;
        this.completedParts = [];
        this.completedBytes = 0;
        await this.createSession();
      }
    },

    async uploadPartWithRetries(index, file) {
      let lastError = null;
      for (let attempt = 0; attempt <= RETRY_DELAYS_MS.length; attempt++) {
        if (attempt > 0) await sleep(RETRY_DELAYS_MS[attempt - 1]);
        this.currentPartLoadedBytes = 0;

        try {
          const url = routes.uploadPart
            .replace(":upload", this.uploadUuid)
            .replace(":index", String(index));
          const response = await xhrJson("POST", url, {
            file,
            onUploadProgress: (loaded) => {
              this.currentPartLoadedBytes = loaded;
              this.recordProgressSample(this.completedBytes + loaded);
            },
          });
          if (response.ruc_backup_id) {
            this.rucBackupId = response.ruc_backup_id;
          }

          return;
        } catch (error) {
          lastError = error;
          // Un 422 es un rechazo definitivo del servidor (checksum/tamaño/
          // permiso) — reintentar no lo va a arreglar.
          if (error.status === 422 || error.status === 403) break;
        }
      }
      throw new Error(lastError?.message || `No se pudo subir la parte ${index}.`);
    },

    forgetResumeSession() {
      const key = this.resumeStorageKey();
      if (key) window.localStorage.removeItem(key);
    },

    async cancel() {
      if (!this.uploadUuid || this.cancelling) return;
      this.cancelling = true;
      try {
        await xhrJson("DELETE", routes.destroy.replace(":upload", this.uploadUuid));
      } catch {
        // Si el servidor ya la había marcado terminal, no es un error real.
      }
      this.forgetResumeSession();
      this.stopTicking();
      this.stage = "cancelled";
      this.cancelling = false;
    },

    reset() {
      this.stopTicking();
      this.stage = "idle";
      this.manifest = null;
      this.manifestError = null;
      this.partFiles = [];
      this.partsError = null;
      this.uploadUuid = null;
      this.completedParts = [];
      this.currentPartIndex = null;
      this.currentPartLoadedBytes = 0;
      this.completedBytes = 0;
      this.errorMessage = null;
      this.rucBackupId = null;
      this.startedAt = null;
      this._speedSamples = [];
    },

    // Reintentar tras un error: vuelve a "ready" conservando manifest y
    // partes ya seleccionadas (no hay que re-seleccionar archivos).
    retry() {
      this.stopTicking();
      this.errorMessage = null;
      this.stage = this.partFiles.length === this.totalParts ? "ready" : "manifest_selected";
    },
  };
}
