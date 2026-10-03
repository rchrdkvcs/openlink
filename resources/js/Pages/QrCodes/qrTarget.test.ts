import { describe, expect, it } from 'vitest';

import { applyPayloadType, blankTarget, initialTarget, setPayloadType, toTargetPayload } from './qrTarget';
import type { PayloadDescriptors, TargetState } from './types';

const descriptors: PayloadDescriptors = {
  url: { label: 'URL', hint: 'Opens a website', defaults: { url: '' }, fields: [] },
  wifi: {
    label: 'Wi-Fi',
    hint: 'Joins a network',
    defaults: { ssid: '', encryption: 'WPA', password: '', hidden: false },
    fields: [],
  },
  raw: { label: 'Raw', hint: 'Raw payload', defaults: { content: '' }, fields: [] },
};

function fakeForm(state: Partial<TargetState>, errors: Record<string, string> = {}) {
  const cleared: string[] = [];
  const form = {
    ...blankTarget(descriptors),
    ...state,
    errors,
    clearErrors: (...fields: never[]) => cleared.push(...(fields as string[])),
  };

  return { form, cleared };
}

describe('initialTarget', () => {
  it('maps a QR Code linked to a Short Link', () => {
    const target = initialTarget(
      { is_direct: false, short_link_id: 7, payload_type: null, payload: null },
      descriptors,
    );

    expect(target).toEqual({ target_type: 'short_link', short_link_id: 7, payload_type: 'url', payload: { url: '' } });
  });

  it('merges a direct payload over the type defaults', () => {
    const target = initialTarget(
      { is_direct: true, short_link_id: null, payload_type: 'wifi', payload: { ssid: 'Lobby', encryption: 'WPA' } },
      descriptors,
    );

    expect(target.target_type).toBe('direct');
    expect(target.short_link_id).toBe('');
    expect(target.payload).toEqual({ ssid: 'Lobby', encryption: 'WPA', password: '', hidden: false });
  });
});

describe('setPayloadType', () => {
  it('switches to a direct target, resets the payload and clears related errors', () => {
    const { form, cleared } = fakeForm(
      { target_type: 'short_link' },
      { short_link_id: 'Required', 'payload.url': 'Invalid', name: 'Too long' },
    );

    setPayloadType(form, 'wifi', descriptors);

    expect(form.target_type).toBe('direct');
    expect(form.payload_type).toBe('wifi');
    expect(form.payload).toEqual({ ssid: '', encryption: 'WPA', password: '', hidden: false });
    expect(cleared).toEqual(['short_link_id', 'payload.url']);
  });

  it('keeps the payload when the selected type is already active on a direct target', () => {
    const { form, cleared } = fakeForm(
      { target_type: 'direct', payload_type: 'url', payload: { url: 'https://example.com' } },
      { 'payload.url': 'Invalid' },
    );

    setPayloadType(form, 'url', descriptors);

    expect(form.payload).toEqual({ url: 'https://example.com' });
    expect(cleared).toEqual([]);
  });

  it('switches target without discarding a payload of the same type', () => {
    const state = { ...blankTarget(descriptors), payload: { url: 'https://example.com' } };

    expect(applyPayloadType(state, 'url', descriptors)).toBe(true);
    expect(state.target_type).toBe('direct');
    expect(state.payload).toEqual({ url: 'https://example.com' });
  });

  it('falls back to the URL type when the selection is cleared', () => {
    const { form } = fakeForm({ target_type: 'direct', payload_type: 'wifi' });

    setPayloadType(form, null, descriptors);

    expect(form.payload_type).toBe('url');
  });
});

describe('toTargetPayload', () => {
  const direct = { name: 'Lobby', ...blankTarget(descriptors, 'direct'), short_link_id: 3 };

  it('sends only the Short Link for linked codes', () => {
    const data = { ...direct, target_type: 'short_link' as const };

    expect(toTargetPayload(data, 'create')).toEqual({ name: 'Lobby', short_link_id: 3 });
    expect(toTargetPayload(data, 'update')).toEqual({ name: 'Lobby', short_link_id: 3 });
  });

  it('omits the Short Link when creating a direct code', () => {
    expect(toTargetPayload(direct, 'create')).toEqual({ name: 'Lobby', payload_type: 'url', payload: { url: '' } });
  });

  it('detaches the Short Link when updating to a direct code', () => {
    expect(toTargetPayload(direct, 'update')).toEqual({
      name: 'Lobby',
      short_link_id: null,
      payload_type: 'url',
      payload: { url: '' },
    });
  });
});
