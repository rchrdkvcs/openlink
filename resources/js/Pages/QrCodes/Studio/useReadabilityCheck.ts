import { computed, ref, shallowRef, type Ref } from 'vue';

import { fetchJson, HttpError } from '@/lib/http';

import type { ReadabilityRequest, ReadabilityResult } from '../qrReadability';
import { createLatestOnly } from '../useShortLinkSearch';

export type ReadabilityFetcher = (request: ReadabilityRequest) => Promise<ReadabilityResult>;

export type ReadabilityState =
  | { status: 'blocked'; reason: string }
  | { status: 'idle' }
  | { status: 'checking' }
  | { status: 'stale'; result: ReadabilityResult }
  | { status: 'failed'; message: string }
  | { status: 'ready'; result: ReadabilityResult };

type Settled = { key: string; result: ReadabilityResult } | { key: string; message: string };

export function postReadability(request: ReadabilityRequest): Promise<ReadabilityResult> {
  return fetchJson<ReadabilityResult>(request.url, { method: 'POST', body: JSON.stringify(request.body) });
}

export function failureMessage(error: unknown): string {
  if (error instanceof HttpError && error.status === 422) {
    const body = error.body as { message?: string } | null;
    return body?.message ?? 'Fix the highlighted settings before checking.';
  }
  if (error instanceof HttpError && error.status === 429) return 'Too many checks. Wait a minute and try again.';

  return 'The readability check is unavailable right now. Try again in a moment.';
}

export function useReadabilityCheck(
  request: Ref<ReadabilityRequest>,
  blocked: Ref<string | null>,
  fetcher: ReadabilityFetcher = postReadability,
) {
  const key = computed(() => JSON.stringify(request.value));
  const settled = shallowRef<Settled | null>(null);
  const pendingKey = ref<string | null>(null);
  const requests = createLatestOnly();

  const state = computed<ReadabilityState>(() => {
    if (blocked.value) return { status: 'blocked', reason: blocked.value };
    if (pendingKey.value === key.value) return { status: 'checking' };

    const last = settled.value;
    if (!last) return { status: 'idle' };
    if ('message' in last) {
      return last.key === key.value ? { status: 'failed', message: last.message } : { status: 'idle' };
    }

    return last.key === key.value ? { status: 'ready', result: last.result } : { status: 'stale', result: last.result };
  });

  async function check() {
    if (blocked.value) return;

    const target = request.value;
    const targetKey = key.value;
    const sequence = requests.next();
    pendingKey.value = targetKey;

    try {
      const result = await fetcher(target);
      if (requests.isLatest(sequence)) settled.value = { key: targetKey, result };
    } catch (error) {
      if (requests.isLatest(sequence)) settled.value = { key: targetKey, message: failureMessage(error) };
    } finally {
      if (requests.isLatest(sequence)) pendingKey.value = null;
    }
  }

  return { state, check };
}
