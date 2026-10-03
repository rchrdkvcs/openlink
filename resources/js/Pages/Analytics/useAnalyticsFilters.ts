import { computed } from 'vue';

import {
  FILTER_KEYS,
  filterQuery,
  hasActiveFilters,
  initialFilterState,
  isRangeReady,
  type AppliedFilters,
} from '@/lib/analyticsFilters';
import { useServerFilters } from '@/lib/useServerFilters';

export function useAnalyticsFilters(applied: AppliedFilters) {
  const initial = initialFilterState(applied);
  const { filters, loading, reload, update } = useServerFilters({
    url: () => route('analytics.index'),
    source: () => initial,
    only: ['report', 'filters'],
    debounced: ['range', ...FILTER_KEYS],
    delay: 0,
    query: filterQuery,
    ready: isRangeReady,
    replace: false,
  });

  function applyCustomRange() {
    if (filters.value.from && filters.value.to) reload();
  }

  function clearFilters() {
    update(Object.fromEntries(FILTER_KEYS.map((key) => [key, ''])));
  }

  function exportCsv() {
    window.location.href = route('analytics.export') + '?' + new URLSearchParams(filterQuery(filters.value)).toString();
  }

  return {
    state: filters,
    loading,
    hasActiveFilters: computed(() => hasActiveFilters(filters.value)),
    applyCustomRange,
    clearFilters,
    exportCsv,
  };
}
