import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-register',
  imports: [FormsModule, RouterLink],
  template: `
  <div class="auth-page">
    <div class="auth-card card">
      <div class="auth-header">
        <div class="logo">Z</div>
        <h1>Créer mon compte parent</h1>
        <p>Un seul compte pour tous vos enfants</p>
      </div>
      @if (error()) { <div class="alert alert-error">{{error()}}</div> }
      <form (ngSubmit)="submit()" class="form">
        <div class="grid grid-2">
          <div><label class="label">Nom</label><input class="input" [(ngModel)]="nom" name="nom" required></div>
          <div><label class="label">Prénom</label><input class="input" [(ngModel)]="prenom" name="prenom" required></div>
        </div>
        <label class="label" style="margin-top:12px">Email</label>
        <input class="input" type="email" [(ngModel)]="email" name="email" placeholder="parent@exemple.com" required>
        <label class="label" style="margin-top:12px">Téléphone</label>
        <input class="input" [(ngModel)]="telephone" name="telephone" placeholder="+225 ...">
        <label class="label" style="margin-top:12px">Mot de passe (6+ caractères)</label>
        <input class="input" type="password" [(ngModel)]="password" name="password" required>
        <button class="btn btn-primary" style="width:100%; margin-top:18px" [disabled]="loading()">
          @if (loading()) { Création... } @else { Créer mon compte }
        </button>
      </form>
      <div class="auth-footer">Déjà un compte ? <a routerLink="/login">Se connecter</a></div>
    </div>
    <div class="auth-hero">
      <h2>Un espace contrôle pour lier vos enfants</h2>
      <p>Après création, vous serez redirigé vers le tableau de bord. Ajoutez chaque enfant avec son <strong>matricule</strong>, <strong>login</strong> et <strong>mot de passe élève</strong> — vérification côté établissement.</p>
    </div>
  </div>
  `,
  styles: [`
  .auth-page{min-height:100vh; display:grid; grid-template-columns: 520px 1fr; background: linear-gradient(135deg,#f0fdfa,#f8fafc);}
  .auth-card{margin:24px; padding:28px; align-self:center; border-radius:24px;}
  .auth-header{text-align:center; margin-bottom:18px;}
  .logo{width:56px;height:56px; border-radius:16px; background:linear-gradient(135deg,#0f766e,#14b8a6); color:white; display:grid; place-items:center; font-weight:800; font-size:22px; margin:0 auto 12px;}
  .auth-header h1{font-size:22px;}
  .auth-header p{color:var(--text-muted); font-size:14px; margin-top:4px;}
  .alert{padding:10px 12px; border-radius:12px; font-size:13px; margin-bottom:12px; background:#fee2e2; color:#991b1b; border:1px solid #fecaca;}
  .auth-footer{text-align:center; margin-top:16px; font-size:13px; color:var(--text-muted);}
  .auth-hero{padding:48px; display:flex; flex-direction:column; justify-content:center; max-width:640px;}
  .auth-hero h2{font-size:32px; color:var(--primary);}
  .auth-hero p{margin-top:14px; color:#334155;}
  .grid{ grid-template-columns:1fr 1fr; gap:12px; }
  @media(max-width:900px){ .auth-page{grid-template-columns:1fr;} .auth-hero{display:none;} }
  `]
})
export class Register {
  private auth = inject(AuthService);
  private router = inject(Router);
  nom=''; prenom=''; email=''; telephone=''; password='';
  loading=signal(false); error=signal('');
  submit(){
    this.error.set(''); this.loading.set(true);
    this.auth.register({nom:this.nom, prenom:this.prenom, email:this.email, telephone:this.telephone, password:this.password}).subscribe({
      next:()=> this.router.navigate(['/dashboard']),
      error:(e)=> { this.error.set(e.error?.error || 'Erreur'); this.loading.set(false); }
    });
  }
}
