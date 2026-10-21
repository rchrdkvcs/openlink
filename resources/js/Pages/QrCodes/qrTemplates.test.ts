import { describe, expect, it } from 'vitest';

import { hasPendingLogoChange, initialEditorFields, logoLabel, showsLogo, toUpdatePayload } from './qrForm';
import { templateFields, templateOptions } from './qrTemplates';
import { previewQuery, withQuery } from './qrUrls';
import type { PayloadDescriptors, QrCodeRecord, QrCodeTemplate } from './types';

const descriptors: PayloadDescriptors = {
  text: { label: 'Text', hint: '', defaults: { text: '' }, fields: [] },
};

const qr: QrCodeRecord = {
  id: 7,
  name: 'Flyer',
  token: 'flyer-token',
  payload_type: 'text',
  payload: { text: 'Hello' },
  content: 'Hello',
  is_direct: true,
  short_link_id: null,
  short_link: null,
  scans: 0,
  size: 2048,
  foreground_color: '#000000',
  background_color: '#ffffff',
  margin: 2,
  error_correction: 'medium',
  style: 'square',
  eye_style: 'square',
  background_transparent: false,
  has_logo: false,
  public_url: 'https://openlink.test/qr/flyer-token',
};

const brand: QrCodeTemplate = {
  id: 3,
  name: 'Brand',
  foreground_color: '#0f172a',
  background_color: '#f8fafc',
  background_transparent: true,
  margin: 4,
  error_correction: 'high',
  style: 'dot',
  eye_style: 'circle',
  has_logo: true,
};

describe('templateFields', () => {
  it('copies the design and points the logo at the template', () => {
    expect(templateFields(brand)).toEqual({
      foreground_color: '#0f172a',
      background_color: '#f8fafc',
      background_transparent: true,
      margin: 4,
      error_correction: 'high',
      style: 'dot',
      eye_style: 'circle',
      logo: null,
      remove_logo: false,
      logo_template_id: 3,
    });
  });

  it('removes the logo when the template has none', () => {
    expect(templateFields({ ...brand, has_logo: false })).toMatchObject({
      remove_logo: true,
      logo_template_id: null,
    });
  });

  it('keeps the name, target, and export size of the QR code', () => {
    const fields = {
      ...initialEditorFields(qr, descriptors),
      ...templateFields(brand),
    };
    const payload = toUpdatePayload(fields);

    expect(payload).toMatchObject({
      name: 'Flyer',
      short_link_id: null,
      payload_type: 'text',
      payload: { text: 'Hello' },
      size: 2048,
      style: 'dot',
      logo_template_id: 3,
      remove_logo: 0,
    });
    expect(payload).not.toHaveProperty('token');
  });

  it('lists templates as select options', () => {
    expect(templateOptions([brand])).toEqual([{ value: 3, label: 'Brand' }]);
  });
});

describe('pending template logo', () => {
  it('counts as an unsaved logo change that shows a logo', () => {
    const state = { logo: null, remove_logo: false, logo_template_id: 3 };

    expect(hasPendingLogoChange(state)).toBe(true);
    expect(showsLogo(false, state)).toBe(true);
    expect(logoLabel(false, state)).toBe('Replace logo');
  });

  it('is previewed before saving', () => {
    const applied = withQuery('/p', previewQuery({ ...qr, ...templateFields(brand) }, 1));
    const bare = withQuery('/p', previewQuery({ ...qr, ...templateFields({ ...brand, has_logo: false }) }, 1));

    expect(applied).toContain('logo_template_id=3');
    expect(applied).not.toContain('remove_logo');
    expect(bare).toContain('remove_logo=1');
    expect(bare).not.toContain('logo_template_id');
  });
});
