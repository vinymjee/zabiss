import { Component, inject, signal, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { AdminService } from '../../core/admin.service';

@Component({
  selector: 'app-admin-eleves',
  imports: [FormsModule, CommonModule],
  template: `
  <h1>Élèves & dossiers JSON</h1>
  <p style="color:var(--text-muted); font-size:14px">Recherche + détail par <code>cle_unique = id_eleve_ecole_année</code></p>

  <div class="card" style="padding:14px; margin-top:14px; display:flex; gap:10px; flex-wrap:wrap">
    <input class="input" style="max-width:300px" [(ngModel)]="search" placeholder="Nom, matricule, login..." (keyup.enter)="refresh()">
    <input class="input" style="max-width:140px" type="number" [(ngModel)]="ecoleId" placeholder="École ID">
    <button class="btn btn-primary" style="background:#7c3aed" (click)="refresh()">Rechercher</button>
  </div>

  <div class="grid grid-2" style="margin-top:16px">
    <div class="table-wrap">
      <table>
        <tr><th>Élève</th><th>École</th><th>Clé unique</th><th></th></tr>
        @for (e of eleves(); track e.id) {
          <tr>
            <td><strong>{{ e.prenom }} {{ e.nom }}</strong><br><span style="font-size:12px; color:var(--text-muted)">{{ e.matricule }} · {{ e.login }}</span></td>
            <td style="font-size:12px">{{ e.ecole_nom }}<br>ID {{ e.ecole_id }} · {{ e.annee_scolaire }}</td>
            <td><code style="font-size:11px">eleve:{{ e.cle_unique || e.id }} · année:{{ e.id }}|{{ e.annee_scolaire || '2025-2026' }}</code></td>
            <td><button class="btn btn-ghost" style="font-size:12px" (click)="voir(e.id)">Voir</button></td>
          </tr>
        }
      </table>
    </div>
    <div class="card" style="padding:16px">
      @if (!detail()) { <p style="color:var(--text-muted)">Sélectionne un élève pour voir ses 4 dossiers JSON.</p> }
      @else {
        <h3>{{ detail().prenom }} {{ detail().nom }}</h3>
        <div style="font-size:12px; color:var(--text-muted)">clé : <code>{{ detail().cle_unique_calculee }}</code></div>
        @for (t of ['eleve_dossiers','notes_moyennes_dossiers','presence_dossiers','paiements_dossiers']; track t) {
          <details style="margin-top:10px; border:1px solid var(--border); border-radius:10px; padding:8px">
            <summary style="font-weight:700; font-size:13px; cursor:pointer">{{ t }} @if(detail()[t]){ ✔ } @else { — vide }</summary>
            <pre style="font-size:11px; overflow:auto; max-height:220px; background:#f8fafc; padding:8px; border-radius:8px; margin-top:8px">{{ detail()[t] | json }}</pre>
          </details>
        }
      }
    </div>
  </div>
  `,
  styles: [`code{background:#f5f3ff; padding:2px 6px; border-radius:6px; border:1px solid #ddd6fe;}`]
})
export class AdminEleves implements OnInit {
  private srv = inject(AdminService);
  eleves = signal<any[]>([]);
  detail = signal<any>(null);
  search=''; ecoleId?:number;
  ngOnInit(){ this.refresh(); }
  refresh(){ this.srv.eleves(this.search, this.ecoleId).subscribe(r=>this.eleves.set(r)); }
  voir(id:number){ this.srv.eleveDetail(id).subscribe(r=>this.detail.set(r)); }
}
