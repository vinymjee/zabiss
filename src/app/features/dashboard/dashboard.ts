import { Component, inject, signal, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { EleveService, Eleve } from '../../core/eleve.service';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-dashboard',
  imports: [RouterLink, CommonModule],
  template: `
  <div class="dash-header animate-in">
    <div>
      <h1 class="grad-title">Tableau de bord</h1>
      <p>Vue d'ensemble de vos enfants</p>
    </div>
    <a routerLink="/enfants" class="btn btn-primary">✨ Gérer mes enfants</a>
  </div>

  @if (loading()) {
    <div class="grid grid-3" style="margin-top:16px">
      <div class="card skeleton" style="height:180px"></div>
      <div class="card skeleton" style="height:180px; --d:80ms"></div>
      <div class="card skeleton" style="height:180px; --d:160ms"></div>
    </div>
  }
  @else if (eleves().length===0) {
    <div class="card empty animate-in">
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
      @for (e of eleves(); track e.id; let i = $index) {
        <a [routerLink]="['/eleves', e.id]" class="card enfant-card animate-in" [style.--d]="(i * 90) + 'ms'">
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

    <div class="card animate-in" style="margin-top:20px; padding:18px; --d:200ms">
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
  .dash-header h1{font-size:30px;}
  .grad-title{
    background: linear-gradient(90deg, #0f766e, #14b8a6 40%, #7c3aed 110%);
    -webkit-background-clip: text; background-clip: text; color: transparent;
  }
  .dash-header p{color:var(--text-muted); font-size:14px;}
  .empty{padding:32px; text-align:center; margin-top:16px; background:linear-gradient(180deg,#fff,#f0fdfa);}
  .empty-icon{font-size:48px; margin-bottom:10px; display:inline-block; animation: floatY 3s ease-in-out infinite;}
  .empty h3{margin-bottom:6px;}
  .empty p{color:var(--text-muted); font-size:14px; max-width:520px; margin:0 auto 14px;}
  .hint{margin-top:16px; background:#f8fafc; border:1px solid var(--border); padding:10px; border-radius:12px; font-size:12px; text-align:left; display:inline-block;}
  .hint code{background:white; padding:2px 6px; border-radius:6px; border:1px solid var(--border);}
  .enfant-card{
    padding:20px; display:block; color:inherit; position:relative; overflow:hidden;
  }
  .enfant-card::before{
    content:''; position:absolute; inset:0 0 auto 0; height:4px;
    background:linear-gradient(90deg,#14b8a6,#7c3aed,#f59e0b);
    opacity:0; transition:opacity .25s;
  }
  .enfant-card:hover{transform:translateY(-6px) scale(1.01); box-shadow:var(--shadow-lg);}
  .enfant-card:hover::before{opacity:1;}
  .enfant-card:active{transform:translateY(-2px) scale(.99);}
  .enfant-head{display:flex; justify-content:space-between; align-items:center;}
  .avatar{
    width:48px; height:48px; border-radius:16px;
    background:linear-gradient(135deg,#14b8a6,#0f766e 55%,#7c3aed 130%);
    color:white; display:grid; place-items:center; font-weight:800;
    box-shadow:0 6px 16px rgba(20,184,166,.35);
    transition:transform .25s var(--ease-spring);
  }
  .enfant-card:hover .avatar{transform:scale(1.08) rotate(-4deg);}
  .enfant-name{font-weight:800; font-size:17px; margin-top:12px;}
  .enfant-mat{font-size:12px; color:var(--text-muted); margin-top:2px;}
  .mini-stats{display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-top:14px; background:linear-gradient(180deg,#f8fafc,#f1f5f9); border:1px solid var(--border); border-radius:14px; padding:10px; text-align:center;}
  .mini{border-radius:10px; padding:6px 4px; transition:background .2s, transform .2s var(--ease-spring);}
  .mini:hover{background:white; transform:translateY(-2px); box-shadow:var(--shadow);}
  .mini span{font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase; display:block;}
  .mini strong{font-size:14px;}
  .mini-loading{margin-top:14px; font-size:12px; color:var(--text-muted);}
  .card-cta{margin-top:14px; font-weight:800; font-size:13px; color:var(--primary); display:inline-flex; align-items:center; gap:6px; transition:gap .2s, transform .2s;}
  .enfant-card:hover .card-cta{gap:12px; transform:translateX(2px);}
  .info-row{display:flex; gap:12px; padding:12px 8px; border-bottom:1px solid #f1f5f9; border-radius:12px; transition:background .2s, transform .2s var(--ease-spring);}
  .info-row:hover{background:#f0fdfa; transform:translateX(4px);}
  .info-row:last-child{border:none;}
  .info-dot{width:10px;height:10px; border-radius:999px; background:#cbd5e1; margin-top:6px; flex-shrink:0; transition:transform .2s;}
  .info-row:hover .info-dot{transform:scale(1.4);}
  .info-dot.important{background:var(--danger); animation:pulseRing 1.8s infinite;}
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
