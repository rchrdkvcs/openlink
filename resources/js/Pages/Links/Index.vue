<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Archive, Folder as FolderIcon, Inbox, Link2, Search, X } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

import type { ComposerLink } from '@/Components/Links/LinkComposer.vue';
import LinkComposer from '@/Components/Links/LinkComposer.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Kbd from '@/Components/ui/Kbd.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Select from '@/Components/ui/Select.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useShell } from '@/lib/shell';

import LinkInspector from './LinkInspector.vue';
import LinkRow from './LinkRow.vue';
import type { LinksPageProps, ShortLink } from './types';
import { useActivationCountdown } from './useActivationCountdown';

const props = defineProps<LinksPageProps>();

const { query, navigation } = useShell();

const filters = ref({ ...props.filters });
let filterTimer: ReturnType<typeof setTimeout> | undefined;

function reload(params: Record<string, string | number>) {
  router.get(route('links.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['links', 'linksPagination', 'filters', 'navigation'],
  });
}

watch(
  () => [filters.value.search, filters.value.status, filters.value.tag],
  () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => reload(cleanParams({ ...filters.value })), 250);
  },
);

watch(
  () => props.filters,
  (value) => {
    filters.value = { ...value };
  },
);

function cleanParams(params: Record<string, string | number | null>): Record<string, string | number> {
  return Object.fromEntries(
    Object.entries(params).filter((entry): entry is [string, string | number] => entry[1] !== '' && entry[1] !== null),
  );
}

function goToPage(page: number) {
  reload(cleanParams({ ...filters.value, page }));
}

const statusOptions = [
  { value: '', label: 'Any status' },
  { value: 'active', label: 'Active' },
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'expired', label: 'Expired' },
  { value: 'disabled', label: 'Disabled' },
  { value: 'archived', label: 'Archived' },
];

const tagOptions = computed(() => [
  { value: '', label: 'Any tag' },
  ...props.tags.map((tag) => ({ value: tag.name, label: `#${tag.name}` })),
]);

const currentFolder = computed(() =>
  props.filters.folder && props.filters.folder !== 'unfiled'
    ? (props.folders.find((folder) => String(folder.id) === props.filters.folder) ?? null)
    : null,
);

const isArchiveView = computed(() => props.filters.status === 'archived');

const title = computed(() => {
  if (isArchiveView.value) return 'Archived';
  if (props.filters.folder === 'unfiled') return 'Unfiled';
  return currentFolder.value?.name ?? 'Links';
});

const titleIcon = computed(() => {
  if (isArchiveView.value) return Archive;
  if (props.filters.folder === 'unfiled') return Inbox;
  return currentFolder.value ? FolderIcon : null;
});

const description = computed(() => {
  const total = props.linksPagination.total;
  const count = `${total.toLocaleString()} link${total === 1 ? '' : 's'}`;
  if (isArchiveView.value) return `${count} · Archived links don’t resolve but keep their slug and analytics.`;
  if (currentFolder.value || props.filters.folder === 'unfiled') return count;
  return `${count} across this workspace`;
});

const hasSearchFilters = computed(() =>
  Boolean(filters.value.search || filters.value.tag || (filters.value.status && !isArchiveView.value)),
);

function clearFilters() {
  filters.value = { ...filters.value, search: '', tag: '', status: isArchiveView.value ? 'archived' : '' };
}

const usableDomains = computed(() => props.domains.filter((domain) => domain.status === 'active'));

const initialSelected = Number(query.value.get('link')) || null;
const selectedId = ref<number | null>(initialSelected);
const selected = computed<ShortLink | null>(() => props.links.find((link) => link.id === selectedId.value) ?? null);

function select(link: ShortLink) {
  selectedId.value = selectedId.value === link.id ? null : link.id;
}

watch(selectedId, (id) => {
  const url = new URL(window.location.href);
  if (id) url.searchParams.set('link', String(id));
  else url.searchParams.delete('link');
  window.history.replaceState(window.history.state, '', url);
});

function onCreated(link: ComposerLink) {
  selectedId.value = null;
  nextTick(() => {
    document.querySelector(`[data-link-id="${link.id}"]`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  });
}

function onEditCreated(link: ComposerLink) {
  selectedId.value = link.id;
}

const { countdownFor } = useActivationCountdown(props);

const searchInput = ref<HTMLInputElement | null>(null);
const composer = ref<InstanceType<typeof LinkComposer> | null>(null);

function moveSelection(delta: number) {
  if (!props.links.length) return;
  const index = props.links.findIndex((link) => link.id === selectedId.value);
  const next = props.links[Math.min(props.links.length - 1, Math.max(0, index === -1 ? 0 : index + delta))];
  selectedId.value = next.id;
  nextTick(() => document.querySelector(`[data-link-id="${next.id}"]`)?.scrollIntoView({ block: 'nearest' }));
}

function onKeydown(event: KeyboardEvent) {
  const target = event.target as HTMLElement | null;
  if (target?.closest('input, textarea, select, [contenteditable="true"], [role="dialog"], [role="menu"]')) return;
  if (event.metaKey || event.ctrlKey || event.altKey) return;

  if (event.key === '/') {
    event.preventDefault();
    searchInput.value?.focus();
  } else if (event.key === 'n' && props.canEditWorkspace) {
    event.preventDefault();
    composer.value?.focus();
  } else if (event.key === 'j' || event.key === 'ArrowDown') {
    event.preventDefault();
    moveSelection(1);
  } else if (event.key === 'k' || event.key === 'ArrowUp') {
    event.preventDefault();
    moveSelection(-1);
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown);
  clearTimeout(filterTimer);
});
</script>

<template>
  <Head :title="title" />

  <AuthenticatedLayout>
    <div class="flex xl:h-full">
      <div
        class="min-w-0 flex-1 px-4 py-6 [scrollbar-gutter:stable] sm:px-6 lg:px-8 lg:py-8 xl:overflow-y-auto xl:overscroll-contain"
      >
        <div class="mx-auto w-full max-w-5xl">
          <PageHeader :title="title" :description="description">
            <template #eyebrow>
              <p v-if="titleIcon" class="mb-1 flex items-center gap-1.5 text-xs font-medium text-faint">
                <component :is="titleIcon" class="h-3.5 w-3.5" />
                {{ isArchiveView ? 'Library' : 'Folder' }}
              </p>
            </template>
          </PageHeader>

          <LinkComposer
            v-if="canEditWorkspace && !isArchiveView"
            ref="composer"
            class="mt-6"
            :domains="domains"
            :folders="folders"
            :folder-id="currentFolder?.id ?? null"
            :preferred-domain-id="currentWorkspace.preferred_domain_id ?? null"
            @created="onCreated"
            @edit="onEditCreated"
          />

          <div class="mt-6 flex flex-wrap items-center gap-2">
            <div class="relative min-w-0 flex-1 sm:max-w-xs">
              <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
              <input
                ref="searchInput"
                v-model="filters.search"
                type="search"
                aria-label="Search links"
                placeholder="Search links"
                class="h-8 w-full rounded-lg border border-transparent bg-elevated/70 pl-8 pr-8 text-[13px] text-foreground outline-none transition-[border-color,box-shadow] placeholder:text-faint hover:bg-elevated focus-visible:border-accent/70 focus-visible:ring-2 focus-visible:ring-accent/15"
                @keydown.escape="
                  filters.search = '';
                  searchInput?.blur();
                "
              />
              <Kbd v-if="!filters.search" class="pointer-events-none absolute right-1.5 top-1/2 -translate-y-1/2"
                >/</Kbd
              >
            </div>
            <Select
              v-if="!isArchiveView"
              v-model="filters.status"
              :options="statusOptions"
              size="sm"
              aria-label="Status"
              class="w-auto min-w-32"
            />
            <Select
              v-if="tags.length"
              v-model="filters.tag"
              :options="tagOptions"
              size="sm"
              aria-label="Tag"
              class="w-auto min-w-28"
            />
            <Button v-if="hasSearchFilters" variant="ghost" size="sm" type="button" @click="clearFilters">
              <X class="h-3.5 w-3.5" /> Clear
            </Button>
            <span v-if="hasSearchFilters" class="ml-auto text-[13px] tabular-nums text-faint">
              {{ linksPagination.total }} result{{ linksPagination.total === 1 ? '' : 's' }}
            </span>
          </div>

          <div v-if="links.length" class="-mx-3 mt-3 space-y-px" role="listbox" aria-label="Links">
            <LinkRow
              v-for="link in links"
              :key="link.id"
              :link="link"
              :selected="link.id === selectedId"
              :folders="folders"
              :can-edit="canEditWorkspace"
              :show-folder="!filters.folder"
              :countdown="countdownFor(link)"
              :compact="selected !== null"
              @select="select(link)"
              @removed="selectedId === link.id && (selectedId = null)"
            />
          </div>

          <div v-else class="mt-4 rounded-xl border border-dashed">
            <EmptyState
              :title="
                hasSearchFilters
                  ? 'No links match'
                  : isArchiveView
                    ? 'Nothing archived'
                    : currentFolder
                      ? 'This folder is empty'
                      : 'No links yet'
              "
              :description="
                hasSearchFilters
                  ? 'Try another search, status or tag.'
                  : isArchiveView
                    ? 'Links you archive appear here.'
                    : currentFolder
                      ? 'Shorten a URL above to add it here, or drag links onto the folder in the sidebar.'
                      : 'Paste a long URL above — your short link is created and copied in one step.'
              "
            >
              <template #icon><Link2 class="h-5 w-5" /></template>
              <template v-if="hasSearchFilters" #action>
                <Button variant="secondary" size="sm" type="button" @click="clearFilters">Clear filters</Button>
              </template>
            </EmptyState>
          </div>

          <nav
            v-if="linksPagination.lastPage > 1"
            class="mt-6 flex items-center justify-between gap-3 text-[13px]"
            aria-label="Pagination"
          >
            <span class="text-faint">Page {{ linksPagination.currentPage }} of {{ linksPagination.lastPage }}</span>
            <div class="flex gap-2">
              <Button
                variant="secondary"
                size="sm"
                type="button"
                :disabled="linksPagination.currentPage <= 1"
                @click="goToPage(linksPagination.currentPage - 1)"
                >Previous</Button
              >
              <Button
                variant="secondary"
                size="sm"
                type="button"
                :disabled="linksPagination.currentPage >= linksPagination.lastPage"
                @click="goToPage(linksPagination.currentPage + 1)"
                >Next</Button
              >
            </div>
          </nav>

          <p
            v-if="links.length && canEditWorkspace && (navigation?.folders.length ?? 0) > 0"
            class="mt-6 hidden text-center text-xs text-faint lg:block"
          >
            Drag a link onto a folder in the sidebar to move it · <Kbd>J</Kbd> <Kbd>K</Kbd> to browse
          </p>
        </div>
      </div>

      <Transition
        enter-active-class="transition duration-300 ease-emphasized-out"
        enter-from-class="translate-x-4 opacity-0"
        leave-active-class="transition duration-150 ease-out"
        leave-to-class="translate-x-4 opacity-0"
      >
        <div
          v-if="selected"
          class="fixed inset-0 z-40 xl:static xl:z-auto xl:h-full xl:w-[440px] xl:shrink-0 xl:border-l 2xl:w-[480px]"
        >
          <LinkInspector
            :link="selected"
            :domains="usableDomains"
            :folders="folders"
            :known-tags="tags"
            :routing-schema="routingSchema"
            :can-edit="canEditWorkspace"
            @close="selectedId = null"
          />
        </div>
      </Transition>
    </div>
  </AuthenticatedLayout>
</template>
