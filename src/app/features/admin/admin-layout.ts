import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AdminService } from '../../core/admin.service';

@Component({
  selector: 'app-admin-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
  <div class="layout">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-logo" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)">Z</div>
        <div><div class="brand-name">ZABISS ADMIN</div><div class="brand-sub">Console de gestion</div></div>
      </div>
      <nav class="nav">
        <a routerLink="/admin" [routerLinkActiveOptions]="{exact:true}" routerLinkActive="active" class="nav-item">📊 Dashboard</a>
        <a routerLink="/admin/ecoles" routerLinkActive="active" class="nav-item">🏫 Écoles & clés API</a>
        <a routerLink="/admin/eleves" routerLinkActive="active" class="nav-item">🎓 Élèves & dossiers</a>
        <a routerLink="/admin/infos" routerLinkActive="active" class="nav-item">📢 Infos</a>
        <a routerLink="/admin/externes" routerLinkActive="active" class="nav-item">🔌 API externes</a>
        <a routerLink="/dashboard" class="nav-item">← Retour parent</a>
      </nav>
      <div class="sidebar-footer">
        <div class="user-card">
          <div class="avatar">A</div>
          <div class="user-meta">
            <div class="user-name">{{ admin.admin()?.nom }}</div>
            <div class="user-email">{{ admin.admin()?.email }}</div>
          </div>
        </div>
        <button class="btn btn-ghost" style="width:100%; margin-top:10px" (click)="admin.logout()">Déconnexion</button>
      </div>
    </aside>
    <div class="main">
      <header class="topbar"><div class="topbar-title">Administration — clé <code>id eleve</code> / <code>id|année</code></div></header>
      <div class="content"><router-outlet /></div>
    </div>
  </div>
  `,
  styles: [`
  .layout{display:flex; min-height:100vh;}
  .sidebar{width:270px; background:#1e1b4b; color:white; display:flex; flex-direction:column; position:sticky; top:0; height:100vh;}
  .brand{display:flex; gap:12px; align-items:center; padding:22px 20px; border-bottom:1px solid #312e81;}
  .brand-logo{width:42px;height:42px; border-radius:12px; display:grid; place-items:center; font-weight:800;}
  .brand-name{font-weight:800; font-size:13px; letter-spacing:.06em;}
  .brand-sub{font-size:12px; color:#a5b4fc;}
  .nav{padding:16px 12px; display:flex; flex-direction:column; gap:6px; flex:1;}
  .nav-item{padding:11px 14px; border-radius:12px; color:#c7d2fe; font-size:14px;}
  .nav-item:hover{background:#312e81; color:white;}
  .nav-item.active{background:#7c3aed; color:white;}
  .sidebar-footer{padding:16px; border-top:1px solid #312e81;}
  .user-card{display:flex; gap:10px; align-items:center; background:#312e81; padding:10px; border-radius:12px;}
  .avatar{width:36px;height:36px; border-radius:999px; background:#4c1d95; display:grid; place-items:center; font-weight:700;}
  .user-name{font-size:13px; font-weight:700;} .user-email{font-size:11px; color:#a5b4fc;}
  .main{flex:1; min-width:0;} .topbar{height:60px; background:white; border-bottom:1px solid var(--border); display:flex; align-items:center; padding:0 24px; position:sticky; top:0; z-index:10;}
  .topbar-title{font-weight:700;} .topbar-title code{background:#f5f3ff; padding:2px 8px; border-radius:8px; border:1px solid #ddd6fe;}
  .content{padding:24px; max-width:1180px; margin:0 auto;}
  @media(max-width:900px){.layout{flex-direction:column;}.sidebar{width:100%;height:auto;position:relative;}}
  `]
})
export class AdminLayout {
  admin = inject(AdminService);
}
