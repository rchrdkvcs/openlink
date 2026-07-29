<script setup lang="ts">
withDefaults(
  defineProps<{
    name?: string | null;
    src?: string | null;
    size?: 'sm' | 'md' | 'lg';
  }>(),
  {
    size: 'md',
  },
);

const sizeClass = {
  sm: 'h-7 w-7 text-xs',
  md: 'h-8 w-8 text-xs',
  lg: 'h-14 w-14 text-lg',
};

function initial(name?: string | null) {
  return String(name ?? 'O')
    .slice(0, 1)
    .toUpperCase();
}
</script>

<template>
  <span
    class="grid shrink-0 place-items-center overflow-hidden rounded-full border bg-elevated font-semibold text-foreground"
    :class="sizeClass[size]"
  >
    <img
      v-if="src"
      :src="src"
      :alt="name ?? 'User avatar'"
      class="h-full w-full object-cover"
      referrerpolicy="no-referrer"
    />
    <span v-else>{{ initial(name) }}</span>
  </span>
</template>
