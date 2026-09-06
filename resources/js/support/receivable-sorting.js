/**
 * Ordering rules for the Daftar Piutang table.
 *
 * Extracted from the Alpine component so the default the client actually
 * sees - newest invoice first - is covered by tests.
 */

/** The default the table opens with: newest invoice date first. */
export const defaultReceivableSort = Object.freeze({
    sortKey: 'issuedSort',
    sortDirection: 'desc',
});

/**
 * Direction a column should take the first time it is clicked.
 *
 * Dates and money read most usefully largest-first (newest invoice, latest
 * due date, biggest receivable); text reads A-Z. Nearest-due-date-first is
 * still one click away by toggling the Jatuh tempo column.
 */
export function initialDirectionFor(sortKey) {
    return ['issuedSort', 'dueSort', 'outstandingValue'].includes(sortKey) ? 'desc' : 'asc';
}

function compare(first, second, sortKey, sortDirection) {
    const a = first[sortKey];
    const b = second[sortKey];

    if (typeof a === 'number' && typeof b === 'number') {
        return sortDirection === 'asc' ? a - b : b - a;
    }

    return sortDirection === 'asc'
        ? String(a).localeCompare(String(b), 'id')
        : String(b).localeCompare(String(a), 'id');
}

/**
 * Sort a copy of the rows, breaking ties by newest invoice id.
 *
 * The tiebreak matters because invoices raised on the same day are common;
 * without it their relative order is whatever the engine happens to do, and
 * rows visibly reshuffle between renders.
 *
 * @param  {Array<Record<string, unknown>>} rows
 * @param  {string} sortKey
 * @param  {'asc'|'desc'} sortDirection
 * @return {Array<Record<string, unknown>>}
 */
export function sortReceivables(rows, sortKey, sortDirection) {
    return [...rows].sort((first, second) => {
        const primary = compare(first, second, sortKey, sortDirection);

        if (primary !== 0) {
            return primary;
        }

        return (Number(second.id) || 0) - (Number(first.id) || 0);
    });
}
