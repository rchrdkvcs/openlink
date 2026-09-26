<script setup lang="ts">
import { CalendarClock, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import { controlVariants } from '@/lib/controls';
import { addDays, fromInputValue, humanize, monthGrid, monthLabel, toInputValue, WEEKDAYS } from '@/lib/datetime';
import { cn } from '@/lib/utils';

const props = withDefaults(
  defineProps<{
    modelValue: string;
    placeholder?: string;
    align?: 'start' | 'end';
  }>(),
  { placeholder: 'Pick a date…', align: 'start' },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const open = ref(false);
const view = ref(new Date());

const selected = computed(() => fromInputValue(props.modelValue));

const PRESETS = [
  { label: 'Today 9:00', days: 0 },
  { label: 'Tomorrow', days: 1 },
  { label: 'In a week', days: 7 },
  { label: 'In a month', days: 30 },
];

function toggle() {
  open.value = !open.value;
  if (open.value) {
    view.value = selected.value ?? new Date();
  }
}

function shiftMonth(delta: number) {
  view.value = new Date(view.value.getFullYear(), view.value.getMonth() + delta, 1);
}

function pickDay(day: Date) {
  const next = new Date(day);
  next.setHours(selected.value?.getHours() ?? 9, selected.value?.getMinutes() ?? 0);
  emit('update:modelValue', toInputValue(next));
}

function setTime(part: 'hours' | 'minutes', value: number) {
  const next = new Date(selected.value ?? new Date());
  if (part === 'hours') next.setHours(value);
  else next.setMinutes(value);
  emit('update:modelValue', toInputValue(next));
}

function applyPreset(days: number) {
  const d = addDays(new Date(), days);
  d.setHours(9, 0, 0, 0);
  emit('update:modelValue', toInputValue(d));
  view.value = d;
}

const pad = (n: number) => String(n).padStart(2, '0');
const HOURS = Array.from({ length: 24 }, (_, i) => ({ value: i, label: pad(i) }));
const MINUTES = Array.from({ length: 12 }, (_, i) => ({ value: i * 5, label: pad(i * 5) }));
</script>

<template>
  <!-- Escape closes the popover without bubbling to the drawer's document listener. -->
  <div class="relative" @keydown.escape.stop="open = false">
    <button
      type="button"
      :class="
        cn(controlVariants(), 'flex items-center justify-between gap-2', modelValue ? 'text-foreground' : 'text-faint')
      "
      @click="toggle"
    >
      <span class="truncate">{{ modelValue ? humanize(modelValue) : placeholder }}</span>
      <CalendarClock class="h-3.5 w-3.5 shrink-0 text-faint" />
    </button>

    <button v-if="open" type="button" class="fixed inset-0 z-20 cursor-default" tabindex="-1" @click="open = false" />
    <Transition
      enter-active-class="transition ease-emphasized-out duration-150"
      enter-from-class="opacity-0 scale-[0.97] -translate-y-0.5"
      enter-to-class="opacity-100 scale-100 translate-y-0"
      leave-active-class="transition ease-out duration-100"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-[0.97] -translate-y-0.5"
    >
      <div
        v-if="open"
        class="absolute z-30 mt-2 w-[19rem] max-w-[calc(100vw-2rem)] rounded-xl bg-overlay p-3 shadow-popover"
        :class="align === 'end' ? 'right-0 origin-top-right' : 'left-0 origin-top-left'"
      >
        <div class="mb-2 flex flex-wrap gap-1.5">
          <button
            v-for="preset in PRESETS"
            :key="preset.label"
            type="button"
            class="rounded-full border px-2.5 py-1 text-xs text-muted transition-colors hover:border-accent/50 hover:text-foreground"
            @click="applyPreset(preset.days)"
          >
            {{ preset.label }}
          </button>
        </div>

        <div class="mb-1 flex items-center justify-between">
          <button
            type="button"
            class="grid h-7 w-7 place-items-center rounded-md text-faint hover:bg-elevated hover:text-foreground"
            @click="shiftMonth(-1)"
          >
            <ChevronLeft class="h-4 w-4" />
          </button>
          <span class="text-[13px] font-semibold text-foreground">{{ monthLabel(view) }}</span>
          <button
            type="button"
            class="grid h-7 w-7 place-items-center rounded-md text-faint hover:bg-elevated hover:text-foreground"
            @click="shiftMonth(1)"
          >
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>

        <div class="grid grid-cols-7 gap-y-0.5 text-center">
          <span v-for="d in WEEKDAYS" :key="d" class="py-1 text-[11px] font-medium text-faint">{{ d }}</span>
          <button
            v-for="day in monthGrid(view)"
            :key="day.key"
            type="button"
            class="mx-auto grid h-8 w-8 place-items-center rounded-md text-[13px] tabular-nums transition-colors"
            :class="[
              day.inMonth ? 'text-foreground hover:bg-elevated' : 'text-faint/50 hover:bg-elevated',
              modelValue && day.key === modelValue.slice(0, 10)
                ? '!bg-accent font-semibold !text-white'
                : day.isToday
                  ? 'border border-accent/40'
                  : '',
            ]"
            @click="pickDay(day.date)"
          >
            {{ day.date.getDate() }}
          </button>
        </div>

        <div class="mt-2 flex items-center justify-between border-t pt-2">
          <div class="flex items-center gap-1">
            <Select
              :model-value="selected?.getHours() ?? 9"
              :options="HOURS"
              size="sm"
              class="w-16 tabular-nums"
              aria-label="Hour"
              @update:model-value="setTime('hours', $event)"
            />
            <span class="text-sm text-faint">:</span>
            <Select
              :model-value="selected?.getMinutes() ?? 0"
              :options="MINUTES"
              size="sm"
              class="w-16 tabular-nums"
              aria-label="Minutes"
              @update:model-value="setTime('minutes', $event)"
            />
          </div>
          <div class="flex gap-1">
            <Button variant="ghost" size="sm" type="button" @click="emit('update:modelValue', '')">Clear</Button>
            <Button variant="secondary" size="sm" type="button" @click="open = false">Done</Button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>
