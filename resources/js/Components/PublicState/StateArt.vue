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

const scanId = `${useId()}-scan`;

const now = new Date();
const secondsIntoMinute = now.getSeconds() + now.getMilliseconds() / 1000;
const secondsIntoHour = now.getMinutes() * 60 + secondsIntoMinute;

const clock = {
  second: { transform: `rotate(${Math.floor(secondsIntoMinute) * 6}deg)`, animationDelay: `-${secondsIntoMinute}s` },
  minute: { transform: `rotate(${secondsIntoHour * 0.1}deg)`, animationDelay: `-${secondsIntoHour}s` },
  arc: { animationDelay: `-${secondsIntoMinute}s` },
};

const style = computed(() => ({ '--tone': stateToneColor[props.tone] }));

const qrFinders = [
  { x: 12, y: 12 },
  { x: 38, y: 12 },
  { x: 12, y: 38 },
];

const qrModules = [
  [31, 13],
  [31, 21],
  [13, 31],
  [21, 31],
  [31, 31],
  [39, 31],
  [47, 31],
  [31, 39],
  [39, 39],
  [47, 39],
  [31, 47],
  [39, 47],
  [47, 47],
];
</script>

<template>
  <div class="state-art mx-auto h-[88px] w-[88px]" :style="style" aria-hidden="true">
    <svg
      :key="`${scene}-${alert}`"
      viewBox="0 0 64 64"
      class="state-glyph h-full w-full overflow-visible"
      :class="{ 'is-alert': alert }"
      fill="none"
      stroke="hsl(var(--foreground) / 0.88)"
      stroke-width="1.25"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <template v-if="scene === 'locked'">
        <path class="lock-shackle" d="M23 29v-7a9 9 0 0 1 18 0v7" />
        <rect x="16" y="29" width="32" height="23" rx="6" />
        <g class="lock-key" stroke="hsl(var(--tone))">
          <circle cx="32" cy="39" r="2.25" fill="hsl(var(--tone))" stroke="none" />
          <path d="M32 41v4" />
        </g>
      </template>

      <template v-else-if="scene === 'scheduled'">
        <circle cx="32" cy="32" r="22" stroke="hsl(var(--foreground) / 0.16)" />
        <circle
          class="clock-arc"
          :style="clock.arc"
          cx="32"
          cy="32"
          r="22"
          pathLength="1"
          stroke="hsl(var(--tone))"
          transform="rotate(-90 32 32)"
        />
        <path class="clock-minute" :style="clock.minute" d="M32 32V19" />
        <path class="clock-second" :style="clock.second" d="M32 35V14" stroke="hsl(var(--tone))" stroke-width="1" />
        <circle cx="32" cy="32" r="1.75" fill="hsl(var(--tone))" stroke="none" />
      </template>

      <template v-else-if="scene === 'ready'">
        <circle cx="32" cy="32" r="22" stroke="hsl(var(--tone) / 0.22)" />
        <circle
          class="ready-ring"
          cx="32"
          cy="32"
          r="22"
          pathLength="1"
          stroke="hsl(var(--tone))"
          transform="rotate(-90 32 32)"
        />
        <path class="ready-check" pathLength="1" d="M23 32.5l6 6 12-13" stroke="hsl(var(--tone))" />
      </template>

      <template v-else-if="scene === 'unlinked'">
        <path class="link-high" d="M34 22l6-6a8.49 8.49 0 0 1 12 12l-6 6" />
        <path class="link-low" d="M30 42l-6 6a8.49 8.49 0 0 1-12-12l6-6" />
        <path class="link-bar" d="M26.5 37.5l11-11" />
        <g class="link-sparks" stroke="hsl(var(--tone))">
          <path d="M23.5 23.5 20 20M40.5 40.5l3.5 3.5" />
          <path d="M30.5 17.5 29.5 14M33.5 46.5l1 3.5" />
        </g>
      </template>

      <template v-else-if="scene === 'offline'">
        <path d="M8 34h11l4-9 6 18 5-14 3 5h19" stroke="hsl(var(--foreground) / 0.32)" />
        <path class="wave-pulse" pathLength="1" d="M8 34h11l4-9 6 18 5-14 3 5h19" stroke="hsl(var(--tone))" />
        <circle class="wave-node" cx="56" cy="34" r="2" fill="hsl(var(--tone))" stroke="none" />
      </template>

      <template v-else-if="scene === 'expired'">
        <g class="hourglass">
          <path d="M20 11h24M20 53h24" />
          <path d="M23 11c0 10 9 13 9 21s-9 11-9 21M41 11c0 10-9 13-9 21s9 11 9 21" />
          <path
            class="sand-top"
            d="M26 17h12c-1 4-3.5 7-6 9-2.5-2-5-5-6-9Z"
            fill="hsl(var(--tone) / 0.85)"
            stroke="none"
          />
          <path class="sand-stream" d="M32 27v14" stroke="hsl(var(--tone))" stroke-width="1" />
          <path
            class="sand-bottom"
            d="M25 49h14c-1.5-4.5-4-7-7-8-3 1-5.5 3.5-7 8Z"
            fill="hsl(var(--tone) / 0.85)"
            stroke="none"
          />
        </g>
      </template>

      <template v-else-if="scene === 'payload'">
        <defs>
          <linearGradient :id="scanId" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="hsl(var(--tone))" stop-opacity="0" />
            <stop offset="50%" stop-color="hsl(var(--tone))" stop-opacity="0.28" />
            <stop offset="100%" stop-color="hsl(var(--tone))" stop-opacity="0" />
          </linearGradient>
        </defs>
        <g v-for="(finder, index) in qrFinders" :key="index">
          <rect :x="finder.x" :y="finder.y" width="14" height="14" rx="3.5" />
          <rect
            :x="finder.x + 4.5"
            :y="finder.y + 4.5"
            width="5"
            height="5"
            rx="1.25"
            fill="hsl(var(--foreground) / 0.88)"
            stroke="none"
          />
        </g>
        <rect
          v-for="(module, index) in qrModules"
          :key="`module-${index}`"
          :x="module[0]"
          :y="module[1]"
          width="4"
          height="4"
          rx="1"
          fill="hsl(var(--foreground) / 0.5)"
          stroke="none"
        />
        <g class="qr-scan">
          <rect x="6" y="-5" width="52" height="10" :fill="`url(#${scanId})`" stroke="none" />
          <path d="M6 0h52" stroke="hsl(var(--tone))" />
        </g>
      </template>
    </svg>
  </div>
</template>

<style scoped>
.state-art {
  --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
  --ease-in-out: cubic-bezier(0.77, 0, 0.175, 1);
  animation: art-in 0.6s var(--ease-out) both;
}

.state-glyph.is-alert {
  animation: glyph-shake 0.42s var(--ease-out) 0.1s;
}

.lock-shackle {
  animation: lock-shackle 5.6s ease-in-out infinite;
}

.lock-key {
  animation: lock-key 5.6s ease-in-out infinite;
}

.clock-arc {
  stroke-dasharray: 1;
  stroke-dashoffset: 0.25;
  animation: clock-arc 60s linear infinite;
}

.clock-minute,
.clock-second {
  transform-box: view-box;
  transform-origin: 32px 32px;
}

.clock-minute {
  animation: clock-turn 3600s linear infinite;
}

.clock-second {
  animation: clock-turn 60s steps(60) infinite;
}

.ready-ring,
.ready-check {
  stroke-dasharray: 1;
  stroke-dashoffset: 0;
}

.ready-ring {
  animation: ready-ring 3.2s var(--ease-out) infinite;
}

.ready-check {
  animation: ready-check 3.2s var(--ease-out) infinite;
}

.link-high,
.link-low {
  animation: link-split 5.2s var(--ease-in-out) infinite;
}

.link-high {
  --joined: translate(-3px, 3px);
}

.link-low {
  --joined: translate(3px, -3px);
}

.link-bar {
  opacity: 0;
  animation: link-bar 5.2s ease-in-out infinite;
}

.link-sparks {
  animation: link-sparks 5.2s ease-out infinite;
}

.wave-pulse {
  stroke-dasharray: 1 0;
  animation: wave-pulse 3.2s var(--ease-in-out) infinite;
}

.wave-node {
  animation: wave-node 3.2s ease-in-out infinite;
}

.hourglass {
  transform-box: view-box;
  transform-origin: 32px 32px;
  animation: hourglass-flip 6.4s var(--ease-in-out) infinite;
}

.sand-top {
  transform-box: view-box;
  transform-origin: 32px 26px;
  transform: scaleY(0.55);
  animation: sand-top 6.4s linear infinite;
}

.sand-bottom {
  transform-box: view-box;
  transform-origin: 32px 49px;
  transform: scaleY(0.55);
  animation: sand-bottom 6.4s linear infinite;
}

.sand-stream {
  stroke-dasharray: 1.5 2.5;
  animation: sand-stream 6.4s linear infinite;
}

.qr-scan {
  opacity: 0;
  animation: qr-scan 3.6s var(--ease-in-out) infinite alternate;
}

@keyframes art-in {
  from {
    opacity: 0;
    transform: translateY(4px);
  }
}

@keyframes glyph-shake {
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

@keyframes lock-shackle {
  0%,
  58%,
  86%,
  100% {
    transform: translateY(0);
  }

  70% {
    transform: translateY(-2.5px);
  }
}

@keyframes lock-key {
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

@keyframes clock-arc {
  from {
    stroke-dashoffset: 1;
  }

  to {
    stroke-dashoffset: 0;
  }
}

@keyframes clock-turn {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}

@keyframes ready-ring {
  0% {
    stroke-dashoffset: 1;
    opacity: 1;
  }

  35%,
  82% {
    stroke-dashoffset: 0;
    opacity: 1;
  }

  100% {
    stroke-dashoffset: 0;
    opacity: 0;
  }
}

@keyframes ready-check {
  0%,
  25% {
    stroke-dashoffset: 1;
    opacity: 1;
  }

  50%,
  82% {
    stroke-dashoffset: 0;
    opacity: 1;
  }

  100% {
    stroke-dashoffset: 0;
    opacity: 0;
  }
}

@keyframes link-split {
  0%,
  14%,
  100% {
    transform: var(--joined);
  }

  32%,
  80% {
    transform: translate(0, 0);
  }
}

@keyframes link-bar {
  0%,
  16%,
  100% {
    opacity: 1;
  }

  26%,
  84% {
    opacity: 0;
  }
}

@keyframes link-sparks {
  0%,
  28% {
    opacity: 0;
  }

  36% {
    opacity: 1;
  }

  62%,
  100% {
    opacity: 0;
  }
}

@keyframes wave-pulse {
  0% {
    stroke-dasharray: 0.32 1;
    stroke-dashoffset: 0.32;
  }

  72%,
  100% {
    stroke-dasharray: 0.32 1;
    stroke-dashoffset: -1;
  }
}

@keyframes wave-node {
  0%,
  60%,
  100% {
    opacity: 0.3;
  }

  74% {
    opacity: 1;
  }
}

@keyframes hourglass-flip {
  0%,
  80% {
    transform: rotate(0deg);
  }

  94%,
  100% {
    transform: rotate(180deg);
  }
}

@keyframes sand-top {
  0% {
    transform: scaleY(1);
  }

  78%,
  100% {
    transform: scaleY(0);
  }
}

@keyframes sand-bottom {
  0% {
    transform: scaleY(0);
  }

  78%,
  100% {
    transform: scaleY(1);
  }
}

@keyframes sand-stream {
  0% {
    opacity: 1;
    stroke-dashoffset: 0;
  }

  74% {
    opacity: 1;
    stroke-dashoffset: -16;
  }

  78%,
  100% {
    opacity: 0;
    stroke-dashoffset: -16;
  }
}

@keyframes qr-scan {
  0% {
    opacity: 0;
    transform: translateY(10px);
  }

  12%,
  88% {
    opacity: 1;
  }

  100% {
    opacity: 0;
    transform: translateY(54px);
  }
}

@keyframes art-fade {
  from {
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .state-art,
  .state-art * {
    animation: none !important;
  }

  .state-art {
    animation: art-fade 0.2s ease both !important;
  }
}
</style>
