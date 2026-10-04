<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'neutral' | 'accent' }>(), { tone: 'neutral' });
</script>

<template>
  <span
    class="grid h-11 w-11 place-items-center rounded-xl border bg-gradient-to-b shadow-[inset_0_1px_0_rgb(255_255_255/0.06)] [&_svg]:h-5 [&_svg]:w-5"
    :class="
      tone === 'accent'
        ? 'border-accent/30 from-accent/15 to-accent/5 text-accent'
        : 'border-border-strong from-elevated to-surface text-muted'
    "
    aria-hidden="true"
  >
    <slot />
  </span>
</template>
