import { CanActivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';

export const adminGuard: CanActivateFn = () => {
  if (localStorage.getItem('zabiss_admin_token')) return true;
  return inject(Router).createUrlTree(['/admin/login']);
};
export const adminGuestGuard: CanActivateFn = () => {
  if (!localStorage.getItem('zabiss_admin_token')) return true;
  return inject(Router).createUrlTree(['/admin']);
};
