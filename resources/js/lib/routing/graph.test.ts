import { describe, expect, it } from 'vitest';

import type { RoutingRuleDraft } from '@/types/shortLinks';

import { buildRoutingGraph, dropIndex, nodeRuleIndex, reorderRule, takenPath } from './graph';
import type { PreviewRule, RoutingPreview } from './preview';
import { routing, schema } from './testRouting';

function country(name: string, id: number): RoutingRuleDraft {
  return { ...routing().newRule('conditional', 'country'), id, name, destination_url: `https://example.com/${id}` };
}

function split(id: number): RoutingRuleDraft {
  const rule = { ...routing().newRule('split_test', 'custom'), id, name: 'Split' };
  rule.variants = rule.variants.map((variant, index) => ({
    ...variant,
    id: 100 + index,
    destination_url: `https://example.com/v${index}`,
    weight: index === 0 ? 25 : 75,
  }));

  return rule;
}

function previewRule(index: number, status: PreviewRule['status'], selected: number | null = null): PreviewRule {
  return {
    index,
    id: null,
    name: `Rule ${index}`,
    type: selected === null ? 'conditional' : 'split_test',
    match_mode: 'all',
    status,
    destination_url: null,
    conditions: [],
    slot: null,
    total_weight: null,
    variants:
      selected === null
        ? []
        : [0, 1].map((position) => ({
            id: null,
            name: String(position),
            destination_url: '',
            weight: 50,
            is_enabled: true,
            eligible: true,
            share: 50,
            selected: position === selected,
          })),
  };
}

function preview(rules: PreviewRule[], isDefault: boolean): RoutingPreview {
  return {
    source: 'draft',
    context: { occurred_at: '', local_time: '', timezone: 'UTC', visitor_hash: '', dimensions: {} },
    rules,
    decision: {
      rule_index: null,
      rule_id: null,
      rule_name: null,
      variant_id: null,
      variant_name: null,
      destination_url: 'https://example.com',
      is_default: isDefault,
    },
    availability: { status: 'active', domain: null, domain_usable: true, password_protected: false, available: true },
  };
}

describe('buildRoutingGraph', () => {
  it('chains the entry, the ordered rules and the default destination left to right', () => {
    const rules = [country('France', 1), split(2)];
    const { nodes, edges } = buildRoutingGraph(rules, 'https://example.com/default', { schema });

    expect(nodes.map((node) => node.id)).toEqual([
      'entry',
      'rule-1',
      'destination-1',
      'rule-2',
      'variant-2-100',
      'variant-2-101',
      'default',
    ]);
    expect(edges.map((edge) => [edge.source, edge.target, edge.kind])).toEqual([
      ['entry', 'rule-1', 'flow'],
      ['rule-1', 'destination-1', 'match'],
      ['rule-1', 'rule-2', 'next'],
      ['rule-2', 'variant-2-100', 'match'],
      ['rule-2', 'variant-2-101', 'match'],
      ['rule-2', 'default', 'next'],
    ]);

    const position = (id: string) => nodes.find((node) => node.id === id)!.position;
    expect(position('entry').x).toBeLessThan(position('rule-1').x);
    expect(position('rule-1').x).toBeLessThan(position('destination-1').x);
    expect(position('rule-1').y).toBeLessThan(position('rule-2').y);
    expect(position('rule-2').y).toBeLessThan(position('default').y);
    expect(position('variant-2-100').y).toBeLessThan(position('variant-2-101').y);
  });

  it('describes rules with their priority, conditions, shares and errors', () => {
    const disabled = { ...country('Paused', 1), is_enabled: false };
    const { nodes } = buildRoutingGraph([disabled, split(2)], 'https://example.com/default', {
      schema,
      errors: { 'routing_rules.1.variants': 'Needs two variants.', destination_url: 'Add a URL.' },
    });

    expect(nodes[1].data).toMatchObject({ index: 0, priority: 1, enabled: false, conditions: ['Country is FR'] });
    expect(nodes[3].data).toMatchObject({ index: 1, errors: ['Needs two variants.'], conditions: [] });
    expect(nodes[4].data).toMatchObject({ label: 'A', share: 25 });
    expect(nodes[5].data).toMatchObject({ label: 'B', share: 75 });
    expect(nodes.at(-1)!.data).toMatchObject({ url: 'https://example.com/default', errors: ['Add a URL.'] });
  });

  it('sends the entry straight to the default destination when there are no rules', () => {
    const { nodes, edges } = buildRoutingGraph([], 'https://example.com/default', { schema });

    expect(nodes.map((node) => node.id)).toEqual(['entry', 'default']);
    expect(edges.map((edge) => edge.target)).toEqual(['default']);
  });

  it('keys unsaved rules and variants by their client ids', () => {
    const draft = routing().newRule('conditional', 'country');
    const { nodes } = buildRoutingGraph([draft], '', { schema });

    expect(nodes[1].id).toBe(`rule-${draft.client_id}`);
    expect(nodeRuleIndex(nodes[1].id, [draft])).toBe(0);
    expect(nodeRuleIndex('default', [draft])).toBeNull();
  });

  it('marks the previewed path as taken and dims the rest', () => {
    const rules = [country('France', 1), split(2), country('Germany', 3)];
    const result = preview(
      [previewRule(0, 'not_matched'), previewRule(1, 'matched', 1), previewRule(2, 'not_reached')],
      false,
    );
    const { nodes, edges } = buildRoutingGraph(rules, 'https://example.com/default', { schema, preview: result });
    const state = (id: string) => [...nodes, ...edges].find((item) => item.id === id)!.data.path;

    expect(state('entry')).toBe('taken');
    expect(state('rule-1')).toBe('taken');
    expect(state('destination-1')).toBe('dimmed');
    expect(state('rule-2')).toBe('taken');
    expect(state('variant-2-101')).toBe('taken');
    expect(state('variant-2-100')).toBe('dimmed');
    expect(state('rule-3')).toBe('dimmed');
    expect(state('default')).toBe('dimmed');
    expect(state('edge-next-1')).toBe('taken');
    expect(state('edge-match-2-101')).toBe('taken');
    expect(state('edge-next-2')).toBe('dimmed');
    expect(nodes.find((node) => node.id === 'rule-2')!.data).toMatchObject({ status: 'matched' });
  });

  it('follows the no match chain to the default destination', () => {
    const rules = [country('France', 1)];
    const path = takenPath(rules, preview([previewRule(0, 'disabled')], true));

    expect([...path!]).toEqual(['entry', 'edge-entry', 'rule-1', 'edge-next-1', 'default']);
  });

  it('ignores a preview that no longer matches the rules', () => {
    const rules = [country('France', 1), country('Germany', 2)];
    const { nodes } = buildRoutingGraph(rules, '', { schema, preview: preview([previewRule(0, 'matched')], false) });

    expect(takenPath(rules, preview([previewRule(0, 'matched')], false))).toBeNull();
    expect(nodes.every((node) => node.data.path === 'idle')).toBe(true);
  });
});

describe('reordering', () => {
  it('moves a rule to a new priority', () => {
    const rules = [country('A', 1), country('B', 2), country('C', 3)];

    expect(reorderRule(rules, 0, 2).rules.map((rule) => rule.name)).toEqual(['B', 'C', 'A']);
    expect(reorderRule(rules, 2, 0)).toMatchObject({ focus: 0 });
    expect(reorderRule(rules, 1, 9).rules.map((rule) => rule.name)).toEqual(['A', 'C', 'B']);
    expect(reorderRule(rules, 1, 1).rules).toEqual(rules);
  });

  it('turns a dropped rule position into the priority it lands on', () => {
    const rules = [country('A', 1), country('B', 2), country('C', 3)];
    const { nodes } = buildRoutingGraph(rules, '', { schema });
    const y = (id: string) => nodes.find((node) => node.id === id)!.position.y;

    expect(dropIndex(nodes, 0, y('rule-3') + 10)).toBe(2);
    expect(dropIndex(nodes, 2, y('rule-1') - 50)).toBe(0);
    expect(dropIndex(nodes, 1, y('rule-2') + 5)).toBe(1);
  });
});
