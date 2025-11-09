export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: string;
}

export interface AuthOrganization {
  id: number | string;
}

export interface AuthLoginResponse {
  token?: string;
  user?: AuthUser;
  organization?: AuthOrganization;
  branding?: Record<string, unknown>;
  feature_flags?: Record<string, boolean>;
}

export interface StandardResponse<T> {
  success?: boolean;
  data?: T;
  message?: string;
}

