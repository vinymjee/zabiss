import { Component, inject, signal, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { EleveService, Eleve } from '../../core/eleve.service';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-enfants',
  imports: [FormsModule, RouterLink],
  template: `
  <h1>Mes enfants</h1>
  <p style="color:var(--text-muted); font-size:14px; margin-top:4px">Ajoutez chaque enfant avec les identifiants fournis par l'établissement (matricule + login + mot de passe élève). Espace contrôle : vérification côté école.</p>

  <div class="grid grid-2" style="margin-top:16px">
    <div class="card" style="padding:18px">
      <h3 style="margin-bottom:12px">Lier un nouvel élève</h3>
      @if (msg()) { <div class="alert" [class.error]="isError()" [class.success]="!isError()">{{msg()}}</div> }
      <form (ngSubmit)="lier()">
        <label class="label">Matricule *</label>
        <input class="input" [(ngModel)]="matricule" name="matricule" placeholder="MAT-2025-001" required>
        <label class="label" style="margin-top:10px">Login élève *</label>
        <input class="input" [(ngModel)]="login" name="login" placeholder="koffi.aya" required>
        <label class="label" style="margin-top:10px">Mot de passe élève *</label>
        <input class="input" type="password" [(ngModel)]="password" name="password" placeholder="••••••••" required>
        <label class="label" style="margin-top:10px">Lien (optionnel)</label>
        <select class="input" [(ngModel)]="lien" name="lien">
          <option value="parent">Parent</option>
          <option value="pere">Père</option>
          <option value="mere">Mère</option>
          <option value="tuteur">Tuteur</option>
        </select>
        <button class="btn btn-primary" style="width:100%; margin-top:16px" [disabled]="loading()">
          {{ loading() ? 'Vérification...' : 'Ajouter et vérifier' }}
        </button>
      </form>
      <div class="hint">
        <strong>Élèves de démo :</strong><br>
        MAT-2025-001 / koffi.aya / eleve123<br>
        MAT-2025-002 / koffi.moussa / eleve123<br>
        MAT-2025-003 / traore.fatou / eleve123
      </div>
    </div>

    <div class="card" style="padding:18px">
      <h3>Enfants liés ({{ eleves().length }})</h3>
      @if (eleves().length===0) { <p style="margin-top:12px; color:var(--text-muted); font-size:14px">Aucun enfant pour le moment.</p> }
      <div style="display:grid; gap:10px; margin-top:12px">
        @for (e of eleves(); track e.id) {
          <div class="enfant-row">
            <div class="avatar">{{ e.prenom[0] }}{{ e.nom[0] }}</div>
            <div style="flex:1; min-width:0">
              <div style="font-weight:700; font-size:14px">{{ e.prenom }} {{ e.nom }}</div>
              <div style="font-size:12px; color:var(--text-muted)">{{ e.matricule }} · {{ e.classe }} · {{ e.lien }}</div>
            </div>
            <a [routerLink]="['/eleves', e.id]" class="btn btn-ghost" style="padding:8px 12px; font-size:12px">Voir</a>
            <button class="btn btn-outline" style="padding:8px 12px; font-size:12px; color:var(--danger); border-color:#fecaca" (click)="retirer(e.id)">Retirer</button>
          </div>
        }
      </div>
    </div>
  </div>
  `,
  styles: [`
  h1{font-size:24px;}
  .alert{padding:10px 12px; border-radius:12px; font-size:13px; margin-bottom:12px;}
  .alert.error{background:#fee2e2; color:#991b1b; border:1px solid #fecaca;}
  .alert.success{background:#dcfce7; color:#166534; border:1px solid #bbf7d0;}
  .hint{margin-top:12px; background:#f8fafc; border:1px solid var(--border); padding:10px; border-radius:12px; font-size:12px; line-height:1.7;}
  .enfant-row{display:flex; gap:10px; align-items:center; padding:10px; border:1px solid var(--border); border-radius:12px; background:#f8fafc;}
  .avatar{width:40px;height:40px; border-radius:999px; background:linear-gradient(135deg,#0f766e,#14b8a6); color:white; display:grid; place-items:center; font-weight:800; font-size:13px; flex-shrink:0;}
  `]
})
export class Enfants implements OnInit {
  private srv = inject(EleveService);
  eleves = signal<Eleve[]>([]);
  matricule=''; login=''; password=''; lien='parent';
  loading=signal(false); msg=signal(''); isError=signal(false);

  ngOnInit(){ this.refresh(); }
  refresh(){ this.srv.mesEleves().subscribe(r=> this.eleves.set(r)); }

  lier(){
    this.msg.set(''); this.isError.set(false); this.loading.set(true);
    this.srv.lier({matricule:this.matricule, login:this.login, password:this.password, lien:this.lien}).subscribe({
      next:(r)=> { this.msg.set('Élève ajouté : '+r.eleve.prenom+' '+r.eleve.nom); this.matricule=''; this.login=''; this.password=''; this.refresh(); this.loading.set(false); },
      error:(e)=> { this.msg.set(e.error?.error || 'Erreur'); this.isError.set(true); this.loading.set(false); }
    });
  }
  retirer(id:number){
    if(!confirm('Retirer cet enfant de votre compte ?')) return;
    this.srv.retirer(id).subscribe(()=> this.refresh());
  }
}
