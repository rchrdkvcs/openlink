import { computed, onUnmounted, ref, watch, type Ref } from 'vue';

import type { ShortLinkOption } from './types';

export function useShortLinkSearch(initial: ShortLinkOption[], selectedId: Ref<string | number>) {
  const search = ref('');
  const links = ref(initial);
  const options = computed(() =>
    links.value.map((link) => ({ value: link.id, label: `${link.short_url} → ${link.destination_url}` })),
  );
  let timer: ReturnType<typeof setTimeout> | undefined;
  let requestNumber = 0;

  watch(search, (term) => {
    clearTimeout(timer);
    const currentRequest = ++requestNumber;
    timer = setTimeout(async () => {
      if (!term.trim()) {
        const selected = links.value.find((link) => link.id === Number(selectedId.value));
        links.value = selected && !initial.some((link) => link.id === selected.id) ? [selected, ...initial] : initial;
        return;
      }

      try {
        const response = await fetch(route('qr-codes.short-links', { search: term }), {
          headers: { Accept: 'application/json' },
        });
        if (!response.ok || currentRequest !== requestNumber) return;

        const result = (await response.json()) as { data: ShortLinkOption[] };
        if (currentRequest !== requestNumber) return;
        const selected = links.value.find((link) => link.id === Number(selectedId.value));
        links.value =
          selected && !result.data.some((link) => link.id === selected.id) ? [selected, ...result.data] : result.data;
      } catch {}
    }, 250);
  });

  onUnmounted(() => clearTimeout(timer));

  return { search, links, options };
}
