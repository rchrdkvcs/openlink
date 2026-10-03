import { describe, expect, it } from 'vitest';

import { hasPendingLogoChange, initialEditorFields, logoLabel, showsLogo, toUpdatePayload } from './qrForm';
import { panelForErrors } from './qrPanels';
import { previewQuery, withQuery } from './qrUrls';
import type { PayloadDescriptors, QrCodeRecord } from './types';

const descriptors: PayloadDescriptors = {
  url: { label: 'URL', hint: '', defaults: { url: '' }, fields: [] },
  raw: { label: 'Raw', hint: '', defaults: { content: '' }, fields: [] },
};

const qr: QrCodeRecord = {
  id: 1,
  name: 'Poster',
  token: 'abc',
  payload_type: 'url',
  payload: { url: 'https://example.com' },
  content: 'https://example.com',
  is_direct: true,
  short_link_id: null,
  short_link: null,
  scans: 0,
  size: 1024,
  foreground_color: '#000000',
  background_color: '#ffffff',
  margin: 2,
  error_correction: 'medium',
  style: 'square',
  eye_style: 'square',
  background_transparent: true,
  has_logo: true,
  public_url: 'https://openlink.test/q/abc',
};

describe('toUpdatePayload', () => {
  it('sends appearance fields and posts booleans as 1/0 flags', () => {
    const payload = toUpdatePayload(initialEditorFields(qr, descriptors));

    expect(payload).toEqual({
      name: 'Poster',
      short_link_id: null,
      payload_type: 'url',
      payload: { url: 'https://example.com' },
      size: 1024,
      foreground_color: '#000000',
      background_color: '#ffffff',
      margin: 2,
      error_correction: 'medium',
      style: 'square',
      eye_style: 'square',
      logo: null,
      _method: 'patch',
      background_transparent: 1,
      remove_logo: 0,
    });
  });
});

describe('logo state', () => {
  const file = new File([''], 'brand.png');

  it('labels the upload control', () => {
    expect(logoLabel(true, { logo: null, remove_logo: false })).toBe('Replace logo');
    expect(logoLabel(true, { logo: null, remove_logo: true })).toBe('Upload logo');
    expect(logoLabel(false, { logo: file, remove_logo: false })).toBe('brand.png');
  });

  it('tracks whether a logo is shown or pending', () => {
    expect(showsLogo(true, { logo: null, remove_logo: true })).toBe(false);
    expect(showsLogo(false, { logo: file, remove_logo: false })).toBe(true);
    expect(hasPendingLogoChange({ logo: null, remove_logo: false })).toBe(false);
    expect(hasPendingLogoChange({ logo: null, remove_logo: true })).toBe(true);
  });
});

describe('panelForErrors', () => {
  it('reveals the first panel containing an error', () => {
    expect(panelForErrors(['payload.url'])).toBe('content');
    expect(panelForErrors(['margin', 'logo'])).toBe('style');
    expect(panelForErrors([])).toBeNull();
  });
});

describe('preview query', () => {
  it('encodes appearance with a cache-busting version', () => {
    const url = withQuery('/qr-codes/abc/preview', previewQuery(qr, 3));

    expect(url).toBe(
      '/qr-codes/abc/preview?size=512&foreground_color=%23000000&background_color=%23ffffff&margin=2&error_correction=medium&style=square&eye_style=square&background_transparent=1&v=3',
    );
  });

  it('leaves URLs without parameters untouched', () => {
    expect(withQuery('/qr-codes/abc/png', {})).toBe('/qr-codes/abc/png');
    expect(withQuery('/qr-codes/abc/png', { size: 2048 })).toBe('/qr-codes/abc/png?size=2048');
  });
});
