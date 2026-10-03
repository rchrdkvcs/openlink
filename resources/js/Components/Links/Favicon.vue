<script setup lang="ts">
import { Globe } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import { faviconFor, isLikelyUrl } from '@/lib/links';

const props = withDefaults(defineProps<{ url: string; size?: 'sm' | 'md' | 'lg' }>(), { size: 'md' });

const failed = ref(false);
const loaded = ref(false);
watch(
  () => props.url,
  () => {
    failed.value = false;
    loaded.value = false;
  },
);

const src = computed(() => (isLikelyUrl(props.url) && !failed.value ? faviconFor(props.url) : null));

const box = computed(
  () =>
    ({
      sm: 'h-4 w-4 rounded-[4px]',
      md: 'h-5 w-5 rounded-[5px]',
      lg: 'h-8 w-8 rounded-lg',
    })[props.size],
);
</script>

<template>
  <span
    class="grid shrink-0 place-items-center overflow-hidden"
    :class="[box, loaded ? '' : 'bg-elevated outline outline-1 -outline-offset-1 outline-white/10']"
  >
    <img
      v-if="src"
      :src="src"
      alt=""
      class="h-full w-full object-contain"
      loading="lazy"
      @load="loaded = true"
      @error="failed = true"
    />
    <Globe v-else class="h-[60%] w-[60%] text-faint" />
  </span>
</template>
