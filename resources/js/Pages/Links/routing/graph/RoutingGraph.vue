<script setup lang="ts">
import { Maximize, Minus, Plus } from '@lucide/vue';
import { Background } from '@vue-flow/background';
import { type Edge, MarkerType, type Node, type NodeDragEvent, useVueFlow, VueFlow } from '@vue-flow/core';
import { computed, nextTick, ref } from 'vue';

import '@vue-flow/core/dist/style.css';
import Button from '@/Components/ui/Button.vue';
import type { GraphEdge, GraphNode } from '@/lib/routing/graph';
import { ruleStatus } from '@/lib/routing/preview';

import RoutingGraphNode from './RoutingGraphNode.vue';

const props = defineProps<{
  nodes: GraphNode[];
  edges: GraphEdge[];
  selectedId: string | null;
  canEdit: boolean;
}>();

const emit = defineEmits<{
  select: [id: string | null];
  drop: [id: string, y: number];
  move: [id: string, direction: -1 | 1];
}>();

const { fitView, updateNode, zoomIn, zoomOut, viewport, setViewport } = useVueFlow('routing-graph');

const canvas = ref<HTMLElement | null>(null);

const REORDER_HINT_ID = 'routing-graph-reorder-hint';
const CONFIG_HINT_ID = 'routing-graph-config-hint';

const EDGE_LABELS = { flow: '', match: 'Match', next: 'No match' } as const;

function pathLabel(node: GraphNode): string {
  if (node.data.path !== 'taken' || node.type === 'entry' || node.type === 'rule') return '';

  return ', chosen by the test';
}

function nodeLabel(node: GraphNode): string {
  const selected = node.id === props.selectedId ? ', selected' : '';

  switch (node.type) {
    case 'entry':
      return 'Incoming visit';
    case 'rule': {
      const parts = [`Routing rule ${node.data.priority}`, node.data.name, ...node.data.conditions];
      if (!node.data.enabled) parts.push('disabled');
      if (node.data.status) parts.push(`test result: ${ruleStatus(node.data.status).label}`);
      if (node.data.errors.length) parts.push(`problems: ${node.data.errors.join(' ')}`);

      return parts.join(', ') + selected;
    }
    case 'default':
      return `Default destination, ${node.data.url || 'not set'}${node.data.errors.length ? `, problem: ${node.data.errors[0]}` : ''}${pathLabel(node)}${selected}`;
    default:
      return `${node.data.label}, ${node.data.url || node.data.hint || 'no destination'}${node.data.share !== null ? `, ${node.data.share}%` : ''}${node.data.enabled ? '' : ', off'}${pathLabel(node)}${selected}`;
  }
}

const flowNodes = computed<Node[]>(() =>
  props.nodes.map((node) => ({
    ...node,
    position: { ...node.position },
    type: 'routing',
    data: { ...node.data, kind: node.type },
    selected: node.id === props.selectedId,
    draggable: props.canEdit && node.type === 'rule',
    connectable: false,
    focusable: node.type !== 'entry',
    selectable: node.type !== 'entry',
    ariaLabel: nodeLabel(node),
    domAttributes:
      node.type === 'entry'
        ? undefined
        : {
            role: 'button',
            'aria-haspopup': 'dialog',
            'aria-describedby': props.canEdit && node.type === 'rule' ? REORDER_HINT_ID : CONFIG_HINT_ID,
          },
  })),
);

const flowEdges = computed<Edge[]>(() =>
  props.edges.map((edge) => ({
    id: edge.id,
    source: edge.source,
    target: edge.target,
    sourceHandle: edge.sourceHandle,
    targetHandle: edge.kind === 'next' ? 'top' : 'left',
    type: 'smoothstep',
    label: EDGE_LABELS[edge.kind] || undefined,
    focusable: false,
    selectable: false,
    markerEnd: { type: MarkerType.ArrowClosed, width: 14, height: 14 },
    class: [
      'routing-edge',
      `routing-edge--${edge.kind}`,
      `routing-edge--${edge.data.path}`,
      edge.data.muted ? 'routing-edge--muted' : '',
    ].join(' '),
  })),
);

function reducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function fitAll() {
  fitView({ padding: 0.15, maxZoom: 1, duration: reducedMotion() ? 0 : 200 });
}

let initialized = false;

function initializeGraph() {
  if (initialized) return;
  initialized = true;
  if ((canvas.value?.clientWidth ?? 0) < 640) {
    const first =
      props.nodes.find((node) => node.type === 'rule') ?? props.nodes.find((node) => node.type === 'default');
    if (first) {
      const width = canvas.value?.clientWidth ?? 358;
      const zoom = Math.min(1, (width - 32) / 288);
      setViewport({ x: (width - 288 * zoom) / 2 - first.position.x * zoom, y: 48 - first.position.y * zoom, zoom });
    }
  } else fitAll();
}

async function focusNode(id: string) {
  await nextTick();
  const node = document.querySelector<HTMLElement>(`.vue-flow__node[data-id="${id}"]`);
  const bounds = node?.getBoundingClientRect();
  const frame = canvas.value?.getBoundingClientRect();
  if (
    bounds &&
    frame &&
    (bounds.left < frame.left + 16 ||
      bounds.right > frame.right - 16 ||
      bounds.top < frame.top + 16 ||
      bounds.bottom > frame.bottom - 60)
  ) {
    await fitView({ nodes: [id], padding: 0.5, minZoom: 0.6, maxZoom: 1, duration: 0 });
  }
  node?.focus({ preventScroll: true });
}

defineExpose({ focusNode });

const ARROWS: Record<string, -1 | 1> = { ArrowUp: -1, ArrowDown: 1 };

function onKeydown(event: KeyboardEvent) {
  const id = (event.target as HTMLElement | null)?.closest<HTMLElement>('.vue-flow__node')?.dataset.id;
  if (!id) return;

  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    emit('select', id);
    return;
  }

  if (event.key.startsWith('Arrow')) {
    event.preventDefault();
    event.stopPropagation();
    if (props.canEdit && event.altKey && ARROWS[event.key] && id.startsWith('rule-'))
      emit('move', id, ARROWS[event.key]);
  }
}

function onDragStop(event: NodeDragEvent) {
  const { id, position } = event.node;
  emit('drop', id, position.y);
  nextTick(() => {
    const laidOut = props.nodes.find((node) => node.id === id);
    if (laidOut) updateNode(id, { position: { ...laidOut.position } });
  });
}
</script>

<template>
  <div ref="canvas" class="routing-graph absolute inset-0" @keydown.capture="onKeydown">
    <p :id="CONFIG_HINT_ID" class="sr-only">Press Enter or Space to open node configuration.</p>
    <p :id="REORDER_HINT_ID" class="sr-only">
      Press Enter to edit. Press Alt with the Up or Down arrow key to change the priority.
    </p>
    <VueFlow
      id="routing-graph"
      :nodes="flowNodes"
      :edges="flowEdges"
      :disable-keyboard-a11y="true"
      :nodes-connectable="false"
      :edges-updatable="false"
      :nodes-draggable="canEdit"
      :elements-selectable="true"
      :min-zoom="0.15"
      :max-zoom="1.5"
      :delete-key-code="null"
      :selection-key-code="null"
      :multi-selection-key-code="null"
      :pan-on-scroll="true"
      :zoom-on-scroll="false"
      :zoom-on-double-click="false"
      :auto-pan-on-node-drag="true"
      @nodes-initialized="initializeGraph"
      aria-label="Routing graph"
      @node-click="emit('select', $event.node.id)"
      @pane-click="emit('select', null)"
      @node-drag-stop="onDragStop"
    >
      <template #node-routing="nodeProps">
        <RoutingGraphNode v-bind="nodeProps" />
      </template>
      <Background :gap="20" :size="1" />
    </VueFlow>
    <div
      class="absolute bottom-3 left-3 flex items-center gap-1 rounded-xl border bg-surface p-1 shadow-sm"
      role="group"
      aria-label="Graph navigation"
    >
      <Button
        type="button"
        variant="ghost"
        class="w-8 px-0"
        aria-label="Zoom out"
        :disabled="viewport.zoom <= 0.15"
        @click="zoomOut()"
        ><Minus
      /></Button>
      <span class="w-10 text-center text-xs tabular-nums text-muted" aria-live="polite"
        >{{ Math.round(viewport.zoom * 100) }}%</span
      >
      <Button
        type="button"
        variant="ghost"
        class="w-8 px-0"
        aria-label="Zoom in"
        :disabled="viewport.zoom >= 1.5"
        @click="zoomIn()"
        ><Plus
      /></Button>
      <Button type="button" variant="ghost" aria-label="Fit entire routing graph" @click="fitAll"
        ><Maximize />Fit</Button
      >
    </div>
  </div>
</template>

<style>
.routing-graph .vue-flow__background pattern circle {
  fill: hsl(var(--border-strong));
}

.routing-graph .vue-flow__node {
  border-radius: 0.75rem;
  cursor: pointer;
}

.routing-graph .vue-flow__node[data-id='entry'] {
  cursor: default;
}

@media (prefers-reduced-motion: reduce) {
  .routing-graph * {
    transition: none !important;
  }
}

.routing-graph .vue-flow__node:focus-visible {
  outline: 2px solid hsl(var(--accent));
  outline-offset: 3px;
}

.routing-graph .vue-flow__edge-path {
  stroke: hsl(var(--border-strong));
  stroke-width: 1.5;
  transition:
    stroke 200ms cubic-bezier(0.23, 1, 0.32, 1),
    opacity 200ms cubic-bezier(0.23, 1, 0.32, 1);
}

.routing-graph .routing-edge--match .vue-flow__edge-path {
  stroke: hsl(var(--muted));
}

.routing-graph .routing-edge--next .vue-flow__edge-path {
  stroke-dasharray: 5 4;
}

.routing-graph .routing-edge--muted .vue-flow__edge-path {
  opacity: 0.4;
}

.routing-graph .routing-edge--taken .vue-flow__edge-path {
  stroke: hsl(var(--accent));
  stroke-width: 2.5;
  stroke-dasharray: none;
  opacity: 1;
}

.routing-graph .routing-edge--dimmed {
  opacity: 0.3;
}

.routing-graph .vue-flow__edge-textbg {
  fill: hsl(var(--canvas));
}

.routing-graph .vue-flow__edge-text {
  fill: hsl(var(--muted));
  font-size: 11px;
}

.routing-graph .vue-flow__arrowhead polyline {
  stroke: hsl(var(--border-strong));
  fill: hsl(var(--border-strong));
}
</style>
