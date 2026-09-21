import { Component, inject, signal, OnInit } from '@angular/core';
import { AdminService } from '../../core/admin.service';

@Component({
  selector: 'app-admin-dashboard',
  template: `
  <h1>Dashboard admin</h1>
  <p style="color:var(--text-muted); font-size:14px">Vue globale + dossiers JSON par clé unique</p>
  @if (stats()) {
    <div class="grid grid-3" style="margin-top:16px">
      <div class="card kpi"><div class="kpi-label">Parents</div><div class="kpi-value">{{ stats().parents }}</div></div>
      <div class="card kpi"><div class="kpi-label">Élèves</div><div class="kpi-value">{{ stats().eleves }}</div></div>
      <div class="card kpi"><div class="kpi-label">Écoles</div><div class="kpi-value">{{ stats().ecoles }}</div></div>
      <div class="card kpi"><div class="kpi-label">Dossiers élève (JSON)</div><div class="kpi-value">{{ stats().dossiers_eleve }}</div><div class="kpi-sub">clé id_eleve_ecole_année</div></div>
      <div class="card kpi"><div class="kpi-label">Dossiers notes/moyennes</div><div class="kpi-value">{{ stats().dossiers_notes }}</div></div>
      <div class="card kpi"><div class="kpi-label">Dossiers présences / paiements</div><div class="kpi-value">{{ stats().dossiers_presences }} / {{ stats().dossiers_paiements }}</div></div>
    </div>
    <div class="card" style="padding:18px; margin-top:16px">
      <h3>Modèle de données</h3>
      <p style="font-size:13px; color:var(--text-muted); margin-top:6px">
        Tables : <code>ecoles</code>, <code>eleve_dossiers</code>, <code>notes_moyennes_dossiers</code>, <code>presence_dossiers</code>, <code>paiements_dossiers</code>.<br>
        Chaque dossier : <code>cle_unique = id_eleve _ id_ecole _ annee_scolaire</code> (ex: <code>12_3_2025-2026</code>) + <code>donnees_json</code>.<br>
        Les applis externes poussent via <code>X-API-KEY</code> (clé de l'école) — voir page API externes.
      </p>
    </div>
  } @else { <div class="card" style="padding:20px; margin-top:16px">Chargement...</div> }
  `,
  styles: [`.grid{margin-top:16px;} code{background:#f5f3ff; padding:2px 6px; border-radius:6px; border:1px solid #ddd6fe;}`]
})
export class AdminDashboard implements OnInit {
  private srv = inject(AdminService);
  stats = signal<any>(null);
  ngOnInit() { this.srv.stats().subscribe(r => this.stats.set(r)); }
}
