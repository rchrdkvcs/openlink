import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

import type { ShortLink } from '@/types/shortLinks';

import { activationTime, formatCountdown } from './activation';

export function useActivationCountdownWithReload(links: () => ShortLink[]) {
  const now = ref(Date.now());
  const reloadedIds = new Set<number>();
  let timer: ReturnType<typeof setInterval> | null = null;
  let reloading = false;

  function syncTimer() {
    const pending = links().some((link) => activationTime(link) !== null);

    if (pending && timer === null) {
      timer = setInterval(() => (now.value = Date.now()), 1000);
    } else if (!pending && timer !== null) {
      clearInterval(timer);
      timer = null;
    }
  }

  function reloadDueLinks(current: number) {
    if (reloading) return;

    const due = links().filter((link) => {
      const time = activationTime(link);
      return time !== null && time <= current && !reloadedIds.has(link.id);
    });
    if (due.length === 0) return;

    due.forEach((link) => reloadedIds.add(link.id));
    reloading = true;
    router.reload({ only: ['links'], onFinish: () => (reloading = false) });
  }

  watch(links, syncTimer, { immediate: true });
  watch(now, reloadDueLinks);

  onBeforeUnmount(() => {
    if (timer !== null) clearInterval(timer);
  });

  function countdownFor(link: ShortLink): string | null {
    const time = activationTime(link);

    return time === null ? null : formatCountdown(time - now.value);
  }

  return { countdownFor };
}
