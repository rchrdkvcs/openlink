import { router } from '@inertiajs/vue3';
import { onScopeDispose, watch } from 'vue';

export function useDestinationHealthPolling(pending: () => boolean, only: string[]) {
  let timer: ReturnType<typeof setTimeout> | undefined;
  let disposed = false;
  let refreshing = false;

  function stop() {
    clearTimeout(timer);
    timer = undefined;
  }

  function schedule() {
    stop();
    if (disposed || !pending() || refreshing) return;
    timer = setTimeout(refresh, 3000);
  }

  function refresh() {
    if (disposed || !pending()) return;
    refreshing = true;
    router.reload({
      only,
      onFinish: () => {
        refreshing = false;
        schedule();
      },
    });
  }

  watch(pending, schedule, { immediate: true });
  onScopeDispose(() => {
    disposed = true;
    stop();
  });
}
