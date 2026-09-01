/**
 * Naming rules for a single invoice line item.
 *
 * These live outside the Alpine component so the preview, the stored invoice
 * and the generated PDF can all agree on how a line is labelled.
 */

/**
 * The noun used in the generated "Sablon ..." description.
 *
 * It follows the product's own catalogue category, so a lid is never described
 * as a cup - which previously made lid rows read "Sablon Cup 12 Oz ..." and,
 * because the preview labelled rows by their description, hid the lid entirely.
 *
 * @param  {string} category  Product catalogue category, e.g. "Tutup / Lid".
 * @return {string}
 */
export function printedItemNoun(category = '') {
    const value = String(category ?? '');

    if (/tutup|lid/i.test(value)) {
        return 'Tutup';
    }

    if (/bowl/i.test(value)) {
        return 'Bowl';
    }

    return 'Cup';
}
