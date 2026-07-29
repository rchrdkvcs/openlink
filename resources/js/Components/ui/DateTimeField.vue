<script setup lang="ts">
import { CalendarClock, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import { addDays, fromInputValue, humanize, monthGrid, monthLabel, toInputValue, WEEKDAYS } from '@/lib/datetime';

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

function setTime(part: 'hours' | 'minutes', raw: string) {
  const next = new Date(selected.value ?? new Date());
  if (part === 'hours') next.setHours(Number(raw));
  else next.setMinutes(Number(raw));
  emit('update:modelValue', toInputValue(next));
}

function applyPreset(days: number) {
  const d = addDays(new Date(), days);
  d.setHours(9, 0, 0, 0);
  emit('update:modelValue', toInputValue(d));
  view.value = d;
}

const HOURS = Array.from({ length: 24 }, (_, i) => i);
const MINUTES = Array.from({ length: 12 }, (_, i) => i * 5);
</script>

<template>
  <!-- Escape closes the popover without bubbling to the drawer's document listener. -->
  <div class="relative" @keydown.escape.stop="open = false">
    <button
      type="button"
      class="flex h-9 w-full items-center justify-between gap-2 rounded-md border bg-surface px-3 text-sm transition-colors hover:border-border-strong focus-visible:border-accent/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
      :class="modelValue ? 'text-foreground' : 'text-faint'"
      @click="toggle"
    >
      <span class="truncate">{{ modelValue ? humanize(modelValue) : placeholder }}</span>
      <CalendarClock class="h-3.5 w-3.5 shrink-0 text-faint" />
    </button>

    <template v-if="open">
      <button type="button" class="fixed inset-0 z-20 cursor-default" tabindex="-1" @click="open = false" />
      <div
        class="absolute z-30 mt-2 w-[19rem] max-w-[calc(100vw-2rem)] rounded-xl bg-overlay p-3 shadow-popover"
        :class="align === 'end' ? 'right-0' : 'left-0'"
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
            <select
              class="h-8 w-auto !py-0 !pr-7 text-[13px] tabular-nums"
              :value="selected?.getHours() ?? 9"
              @change="setTime('hours', ($event.target as HTMLSelectElement).value)"
            >
              <option v-for="h in HOURS" :key="h" :value="h">{{ String(h).padStart(2, '0') }}</option>
            </select>
            <span class="text-sm text-faint">:</span>
            <select
              class="h-8 w-auto !py-0 !pr-7 text-[13px] tabular-nums"
              :value="selected?.getMinutes() ?? 0"
              @change="setTime('minutes', ($event.target as HTMLSelectElement).value)"
            >
              <option v-for="m in MINUTES" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
            </select>
          </div>
          <div class="flex gap-1">
            <Button variant="ghost" size="sm" type="button" @click="emit('update:modelValue', '')">Clear</Button>
            <Button variant="secondary" size="sm" type="button" @click="open = false">Done</Button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
