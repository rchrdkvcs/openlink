import { describe, expect, it } from 'vitest';

import {
  associationInput,
  emptyDraft,
  firstFieldError,
  formatAssociationFile,
  platformLabel,
  splitLines,
} from '@/lib/appAssociations';

describe('app association drafts', () => {
  it('splits paths and fingerprints on lines or commas, dropping blanks and duplicates', () => {
    expect(splitLines(' /spring \n\n/products/*, /spring\n')).toEqual(['/spring', '/products/*']);
  });

  it('sends iOS paths with an uppercase Team ID and no fingerprints', () => {
    const draft = {
      ...emptyDraft('ios'),
      team_id: ' abcde12345 ',
      identifier: ' com.shop.app ',
      paths: '/spring\n/products/*',
      sha256_cert_fingerprints: 'AA',
    };

    expect(associationInput(draft)).toEqual({
      platform: 'ios',
      team_id: 'ABCDE12345',
      identifier: 'com.shop.app',
      paths: ['/spring', '/products/*'],
    });
  });

  it('sends Android fingerprints in uppercase and no paths', () => {
    const draft = {
      ...emptyDraft('android'),
      identifier: 'com.shop.app',
      paths: '/*',
      sha256_cert_fingerprints: 'ab:cd\nEF:01',
    };

    expect(associationInput(draft)).toEqual({
      platform: 'android',
      identifier: 'com.shop.app',
      sha256_cert_fingerprints: ['AB:CD', 'EF:01'],
    });
  });

  it('labels platforms', () => {
    expect(platformLabel('ios')).toBe('iOS');
    expect(platformLabel('android')).toBe('Android');
  });
});

describe('association file preview', () => {
  it('pretty prints generated files without escaping slashes', () => {
    expect(formatAssociationFile({ applinks: { details: [{ components: [{ '/': '/spring' }] }] } })).toBe(
      '{\n  "applinks": {\n    "details": [\n      {\n        "components": [\n          {\n            "/": "/spring"\n          }\n        ]\n      }\n    ]\n  }\n}',
    );
  });
});

describe('field errors', () => {
  it('surfaces the first nested list error under its field', () => {
    expect(firstFieldError({ 'paths.1': 'This path is reserved by Openlink.' }, 'paths')).toBe(
      'This path is reserved by Openlink.',
    );
    expect(firstFieldError({ paths: 'Required.', 'paths.0': 'Other' }, 'paths')).toBe('Required.');
    expect(firstFieldError({}, 'paths')).toBeUndefined();
  });
});
