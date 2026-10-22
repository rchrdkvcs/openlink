import { describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

import { HttpError } from '@/lib/http';

import type { ReadabilityRequest, ReadabilityResult } from '../qrReadability';
import { failureMessage, postReadability, useReadabilityCheck } from './useReadabilityCheck';

function request(url: string): ReadabilityRequest {
  return { url, body: { size: 1024 }, version: 0 };
}

function result(decoded: string): ReadabilityResult {
  return {
    size: 1024,
    formats: { png: { status: 'readable', decoded }, svg: { status: 'readable', decoded } },
    warnings: [],
    summary: {} as ReadabilityResult['summary'],
  };
}

function deferred() {
  const calls: {
    request: ReadabilityRequest;
    resolve: (value: ReadabilityResult) => void;
    reject: (error: unknown) => void;
  }[] = [];
  const fetcher = (checked: ReadabilityRequest) =>
    new Promise<ReadabilityResult>((resolve, reject) => {
      calls.push({ request: checked, resolve, reject });
    });

  return { calls, fetcher };
}

describe('useReadabilityCheck', () => {
  it('shows a result only while the checked draft is unchanged', async () => {
    const url = ref(request('/check?v=1'));
    const { calls, fetcher } = deferred();
    const { state, check } = useReadabilityCheck(url, ref(null), fetcher);

    expect(state.value.status).toBe('idle');
    const running = check();
    expect(state.value.status).toBe('checking');
    calls[0].resolve(result('a'));
    await running;

    expect(state.value).toEqual({ status: 'ready', result: result('a') });

    url.value = request('/check?v=2');
    expect(state.value.status).toBe('stale');
  });

  it('never presents a late response for an earlier draft as valid', async () => {
    const url = ref(request('/check?margin=2'));
    const { calls, fetcher } = deferred();
    const { state, check } = useReadabilityCheck(url, ref(null), fetcher);

    const first = check();
    url.value = request('/check?margin=0');
    const second = check();

    calls[0].resolve(result('old'));
    await first;
    expect(state.value.status).toBe('checking');

    calls[1].resolve(result('new'));
    await second;
    expect(state.value).toEqual({ status: 'ready', result: result('new') });
  });

  it('marks an in-flight result stale when the draft changes before it arrives', async () => {
    const url = ref(request('/check?size=1024'));
    const { calls, fetcher } = deferred();
    const { state, check } = useReadabilityCheck(url, ref(null), fetcher);

    const running = check();
    url.value = request('/check?size=512');
    calls[0].resolve(result('a'));
    await running;

    expect(state.value.status).toBe('stale');
  });

  it('reports blocked drafts and failures as distinct states', async () => {
    const url = ref(request('/check'));
    const blocked = ref<string | null>('Save the logo change to check it.');
    const { calls, fetcher } = deferred();
    const { state, check } = useReadabilityCheck(url, blocked, fetcher);

    await check();
    expect(calls).toHaveLength(0);
    expect(state.value).toEqual({ status: 'blocked', reason: 'Save the logo change to check it.' });

    blocked.value = null;
    const running = check();
    calls[0].reject(new HttpError(500, null));
    await running;

    expect(state.value).toEqual({
      status: 'failed',
      message: 'The readability check is unavailable right now. Try again in a moment.',
    });
  });

  it('explains validation and throttling failures', () => {
    expect(failureMessage(new HttpError(422, { message: 'The size must be at least 128.' }))).toBe(
      'The size must be at least 128.',
    );
    expect(failureMessage(new HttpError(429, null))).toMatch(/Too many checks/);
  });
});

describe('postReadability', () => {
  it('sends the draft as a JSON POST body instead of a query string', async () => {
    const fetchMock = vi.fn(async () => new Response(JSON.stringify(result('a')), { status: 200 }));
    vi.stubGlobal('fetch', fetchMock);
    vi.stubGlobal('document', { cookie: '' });

    await postReadability({ url: '/qr-codes/abc/readability', body: { payload: { password: 'secret' } }, version: 2 });

    expect(fetchMock).toHaveBeenCalledWith(
      '/qr-codes/abc/readability',
      expect.objectContaining({ method: 'POST', body: JSON.stringify({ payload: { password: 'secret' } }) }),
    );
    vi.unstubAllGlobals();
  });
});
