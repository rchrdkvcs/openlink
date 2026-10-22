<script setup lang="ts">
import { useEventListener, useResizeObserver } from '@vueuse/core';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

import { stateToneColor, type StateTone } from '@/lib/publicStates';

const props = withDefaults(defineProps<{ tone?: StateTone; anchor?: HTMLElement | null }>(), {
  tone: 'accent',
  anchor: null,
});

const cell = 22;
const reach = 7;

const root = ref<HTMLElement | null>(null);
const focus = ref<{ x: number; y: number } | null>(null);

const sparkles = (() => {
  const cells: { x: number; y: number; duration: number; delay: number; rest: number }[] = [];

  for (let column = -reach; column <= reach; column++) {
    for (let row = -reach; row <= reach; row++) {
      const distance = Math.hypot(column / reach, row / (reach * 0.62));
      const seed = Math.abs(Math.sin(column * 12.9898 + row * 78.233) * 43758.5453) % 1;
      const behindCopy = row > 1 && Math.abs(column) < 6;

      if (distance < 0.36 || distance > 0.95 || behindCopy || seed > 0.4) {
        continue;
      }

      cells.push({
        x: (column + reach) * cell,
        y: (row + reach) * cell,
        duration: 3.6 + seed * 9,
        delay: -seed * 17,
        rest: seed > 0.17 ? 0.55 : 0.2,
      });
    }
  }

  return cells;
})();

function measure() {
  const element = root.value;

  if (!element) {
    return;
  }

  const box = element.getBoundingClientRect();
  const target = props.anchor?.getBoundingClientRect();
  const centerX = box.width / 2;
  const rawX = target ? target.left + target.width / 2 - box.left : centerX;
  const rawY = target ? target.top + target.height / 2 - box.top : 0;

  focus.value = {
    x: centerX + cell * Math.round((rawX - centerX) / cell),
    y: cell / 2 + cell * Math.round((rawY - cell / 2) / cell),
  };
}

const observed = computed(() => [root.value, props.anchor?.closest('section') ?? null]);

useResizeObserver(observed, measure);
useEventListener('resize', measure, { passive: true });
onMounted(() => nextTick(measure));
watch(() => props.anchor, measure);

const style = computed(() => ({
  '--tone': stateToneColor[props.tone],
  '--focus-x': focus.value ? `${focus.value.x}px` : '50%',
  '--focus-y': focus.value ? `${focus.value.y}px` : '0px',
}));

const sparkleBox = computed(() =>
  focus.value
    ? {
        left: `${focus.value.x - reach * cell}px`,
        top: `${focus.value.y - reach * cell}px`,
      }
    : null,
);
</script>

<template>
  <div
    ref="root"
    class="public-backdrop pointer-events-none absolute inset-0 -z-10 overflow-hidden"
    :style="style"
    aria-hidden="true"
  >
    <div class="dot-field dot-field-base absolute inset-0" />
    <div v-if="focus" class="dot-field dot-field-tone absolute inset-0" />
    <svg
      v-if="sparkleBox"
      class="absolute overflow-visible"
      :style="sparkleBox"
      :width="reach * cell * 2"
      :height="reach * cell * 2"
    >
      <circle
        v-for="(sparkle, index) in sparkles"
        :key="index"
        class="sparkle"
        :cx="sparkle.x"
        :cy="sparkle.y"
        r="1.5"
        fill="hsl(var(--tone))"
        :style="{
          '--rest': sparkle.rest,
          animationDuration: `${sparkle.duration}s`,
          animationDelay: `${sparkle.delay}s`,
        }"
      />
    </svg>
  </div>
</template>

<style scoped>
@property --spot {
  syntax: '<length>';
  inherits: false;
  initial-value: 120px;
}

.dot-field {
  background-size: 22px 22px;
  background-position: 50% 0;
}

.dot-field-base {
  background-image: radial-gradient(circle, hsl(var(--border-strong)) 1px, transparent 1.5px);
  mask-image:
    radial-gradient(circle 56px at var(--focus-x) var(--focus-y), transparent 55%, black 100%),
    radial-gradient(ellipse 720px 520px at var(--focus-x) var(--focus-y), black 20%, transparent 75%);
  mask-composite: intersect;
}

.dot-field-tone {
  --spot: 120px;
  background-image: radial-gradient(circle, hsl(var(--tone)) 1.15px, transparent 1.65px);
  mask-image: radial-gradient(
    ellipse calc(var(--spot) * 1.7) var(--spot) at var(--focus-x) var(--focus-y),
    transparent 30%,
    black 44%,
    rgb(0 0 0 / 0.4) 70%,
    transparent 100%
  );
  animation: spot-breathe 9s ease-in-out infinite alternate;
}

.sparkle {
  opacity: 0;
  animation-name: sparkle;
  animation-timing-function: ease-in-out;
  animation-iteration-count: infinite;
}

@keyframes spot-breathe {
  from {
    --spot: 112px;
  }

  to {
    --spot: 136px;
  }
}

@keyframes sparkle {
  0%,
  100% {
    opacity: 0;
  }

  50% {
    opacity: 0.9;
  }
}

@media (prefers-reduced-motion: reduce) {
  .dot-field-tone,
  .sparkle {
    animation: none !important;
  }

  .sparkle {
    opacity: var(--rest);
  }
}
</style>
