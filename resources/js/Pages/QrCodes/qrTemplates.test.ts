import { describe, expect, it } from 'vitest';

import { initialEditorFields, logoLabel, showsLogo, toUpdatePayload } from './qrForm';
import { templateFields, templateOptions, withTemplateDesign } from './qrTemplates';
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
  it('copies the design and remembers the template', () => {
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
      template_id: 3,
    });
  });

  it('lists templates as select options', () => {
    expect(templateOptions([brand])).toEqual([{ value: 3, label: 'Brand' }]);
  });
});

describe('withTemplateDesign', () => {
  const applied = () => toUpdatePayload({ ...initialEditorFields(qr, descriptors), ...templateFields(brand) });

  it('lets the server take the design from the template while keeping the QR code identity', () => {
    const payload = withTemplateDesign(applied(), brand);

    expect(payload).toEqual({
      name: 'Flyer',
      short_link_id: null,
      payload_type: 'text',
      payload: { text: 'Hello' },
      size: 2048,
      logo: null,
      template_id: 3,
      _method: 'patch',
      remove_logo: 0,
    });
  });

  it('keeps fields changed after applying the template', () => {
    const payload = withTemplateDesign({ ...applied(), foreground_color: '#ff0000', margin: 6 }, brand);

    expect(payload).toMatchObject({ foreground_color: '#ff0000', margin: 6, template_id: 3 });
    expect(payload).not.toHaveProperty('style');
  });

  it('sends the full design when no template is applied', () => {
    const payload = toUpdatePayload(initialEditorFields(qr, descriptors));

    expect(withTemplateDesign(payload, null)).toBe(payload);
  });
});

describe('template preview', () => {
  it('asks the server to render the template before saving', () => {
    const url = withQuery('/p', previewQuery({ ...qr, ...templateFields(brand) }, 1));
    const removed = withQuery('/p', previewQuery({ ...qr, ...templateFields(brand), remove_logo: true }, 1));

    expect(url).toContain('template_id=3');
    expect(url).not.toContain('remove_logo');
    expect(removed).toContain('template_id=3');
    expect(removed).toContain('remove_logo=1');
  });

  it('counts the template logo as the current logo', () => {
    const state = { logo: null, remove_logo: false };

    expect(showsLogo(brand.has_logo, state)).toBe(true);
    expect(logoLabel(brand.has_logo, state)).toBe('Replace logo');
    expect(showsLogo(false, state)).toBe(false);
  });
});
