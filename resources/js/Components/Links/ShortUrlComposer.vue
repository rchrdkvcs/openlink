<script setup lang="ts">
import { Dices } from '@lucide/vue';
import { computed } from 'vue';

import Select from '@/Components/ui/Select.vue';
import { randomSlug } from '@/lib/links';
import type { Domain } from '@/types/payloads';

const props = withDefaults(
  defineProps<{
    domainId: number | string;
    slug: string;
    domains: Pick<Domain, 'id' | 'hostname'>[];
    slugPlaceholder?: string;
    slugLabel?: string;
    disabled?: boolean;
    size?: 'sm' | 'md';
  }>(),
  { slugPlaceholder: 'auto-generated', slugLabel: 'Slug', disabled: false, size: 'md' },
);

const emit = defineEmits<{ 'update:domainId': [value: number | string]; 'update:slug': [value: string] }>();

const domainOptions = computed(() => props.domains.map((domain) => ({ value: domain.id, label: domain.hostname })));
</script>

<template>
  <div
    class="flex items-stretch rounded-lg border border-transparent bg-elevated/70 transition-[border-color,box-shadow] focus-within:border-accent/70 focus-within:ring-2 focus-within:ring-accent/15 hover:bg-elevated"
    :class="size === 'sm' ? 'h-7' : 'h-8'"
  >
    <Select
      :model-value="domainId"
      :options="domainOptions"
      :disabled="disabled"
      :size="size"
      aria-label="Domain"
      class="h-full w-auto max-w-[50%] rounded-l-lg rounded-r-none border-0 border-r bg-transparent text-[13px] font-medium hover:border-r-border focus-visible:ring-0"
      @update:model-value="emit('update:domainId', $event)"
    />
    <span class="grid place-items-center pl-2 pr-1 font-mono text-[13px] text-faint">/</span>
    <input
      :value="slug"
      :disabled="disabled"
      :aria-label="slugLabel"
      class="min-w-0 flex-1 bg-transparent font-mono text-[13px] text-foreground outline-none placeholder:text-faint disabled:opacity-60"
      :placeholder="slugPlaceholder"
      spellcheck="false"
      autocomplete="off"
      @input="emit('update:slug', ($event.target as HTMLInputElement).value)"
    />
    <button
      v-if="!disabled"
      type="button"
      class="grid shrink-0 place-items-center rounded-r-lg text-faint transition-colors hover:text-foreground"
      :class="size === 'sm' ? 'w-7' : 'w-8'"
      title="Random slug"
      aria-label="Random slug"
      @mousedown.prevent
      @click="emit('update:slug', randomSlug())"
    >
      <Dices class="h-3.5 w-3.5" />
    </button>
  </div>
</template>
