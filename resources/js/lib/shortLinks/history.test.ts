import { describe, expect, it } from 'vitest';

import type { LinkRevision } from '@/types/shortLinks';

import { actionLabel, actorLabel, channelLabel, describeChange } from './history';

function revision(overrides: Partial<LinkRevision> = {}): LinkRevision {
  return {
    number: 2,
    action: 'updated',
    channel: 'dashboard',
    actor: { id: 1, name: 'Ana' },
    restored_from: null,
    changes: [],
    created_at: '2026-10-21T10:00:00Z',
    ...overrides,
  };
}

describe('actorLabel', () => {
  it('names the member who made the change', () => {
    expect(actorLabel(revision())).toBe('Ana');
  });

  it('marks changes from deleted members and from the system', () => {
    expect(actorLabel(revision({ actor: null }))).toBe('Deleted member');
    expect(actorLabel(revision({ actor: null, channel: 'system' }))).toBe('System');
  });
});

describe('actionLabel', () => {
  it('describes each action', () => {
    expect(actionLabel(revision({ action: 'created' }))).toBe('Created');
    expect(actionLabel(revision())).toBe('Edited');
    expect(actionLabel(revision({ action: 'moved' }))).toBe('Moved');
    expect(actionLabel(revision({ action: 'archived' }))).toBe('Archived');
    expect(actionLabel(revision({ action: 'restored', restored_from: 1 }))).toBe('Restored revision 1');
  });
});

describe('channelLabel', () => {
  it('names the channel', () => {
    expect(channelLabel('dashboard')).toBe('Dashboard');
    expect(channelLabel('api')).toBe('API');
    expect(channelLabel('system')).toBe('System');
  });
});

describe('describeChange', () => {
  it('formats destinations and empty values', () => {
    expect(describeChange({ field: 'fallback_url', before: null, after: 'https://example.com/x' })).toEqual({
      label: 'Fallback',
      before: 'None',
      after: 'https://example.com/x',
    });
  });

  it('formats states without exposing secrets', () => {
    expect(describeChange({ field: 'is_enabled', before: true, after: false })).toEqual({
      label: 'State',
      before: 'Enabled',
      after: 'Disabled',
    });
    expect(describeChange({ field: 'has_password', before: false, after: true })).toEqual({
      label: 'Password',
      before: 'Not protected',
      after: 'Protected',
    });
    expect(describeChange({ field: 'is_archived', before: false, after: true }).after).toBe('Archived');
  });

  it('formats folders, tags, limits and routing rules', () => {
    expect(describeChange({ field: 'folder', before: null, after: { id: 2, name: 'Spring' } })).toMatchObject({
      before: 'No folder',
      after: 'Spring',
    });
    expect(
      describeChange({
        field: 'tags',
        before: [],
        after: [
          { id: 1, name: 'a' },
          { id: 2, name: 'b' },
        ],
      }),
    ).toMatchObject({ before: 'No tags', after: 'a, b' });
    expect(describeChange({ field: 'visit_limit', before: null, after: 100 })).toMatchObject({
      before: 'Unlimited',
      after: '100',
    });
  });

  it('shows each routing rule with its target so edits stay visible', () => {
    const france = {
      name: 'France',
      is_enabled: true,
      conditions: [{}],
      destination_url: 'https://example.com/fr',
      variants: [],
    };
    const split = {
      name: 'Split',
      is_enabled: false,
      conditions: [],
      destination_url: null,
      variants: [
        { name: 'A', weight: 50 },
        { name: 'B', weight: 50 },
      ],
    };

    expect(describeChange({ field: 'routing_rules', before: [], after: [france, split] })).toMatchObject({
      label: 'Smart routing',
      before: 'No rules',
      after: '1. France → https://example.com/fr; 2. Split (off) → A 50, B 50',
    });
    expect(
      describeChange({
        field: 'routing_rules',
        before: [france],
        after: [{ ...france, conditions: [{ value: 'DE' }] }],
      }),
    ).toMatchObject({ after: '1. France → https://example.com/fr (conditions changed)' });
  });

  it('falls back to the raw field for unknown fields', () => {
    expect(describeChange({ field: 'mystery', before: 'x', after: 'y' })).toEqual({
      label: 'mystery',
      before: 'x',
      after: 'y',
    });
  });
});
