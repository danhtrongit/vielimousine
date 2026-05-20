# VieLimo Admin — Design Tokens

Tài liệu tham khảo cho team frontend. Đảm bảo nhất quán visual cho toàn bộ admin dashboard.

> **Quy tắc vàng**: Đừng hardcode `#fa541c`, `#fef3c7`, `0.75rem`, `1.5rem`… trong page/component nữa. Luôn tham chiếu token. Khi cần đổi visual, sửa một nguồn duy nhất.

---

## Cấu trúc theme

- [`src/styles/tokens.css`](../src/styles/tokens.css) — Layer tokens ngoài PrimeVue (spacing, radius, shadow, motion, font stacks).
- [`src/styles/preset.ts`](../src/styles/preset.ts) — Custom PrimeVue Aura preset (color scale + semantic + components). Đây là nơi rebrand.
- Khi PrimeVue resolve preset, nó expose toàn bộ tokens thành CSS variables `--p-{name}` (vd `--p-primary-500`, `--p-surface-100`).

```
preset.ts (định nghĩa)  →  PrimeVue runtime  →  --p-* CSS vars  ←  component dùng
tokens.css (định nghĩa)  ────────────────────────  --space/radius/shadow/motion vars  ←  component dùng
```

---

## Brand color (cam)

Primary scale tự xây dựng quanh `#fa541c`:

| Token | Hex | Use |
|---|---|---|
| `--p-primary-50` | `#fff2eb` | Background tile nhẹ (sidebar active, KPI icon) |
| `--p-primary-100` | `#ffe0cd` | Hover background, avatar background |
| `--p-primary-200` | `#ffc09a` | Border subtle, tag border |
| `--p-primary-300` | `#ff9a64` | Disabled accent, dark mode text |
| `--p-primary-400` | `#ff7237` | Dark mode primary |
| **`--p-primary-500`** | **`#fa541c`** | **Brand primary** (button bg, link, ring) |
| `--p-primary-600` | `#e34112` | Hover/active button |
| `--p-primary-700` | `#bd3210` | Active state text |
| `--p-primary-800` | `#962812` | Heading on tint |
| `--p-primary-900` | `#7a2212` | Strong contrast |
| `--p-primary-950` | `#420f06` | Maximum contrast |

---

## Neutrals (slate)

`--p-surface-{0..950}` — dùng cho background, border, text muted, divider…

| Light mode | Dark mode | Use case |
|---|---|---|
| `surface-0` `#ffffff` | `surface-900` `#0f172a` | Card background |
| `surface-50` `#f8fafc` | `surface-950` `#020617` | Page background |
| `surface-100` `#f1f5f9` | `surface-800` `#1e293b` | Subtle background, hover |
| `surface-200` `#e2e8f0` | `surface-700` `#334155` | Border |
| `surface-300` `#cbd5e1` | `surface-600` `#475569` | Form border |
| `surface-500` `#64748b` | `surface-400` `#94a3b8` | Text muted |
| `surface-900` `#0f172a` | `surface-0` `#ffffff` | Text body |

---

## Semantic colors

Dùng cho status, alert, badge. Mỗi màu có scale 50–900 đầy đủ:

- `--p-green-*` — success
- `--p-blue-*` — info
- `--p-amber-*` — warning (cũng dùng cho stars rating)
- `--p-red-*` — danger / error

Pattern dùng cho status tile:
```css
background: var(--p-{color}-50);
color: var(--p-{color}-700);
border: 1px solid var(--p-{color}-200);
```

---

## Typography

| Token | Value |
|---|---|
| `--font-sans` | `'Be Vietnam Pro', 'Inter', system-ui, sans-serif` |
| `--font-mono` | `'JetBrains Mono', ui-monospace, monospace` |

**Class util** (định nghĩa trong `App.vue`):
- `.font-mono` — áp dụng cho cột tiền, mã đơn, hash. Bật `tabular-nums` để số canh cột.
- `.required-mark` — dấu `*` đỏ ở label form.

Quy tắc:
- Body: 14px (PrimeVue mặc định), line-height 1.5
- Page title: 1.5rem semibold (do `<PageHeader>` xử lý)
- Section title: 1rem semibold (do `<SectionCard>` xử lý)
- KPI value: 1.6rem 700 (do `<StatCard>` xử lý)
- Mọi cột số tiền: thêm `class="font-mono"`

---

## Spacing scale (4px base)

| Token | Value | Use |
|---|---|---|
| `--space-1` | 4px | Tight inline gap |
| `--space-2` | 8px | Standard inline gap |
| `--space-3` | 12px | Default gap |
| `--space-4` | 16px | Section gap |
| `--space-5` | 20px | Card padding |
| `--space-6` | 24px | Content padding |
| `--space-7` | 32px | Section margin |
| `--space-8` | 40px | Large gap |
| `--space-9` | 48px | Empty state padding |
| `--space-10` | 64px | Hero spacing |

---

## Radius

| Token | Value | Use |
|---|---|---|
| `--radius-xs` | 4px | Inline tag, dot |
| `--radius-sm` | 6px | Chip, small tag |
| `--radius-md` | 8px | Button, input, nav item |
| `--radius-lg` | 12px | Card, panel |
| `--radius-xl` | 16px | Modal, hero |
| `--radius-full` | 9999px | Avatar, pill, circle button |

---

## Shadow (layered)

| Token | Light | Dark |
|---|---|---|
| `--shadow-xs` | Subtle (card resting) | Subtle dark |
| `--shadow-sm` | Card resting | — |
| `--shadow-md` | Hover, dropdown | — |
| `--shadow-lg` | Modal, popover | — |
| `--shadow-xl` | Toast, important overlay | — |

Tokens tự đổi giá trị theo dark mode (`.dark-mode` selector).

---

## Z-index

```
--z-dropdown: 1000
--z-sticky:   1020
--z-overlay:  1100
--z-modal:    1300
--z-toast:    1400
--z-tooltip:  1500
```

---

## Motion

| Token | Duration | Use |
|---|---|---|
| `--motion-fast` | 120ms | Hover, focus |
| `--motion-base` | 200ms | Default transition |
| `--motion-slow` | 320ms | Drawer, modal entrance |

Easing: `--ease-out`, `--ease-in-out`.

> Tự respect `prefers-reduced-motion: reduce` — set tất cả về 0ms.

---

## Shared components

| Component | Use | Props chính |
|---|---|---|
| `<PageHeader title subtitle? icon?>` | Tiêu đề trang. Slot default = action buttons phải | Required `title` |
| `<SectionCard title? subtitle? icon? flush?>` | Card có header + body. `flush` để bỏ padding body (vd wrap DataTable) | Slots: `header`, `actions`, `footer` |
| `<StatCard label value sub? icon? accent? trend? mono?>` | KPI card. Accent: `primary` / `success` / `info` / `warning` / `danger` / `neutral` | `mono` default `true` |
| `<EmptyState icon? title? description? inset?>` | No-data state. `inset` để bỏ padding ngoài | Slot default = action buttons |
| `<LoadingState variant? rows? fill?>` | Skeleton/spinner. Variant: `spinner` / `skeleton-cards` / `skeleton-table` / `skeleton-text` | — |

---

## Dark mode

- Toggle ở topbar (icon sun/moon).
- Composable: `useTheme()` trong [`src/composables/useTheme.ts`](../src/composables/useTheme.ts).
- State: lưu trong `useUIStore().theme`, persist localStorage key `vielimo:ui`.
- Selector: `.dark-mode` trên `<html>`. Tự đồng bộ `color-scheme` browser native.
- Default: detect `prefers-color-scheme`.

Khi viết style mới, ghi nhớ:
- Đừng hardcode `#fff` → dùng `var(--p-surface-0)` (sẽ tự đảo trong dark).
- Background card: `var(--p-surface-0)` (light) vs `var(--p-surface-900)` (dark).
- Border: `var(--p-surface-200)` (light) vs `var(--p-surface-800)` (dark).

---

## Guideline khi thêm component mới

1. **Không** hardcode hex. Dùng `var(--p-{token})` hoặc `var(--space/radius/shadow/motion-*)`.
2. **Không** tạo `<h1 class="page-title">` nữa. Dùng `<PageHeader>`.
3. **Không** spinner ad-hoc. Dùng `<LoadingState>`.
4. **Không** "Không có dữ liệu" text. Dùng `<EmptyState>`.
5. **Không** Tag PrimeVue mặc định cho status order/payment. Dùng `<StatusTag>`.
6. **Cột số tiền / mã đơn**: bọc `<span class="font-mono">{{ formatVND(x) }}</span>`.
7. **Icon-only button**: bắt buộc `aria-label="..."`.
8. **Active nav link**: tự động `aria-current="page"` (đã có trong DefaultLayout).

## Khi muốn đổi brand color hoặc spacing

- Brand color → sửa `primitive.vielimo.*` trong [`src/styles/preset.ts`](../src/styles/preset.ts).
- Spacing/radius/shadow/motion → sửa [`src/styles/tokens.css`](../src/styles/tokens.css).
- **Không** sửa trực tiếp `--p-*` CSS vars — chúng là output của preset.

---

## Verification

```bash
cd admin-app && npm run dev
# Mở http://localhost:5173/vie-admin/

npm run build  # Typecheck + bundle
```

Test:
- [ ] Toggle dark mode ở topbar, reload giữ theme
- [ ] Tab keyboard, focus ring cam 2px hiện rõ
- [ ] Mobile 375px: sidebar collapse, DataTable scroll ngang, dialog full-screen
- [ ] Invoice print (Ctrl+P): nền trắng, chữ đen (không leak dark mode)
- [ ] `grep -rn 'page-title' src/views/` → 0 result
