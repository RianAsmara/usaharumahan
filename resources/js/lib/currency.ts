export function formatRupiah(amount: number): string {
    return `Rp ${Math.round(amount).toLocaleString('id-ID')}`;
}
