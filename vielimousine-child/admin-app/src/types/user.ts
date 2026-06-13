export type StaffRole = 'vie_sales' | 'vie_hotel_manager';

export interface StaffUser {
  id: number;
  user_login: string;
  email: string;
  display_name: string;
  role: string;
  created: string;
}

export interface CreateUserPayload {
  user_login: string;
  email: string;
  password: string;
  display_name: string;
  role: StaffRole;
}

export interface UpdateUserPayload {
  email?: string;
  display_name?: string;
  role?: StaffRole;
  password?: string;
}
