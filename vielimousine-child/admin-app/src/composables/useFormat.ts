const vndFormatter = new Intl.NumberFormat('vi-VN');

export function formatVND(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—';
  return vndFormatter.format(Math.round(value)) + 'đ';
}

/**
 * Compact: 1500000 → "1.5M", 750000 → "750K", 950 → "950".
 */
export function formatCompact(value: number | null | undefined): string {
  if (value === null || value === undefined || value === 0) return '0';
  const abs = Math.abs(value);
  if (abs >= 1_000_000) {
    const m = value / 1_000_000;
    return Number.isInteger(m) ? `${m}M` : `${m.toFixed(1).replace(/\.0$/, '')}M`;
  }
  if (abs >= 1_000) return `${Math.round(value / 1_000)}K`;
  return String(value);
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

/**
 * Trả về 'YYYY-MM-DD' theo giờ địa phương (KHÔNG dùng UTC).
 * Phải dùng cái này thay cho `d.toISOString().slice(0,10)` khi so sánh
 * với `created_at` / `checkin` từ DB (vì backend lưu theo `current_time('mysql')`
 * — giờ địa phương WP, lệch UTC 7 tiếng ở VN).
 */
/**
 * Decode common HTML entities (&amp; &lt; &gt; &quot; &#39; &nbsp;).
 * Use when rendering names that come from WP post titles which may be HTML-encoded.
 */
export function decodeEntities(s: string | null | undefined): string {
  if (!s) return '';
  return s
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;/g, "'")
    .replace(/&nbsp;/g, ' ');
}

export function ymdLocal(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}
