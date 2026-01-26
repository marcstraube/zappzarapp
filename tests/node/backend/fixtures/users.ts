/**
 * Test fixtures for user entities
 */

export interface UserFixture {
  id: number;
  email: string;
  password_hash: string;
  name: string;
  totp_secret: string | null;
  totp_enabled: boolean;
  created_at: Date;
  updated_at: Date;
}

export const mockUser: UserFixture = {
  id: 1,
  email: 'test@example.com',
  password_hash: '$2b$10$abcdefghijklmnopqrstuvwxyz1234567890',
  name: 'Test User',
  totp_secret: null,
  totp_enabled: false,
  created_at: new Date('2024-01-01T00:00:00Z'),
  updated_at: new Date('2024-01-01T00:00:00Z'),
};

export const mockUserWithTotp: UserFixture = {
  id: 2,
  email: 'admin@example.com',
  password_hash: '$2b$10$abcdefghijklmnopqrstuvwxyz1234567890',
  name: 'Admin User',
  totp_secret: 'JBSWY3DPEHPK3PXP',
  totp_enabled: true,
  created_at: new Date('2024-01-01T00:00:00Z'),
  updated_at: new Date('2024-01-01T00:00:00Z'),
};

export const mockUsers = [mockUser, mockUserWithTotp];
