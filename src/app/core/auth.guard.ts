import { CanActivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';

export const authGuard: CanActivateFn = () => {
  const token = localStorage.getItem('zabiss_token');
  if (token) return true;
  return inject(Router).createUrlTree(['/login']);
};
export const guestGuard: CanActivateFn = () => {
  const token = localStorage.getItem('zabiss_token');
  if (!token) return true;
  return inject(Router).createUrlTree(['/dashboard']);
};
