import { describe, expect, it } from 'vitest';

import {
  dnsRecordRequirements,
  domainNeedsSetup,
  domainStatusLabel,
  domainStatusVariant,
  domainSubtitle,
  setupDescription,
  setupStep,
  transferTargetOptions,
} from '@/lib/domains';
import type { DomainSetup } from '@/types/payloads';

const custom = (status: string) => ({ status, is_default: false });

describe('domain status presentation', () => {
  it('treats the instance default as a neutral, always-ready domain', () => {
    const domain = { status: 'pending_verification', is_default: true };
    expect(domainStatusVariant(domain)).toBe('outline');
    expect(domainStatusLabel(domain)).toBe('Default');
    expect(domainSubtitle(domain)).toBe('Instance default, available to every workspace');
    expect(domainNeedsSetup(domain)).toBe(false);
  });

  it('maps custom domain statuses to badges', () => {
    expect(domainStatusVariant(custom('active'))).toBe('success');
    expect(domainStatusVariant(custom('failed_verification'))).toBe('danger');
    expect(domainStatusVariant(custom('disabled'))).toBe('default');
    expect(domainStatusVariant(custom('ownership_verified'))).toBe('warning');
    expect(domainStatusLabel(custom('ownership_verified'))).toBe('Almost ready');
    expect(domainStatusLabel(custom('failed_verification'))).toBe('Setup needed');
    expect(domainStatusLabel(custom('unknown'))).toBe('unknown');
  });

  it('only asks for setup while a custom domain is not active or disabled', () => {
    expect(domainNeedsSetup(custom('pending_verification'))).toBe(true);
    expect(domainNeedsSetup(custom('ownership_verified'))).toBe(true);
    expect(domainNeedsSetup(custom('active'))).toBe(false);
    expect(domainNeedsSetup(custom('disabled'))).toBe(false);
    expect(domainSubtitle(custom('ownership_verified'))).toBe('Ownership verified, waiting for DNS to point here');
    expect(domainSubtitle(custom('pending_verification'))).toBe('Waiting for DNS records');
  });
});

describe('transferTargetOptions', () => {
  it('excludes the current workspace', () => {
    const workspaces = [
      { id: 1, name: 'One', slug: 'one' },
      { id: 2, name: 'Two', slug: 'two' },
    ];
    expect(transferTargetOptions(workspaces, 1)).toEqual([{ value: 2, label: 'Two' }]);
  });
});

describe('domain setup', () => {
  it('derives the wizard step from the domain', () => {
    expect(setupStep(null)).toBe(1);
    expect(setupStep({ status: 'pending_verification' })).toBe(2);
    expect(setupStep({ status: 'active' })).toBe(3);
    expect(setupDescription(3)).toBe('Verified and serving short links.');
  });

  it('describes the TXT and pointing records', () => {
    const domain: DomainSetup = {
      id: 1,
      hostname: 'go.example.com',
      status: 'ownership_verified',
      is_default: false,
      expected_txt_name: '_openlink.go.example.com',
      expected_txt: 'token',
      failure_reason: 'TXT missing',
      ownership_verified: true,
      dns_pointed: false,
      dns_check_error: 'No A record',
      dns_record: { type: 'A', value: '1.2.3.4' },
    };

    const [txt, pointing] = dnsRecordRequirements(domain);

    expect(txt).toMatchObject({ type: 'TXT', name: '_openlink.go.example.com', done: true, error: null });
    expect(pointing).toMatchObject({ type: 'A', name: 'go.example.com', value: '1.2.3.4', done: false });
    expect(pointing.error).toBe('No A record');
  });
});
