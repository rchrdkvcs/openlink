<script setup lang="ts">
import { Check, Search } from '@lucide/vue';

import Input from '@/Components/ui/Input.vue';
import { displayUrl } from '@/lib/links';

import type { ShortLinkOption } from './types';

defineProps<{
  links: ShortLinkOption[];
  error?: string;
}>();

const selected = defineModel<string | number>({ required: true });
const search = defineModel<string>('search', { required: true });
</script>

<template>
  <div class="grid gap-2">
    <div class="relative">
      <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
      <Input
        v-model="search"
        type="search"
        class="pl-9"
        placeholder="Search short links"
        aria-label="Search short links"
      />
    </div>
    <div
      role="radiogroup"
      aria-label="Short link"
      class="max-h-56 overflow-y-auto rounded-xl border bg-background/30 p-1"
      :class="error ? 'border-danger/60' : ''"
    >
      <button
        v-for="link in links"
        :key="link.id"
        type="button"
        role="radio"
        :aria-checked="Number(selected) === link.id"
        class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left transition-colors duration-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
        :class="Number(selected) === link.id ? 'bg-elevated' : 'hover:bg-elevated/50'"
        @click="selected = link.id"
      >
        <span class="min-w-0 flex-1">
          <span class="block truncate text-[13px] font-medium text-foreground">{{ displayUrl(link.short_url) }}</span>
          <span class="block truncate text-xs text-faint">{{ displayUrl(link.destination_url) }}</span>
        </span>
        <Check v-if="Number(selected) === link.id" class="h-4 w-4 shrink-0 text-accent" />
      </button>
      <p v-if="links.length === 0" class="px-2.5 py-8 text-center text-[13px] text-faint">
        {{ search ? 'No short links match.' : 'No short links yet.' }}
      </p>
    </div>
    <p v-if="error" class="text-xs text-danger">{{ error }}</p>
  </div>
</template>
