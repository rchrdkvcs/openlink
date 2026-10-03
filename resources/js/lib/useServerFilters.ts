import { router } from '@inertiajs/vue3';
import { onScopeDispose, type Ref, ref, watch } from 'vue';

export type FilterValues = Record<string, string>;

type ParamValue = string | number | null | undefined;

export type ServerFiltersOptions<F extends FilterValues> = {
  url: () => string;
  source: () => F;
  only: string[];
  debounced: (keyof F)[];
  delay?: number;
  query?: (filters: F) => Record<string, ParamValue>;
  ready?: (filters: F) => boolean;
  replace?: boolean;
  replacePages?: boolean;
};

export function compactParams(params: Record<string, ParamValue>): Record<string, string | number> {
  return Object.fromEntries(
    Object.entries(params).filter(
      (entry): entry is [string, string | number] => entry[1] !== '' && entry[1] !== null && entry[1] !== undefined,
    ),
  );
}

export function useServerFilters<F extends FilterValues>(options: ServerFiltersOptions<F>) {
  const filters = ref({ ...options.source() }) as Ref<F>;
  const loading = ref(false);
  const delay = options.delay ?? 250;
  const replace = options.replace ?? true;
  const query = options.query ?? ((values: F) => values);
  let timer: ReturnType<typeof setTimeout> | undefined;

  function visit(extra: Record<string, ParamValue> = {}, replaceHistory = replace) {
    clearTimeout(timer);
    loading.value = true;
    router.get(options.url(), compactParams({ ...query(filters.value), ...extra }), {
      preserveState: true,
      preserveScroll: true,
      replace: replaceHistory,
      only: options.only,
      onFinish: () => {
        loading.value = false;
      },
    });
  }

  watch(
    options.debounced.map((key) => () => filters.value[key]),
    () => {
      clearTimeout(timer);
      if (options.ready && !options.ready(filters.value)) return;
      if (delay > 0) timer = setTimeout(visit, delay);
      else visit();
    },
  );

  watch(options.source, (value) => {
    filters.value = { ...value };
  });

  onScopeDispose(() => clearTimeout(timer));

  return {
    filters,
    loading,
    reload: () => visit(),
    goToPage: (page: number) => visit({ page }, options.replacePages ?? replace),
    update: (values: Partial<F>) => {
      filters.value = { ...filters.value, ...values };
    },
  };
}
