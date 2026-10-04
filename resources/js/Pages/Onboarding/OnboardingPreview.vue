<script setup lang="ts">
import { Check, Link2, User, Users } from '@lucide/vue';
import { computed } from 'vue';

import Favicon from '@/Components/Links/Favicon.vue';
import { displayUrl } from '@/lib/links';
import { roleLabel } from '@/lib/permissions';
import { workspaceInitial } from '@/lib/workspaces';

import type { OnboardingStep } from './onboardingSteps';

const props = defineProps<{
  step: OnboardingStep;
  workspaceName: string;
  hostname?: string;
  destinationUrl: string;
  shortUrl?: string | null;
  inviteUrl?: string | null;
  inviteRole: string;
}>();

const destination = computed(() => props.destinationUrl.trim());
const destinationLabel = computed(() => (destination.value ? displayUrl(destination.value) : ''));
const shortLabel = computed(() => (props.shortUrl ? displayUrl(props.shortUrl) : null));

const done = computed<Record<OnboardingStep, boolean>>(() => ({
  1: props.step > 1,
  2: Boolean(props.shortUrl),
  3: Boolean(props.inviteUrl),
}));

const isActive = (step: OnboardingStep) => props.step === step;
const isLit = (step: OnboardingStep) => props.step === step || done.value[step];

const seats = [
  { left: '17.3%', top: '50%', hue: 234 },
  { left: '21.7%', top: '66.3%', hue: 280 },
  { left: '78.3%', top: '66.3%', hue: 152 },
  { left: '82.7%', top: '50%', hue: 38 },
];

const seatLines = ['M214 260 L110 260', 'M224 288 L133 333', 'M296 288 L387 333', 'M306 260 L410 260'];
</script>

<template>
  <div
    class="relative isolate flex items-center justify-center overflow-hidden rounded-2xl border bg-canvas p-8"
    aria-hidden="true"
  >
    <div
      class="absolute inset-0 -z-10 bg-[radial-gradient(hsl(var(--border-strong))_1px,transparent_1px)] [background-size:22px_22px] [mask-image:radial-gradient(ellipse_at_center,black_20%,transparent_70%)]"
    />
    <div
      class="absolute left-1/2 top-1/2 -z-10 h-[560px] w-[560px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(circle,hsl(var(--accent)/0.16),transparent_60%)]"
    />

    <div class="relative aspect-square w-full max-w-[580px] select-none">
      <svg viewBox="0 0 520 520" fill="none" class="absolute inset-0 h-full w-full overflow-visible">
        <defs>
          <linearGradient id="onboarding-flow" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="520" y2="520">
            <stop offset="0%" stop-color="hsl(var(--accent))" />
            <stop offset="100%" stop-color="hsl(var(--success))" />
          </linearGradient>
          <radialGradient id="onboarding-ring-fade" cx="50%" cy="50%" r="50%">
            <stop offset="55%" stop-color="white" stop-opacity="1" />
            <stop offset="100%" stop-color="white" stop-opacity="0" />
          </radialGradient>
          <mask id="onboarding-ring-mask">
            <rect width="520" height="520" fill="url(#onboarding-ring-fade)" />
          </mask>
        </defs>

        <g mask="url(#onboarding-ring-mask)">
          <circle cx="260" cy="260" r="110" stroke="hsl(var(--border-strong))" />
          <g class="origin-center animate-orbit-spin [transform-box:view-box]">
            <circle cx="260" cy="260" r="170" stroke="hsl(var(--border-strong))" stroke-dasharray="2 7" />
            <circle cx="430" cy="260" r="2.5" fill="hsl(var(--accent))" />
            <circle cx="90" cy="260" r="1.5" fill="hsl(var(--muted))" />
          </g>
          <circle cx="260" cy="260" r="240" stroke="hsl(var(--border))" />
        </g>

        <g class="ease-emphasized-out transition-opacity duration-500" :class="isLit(2) ? 'opacity-100' : 'opacity-30'">
          <path d="M228 106 L292 106" stroke="hsl(var(--border-strong))" stroke-width="1.5" />
          <path d="M260 214 C260 176 398 186 398 142" stroke="hsl(var(--border-strong))" stroke-width="1.5" />
          <template v-if="isActive(2) || done[2]">
            <path
              d="M228 106 L292 106"
              stroke="url(#onboarding-flow)"
              stroke-width="1.5"
              stroke-dasharray="4 6"
              stroke-linecap="round"
              class="animate-dash-flow"
            />
            <path
              d="M260 214 C260 176 398 186 398 142"
              stroke="url(#onboarding-flow)"
              stroke-width="1.5"
              stroke-dasharray="4 6"
              stroke-linecap="round"
              class="animate-dash-flow"
            />
          </template>
        </g>

        <g class="ease-emphasized-out transition-opacity duration-500" :class="isLit(3) ? 'opacity-100' : 'opacity-25'">
          <path d="M260 348 L260 398" stroke="hsl(var(--border-strong))" stroke-width="1.5" />
          <path
            v-for="line in seatLines"
            :key="line"
            :d="line"
            stroke="hsl(var(--border-strong))"
            stroke-dasharray="2 5"
          />
          <template v-if="isActive(3) || done[3]">
            <path
              v-for="line in seatLines"
              :key="`flow-${line}`"
              :d="line"
              stroke="url(#onboarding-flow)"
              stroke-dasharray="4 6"
              stroke-linecap="round"
              class="animate-dash-flow"
            />
          </template>
          <path
            v-if="isActive(3) || done[3]"
            d="M260 348 L260 398"
            stroke="url(#onboarding-flow)"
            stroke-width="1.5"
            stroke-dasharray="4 6"
            stroke-linecap="round"
            class="animate-dash-flow"
          />
        </g>
      </svg>

      <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
        <span v-if="isActive(1)" class="absolute inset-0 animate-hub-pulse rounded-[26px] border border-accent/50" />
        <div
          class="ease-emphasized-out relative grid h-[92px] w-[92px] place-items-center rounded-[26px] border bg-gradient-to-b from-elevated to-surface transition-[border-color,box-shadow] duration-500"
          :class="
            isActive(1)
              ? 'border-accent/50 shadow-[inset_0_1px_0_rgb(255_255_255/0.06),0_0_48px_-8px_hsl(var(--accent)/0.5)]'
              : 'border-border-strong shadow-[inset_0_1px_0_rgb(255_255_255/0.06),0_24px_48px_-16px_rgb(0_0_0/0.8)]'
          "
        >
          <span
            class="bg-gradient-to-b from-foreground to-muted bg-clip-text text-[38px] font-semibold tracking-tight text-transparent"
          >
            {{ workspaceInitial(workspaceName || 'O') }}
          </span>
          <span
            class="ease-emphasized-out absolute -right-1.5 -top-1.5 grid h-5 w-5 place-items-center rounded-full bg-success text-background ring-4 ring-canvas transition-[transform,opacity] duration-300"
            :class="done[1] ? 'scale-100 opacity-100' : 'scale-50 opacity-0'"
          >
            <Check class="h-3 w-3" :stroke-width="3" />
          </span>
        </div>
      </div>

      <div class="absolute left-1/2 top-[61.5%] max-w-[46%] -translate-x-1/2">
        <p
          class="truncate rounded-full border bg-surface/90 px-3 py-1 text-center text-xs font-medium backdrop-blur"
          :class="workspaceName ? 'text-foreground' : 'text-faint'"
        >
          {{ workspaceName || 'Your workspace' }}
        </p>
      </div>

      <div
        class="ease-emphasized-out absolute left-[3.1%] top-[12%] w-[40.8%] transition-[opacity,transform] duration-500"
        :class="isLit(2) ? 'translate-y-0 opacity-100' : 'translate-y-1 opacity-40'"
      >
        <div class="rounded-xl border bg-surface/90 p-3 backdrop-blur">
          <p class="text-[10px] font-medium uppercase tracking-[0.08em] text-faint">Long URL</p>
          <div class="mt-2 flex h-5 items-center gap-2">
            <Favicon v-if="destinationLabel" :url="destination" size="sm" />
            <span v-else class="h-4 w-4 shrink-0 rounded-[4px] bg-elevated" />
            <span v-if="destinationLabel" class="truncate text-xs text-muted">{{ destinationLabel }}</span>
            <span v-else class="grid flex-1 gap-1.5">
              <span class="h-1.5 w-full rounded-full bg-elevated" />
              <span class="h-1.5 w-2/3 rounded-full bg-elevated" />
            </span>
          </div>
        </div>
      </div>

      <div
        class="ease-emphasized-out absolute left-[56.2%] top-[12%] w-[40.8%] transition-[opacity,transform] duration-500"
        :class="isLit(2) ? 'translate-y-0 opacity-100' : 'translate-y-1 opacity-40'"
      >
        <div
          class="relative rounded-xl border bg-surface/90 p-3 backdrop-blur transition-[border-color,box-shadow] duration-500"
          :class="
            isActive(2) && !done[2]
              ? 'border-accent/40 shadow-[0_0_32px_-8px_hsl(var(--accent)/0.45)]'
              : done[2]
                ? 'border-success/30'
                : ''
          "
        >
          <p class="flex items-center gap-1 text-[10px] font-medium uppercase tracking-[0.08em] text-faint">
            <Link2 class="h-3 w-3" /> Short link
          </p>
          <p class="mt-2 flex h-5 items-center truncate font-mono text-[13px] font-medium text-foreground">
            <template v-if="shortLabel">{{ shortLabel }}</template>
            <template v-else> {{ hostname ?? 'your.link' }}<span class="text-faint">/•••••</span> </template>
          </p>
          <span
            class="ease-emphasized-out absolute -right-1.5 -top-1.5 grid h-5 w-5 place-items-center rounded-full bg-success text-background ring-4 ring-canvas transition-[transform,opacity] duration-300"
            :class="done[2] ? 'scale-100 opacity-100' : 'scale-50 opacity-0'"
          >
            <Check class="h-3 w-3" :stroke-width="3" />
          </span>
        </div>
      </div>

      <span
        v-for="(seat, index) in seats"
        :key="seat.hue"
        class="ease-emphasized-out absolute grid h-10 w-10 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border transition-[opacity,scale,background-color,border-color] duration-500"
        :class="
          done[3]
            ? 'scale-100 border-transparent opacity-100'
            : isActive(3)
              ? 'scale-100 border-dashed border-border-strong bg-surface opacity-100'
              : 'scale-90 border-dashed border-border-strong bg-surface opacity-30'
        "
        :style="{
          left: seat.left,
          top: seat.top,
          transitionDelay: `${index * 60}ms`,
          backgroundImage: done[3]
            ? `linear-gradient(135deg, hsl(${seat.hue} 60% 62%), hsl(${seat.hue + 30} 55% 42%))`
            : undefined,
        }"
      >
        <User class="h-4 w-4" :class="done[3] ? 'text-white/90' : 'text-faint'" />
      </span>

      <div
        class="ease-emphasized-out absolute left-1/2 top-[76.5%] w-[50%] -translate-x-1/2 transition-[opacity,transform] duration-500"
        :class="isLit(3) ? 'translate-y-0 opacity-100' : 'translate-y-1 opacity-40'"
      >
        <div
          class="relative flex items-center gap-2.5 rounded-xl border bg-surface/90 p-3 backdrop-blur transition-[border-color,box-shadow] duration-500"
          :class="
            isActive(3) && !done[3]
              ? 'border-accent/40 shadow-[0_0_32px_-8px_hsl(var(--accent)/0.45)]'
              : done[3]
                ? 'border-success/30'
                : ''
          "
        >
          <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-elevated">
            <Users class="h-4 w-4" :class="done[3] ? 'text-foreground' : 'text-faint'" />
          </span>
          <div class="grid min-w-0 flex-1 gap-1">
            <p class="truncate text-xs font-medium text-foreground">
              {{ inviteUrl ? displayUrl(inviteUrl) : 'Invite link' }}
            </p>
            <p class="text-[11px] text-faint">Joins as {{ roleLabel(inviteRole) }}</p>
          </div>
          <span
            class="ease-emphasized-out absolute -right-1.5 -top-1.5 grid h-5 w-5 place-items-center rounded-full bg-success text-background ring-4 ring-canvas transition-[transform,opacity] duration-300"
            :class="done[3] ? 'scale-100 opacity-100' : 'scale-50 opacity-0'"
          >
            <Check class="h-3 w-3" :stroke-width="3" />
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
