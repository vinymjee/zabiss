import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AdminService } from '../../core/admin.service';

@Component({
  selector: 'app-admin-login',
  imports: [FormsModule],
  template: `
  <div class="auth-page">
    <div class="auth-card card">
      <div class="auth-header">
        <div class="logo" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)">A</div>
        <h1>Console Admin Zabiss</h1>
        <p>Gérer écoles, élèves, dossiers JSON et clés API</p>
      </div>
      @if (error()) { <div class="alert">{{error()}}</div> }
      <form (ngSubmit)="submit()">
        <label class="label">Email admin</label>
        <input class="input" [(ngModel)]="email" name="email" placeholder="admin@zabiss.ci" required>
        <label class="label" style="margin-top:12px">Mot de passe</label>
        <input class="input" type="password" [(ngModel)]="password" name="password" required>
        <button class="btn btn-primary" style="width:100%; margin-top:18px; background:#7c3aed" [disabled]="loading()">
          {{ loading() ? 'Connexion...' : 'Entrer' }}
        </button>
      </form>
      <div class="hint">Défaut : <code>admin@zabiss.ci / admin123</code> (change-le en prod)</div>
    </div>
  </div>
  `,
  styles: [`
  .auth-page{min-height:100vh; display:grid; place-items:center; background:linear-gradient(135deg,#ede9fe,#f8fafc); padding:20px;}
  .auth-card{max-width:440px; width:100%; padding:28px; border-radius:24px;}
  .auth-header{text-align:center; margin-bottom:18px;}
  .logo{width:56px;height:56px; border-radius:16px; color:white; display:grid; place-items:center; font-weight:800; font-size:22px; margin:0 auto 12px;}
  .alert{background:#fee2e2; color:#991b1b; padding:10px; border-radius:12px; font-size:13px; margin-bottom:12px;}
  .hint{margin-top:12px; font-size:12px; background:#f8fafc; border:1px solid var(--border); padding:10px; border-radius:12px;}
  .hint code{background:white; padding:2px 6px; border-radius:6px; border:1px solid var(--border);}
  `]
})
export class AdminLogin {
  private srv = inject(AdminService);
  private router = inject(Router);
  email = 'admin@zabiss.ci'; password = '';
  loading = signal(false); error = signal('');
  submit() {
    this.error.set(''); this.loading.set(true);
    this.srv.login(this.email, this.password).subscribe({
      next: () => this.router.navigate(['/admin']),
      error: e => { this.error.set(e.error?.error || 'Erreur'); this.loading.set(false); }
    });
  }
}
