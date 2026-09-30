/** Amounts travel as integer paise; show them as ₹ with Indian digit grouping. */
export function formatRupees(paise) {
    if (paise === null || paise === undefined) return '—';
    const rupees = paise / 100;
    return `₹${rupees.toLocaleString('en-IN', {
        minimumFractionDigits: paise % 100 === 0 ? 0 : 2,
        maximumFractionDigits: 2,
    })}`;
}

/** "1,500.50" → 150050. Returns null when the input is not a valid amount. */
export function toPaise(input) {
    const cleaned = String(input ?? '').replace(/[,₹\s]/g, '');
    if (!/^\d+(\.\d{1,2})?$/.test(cleaned)) return null;
    const [whole, fraction = ''] = cleaned.split('.');
    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
}

export function paiseToInput(paise) {
    return paise % 100 === 0 ? String(paise / 100) : (paise / 100).toFixed(2);
}
