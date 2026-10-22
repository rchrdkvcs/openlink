<script setup lang="ts">
import { computed, useId } from 'vue';

import { stateToneColor, type StateScene, type StateTone } from '@/lib/publicStates';

const props = withDefaults(
  defineProps<{
    scene: StateScene;
    tone?: StateTone;
    alert?: boolean;
  }>(),
  { tone: 'neutral', alert: false },
);

const id = useId();
const fadeId = `${id}-fade`;
const maskId = `${id}-mask`;
const scanId = `${id}-scan`;

const style = computed(() => ({ '--tone': stateToneColor[props.tone] }));

const qrFinders = [
  { x: 5.5, y: 5.5 },
  { x: 19.5, y: 5.5 },
  { x: 5.5, y: 19.5 },
];

const qrModules = [
  [15, 6],
  [15, 10],
  [6, 15],
  [10, 15],
  [15, 15],
  [19.5, 15],
  [24, 15],
  [19.5, 19.5],
  [24, 19.5],
  [15, 20],
  [19.5, 24],
  [24, 24],
  [15, 24.5],
];
</script>

<template>
  <div class="state-art relative mx-auto grid h-40 w-40 place-items-center" :style="style" aria-hidden="true">
    <svg viewBox="0 0 160 160" fill="none" class="state-orbit absolute inset-0 h-full w-full overflow-visible">
      <defs>
        <radialGradient :id="fadeId" cx="50%" cy="50%" r="50%">
          <stop offset="55%" stop-color="white" stop-opacity="1" />
          <stop offset="100%" stop-color="white" stop-opacity="0" />
        </radialGradient>
        <mask :id="maskId">
          <rect width="160" height="160" :fill="`url(#${fadeId})`" />
        </mask>
      </defs>
      <g :mask="`url(#${maskId})`">
        <circle cx="80" cy="80" r="54" stroke="hsl(var(--border-strong))" />
        <g class="origin-center animate-orbit-spin [transform-box:view-box]">
          <circle cx="80" cy="80" r="72" stroke="hsl(var(--border-strong))" stroke-dasharray="2 6" />
          <circle cx="152" cy="80" r="2" fill="hsl(var(--tone))" />
          <circle cx="8" cy="80" r="1.25" fill="hsl(var(--muted))" />
        </g>
      </g>
    </svg>

    <div :key="`${scene}-${alert}`" class="state-tile relative" :class="{ 'is-alert': alert }">
      <span class="state-halo absolute inset-0 rounded-[22px]" />
      <div
        class="relative grid h-[76px] w-[76px] place-items-center rounded-[22px] border bg-gradient-to-b from-elevated to-surface shadow-[inset_0_1px_0_rgb(255_255_255/0.06),0_24px_48px_-16px_rgb(0_0_0/0.8)]"
        :style="{ borderColor: 'hsl(var(--tone) / 0.32)' }"
      >
        <svg
          viewBox="0 0 32 32"
          class="h-9 w-9 overflow-visible"
          fill="none"
          stroke="hsl(var(--tone))"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <template v-if="scene === 'locked'">
            <path class="lock-shackle" d="M11 14.5V11a5 5 0 0 1 10 0v3.5" />
            <rect x="7.5" y="14.5" width="17" height="12" rx="3" />
            <path class="lock-key" d="M16 19.25v2.5" />
          </template>

          <template v-else-if="scene === 'scheduled'">
            <circle cx="16" cy="16" r="10.5" />
            <path d="M16 16h4" />
            <path class="clock-hand" d="M16 16V8.5" />
            <circle cx="16" cy="16" r="1.1" fill="hsl(var(--tone))" stroke="none" />
          </template>

          <template v-else-if="scene === 'ready'">
            <circle cx="16" cy="16" r="10.5" />
            <path class="draw" pathLength="1" d="M11.5 16.5l3 3 6-6.5" />
          </template>

          <template v-else-if="scene === 'unlinked'">
            <path class="link-high" d="M17 11l3-3a4.24 4.24 0 0 1 6 6l-3 3" />
            <path class="link-low" d="M15 21l-3 3a4.24 4.24 0 0 1-6-6l3-3" />
            <path class="link-bar" d="M13.5 18.5l5-5" />
            <path class="spark" d="M11.6 11.6 9.8 9.8M20.4 20.4l1.8 1.8" />
          </template>

          <template v-else-if="scene === 'offline'">
            <path class="draw draw-slow" pathLength="1" d="M4.5 16.5H9l2.5-6 3.5 11 3-8 1.5 3h3" />
            <circle class="beacon" cx="26.5" cy="16.5" r="1.6" fill="hsl(var(--tone))" stroke="none" />
          </template>

          <template v-else-if="scene === 'expired'">
            <g class="hourglass">
              <path d="M10 5.5h12M10 26.5h12" />
              <path
                d="M11.5 5.5c0 5 4.5 6.5 4.5 10.5s-4.5 5.5-4.5 10.5M20.5 5.5c0 5-4.5 6.5-4.5 10.5s4.5 5.5 4.5 10.5"
              />
            </g>
            <path
              class="sand"
              d="M13.3 24.5h5.4c-.5-1.8-1.6-2.9-2.7-3.5-1.1.6-2.2 1.7-2.7 3.5Z"
              fill="hsl(var(--tone) / 0.55)"
              stroke="none"
            />
          </template>

          <template v-else-if="scene === 'payload'">
            <defs>
              <linearGradient :id="scanId" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="hsl(var(--tone))" stop-opacity="0" />
                <stop offset="100%" stop-color="hsl(var(--tone))" stop-opacity="0.55" />
              </linearGradient>
            </defs>
            <g v-for="(finder, index) in qrFinders" :key="index" class="qr-finder" :style="{ '--i': index }">
              <rect :x="finder.x" :y="finder.y" width="7" height="7" rx="1.75" />
              <rect
                :x="finder.x + 2.5"
                :y="finder.y + 2.5"
                width="2"
                height="2"
                rx="0.5"
                fill="hsl(var(--tone))"
                stroke="none"
              />
            </g>
            <rect
              v-for="(module, index) in qrModules"
              :key="`module-${index}`"
              class="qr-module"
              :style="{ '--i': index }"
              :x="module[0]"
              :y="module[1]"
              width="2.5"
              height="2.5"
              rx="0.6"
              fill="hsl(var(--tone) / 0.7)"
              stroke="none"
            />
            <rect class="qr-scan" x="3" y="0" width="26" height="5" rx="1" :fill="`url(#${scanId})`" stroke="none" />
          </template>
        </svg>
      </div>
    </div>
  </div>
</template>

<style scoped>
.state-art {
  --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
  --ease-in-out: cubic-bezier(0.77, 0, 0.175, 1);
}

.state-orbit {
  animation: art-fade 0.9s var(--ease-out) both;
}

.state-tile {
  animation: tile-in 0.5s var(--ease-out) both;
}

.state-tile.is-alert {
  animation:
    tile-in 0.5s var(--ease-out) both,
    tile-shake 0.42s var(--ease-out) 0.45s;
}

.state-halo {
  box-shadow: 0 0 44px -8px hsl(var(--tone) / 0.45);
  opacity: 0.8;
}

.lock-shackle {
  animation: shackle-close 0.28s var(--ease-out) 0.45s both;
}

.lock-key {
  animation: art-fade 0.2s ease 0.7s both;
}

.clock-hand {
  transform-box: view-box;
  transform-origin: 16px 16px;
  animation: clock-tick 60s steps(60) infinite;
}

.draw {
  stroke-dasharray: 1;
  stroke-dashoffset: 0;
  animation: draw 0.45s var(--ease-out) 0.2s both;
}

.draw-slow {
  animation-duration: 0.9s;
  animation-timing-function: var(--ease-in-out);
}

.beacon {
  animation:
    art-fade 0.2s ease 1s both,
    beacon 2.4s var(--ease-out) 1.2s infinite;
}

.link-high,
.link-low {
  animation: link-split 0.7s var(--ease-out) 0.35s both;
}

.link-high {
  --joined: translate(-2px, 2px);
}

.link-low {
  --joined: translate(2px, -2px);
}

.link-bar {
  opacity: 0;
  animation: link-bar-break 0.3s ease 0.3s both;
}

.spark {
  stroke: hsl(var(--tone) / 0.7);
  animation: art-fade 0.25s ease 0.55s both;
}

.hourglass {
  transform-box: view-box;
  transform-origin: 16px 16px;
  animation: hourglass-flip 0.8s var(--ease-in-out) 0.3s both;
}

.sand {
  animation: art-fade 0.3s ease 1.05s both;
}

.qr-finder {
  transform-box: fill-box;
  transform-origin: center;
  animation: module-in 0.4s var(--ease-out) both;
  animation-delay: calc(0.15s + var(--i) * 60ms);
}

.qr-module {
  animation: art-fade 0.2s ease both;
  animation-delay: calc(0.35s + var(--i) * 30ms);
}

.qr-scan {
  opacity: 0;
  animation: qr-scan 1s var(--ease-in-out) 0.8s both;
}

@keyframes art-fade {
  from {
    opacity: 0;
  }
}

@keyframes tile-in {
  from {
    opacity: 0;
    transform: translateY(6px) scale(0.96);
  }
}

@keyframes tile-shake {
  0%,
  100% {
    transform: translateX(0);
  }

  20% {
    transform: translateX(-5px);
  }

  40% {
    transform: translateX(4px);
  }

  60% {
    transform: translateX(-2.5px);
  }

  80% {
    transform: translateX(1.5px);
  }
}

@keyframes shackle-close {
  from {
    transform: translateY(-3px);
  }
}

@keyframes clock-tick {
  to {
    transform: rotate(360deg);
  }
}

@keyframes draw {
  from {
    stroke-dashoffset: 1;
  }
}

@keyframes beacon {
  0%,
  100% {
    opacity: 1;
  }

  50% {
    opacity: 0.35;
  }
}

@keyframes link-split {
  from {
    transform: var(--joined);
  }
}

@keyframes link-bar-break {
  from {
    opacity: 1;
  }
}

@keyframes hourglass-flip {
  from {
    transform: rotate(-180deg);
  }
}

@keyframes module-in {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
}

@keyframes qr-scan {
  0% {
    opacity: 0;
    transform: translateY(3px);
  }

  15% {
    opacity: 1;
  }

  85% {
    opacity: 1;
  }

  100% {
    opacity: 0;
    transform: translateY(25px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .state-art * {
    animation: none !important;
  }

  .state-art .state-tile {
    animation: art-fade 0.2s ease both !important;
  }
}
</style>
