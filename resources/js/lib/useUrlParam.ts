import { type Ref, ref, watch } from 'vue';

export function useNumericUrlParam(name: string): Ref<number | null> {
  const value = ref<number | null>(Number(new URL(window.location.href).searchParams.get(name)) || null);

  watch(value, (current) => {
    const url = new URL(window.location.href);
    if (current) url.searchParams.set(name, String(current));
    else url.searchParams.delete(name);
    window.history.replaceState(window.history.state, '', url);
  });

  return value;
}
