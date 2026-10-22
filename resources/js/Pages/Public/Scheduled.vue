<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import PublicStateLayout from '@/Components/PublicState/PublicStateLayout.vue';
import StateArt from '@/Components/PublicState/StateArt.vue';
import Badge from '@/Components/ui/Badge.vue';

const props = defineProps<{
  shortUrl: string;
  activatesAt: string;
}>();

const target = new Date(props.activatesAt).getTime();
const now = ref(Date.now());
const done = ref(false);
let timer: number | null = null;

const remaining = computed(() => Math.max(0, Math.ceil((target - now.value) / 1000)));
const days = computed(() => Math.floor(remaining.value / 86400));

const units = computed(() => {
  const list = [
    { label: 'Days', value: days.value },
    { label: 'Hours', value: Math.floor((remaining.value % 86400) / 3600) },
    { label: 'Minutes', value: Math.floor((remaining.value % 3600) / 60) },
    { label: 'Seconds', value: remaining.value % 60 },
  ];

  return days.value > 0 ? list : list.slice(1);
});

function pad(value: number) {
  return String(value).padStart(2, '0');
}

onMounted(() => {
  timer = window.setInterval(() => {
    now.value = Date.now();

    if (target - now.value <= 0 && !done.value) {
      done.value = true;

      if (timer !== null) {
        clearInterval(timer);
      }

      setTimeout(() => window.location.reload(), 800);
    }
  }, 1000);
});

onBeforeUnmount(() => {
  if (timer !== null) {
    clearInterval(timer);
  }
});

const activationLabel = new Date(props.activatesAt).toLocaleString(undefined, {
  dateStyle: 'long',
  timeStyle: 'short',
});
</script>

<template>
  <Head title="Coming soon" />

  <PublicStateLayout
    :title="done ? 'This link is live' : 'This link isn’t live yet'"
    :tone="done ? 'success' : 'accent'"
    wide
  >
    <template #art>
      <StateArt :scene="done ? 'ready' : 'scheduled'" :tone="done ? 'success' : 'accent'" />
    </template>

    <template #badge>
      <Badge :variant="done ? 'success' : 'accent'">
        <span class="h-1.5 w-1.5 rounded-full bg-current" :class="{ 'animate-pulse': !done }" />
        {{ done ? 'Live' : 'Scheduled' }}
      </Badge>
    </template>

    <template #description>
      <template v-if="done">Redirecting you to the destination…</template>
      <template v-else>
        <span class="font-medium text-foreground">{{ shortUrl }}</span> goes live in
      </template>
    </template>

    <div v-if="!done" class="flex items-stretch justify-center gap-2 sm:gap-3" role="timer" aria-live="off">
      <div
        v-for="unit in units"
        :key="unit.label"
        class="w-[4.5rem] rounded-xl border bg-surface/80 py-4 shadow-[inset_0_1px_0_rgb(255_255_255/0.04)] backdrop-blur sm:w-24 sm:py-5"
      >
        <span
          class="block overflow-hidden text-4xl font-semibold tabular-nums tracking-tight text-foreground sm:text-5xl"
        >
          <span :key="unit.value" class="digit block">{{ pad(unit.value) }}</span>
        </span>
        <span class="mt-1.5 block text-[11px] font-medium uppercase tracking-[0.08em] text-faint">{{
          unit.label
        }}</span>
      </div>
    </div>

    <template v-if="!done" #footer> Goes live {{ activationLabel }} · this page will redirect automatically. </template>
  </PublicStateLayout>
</template>

<style scoped>
.digit {
  animation: digit-in 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes digit-in {
  from {
    opacity: 0;
    transform: translateY(40%);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .digit {
    animation: none !important;
  }
}
</style>
