// GTM dataLayer cho trang khách — yêu cầu từ team marketing (Quang Digi).
//
// booking_combo_success: bắn SAU KHI server xác nhận tạo đơn combo thành công (201),
// không bắn lúc khách bấm nút. Mỗi mã đơn chỉ bắn 1 lần (localStorage, vĩnh viễn).
// content_id / content_name lấy động theo combo (slug + tên bài khách sạn).

declare global {
  interface Window {
    dataLayer?: unknown[];
  }
}

export interface BookingComboSuccess {
  orderCode: string;
  hotelSlug: string;
  hotelName: string;
  value: number;
  currency?: string;
}

/** @returns true nếu đã push (caller chờ flush trước khi điều hướng). */
export function pushBookingComboSuccess(p: BookingComboSuccess): boolean {
  if (typeof window === 'undefined' || !p.orderCode) return false;

  const key = `vie_dl_combo_${p.orderCode}`;
  try {
    if (localStorage.getItem(key)) return false;
    localStorage.setItem(key, String(Date.now()));
  } catch {
    // localStorage bị chặn → vẫn push (thà trùng còn hơn mất).
  }

  try {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: 'booking_combo_success',
      content_id: p.hotelSlug,
      content_name: p.hotelName,
      value: p.value,
      currency: p.currency ?? 'VND',
      booking_id: p.orderCode,
    });
    return true;
  } catch {
    return false;
  }
}
