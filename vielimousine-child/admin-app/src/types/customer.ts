export interface CustomerListItem {
  id: number;
  phone: string;
  name: string;
  email: string | null;
  booking_count: number;
  vat_company_name: string | null;
  vat_tax_code: string | null;
  created_at: string;
}
