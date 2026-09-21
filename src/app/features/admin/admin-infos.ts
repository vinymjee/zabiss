import { Component, inject, signal, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../core/admin.service';

@Component({
  selector: 'app-admin-infos',
  imports: [FormsModule],
  template: `
  <h1>Infos établissements</h1>
  <p style="color:var(--text-muted); font-size:14px">Publier une info rattachée à une école (nom affiché côté parent + filtre).</p>
  <div class="card" style="padding:16px; margin-top:14px">
    <h3>Nouvelle info</h3>
    <div class="grid grid-2" style="margin-top:10px">
      <div><label class="label">École ID *</label><input class="input" type="number" [(ngModel)]="ecole_id"></div>
      <div><label class="label">Type</label><select class="input" [(ngModel)]="type_info"><option value="general">general</option><option value="reunion">reunion</option><option value="evenement">evenement</option><option value="urgence">urgence</option></select></div>
    </div>
    <label class="label" style="margin-top:10px">Titre *</label><input class="input" [(ngModel)]="titre">
    <label class="label" style="margin-top:10px">Contenu *</label><textarea class="input" rows="3" [(ngModel)]="contenu"></textarea>
    <label style="margin-top:10px; font-size:13px"><input type="checkbox" [(ngModel)]="important"> Important</label><br>
    <button class="btn btn-primary" style="margin-top:10px; background:#7c3aed" (click)="publier()">Publier</button>
    @if(msg()){<div style="margin-top:8px; font-size:13px">{{msg()}}</div>}
  </div>
  <div class="table-wrap" style="margin-top:16px">
    <table>
      <tr><th>École</th><th>Titre</th><th>Type</th><th></th></tr>
      @for (i of infos(); track i.id) {
        <tr>
          <td><span class="badge badge-success">🏫 {{ i.ecole_nom || i.etablissement_id }}</span></td>
          <td><strong>{{ i.titre }}</strong><br><span style="font-size:12px; color:var(--text-muted)">{{ i.contenu.slice(0,80) }}...</span></td>
          <td>{{ i.type_info }}</td>
          <td><button class="btn btn-ghost" style="font-size:12px; color:var(--danger)" (click)="supprimer(i.id)">Suppr</button></td>
        </tr>
      }
    </table>
  </div>
  `
})
export class AdminInfos implements OnInit {
  private srv = inject(AdminService);
  infos = signal<any[]>([]);
  ecole_id?:number; titre=''; contenu=''; type_info='general'; important=false;
  msg = signal('');
  ngOnInit(){ this.refresh(); }
  refresh(){ this.srv.infos().subscribe(r=>this.infos.set(r)); }
  publier(){
    this.srv.createInfo({ecole_id:this.ecole_id, titre:this.titre, contenu:this.contenu, type_info:this.type_info, important:this.important?1:0}).subscribe({
      next:()=>{ this.msg.set('Publié'); this.titre=''; this.contenu=''; this.refresh(); },
      error:e=> this.msg.set(e.error?.error||'Erreur')
    });
  }
  supprimer(id:number){ if(confirm('Supprimer ?')) this.srv.deleteInfo(id).subscribe(()=>this.refresh()); }
}
