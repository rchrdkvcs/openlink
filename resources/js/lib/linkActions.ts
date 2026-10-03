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
