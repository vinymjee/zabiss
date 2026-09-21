import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth.guard';
import { Layout } from './shared/layout/layout';

export const routes: Routes = [
  { path: 'login', loadComponent: () => import('./features/auth/login').then(m => m.Login), canActivate: [guestGuard] },
  { path: 'register', loadComponent: () => import('./features/auth/register').then(m => m.Register), canActivate: [guestGuard] },
  {
    path: '', component: Layout, canActivate: [authGuard],
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      { path: 'dashboard', loadComponent: () => import('./features/dashboard/dashboard').then(m => m.Dashboard) },
      { path: 'enfants', loadComponent: () => import('./features/enfants/enfants').then(m => m.Enfants) },
      { path: 'eleves/:id', loadComponent: () => import('./features/eleve-detail/eleve-detail').then(m => m.EleveDetail) },
      { path: 'paiements', loadComponent: () => import('./features/paiements/paiements').then(m => m.PaiementsPage) },
      { path: 'infos', loadComponent: () => import('./features/infos/infos').then(m => m.InfosPage) },
    ]
  },
  { path: '**', redirectTo: 'dashboard' }
];
