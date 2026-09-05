// Meta Pixel helper cho trang khách.
// - Chỉ fire khi window.fbq đã có sẵn (pixel VL3 nhúng qua GTM/plugin) → guard an toàn.
// - dedupKey: chặn fire trùng (refresh/poll) qua localStorage, và dùng làm eventID để
//   Meta chống trùng phía server (sẵn sàng cho Conversions API sau này).
// - ttlMs: cửa sổ chặn trùng. Không truyền = chặn vĩnh viễn (Purchase: 1 lần/đơn).
//   Có truyền = cho fire lại sau ttlMs (InitiateCheckout: khách bỏ dở rồi trả lại sau).
// - Không bao giờ để lỗi tracking làm gãy UX (nuốt mọi throw).

declare global {
  interface Window {
    fbq?: (...args: unknown[]) => void;
  }
}

/**
 * Đánh dấu key là "đã fire". Trả về true nếu lần này được phép fire.
 *
 * ttlMs = undefined → key tồn tại là chặn vĩnh viễn; có ttlMs → key hết hạn thì fire lại.
 */
function claim(key: string, ttlMs?: number): boolean {
  try {
    const raw = localStorage.getItem(key);
    if (raw !== null) {
      const firedAt = Number(raw);
      const expired = ttlMs !== undefined && Number.isFinite(firedAt) && Date.now() - firedAt >= ttlMs;
      if (!expired) return false;
    }
    localStorage.setItem(key, String(Date.now()));
  } catch {
    // localStorage bị chặn (private mode…) → coi như chưa fire, vẫn cho chạy.
  }
  return true;
}

/** @returns true nếu event đã được đẩy sang fbq (dùng để biết có cần chờ flush beacon). */
export function fbTrack(
  event: string,
  params: Record<string, unknown> = {},
  opts: { dedupKey?: string; eventId?: string; ttlMs?: number } = {},
): boolean {
  if (typeof window === 'undefined' || typeof window.fbq !== 'function') return false;
  if (opts.dedupKey && !claim(opts.dedupKey, opts.ttlMs)) return false;
  try {
    const eventId = opts.eventId ?? opts.dedupKey;
    if (eventId) window.fbq('track', event, params, { eventID: eventId });
    else window.fbq('track', event, params);
    return true;
  } catch {
    /* tracking không được làm gãy UX */
    return false;
  }
}
