const SECRET_KEY_PATTERN = /(authorization|bearer|cookie|csrf|session|token|password|secret|api[-_]?key|x-xsrf-token|set-cookie)/i;
const MEDIA_PATH_PATTERN = /\.(m3u8?|mp4)(?:$|[?#])/i;
const PLAYER_PATH_PATTERN = /(\/jkplayer\/|\/player\/|\/embed\/|\/c1\.php|\/c4\.php)/i;
const SENSITIVE_QUERY_PATTERN = /(authorization|bearer|csrf|session|token|password|secret|signature|expires|policy|key|cookie)/i;

export function isSecretKey(key) {
  return SECRET_KEY_PATTERN.test(String(key));
}

export function isMediaUrl(value) {
  return MEDIA_PATH_PATTERN.test(String(value));
}

function redactScalarValue(value) {
  if (value === null || value === undefined) {
    return value;
  }

  const text = String(value);

  return text
    .replace(/(<meta[^>]+name=["']csrf-token["'][^>]+content=["'])[^"']+(["'])/gi, '$1[REDACTED]$2')
    .replace(/Bearer\s+[A-Za-z0-9._~+/=-]+/gi, 'Bearer [REDACTED]')
    .replace(/(authorization|csrf|session|token|password|secret|api[-_]?key)=([^&\s]+)/gi, '$1=[REDACTED]')
    .replace(/(Cookie|Set-Cookie):\s*[^\r\n]+/gi, '$1: [REDACTED]');
}

export function redactValue(value) {
  if (value === null || value === undefined) {
    return value;
  }

  const text = String(value).replace(/https?:\/\/[^\s"'<>]+/gi, (match) => {
    if (PLAYER_PATH_PATTERN.test(match) || isMediaUrl(match)) {
      return sanitizeUrl(match);
    }

    return match;
  });

  return redactScalarValue(text);
}

export function redactHeaders(headers = {}) {
  return Object.fromEntries(
    Object.entries(headers).map(([key, value]) => [
      key,
      isSecretKey(key) ? '[REDACTED]' : redactValue(Array.isArray(value) ? value.join('; ') : value),
    ]),
  );
}

export function redactBody(body, maxBytes = 32768) {
  if (body === null || body === undefined || body === '') {
    return null;
  }

  const text = Buffer.isBuffer(body) ? body.toString('utf8') : String(body);
  const truncated = Buffer.byteLength(text, 'utf8') > maxBytes;
  const slice = truncated ? text.slice(0, maxBytes) : text;

  return {
    text: redactValue(slice),
    truncated,
  };
}

export function sanitizeUrl(rawUrl, options = {}) {
  const includeMediaUrls = options.includeMediaUrls === true;

  try {
    const url = new URL(rawUrl);

    for (const key of [...url.searchParams.keys()]) {
      if (SENSITIVE_QUERY_PATTERN.test(key)) {
        url.searchParams.set(key, '[REDACTED]');
      }
    }

    if ((isMediaUrl(url.toString()) || PLAYER_PATH_PATTERN.test(url.pathname)) && !includeMediaUrls) {
      for (const key of [...url.searchParams.keys()]) {
        url.searchParams.set(key, '[REDACTED]');
      }
    }

    if (isMediaUrl(url.toString()) && !includeMediaUrls) {
      return `${url.origin}/[media-url-redacted]`;
    }

    return redactScalarValue(url.toString());
  } catch {
    return redactScalarValue(rawUrl);
  }
}

export function safeJson(value) {
  return JSON.parse(JSON.stringify(value, (_key, item) => {
    if (typeof item === 'string') {
      return redactValue(item);
    }

    return item;
  }));
}
