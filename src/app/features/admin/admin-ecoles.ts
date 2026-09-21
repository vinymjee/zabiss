import { Component, inject, signal, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../core/admin.service';

@Component({
  selector: 'app-admin-ecoles',
  imports: [FormsModule],
  template: `
  <h1>Écoles & clés API</h1>
  <p style="color:var(--text-muted); font-size:14px">Chaque école a une <code>X-API-KEY</code> pour déverser ses données (élèves, notes, présences, paiements, infos).</p>

  <div class="card" style="padding:16px; margin-top:16px">
    <h3>Créer une école</h3>
    <div class="grid grid-2" style="margin-top:10px">
      <div><label class="label">Nom *</label><input class="input" [(ngModel)]="nom" placeholder="Collège Moderne ..."></div>
      <div><label class="label">Code</label><input class="input" [(ngModel)]="code" placeholder="COL-MOD"></div>
    </div>
    <div class="grid grid-2" style="margin-top:10px">
      <div><label class="label">Téléphone</label><input class="input" [(ngModel)]="telephone"></div>
      <div><label class="label">Email</label><input class="input" [(ngModel)]="email"></div>
    </div>
    <button class="btn btn-primary" style="margin-top:12px; background:#7c3aed" (click)="creer()">Créer + générer clé API</button>
    @if (msg()) { <div style="margin-top:10px; font-size:13px">{{ msg() }}</div> }
  </div>

  <div class="table-wrap" style="margin-top:16px">
    <table>
      <tr><th>ID</th><th>Code / Nom</th><th>Contact</th><th>Clé API</th><th>Élèves</th><th>Actions</th></tr>
      @for (e of ecoles(); track e.id) {
        <tr>
          <td>{{ e.id }}</td>
          <td><strong>{{ e.nom }}</strong><br><span style="color:var(--text-muted); font-size:12px">{{ e.code }}</span></td>
          <td style="font-size:12px">{{ e.telephone }}<br>{{ e.email }}</td>
          <td><code style="font-size:11px; word-break:break-all">{{ e.api_key }}</code></td>
          <td>{{ e.nb_eleves }}</td>
          <td><button class="btn btn-ghost" style="font-size:12px" (click)="regen(e.id)">↻ Régénérer</button></td>
        </tr>
      }
    </table>
  </div>
  `,
  styles: [`code{background:#f5f3ff; padding:2px 6px; border-radius:6px; border:1px solid #ddd6fe;}`]
})
export class AdminEcoles implements OnInit {
  private srv = inject(AdminService);
  ecoles = signal<any[]>([]);
  nom=''; code=''; telephone=''; email='';
  msg = signal('');
  ngOnInit(){ this.refresh(); }
  refresh(){ this.srv.ecoles().subscribe(r=>this.ecoles.set(r)); }
  creer(){
    this.srv.createEcole({nom:this.nom, code:this.code||undefined, telephone:this.telephone, email:this.email}).subscribe({
      next:r=>{ this.msg.set('École créée, clé : '+r.api_key); this.nom=''; this.code=''; this.refresh(); },
      error:e=> this.msg.set(e.error?.error||'Erreur')
    });
  }
  regen(id:number){
    if(!confirm('Régénérer la clé API ? Les applis externes devront utiliser la nouvelle.')) return;
    this.srv.regenKey(id).subscribe(r=>{ this.msg.set('Nouvelle clé : '+r.api_key); this.refresh(); });
  }
}
