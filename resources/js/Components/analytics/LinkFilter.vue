<script setup lang="ts">
import { Check, ChevronDown, Link2, Search, X } from '@lucide/vue';
import { PopoverAnchor, PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'radix-vue';
import { computed, nextTick, ref, useId, watch } from 'vue';

import Favicon from '@/Components/Links/Favicon.vue';
import { shortLabel, type LinkOption } from '@/lib/analytics';

const props = withDefaults(
  defineProps<{
    links: LinkOption[];
    topLinkIds?: number[];
  }>(),
  { topLinkIds: () => [] },
);

const model = defineModel<string>({ required: true });

const RESULT_LIMIT = 50;
const RECENT_LIMIT = 8;
const TOP_LIMIT = 5;

type Entry = { key: string; link: LinkOption | null };
type Group = { label: string | null; entries: Entry[] };

const open = ref(false);
const search = ref('');
const activeIndex = ref(0);
const input = ref<HTMLInputElement | null>(null);
const list = ref<HTMLElement | null>(null);
const listId = useId();

const selected = computed(() => props.links.find((link) => String(link.id) === model.value) ?? null);

const byId = computed(() => new Map(props.links.map((link) => [link.id, link])));

function score(link: LinkOption, term: string): number {
  const slug = link.slug.toLowerCase();
  const short = shortLabel(link).toLowerCase();
  const destination = (link.destination_url ?? '').toLowerCase();
  if (slug === term) return 0;
  if (slug.startsWith(term)) return 1;
  if (short.includes(term)) return 2;
  if (destination.includes(term)) return 3;
  return -1;
}

const groups = computed<Group[]>(() => {
  const term = search.value.trim().toLowerCase();
  const all: Entry = { key: 'all', link: null };

  if (term === '') {
    const top = props.topLinkIds
      .map((id) => byId.value.get(id))
      .filter((link): link is LinkOption => Boolean(link))
      .slice(0, TOP_LIMIT);
    const topIds = new Set(top.map((link) => link.id));
    const recent = props.links.filter((link) => !topIds.has(link.id)).slice(0, RECENT_LIMIT);

    return [
      { label: null, entries: [all] },
      { label: 'Top in this period', entries: top.map((link) => ({ key: String(link.id), link })) },
      { label: 'Recent', entries: recent.map((link) => ({ key: String(link.id), link })) },
    ].filter((group) => group.entries.length > 0);
  }

  const matches = props.links
    .map((link) => ({ link, rank: score(link, term) }))
    .filter((match) => match.rank >= 0)
    .toSorted((a, b) => a.rank - b.rank || a.link.slug.localeCompare(b.link.slug))
    .slice(0, RESULT_LIMIT)
    .map(({ link }) => ({ key: String(link.id), link }));

  return matches.length > 0 ? [{ label: null, entries: matches }] : [];
});

const entries = computed(() => groups.value.flatMap((group) => group.entries));

const offsets = computed(() => {
  let offset = 0;
  return groups.value.map((group) => {
    const start = offset;
    offset += group.entries.length;
    return start;
  });
});

const activeId = computed(() => {
  const entry = entries.value[activeIndex.value];
  return entry ? `${listId}-${entry.key}` : undefined;
});

watch(search, () => (activeIndex.value = 0));

watch(open, (value) => {
  if (!value) return;
  search.value = '';
  const index = entries.value.findIndex((entry) => (entry.link ? String(entry.link.id) : '') === model.value);
  activeIndex.value = Math.max(index, 0);
  nextTick(() => {
    input.value?.focus();
    scrollActiveIntoView();
  });
});

function scrollActiveIntoView() {
  if (!activeId.value) return;
  list.value?.querySelector(`[id="${activeId.value}"]`)?.scrollIntoView({ block: 'nearest' });
}

function move(delta: number) {
  const count = entries.value.length;
  if (count === 0) return;
  activeIndex.value = (activeIndex.value + delta + count) % count;
  nextTick(scrollActiveIntoView);
}

function choose(entry: Entry | undefined) {
  if (!entry) return;
  model.value = entry.link ? String(entry.link.id) : '';
  open.value = false;
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    move(1);
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    move(-1);
  } else if (event.key === 'Home' && search.value === '') {
    event.preventDefault();
    activeIndex.value = 0;
    nextTick(scrollActiveIntoView);
  } else if (event.key === 'End' && search.value === '') {
    event.preventDefault();
    activeIndex.value = Math.max(entries.value.length - 1, 0);
    nextTick(scrollActiveIntoView);
  } else if (event.key === 'Enter') {
    event.preventDefault();
    choose(entries.value[activeIndex.value]);
  }
}

function clear() {
  model.value = '';
}
</script>

<template>
  <PopoverRoot v-model:open="open">
    <PopoverAnchor as-child>
      <div
        class="inline-flex h-7 max-w-full items-center rounded-lg text-[13px] transition-colors duration-150"
        :class="selected ? 'bg-elevated' : 'bg-elevated/70 hover:bg-elevated'"
      >
        <PopoverTrigger as-child>
          <button
            type="button"
            class="inline-flex h-full min-w-0 items-center gap-1.5 rounded-lg px-2.5 outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
            :class="selected ? 'pe-1.5' : ''"
            :aria-label="selected ? `Link filter: ${shortLabel(selected)}` : 'Filter by link'"
          >
            <Favicon v-if="selected && selected.destination_url" :url="selected.destination_url" size="sm" />
            <Link2 v-else class="h-3.5 w-3.5 shrink-0 text-muted" />
            <span v-if="selected" class="min-w-0 truncate font-medium text-foreground">{{ shortLabel(selected) }}</span>
            <span v-else class="text-muted">All links</span>
            <ChevronDown v-if="!selected" class="h-3.5 w-3.5 shrink-0 text-faint" />
          </button>
        </PopoverTrigger>
        <button
          v-if="selected"
          type="button"
          class="me-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-md text-faint outline-none transition-colors hover:bg-border-strong hover:text-foreground focus-visible:ring-2 focus-visible:ring-accent/40"
          aria-label="Clear link filter"
          @click="clear"
        >
          <X class="h-3.5 w-3.5" />
        </button>
      </div>
    </PopoverAnchor>

    <PopoverPortal>
      <PopoverContent
        align="start"
        :side-offset="6"
        :collision-padding="12"
        class="z-[90] w-[22rem] max-w-[calc(100vw-2rem)] origin-[var(--radix-popover-content-transform-origin)] overflow-hidden rounded-xl bg-overlay shadow-popover outline-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-[0.97] data-[state=open]:zoom-in-[0.97]"
        @open-auto-focus.prevent
      >
        <div class="flex items-center gap-2 border-b px-3">
          <Search class="h-3.5 w-3.5 shrink-0 text-faint" />
          <input
            ref="input"
            v-model="search"
            type="text"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="true"
            :aria-controls="listId"
            :aria-activedescendant="activeId"
            aria-label="Search links"
            placeholder="Search by slug or destination"
            autocomplete="off"
            spellcheck="false"
            class="h-10 min-w-0 flex-1 bg-transparent text-[13px] text-foreground outline-none placeholder:text-faint"
            @keydown="onKeydown"
          />
        </div>

        <div :id="listId" ref="list" role="listbox" aria-label="Links" class="max-h-80 overflow-y-auto p-1">
          <div v-for="(group, groupIndex) in groups" :key="group.label ?? `group-${groupIndex}`" role="group">
            <p v-if="group.label" class="px-2.5 pb-1 pt-2 text-xs font-medium text-faint" role="presentation">
              {{ group.label }}
            </p>
            <div
              v-for="(entry, entryIndex) in group.entries"
              :id="`${listId}-${entry.key}`"
              :key="entry.key"
              role="option"
              :aria-selected="(entry.link ? String(entry.link.id) : '') === model"
              class="flex cursor-default select-none items-center gap-2.5 rounded-lg px-2.5 text-[13px]"
              :class="[
                entry.link?.destination_url ? 'py-1.5' : 'h-8',
                offsets[groupIndex] + entryIndex === activeIndex ? 'bg-elevated' : '',
              ]"
              @pointermove="activeIndex = offsets[groupIndex] + entryIndex"
              @pointerdown.prevent
              @click="choose(entry)"
            >
              <template v-if="entry.link">
                <Favicon :url="entry.link.destination_url ?? ''" size="sm" />
                <span class="min-w-0 flex-1">
                  <span class="block truncate font-medium text-foreground">{{ shortLabel(entry.link) }}</span>
                  <span v-if="entry.link.destination_url" class="block truncate text-xs text-faint">{{
                    entry.link.destination_url
                  }}</span>
                </span>
              </template>
              <template v-else>
                <Link2 class="h-3.5 w-3.5 shrink-0 text-muted" />
                <span class="min-w-0 flex-1 truncate text-foreground">All links</span>
              </template>
              <Check
                v-if="(entry.link ? String(entry.link.id) : '') === model"
                class="h-3.5 w-3.5 shrink-0 text-accent"
              />
            </div>
          </div>

          <p v-if="entries.length === 0" class="px-2.5 py-6 text-center text-[13px] text-faint">
            No links match “{{ search.trim() }}”
          </p>
        </div>

        <p v-if="search.trim() !== '' && entries.length === RESULT_LIMIT" class="border-t px-3 py-2 text-xs text-faint">
          Showing the first {{ RESULT_LIMIT }} matches. Refine your search to narrow it down.
        </p>
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>
