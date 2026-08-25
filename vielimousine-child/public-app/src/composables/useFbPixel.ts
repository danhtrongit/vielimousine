// Meta Pixel helper cho trang khách.
// - Chỉ fire khi window.fbq đã có sẵn (pixel VL3 nhúng qua GTM/plugin) → guard an toàn.
// - dedupKey: chặn fire trùng (refresh/poll) qua localStorage, và dùng làm eventID để
//   Meta chống trùng phía server (sẵn sàng cho Conversions API sau này).
// - Không bao giờ để lỗi tracking làm gãy UX (nuốt mọi throw).

declare global {
  interface Window {
    fbq?: (...args: unknown[]) => void;
  }
}

/** true nếu key ĐÃ được đánh dấu trước đó (⇒ bỏ qua); false nếu lần đầu (đánh dấu & cho fire). */
function alreadyFired(key: string): boolean {
  try {
    if (localStorage.getItem(key)) return true;
    localStorage.setItem(key, String(Date.now()));
  } catch {
    // localStorage bị chặn (private mode…) → coi như chưa fire, vẫn cho chạy.
  }
  return false;
}

export function fbTrack(
  event: string,
  params: Record<string, unknown> = {},
  opts: { dedupKey?: string; eventId?: string } = {},
): void {
  if (typeof window === 'undefined' || typeof window.fbq !== 'function') return;
  if (opts.dedupKey && alreadyFired(opts.dedupKey)) return;
  try {
    const eventId = opts.eventId ?? opts.dedupKey;
    if (eventId) window.fbq('track', event, params, { eventID: eventId });
    else window.fbq('track', event, params);
  } catch {
    /* tracking không được làm gãy UX */
  }
}
