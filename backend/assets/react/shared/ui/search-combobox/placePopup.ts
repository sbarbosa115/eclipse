import type {CSSProperties} from 'react';

/** The list's tallest (matches `.combo-popup` in admin.css). */
const MAX_HEIGHT = 280;
/** Below this much room under the box, the list opens above it when there is more room there. */
const MIN_BELOW = 160;
const GAP = 4;
const MARGIN = 8;

/**
 * Where a search box's list goes (it is position: fixed, so it never scrolls into view by itself): under the box,
 * or above it when the box sits near the bottom of the screen; never taller than the room it has.
 */
export function placePopup(
  box: {top: number; bottom: number; left: number; width: number},
  viewportHeight: number,
): CSSProperties {
  const below = viewportHeight - box.bottom - GAP - MARGIN;
  const above = box.top - GAP - MARGIN;
  const common = {left: box.left, minWidth: Math.max(box.width, 260)};
  if (below < MIN_BELOW && above > below) {
    return {
      bottom: viewportHeight - box.top + GAP,
      ...common,
      maxHeight: Math.min(MAX_HEIGHT, above),
    };
  }

  return {
    top: box.bottom + GAP,
    ...common,
    maxHeight: Math.min(MAX_HEIGHT, below),
  };
}
