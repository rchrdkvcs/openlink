import type { RoutingRuleDraft, RoutingSchema, RoutingVariantDraft } from '@/types/shortLinks';

import { type FormErrors, ruleErrors } from './errors';
import type { PreviewRuleStatus, RoutingPreview } from './preview';
import type { RuleListChange } from './rules';
import { conditionSummary, ruleName, variantShare } from './summary';

export type PathState = 'idle' | 'taken' | 'dimmed';

export type GraphNodeKind = 'entry' | 'rule' | 'destination' | 'variant' | 'default';

export type EntryNodeData = { path: PathState };

export type RuleNodeData = {
  path: PathState;
  index: number;
  priority: number;
  name: string;
  type: RoutingRuleDraft['type'];
  enabled: boolean;
  matchMode: RoutingRuleDraft['match_mode'];
  conditions: string[];
  errors: string[];
  status: PreviewRuleStatus | null;
};

export type DestinationNodeData = {
  path: PathState;
  index: number;
  label: string;
  url: string;
  enabled: boolean;
  share: number | null;
  hint?: string;
};

export type DefaultNodeData = { path: PathState; url: string; errors: string[] };

export type GraphNode =
  | { id: string; type: 'entry'; position: Position; data: EntryNodeData }
  | { id: string; type: 'rule'; position: Position; data: RuleNodeData }
  | { id: string; type: 'destination' | 'variant'; position: Position; data: DestinationNodeData }
  | { id: string; type: 'default'; position: Position; data: DefaultNodeData };

export type GraphEdgeKind = 'flow' | 'match' | 'next';

export type GraphEdge = {
  id: string;
  source: string;
  target: string;
  sourceHandle: GraphEdgeKind;
  kind: GraphEdgeKind;
  data: { path: PathState; muted: boolean };
};

type Position = { x: number; y: number };

export type GraphOptions = { schema: RoutingSchema; errors?: FormErrors; preview?: RoutingPreview | null };

export const LAYOUT = {
  entryX: 0,
  ruleX: 300,
  destinationX: 680,
  ruleBaseHeight: 92,
  conditionHeight: 20,
  destinationHeight: 68,
  variantGap: 12,
  rowGap: 40,
};

function key(item: { id?: number; client_id?: string }, index: number): string {
  return String(item.id ?? item.client_id ?? `new-${index}`);
}

export function ruleNodeId(rule: RoutingRuleDraft, index: number): string {
  return `rule-${key(rule, index)}`;
}

function destinationNodeId(rule: RoutingRuleDraft, index: number): string {
  return `destination-${key(rule, index)}`;
}

function variantNodeId(rule: RoutingRuleDraft, index: number, variant: RoutingVariantDraft, position: number): string {
  return `variant-${key(rule, index)}-${key(variant, position)}`;
}

function matchEdgeId(rule: RoutingRuleDraft, index: number, variant?: RoutingVariantDraft, position = 0): string {
  return variant ? `edge-match-${key(rule, index)}-${key(variant, position)}` : `edge-match-${key(rule, index)}`;
}

function nextEdgeId(rule: RoutingRuleDraft, index: number): string {
  return `edge-next-${key(rule, index)}`;
}

export function nodeRuleIndex(nodeId: string, rules: RoutingRuleDraft[]): number | null {
  const index = rules.findIndex((rule, position) => ruleNodeId(rule, position) === nodeId);

  return index >= 0 ? index : null;
}

function ruleHeight(rule: RoutingRuleDraft): number {
  return LAYOUT.ruleBaseHeight + Math.max(rule.conditions.length, 1) * LAYOUT.conditionHeight;
}

function fittingPreview(rules: RoutingRuleDraft[], preview: RoutingPreview | null | undefined): RoutingPreview | null {
  return preview && preview.rules.length === rules.length ? preview : null;
}

export function takenPath(rules: RoutingRuleDraft[], preview: RoutingPreview | null | undefined): Set<string> | null {
  const fitting = fittingPreview(rules, preview);
  if (!fitting) return null;

  const path = new Set<string>(['entry', 'edge-entry']);

  for (const [index, rule] of rules.entries()) {
    const result = fitting.rules[index];
    path.add(ruleNodeId(rule, index));

    if (result.status === 'matched') {
      if (rule.type === 'split_test') {
        const position = result.variants.findIndex((variant) => variant.selected);
        const variant = rule.variants[position];
        if (variant) {
          path.add(matchEdgeId(rule, index, variant, position));
          path.add(variantNodeId(rule, index, variant, position));
        }
      } else {
        path.add(matchEdgeId(rule, index));
        path.add(destinationNodeId(rule, index));
      }

      return path;
    }

    path.add(nextEdgeId(rule, index));
  }

  path.add('default');

  return path;
}

export function buildRoutingGraph(
  rules: RoutingRuleDraft[],
  defaultDestination: string,
  options: GraphOptions,
): { nodes: GraphNode[]; edges: GraphEdge[] } {
  const path = takenPath(rules, options.preview);
  const preview = fittingPreview(rules, options.preview);
  const state = (id: string): PathState => (path ? (path.has(id) ? 'taken' : 'dimmed') : 'idle');
  const nodes: GraphNode[] = [
    { id: 'entry', type: 'entry', position: { x: LAYOUT.entryX, y: 0 }, data: { path: state('entry') } },
  ];
  const edges: GraphEdge[] = [];
  let y = 0;

  function edge(id: string, source: string, target: string, kind: GraphEdgeKind, muted = false) {
    edges.push({ id, source, target, sourceHandle: kind, kind, data: { path: state(id), muted } });
  }

  rules.forEach((rule, index) => {
    const id = ruleNodeId(rule, index);
    const variants = rule.type === 'split_test' ? rule.variants : [];
    const branchCount = Math.max(variants.length, 1);
    const rowHeight = Math.max(
      ruleHeight(rule),
      branchCount * LAYOUT.destinationHeight + (branchCount - 1) * LAYOUT.variantGap,
    );

    edge(
      index === 0 ? 'edge-entry' : nextEdgeId(rules[index - 1], index - 1),
      index === 0 ? 'entry' : ruleNodeId(rules[index - 1], index - 1),
      id,
      index === 0 ? 'flow' : 'next',
    );

    nodes.push({
      id,
      type: 'rule',
      position: { x: LAYOUT.ruleX, y },
      data: {
        path: state(id),
        index,
        priority: index + 1,
        name: ruleName(rule),
        type: rule.type,
        enabled: rule.is_enabled,
        matchMode: rule.match_mode,
        conditions: rule.conditions.map((condition) => conditionSummary(options.schema, condition)),
        errors: ruleErrors(options.errors, index).map(([, message]) => message),
        status: preview ? preview.rules[index].status : null,
      },
    });

    if (variants.length > 0) {
      variants.forEach((variant, position) => {
        const variantId = variantNodeId(rule, index, variant, position);
        nodes.push({
          id: variantId,
          type: 'variant',
          position: { x: LAYOUT.destinationX, y: y + position * (LAYOUT.destinationHeight + LAYOUT.variantGap) },
          data: {
            path: state(variantId),
            index,
            label: variant.name || `Variant ${position + 1}`,
            url: variant.destination_url,
            enabled: variant.is_enabled,
            share: variantShare(rule, variant),
          },
        });
        edge(
          matchEdgeId(rule, index, variant, position),
          id,
          variantId,
          'match',
          !rule.is_enabled || !variant.is_enabled,
        );
      });
    } else {
      const destinationId = destinationNodeId(rule, index);
      const splitTest = rule.type === 'split_test';
      nodes.push({
        id: destinationId,
        type: 'destination',
        position: { x: LAYOUT.destinationX, y },
        data: {
          path: state(destinationId),
          index,
          label: splitTest ? 'No variants' : 'Redirect to',
          url: splitTest ? '' : rule.destination_url,
          enabled: rule.is_enabled && !splitTest,
          share: null,
          ...(splitTest ? { hint: 'Add variants to split traffic' } : {}),
        },
      });
      edge(matchEdgeId(rule, index), id, destinationId, 'match', !rule.is_enabled || splitTest);
    }

    y += rowHeight + LAYOUT.rowGap;
  });

  const last = rules.length - 1;
  edge(
    last < 0 ? 'edge-entry' : nextEdgeId(rules[last], last),
    last < 0 ? 'entry' : ruleNodeId(rules[last], last),
    'default',
    last < 0 ? 'flow' : 'next',
  );
  nodes.push({
    id: 'default',
    type: 'default',
    position: { x: LAYOUT.ruleX, y },
    data: {
      path: state('default'),
      url: defaultDestination,
      errors: options.errors?.destination_url ? [options.errors.destination_url] : [],
    },
  });

  return { nodes, edges };
}

export function reorderRule(rules: RoutingRuleDraft[], from: number, to: number): RuleListChange {
  const target = Math.max(0, Math.min(to, rules.length - 1));
  if (target === from) return { rules, focus: from };

  const reordered = [...rules];
  const [moved] = reordered.splice(from, 1);
  reordered.splice(target, 0, moved);

  return { rules: reordered, focus: target };
}

export function dropIndex(nodes: GraphNode[], from: number, droppedY: number): number {
  return nodes.filter((node) => node.type === 'rule' && node.data.index !== from && node.position.y < droppedY).length;
}
