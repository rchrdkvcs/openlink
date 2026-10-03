import { ref } from 'vue';

import { needsMove } from '@/Components/Shell/folders';
import { draggedLink } from '@/lib/dragLink';
import { moveLinkToFolder } from '@/lib/linkActions';
import type { NavigationFolder } from '@/types';

export function useLinkDrop() {
  const dropKey = ref<string | null>(null);

  function onDragOver(key: string, event: DragEvent) {
    if (!draggedLink.value) return;
    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
    dropKey.value = key;
  }

  function onDragLeave() {
    dropKey.value = null;
  }

  function onDrop(folder: NavigationFolder | null) {
    const link = draggedLink.value;
    dropKey.value = null;
    draggedLink.value = null;
    if (!link || !needsMove(link, folder?.id ?? null)) return;
    moveLinkToFolder(link, folder?.id ?? null, folder?.name);
  }

  return { dropKey, onDragOver, onDragLeave, onDrop };
}
