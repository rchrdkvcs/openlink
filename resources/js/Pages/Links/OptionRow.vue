<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, provide, useId } from 'vue';

import { fieldContextKey } from '@/lib/select';

const props = defineProps<{
  label: string;
  icon: unknown;
}>();

const id = useId();
provide(
  fieldContextKey,
  computed(() => ({ labelId: id, invalid: false })),
);

const emit = defineEmits<{ remove: [] }>();
</script>

<template>
  <div class="group/opt rounded-3xl border bg-surface p-3">
    <div class="mb-2 flex items-center justify-between">
      <span :id="id" class="flex items-center gap-2 text-[13px] font-medium text-foreground">
        <component :is="icon" class="h-3.5 w-3.5 text-accent" />
        {{ label }}
      </span>
      <button
        type="button"
        class="grid h-6 w-6 place-items-center rounded-md text-faint transition-[color,background-color,opacity] hover:bg-elevated hover:text-foreground focus-visible:opacity-100 group-hover/opt:opacity-100 [@media(hover:hover)]:opacity-0"
        :title="`Remove ${label.toLowerCase()}`"
        @click="emit('remove')"
      >
        <X class="h-3.5 w-3.5" />
      </button>
    </div>
    <slot />
  </div>
</template>
