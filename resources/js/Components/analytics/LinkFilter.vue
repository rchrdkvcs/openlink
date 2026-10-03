<script setup lang="ts">
import { Check, Link2, Search } from '@lucide/vue';
import { PopoverContent, PopoverPortal, PopoverRoot } from 'radix-vue';
import { computed, nextTick, ref, useId, watch } from 'vue';

import {
  RESULT_LIMIT,
  entryValue,
  groupOffsets,
  linkGroups,
  listboxIndex,
  type LinkEntry,
} from '@/Components/analytics/linkFilterGroups';
import LinkFilterTrigger from '@/Components/analytics/LinkFilterTrigger.vue';
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

const open = ref(false);
const search = ref('');
const activeIndex = ref(0);
const input = ref<HTMLInputElement | null>(null);
const list = ref<HTMLElement | null>(null);
const listId = useId();

const selected = computed(() => props.links.find((link) => String(link.id) === model.value) ?? null);
const groups = computed(() => linkGroups(props.links, props.topLinkIds, search.value));
const entries = computed(() => groups.value.flatMap((group) => group.entries));
const offsets = computed(() => groupOffsets(groups.value));

const activeId = computed(() => {
  const entry = entries.value[activeIndex.value];
  return entry ? `${listId}-${entry.key}` : undefined;
});

watch(search, () => (activeIndex.value = 0));

watch(open, (value) => {
  if (!value) return;
  search.value = '';
  const index = entries.value.findIndex((entry) => entryValue(entry) === model.value);
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

function choose(entry: LinkEntry | undefined) {
  if (!entry) return;
  model.value = entryValue(entry);
  open.value = false;
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter') {
    event.preventDefault();
    choose(entries.value[activeIndex.value]);
    return;
  }
  const next = listboxIndex(event.key, activeIndex.value, entries.value.length, search.value !== '');
  if (next === undefined) return;
  event.preventDefault();
  activeIndex.value = next;
  nextTick(scrollActiveIntoView);
}
</script>

<template>
  <PopoverRoot v-model:open="open">
    <LinkFilterTrigger :selected="selected" @clear="model = ''" />

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
              :aria-selected="entryValue(entry) === model"
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
              <Check v-if="entryValue(entry) === model" class="h-3.5 w-3.5 shrink-0 text-accent" />
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
