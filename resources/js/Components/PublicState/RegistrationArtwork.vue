<script setup lang="ts">
import { computed } from 'vue';

import { stateToneColor, type StateTone } from '@/lib/publicStates';

const props = defineProps<{
  mode: 'closed' | 'invite_only';
}>();

const modeTone: Record<'closed' | 'invite_only', StateTone> = {
  closed: 'neutral',
  invite_only: 'accent',
};

const style = computed(() => ({
  '--tone': stateToneColor[modeTone[props.mode]],
}));
</script>

<template>
  <div class="registration-art mx-auto h-[88px] w-[88px]" :style="style" aria-hidden="true">
    <svg
      :key="mode"
      viewBox="0 0 64 64"
      class="h-full w-full overflow-visible"
      fill="none"
      stroke="hsl(var(--foreground) / 0.88)"
      stroke-width="1.25"
      stroke-linecap="round"
      stroke-linejoin="round"
      focusable="false"
    >
      <rect x="13" y="12" width="32" height="38" rx="6" />
      <circle cx="29" cy="21" r="3.25" />
      <path d="M23.5 30a5.5 5.5 0 0 1 11 0" />
      <path d="M19 37h18M19 43h12" stroke="hsl(var(--foreground) / 0.32)" />

      <circle cx="45" cy="45" r="10" fill="hsl(var(--background))" />
      <circle cx="45" cy="45" r="10" fill="hsl(var(--tone) / 0.08)" stroke="none" />

      <g v-if="mode === 'invite_only'" stroke="hsl(var(--tone))" transform="rotate(-45 45 45)">
        <rect class="invite-link-start" x="37" y="42.5" width="9" height="5" rx="2.5" />
        <rect class="invite-link-end" x="44" y="42.5" width="9" height="5" rx="2.5" />
      </g>

      <g v-else stroke="hsl(var(--tone))">
        <path class="closed-shackle" d="M41.5 44v-2.5a3.5 3.5 0 0 1 7 0V44" />
        <rect x="40" y="44" width="10" height="7" rx="2" />
        <circle class="closed-key" cx="45" cy="47.5" r="1" fill="hsl(var(--tone))" stroke="none" />
      </g>
    </svg>
  </div>
</template>

<style scoped>
.registration-art {
  --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
  --ease-in-out: cubic-bezier(0.77, 0, 0.175, 1);
  animation: art-in 0.6s var(--ease-out) both;
}

.invite-link-start,
.invite-link-end {
  animation: invite-link 5.2s var(--ease-in-out) infinite;
}

.invite-link-start {
  --apart: translateX(-1.25px);
}

.invite-link-end {
  --apart: translateX(1.25px);
}

.closed-shackle {
  animation: closed-shackle 5.6s ease-in-out infinite;
}

.closed-key {
  animation: closed-key 5.6s ease-in-out infinite;
}

@keyframes art-in {
  from {
    opacity: 0;
    transform: translateY(4px);
  }
}

@keyframes invite-link {
  0%,
  14%,
  100% {
    transform: translateX(0);
  }

  32%,
  80% {
    transform: var(--apart);
  }
}

@keyframes closed-shackle {
  0%,
  58%,
  86%,
  100% {
    transform: translateY(0);
  }

  70% {
    transform: translateY(-1.5px);
  }
}

@keyframes closed-key {
  0%,
  58%,
  86%,
  100% {
    opacity: 1;
  }

  70% {
    opacity: 0.4;
  }
}

@keyframes art-fade {
  from {
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .registration-art,
  .registration-art * {
    animation: none !important;
  }

  .registration-art {
    animation: art-fade 0.2s ease both !important;
  }
}
</style>
