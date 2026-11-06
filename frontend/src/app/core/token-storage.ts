import { Injectable } from '@angular/core';

export interface SessionUser {
  id: number;
  email: string;
  name: string;
}

interface Session {
  token: string;
  user: SessionUser;
}

@Injectable({ providedIn: 'root' })
export class TokenStorage {
  private readonly key = 'products.session';

  token(): string | null {
    return this.read()?.token ?? null;
  }

  user(): SessionUser | null {
    return this.read()?.user ?? null;
  }

  set(token: string, user: SessionUser): void {
    localStorage.setItem(this.key, JSON.stringify({ token, user }));
  }

  clear(): void {
    localStorage.removeItem(this.key);
  }

  private read(): Session | null {
    const raw = localStorage.getItem(this.key);
    if (!raw) {
      return null;
    }
    try {
      const parsed = JSON.parse(raw) as Partial<Session>;
      if (typeof parsed.token !== 'string' || !parsed.user || typeof parsed.user.email !== 'string') {
        this.clear();
        return null;
      }
      return parsed as Session;
    } catch {
      this.clear();
      return null;
    }
  }
}
