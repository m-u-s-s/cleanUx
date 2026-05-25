export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: string;
  locale: string;
  email_verified_at: string | null;
  created_at: string;
}

export class ApiError extends Error {
  constructor(
    public status: number,
    public errorCode: string,
    message: string,
    public errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}
