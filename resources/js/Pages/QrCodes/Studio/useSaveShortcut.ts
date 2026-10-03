import { onMounted, onUnmounted } from 'vue';

export function isSaveShortcut(event: Pick<KeyboardEvent, 'metaKey' | 'ctrlKey' | 'key'>): boolean {
  return (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's';
}

export function useSaveShortcut(onSave: () => void) {
  function onKeydown(event: KeyboardEvent) {
    if (!isSaveShortcut(event)) return;
    event.preventDefault();
    onSave();
  }

  onMounted(() => document.addEventListener('keydown', onKeydown));
  onUnmounted(() => document.removeEventListener('keydown', onKeydown));
}
