export function randomSlug(): string {
    return Math.random().toString(36).replace(/[^a-z0-9]/g, '').slice(0, 7);
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
