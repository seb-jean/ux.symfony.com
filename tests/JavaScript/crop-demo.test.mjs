import { test } from "node:test";
import assert from "node:assert/strict";
import { FRAME, MAX_ZOOM, place, reorder, zoomRange } from "../../assets/demos/image/crop-demo.js";

test("the square stands at the center of the stage", () => {
  const { cropBox } = place({ width: 800, height: 600 }, { width: 1500, height: 2000 }, { x: 0, y: 250, width: 1500 });

  assert.equal(cropBox.width, 600 * FRAME);
  assert.equal(cropBox.height, cropBox.width);
  assert.equal(cropBox.left + cropBox.width / 2, 400);
  assert.equal(cropBox.top + cropBox.height / 2, 300);
});

test("the photo goes where the square shows the selection", () => {
  const selection = { x: 200, y: 600, width: 1000 };
  const { cropBox, canvas } = place({ width: 500, height: 500 }, { width: 1500, height: 2000 }, selection);
  const ratio = canvas.width / 1500;

  // Back to the pixels of the photo, the square starts at the selection and is as wide
  assert.ok(Math.abs((cropBox.left - canvas.left) / ratio - selection.x) < 1e-9);
  assert.ok(Math.abs((cropBox.top - canvas.top) / ratio - selection.y) < 1e-9);
  assert.ok(Math.abs(cropBox.width / ratio - selection.width) < 1e-9);
});

test("the photo covers the square at the farthest zoom", () => {
  // The whole width of an upright phone photo: the square shows all of it, nothing more
  const { cropBox, canvas } = place({ width: 600, height: 450 }, { width: 1500, height: 2000 }, { x: 0, y: 250, width: 1500 });
  const { min } = zoomRange(cropBox.width, { width: 1500, height: 2000 });

  assert.ok(Math.abs(canvas.width / 1500 - min) < 1e-9);
  assert.ok(canvas.left <= cropBox.left && canvas.left + canvas.width >= cropBox.left + cropBox.width);
});

test("the zoom goes from the photo that covers the square to MAX_ZOOM times closer", () => {
  assert.deepEqual(zoomRange(300, { width: 4000, height: 3000 }), { min: 0.1, max: 0.1 * MAX_ZOOM });
  assert.deepEqual(zoomRange(300, { width: 1500, height: 2000 }), { min: 0.2, max: 0.2 * MAX_ZOOM });
});

test("a photo dropped on another one takes its place", () => {
  assert.deepEqual(reorder(["a", "b", "c", "d"], "a", "c"), ["b", "c", "a", "d"]);
  assert.deepEqual(reorder(["a", "b", "c", "d"], "d", "a"), ["d", "a", "b", "c"]);
  assert.deepEqual(reorder(["a", "b", "c", "d"], "b", "c"), ["a", "c", "b", "d"]);
});

test("a photo dropped on itself or out of the grid changes nothing", () => {
  assert.deepEqual(reorder(["a", "b"], "a", "a"), ["a", "b"]);
  assert.deepEqual(reorder(["a", "b"], "a", "z"), ["a", "b"]);
  assert.deepEqual(reorder(["a", "b"], "z", "a"), ["a", "b"]);
});
