import { beforeAll, describe, expect, it, vi } from 'vitest';

import { initialEditorFields } from './qrForm';
import {
  blockedReason,
  exportSizeFor,
  hasFixes,
  printRestrictions,
  readabilityBody,
  readabilityRequest,
  readableFixes,
  type ReadabilitySummary,
  type ReadabilityWarning,
} from './qrReadability';
import type { PayloadDescriptors, QrCodeRecord } from './types';

const descriptors: PayloadDescriptors = {
  wifi: { label: 'Wi-Fi', hint: '', defaults: { ssid: '', password: '', hidden: false }, fields: [] },
};

const direct: QrCodeRecord = {
  id: 1,
  name: 'Lobby',
  token: 'abc',
  payload_type: 'wifi',
  payload: { ssid: 'Café', encryption: 'WPA', password: null, hidden: true },
  content: 'WIFI:...',
  is_direct: true,
  short_link_id: null,
  short_link: null,
  scans: 0,
  size: 1024,
  foreground_color: '#111827',
  background_color: '#ffffff',
  margin: 2,
  error_correction: 'medium',
  style: 'dot',
  eye_style: 'circle',
  background_transparent: false,
  has_logo: false,
  public_url: 'https://openlink.test/qr/abc',
};

const linked: QrCodeRecord = { ...direct, is_direct: false, short_link_id: 7, payload_type: null, payload: null };

function warning(
  code: ReadabilityWarning['code'],
  severity: ReadabilityWarning['severity'] = 'warning',
): ReadabilityWarning {
  return { code, severity, title: code, message: code };
}

const linkSummary: ReadabilitySummary['short_link'] = {
  short_url: 'https://go.test/promo',
  status: 'active',
  domain: 'go.test',
  domain_active: true,
  activates_at: null,
  expires_at: null,
  visit_limit: null,
  successful_visits: 0,
  has_password: false,
};

beforeAll(() => {
  vi.stubGlobal('route', (name: string, token: string) => `/qr-codes/${token}/${name.split('.').pop()}`);
});

describe('readabilityBody', () => {
  it('sends the export size, print width, draft appearance, and native payload in the request body', () => {
    const body = readabilityBody(initialEditorFields(direct, descriptors), 2048, 30);

    expect(body).toEqual({
      size: 2048,
      print_width_mm: 30,
      foreground_color: '#111827',
      background_color: '#ffffff',
      margin: 2,
      error_correction: 'medium',
      style: 'dot',
      eye_style: 'circle',
      background_transparent: false,
      payload_type: 'wifi',
      payload: { ssid: 'Café', encryption: 'WPA', password: null, hidden: true },
    });
  });

  it('omits the payload for linked QR codes, which are checked against their stable entry URL', () => {
    const body = readabilityBody(initialEditorFields(linked, descriptors), 1024, null);

    expect(body).not.toHaveProperty('payload');
    expect(body).not.toHaveProperty('payload_type');
  });
});

describe('readabilityRequest', () => {
  it('keeps the draft out of the URL and changes whenever the payload, design, size, or saved logo changes', () => {
    const fields = initialEditorFields(direct, descriptors);
    const key = (request: ReturnType<typeof readabilityRequest>) => JSON.stringify(request);
    const base = readabilityRequest('abc', fields, 1024, null, 0);

    expect(base.url).toBe('/qr-codes/abc/readability');
    expect(
      key(readabilityRequest('abc', { ...fields, payload: { ...fields.payload, ssid: 'Other' } }, 1024, null, 0)),
    ).not.toBe(key(base));
    expect(key(readabilityRequest('abc', { ...fields, margin: 0 }, 1024, null, 0))).not.toBe(key(base));
    expect(key(readabilityRequest('abc', fields, 2048, null, 0))).not.toBe(key(base));
    expect(key(readabilityRequest('abc', fields, 1024, 40, 0))).not.toBe(key(base));
    expect(key(readabilityRequest('abc', fields, 1024, null, 1))).not.toBe(key(base));
  });
});

describe('blockedReason', () => {
  it('blocks checks for unsaved logos and targets the server cannot render yet', () => {
    const fields = initialEditorFields(linked, descriptors);

    expect(blockedReason(fields, linked)).toBeNull();
    expect(blockedReason({ ...fields, logo: new File([''], 'logo.png') }, linked)).toMatch(/logo/);
    expect(blockedReason({ ...fields, remove_logo: true }, linked)).toMatch(/logo/);
    expect(blockedReason({ ...fields, target_type: 'direct' }, linked)).toMatch(/target/);
    expect(blockedReason({ ...fields, short_link_id: 8 }, linked)).toMatch(/short link/);
  });
});

describe('readableFixes', () => {
  const draft = { margin: 2, error_correction: 'medium', foreground_color: '#111827', background_color: '#ffffff' };
  const summary = { modules: 25, min_module_pixels: 4 };

  it('ignores notice-level guidance', () => {
    const fixes = readableFixes(
      [warning('transparent_background', 'notice'), warning('low_contrast', 'notice'), warning('quiet_zone', 'notice')],
      { ...draft, margin: 1, foreground_color: '#9ca3af' },
      1024,
      summary,
    );

    expect(fixes).toEqual({ appearance: {} });
    expect(hasFixes(fixes)).toBe(false);
  });

  it('restores readable colors and quiet zone', () => {
    expect(
      readableFixes(
        [warning('low_contrast'), warning('quiet_zone')],
        { ...draft, margin: 0, foreground_color: '#d1d5db' },
        1024,
        summary,
      ),
    ).toEqual({ appearance: { foreground_color: '#111827', margin: 4 } });
  });

  it('raises error correction and trims the margin for logo obstruction', () => {
    expect(readableFixes([warning('logo_obstruction')], { ...draft, margin: 16 }, 1024, summary)).toEqual({
      appearance: { error_correction: 'high', margin: 4 },
    });
  });

  it('offers no logo fix when the draft already uses its readable settings', () => {
    const fixes = readableFixes(
      [warning('logo_obstruction')],
      { ...draft, error_correction: 'high', margin: 2 },
      1024,
      summary,
    );

    expect(fixes).toEqual({ appearance: {} });
    expect(hasFixes(fixes)).toBe(false);
  });

  it('suggests a larger export size only when one exists', () => {
    expect(readableFixes([warning('small_modules')], draft, 128, { modules: 177, min_module_pixels: 4 })).toEqual({
      appearance: {},
      exportSize: 1024,
    });
    expect(
      hasFixes(readableFixes([warning('small_modules')], draft, 4096, { modules: 2000, min_module_pixels: 4 })),
    ).toBe(false);
  });

  it('picks the smallest export size that meets the module threshold from the server', () => {
    expect(exportSizeFor(29, 4)).toBe(512);
    expect(exportSizeFor(181, 4)).toBe(1024);
    expect(exportSizeFor(181, 6)).toBe(2048);
    expect(exportSizeFor(5000, 4)).toBe(4096);
  });
});

describe('printRestrictions', () => {
  it('lists lifecycle restrictions that affect printed codes', () => {
    const restrictions = printRestrictions({
      is_direct: false,
      short_link: {
        ...linkSummary,
        status: 'expired',
        domain_active: false,
        expires_at: '2026-01-01T10:00:00Z',
        visit_limit: 10,
        successful_visits: 3,
        has_password: true,
      },
    });

    expect(restrictions[0]).toBe('go.test is not active, so scans will not resolve.');
    expect(restrictions[1]).toMatch(/^Expired /);
    expect(restrictions.slice(2)).toEqual(['Visit limit: 3 of 10 used.', 'Visitors must enter a password.']);
  });

  it('reminds that native content needs reprinting and stays quiet for unrestricted links', () => {
    expect(printRestrictions({ is_direct: true, short_link: null })).toEqual([
      'Native content: changing it later means reprinting.',
    ]);
    expect(printRestrictions({ is_direct: false, short_link: linkSummary })).toEqual([]);
  });
});
