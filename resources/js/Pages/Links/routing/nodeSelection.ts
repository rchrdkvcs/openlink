import type { GraphNode } from '@/lib/routing/graph';

export function dropPriority(nodes: GraphNode[], y: number): number {
  const rules = nodes.filter((node) => node.type === 'rule');
  return rules.reduce(
    (nearest, node, index) =>
      Math.abs(node.position.y - y) < Math.abs(rules[nearest].position.y - y) ? index : nearest,
    0,
  );
}

export function ruleDestination(nodes: GraphNode[], index: number): string | null {
  return nodes.find((node) => node.type === 'destination' && node.data.index === index)?.id ?? null;
}

export function invalidNode(nodes: GraphNode[], errors: Record<string, string>): string | null {
  const key = Object.keys(errors).find((field) => field.startsWith('routing_rules.'));
  if (!key) return errors.destination_url ? 'default' : null;
  const [, index, field, position] = key.split('.');
  const ruleIndex = Number(index);
  if (field === 'destination_url') return ruleDestination(nodes, ruleIndex);
  if (field === 'variants' && position !== undefined) {
    const variants = nodes.filter((node) => node.type === 'variant' && node.data.index === ruleIndex);
    if (variants[Number(position)]) return variants[Number(position)].id;
  }
  return nodes.find((node) => node.type === 'rule' && node.data.index === ruleIndex)?.id ?? null;
}
