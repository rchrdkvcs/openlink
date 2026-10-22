<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ChevronDown, FlaskConical, Plus, Workflow, X } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import SaveBar from '@/Components/ui/SaveBar.vue';
import { normalizeUrl } from '@/lib/links';
import { cloneRules, createRouting, remapRuleErrors, type RuleListChange, ruleErrors } from '@/lib/routing';
import { buildRoutingGraph, nodeRuleIndex, reorderRule, ruleNodeId } from '@/lib/routing/graph';
import { newPreviewContext, type RoutingPreview } from '@/lib/routing/preview';
import { useShell } from '@/lib/shell';
import { toast } from '@/lib/toast';
import { useSaveShortcut } from '@/lib/useSaveShortcut';
import type { RoutingRuleDraft, RoutingSchema, ShortLink } from '@/types/shortLinks';

import RoutingGraph from './graph/RoutingGraph.vue';
import type { RoutingSaveReceipt } from './inspectorDraft';
import { dropPriority, invalidNode, ruleDestination } from './nodeSelection';
import { presetIcon } from './presetIcons';
import RoutingDialog from './RoutingDialog.vue';
import RoutingNodeEditor from './RoutingNodeEditor.vue';
import RoutingPreviewPanel from './RoutingPreviewPanel.vue';

const props = defineProps<{ link: ShortLink; routingSchema: RoutingSchema; inspectorDirty?: boolean }>();
const emit = defineEmits<{ close: []; saved: [receipt: RoutingSaveReceipt] }>();

const { canEdit } = useShell();

type RoutingForm = { destination_url: string; routing_rules: RoutingRuleDraft[]; expected_revision: number };

function formFrom(link: ShortLink): RoutingForm {
  return {
    destination_url: link.destination_url,
    routing_rules: cloneRules(link.routing_rules ?? []),
    expected_revision: link.revision,
  };
}

const form = useForm<RoutingForm>(formFrom(props.link));
const routing = computed(() => createRouting(props.routingSchema));

const selectedId = ref<string | null>(null);
const nodeOpen = ref(false);
const testOpen = ref(false);
const confirmClose = ref(false);
const preview = ref<RoutingPreview | null>(null);
const previewContext = ref(newPreviewContext(Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'));
const previewRequestedTime = ref('');
const graphView = ref<InstanceType<typeof RoutingGraph> | null>(null);
const announcement = ref('');
let leaving = false;

function loadSaved(link: ShortLink) {
  const index = selectedRuleIndex.value;
  nodeOpen.value = false;
  preview.value = null;
  form.defaults(formFrom(link));
  form.reset();
  form.clearErrors();
  const rule = index === null ? undefined : form.routing_rules[index];
  selectedId.value = rule ? ruleNodeId(rule, index!) : selectedId.value === 'default' ? 'default' : null;
}

watch(
  () => props.link.id,
  () => loadSaved(props.link),
);

const graph = computed(() =>
  buildRoutingGraph(form.routing_rules, form.destination_url, {
    schema: props.routingSchema,
    errors: form.errors,
    preview: preview.value,
  }),
);

const selectedNode = computed(() => graph.value.nodes.find((node) => node.id === selectedId.value) ?? null);

const selectedRuleIndex = computed(() => {
  const node = selectedNode.value;
  if (!node || node.type === 'entry' || node.type === 'default') return null;

  return node.data.index;
});

const errorCount = computed(() => Object.keys(form.errors).length);

const draft = computed(() =>
  canEdit.value ? { destination_url: form.destination_url, routing_rules: form.routing_rules } : null,
);

function select(id: string | null) {
  selectedId.value = id;
  nodeOpen.value = Boolean(id && id !== 'entry');
}

function replaceRules(rules: RoutingRuleDraft[]) {
  const errors = remapRuleErrors(form.errors, form.routing_rules, rules);
  form.routing_rules = rules;
  form.clearErrors();
  form.setError(errors as Record<string, string>);
}

function apply(change: RuleListChange | null, configure = false) {
  if (!change) return;
  replaceRules(change.rules);
  selectedId.value = change.focus === null ? null : ruleNodeId(change.rules[change.focus], change.focus);
  if (configure) nodeOpen.value = selectedId.value !== null;
  if (change.focus !== null) announcement.value = `Routing rule ${change.focus + 1} of ${change.rules.length}.`;
}

function onDrop(id: string, y: number) {
  const from = nodeRuleIndex(id, form.routing_rules);
  if (from === null || !canEdit.value) return;

  apply(reorderRule(form.routing_rules, from, dropPriority(graph.value.nodes, y)));
}

function onMove(id: string, direction: -1 | 1) {
  const from = nodeRuleIndex(id, form.routing_rules);
  if (from === null) return;

  apply(routing.value.move(form.routing_rules, from, direction));
  graphView.value?.focusNode(id);
}

function save() {
  if (!canEdit.value || !form.isDirty || form.processing || confirmClose.value) return;

  form
    .transform((data) => ({ ...data, destination_url: normalizeUrl(data.destination_url) }))
    .put(route('short-links.routing.update', props.link.id), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: (page) => {
        const receipt = (page.flash as { routingSaved?: RoutingSaveReceipt }).routingSaved;
        if (receipt) emit('saved', receipt);
        loadSaved(props.link);
        toast({ title: 'Routing saved', tone: 'success', duration: 2000 });
      },
      onError: (errors) => {
        const id = invalidNode(graph.value.nodes, errors);
        if (id) select(id);
      },
    });
}

function discard() {
  loadSaved(props.link);
  selectedId.value = null;
}

useSaveShortcut(save);

const LEAVE_MESSAGE = 'Leave without saving your routing changes?';

function warnBeforeUnload(event: BeforeUnloadEvent) {
  if (form.isDirty) event.preventDefault();
}

let stopGuard: (() => void) | null = null;

onMounted(() => {
  window.addEventListener('beforeunload', warnBeforeUnload);
  stopGuard = router.on('before', (event) => {
    const visit = event.detail.visit;
    const updatesLink = !visit.only.length || visit.only.some((key) => key === 'link' || key === 'links');
    if (visit.method === 'get' && updatesLink && !leaving && form.isDirty && !window.confirm(LEAVE_MESSAGE))
      event.preventDefault();
  });
});

onUnmounted(() => {
  window.removeEventListener('beforeunload', warnBeforeUnload);
  stopGuard?.();
});

watch(
  () => form.data(),
  () => {
    preview.value = null;
  },
  { deep: true },
);

const selectedVariantIndex = computed(() =>
  graph.value.nodes
    .filter((node) => node.type === 'variant' && node.data.index === selectedRuleIndex.value)
    .findIndex((node) => node.id === selectedId.value),
);

function closeNode() {
  nodeOpen.value = false;
}

function focusSelected() {
  if (selectedId.value) graphView.value?.focusNode(selectedId.value);
}

function removeSelected() {
  if (selectedRuleIndex.value === null) return;
  apply(routing.value.remove(form.routing_rules, selectedRuleIndex.value));
  closeNode();
}

function requestClose() {
  if (form.processing) return;
  if (form.isDirty) confirmClose.value = true;
  else finishClose();
}

function finishClose() {
  leaving = true;
  emit('close');
}

defineExpose({ requestClose });
</script>

<template>
  <section class="flex min-h-0 min-w-0 flex-col overflow-hidden bg-canvas" aria-label="Smart routing editor">
    <header class="flex shrink-0 flex-wrap items-center gap-3 border-b px-4 py-3 sm:px-5">
      <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border bg-surface text-accent">
        <Workflow class="h-4 w-4" />
      </span>
      <div class="min-w-0 flex-1">
        <h1 class="text-[15px] font-semibold">Smart routing</h1>
        <p class="truncate text-xs text-muted" :title="link.short_url">{{ link.short_url }}</p>
      </div>
      <Button
        type="button"
        variant="ghost"
        aria-label="Close routing editor"
        :disabled="form.processing"
        @click="requestClose"
      >
        <X />
      </Button>
    </header>

    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b px-4 py-2 sm:px-5">
      <p class="text-xs text-muted">
        {{ form.routing_rules.length }} {{ form.routing_rules.length === 1 ? 'rule' : 'rules' }} · First match wins
      </p>
      <div class="flex flex-wrap items-center gap-2">
        <Menu v-if="form.routing_rules.length" align="end" width="w-64">
          <template #trigger>
            <Button type="button" variant="ghost" size="sm">Find node<ChevronDown /></Button>
          </template>
          <MenuItem
            v-for="(rule, index) in form.routing_rules"
            :key="ruleNodeId(rule, index)"
            @select="select(ruleNodeId(rule, index))"
          >
            <span class="truncate">{{ index + 1 }}. {{ rule.name || 'Untitled routing rule' }}</span>
          </MenuItem>
          <MenuItem @select="select('default')">Default destination</MenuItem>
        </Menu>
        <Button type="button" variant="secondary" size="sm" @click="testOpen = true"
          ><FlaskConical />Test routing</Button
        >
        <Menu v-if="canEdit" align="end" width="w-56">
          <template #trigger>
            <Button type="button" size="sm"><Plus />Add rule<ChevronDown /></Button>
          </template>
          <MenuItem
            v-for="preset in routingSchema.presets"
            :key="preset.kind"
            :icon="presetIcon(preset.kind)"
            @select="apply(routing.addPreset(form.routing_rules, preset.kind), true)"
          >
            {{ preset.label }}
          </MenuItem>
        </Menu>
      </div>
    </div>

    <p v-if="!canEdit" class="shrink-0 border-b px-5 py-2 text-xs text-muted">
      View and test this routing. Only editors can change it.
    </p>
    <p v-if="inspectorDirty" class="shrink-0 border-b px-5 py-2 text-xs text-muted">
      Your link details draft is kept in the drawer. Routing uses the saved default destination until you change it
      here.
    </p>
    <p v-if="errorCount" role="alert" class="shrink-0 border-b px-5 py-2 text-xs text-danger">
      {{
        form.errors.expected_revision ||
        form.errors.routing_rules ||
        `${errorCount} ${errorCount === 1 ? 'field needs' : 'fields need'} attention. Open the highlighted nodes to review.`
      }}
    </p>

    <div class="relative min-h-[240px] flex-1 bg-background">
      <RoutingGraph
        ref="graphView"
        :nodes="graph.nodes"
        :edges="graph.edges"
        :selected-id="selectedId"
        :can-edit="canEdit"
        @select="select"
        @drop="onDrop"
        @move="onMove"
      />
      <div
        v-if="!form.routing_rules.length"
        class="pointer-events-none absolute inset-x-4 top-4 mx-auto max-w-sm rounded-xl border bg-surface/95 px-4 py-3 text-center shadow-sm"
      >
        <p class="text-[13px] font-medium">Every visitor goes to the default destination</p>
        <p class="mt-1 text-xs text-muted">
          {{
            canEdit
              ? 'Add a routing rule to choose a destination by audience or split traffic.'
              : 'No routing rules are configured.'
          }}
        </p>
      </div>
      <div
        v-if="preview"
        class="absolute left-3 top-3 flex max-w-[calc(100%-1.5rem)] items-center gap-2 rounded-lg border bg-surface px-3 py-2 text-xs shadow-sm"
        role="status"
      >
        <span class="min-w-0 truncate"
          >Test path highlighted ·
          {{ preview.decision.is_default ? 'Default destination' : preview.decision.rule_name }}</span
        >
        <Button type="button" variant="ghost" class="w-7 px-0" aria-label="Clear test path" @click="preview = null"
          ><X
        /></Button>
      </div>
    </div>

    <footer
      class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t px-4 py-3 text-xs text-muted sm:px-5"
    >
      <p>Click a node to {{ canEdit ? 'configure it' : 'view details' }}. Drag the canvas to pan.</p>
      <p v-if="canEdit">Drag rules to reorder · Alt + ↑ / ↓</p>
    </footer>
    <div v-if="canEdit && (form.isDirty || form.processing)" class="shrink-0 px-3 pb-3">
      <SaveBar
        :dirty="form.isDirty"
        :processing="form.processing"
        :has-errors="errorCount > 0"
        @discard="discard"
        @save="save"
      />
    </div>
    <p class="sr-only" aria-live="polite">{{ announcement }}</p>

    <RoutingDialog
      :open="nodeOpen && selectedNode !== null"
      :title="
        selectedNode?.type === 'default'
          ? 'Default destination'
          : selectedNode?.type === 'rule'
            ? `Routing rule ${(selectedRuleIndex ?? 0) + 1}`
            : selectedNode?.type === 'variant'
              ? 'Split variant'
              : 'Routing destination'
      "
      @update:open="!$event && closeNode()"
      @closed="focusSelected"
    >
      <RoutingNodeEditor
        v-if="selectedNode"
        :key="selectedId ?? 'none'"
        v-model:default-destination="form.destination_url"
        :node="selectedNode"
        :rule="selectedRuleIndex === null ? null : form.routing_rules[selectedRuleIndex]"
        :variant-index="selectedVariantIndex"
        :is-last="selectedRuleIndex === form.routing_rules.length - 1"
        :routing="routing"
        :can-edit="canEdit"
        :errors="
          selectedRuleIndex === null
            ? Object.entries(form.errors).filter(([key]) => key === 'destination_url')
            : ruleErrors(form.errors, selectedRuleIndex)
        "
        @move="selectedRuleIndex !== null && apply(routing.move(form.routing_rules, selectedRuleIndex, $event))"
        @duplicate="selectedRuleIndex !== null && apply(routing.duplicate(form.routing_rules, selectedRuleIndex), true)"
        @destination="selectedRuleIndex !== null && select(ruleDestination(graph.nodes, selectedRuleIndex))"
        @rule="select(ruleNodeId(form.routing_rules[$event], $event))"
        @remove="removeSelected"
        @close="closeNode"
      />
    </RoutingDialog>

    <RoutingDialog :open="testOpen" title="Routing Preview" @update:open="testOpen = $event">
      <div class="min-h-0 overflow-y-auto overscroll-contain px-5 py-4">
        <RoutingPreviewPanel
          v-model:context="previewContext"
          v-model:result="preview"
          v-model:requested-time="previewRequestedTime"
          :link-id="link.id"
          :schema="routingSchema"
          :draft="draft"
          :unsaved="form.isDirty"
        />
      </div>
      <div class="flex shrink-0 justify-end border-t px-5 py-3">
        <Button type="button" @click="testOpen = false">Back to graph</Button>
      </div>
    </RoutingDialog>

    <RoutingDialog
      :open="confirmClose"
      alert
      title="Discard routing changes?"
      description="Your routing changes have not been saved. Keep editing or discard them before closing."
      @update:open="confirmClose = $event"
    >
      <div class="flex justify-end gap-2 p-5">
        <Button type="button" variant="secondary" @click="confirmClose = false">Keep editing</Button>
        <Button type="button" variant="danger" @click="finishClose">Discard and close</Button>
      </div>
    </RoutingDialog>
  </section>
</template>
