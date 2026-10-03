<script setup lang="ts">
import { Check } from '@lucide/vue';

import { type OnboardingStep, onboardingSteps } from './onboardingSteps';

defineProps<{ step: OnboardingStep }>();
</script>

<template>
  <ol class="mb-6 flex items-center justify-center gap-2" aria-label="Setup progress">
    <template v-for="(item, index) in onboardingSteps" :key="item.number">
      <li class="flex items-center gap-2" :aria-current="step === item.number ? 'step' : undefined">
        <span
          class="grid h-5 w-5 place-items-center rounded-full text-[11px] font-semibold tabular-nums transition-colors duration-200"
          :class="step >= item.number ? 'bg-foreground text-background' : 'border border-border-strong text-faint'"
        >
          <Check v-if="step > item.number" class="h-3 w-3" />
          <template v-else>{{ item.number }}</template>
        </span>
        <span class="text-xs font-medium" :class="step >= item.number ? 'text-foreground' : 'text-faint'">{{
          item.label
        }}</span>
      </li>
      <li v-if="index < onboardingSteps.length - 1" class="h-px w-6 bg-border" aria-hidden="true" />
    </template>
  </ol>
</template>
