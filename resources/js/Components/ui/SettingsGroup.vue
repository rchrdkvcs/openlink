<script setup lang="ts">
defineProps<{
  title?: string;
  description?: string;
}>();
</script>

<template>
  <section>
    <div v-if="title || $slots.actions" class="mb-2.5 flex items-end justify-between gap-4 px-1">
      <div class="min-w-0">
        <h2 v-if="title" class="text-[13px] font-semibold text-foreground">{{ title }}</h2>
        <p v-if="description" class="mt-0.5 text-[13px] text-muted">{{ description }}</p>
      </div>
      <slot name="actions" />
    </div>
    <div class="divide-y divide-border overflow-hidden rounded-xl border bg-surface">
      <slot />
    </div>
    <p v-if="$slots.footer" class="mt-2 px-1 text-xs leading-relaxed text-faint"><slot name="footer" /></p>
  </section>
</template>
