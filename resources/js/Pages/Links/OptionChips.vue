<script setup lang="ts" generic="K extends string">
defineProps<{
  options: { key: K; label: string; icon: unknown }[];
}>();

const emit = defineEmits<{ add: [key: K] }>();
</script>

<template>
  <div v-if="options.length">
    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-faint">Add options</p>
    <div class="flex flex-wrap gap-1.5">
      <button
        v-for="option in options"
        :key="option.key"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[13px] text-muted transition-colors hover:border-accent/50 hover:bg-accent/10 hover:text-foreground"
        @click="emit('add', option.key)"
      >
        <component :is="option.icon" class="h-3.5 w-3.5" />
        {{ option.label }}
      </button>
    </div>
  </div>
</template>
