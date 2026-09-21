import { Component, inject, signal, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { EleveService, Eleve } from '../../core/eleve.service';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-dashboard',
  imports: [RouterLink, CommonModule],
  template: `
  <div class="dash-header">
    <div>
      <h1>Tableau de bord</h1>
      <p>Vue d'ensemble de vos enfants</p>
    </div>
    <a routerLink="/enfants" class="btn btn-primary">Gérer mes enfants</a>
  </div>

  @if (loading()) { <div class="card" style="padding:20px; margin-top:16px">Chargement...</div> }
  @else if (eleves().length===0) {
    <div class="card empty">
      <div class="empty-icon">🎓</div>
      <h3>Aucun enfant lié</h3>
      <p>Ajoutez votre premier enfant avec son matricule, login et mot de passe fournis par l'établissement.</p>
      <a routerLink="/enfants" class="btn btn-primary">Ajouter un enfant</a>
      <div class="hint">
        Exemples de test :<br>
        <code>MAT-2025-001 / koffi.aya / eleve123</code> — Aya KOFFI (6e A)<br>
        <code>MAT-2025-002 / koffi.moussa / eleve123</code> — Moussa KOFFI (3e B)
      </div>
    </div>
  } @else {
    <div class="grid grid-3" style="margin-top:16px">
      @for (e of eleves(); track e.id) {
        <a [routerLink]="['/eleves', e.id]" class="card enfant-card">
          <div class="enfant-head">
            <div class="avatar">{{ e.prenom[0] }}{{ e.nom[0] }}</div>
            <span class="badge badge-info">{{ e.classe || 'Classe' }}</span>
          </div>
          <div class="enfant-name">{{ e.prenom }} {{ e.nom }}</div>
          <div class="enfant-mat">{{ e.matricule }} · {{ e.etablissement }}</div>
          @if (stats[e.id]) {
            <div class="mini-stats">
              <div class="mini"><span>Moyenne</span><strong>{{ stats[e.id].moyenneGenerale ?? '—' }}</strong></div>
              <div class="mini"><span>Présence</span><strong>{{ stats[e.id].taux }}%</strong></div>
              <div class="mini"><span>Reste à payer</span><strong [style.color]="stats[e.id].reste>0?'var(--danger)':'var(--success)'">{{ stats[e.id].reste | number }} F</strong></div>
            </div>
          } @else { <div class="mini-loading">Chargement stats...</div> }
          <div class="card-cta">Voir le détail →</div>
        </a>
      }
    </div>

    <div class="card" style="margin-top:20px; padding:18px">
      <h3 style="margin-bottom:10px">Infos de l'établissement</h3>
      @if (infos().length===0) { <p style="color:var(--text-muted)">Aucune info pour le moment.</p> }
      @for (info of infos().slice(0,3); track info.id) {
        <div class="info-row">
          <div class="info-dot" [class.important]="info.important"></div>
          <div>
            <div class="info-title">{{ info.titre }} @if(info.important){<span class="badge badge-danger" style="margin-left:6px">Important</span>}</div>
            <div class="info-content">{{ info.contenu }}</div>
            <div class="info-meta">{{ info.date_publication | date:'mediumDate' }} · {{ info.type_info }}</div>
          </div>
        </div>
      }
      <a routerLink="/infos" class="btn btn-ghost" style="margin-top:10px">Voir toutes les infos</a>
    </div>
  }
  `,
  styles: [`
  .dash-header{display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;}
  .dash-header h1{font-size:26px;}
  .dash-header p{color:var(--text-muted); font-size:14px;}
  .empty{padding:28px; text-align:center; margin-top:16px;}
  .empty-icon{font-size:42px; margin-bottom:10px;}
  .empty h3{margin-bottom:6px;}
  .empty p{color:var(--text-muted); font-size:14px; max-width:520px; margin:0 auto 14px;}
  .hint{margin-top:16px; background:#f8fafc; border:1px solid var(--border); padding:10px; border-radius:12px; font-size:12px; text-align:left; display:inline-block;}
  .hint code{background:white; padding:2px 6px; border-radius:6px; border:1px solid var(--border);}
  .enfant-card{padding:18px; transition:.2s; display:block; color:inherit;}
  .enfant-card:hover{transform:translateY(-2px); box-shadow:var(--shadow-lg);}
  .enfant-head{display:flex; justify-content:space-between; align-items:center;}
  .avatar{width:44px;height:44px; border-radius:999px; background:linear-gradient(135deg,#0f766e,#14b8a6); color:white; display:grid; place-items:center; font-weight:800;}
  .enfant-name{font-weight:800; font-size:16px; margin-top:12px;}
  .enfant-mat{font-size:12px; color:var(--text-muted); margin-top:2px;}
  .mini-stats{display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-top:14px; background:#f8fafc; border-radius:12px; padding:10px; text-align:center;}
  .mini span{font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase; display:block;}
  .mini strong{font-size:14px;}
  .mini-loading{margin-top:14px; font-size:12px; color:var(--text-muted);}
  .card-cta{margin-top:12px; font-weight:700; font-size:13px; color:var(--primary);}
  .info-row{display:flex; gap:12px; padding:12px 0; border-bottom:1px solid #f1f5f9;}
  .info-row:last-child{border:none;}
  .info-dot{width:10px;height:10px; border-radius:999px; background:#cbd5e1; margin-top:6px; flex-shrink:0;}
  .info-dot.important{background:var(--danger);}
  .info-title{font-weight:700; font-size:14px;}
  .info-content{font-size:13px; color:#334155; margin-top:2px;}
  .info-meta{font-size:12px; color:var(--text-muted); margin-top:4px;}
  `]
})
export class Dashboard implements OnInit {
  private eleveSrv = inject(EleveService);
  eleves = signal<Eleve[]>([]);
  loading = signal(true);
  infos = signal<any[]>([]);
  stats: Record<number, {moyenneGenerale:any, taux:any, reste:any}> = {};

  ngOnInit(){
    this.eleveSrv.mesEleves().subscribe({
      next: (list) => {
        this.eleves.set(list);
        this.loading.set(false);
        list.forEach(e => {
          this.eleveSrv.moyennes(e.id).subscribe(r=> this.stats[e.id]={...this.stats[e.id], moyenneGenerale: r.moyenneGenerale});
          this.eleveSrv.presences(e.id).subscribe(r=> this.stats[e.id]={...this.stats[e.id], taux: r.stats.tauxPresence});
          this.eleveSrv.paiements(e.id).subscribe(r=> this.stats[e.id]={...this.stats[e.id], reste: r.stats.reste});
        });
      },
      error:()=> this.loading.set(false)
    });
    this.eleveSrv.infos().subscribe(r=> this.infos.set(r));
  }
}
