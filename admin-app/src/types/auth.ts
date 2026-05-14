export interface AuthUser {
  id: number;
  username: string;
  display_name: string;
  email: string;
  roles: string[];
  caps: string[];
  managed_hotels: number[];
}

export interface AuthTokens {
  access_token: string;
  expires_in: number;
  token_type: string;
  user: AuthUser;
}
