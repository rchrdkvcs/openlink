import { watch, type Ref } from 'vue';

/** Controlled dialogs may be opened from menus that disappear before dismissal. */
export function useDialogFocus(open: Readonly<Ref<boolean>>) {
  let openers: HTMLElement[] = [];
  watch(
    open,
    (value) => {
      if (!value || typeof document === 'undefined') return;
      openers = [];
      let element = document.activeElement as HTMLElement | null;
      if (element) openers.push(element);
      while (element) {
        if (element.id) {
          const trigger = Array.from(document.querySelectorAll<HTMLElement>('[aria-controls]')).find(
            (candidate) => candidate.getAttribute('aria-controls') === element?.id,
          );
          if (trigger && !openers.includes(trigger)) openers.push(trigger);
        }
        element = element.parentElement;
      }
    },
    { immediate: true, flush: 'sync' },
  );

  return (event: Event) => {
    const opener = openers.find((element) => element.isConnected);
    if (opener) {
      event.preventDefault();
      opener.focus();
    }
  };
}
