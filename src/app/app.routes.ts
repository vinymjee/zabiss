import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth.guard';
import { adminGuard, adminGuestGuard } from './core/admin.guard';
import { Layout } from './shared/layout/layout';
import { AdminLayout } from './features/admin/admin-layout';

export const routes: Routes = [
  { path: 'login', loadComponent: () => import('./features/auth/login').then(m => m.Login), canActivate: [guestGuard] },
  { path: 'register', loadComponent: () => import('./features/auth/register').then(m => m.Register), canActivate: [guestGuard] },
  { path: 'admin/login', loadComponent: () => import('./features/admin/admin-login').then(m => m.AdminLogin), canActivate: [adminGuestGuard] },
  {
    path: 'admin', component: AdminLayout, canActivate: [adminGuard],
    children: [
      { path: '', loadComponent: () => import('./features/admin/admin-dashboard').then(m => m.AdminDashboard) },
      { path: 'ecoles', loadComponent: () => import('./features/admin/admin-ecoles').then(m => m.AdminEcoles) },
      { path: 'eleves', loadComponent: () => import('./features/admin/admin-eleves').then(m => m.AdminEleves) },
      { path: 'infos', loadComponent: () => import('./features/admin/admin-infos').then(m => m.AdminInfos) },
      { path: 'externes', loadComponent: () => import('./features/admin/admin-externes').then(m => m.AdminExternes) },
    ]
  },
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
