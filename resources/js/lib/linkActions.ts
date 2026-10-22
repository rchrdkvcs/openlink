import { router } from '@inertiajs/vue3';

import { confirmAction } from '@/lib/confirm';
import { displayUrl } from '@/lib/links';
import { toast } from '@/lib/toast';

type LinkRef = { id: number; slug: string; short_url: string };

export function createQrCodeFor(link: LinkRef) {
  router.post(
    route('qr-codes.store'),
    { name: link.slug, short_link_id: link.id },
    {
      onError: (errors) => toast({ title: Object.values(errors)[0] ?? 'Could not create the QR code', tone: 'danger' }),
    },
  );
}

export function moveLinkToFolder(link: { id: number }, folderId: number | null, folderName?: string) {
  router.post(
    route('short-links.move', link.id),
    { folder_id: folderId },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast({ title: folderName ? `Moved to ${folderName}` : 'Moved out of folders' }),
    },
  );
}

export function archiveLink(link: LinkRef, onSuccess?: () => void) {
  router.post(
    route('short-links.archive', link.id),
    {},
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        toast({ title: 'Link archived', description: displayUrl(link.short_url) });
        onSuccess?.();
      },
    },
  );
}

export function unarchiveLink(link: LinkRef & { revision: number }, onSuccess?: () => void) {
  router.post(
    route('short-links.unarchive', link.id),
    { expected_revision: link.revision },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        toast({ title: 'Link unarchived', description: displayUrl(link.short_url) });
        onSuccess?.();
      },
      onError: (errors) =>
        toast({ title: 'Could not unarchive this link', description: Object.values(errors)[0], tone: 'danger' }),
    },
  );
}

export async function deleteLink(link: LinkRef, onSuccess?: () => void) {
  const confirmed = await confirmAction({
    title: 'Delete this link permanently?',
    message: `${displayUrl(link.short_url)} stops working immediately and its slug becomes available again. Analytics are kept.`,
    confirmLabel: 'Delete link',
    destructive: true,
  });

  if (!confirmed) return;

  router.delete(route('short-links.destroy', link.id), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      toast({ title: 'Link deleted' });
      onSuccess?.();
    },
  });
}

export type BulkLinkAction = 'move' | 'add_tags' | 'remove_tags' | 'enable' | 'disable' | 'archive';

export type BulkLinkRequest = {
  action: BulkLinkAction;
  ids: number[];
  folder_id?: number | null;
  tags?: string[];
};

function bulkFailure(description: string) {
  toast({ title: 'Nothing was changed', description, tone: 'danger', duration: 8000 });
}

export function bulkUpdateLinks(request: BulkLinkRequest, callbacks: { onSuccess: () => void; onFinish?: () => void }) {
  router.post(route('short-links.bulk'), request, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: callbacks.onSuccess,
    onError: (errors) => bulkFailure(Object.values(errors)[0] ?? 'The selected links could not be updated.'),
    onHttpException: (response) => {
      bulkFailure(
        response.status === 403
          ? 'You don’t have permission to change these links.'
          : 'The server could not update the selected links. Try again.',
      );
      return false;
    },
    onNetworkError: () => {
      bulkFailure('The request did not reach the server. Check your connection and try again.');
      return false;
    },
    onFinish: callbacks.onFinish,
  });
}
