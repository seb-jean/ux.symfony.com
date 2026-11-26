/*
 * Upload, Crop & Serve: the arithmetic of the grid and of the cropper.
 *
 * The square of the cropper stays at the center of its stage. The photo moves
 * and zooms under it, and always covers it: Cropper.js keeps it there (viewMode 1).
 */

// The share of the shorter side of the stage the square takes: the photo shows, dimmed, around it
export const FRAME = 0.76;

// The closest zoom: the square then shows a quarter of the shorter side of the photo
export const MAX_ZOOM = 4;

/**
 * The square at the center of the stage, and the photo placed so that the
 * selection fills it: the data of the crop box and of the canvas of Cropper.js.
 *
 * @param {{width: number, height: number}} stage the container of the cropper, in CSS pixels
 * @param {{width: number, height: number}} photo its natural size, in pixels
 * @param {{x: number, y: number, width: number}} selection a square, in pixels of the photo
 */
export function place(stage, photo, selection) {
    const size = Math.min(stage.width, stage.height) * FRAME;
    const left = (stage.width - size) / 2;
    const top = (stage.height - size) / 2;
    const ratio = size / selection.width;

    return {
        cropBox: { left, top, width: size, height: size },
        canvas: { left: left - selection.x * ratio, top: top - selection.y * ratio, width: photo.width * ratio },
    };
}

/**
 * The zoom ratios of Cropper.js for a square of this size: from the photo
 * that just covers it, to MAX_ZOOM times closer.
 *
 * @param {number} size of the square, in CSS pixels
 * @param {{width: number, height: number}} photo its natural size, in pixels
 */
export function zoomRange(size, photo) {
    const min = size / Math.min(photo.width, photo.height);

    return { min, max: min * MAX_ZOOM };
}

/**
 * The names of the photos once one of them is dropped on the place of another.
 *
 * @param {string[]} names in their order
 * @param {string} moved the photo dragged
 * @param {string} target the photo it was dropped on
 */
export function reorder(names, moved, target) {
    const to = names.indexOf(target);
    if (!names.includes(moved) || to < 0 || moved === target) {
        return names;
    }

    const order = names.filter((name) => name !== moved);
    order.splice(to, 0, moved);

    return order;
}
