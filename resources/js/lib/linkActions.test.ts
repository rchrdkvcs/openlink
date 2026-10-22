import { router } from '@inertiajs/vue3';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { unarchiveLink } from './linkActions';
import { toast } from './toast';

vi.mock('@inertiajs/vue3', () => ({ router: { post: vi.fn() } }));
vi.mock('./toast', () => ({ toast: vi.fn() }));

beforeEach(() => {
  vi.clearAllMocks();
  vi.stubGlobal('route', (name: string, id: number) => `${name}/${id}`);
});

describe('unarchiveLink', () => {
  it('refreshes through Inertia and closes the selection only after server success', () => {
    const onSuccess = vi.fn();
    const link = { id: 7, slug: 'launch', short_url: 'https://go.example.test/launch', revision: 3 };

    unarchiveLink(link, onSuccess);

    expect(router.post).toHaveBeenCalledWith('short-links.unarchive/7', { expected_revision: 3 }, expect.any(Object));
    expect(onSuccess).not.toHaveBeenCalled();
    expect(toast).not.toHaveBeenCalled();
    const options = vi.mocked(router.post).mock.calls[0][2]!;
    expect(options).toMatchObject({ preserveState: true, preserveScroll: true });
    options.onSuccess?.({} as never);
    expect(onSuccess).toHaveBeenCalledOnce();
    expect(toast).toHaveBeenCalledWith({ title: 'Link unarchived', description: 'go.example.test/launch' });
  });

  it('keeps the selection and reports a rejected mutation', () => {
    const onSuccess = vi.fn();
    unarchiveLink({ id: 7, slug: 'launch', short_url: 'https://go.example.test/launch', revision: 3 }, onSuccess);

    const options = vi.mocked(router.post).mock.calls[0][2]!;
    options.onError?.({ link: 'This link could not be updated.' });

    expect(onSuccess).not.toHaveBeenCalled();
    expect(toast).toHaveBeenCalledWith({
      title: 'Could not unarchive this link',
      description: 'This link could not be updated.',
      tone: 'danger',
    });
  });
});
