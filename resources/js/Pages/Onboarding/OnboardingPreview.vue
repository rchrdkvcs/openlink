<script setup lang="ts">
import { computed, ref } from 'vue';

import Favicon from '@/Components/Links/Favicon.vue';
import PublicBackdrop from '@/Components/PublicState/PublicBackdrop.vue';
import StateArt from '@/Components/PublicState/StateArt.vue';
import StateBadge from '@/Components/PublicState/StateBadge.vue';
import { displayUrl } from '@/lib/links';
import { roleLabel } from '@/lib/permissions';
import type { StateScene, StateTone } from '@/lib/publicStates';

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

const art = ref<HTMLElement | null>(null);

const destination = computed(() => props.destinationUrl.trim());
const destinationLabel = computed(() => (destination.value ? displayUrl(destination.value) : ''));
const shortLabel = computed(() => (props.shortUrl ? displayUrl(props.shortUrl) : null));

const done = computed(() =>
  props.step === 2 ? Boolean(props.shortUrl) : props.step === 3 && Boolean(props.inviteUrl),
);

const stepScene: Record<OnboardingStep, StateScene> = { 1: 'workspace', 2: 'link', 3: 'team' };

const scene = computed<StateScene>(() => (done.value ? 'ready' : stepScene[props.step]));
const tone = computed<StateTone>(() => (done.value ? 'success' : 'accent'));
</script>

<template>
  <div class="relative isolate flex overflow-hidden rounded-2xl border bg-canvas" aria-hidden="true">
    <PublicBackdrop :tone="tone" :anchor="art" />

    <section class="m-auto w-full max-w-[400px] select-none px-8 py-16 text-center">
      <div ref="art">
        <Transition
          mode="out-in"
          leave-active-class="transition-opacity duration-150 ease-out"
          leave-to-class="opacity-0"
        >
          <StateArt :key="scene" :scene="scene" :tone="tone" />
        </Transition>
      </div>

      <Transition
        mode="out-in"
        enter-active-class="transition-[opacity,transform] duration-300 ease-emphasized-out"
        enter-from-class="translate-y-1.5 opacity-0"
        leave-active-class="transition-opacity duration-150 ease-out"
        leave-to-class="opacity-0"
      >
        <div :key="`${step}-${done}`" class="mt-10">
          <div class="mb-4 flex justify-center">
            <StateBadge :tone="tone" :live="!done">
              <template v-if="step === 1">Workspace</template>
              <template v-else-if="step === 2">{{ done ? 'Live' : 'Draft' }}</template>
              <template v-else>{{ done ? 'Ready' : 'Invite' }}</template>
            </StateBadge>
          </div>

          <template v-if="step === 1">
            <h2
              class="truncate text-[32px] font-semibold leading-[1.1] tracking-[-0.025em]"
              :class="workspaceName ? 'text-foreground' : 'text-faint'"
            >
              {{ workspaceName || 'Your workspace' }}
            </h2>
            <p class="mx-auto mt-3 max-w-[320px] text-pretty text-[15px] leading-relaxed text-muted">
              Links, domains and your team live here.
            </p>
          </template>

          <template v-else-if="step === 2">
            <h2 class="truncate font-mono text-[22px] font-medium leading-[1.2] tracking-[-0.01em] text-foreground">
              <template v-if="shortLabel">{{ shortLabel }}</template>
              <template v-else>{{ hostname ?? 'your.link' }}<span class="text-faint">/•••••</span></template>
            </h2>
            <p class="mx-auto mt-3 flex max-w-[320px] items-center justify-center gap-2 text-[15px] text-muted">
              <template v-if="destinationLabel">
                <Favicon :url="destination" size="sm" />
                <span class="truncate">{{ destinationLabel }}</span>
              </template>
              <span v-else class="text-faint">Paste a URL to preview it</span>
            </p>
          </template>

          <template v-else>
            <h2 class="truncate text-[32px] font-semibold leading-[1.1] tracking-[-0.025em] text-foreground">
              Join {{ workspaceName || 'your workspace' }}
            </h2>
            <p class="mx-auto mt-3 max-w-[320px] text-pretty text-[15px] leading-relaxed text-muted">
              New members join as {{ roleLabel(inviteRole) }}.
            </p>
            <p v-if="inviteUrl" class="mx-auto mt-2 max-w-[320px] truncate font-mono text-xs text-faint">
              {{ displayUrl(inviteUrl) }}
            </p>
          </template>
        </div>
      </Transition>
    </section>
  </div>
</template>
