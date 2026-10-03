<script setup lang="ts">
import { ArrowDown, ArrowUp, ChevronRight, Copy, Ellipsis, Trash2 } from '@lucide/vue';

import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Switch from '@/Components/ui/Switch.vue';
import { type Routing, ruleName } from '@/lib/routing';
import type { RoutingRuleDraft } from '@/types/shortLinks';

import RoutingConditions from './RoutingConditions.vue';
import RoutingVariants from './RoutingVariants.vue';

defineProps<{
  rule: RoutingRuleDraft;
  index: number;
  isLast: boolean;
  open: boolean;
  errors: [string, string][];
  panelId: string;
  routing: Routing;
}>();

const emit = defineEmits<{ toggle: []; move: [direction: -1 | 1]; duplicate: []; remove: [] }>();

const ruleTypeOptions: { value: RoutingRuleDraft['type']; label: string }[] = [
  { value: 'conditional', label: 'Conditional' },
  { value: 'split_test', label: 'Split test' },
];
</script>

<template>
  <li class="min-w-0 rounded-xl border bg-surface" :class="errors.length > 0 && 'border-danger/50'">
    <div class="flex min-h-12 items-center gap-2 py-2 pe-2 ps-2">
      <button
        type="button"
        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-faint transition-colors duration-150 hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
        :aria-expanded="open"
        :aria-controls="panelId"
        :aria-label="`${open ? 'Collapse' : 'Expand'} ${ruleName(rule)}`"
        @click="emit('toggle')"
      >
        <ChevronRight
          class="ease-emphasized-out h-3.5 w-3.5 transition-transform duration-200"
          :class="open && 'rotate-90'"
        />
      </button>
      <span
        class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-md bg-elevated px-1 font-mono text-[11px] tabular-nums text-muted"
        :title="`Priority ${index + 1}`"
        >{{ index + 1 }}</span
      >
      <div class="min-w-0 flex-1 ps-1">
        <input
          v-if="open"
          v-model="rule.name"
          type="text"
          aria-label="Rule name"
          placeholder="Untitled routing rule"
          class="-ms-1.5 h-7 w-full min-w-0 rounded-md border border-transparent bg-transparent px-1.5 text-[13px] font-medium text-foreground outline-none transition-[background-color,border-color] duration-150 placeholder:text-faint hover:bg-elevated/60 focus-visible:border-accent/60 focus-visible:bg-elevated"
        />
        <button
          v-else
          type="button"
          class="block w-full min-w-0 rounded-md text-start outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
          :class="!rule.is_enabled && 'opacity-60'"
          :aria-expanded="false"
          :aria-controls="panelId"
          @click="emit('toggle')"
        >
          <span class="block truncate text-[13px] font-medium text-foreground">{{ ruleName(rule) }}</span>
          <span class="block truncate text-xs text-muted">{{
            rule.is_enabled ? routing.summary(rule) : 'Disabled · skipped when routing visitors'
          }}</span>
        </button>
      </div>
      <Switch v-model="rule.is_enabled" :aria-label="`Enable ${ruleName(rule)}`" />
      <Menu align="end" width="w-44">
        <template #trigger>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="w-7 shrink-0 px-0"
            :aria-label="`Actions for ${ruleName(rule)}`"
            ><Ellipsis
          /></Button>
        </template>
        <MenuItem :icon="ArrowUp" :disabled="index === 0" @select="emit('move', -1)">Move up</MenuItem>
        <MenuItem :icon="ArrowDown" :disabled="isLast" @select="emit('move', 1)">Move down</MenuItem>
        <MenuItem :icon="Copy" @select="emit('duplicate')">Duplicate</MenuItem>
        <MenuSeparator />
        <MenuItem :icon="Trash2" destructive @select="emit('remove')">Delete</MenuItem>
      </Menu>
    </div>

    <div v-if="open" :id="panelId" class="grid gap-5 border-t p-4">
      <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <span class="text-[13px] font-medium text-foreground">Rule type</span>
        <SegmentedControl
          size="sm"
          label="Rule type"
          :options="ruleTypeOptions"
          :model-value="rule.type"
          @update:model-value="routing.setRuleType(rule, $event)"
        />
      </div>

      <RoutingConditions :rule="rule" :routing="routing" />

      <section v-if="rule.type === 'conditional'" class="grid gap-2">
        <label :for="`${panelId}-destination`" class="flex min-h-7 items-center text-[13px] font-medium">
          Then <span class="ms-1 font-normal text-muted">redirect to</span>
        </label>
        <Input
          :id="`${panelId}-destination`"
          v-model="rule.destination_url"
          type="url"
          spellcheck="false"
          placeholder="https://example.com/landing"
        />
      </section>

      <RoutingVariants v-else :rule="rule" :routing="routing" />

      <div v-if="errors.length" role="alert" class="grid gap-1 rounded-lg bg-danger/10 px-3 py-2">
        <p v-for="[key, error] in errors" :key="key" class="text-xs text-danger">{{ error }}</p>
      </div>

      <p class="flex items-center gap-2 text-xs text-faint">
        <ArrowDown class="h-3.5 w-3.5 shrink-0" />
        Otherwise, continue to {{ isLast ? 'the default destination.' : 'the next rule.' }}
      </p>
    </div>
    <p v-else-if="errors.length" role="alert" class="border-t px-4 py-2 text-xs text-danger">
      This routing rule needs attention. Expand it to review the errors.
    </p>
  </li>
</template>
