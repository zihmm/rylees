// Base origin that serves the public Release History (per environment), including
// the scheme — e.g. "https://rylees.ai" in production or "http://rylees.test" in
// local dev. The scheme drives the link href but is never shown in the console.
const RAW_BASE = import.meta.env.VITE_HISTORY_BASE_DOMAIN || 'https://rylees.ai';

// Split the configured base into its scheme and host, e.g.
// "http://rylees.test" → { protocol: 'http://', host: 'rylees.test' }.
// Defaults to https:// when the env var omits a scheme.
function parseBase() {
  const match = /^(\w+:\/\/)?(.+)$/.exec(RAW_BASE);
  return { protocol: match[1] || 'https://', host: match[2] };
}

// Display text for the link — host only, no scheme:
// "acme-ltd.rylees.ai/member-portal". Empty when slug/key are missing.
export function releaseHistoryDomain(slug, key) {
  if (!slug || !key) return '';
  return `${slug}.${parseBase().host}/${key}`;
}

// Full href including the configured scheme, for use as a link target:
// "https://acme-ltd.rylees.ai/member-portal".
export function releaseHistoryUrl(slug, key) {
  if (!slug || !key) return '';
  return `${parseBase().protocol}${releaseHistoryDomain(slug, key)}`;
}
