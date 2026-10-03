import { router } from '@inertiajs/vue3';
import { nextTick, ref, type Ref } from 'vue';

import { folderDeleteMessage, folderRename } from '@/Components/Shell/folders';
import { confirmAction } from '@/lib/confirm';
import { toast } from '@/lib/toast';
import type { NavigationFolder } from '@/types';

export function useFolderEditing(activeFolder: Ref<string | null>) {
  const creating = ref(false);
  const newName = ref('');
  const newInput = ref<HTMLInputElement | null>(null);
  const renamingId = ref<number | null>(null);
  const renameValue = ref('');
  const renameInput = ref<HTMLInputElement[]>([]);

  function startCreate() {
    creating.value = true;
    newName.value = '';
    nextTick(() => newInput.value?.focus());
  }

  function commitCreate() {
    const name = newName.value.trim();
    creating.value = false;
    if (!name) return;

    router.post(
      route('folders.store'),
      { name },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast({ title: `Folder “${name}” created`, tone: 'success' }),
        onError: (errors) => toast({ title: errors.name ?? 'Could not create folder', tone: 'danger' }),
      },
    );
  }

  function startRename(folder: NavigationFolder) {
    renamingId.value = folder.id;
    renameValue.value = folder.name;
    nextTick(() => {
      renameInput.value[0]?.focus();
      renameInput.value[0]?.select();
    });
  }

  function commitRename(folder: NavigationFolder) {
    if (renamingId.value !== folder.id) return;
    const name = folderRename(folder.name, renameValue.value);
    renamingId.value = null;
    if (!name) return;

    router.patch(route('folders.update', folder.id), { name }, { preserveScroll: true, preserveState: true });
  }

  async function remove(folder: NavigationFolder) {
    const confirmed = await confirmAction({
      title: `Delete “${folder.name}”?`,
      message: folderDeleteMessage(folder.links_count),
      confirmLabel: 'Delete folder',
      destructive: true,
    });
    if (!confirmed) return;

    router.delete(route('folders.destroy', folder.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast({ title: 'Folder deleted' });
        if (activeFolder.value === String(folder.id)) router.visit(route('links.index'));
      },
    });
  }

  return {
    creating,
    newName,
    newInput,
    renamingId,
    renameValue,
    renameInput,
    startCreate,
    commitCreate,
    startRename,
    commitRename,
    remove,
  };
}
