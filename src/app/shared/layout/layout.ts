import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
  <div class="layout">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-logo">Z</div>
        <div>
          <div class="brand-name">ZABISS</div>
          <div class="brand-sub">Portail Parent</div>
        </div>
      </div>
      <nav class="nav">
        <a routerLink="/dashboard" routerLinkActive="active" class="nav-item">🏠 Tableau de bord</a>
        <a routerLink="/enfants" routerLinkActive="active" class="nav-item">🎓 Mes enfants</a>
        <a routerLink="/paiements" routerLinkActive="active" class="nav-item">💳 Paiements</a>
        <a routerLink="/infos" routerLinkActive="active" class="nav-item">📢 Infos établissement</a>
      </nav>
      <div class="sidebar-footer">
        <div class="user-card">
          <div class="avatar">{{ initials }}</div>
          <div class="user-meta">
            <div class="user-name">{{ auth.parent()?.prenom }} {{ auth.parent()?.nom }}</div>
            <div class="user-email">{{ auth.parent()?.email }}</div>
          </div>
        </div>
        <button class="btn btn-ghost" style="width:100%; margin-top:10px" (click)="auth.logout()">Déconnexion</button>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <div class="topbar-title">Portail Parent d'Élève</div>
        <div class="topbar-actions">
          <span class="badge badge-info">Année 2025-2026</span>
          <a routerLink="/enfants" class="btn btn-primary">+ Ajouter un élève</a>
        </div>
      </header>
      <div class="content">
        <router-outlet />
      </div>
    </div>
  </div>
  `,
  styles: [`
  .layout{display:flex; min-height:100vh;}
  .sidebar{width:270px; background:#0f172a; color:white; display:flex; flex-direction:column; position:sticky; top:0; height:100vh;}
  .brand{display:flex; gap:12px; align-items:center; padding:22px 20px; border-bottom:1px solid #1e293b;}
  .brand-logo{width:42px;height:42px; border-radius:12px; background:linear-gradient(135deg,#0f766e,#14b8a6); display:grid; place-items:center; font-weight:800; font-size:18px;}
  .brand-name{font-weight:800; letter-spacing:.06em; font-size:14px;}
  .brand-sub{font-size:12px; color:#94a3b8;}
  .nav{padding:16px 12px; display:flex; flex-direction:column; gap:6px; flex:1;}
  .nav-item{padding:11px 14px; border-radius:12px; color:#cbd5e1; font-weight:500; font-size:14px; transition:.2s;}
  .nav-item:hover{background:#1e293b; color:white;}
  .nav-item.active{background:#0f766e; color:white;}
  .sidebar-footer{padding:16px; border-top:1px solid #1e293b;}
  .user-card{display:flex; gap:10px; align-items:center; background:#1e293b; padding:10px; border-radius:12px;}
  .avatar{width:36px;height:36px; border-radius:999px; background:#334155; display:grid; place-items:center; font-weight:700; font-size:13px;}
  .user-name{font-size:13px; font-weight:700;}
  .user-email{font-size:11px; color:#94a3b8; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:150px;}
  .main{flex:1; min-width:0;}
  .topbar{height:64px; background:white; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; padding:0 24px; position:sticky; top:0; z-index:10;}
  .topbar-title{font-weight:700; color:var(--text);}
  .topbar-actions{display:flex; gap:10px; align-items:center;}
  .content{padding:24px; max-width:1180px; margin:0 auto; width:100%;}
  @media(max-width:900px){
    .layout{flex-direction:column;}
    .sidebar{width:100%; height:auto; position:relative;}
    .topbar{padding:0 16px;}
    .content{padding:16px;}
  }
  `]
})
export class Layout {
  auth = inject(AuthService);
  get initials(){
    const p = this.auth.parent(); if(!p) return 'P';
    return (p.prenom[0]||'')+(p.nom[0]||'');
  }
}
