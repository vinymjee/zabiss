import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-admin-externes',
  imports: [CommonModule],
  template: `
  <h1>API externes — déversement école → Zabiss</h1>
  <p style="color:var(--text-muted); font-size:14px">Les logiciels de gestion scolaire poussent les données avec la clé API de l'école (<code>X-API-KEY</code>). Les parents consultent ensuite sans changement.</p>

  <div class="card" style="padding:18px; margin-top:16px">
    <h3>Authentification</h3>
    <pre style="background:#1e1b4b; color:#c4b5fd; padding:12px; border-radius:12px; font-size:12px; overflow:auto">X-API-KEY: zbk_...   # clé visible dans "Écoles & clés API"
GET /api/externes/health  →  &#123; ok, ecole: &#123; id, code, nom &#125; &#125;</pre>

    <h3 style="margin-top:16px">Endpoints</h3>
    <div class="table-wrap" style="margin-top:10px">
      <table>
        <tr><th>Méthode</th><th>Route</th><th>Body JSON</th></tr>
        <tr><td><span class="badge badge-success">POST</span></td><td><code>/api/externes/eleves</code></td><td style="font-size:12px">matricule, login, password, nom, prenom, annee_scolaire → crée <code>cle_unique</code> + dossier <code>eleve_dossiers</code></td></tr>
        <tr><td><span class="badge badge-success">POST</span></td><td><code>/api/externes/notes</code></td><td style="font-size:12px">eleve_id|matricule|cle_unique + notes:[&#123;matiere, code, periode, type_eval, note, note_sur, coefficient, date_eval&#125;] → dossier + table <code>notes</code></td></tr>
        <tr><td><span class="badge badge-success">POST</span></td><td><code>/api/externes/presences</code></td><td style="font-size:12px">eleve + lignes:[&#123;date_jour, statut, motif, justifie&#125;] → dossier + table <code>presences</code></td></tr>
        <tr><td><span class="badge badge-success">POST</span></td><td><code>/api/externes/paiements</code></td><td style="font-size:12px">eleve + paiements:[&#123;montant, montant_paye, type_paiement, statut, reference&#125;] → dossier + table <code>paiements</code></td></tr>
        <tr><td><span class="badge badge-success">POST</span></td><td><code>/api/externes/infos</code></td><td style="font-size:12px">titre, contenu, type_info, date_evenement, important</td></tr>
      </table>
    </div>

    <h3 style="margin-top:16px">Exemple curl</h3>
    <pre style="background:#0f172a; color:#a5f3fc; padding:12px; border-radius:12px; font-size:12px; overflow:auto">curl -X POST https://TON_DOMAINE/api/externes/notes \\
  -H "Content-Type: application/json" -H "X-API-KEY: zbk_TA_CLE" \\
  -d '&#123;"matricule":"MAT-2025-001","annee_scolaire":"2025-2026",
    "notes":[&#123;"matiere":"Mathématiques","code":"MATH","periode":"T1","type_eval":"composition","note":14,"note_sur":20,"coefficient":3&#125;]&#125;'
# → &#123;"ok":true,"cle_unique":"1|2025-2026","notes_synced":1&#125;</pre>

    <h3 style="margin-top:16px">Clé unique</h3>
    <p style="font-size:13px">Table <code>eleve_dossiers</code> : clé <code>id eleve</code> ex: <code>12</code>. Tables <code>notes_moyennes_dossiers</code>, <code>presence_dossiers</code>, <code>paiements_dossiers</code> : clé <code>id eleve|année scolaire</code> ex: <code>12|2025-2026</code> — une ligne par élève et par année, données en JSON (<code>donnees_json</code>).</p>
  </div>
  `,
  styles: [`code{background:#f5f3ff; padding:2px 6px; border-radius:6px; border:1px solid #ddd6fe;}`]
})
export class AdminExternes {}
