import { describe, expect, it } from 'vitest';

import { buildRoutingGraph } from '@/lib/routing/graph';
import { routing, schema } from '@/lib/routing/testRouting';

import { dropPriority, invalidNode, ruleDestination } from './nodeSelection';

describe('focused node configuration', () => {
  const editor = routing();
  const rule = editor.newRule('conditional', 'country');
  const split = editor.newRule('split_test', 'custom');
  const graph = buildRoutingGraph([rule, split], 'https://example.com', { schema });
  const id = (type: string, position = 0) => graph.nodes.filter((node) => node.type === type)[position].id;

  it('opens the destination field when its saved value is invalid', () => {
    expect(ruleDestination(graph.nodes, 0)).toBe(id('destination'));
    expect(invalidNode(graph.nodes, { 'routing_rules.0.destination_url': 'Invalid URL' })).toBe(id('destination'));
    expect(invalidNode(graph.nodes, { destination_url: 'Invalid URL' })).toBe('default');
  });

  it('opens the specific invalid variant and falls back to the split rule for structural errors', () => {
    expect(invalidNode(graph.nodes, { 'routing_rules.1.variants.1.weight': 'Invalid weight' })).toBe(id('variant', 1));
    expect(invalidNode(graph.nodes, { 'routing_rules.1.variants': 'Add variants' })).toBe(id('rule', 1));
    expect(invalidNode(graph.nodes, { 'routing_rules.0.conditions.0.value': 'Invalid country' })).toBe(id('rule'));
  });

  it('resolves error indexes against the current rule order', () => {
    const reordered = buildRoutingGraph([split, rule], 'https://example.com', { schema });
    expect(invalidNode(reordered.nodes, { 'routing_rules.1.destination_url': 'Invalid URL' })).toBe(id('destination'));
    expect(invalidNode(graph.nodes, {})).toBeNull();
  });

  it('snaps drops near a rule to its priority in either direction', () => {
    const rules = graph.nodes.filter((node) => node.type === 'rule');
    expect(dropPriority(graph.nodes, rules[0].position.y + 5)).toBe(0);
    expect(dropPriority(graph.nodes, rules[1].position.y - 5)).toBe(1);
    expect(dropPriority(graph.nodes, -200)).toBe(0);
    expect(dropPriority(graph.nodes, 2000)).toBe(1);
    expect(dropPriority([], 0)).toBe(0);
  });
});
