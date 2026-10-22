export type RoutingSaveReceipt = { link_id: number; expected_revision: number; revision: number };

export function changedFields<T extends object>(draft: T, saved: T): Partial<T> {
  return Object.fromEntries(
    (Object.keys(draft) as (keyof T)[])
      .filter((key) => !Object.is(draft[key], saved[key]))
      .map((key) => [key, draft[key]]),
  ) as Partial<T>;
}
