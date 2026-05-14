export function useCsvExport() {
  function escape(cell: unknown): string {
    if (cell === null || cell === undefined) return '';
    const s = String(cell);
    if (s.includes(',') || s.includes('"') || s.includes('\n')) {
      return `"${s.replace(/"/g, '""')}"`;
    }
    return s;
  }

  function downloadCsv(filename: string, headers: string[], rows: unknown[][]): void {
    const BOM = '﻿';
    const csv =
      BOM +
      headers.map(escape).join(',') +
      '\n' +
      rows.map((r) => r.map(escape).join(',')).join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  return { downloadCsv };
}
