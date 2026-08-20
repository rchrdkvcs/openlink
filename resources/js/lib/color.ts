function normalizeHex(value: string): string | null {
  const match = value.trim().match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);

  if (!match) return null;

  const hex = match[1];
  return hex.length === 3
    ? hex
        .split('')
        .map((character) => character + character)
        .join('')
    : hex;
}

function luminance(value: string): number | null {
  const hex = normalizeHex(value);
  if (!hex) return null;

  const channels = [0, 2, 4].map((offset) => {
    const channel = Number.parseInt(hex.slice(offset, offset + 2), 16) / 255;
    return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
  });

  return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
}

export function contrastRatio(foreground: string, background: string): number {
  const foregroundLuminance = luminance(foreground);
  const backgroundLuminance = luminance(background);

  if (foregroundLuminance === null || backgroundLuminance === null) return 1;

  const lighter = Math.max(foregroundLuminance, backgroundLuminance);
  const darker = Math.min(foregroundLuminance, backgroundLuminance);
  return (lighter + 0.05) / (darker + 0.05);
}

export function hasAccessibleContrast(foreground: string, background: string): boolean {
  return contrastRatio(foreground, background) >= 4.5;
}

export function suggestedTextColor(background: string): string {
  return contrastRatio('#ffffff', background) >= contrastRatio('#17171c', background) ? '#ffffff' : '#17171c';
}
