import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map, tap } from 'rxjs';
import { SessionUser, TokenStorage } from '../token-storage';

interface LoginResponse {
  token: string;
  user: SessionUser;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly tokens = inject(TokenStorage);

  login(email: string, password: string): Observable<void> {
    return this.http.post<LoginResponse>('/api/auth/login', { email, password }).pipe(
      tap((response) => this.tokens.set(response.token, response.user)),
      map(() => undefined),
    );
  }

  logout(): void {
    this.tokens.clear();
  }
}
