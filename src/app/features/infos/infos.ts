import { Component, inject, signal, OnInit } from '@angular/core';
import { EleveService } from '../../core/eleve.service';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-infos-page',
  imports: [CommonModule],
  template: `
  <h1>Infos de l'établissement</h1>
  <p style="color:var(--text-muted); font-size:14px">Communiqués, événements et annonces</p>
  <div style="display:grid; gap:12px; margin-top:16px">
    @for (info of infos(); track info.id) {
      <div class="card" style="padding:16px">
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap">
          <span class="badge" [ngClass]="info.important?'badge-danger':'badge-info'">{{info.type_info}}</span>
          @if(info.important){<span class="badge badge-danger">Important</span>}
          <span style="font-size:12px; color:var(--text-muted)">{{info.date_publication | date:'mediumDate'}} · {{info.etablissement}}</span>
        </div>
        <div style="font-weight:700; margin-top:8px; font-size:16px">{{info.titre}}</div>
        <div style="font-size:14px; color:#334155; margin-top:6px; white-space:pre-wrap">{{info.contenu}}</div>
        @if(info.date_evenement){<div style="font-size:13px; color:var(--primary); margin-top:8px">📅 Événement : {{info.date_evenement | date:'mediumDate'}}</div>}
      </div>
    }
    @if(infos().length===0){<div class="card" style="padding:20px; color:var(--text-muted)">Aucune info.</div>}
  </div>
  `})
export class InfosPage implements OnInit {
  private srv = inject(EleveService);
  infos = signal<any[]>([]);
  ngOnInit(){ this.srv.infos().subscribe(r=> this.infos.set(r)); }
}
