<script setup lang="ts">
import { Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';

import Input from '@/Components/ui/Input.vue';
import { displayUrl } from '@/lib/links';

import type { ShortLinkOption } from '../QrCodes/types';
import { useShortLinkSearch } from '../QrCodes/useShortLinkSearch';

const props = defineProps<{
  initial: ShortLinkOption[];
  selectedIds: number[];
  disabled?: boolean;
}>();

const emit = defineEmits<{ add: [link: ShortLinkOption] }>();

async function fetchLinks(term: string): Promise<ShortLinkOption[] | null> {
  const response = await fetch(route('link-pages.short-links', { search: term }), {
    headers: { Accept: 'application/json' },
  });
  if (!response.ok) return null;

  return ((await response.json()) as { data: ShortLinkOption[] }).data;
}

const { search, links } = useShortLinkSearch(props.initial, ref(''), fetchLinks);

const available = computed(() => links.value.filter((link) => !props.selectedIds.includes(link.id)));
</script>

<template>
  <div class="grid gap-2">
    <div class="relative">
      <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
      <Input
        v-model="search"
        type="search"
        class="pl-9"
        placeholder="Search short links to add"
        aria-label="Search short links to add"
      />
    </div>
    <ul class="max-h-56 overflow-y-auto rounded-xl border bg-background/30 p-1" aria-label="Short links to add">
      <li v-for="link in available" :key="link.id">
        <button
          type="button"
          :disabled="disabled"
          class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left transition-colors duration-100 hover:bg-elevated/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 disabled:opacity-50"
          :aria-label="`Add ${displayUrl(link.short_url)}`"
          @click="emit('add', link)"
        >
          <span class="min-w-0 flex-1">
            <span class="block truncate text-[13px] font-medium text-foreground">{{ displayUrl(link.short_url) }}</span>
            <span class="block truncate text-xs text-faint">{{ displayUrl(link.destination_url) }}</span>
          </span>
          <Plus class="h-4 w-4 shrink-0 text-faint" aria-hidden="true" />
        </button>
      </li>
      <li v-if="available.length === 0" class="px-2.5 py-8 text-center text-[13px] text-faint">
        {{ search ? 'No short links match.' : 'No more short links to add.' }}
      </li>
    </ul>
  </div>
</template>
