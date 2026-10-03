import { onMounted, onUnmounted } from 'vue';

import { isLikelyUrl, normalizeUrl } from '@/lib/links';

const EDITABLE_TARGETS = 'input, textarea, select, [contenteditable="true"], [role="dialog"]';

export function useUrlPasteCapture(enabled: () => boolean, onUrl: (url: string) => void) {
  function onPaste(event: ClipboardEvent) {
    if (!enabled()) return;
    if ((event.target as HTMLElement | null)?.closest(EDITABLE_TARGETS)) return;

    const text = event.clipboardData?.getData('text') ?? '';
    if (!isLikelyUrl(normalizeUrl(text))) return;

    event.preventDefault();
    onUrl(text.trim());
  }

  onMounted(() => document.addEventListener('paste', onPaste));
  onUnmounted(() => document.removeEventListener('paste', onPaste));
}
