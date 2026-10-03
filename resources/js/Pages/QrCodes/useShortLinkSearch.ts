import { onUnmounted, ref, watch, type Ref } from 'vue';

import type { ShortLinkOption } from './types';

export type ShortLinkFetcher = (term: string) => Promise<ShortLinkOption[] | null>;

export const SHORT_LINK_SEARCH_DELAY = 250;

export function keepSelected(
  results: ShortLinkOption[],
  current: ShortLinkOption[],
  selectedId: string | number,
): ShortLinkOption[] {
  const selected = current.find((link) => link.id === Number(selectedId));

  return selected && !results.some((link) => link.id === selected.id) ? [selected, ...results] : results;
}

export async function fetchShortLinks(term: string): Promise<ShortLinkOption[] | null> {
  const response = await fetch(route('qr-codes.short-links', { search: term }), {
    headers: { Accept: 'application/json' },
  });
  if (!response.ok) return null;

  return ((await response.json()) as { data: ShortLinkOption[] }).data;
}

export function createLatestOnly() {
  let latest = 0;

  return {
    next: () => ++latest,
    isLatest: (request: number) => request === latest,
  };
}

export function useShortLinkSearch(
  initial: ShortLinkOption[],
  selectedId: Ref<string | number>,
  fetcher: ShortLinkFetcher = fetchShortLinks,
) {
  const search = ref('');
  const links = ref(initial);
  const requests = createLatestOnly();
  let timer: ReturnType<typeof setTimeout> | undefined;

  watch(search, (term) => {
    clearTimeout(timer);
    const request = requests.next();
    timer = setTimeout(async () => {
      if (!term.trim()) {
        links.value = keepSelected(initial, links.value, selectedId.value);
        return;
      }

      try {
        const results = await fetcher(term);
        if (results === null || !requests.isLatest(request)) return;
        links.value = keepSelected(results, links.value, selectedId.value);
      } catch {}
    }, SHORT_LINK_SEARCH_DELAY);
  });

  onUnmounted(() => clearTimeout(timer));

  return { search, links };
}
