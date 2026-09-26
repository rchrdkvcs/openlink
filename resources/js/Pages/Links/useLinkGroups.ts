import { router } from '@inertiajs/vue3';
import { computed, ref, watch, type Ref } from 'vue';

import type { LinkFilters, LinkGroup, LinksPageProps, ShortLink } from './types';

function matchesFilters(link: ShortLink, filters: LinkFilters) {
  if (!filters.status && link.status === 'archived') {
    return false;
  }

  const haystack = `${link.short_url} ${link.destination_url} ${link.slug}`.toLowerCase();
  const matchesSearch = !filters.search || haystack.includes(filters.search.toLowerCase());
  const matchesStatus = !filters.status || link.status === filters.status;
  const matchesTag = !filters.tag || link.tags.some((tag) => tag.name === filters.tag);

  return matchesSearch && matchesStatus && matchesTag;
}

export function useLinkGroups(props: LinksPageProps, filters: Ref<LinkFilters>) {
  const hasActiveFilters = computed(() => Boolean(filters.value.search || filters.value.status || filters.value.tag));

  const groups = computed<LinkGroup[]>(() => {
    const result: LinkGroup[] = props.folders.map((folder) => ({
      key: String(folder.id),
      folder,
      links: props.links.filter((link) => link.folder?.id === folder.id && matchesFilters(link, filters.value)),
    }));

    const unfiled = props.links.filter((link) => !link.folder && matchesFilters(link, filters.value));
    result.unshift({ key: 'unfiled', folder: null, links: unfiled });
    return result;
  });

  const totalMatching = computed(() => groups.value.reduce((sum, group) => sum + group.links.length, 0));
  const selectedFolderKey = ref('all');
  watch(
    () => props.currentWorkspace.id,
    (id) => {
      try {
        selectedFolderKey.value = localStorage.getItem(`links.folder.${id}`) ?? 'all';
      } catch {
        selectedFolderKey.value = 'all';
      }
    },
    { immediate: true },
  );
  watch(
    [selectedFolderKey, () => props.folders],
    () => {
      if (
        !['all', 'unfiled'].includes(selectedFolderKey.value) &&
        !props.folders.some((folder) => String(folder.id) === selectedFolderKey.value)
      ) {
        selectedFolderKey.value = 'all';
      }
      try {
        localStorage.setItem(`links.folder.${props.currentWorkspace.id}`, selectedFolderKey.value);
      } catch {
        /* Folder navigation still works when browser storage is unavailable. */
      }
    },
    { immediate: true },
  );
  const selectedGroup = computed(() => groups.value.find((group) => group.key === selectedFolderKey.value));
  const visibleLinks = computed(() =>
    selectedFolderKey.value === 'all'
      ? props.links.filter((link) => matchesFilters(link, filters.value))
      : (selectedGroup.value?.links ?? []),
  );

  const dragLinkId = ref<number | null>(null);
  const dropGroupKey = ref<string | null>(null);

  function moveLink(link: ShortLink, folderId: number | null) {
    if (!props.canEditWorkspace || (link.folder?.id ?? null) === folderId) {
      return;
    }

    router.post(route('short-links.move', link.id), { folder_id: folderId }, { preserveScroll: true });
  }

  function onDrop(group: LinkGroup) {
    const link = props.links.find((candidate) => candidate.id === dragLinkId.value);
    dragLinkId.value = null;
    dropGroupKey.value = null;

    if (link && props.canEditWorkspace) {
      moveLink(link, group.folder?.id ?? null);
    }
  }

  return {
    groups,
    totalMatching,
    hasActiveFilters,
    dragLinkId,
    dropGroupKey,
    selectedFolderKey,
    selectedGroup,
    visibleLinks,
    moveLink,
    onDrop,
  };
}
