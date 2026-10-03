import { onMounted, onUnmounted } from 'vue';

export type LinkListShortcuts = {
  focusSearch: () => void;
  compose?: () => void;
  move: (delta: number) => void;
};

const IGNORED_TARGETS = 'input, textarea, select, [contenteditable="true"], [role="dialog"], [role="menu"]';

export function useLinkListKeyboard(shortcuts: LinkListShortcuts) {
  const actions: Record<string, (() => void) | undefined> = {
    '/': shortcuts.focusSearch,
    n: shortcuts.compose,
    j: () => shortcuts.move(1),
    ArrowDown: () => shortcuts.move(1),
    k: () => shortcuts.move(-1),
    ArrowUp: () => shortcuts.move(-1),
  };

  function onKeydown(event: KeyboardEvent) {
    const target = event.target as HTMLElement | null;
    if (target?.closest(IGNORED_TARGETS)) return;
    if (event.metaKey || event.ctrlKey || event.altKey) return;

    const action = actions[event.key];
    if (!action) return;

    event.preventDefault();
    action();
  }

  onMounted(() => document.addEventListener('keydown', onKeydown));
  onUnmounted(() => document.removeEventListener('keydown', onKeydown));
}
