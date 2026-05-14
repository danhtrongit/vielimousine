const vndFormatter = new Intl.NumberFormat('vi-VN');

export function formatVND(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—';
  return vndFormatter.format(Math.round(value)) + 'đ';
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—';
  const d = new Date(value);
  if (isNaN(d.getTime())) return value;
  return d.toLocaleDateString('vi-VN', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—';
  const d = new Date(value.includes(' ') ? value.replace(' ', 'T') : value);
  if (isNaN(d.getTime())) return value;
  return d.toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' });
}
