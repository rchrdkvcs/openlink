import { computed, ref, shallowRef, type Ref } from 'vue';

import { fetchJson, HttpError } from '@/lib/http';

import type { ReadabilityResult } from '../qrReadability';
import { createLatestOnly } from '../useShortLinkSearch';

export type ReadabilityFetcher = (url: string) => Promise<ReadabilityResult>;

export type ReadabilityState =
  | { status: 'blocked'; reason: string }
  | { status: 'idle' }
  | { status: 'checking' }
  | { status: 'stale'; result: ReadabilityResult }
  | { status: 'failed'; message: string }
  | { status: 'ready'; result: ReadabilityResult };

type Settled = { url: string; result: ReadabilityResult } | { url: string; message: string };

export function failureMessage(error: unknown): string {
  if (error instanceof HttpError && error.status === 422) {
    const body = error.body as { message?: string } | null;
    return body?.message ?? 'Fix the highlighted settings before checking.';
  }
  if (error instanceof HttpError && error.status === 429) return 'Too many checks. Wait a minute and try again.';

  return 'The readability check is unavailable right now. Try again in a moment.';
}

export function useReadabilityCheck(
  url: Ref<string>,
  blocked: Ref<string | null>,
  fetcher: ReadabilityFetcher = (target) => fetchJson<ReadabilityResult>(target),
) {
  const settled = shallowRef<Settled | null>(null);
  const pendingUrl = ref<string | null>(null);
  const requests = createLatestOnly();

  const state = computed<ReadabilityState>(() => {
    if (blocked.value) return { status: 'blocked', reason: blocked.value };
    if (pendingUrl.value === url.value) return { status: 'checking' };

    const last = settled.value;
    if (!last) return { status: 'idle' };
    if ('message' in last)
      return last.url === url.value ? { status: 'failed', message: last.message } : { status: 'idle' };

    return last.url === url.value ? { status: 'ready', result: last.result } : { status: 'stale', result: last.result };
  });

  async function check() {
    if (blocked.value) return;

    const target = url.value;
    const request = requests.next();
    pendingUrl.value = target;

    try {
      const result = await fetcher(target);
      if (requests.isLatest(request)) settled.value = { url: target, result };
    } catch (error) {
      if (requests.isLatest(request)) settled.value = { url: target, message: failureMessage(error) };
    } finally {
      if (requests.isLatest(request)) pendingUrl.value = null;
    }
  }

  return { state, check };
}
