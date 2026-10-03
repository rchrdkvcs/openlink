export type UpdateState = 'pending' | 'running' | 'succeeded' | 'failed' | null;

export type UpdateStatus = {
  current: string;
  latest: { version: string; url: string } | null;
  available: boolean;
  canUpdate: boolean;
  state: UpdateState;
};

export function isUpdateInProgress(status: UpdateStatus | null): boolean {
  return status?.state === 'pending' || status?.state === 'running';
}

export function updateMessage(status: UpdateStatus | null): string {
  if (status?.state === 'pending') return 'Update requested…';
  if (status?.state === 'running') return 'Updating containers…';
  if (status?.state === 'failed') return 'Update failed. Check the updater logs and retry.';
  if (status?.available && !status.canUpdate)
    return 'A new release is available. Update through your deployment platform.';
  if (status?.available) return 'A new release is available.';
  if (!status || status.current === 'dev') return 'Development builds have no release version.';
  if (status.latest) return 'Up to date.';
  return 'Release information is temporarily unavailable.';
}
