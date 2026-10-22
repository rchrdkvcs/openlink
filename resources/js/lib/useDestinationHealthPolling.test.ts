import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, ref } from 'vue';

import { useDestinationHealthPolling } from './useDestinationHealthPolling';

vi.mock('@inertiajs/vue3', () => ({ router: { reload: vi.fn() } }));

afterEach(() => {
  vi.clearAllMocks();
  vi.useRealTimers();
});

describe('Destination Health refresh', () => {
  it('reads a worker result after more than a minute in the queue and then stops', async () => {
    vi.useFakeTimers();
    const pending = ref(true);
    const result = ref('unchecked');
    const scope = effectScope();
    vi.mocked(router.reload).mockImplementation((options) => {
      if (Date.now() - started > 60000) {
        result.value = 'http_failure';
        pending.value = false;
      }
      options?.onFinish?.({} as never);
    });
    const started = Date.now();

    scope.run(() => useDestinationHealthPolling(() => pending.value, ['destinationHealth', 'navigation']));
    await vi.advanceTimersByTimeAsync(66000);
    await nextTick();

    expect(result.value).toBe('http_failure');
    expect(router.reload).toHaveBeenCalledTimes(21);
    expect(router.reload).toHaveBeenLastCalledWith(
      expect.objectContaining({ only: ['destinationHealth', 'navigation'] }),
    );
    await vi.advanceTimersByTimeAsync(30000);
    expect(router.reload).toHaveBeenCalledTimes(21);
    scope.stop();
  });

  it('waits for the current refresh and cancels timers when the page leaves', async () => {
    vi.useFakeTimers();
    const scope = effectScope();
    let finish: (() => void) | undefined;
    vi.mocked(router.reload).mockImplementation((options) => {
      finish = () => options?.onFinish?.({} as never);
    });
    scope.run(() => useDestinationHealthPolling(() => true, ['issues', 'navigation']));

    await vi.advanceTimersByTimeAsync(15000);
    expect(router.reload).toHaveBeenCalledTimes(1);
    scope.stop();
    finish?.();
    await vi.advanceTimersByTimeAsync(15000);
    expect(router.reload).toHaveBeenCalledTimes(1);
  });
});
