import { Component, inject, signal, OnInit } from '@angular/core';
import { ApiService } from '../../core/api';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-paiements-page',
  imports: [CommonModule],
  template: `
  <h1>Historique des paiements</h1>
  <p style="color:var(--text-muted); font-size:14px">Tous les paiements de vos enfants</p>
  @if (rows().length===0) { <div class="card" style="padding:20px; margin-top:16px; color:var(--text-muted)">Aucun paiement à afficher. Liez d'abord un enfant.</div> }
  @else {
    <div class="table-wrap" style="margin-top:16px">
      <table>
        <tr><th>Élève</th><th>Réf</th><th>Type</th><th>Montant</th><th>Payé</th><th>Statut</th><th>Échéance</th></tr>
        @for (r of rows(); track r.id) {
          <tr>
            <td><strong>{{r.prenom}} {{r.nom}}</strong></td>
            <td>{{r.reference}}</td>
            <td>{{r.type_paiement}}</td>
            <td>{{r.montant | number}} F</td>
            <td>{{r.montant_paye | number}} F</td>
            <td><span class="badge" [ngClass]="{'badge-success':r.statut==='paye','badge-warning':r.statut==='partiel','badge-danger':r.statut==='impaye'}">{{r.statut}}</span></td>
            <td>{{r.date_echeance | date:'shortDate'}}</td>
          </tr>
        }
      </table>
    </div>
  }
  `})
export class PaiementsPage implements OnInit {
  private api = inject(ApiService);
  rows = signal<any[]>([]);
  ngOnInit(){ this.api.get<any[]>('/parent/paiements').subscribe(r=> this.rows.set(r)); }
}
