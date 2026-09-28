import type { InjectionKey, Ref } from 'vue';

export const portalTargetKey: InjectionKey<Ref<HTMLElement | undefined>> = Symbol('portalTarget');

export function hasOpenFloatingLayer(): boolean {
  return document.querySelector('[data-radix-popper-content-wrapper]') !== null;
}
