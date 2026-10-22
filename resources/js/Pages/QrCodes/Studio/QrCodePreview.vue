<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  name: string;
  src: string;
  backgroundColor: string;
  transparent: boolean;
  pendingLogo: boolean;
}>();

const backdrop = computed(() =>
  props.transparent
    ? {
        backgroundImage: 'repeating-conic-gradient(rgba(128,128,128,0.18) 0% 25%, transparent 0% 50%)',
        backgroundSize: '20px 20px',
      }
    : { backgroundColor: props.backgroundColor },
);
</script>

<template>
  <div class="w-full max-w-[380px]">
    <div class="rounded-2xl border bg-surface p-2">
      <div class="grid place-items-center rounded-xl p-2" :style="backdrop">
        <img :src="src" :alt="`${name} QR code preview`" class="aspect-square w-full" />
      </div>
    </div>
    <p v-if="pendingLogo" class="pt-2.5 text-center text-xs text-faint">Save to see the new logo.</p>
  </div>
</template>
