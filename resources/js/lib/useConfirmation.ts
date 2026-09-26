import { shallowRef } from 'vue';

export type Confirmation = { title: string; description: string; action: () => void };

export function useConfirmation() {
  const confirmation = shallowRef<Confirmation | null>(null);
  function requestConfirmation(request: Confirmation) {
    confirmation.value = request;
  }
  return { confirmation, requestConfirmation };
}
