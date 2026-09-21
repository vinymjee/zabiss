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
        <a routerLink="/dashboard" routerLinkActive="active" class="nav-item"><span class="nav-ico">🏠</span> Tableau de bord</a>
        <a routerLink="/enfants" routerLinkActive="active" class="nav-item"><span class="nav-ico">🎓</span> Mes enfants</a>
        <a routerLink="/paiements" routerLinkActive="active" class="nav-item"><span class="nav-ico">💳</span> Paiements</a>
        <a routerLink="/infos" routerLinkActive="active" class="nav-item"><span class="nav-ico">📢</span> Infos établissement</a>
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
  .sidebar{
    width:280px; color:white; display:flex; flex-direction:column; position:sticky; top:0; height:100vh;
    background: linear-gradient(180deg, #0b1e2e 0%, #0f172a 45%, #1e1b4b 100%);
    border-right: 1px solid rgba(255,255,255,0.08);
    box-shadow: 8px 0 32px rgba(2,6,23,0.25);
  }
  .brand{display:flex; gap:12px; align-items:center; padding:22px 20px; border-bottom:1px solid rgba(255,255,255,0.08);}
  .brand-logo{
    width:46px; height:46px; border-radius:14px;
    background: linear-gradient(135deg,#14b8a6,#0f766e 40%,#7c3aed 130%);
    background-size: 200% 200%;
    display:grid; place-items:center; font-weight:800; font-size:19px; color: white;
    box-shadow: 0 6px 20px rgba(20,184,166,0.45);
    animation: gradientShift 5s ease infinite;
    transition: transform .25s var(--ease-spring);
  }
  .brand:hover .brand-logo{transform: rotate(-8deg) scale(1.06);}
  .brand-name{font-weight:800; letter-spacing:.08em; font-size:14px;}
  .brand-sub{font-size:12px; color:#94a3b8;}
  .nav{padding:16px 12px; display:flex; flex-direction:column; gap:8px; flex:1;}
  .nav-item{
    position:relative; display:flex; align-items:center; gap:10px;
    padding:12px 14px; border-radius:14px; color:#cbd5e1; font-weight:600; font-size:14px;
    transition: transform .2s var(--ease-spring), background .2s, color .2s, box-shadow .2s;
    border: 1px solid transparent;
  }
  .nav-ico{display:inline-grid; place-items:center; width:28px; height:28px; border-radius:9px; background:rgba(255,255,255,0.08); transition: transform .25s var(--ease-spring), background .2s;}
  .nav-item:hover{background:rgba(255,255,255,0.07); color:white; transform:translateX(4px);}
  .nav-item:hover .nav-ico{transform:scale(1.15) rotate(-6deg); background:rgba(20,184,166,0.25);}
  .nav-item:active{transform:translateX(2px) scale(.98);}
  .nav-item.active{
    background:linear-gradient(135deg, rgba(20,184,166,.9), rgba(15,118,110,.9));
    color:white; box-shadow:0 8px 24px rgba(20,184,166,.35);
    border-color: rgba(255,255,255,.15);
  }
  .nav-item.active .nav-ico{background:rgba(255,255,255,.22);}
  .sidebar-footer{padding:16px; border-top:1px solid rgba(255,255,255,0.08);}
  .user-card{display:flex; gap:10px; align-items:center; background:rgba(255,255,255,0.06); padding:10px; border-radius:14px; border:1px solid rgba(255,255,255,0.08); transition: transform .2s var(--ease-spring), background .2s;}
  .user-card:hover{transform:translateY(-2px); background:rgba(255,255,255,0.1);}
  .avatar{width:38px;height:38px; border-radius:999px; background:linear-gradient(135deg,#14b8a6,#7c3aed); display:grid; place-items:center; font-weight:800; font-size:13px; box-shadow:0 0 0 2px rgba(255,255,255,.2);}
  .user-name{font-size:13px; font-weight:700;}
  .user-email{font-size:11px; color:#94a3b8; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:150px;}
  .main{flex:1; min-width:0;}
  .topbar{
    height:66px; background:rgba(255,255,255,0.8); backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;
    padding:0 24px; position:sticky; top:0; z-index:10;
  }
  .topbar-title{font-weight:800; color:var(--text);}
  .topbar-actions{display:flex; gap:10px; align-items:center;}
  .content{padding:24px; max-width:1180px; margin:0 auto; width:100%; animation: fadeUp .5s var(--ease-smooth) both;}
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
