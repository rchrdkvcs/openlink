<script setup lang="ts">
import { type OnboardingStep, onboardingStepCount, onboardingSteps } from './onboardingSteps';

defineProps<{ step: OnboardingStep; completed: boolean }>();
</script>

<template>
  <div>
    <p class="text-[11px] font-medium uppercase tabular-nums tracking-[0.08em] text-faint">
      Step {{ step }} of {{ onboardingStepCount }}
    </p>
    <ol class="mt-3 grid grid-cols-3 gap-1.5" aria-label="Setup progress">
      <li
        v-for="item in onboardingSteps"
        :key="item.number"
        class="grid gap-2"
        :aria-current="step === item.number ? 'step' : undefined"
      >
        <span class="relative h-1 overflow-hidden rounded-full bg-border-strong" aria-hidden="true">
          <span
            class="ease-emphasized-out absolute inset-0 origin-left rounded-full bg-foreground transition-transform duration-500"
            :class="{
              'scale-x-100': step > item.number || (step === item.number && completed),
              'scale-x-[0.35]': step === item.number && !completed,
              'scale-x-0': step < item.number,
            }"
          />
        </span>
        <span
          class="text-xs font-medium transition-colors duration-200"
          :class="step >= item.number ? 'text-foreground' : 'text-faint'"
        >
          {{ item.label }}
          <span v-if="item.optional" class="hidden font-normal text-faint sm:inline">· Optional</span>
        </span>
      </li>
    </ol>
  </div>
</template>
