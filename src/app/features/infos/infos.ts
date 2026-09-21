import { Component, inject, signal, OnInit, computed } from '@angular/core';
import { EleveService, Ecole } from '../../core/eleve.service';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-infos-page',
  imports: [CommonModule, FormsModule],
  styles: [`
    h1{background:linear-gradient(90deg,#0f766e,#14b8a6 50%,#7c3aed); -webkit-background-clip:text; background-clip:text; color:transparent;}
    .info-hover{transition:transform .2s var(--ease-spring), box-shadow .2s;}
    .info-hover:hover{transform:translateY(-4px); box-shadow:var(--shadow-lg);}
  `],
  template: `
  <h1 class="animate-in">Infos de l'établissement</h1>
  <p class="animate-in" style="color:var(--text-muted); font-size:14px; --d:60ms">Communiqués, événements et annonces — avec nom de l'établissement</p>

  <div class="card animate-in" style="padding:14px; margin-top:14px; display:flex; gap:12px; align-items:center; flex-wrap:wrap; --d:120ms">
    <label style="font-size:13px; font-weight:700">Filtrer par établissement :</label>
    <select class="input" style="max-width:320px" [(ngModel)]="filtreEcole" (ngModelChange)="charger()">
      <option [ngValue]="0">Tous mes établissements</option>
      @for (e of ecoles(); track e.id) {
        <option [ngValue]="e.id">{{ e.nom }} ({{ e.code }})</option>
      }
    </select>
    <span class="badge badge-neutral">{{ filtrees().length }} info(s)</span>
  </div>

  <div style="display:grid; gap:12px; margin-top:16px">
    @for (info of filtrees(); track info.id; let i = $index) {
      <div class="card animate-in info-hover" style="padding:16px" [style.--d]="(i * 70) + 'ms'">
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap">
          <span class="badge badge-success">🏫 {{ info.etablissement || 'Établissement' }}</span>
          <span class="badge" [ngClass]="info.important?'badge-danger':'badge-info'">{{ info.type_info }}</span>
          @if(info.important){<span class="badge badge-danger">Important</span>}
          <span style="font-size:12px; color:var(--text-muted)">{{ info.date_publication | date:'mediumDate' }}</span>
        </div>
        <div style="font-weight:700; margin-top:8px; font-size:16px">{{ info.titre }}</div>
        <div style="font-size:14px; color:#334155; margin-top:6px; white-space:pre-wrap">{{ info.contenu }}</div>
        @if(info.date_evenement){<div style="font-size:13px; color:var(--primary); margin-top:8px">📅 Événement : {{ info.date_evenement | date:'mediumDate' }}</div>}
      </div>
    }
    @if(filtrees().length===0){<div class="card" style="padding:20px; color:var(--text-muted)">Aucune info pour ce filtre.</div>}
  </div>
  `})
export class InfosPage implements OnInit {
  private srv = inject(EleveService);
  infos = signal<any[]>([]);
  ecoles = signal<Ecole[]>([]);
  filtreEcole = 0;
  filtrees = computed(() => {
    const f = this.filtreEcole;
    if (!f) return this.infos();
    return this.infos().filter(i => Number(i.ecole_id) === Number(f));
  });
  ngOnInit() {
    this.srv.toutesEcoles().subscribe({
      next: r => this.ecoles.set(r.toutes?.length ? r.toutes : r.mes_ecoles),
      error: () => this.srv.mesEcoles().subscribe(r => this.ecoles.set(r))
    });
    this.charger();
  }
  charger() {
    this.srv.infos(this.filtreEcole || undefined).subscribe(r => this.infos.set(r));
  }
}
