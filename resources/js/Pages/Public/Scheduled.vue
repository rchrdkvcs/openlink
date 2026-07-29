<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarClock, Check } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

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

      // The server resolves to the destination once the activation date has passed.
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

  <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-background px-4">
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-[radial-gradient(ellipse_at_top,hsl(var(--accent)/0.12),transparent_65%)]"
    />

    <section class="relative w-full max-w-xl animate-slide-up text-center">
      <div class="mx-auto mb-5 grid h-11 w-11 place-items-center rounded-xl border bg-elevated text-accent">
        <CalendarClock class="h-5 w-5" />
      </div>

      <span
        class="inline-flex items-center gap-1.5 rounded-full bg-accent/15 px-2.5 py-0.5 text-xs font-medium text-accent"
      >
        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-current" />
        Scheduled
      </span>

      <h1 class="mt-3 text-xl font-semibold text-foreground">This link isn't live yet</h1>
      <p class="mt-1.5 text-sm text-muted">
        <span class="font-medium text-foreground">{{ shortUrl }}</span> goes live in
      </p>

      <div v-if="!done" class="mt-8 flex items-stretch justify-center gap-2 sm:gap-3">
        <div
          v-for="unit in units"
          :key="unit.label"
          class="card-sheen w-20 rounded-xl border bg-surface py-4 shadow-2xl shadow-black/30 sm:w-24 sm:py-5"
        >
          <span class="block overflow-hidden text-4xl font-semibold tabular-nums text-foreground sm:text-5xl">
            <span :key="unit.value" class="digit block">{{ pad(unit.value) }}</span>
          </span>
          <span class="mt-1.5 block text-[11px] font-medium uppercase tracking-wider text-faint">{{ unit.label }}</span>
        </div>
      </div>

      <div v-else class="mt-8 flex flex-col items-center gap-3">
        <div class="grid h-14 w-14 place-items-center rounded-full bg-success/15 text-success">
          <Check class="h-7 w-7" />
        </div>
        <p class="text-sm text-muted">The link is live — redirecting…</p>
      </div>

      <p v-if="!done" class="mt-8 text-[13px] text-faint">
        Goes live {{ activationLabel }} · this page will redirect automatically.
      </p>
    </section>
  </main>
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
</style>
