<script setup lang="ts">
import { CalendarClock, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, inject, ref, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Popover from '@/Components/ui/Popover.vue';
import Select from '@/Components/ui/Select.vue';
import SelectOption from '@/Components/ui/SelectOption.vue';
import { addDays, fromInputValue, humanize, monthGrid, monthLabel, toInputValue, WEEKDAYS } from '@/lib/datetime';
import { fieldContextKey } from '@/lib/select';

const props = withDefaults(
  defineProps<{
    modelValue: string;
    placeholder?: string;
    align?: 'start' | 'end';
  }>(),
  { placeholder: 'Pick a date…', align: 'start' },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const field = inject(fieldContextKey, undefined);
const open = ref(false);
const view = ref(new Date());

const selected = computed(() => fromInputValue(props.modelValue));

const PRESETS = [
  { label: 'Today 9:00', days: 0 },
  { label: 'Tomorrow', days: 1 },
  { label: 'In a week', days: 7 },
  { label: 'In a month', days: 30 },
];

watch(open, (value) => {
  if (value) view.value = selected.value ?? new Date();
});

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
const MINUTES = computed(() =>
  [...new Set([...Array.from({ length: 12 }, (_, i) => i * 5), selected.value?.getMinutes() ?? 0])].toSorted(
    (a, b) => a - b,
  ),
);
</script>

<template>
  <Popover v-model:open="open" :align="align" class="ui-popover-form w-[19rem] p-3" aria-label="Date and time">
    <template #trigger>
      <button
        type="button"
        :aria-labelledby="field?.labelId"
        :aria-describedby="field?.descriptionId"
        :aria-invalid="field?.invalid || undefined"
        class="ui-control flex h-9 w-full items-center justify-between gap-2 px-3"
        :class="modelValue ? 'text-foreground' : 'text-faint'"
      >
        <span class="truncate">{{ modelValue ? humanize(modelValue) : placeholder }}</span>
        <CalendarClock class="h-3.5 w-3.5 shrink-0 text-faint" />
      </button>
    </template>
    <div class="mb-2 flex flex-wrap gap-1.5">
      <button
        v-for="preset in PRESETS"
        :key="preset.label"
        type="button"
        class="rounded-xl border px-2.5 py-1 text-xs text-muted transition-colors hover:border-accent/50 hover:text-foreground"
        @click="applyPreset(preset.days)"
      >
        {{ preset.label }}
      </button>
    </div>

    <div class="mb-1 flex items-center justify-between">
      <button
        type="button"
        class="grid h-7 w-7 place-items-center rounded-md text-faint hover:bg-elevated hover:text-foreground"
        aria-label="Previous month"
        @click="shiftMonth(-1)"
      >
        <ChevronLeft class="h-4 w-4" />
      </button>
      <span class="text-[13px] font-semibold text-foreground">{{ monthLabel(view) }}</span>
      <button
        type="button"
        class="grid h-7 w-7 place-items-center rounded-md text-faint hover:bg-elevated hover:text-foreground"
        aria-label="Next month"
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
        :aria-label="day.date.toLocaleDateString(undefined, { dateStyle: 'full' })"
        :aria-pressed="day.key === modelValue.slice(0, 10)"
        @click="pickDay(day.date)"
      >
        {{ day.date.getDate() }}
      </button>
    </div>

    <div class="mt-2 flex items-center justify-between border-t pt-2">
      <div class="flex items-center gap-1">
        <Select
          class="h-8 w-16 tabular-nums"
          aria-label="Hours"
          :model-value="selected?.getHours() ?? 9"
          @update:model-value="setTime('hours', String($event))"
        >
          <SelectOption v-for="h in HOURS" :key="h" :value="h">{{ String(h).padStart(2, '0') }}</SelectOption>
        </Select>
        <span class="text-sm text-faint">:</span>
        <Select
          class="h-8 w-16 tabular-nums"
          aria-label="Minutes"
          :model-value="selected?.getMinutes() ?? 0"
          @update:model-value="setTime('minutes', String($event))"
        >
          <SelectOption v-for="m in MINUTES" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</SelectOption>
        </Select>
      </div>
      <div class="flex gap-1">
        <Button variant="ghost" size="sm" type="button" @click="emit('update:modelValue', '')">Clear</Button>
        <Button variant="secondary" size="sm" type="button" @click="open = false">Done</Button>
      </div>
    </div>
  </Popover>
</template>
