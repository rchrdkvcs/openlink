import { computed, ref, type Ref } from 'vue';
import type { LinkFilters, LinkGroup, LinksPageProps, ShortLink } from './types';
import { router } from '@inertiajs/vue3';

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
        if (unfiled.length > 0 || props.folders.length === 0) {
            result.push({ key: 'unfiled', folder: null, links: unfiled });
        }

        return hasActiveFilters.value ? result.filter((group) => group.links.length > 0) : result;
    });

    const totalMatching = computed(() => groups.value.reduce((sum, group) => sum + group.links.length, 0));
    const collapseStorageKey = `links.collapsed.${props.currentWorkspace.id}`;

    function readCollapsed(): Set<string> {
        try {
            return new Set(JSON.parse(localStorage.getItem(collapseStorageKey) ?? '[]'));
        } catch {
            return new Set();
        }
    }

    const collapsed = ref<Set<string>>(readCollapsed());

    function toggleCollapse(key: string) {
        const next = new Set(collapsed.value);
        next.has(key) ? next.delete(key) : next.add(key);
        collapsed.value = next;
        localStorage.setItem(collapseStorageKey, JSON.stringify([...next]));
    }

    function isCollapsed(key: string) {
        return collapsed.value.has(key) && !hasActiveFilters.value;
    }

    const dragLinkId = ref<number | null>(null);
    const dropGroupKey = ref<string | null>(null);

    function moveLink(link: ShortLink, folderId: number | null) {
        if ((link.folder?.id ?? null) === folderId) {
            return;
        }

        router.post(route('short-links.move', link.id), { folder_id: folderId }, { preserveScroll: true });
    }

    function onDrop(group: LinkGroup) {
        const link = props.links.find((candidate) => candidate.id === dragLinkId.value);
        dragLinkId.value = null;
        dropGroupKey.value = null;

        if (link) {
            moveLink(link, group.folder?.id ?? null);
        }
    }

    return {
        groups,
        totalMatching,
        hasActiveFilters,
        dragLinkId,
        dropGroupKey,
        toggleCollapse,
        isCollapsed,
        moveLink,
        onDrop,
    };
}
