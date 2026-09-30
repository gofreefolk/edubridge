/** Display status for an invoice: overdue wins over issued / partially paid. */
export function feeStatusKey(invoice) {
    if (invoice.status === 'void' || invoice.status === 'paid') return invoice.status;
    if (invoice.overdue) return 'overdue';
    return invoice.status;
}

export function feeStatusClass(invoice) {
    return {
        paid: 'bg-green-100 text-green-800',
        void: 'bg-slate-100 text-slate-500 line-through',
        overdue: 'bg-red-100 text-red-800',
        partially_paid: 'bg-amber-100 text-amber-800',
        issued: 'bg-blue-100 text-blue-800',
    }[feeStatusKey(invoice)];
}
