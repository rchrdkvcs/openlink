<script setup lang="ts">
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { type Edge, MarkerType, type Node, type NodeDragEvent, useVueFlow, VueFlow } from '@vue-flow/core';
import { computed, nextTick, watch } from 'vue';

import '@vue-flow/core/dist/style.css';
import '@vue-flow/controls/dist/style.css';

import type { GraphEdge, GraphNode } from '@/lib/routing/graph';

import RoutingGraphNode from './RoutingGraphNode.vue';

const props = defineProps<{
  nodes: GraphNode[];
  edges: GraphEdge[];
  selectedId: string | null;
  canEdit: boolean;
}>();

const emit = defineEmits<{ select: [id: string | null]; drop: [id: string, y: number] }>();

const { fitView } = useVueFlow('routing-graph');

const EDGE_LABELS = { flow: '', match: 'Match', next: 'No match' } as const;

function nodeLabel(node: GraphNode): string {
  switch (node.type) {
    case 'entry':
      return 'Incoming visit';
    case 'rule':
      return `Rule ${node.data.priority}, ${node.data.name}${node.data.enabled ? '' : ', disabled'}`;
    case 'default':
      return `Default destination, ${node.data.url || 'not set'}`;
    default:
      return `${node.data.label}, ${node.data.url || 'no destination'}`;
  }
}

const flowNodes = computed<Node[]>(() =>
  props.nodes.map((node) => ({
    ...node,
    type: 'routing',
    data: { ...node.data, kind: node.type },
    selected: node.id === props.selectedId,
    draggable: props.canEdit && node.type === 'rule',
    connectable: false,
    ariaLabel: nodeLabel(node),
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

watch(
  () => props.nodes.length,
  () => nextTick(() => fitView({ padding: 0.2, duration: 200 })),
);

function onKeydown(event: KeyboardEvent) {
  if (event.key !== 'Enter' && event.key !== ' ') return;
  const id = (event.target as HTMLElement | null)?.closest<HTMLElement>('.vue-flow__node')?.dataset.id;
  if (!id) return;
  event.preventDefault();
  emit('select', id);
}

function onDragStop(event: NodeDragEvent) {
  emit('drop', event.node.id, event.node.position.y);
}
</script>

<template>
  <div class="routing-graph relative h-full min-h-[420px] w-full" @keydown="onKeydown">
    <VueFlow
      id="routing-graph"
      :nodes="flowNodes"
      :edges="flowEdges"
      :nodes-connectable="false"
      :edges-updatable="false"
      :nodes-draggable="canEdit"
      :elements-selectable="true"
      :min-zoom="0.3"
      :max-zoom="1.5"
      :delete-key-code="null"
      :selection-key-code="null"
      :multi-selection-key-code="null"
      fit-view-on-init
      aria-label="Routing graph"
      @node-click="emit('select', $event.node.id)"
      @pane-click="emit('select', null)"
      @node-drag-stop="onDragStop"
    >
      <template #node-routing="nodeProps">
        <RoutingGraphNode v-bind="nodeProps" />
      </template>
      <Background :gap="20" :size="1" pattern-color="hsl(var(--border-strong))" />
      <Controls :show-interactive="false" position="bottom-left" />
    </VueFlow>
  </div>
</template>

<style>
.routing-graph .vue-flow__node {
  border-radius: 0.75rem;
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

.routing-graph .vue-flow__controls {
  box-shadow: none;
  border: 1px solid hsl(var(--border));
  border-radius: 0.5rem;
  overflow: hidden;
}

.routing-graph .vue-flow__controls-button {
  background: hsl(var(--surface));
  border-bottom: 1px solid hsl(var(--border));
  color: hsl(var(--muted));
  fill: currentColor;
}

.routing-graph .vue-flow__controls-button:hover {
  background: hsl(var(--elevated));
  color: hsl(var(--foreground));
}

.routing-graph .vue-flow__controls-button svg {
  fill: currentColor;
}

.routing-graph .vue-flow__arrowhead polyline {
  stroke: hsl(var(--border-strong));
  fill: hsl(var(--border-strong));
}
</style>
