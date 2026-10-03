<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Archive, Folder as FolderIcon, Inbox, Link2 } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';

import type { ComposerLink } from '@/Components/Links/composerLink';
import LinkComposer from '@/Components/Links/LinkComposer.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Kbd from '@/Components/ui/Kbd.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useShell } from '@/lib/shell';
import { selectableDomains } from '@/lib/shortLinks/shortLinkForm';
import { useServerFilters } from '@/lib/useServerFilters';
import { useNumericUrlParam } from '@/lib/useUrlParam';
import type { LinksPageProps } from '@/Pages/Links/types';
import type { ShortLink } from '@/types/shortLinks';

import LinkInspector from './LinkInspector.vue';
import LinkRow from './list/LinkRow.vue';
import LinksPagination from './list/LinksPagination.vue';
import LinksToolbar from './list/LinksToolbar.vue';
import { clearedSearch, emptyCopy, hasSearchFilters, linksView } from './list/linksView';
import { useActivationCountdownWithReload } from './list/useActivationCountdownWithReload';
import { useLinkListKeyboard } from './list/useLinkListKeyboard';

const props = defineProps<LinksPageProps>();

const { navigation, canEdit, workspace } = useShell();

const { filters, goToPage, update } = useServerFilters({
  url: () => route('links.index'),
  source: () => props.filters,
  only: ['links', 'linksPagination', 'filters', 'navigation'],
  debounced: ['search', 'status', 'tag'],
});

const view = computed(() => linksView(props.filters, props.folders, props.linksPagination.total));
const scopeIcons = {
  archive: Archive,
  unfiled: Inbox,
  folder: FolderIcon,
  all: null,
};
const isArchiveView = computed(() => view.value.scope === 'archive');
const searching = computed(() => hasSearchFilters(filters.value, view.value.scope));
const empty = computed(() => emptyCopy(view.value.scope, searching.value));
const usableDomains = computed(() => selectableDomains(props.domains));

const selectedId = useNumericUrlParam('link');
const selected = computed<ShortLink | null>(() => props.links.find((link) => link.id === selectedId.value) ?? null);

const { countdownFor } = useActivationCountdownWithReload(() => props.links);

const toolbar = ref<InstanceType<typeof LinksToolbar> | null>(null);
const composer = ref<InstanceType<typeof LinkComposer> | null>(null);

function scrollToLink(id: number, behavior?: ScrollBehavior) {
  nextTick(() => document.querySelector(`[data-link-id="${id}"]`)?.scrollIntoView({ block: 'nearest', behavior }));
}

function select(link: ShortLink) {
  selectedId.value = selectedId.value === link.id ? null : link.id;
}

function onCreated(link: ComposerLink) {
  selectedId.value = null;
  scrollToLink(link.id, 'smooth');
}

function moveSelection(delta: number) {
  if (!props.links.length) return;
  const index = props.links.findIndex((link) => link.id === selectedId.value);
  const next = props.links[Math.min(props.links.length - 1, Math.max(0, index === -1 ? 0 : index + delta))];
  selectedId.value = next.id;
  scrollToLink(next.id);
}

useLinkListKeyboard({
  focusSearch: () => toolbar.value?.focusSearch(),
  compose: canEdit.value ? () => composer.value?.focus() : undefined,
  move: moveSelection,
});
</script>

<template>
  <Head :title="view.title" />

  <AuthenticatedLayout>
    <div class="flex xl:h-full">
      <div
        class="min-w-0 flex-1 px-4 py-6 [scrollbar-gutter:stable] sm:px-6 lg:px-8 lg:py-8 xl:overflow-y-auto xl:overscroll-contain"
      >
        <div class="mx-auto w-full max-w-5xl">
          <PageHeader :title="view.title" :description="view.description">
            <template #eyebrow>
              <p v-if="view.eyebrow" class="mb-1 flex items-center gap-1.5 text-xs font-medium text-faint">
                <component :is="scopeIcons[view.scope]" class="h-3.5 w-3.5" />
                {{ view.eyebrow }}
              </p>
            </template>
          </PageHeader>

          <LinkComposer
            v-if="canEdit && !isArchiveView"
            ref="composer"
            class="mt-6"
            :domains="domains"
            :folders="folders"
            :folder-id="view.folder?.id ?? null"
            :preferred-domain-id="workspace?.preferred_domain_id ?? null"
            @created="onCreated"
            @edit="selectedId = $event.id"
          />

          <LinksToolbar
            ref="toolbar"
            v-model:filters="filters"
            :tags="tags"
            :show-status="!isArchiveView"
            :searching="searching"
            :total="linksPagination.total"
            @clear="update(clearedSearch(view.scope))"
          />

          <div v-if="links.length" class="-mx-3 mt-3 space-y-px" role="listbox" aria-label="Links">
            <LinkRow
              v-for="link in links"
              :key="link.id"
              :link="link"
              :selected="link.id === selectedId"
              :folders="folders"
              :show-folder="!filters.folder"
              :countdown="countdownFor(link)"
              :compact="selected !== null"
              @select="select(link)"
              @removed="selectedId === link.id && (selectedId = null)"
            />
          </div>

          <div v-else class="mt-4 rounded-xl border border-dashed">
            <EmptyState :title="empty.title" :description="empty.description">
              <template #icon><Link2 class="h-5 w-5" /></template>
              <template v-if="searching" #action>
                <Button variant="secondary" size="sm" type="button" @click="update(clearedSearch(view.scope))"
                  >Clear filters</Button
                >
              </template>
            </EmptyState>
          </div>

          <LinksPagination :pagination="linksPagination" @page="goToPage" />

          <p
            v-if="links.length && canEdit && (navigation?.folders.length ?? 0) > 0"
            class="mt-6 hidden text-center text-xs text-faint lg:block"
          >
            Drag a link onto a folder in the sidebar to move it ·
            <Kbd>J</Kbd> <Kbd>K</Kbd> to browse
          </p>
        </div>
      </div>

      <Transition
        enter-active-class="transition-[width,translate,opacity] duration-300 ease-drawer motion-reduce:transition-none"
        enter-from-class="translate-x-4 opacity-0 xl:w-0! xl:translate-x-0"
        leave-active-class="transition-[width,translate,opacity] duration-200 ease-drawer motion-reduce:transition-none"
        leave-to-class="translate-x-4 opacity-0 xl:w-0! xl:translate-x-0"
      >
        <div
          v-if="selected"
          class="fixed inset-0 z-40 xl:static xl:z-auto xl:h-full xl:w-[440px] xl:shrink-0 xl:overflow-hidden xl:border-l 2xl:w-[480px]"
        >
          <div class="h-full xl:w-[440px] 2xl:w-[480px]">
            <LinkInspector
              :link="selected"
              :domains="usableDomains"
              :folders="folders"
              :known-tags="tags"
              :routing-schema="routingSchema"
              @close="selectedId = null"
            />
          </div>
        </div>
      </Transition>
    </div>
  </AuthenticatedLayout>
</template>
