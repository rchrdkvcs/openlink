<script setup lang="ts">
import { CalendarClock, X } from '@lucide/vue';
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import {
  changeOperator,
  hasValueInput,
  isZoned,
  rangeInputType,
  rangeValue,
  type Routing,
  scalarValue,
  valueInputType,
  valuePlaceholder,
} from '@/lib/routing';
import type { RoutingCondition } from '@/types/shortLinks';

const props = defineProps<{ condition: RoutingCondition; position: number; routing: Routing }>();

const emit = defineEmits<{ remove: [] }>();

const valueOptions = computed(() => props.routing.valueOptions(props.condition));
const label = (part: string) => `Condition ${props.position} ${part}`;

function setValue(value: unknown) {
  props.condition.value = String(value ?? '');
}
</script>

<template>
  <div
    class="@xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.4fr)_1.75rem] grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_1.75rem] items-center gap-2"
  >
    <Select
      :model-value="condition.type"
      :options="routing.schema.conditionTypes"
      :aria-label="label('field')"
      @update:model-value="routing.changeConditionType(condition, $event)"
    />
    <Select
      :model-value="condition.operator"
      :options="routing.operators(condition)"
      :aria-label="label('operator')"
      @update:model-value="changeOperator(condition, $event)"
    />
    <template v-if="hasValueInput(condition)">
      <div v-if="condition.operator === 'between'" class="@xl:col-span-3 col-span-2 grid min-w-0 grid-cols-2 gap-2">
        <Input
          v-model="rangeValue(condition).from"
          :type="rangeInputType(condition.type)"
          :aria-label="label('start')"
        />
        <Input v-model="rangeValue(condition).to" :type="rangeInputType(condition.type)" :aria-label="label('end')" />
      </div>
      <Select
        v-else-if="valueOptions"
        class="@xl:col-span-1 col-span-2"
        :model-value="scalarValue(condition)"
        :options="valueOptions"
        :aria-label="label('value')"
        @update:model-value="setValue"
      />
      <Input
        v-else
        :model-value="scalarValue(condition)"
        class="@xl:col-span-1 col-span-2"
        :type="valueInputType(condition.type)"
        :placeholder="valuePlaceholder(condition.type)"
        :aria-label="label('value')"
        @update:model-value="setValue"
      />
    </template>
    <span v-else class="@xl:col-span-1 col-span-2 px-2.5 text-xs text-faint">No value needed</span>
    <label
      v-if="isZoned(routing.schema, condition.type)"
      class="@xl:col-span-3 col-span-2 flex min-w-0 items-center gap-2 text-xs text-muted"
    >
      <CalendarClock class="h-3.5 w-3.5 shrink-0 text-faint" />
      Timezone
      <Input v-model="condition.timezone" size="sm" class="max-w-56 flex-1" placeholder="Europe/Paris" />
    </label>
    <Button
      type="button"
      variant="ghost"
      size="sm"
      class="@xl:col-start-4 col-start-3 row-start-1 w-7 px-0 text-faint hover:text-danger"
      :aria-label="`Remove condition ${position}`"
      @click="emit('remove')"
      ><X
    /></Button>
  </div>
</template>
