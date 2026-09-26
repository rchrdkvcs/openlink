import type { InjectionKey, Ref } from 'vue';

/**
 * Where floating layers (Select menus…) should portal to. Modal provides its
 * <dialog>: a modal dialog sits in the browser top layer and makes the rest of
 * the document inert, so anything portaled to <body> would render behind it
 * and be unclickable.
 */
export const portalTargetKey: InjectionKey<Ref<HTMLElement | undefined>> = Symbol('portalTarget');

/**
 * True while a Radix floating layer (select, dropdown menu) is open. Radix
 * handles Escape on `window`, after our `document` listeners, so containers
 * must check this to let Escape close the layer instead of the container.
 */
export function hasOpenFloatingLayer(): boolean {
  return document.querySelector('[data-radix-popper-content-wrapper]') !== null;
}
