import { computed } from 'vue';

import { useServerFilters } from '@/lib/useServerFilters';

export const QR_SEARCH_DEBOUNCE = 300;

export function useQrCodeQuery(source: () => { search: string }) {
  const { filters, goToPage } = useServerFilters({
    url: () => route('qr-codes.index'),
    source,
    only: ['qrCodes', 'qrPagination', 'qrFilters'],
    debounced: ['search'],
    delay: QR_SEARCH_DEBOUNCE,
    replacePages: false,
  });

  const search = computed({
    get: () => filters.value.search,
    set: (value: string) => {
      filters.value = { ...filters.value, search: value };
    },
  });

  return { search, goToPage };
}
