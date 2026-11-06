import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { TokenStorage } from '../token-storage';

export const authGuard: CanActivateFn = () => {
  const tokens = inject(TokenStorage);
  const router = inject(Router);
  if (tokens.token()) {
    return true;
  }
  return router.createUrlTree(['/login']);
};
