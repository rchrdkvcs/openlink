<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, ListOrdered, Plus, Workflow } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SaveBar from '@/Components/ui/SaveBar.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { normalizeUrl } from '@/lib/links';
import {
  cloneRules,
  createRouting,
  firstInvalidRule,
  remapRuleErrors,
  type RuleListChange,
  ruleErrors,
} from '@/lib/routing';
import { buildRoutingGraph, dropIndex, nodeRuleIndex, reorderRule, ruleNodeId } from '@/lib/routing/graph';
import type { RoutingPreview } from '@/lib/routing/preview';
import { useShell } from '@/lib/shell';
import { toast } from '@/lib/toast';
import { useSaveShortcut } from '@/lib/useSaveShortcut';
import type { RoutingRuleDraft, RoutingSchema, ShortLink } from '@/types/shortLinks';

import RoutingGraph from './routing/graph/RoutingGraph.vue';
import { presetIcon } from './routing/presetIcons';
import RoutingPreviewPanel from './routing/RoutingPreviewPanel.vue';
import RoutingRuleCard from './routing/RoutingRuleCard.vue';
import RoutingRulesEditor from './routing/RoutingRulesEditor.vue';

const props = defineProps<{ link: ShortLink; routingSchema: RoutingSchema }>();

const { canEdit } = useShell();

type RoutingForm = { destination_url: string; routing_rules: RoutingRuleDraft[] };

function formFrom(link: ShortLink): RoutingForm {
  return { destination_url: link.destination_url, routing_rules: cloneRules(link.routing_rules ?? []) };
}

const form = useForm<RoutingForm>(formFrom(props.link));
const routing = computed(() => createRouting(props.routingSchema));

const view = ref<'graph' | 'list'>('graph');
const panel = ref<'edit' | 'test'>(canEdit.value ? 'edit' : 'test');
const selectedId = ref<string | null>(null);
const preview = ref<RoutingPreview | null>(null);

const views = [
  { value: 'graph' as const, label: 'Graph', icon: Workflow },
  { value: 'list' as const, label: 'List', icon: ListOrdered },
];
const panels = [
  { value: 'edit' as const, label: canEdit.value ? 'Edit' : 'Details' },
  { value: 'test' as const, label: 'Test' },
];

function loadSaved(link: ShortLink) {
  const index = selectedRuleIndex.value;
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
  if (id && id !== 'entry') panel.value = 'edit';
}

function replaceRules(rules: RoutingRuleDraft[]) {
  const errors = remapRuleErrors(form.errors, form.routing_rules, rules);
  form.routing_rules = rules;
  form.clearErrors();
  form.setError(errors as Record<string, string>);
}

function apply(change: RuleListChange | null) {
  if (!change) return;
  replaceRules(change.rules);
  select(change.focus === null ? null : ruleNodeId(change.rules[change.focus], change.focus));
}

function onDrop(id: string, y: number) {
  const from = nodeRuleIndex(id, form.routing_rules);
  if (from === null || !canEdit.value) return;

  apply(reorderRule(form.routing_rules, from, dropIndex(graph.value.nodes, from, y)));
}

function onMove(id: string, direction: -1 | 1) {
  const from = nodeRuleIndex(id, form.routing_rules);
  if (from === null) return;

  apply(routing.value.move(form.routing_rules, from, direction));
  nextTick(() => document.querySelector<HTMLElement>(`.vue-flow__node[data-id="${id}"]`)?.focus());
}

function save() {
  if (!canEdit.value || !form.isDirty || form.processing) return;

  form
    .transform((data) => ({ ...data, destination_url: normalizeUrl(data.destination_url) }))
    .put(route('short-links.routing.update', props.link.id), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        loadSaved(props.link);
        toast({ title: 'Routing saved', tone: 'success', duration: 2000 });
      },
      onError: (errors) => {
        const index = firstInvalidRule(form.routing_rules, errors);
        if (index !== null) select(ruleNodeId(form.routing_rules[index], index));
        else if (errors.destination_url) select('default');
      },
    });
}

function discard() {
  form.reset();
  form.clearErrors();
  preview.value = null;
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
    if (event.detail.visit.method === 'get' && form.isDirty && !window.confirm(LEAVE_MESSAGE)) event.preventDefault();
  });
});

onUnmounted(() => {
  window.removeEventListener('beforeunload', warnBeforeUnload);
  stopGuard?.();
});
</script>

<template>
  <Head :title="`Smart routing · ${link.slug}`" />

  <AuthenticatedLayout panels>
    <div class="flex min-h-0 flex-col gap-2 xl:h-full xl:flex-row">
      <section
        class="flex min-h-0 min-w-0 flex-1 flex-col gap-4 px-4 py-6 sm:px-6 lg:rounded-xl lg:border lg:bg-canvas lg:px-6 lg:py-6"
        aria-labelledby="routing-title"
      >
        <PageHeader title="Smart routing" :description="link.short_url">
          <template #eyebrow>
            <Link
              :href="route('links.index', { link: link.id })"
              class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-faint transition-colors hover:text-foreground"
            >
              <ArrowLeft class="h-3.5 w-3.5" /> Links
            </Link>
          </template>
          <template #actions>
            <SegmentedControl v-model="view" :options="views" size="sm" label="Routing view" />
            <Menu v-if="canEdit" align="end" width="w-56">
              <template #trigger>
                <Button type="button" size="sm"><Plus />Add rule<ChevronDown class="opacity-70" /></Button>
              </template>
              <MenuItem
                v-for="preset in routingSchema.presets"
                :key="preset.kind"
                :icon="presetIcon(preset.kind)"
                @select="apply(routing.addPreset(form.routing_rules, preset.kind))"
                >{{ preset.label }}</MenuItem
              >
            </Menu>
          </template>
        </PageHeader>

        <p v-if="!canEdit" class="rounded-lg bg-elevated/40 px-3 py-2 text-xs text-muted">
          You can view and test this routing. Only editors can change it.
        </p>

        <div
          v-if="view === 'graph'"
          class="relative min-h-[60vh] flex-1 overflow-hidden rounded-xl border bg-background xl:min-h-0"
        >
          <RoutingGraph
            :nodes="graph.nodes"
            :edges="graph.edges"
            :selected-id="selectedId"
            :can-edit="canEdit"
            @select="select"
            @drop="onDrop"
            @move="onMove"
          />
          <p class="pointer-events-none absolute bottom-3 right-3 max-w-xs text-end text-xs text-faint">
            The first matching routing rule wins.
            <template v-if="canEdit"
              >Drag a routing rule, or focus it and press Alt with an arrow key, to change its priority.</template
            >
          </p>
        </div>

        <fieldset v-else :disabled="!canEdit" class="grid min-w-0 gap-5 overflow-y-auto">
          <Field label="Default destination" :error="form.errors.destination_url">
            <Input
              v-model="form.destination_url"
              type="url"
              spellcheck="false"
              placeholder="https://example.com/page"
            />
          </Field>
          <RoutingRulesEditor
            :model-value="form.routing_rules"
            @update:model-value="replaceRules"
            :errors="form.errors"
            :schema="routingSchema"
            :default-destination="form.destination_url"
          />
        </fieldset>

        <SaveBar
          v-if="canEdit"
          :dirty="form.isDirty"
          :processing="form.processing"
          :has-errors="errorCount > 0"
          @discard="discard"
          @save="save"
        />
      </section>

      <aside
        class="flex min-h-0 w-full flex-col gap-4 px-4 py-6 sm:px-6 lg:rounded-xl lg:border lg:bg-canvas xl:w-[420px] xl:shrink-0 xl:overflow-y-auto xl:overscroll-contain"
        aria-label="Routing details"
      >
        <SegmentedControl v-model="panel" :options="panels" size="sm" label="Panel" class="self-start" />

        <div v-show="panel === 'edit'">
          <Transition
            mode="out-in"
            enter-active-class="transition duration-150 ease-[cubic-bezier(0.23,1,0.32,1)]"
            enter-from-class="opacity-0 translate-y-1"
            leave-active-class="transition duration-100 ease-out"
            leave-to-class="opacity-0"
          >
            <div :key="selectedId ?? 'none'" class="grid min-w-0 gap-4">
              <fieldset v-if="selectedRuleIndex !== null" :disabled="!canEdit" class="min-w-0">
                <ol aria-label="Selected routing rule">
                  <RoutingRuleCard
                    :rule="form.routing_rules[selectedRuleIndex]"
                    :index="selectedRuleIndex"
                    :is-last="selectedRuleIndex === form.routing_rules.length - 1"
                    :open="true"
                    :errors="ruleErrors(form.errors, selectedRuleIndex)"
                    :panel-id="`routing-panel-${selectedRuleIndex}`"
                    :routing="routing"
                    @toggle="select(null)"
                    @move="apply(routing.move(form.routing_rules, selectedRuleIndex, $event))"
                    @duplicate="apply(routing.duplicate(form.routing_rules, selectedRuleIndex))"
                    @remove="apply(routing.remove(form.routing_rules, selectedRuleIndex))"
                  />
                </ol>
              </fieldset>

              <fieldset v-else-if="selectedId === 'default'" :disabled="!canEdit" class="grid gap-3">
                <h2 class="text-[13px] font-medium text-foreground">Default destination</h2>
                <p class="text-xs leading-relaxed text-muted">
                  Visitors who match no rule, or reach only disabled rules, go here.
                </p>
                <Field label="Destination URL" :error="form.errors.destination_url">
                  <Input
                    v-model="form.destination_url"
                    type="url"
                    spellcheck="false"
                    placeholder="https://example.com/page"
                  />
                </Field>
              </fieldset>

              <div v-else class="grid gap-3 text-xs leading-relaxed text-muted">
                <h2 class="text-[13px] font-medium text-foreground">How this routing works</h2>
                <p>
                  Each visit starts on the left and checks rules from top to bottom. The first rule whose conditions
                  match sends the visitor to its destination or splits them between variants. Visitors who match nothing
                  go to the default destination.
                </p>
                <p>Select a rule or destination in the graph to {{ canEdit ? 'edit' : 'inspect' }} it.</p>
                <p v-if="errorCount" class="text-danger">
                  {{ errorCount === 1 ? 'One field needs' : `${errorCount} fields need` }} attention. Rules with
                  problems are outlined in red.
                </p>
              </div>
            </div>
          </Transition>
        </div>

        <RoutingPreviewPanel
          v-show="panel === 'test'"
          :link-id="link.id"
          :schema="routingSchema"
          :draft="draft"
          :unsaved="form.isDirty"
          @result="preview = $event"
        />
      </aside>
    </div>
  </AuthenticatedLayout>
</template>
