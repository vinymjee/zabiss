import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-login',
  imports: [FormsModule, RouterLink],
  template: `
  <div class="auth-page">
    <div class="blob blob-a"></div>
    <div class="blob blob-b"></div>
    <div class="auth-card card animate-in">
      <div class="auth-header">
        <div class="logo">Z</div>
        <h1>Connexion parent</h1>
        <p>Accédez au suivi scolaire de vos enfants</p>
      </div>

      @if (error()) { <div class="alert alert-error">{{error()}}</div> }

      <form (ngSubmit)="submit()" class="form">
        <label class="label">Email</label>
        <input class="input" type="email" [(ngModel)]="email" name="email" placeholder="parent@exemple.com" required>
        <label class="label" style="margin-top:12px">Mot de passe</label>
        <input class="input" type="password" [(ngModel)]="password" name="password" placeholder="••••••••" required>
        <button class="btn btn-primary" style="width:100%; margin-top:18px" [disabled]="loading()">
          @if (loading()) { Connexion... } @else { Se connecter }
        </button>
      </form>

      <div class="auth-footer">
        Pas encore de compte ? <a routerLink="/register">Créer un compte</a><br>
        <a routerLink="/admin/login" style="font-size:12px">Console admin →</a>
        <div class="demo">
          <strong>Comptes de test :</strong><br>
          Créez un parent puis ajoutez l'élève :<br>
          <code>MAT-2025-001 / koffi.aya / eleve123</code><br>
          <code>MAT-2025-002 / koffi.moussa / eleve123</code><br>
          <code>MAT-2025-003 / traore.fatou / eleve123</code>
        </div>
      </div>
    </div>
    <div class="auth-hero animate-in" style="--d:120ms">
      <h2>ZABISS — Portail Parent d'Élève</h2>
      <p>Suivez en temps réel les <strong>moyennes</strong>, <strong>présences</strong>, <strong>paiements</strong> et <strong>infos</strong> de l'établissement pour chacun de vos enfants, en un seul endroit.</p>
      <ul>
        <li class="perk animate-in" style="--d:200ms">✓ Plusieurs enfants par parent</li>
        <li class="perk animate-in" style="--d:280ms">✓ Vérification par matricule + login + mot de passe élève (espace contrôle)</li>
        <li class="perk animate-in" style="--d:360ms">✓ Bulletins, assiduité, historique des paiements</li>
      </ul>
    </div>
  </div>
  `,
  styles: [`
  .auth-page{position:relative; overflow:hidden; min-height:100vh; display:grid; grid-template-columns: 480px 1fr; background:linear-gradient(135deg,#f0fdfa,#f8fafc 50%,#ede9fe);}
  .blob{position:absolute; border-radius:999px; filter:blur(70px); opacity:.5; pointer-events:none;}
  .blob-a{width:420px; height:420px; background:#99f6e4; top:-120px; right:10%; animation:floatY 7s ease-in-out infinite;}
  .blob-b{width:360px; height:360px; background:#ddd6fe; bottom:-120px; left:30%; animation:floatY 9s ease-in-out infinite reverse;}
  .auth-card{position:relative; z-index:1; margin:32px; padding:30px; align-self:center; border-radius:26px; box-shadow:var(--shadow-lg);}
  .auth-card:hover{transform:translateY(-3px);}
  .auth-header{text-align:center; margin-bottom:18px;}
  .logo{
    width:60px; height:60px; border-radius:18px;
    background:linear-gradient(135deg,#14b8a6,#0f766e 55%,#7c3aed 130%); background-size:200% 200%;
    color:white; display:grid; place-items:center; font-weight:800; font-size:24px; margin:0 auto 12px;
    box-shadow:0 10px 26px rgba(20,184,166,.4); animation:gradientShift 5s ease infinite;
    transition:transform .25s var(--ease-spring);
  }
  .logo:hover{transform:scale(1.08) rotate(-6deg);}
  .auth-header h1{font-size:22px;}
  .auth-header p{color:var(--text-muted); font-size:14px; margin-top:4px;}
  .form{margin-top:10px;}
  .alert{padding:10px 12px; border-radius:12px; font-size:13px; margin-bottom:12px;}
  .alert-error{background:#fee2e2; color:#991b1b; border:1px solid #fecaca;}
  .auth-footer{text-align:center; margin-top:16px; font-size:13px; color:var(--text-muted);}
  .demo{margin-top:14px; text-align:left; background:#f8fafc; border:1px solid var(--border); padding:10px; border-radius:12px; font-size:12px; line-height:1.7;}
  .demo code{background:white; padding:2px 6px; border-radius:6px; border:1px solid var(--border);}
  .auth-hero{padding:48px; display:flex; flex-direction:column; justify-content:center; max-width:640px;}
  .auth-hero{position:relative; z-index:1;}
  .auth-hero h2{font-size:36px; line-height:1.08; background:linear-gradient(90deg,#0f766e,#14b8a6 45%,#7c3aed); -webkit-background-clip:text; background-clip:text; color:transparent;}
  .auth-hero p{margin-top:14px; color:#334155; font-size:16px;}
  .auth-hero ul{margin-top:18px; list-style:none; display:grid; gap:10px; font-size:14px; color:#334155;}
  .perk{background:rgba(255,255,255,.75); backdrop-filter:blur(6px); border:1px solid var(--border); border-radius:14px; padding:10px 12px; transition:transform .2s var(--ease-spring), box-shadow .2s;}
  .perk:hover{transform:translateX(6px) scale(1.01); box-shadow:var(--shadow);}
  @media(max-width:900px){ .auth-page{grid-template-columns:1fr;} .auth-hero{display:none;} .auth-card{margin:16px;} }
  `]
})
export class Login {
  private auth = inject(AuthService);
  private router = inject(Router);
  email=''; password='';
  loading = signal(false);
  error = signal('');
  submit(){
    this.error.set('');
    this.loading.set(true);
    this.auth.login(this.email, this.password).subscribe({
      next:()=> this.router.navigate(['/dashboard']),
      error:(e)=> { this.error.set(e.error?.error || 'Erreur de connexion'); this.loading.set(false); },
      complete:()=> this.loading.set(false)
    });
  }
}
