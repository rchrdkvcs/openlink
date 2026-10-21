<script setup lang="ts">
import { CornerDownRight, MousePointerClick, Route, Shuffle, TriangleAlert } from '@lucide/vue';
import { Handle, Position } from '@vue-flow/core';
import { computed } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import { displayUrl } from '@/lib/links';
import type {
  DefaultNodeData,
  DestinationNodeData,
  EntryNodeData,
  GraphNodeKind,
  RuleNodeData,
} from '@/lib/routing/graph';
import { ruleStatus } from '@/lib/routing/preview';

type NodeData = (EntryNodeData | RuleNodeData | DestinationNodeData | DefaultNodeData) & { kind: GraphNodeKind };

const props = defineProps<{ id: string; data: NodeData; selected: boolean }>();

const rule = computed(() => (props.data.kind === 'rule' ? (props.data as RuleNodeData & { kind: 'rule' }) : null));
const destination = computed(() =>
  props.data.kind === 'destination' || props.data.kind === 'variant' ? (props.data as DestinationNodeData) : null,
);
const fallback = computed(() => (props.data.kind === 'default' ? (props.data as DefaultNodeData) : null));

const frame = computed(() => [
  'rounded-xl border bg-surface text-start shadow-sm transition-[opacity,border-color,box-shadow] duration-200 ease-[cubic-bezier(0.23,1,0.32,1)]',
  props.selected
    ? 'border-accent ring-2 ring-accent/30'
    : rule.value?.errors.length || fallback.value?.errors.length
      ? 'border-danger/60'
      : 'border-border-strong',
  props.data.path === 'taken' && !props.selected && 'border-accent/70',
  props.data.path === 'dimmed' && 'opacity-40',
]);
</script>

<template>
  <div v-if="data.kind === 'entry'" :class="frame" class="flex w-[200px] items-center gap-2.5 px-3.5 py-3">
    <MousePointerClick class="h-4 w-4 shrink-0 text-accent" />
    <span class="grid">
      <span class="text-[13px] font-medium text-foreground">Incoming visit</span>
      <span class="text-xs text-faint">Rules run top to bottom</span>
    </span>
    <Handle id="flow" type="source" :position="Position.Right" :connectable="false" />
  </div>

  <div v-else-if="rule" :class="frame" class="w-[288px]" :data-path="data.path">
    <Handle id="left" type="target" :position="Position.Left" :connectable="false" />
    <Handle id="top" type="target" :position="Position.Top" :connectable="false" />
    <div class="flex items-center gap-2 border-b px-3 py-2">
      <span
        class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-md bg-elevated px-1 font-mono text-[11px] tabular-nums text-muted"
        :aria-label="`Priority ${rule.priority}`"
        >{{ rule.priority }}</span
      >
      <component :is="rule.type === 'split_test' ? Shuffle : Route" class="h-3.5 w-3.5 shrink-0 text-faint" />
      <span class="min-w-0 flex-1 truncate text-[13px] font-medium text-foreground" :title="rule.name">{{
        rule.name
      }}</span>
      <Badge v-if="rule.status" :variant="ruleStatus(rule.status).tone">{{ ruleStatus(rule.status).label }}</Badge>
      <Badge v-else-if="!rule.enabled" variant="outline">Disabled</Badge>
    </div>
    <ul class="grid gap-1 px-3 py-2 text-xs text-muted">
      <li v-if="rule.conditions.length === 0" class="text-faint">Every visitor</li>
      <li v-for="(condition, index) in rule.conditions" :key="index" class="truncate" :title="condition">
        <span v-if="index > 0" class="me-1 text-faint">{{ rule.matchMode === 'all' ? 'and' : 'or' }}</span
        >{{ condition }}
      </li>
    </ul>
    <p v-if="rule.errors.length" class="flex items-center gap-1.5 border-t px-3 py-1.5 text-xs text-danger">
      <TriangleAlert class="h-3.5 w-3.5 shrink-0" />{{
        rule.errors.length === 1 ? rule.errors[0] : `${rule.errors.length} problems`
      }}
    </p>
    <Handle id="match" type="source" :position="Position.Right" :connectable="false" />
    <Handle id="next" type="source" :position="Position.Bottom" :connectable="false" />
  </div>

  <div v-else-if="destination" :class="frame" class="flex w-[280px] items-center gap-2.5 px-3 py-2.5">
    <Handle id="left" type="target" :position="Position.Left" :connectable="false" />
    <CornerDownRight class="h-3.5 w-3.5 shrink-0" :class="data.path === 'taken' ? 'text-accent' : 'text-faint'" />
    <span class="grid min-w-0 flex-1">
      <span class="flex items-center gap-1.5 text-xs text-faint">
        {{ destination.label }}
        <span v-if="!destination.enabled" class="text-faint">· off</span>
        <span v-if="data.path === 'taken'" class="font-medium text-accent">· chosen</span>
      </span>
      <span
        class="truncate text-[13px]"
        :class="destination.url ? 'text-foreground' : 'text-faint'"
        :title="destination.url"
        >{{ destination.url ? displayUrl(destination.url) : 'No destination yet' }}</span
      >
    </span>
    <span v-if="destination.share !== null" class="shrink-0 text-xs tabular-nums text-muted"
      >{{ destination.share }}%</span
    >
  </div>

  <div v-else-if="fallback" :class="frame" class="flex w-[288px] items-center gap-2.5 px-3 py-2.5">
    <Handle id="left" type="target" :position="Position.Left" :connectable="false" />
    <Handle id="top" type="target" :position="Position.Top" :connectable="false" />
    <CornerDownRight class="h-3.5 w-3.5 shrink-0" :class="data.path === 'taken' ? 'text-accent' : 'text-faint'" />
    <span class="grid min-w-0 flex-1">
      <span class="text-xs text-faint"
        >Default destination<span v-if="data.path === 'taken'" class="font-medium text-accent"> · chosen</span></span
      >
      <span
        class="truncate text-[13px]"
        :class="fallback.url ? 'text-foreground' : 'text-faint'"
        :title="fallback.url"
        >{{ fallback.url ? displayUrl(fallback.url) : 'No destination yet' }}</span
      >
      <span v-if="fallback.errors.length" class="flex items-center gap-1.5 text-xs text-danger"
        ><TriangleAlert class="h-3.5 w-3.5 shrink-0" />{{ fallback.errors[0] }}</span
      >
    </span>
  </div>
</template>

<style scoped>
:deep(.vue-flow__handle) {
  width: 8px;
  height: 8px;
  min-width: 0;
  min-height: 0;
  border: 1px solid hsl(var(--border-strong));
  background: hsl(var(--elevated));
}
</style>
