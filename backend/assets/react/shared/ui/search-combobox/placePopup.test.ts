import {describe, expect, it} from 'vitest';
import {placePopup} from './placePopup';

const box = (top: number, bottom = top + 36) => ({
  top,
  bottom,
  left: 40,
  width: 300,
});

describe('where the list of a search box opens', () => {
  it('opens below the box when the list fits there', () => {
    expect(placePopup(box(100), 768)).toEqual({
      top: 140,
      left: 40,
      minWidth: 300,
      maxHeight: 280,
    });
  });

  it('opens above the box when there is no room below and more above, so it never leaves the screen', () => {
    // A field near the bottom of a laptop screen: 768 − 724 = 44 px below, 680 px above.
    expect(placePopup(box(688), 768)).toEqual({
      bottom: 84,
      left: 40,
      minWidth: 300,
      maxHeight: 280,
    });
  });

  it('shrinks to the room it has rather than run past the edge', () => {
    expect(placePopup(box(520), 768).maxHeight, '768 − 556 − 4 − 8 below').toBe(
      200,
    );
    expect(placePopup(box(250, 286), 420)).toMatchObject({
      bottom: 174,
      maxHeight: 238,
    });
  });

  it('is never narrower than 260 px', () => {
    expect(
      placePopup({top: 100, bottom: 136, left: 0, width: 120}, 768).minWidth,
    ).toBe(260);
  });
});
