export function randomSlug(length = 7): string {
  const alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
  const cryptoObj = globalThis.crypto;

  if (cryptoObj?.getRandomValues) {
    const bytes = new Uint8Array(length);
    cryptoObj.getRandomValues(bytes);
    return Array.from(bytes, (b) => alphabet[b % alphabet.length]).join('');
  }

  return Math.random()
    .toString(36)
    .replace(/[^a-z0-9]/g, '')
    .padEnd(length, '0')
    .slice(0, length);
}

export function isLikelyUrl(value: string): boolean {
  try {
    const u = new URL(value);
    return (u.protocol === 'http:' || u.protocol === 'https:') && u.hostname.includes('.');
  } catch {
    return false;
  }
}

export function hostOf(value: string): string {
  try {
    return new URL(value.includes('://') ? value : `https://${value}`).host;
  } catch {
    return '';
  }
}

export function originOf(value: string): string | null {
  try {
    const url = new URL(value);

    return url.protocol === 'http:' || url.protocol === 'https:' ? url.origin : null;
  } catch {
    return null;
  }
}
