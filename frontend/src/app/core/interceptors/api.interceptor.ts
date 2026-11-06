import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { TokenStorage } from '../token-storage';

export const apiInterceptor: HttpInterceptorFn = (request, next) => {
  const tokens = inject(TokenStorage);
  const router = inject(Router);
  const snackBar = inject(MatSnackBar);
  const token = tokens.token();
  const outgoing = token
    ? request.clone({ setHeaders: { Authorization: `Bearer ${token}` } })
    : request;

  return next(outgoing).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse) {
        const isLogin = request.url.includes('/api/auth/login');
        if (error.status === 401 && !isLogin) {
          tokens.clear();
          void router.navigate(['/login']);
        }
        if (error.status >= 500) {
          snackBar.open('Ошибка сервера. Попробуйте ещё раз.', 'Закрыть', { duration: 4000 });
        }
      }
      return throwError(() => error);
    }),
  );
};
