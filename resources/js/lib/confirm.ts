import { shallowRef } from 'vue';

export type ConfirmRequest = {
  title: string;
  message?: string;
  confirmLabel?: string;
  destructive?: boolean;
};

type PendingConfirm = ConfirmRequest & { resolve: (value: boolean) => void };

export const pendingConfirm = shallowRef<PendingConfirm | null>(null);

export function confirmAction(request: ConfirmRequest): Promise<boolean> {
  pendingConfirm.value?.resolve(false);

  return new Promise((resolve) => {
    pendingConfirm.value = { ...request, resolve };
  });
}

export function settleConfirm(value: boolean) {
  pendingConfirm.value?.resolve(value);
  pendingConfirm.value = null;
}
