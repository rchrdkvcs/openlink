import { describe, expect, it } from 'vitest';

import {
  dnsRecordRequirements,
  domainNeedsSetup,
  domainStatusLabel,
  domainStatusVariant,
  domainEdge,
  domainSubtitle,
  setupActionLabel,
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

  it('flags active domains that need a DNS update without hiding that they are active', () => {
    const migrated = { status: 'active', is_default: false, needs_dns_update: true };

    expect(domainStatusLabel(migrated)).toBe('Update DNS');
    expect(domainStatusVariant(migrated)).toBe('warning');
    expect(domainSubtitle(migrated)).toBe('Its DNS record needs an update to keep working');
    expect(domainNeedsSetup(migrated)).toBe(true);
    expect(setupActionLabel(migrated)).toBe('Update DNS');
    expect(setupActionLabel(custom('pending_verification'))).toBe('Continue setup');
    expect(domainNeedsSetup({ ...migrated, is_default: true })).toBe(false);
  });

  it('tells members when HTTPS is being issued', () => {
    expect(domainSubtitle({ ...custom('failed_verification'), edge_status: 'issuing' })).toBe(
      'CNAME found, issuing HTTPS…',
    );
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
    expect(setupStep({ status: 'active', needs_dns_update: true })).toBe(2);
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

    const [txt, pointing] = dnsRecordRequirements(domain, 'direct');

    expect(txt).toMatchObject({ type: 'TXT', name: '_openlink.go.example.com', done: true, error: null });
    expect(pointing).toMatchObject({ type: 'A', name: 'go.example.com', value: '1.2.3.4', done: false });
    expect(pointing.purpose).toBe('Sends visitors to this server');
    expect(pointing.error).toBe('No A record');

    const cnameDomain: DomainSetup = { ...domain, dns_record: { type: 'CNAME', value: 'cname.example.com' } };
    const [, edgePointing] = dnsRecordRequirements(cnameDomain, 'cloudflare');

    expect(edgePointing).toMatchObject({ type: 'CNAME', value: 'cname.example.com', progress: 'waiting' });
    expect(edgePointing.purpose).toContain('HTTPS automatically');
  });

  it('follows the certificate on the Cloudflare edge', () => {
    const domain: DomainSetup = {
      id: 1,
      hostname: 'go.example.com',
      status: 'failed_verification',
      is_default: false,
      expected_txt_name: '_openlink.go.example.com',
      expected_txt: 'token',
      failure_reason: 'TXT missing',
      ownership_verified: false,
      dns_pointed: false,
      dns_check_error: 'HTTPS setup starts as soon as Openlink finds the TXT or CNAME record.',
      edge_status: 'waiting',
      dns_record: { type: 'CNAME', value: 'cname.example.com' },
    };

    const [, waiting] = dnsRecordRequirements(domain, 'cloudflare');
    expect(waiting).toMatchObject({ progress: 'waiting', statusLabel: 'Waiting', note: null, done: false });
    expect(waiting.error).toContain('starts as soon as');

    const [, issuing] = dnsRecordRequirements(
      { ...domain, edge_status: 'issuing', dns_check_error: null },
      'cloudflare',
    );
    expect(issuing).toMatchObject({ progress: 'issuing', statusLabel: 'Issuing HTTPS', done: false });
    expect(issuing.note).toBe('CNAME found, issuing HTTPS…');
    expect(issuing.error).toBeNull();

    const [, connected] = dnsRecordRequirements(
      { ...domain, edge_status: 'connected', dns_check_error: null },
      'cloudflare',
    );
    expect(connected).toMatchObject({ progress: 'done', statusLabel: 'Found', done: true });

    const [, direct] = dnsRecordRequirements({ ...domain, edge_status: 'issuing', dns_pointed: true }, 'direct');
    expect(direct.progress).toBe('done');
  });

  it('asks migrated domains to replace their record with the CNAME', () => {
    const domain: DomainSetup = {
      id: 1,
      hostname: 'go.example.com',
      status: 'active',
      is_default: false,
      expected_txt_name: '_openlink.go.example.com',
      expected_txt: 'token',
      failure_reason: null,
      ownership_verified: true,
      dns_pointed: true,
      dns_check_error: null,
      edge_status: 'waiting',
      needs_dns_update: true,
      dns_record: { type: 'CNAME', value: 'cname.example.com' },
    };

    const [txt, pointing] = dnsRecordRequirements(domain, 'cloudflare');

    expect(txt.done).toBe(true);
    expect(pointing.done).toBe(false);
    expect(pointing.error).toContain('Replace the current record');
  });

  it('describes each domain edge from one map', () => {
    expect(domainEdge('cloudflare').readyMessage).toContain('HTTPS is set up automatically');
    expect(domainEdge('cloudflare').managesDnsTarget).toBe(true);
    expect(domainEdge('direct').readyMessage).toContain('instance administrator');
    expect(domainEdge('direct').administratorAddsDomains).toBe(true);
    expect(domainEdge(undefined)).toBe(domainEdge('direct'));
  });
});
