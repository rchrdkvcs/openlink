import { describe, expect, it } from 'vitest';

import { initialEditorFields } from './qrForm';
import {
  blockedReason,
  exportSizeFor,
  isFixable,
  printRestrictions,
  readabilityQuery,
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

describe('readabilityQuery', () => {
  it('sends the export size, the draft appearance, and the native payload with boolean flags', () => {
    const query = readabilityQuery(initialEditorFields(direct, descriptors), 2048, 3);

    expect(query).toEqual({
      size: 2048,
      foreground_color: '#111827',
      background_color: '#ffffff',
      margin: 2,
      error_correction: 'medium',
      style: 'dot',
      eye_style: 'circle',
      background_transparent: 0,
      payload_type: 'wifi',
      'payload[ssid]': 'Café',
      'payload[encryption]': 'WPA',
      'payload[password]': '',
      'payload[hidden]': 1,
      v: 3,
    });
  });

  it('omits the payload for linked QR codes, which are checked against their stable entry URL', () => {
    const query = readabilityQuery(initialEditorFields(linked, descriptors), 1024, 0);

    expect(Object.keys(query).some((key) => key.startsWith('payload'))).toBe(false);
  });

  it('changes whenever the payload, design, export size, or saved logo version changes', () => {
    const fields = initialEditorFields(direct, descriptors);
    const base = JSON.stringify(readabilityQuery(fields, 1024, 0));

    expect(
      JSON.stringify(readabilityQuery({ ...fields, payload: { ...fields.payload, ssid: 'Other' } }, 1024, 0)),
    ).not.toBe(base);
    expect(JSON.stringify(readabilityQuery({ ...fields, margin: 0 }, 1024, 0))).not.toBe(base);
    expect(JSON.stringify(readabilityQuery(fields, 2048, 0))).not.toBe(base);
    expect(JSON.stringify(readabilityQuery(fields, 1024, 1))).not.toBe(base);
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
  it('only proposes fixes for actionable warnings', () => {
    expect(isFixable([warning('transparent_background', 'notice')])).toBe(false);
    expect(isFixable([warning('quiet_zone', 'notice')])).toBe(false);
    expect(isFixable([warning('low_contrast')])).toBe(true);
  });

  it('leaves notice-level guidance untouched', () => {
    expect(
      readableFixes(
        [warning('low_contrast', 'notice'), warning('quiet_zone', 'notice'), warning('small_modules')],
        { margin: 1 },
        { modules: 25 },
      ),
    ).toEqual({ appearance: {}, exportSize: 512 });
  });

  it('restores readable colors, quiet zone, error correction, and export size', () => {
    expect(readableFixes([warning('low_contrast'), warning('quiet_zone')], { margin: 0 }, { modules: 25 })).toEqual({
      appearance: { foreground_color: '#111827', background_color: '#ffffff', margin: 4 },
    });
    expect(readableFixes([warning('logo_obstruction')], { margin: 16 }, { modules: 25 })).toEqual({
      appearance: { error_correction: 'high', margin: 4 },
    });
    expect(readableFixes([warning('small_modules')], { margin: 2 }, { modules: 177 })).toEqual({
      appearance: {},
      exportSize: 1024,
    });
  });

  it('picks the smallest export size that keeps modules at least 4 px', () => {
    expect(exportSizeFor(29)).toBe(512);
    expect(exportSizeFor(181)).toBe(1024);
    expect(exportSizeFor(5000)).toBe(4096);
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
