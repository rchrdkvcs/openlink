import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, ref } from 'vue';

import { compactParams, useServerFilters } from './useServerFilters';

const get = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({ router: { get } }));

type Filters = { search: string; status: string; folder: string };

function setup(initial: Filters) {
  const source = ref(initial);
  const scope = effectScope();
  const api = scope.run(() =>
    useServerFilters({
      url: () => '/links',
      source: () => source.value,
      only: ['links'],
      debounced: ['search', 'status'],
    }),
  )!;

  return { source, scope, ...api };
}

beforeEach(() => {
  vi.useFakeTimers();
  get.mockReset();
});

afterEach(() => {
  vi.useRealTimers();
});

describe('compactParams', () => {
  it('drops empty values but keeps zero', () => {
    expect(compactParams({ search: '', status: 'active', page: 0, tag: null, folder: undefined })).toEqual({
      status: 'active',
      page: 0,
    });
  });
});

describe('useServerFilters', () => {
  it('debounces watched filters into one partial visit', async () => {
    const { filters } = setup({ search: '', status: '', folder: '3' });
    filters.value.search = 'a';
    await nextTick();
    filters.value.search = 'ab';
    await nextTick();
    vi.advanceTimersByTime(249);
    expect(get).not.toHaveBeenCalled();

    vi.advanceTimersByTime(1);
    expect(get).toHaveBeenCalledTimes(1);
    expect(get).toHaveBeenCalledWith(
      '/links',
      { search: 'ab', folder: '3' },
      { preserveState: true, preserveScroll: true, replace: true, only: ['links'], onFinish: expect.any(Function) },
    );
  });

  it('tracks loading until the visit finishes', async () => {
    const { filters, loading } = setup({ search: '', status: '', folder: '' });
    filters.value.status = 'active';
    await nextTick();
    vi.runAllTimers();
    expect(loading.value).toBe(true);

    get.mock.calls[0][2].onFinish();
    expect(loading.value).toBe(false);
  });

  it('ignores filters that are not debounced', async () => {
    const { filters } = setup({ search: '', status: '', folder: '' });
    filters.value.folder = '4';
    await nextTick();
    vi.runAllTimers();
    expect(get).not.toHaveBeenCalled();
  });

  it('syncs from the server without visiting again', async () => {
    const { source, filters } = setup({ search: 'a', status: '', folder: '' });
    source.value = { search: 'a', status: '', folder: '' };
    await nextTick();
    vi.runAllTimers();
    expect(filters.value.search).toBe('a');
    expect(get).not.toHaveBeenCalled();
  });

  it('visits a page immediately and cancels a pending debounce', async () => {
    const { filters, goToPage, update } = setup({ search: '', status: '', folder: '' });
    update({ status: 'expired' });
    await nextTick();
    goToPage(2);
    vi.runAllTimers();
    expect(get).toHaveBeenCalledTimes(1);
    expect(get.mock.calls[0][1]).toEqual({ status: 'expired', page: 2 });
    expect(filters.value.status).toBe('expired');
  });

  it('visits immediately with a custom query once ready, pushing history', async () => {
    const scope = effectScope();
    const { filters, goToPage } = scope.run(() =>
      useServerFilters({
        url: () => '/analytics',
        source: () => ({ range: '30d', from: '', to: '' }),
        only: ['report'],
        debounced: ['range'],
        delay: 0,
        query: (values) => ({ range: values.range, from: values.range === 'custom' ? values.from : '' }),
        ready: (values) => values.range !== 'custom' || values.from !== '',
        replace: false,
      }),
    )!;
    filters.value = { range: 'custom', from: '', to: '' };
    await nextTick();
    expect(get).not.toHaveBeenCalled();

    filters.value = { range: '7d', from: '2026-01-01', to: '' };
    await nextTick();
    expect(get).toHaveBeenCalledTimes(1);
    expect(get.mock.calls[0][1]).toEqual({ range: '7d' });
    expect(get.mock.calls[0][2].replace).toBe(false);

    goToPage(2);
    expect(get.mock.calls[1][2].replace).toBe(false);
    scope.stop();
  });

  it('can push page visits while replacing filter visits', async () => {
    const scope = effectScope();
    const { filters, goToPage } = scope.run(() =>
      useServerFilters({
        url: () => '/qr-codes',
        source: () => ({ search: '' }),
        only: ['qrCodes'],
        debounced: ['search'],
        delay: 300,
        replacePages: false,
      }),
    )!;
    filters.value.search = 'menu';
    await nextTick();
    vi.advanceTimersByTime(300);
    goToPage(3);
    expect(get.mock.calls.map((call) => [call[1], call[2].replace])).toEqual([
      [{ search: 'menu' }, true],
      [{ search: 'menu', page: 3 }, false],
    ]);
    scope.stop();
  });

  it('cancels the pending visit when its scope stops', async () => {
    const { filters, scope } = setup({ search: '', status: '', folder: '' });
    filters.value.search = 'x';
    await nextTick();
    scope.stop();
    vi.runAllTimers();
    expect(get).not.toHaveBeenCalled();
  });
});
