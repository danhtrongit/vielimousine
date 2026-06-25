<script setup lang="ts">
import { ref, computed, reactive } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import RadioButton from 'primevue/radiobutton';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Checkbox from 'primevue/checkbox';
import { api, uuidv4 } from '@/api/client';
import type {
  CreateOrderRequest,
  CreateOrderResponse,
  CouponValidateResponse,
  QuoteInquiryRequest,
  QuoteInquiryResponse,
} from '@/api/types';
import { search, selection, getQuote, clearSelectionBack, appliedCoupon, resetCoupon, DROPOFF_OPTIONS, setSelection, type BookingType } from '@/composables/useBookingState';
import { fetchQuoteForRoom } from '@/composables/useQuotes';
import { submitCheckoutForm } from '@/composables/useCheckout';
import { formatVND, formatDateVN } from '@/composables/useFormat';

const props = defineProps<{ rooms: Array<{ id: number; name: string }> }>();

const idemKey = uuidv4();
const submitting = ref(false);
const errorMsg = ref('');
const couponStatus = ref<{ text: string; kind: 'ok' | 'err' } | null>(null);
const inquirySent = ref(false);

const form = reactive({
  name: '',
  phone: '',
  email: '',
  customer_note: '',
  coupon_code: '',
  payment_method: 'sepay' as 'sepay' | 'bank_transfer',
  pickupAddress: '',
  dropoffAddress: '',
  vat: { company_name: '', tax_code: '', address: '', email: '' },
});
const vatEnabled = ref(false);

const room = computed(() => props.rooms.find((r) => r.id === selection.roomId));
const quote = computed(() => (selection.roomId ? getQuote(selection.roomId, selection.bookingType) : null));
const isQuoteMode = computed(() => quote.value?.requires_quote === true);
const isCombo = computed(() => selection.bookingType === 'combo');

// Đổi nhanh loại đặt (phòng ↔ combo) ngay trong form, không phải quay lại danh sách phòng.
const typeOptions = [
  { label: 'Chỉ phòng', value: 'room' as BookingType },
  { label: 'Combo (phòng + vé)', value: 'combo' as BookingType },
];
async function switchType(type: BookingType | null) {
  if (!type || !selection.roomId || type === selection.bookingType) return;
  const rid = selection.roomId;
  setSelection(rid, type);          // cập nhật loại đặt + tự reset mã giảm giá
  couponStatus.value = null;
  form.coupon_code = '';
  if (!getQuote(rid, type)) await fetchQuoteForRoom(rid, type); // nạp quote nếu chưa cache
}

async function applyCoupon() {
  couponStatus.value = null;
  const code = form.coupon_code.trim();
  if (!code) { resetCoupon(); return; }
  if (!selection.roomId || !quote.value) {
    couponStatus.value = { text: 'Chọn phòng + chờ tính giá trước.', kind: 'err' };
    return;
  }
  try {
    const data = await api.post<CouponValidateResponse>('coupons/validate', {
      code,
      order_subtotal: quote.value.subtotal,
      room_id: selection.roomId,
      booking_type: selection.bookingType,
    });
    appliedCoupon.code = code;
    appliedCoupon.discount = data.discount;
    couponStatus.value = { text: 'Đã áp dụng: −' + formatVND(data.discount), kind: 'ok' };
  } catch (e: any) {
    resetCoupon();
    couponStatus.value = { text: e?.errors?.[0]?.message || 'Mã không hợp lệ', kind: 'err' };
  }
}

function changeRoom() {
  clearSelectionBack();
  document.querySelector('.vh-rooms')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function validateContact(): string | null {
  if (!selection.roomId) return 'Vui lòng chọn phòng.';
  if (!form.name.trim() || !form.phone.trim()) {
    return 'Vui lòng nhập họ tên và số điện thoại.';
  }
  if (isCombo.value && (!form.pickupAddress.trim() || !form.dropoffAddress.trim())) {
    return 'Đơn combo cần nhập điểm đón và chọn điểm trả.';
  }
  if (vatEnabled.value && (!form.vat.company_name.trim() || !form.vat.tax_code.trim())) {
    return 'Vui lòng nhập tên công ty và mã số thuế để xuất hóa đơn VAT.';
  }
  return null;
}

async function submitOrder(ev: Event) {
  ev.preventDefault();
  errorMsg.value = '';

  if (isQuoteMode.value) {
    await submitInquiry();
    return;
  }

  const err = validateContact();
  if (err) { errorMsg.value = err; return; }

  const body: CreateOrderRequest = {
    customer: {
      phone: form.phone.trim(),
      name: form.name.trim(),
      email: form.email.trim() || null,
    },
    items: [{
      room_id: selection.roomId!,
      booking_type: selection.bookingType,
      checkin: search.checkin,
      checkout: search.checkout,
      adults: search.adults,
      child_ages: search.childAges.slice(),
      user_rooms: search.userRooms,
    }],
    customer_note: form.customer_note.trim() || null,
    payment_method: form.payment_method,
  };
  if (appliedCoupon.code) body.coupon_code = appliedCoupon.code;
  if (isCombo.value) {
    body.pickup = { address: form.pickupAddress.trim() };
    body.dropoff = { address: form.dropoffAddress.trim() };
  }
  if (vatEnabled.value) {
    body.customer.vat = {
      company_name: form.vat.company_name.trim() || null,
      tax_code: form.vat.tax_code.trim() || null,
      address: form.vat.address.trim() || null,
      email: form.vat.email.trim() || null,
    };
  }

  submitting.value = true;
  try {
    const data = await api.post<CreateOrderResponse>('public/orders', body, { idempotencyKey: idemKey });
    if (data.checkout) {
      // Đẩy khách sang trang thanh toán SePay bằng POST form.
      submitCheckoutForm(data.checkout);
      return;
    }
    // Không có cổng thanh toán (vd chỉ chuyển khoản) → về trang xem đơn.
    const successUrl = window.VieRest?.successUrl || '/dat-phong-thanh-cong/';
    window.location.href = successUrl + '?' + new URLSearchParams({
      code: data.code,
      phone: form.phone.trim(),
    }).toString();
  } catch (e: any) {
    errorMsg.value = (e?.errors || []).map((er: any) => er.message).join('. ') || 'Đặt phòng thất bại';
    submitting.value = false;
  }
}

async function submitInquiry() {
  const err = validateContact();
  if (err) { errorMsg.value = err; return; }

  const body: QuoteInquiryRequest = {
    customer: {
      phone: form.phone.trim(),
      name: form.name.trim(),
      email: form.email.trim() || null,
    },
    note: form.customer_note.trim() || null,
    item: {
      room_id: selection.roomId!,
      booking_type: selection.bookingType,
      checkin: search.checkin,
      checkout: search.checkout,
      adults: search.adults,
      child_ages: search.childAges.slice(),
      user_rooms: search.userRooms,
    },
  };

  submitting.value = true;
  try {
    await api.post<QuoteInquiryResponse>('public/quote-inquiries', body);
    inquirySent.value = true;
  } catch (e: any) {
    errorMsg.value = (e?.errors || []).map((er: any) => er.message).join('. ') || 'Gửi yêu cầu thất bại';
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <section v-if="room" class="vh-checkout" data-vh-checkout>
    <!-- Thank-you state after inquiry submitted -->
    <div v-if="inquirySent" class="vh-inquiry-success">
      <i class="pi pi-check-circle vh-inquiry-success-icon" />
      <h2 class="vh-section-title vh-inquiry-success-title">Đã gửi yêu cầu báo giá</h2>
      <p>Cảm ơn <strong>{{ form.name }}</strong>! Vie Lim sẽ liên hệ lại số <strong>{{ form.phone }}</strong> trong thời gian sớm nhất để tư vấn và báo giá chi tiết.</p>
      <p class="vh-muted">Đặt phòng khác hoặc đóng trình duyệt — bạn không cần thao tác thêm.</p>
      <Button label="Chọn phòng khác" icon="pi pi-arrow-left" severity="secondary" outlined @click="changeRoom" />
    </div>

    <template v-else>
      <h2 class="vh-section-title">
        <i :class="['pi', isQuoteMode ? 'pi-comments' : 'pi-check-circle']" />
        {{ isQuoteMode ? 'Gửi yêu cầu báo giá' : 'Hoàn tất đặt phòng' }}
      </h2>
      <div class="vh-progress">
        <span class="vh-step vh-step-done">1. Phòng đã chọn</span>
        <span class="vh-step vh-step-active">
          2. {{ isQuoteMode ? 'Thông tin liên hệ' : 'Thông tin &amp; Thanh toán' }}
        </span>
      </div>

      <div class="vh-picked">
        <div class="vh-picked-info">
          <strong>
            <i :class="['pi', selection.bookingType === 'combo' ? 'pi-car' : 'pi-key']" />
            {{ room.name }} · {{ selection.bookingType === 'combo' ? 'Combo' : 'Chỉ phòng' }}
          </strong>
          <span>{{ formatDateVN(search.checkin) }} → {{ formatDateVN(search.checkout) }} · {{ search.adults }} người lớn{{ search.childAges.length ? ', ' + search.childAges.length + ' trẻ em' : '' }}</span>
        </div>
        <Button label="Đổi phòng" icon="pi pi-pencil" severity="secondary" text size="small" @click="changeRoom" />
      </div>

      <!-- Đổi nhanh loại đặt phòng/combo ngay tại đây -->
      <div class="vh-type-switch">
        <SelectButton
          :model-value="selection.bookingType"
          :options="typeOptions"
          option-label="label"
          option-value="value"
          :allow-empty="false"
          @update:model-value="switchType"
        />
      </div>

      <div v-if="isQuoteMode" class="vh-inquiry-note">
        <i class="pi pi-info-circle" />
        <span>Cấu hình bạn chọn vượt giá niêm yết — vui lòng để lại thông tin, Vie Lim sẽ liên hệ báo giá phù hợp.</span>
      </div>

      <form class="vh-checkout-form" novalidate @submit="submitOrder">
        <fieldset class="vh-fieldset">
          <legend><i class="pi pi-user" /> Thông tin liên hệ</legend>
          <div class="vh-form-row">
            <div class="vh-field">
              <label>Họ và tên <em>*</em></label>
              <InputText v-model="form.name" autocomplete="name" fluid />
            </div>
            <div class="vh-field">
              <label>Số điện thoại <em>*</em></label>
              <InputText v-model="form.phone" autocomplete="tel" inputmode="tel" fluid />
            </div>
          </div>
          <div class="vh-field">
            <label>Email <small>(để nhận xác nhận)</small></label>
            <InputText v-model="form.email" autocomplete="email" fluid />
          </div>
          <div class="vh-field">
            <label>Ghi chú</label>
            <Textarea
              v-model="form.customer_note"
              rows="2"
              :placeholder="isQuoteMode ? 'Mô tả thêm nhu cầu của bạn (số khách thực tế, dịch vụ kèm theo…)' : 'Yêu cầu đặc biệt...'"
              fluid
            />
          </div>
        </fieldset>

        <!-- Đưa đón: chỉ hiện cho đơn combo (đã gồm xe limousine khứ hồi) -->
        <fieldset v-if="!isQuoteMode && isCombo" class="vh-fieldset">
          <legend><i class="pi pi-car" /> Thông tin đưa đón</legend>
          <div class="vh-field">
            <label>Điểm đón <em>*</em></label>
            <InputText
              v-model="form.pickupAddress"
              placeholder="Địa chỉ đón khách (số nhà, đường, phường, tỉnh/thành)"
              fluid
            />
          </div>
          <div class="vh-field">
            <label>Điểm trả <em>*</em></label>
            <Select
              v-model="form.dropoffAddress"
              :options="DROPOFF_OPTIONS"
              placeholder="Chọn điểm trả"
              fluid
            />
          </div>
        </fieldset>

        <!-- Hóa đơn VAT: tích để mở phần điền thông tin -->
        <fieldset v-if="!isQuoteMode" class="vh-fieldset">
          <legend><i class="pi pi-file-edit" /> Hóa đơn VAT</legend>
          <label class="vh-check-row" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <Checkbox v-model="vatEnabled" :binary="true" input-id="vh-vat-toggle" />
            <span>Tôi cần xuất hóa đơn VAT (tích để điền thông tin)</span>
          </label>
          <template v-if="vatEnabled">
            <div class="vh-form-row" style="margin-top:12px;">
              <div class="vh-field">
                <label>Tên công ty <em>*</em></label>
                <InputText v-model="form.vat.company_name" fluid />
              </div>
              <div class="vh-field">
                <label>Mã số thuế <em>*</em></label>
                <InputText v-model="form.vat.tax_code" inputmode="numeric" fluid />
              </div>
            </div>
            <div class="vh-field">
              <label>Địa chỉ công ty</label>
              <InputText v-model="form.vat.address" fluid />
            </div>
            <div class="vh-field">
              <label>Email nhận hóa đơn</label>
              <InputText v-model="form.vat.email" autocomplete="email" fluid />
            </div>
          </template>
        </fieldset>

        <!-- Coupon + Payment chỉ hiện khi không phải chế độ báo giá -->
        <fieldset v-if="!isQuoteMode" class="vh-fieldset">
          <legend><i class="pi pi-tag" /> Mã giảm giá</legend>
          <div class="vh-inline-group">
            <InputText v-model="form.coupon_code" placeholder="Nhập mã (nếu có)" fluid />
            <Button label="Áp dụng" severity="secondary" outlined @click="applyCoupon" />
          </div>
          <div v-if="couponStatus" class="vh-coupon-status" :class="couponStatus.kind === 'ok' ? 'vh-coupon-ok' : 'vh-coupon-err'">
            <i :class="['pi', couponStatus.kind === 'ok' ? 'pi-check' : 'pi-times']" />
            {{ couponStatus.text }}
          </div>
        </fieldset>

        <fieldset v-if="!isQuoteMode" class="vh-fieldset">
          <legend><i class="pi pi-credit-card" /> Phương thức thanh toán</legend>
          <label class="vh-pay-option" :class="{ 'vh-pay-picked': form.payment_method === 'sepay' }">
            <RadioButton v-model="form.payment_method" value="sepay" input-id="pm-sepay" />
            <span>
              <strong>SePay</strong>
              <small>Thanh toán qua QR code / thẻ ngân hàng</small>
            </span>
          </label>
          <label class="vh-pay-option" :class="{ 'vh-pay-picked': form.payment_method === 'bank_transfer' }">
            <RadioButton v-model="form.payment_method" value="bank_transfer" input-id="pm-bank" />
            <span>
              <strong>Chuyển khoản ngân hàng</strong>
              <small>Nhận thông tin sau khi đặt</small>
            </span>
          </label>
        </fieldset>

        <div v-if="errorMsg" class="vh-error">
          <i class="pi pi-exclamation-triangle" /> {{ errorMsg }}
        </div>

        <div class="vh-submit-row">
          <Button
            type="submit"
            :label="submitting
              ? 'Đang xử lý…'
              : (isQuoteMode ? 'Gửi yêu cầu báo giá' : 'Xác nhận & Thanh toán')"
            :icon="isQuoteMode ? 'pi pi-send' : 'pi pi-arrow-right'"
            icon-pos="right"
            size="large"
            :loading="submitting"
          />
        </div>
      </form>
    </template>
  </section>
</template>
