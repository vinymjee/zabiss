import { Component, inject, signal, OnInit } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { CommonModule } from '@angular/common';
import { EleveService } from '../../core/eleve.service';

@Component({
  selector: 'app-eleve-detail',
  imports: [CommonModule, RouterLink],
  template: `
  @if (eleve()) {
    <a routerLink="/dashboard" class="btn btn-ghost animate-in" style="margin-bottom:12px">← Retour</a>
    <div class="card hero animate-in" style="--d:80ms">
      <div class="avatar">{{ eleve().prenom[0] }}{{ eleve().nom[0] }}</div>
      <div>
        <h1>{{ eleve().prenom }} {{ eleve().nom }}</h1>
        <div class="sub">{{ eleve().matricule }} · {{ eleve().classe }} · {{ eleve().etablissement }} · {{ eleve().annee_scolaire }}</div>
      </div>
      <div class="hero-kpis">
        @if (moyennes()?.moyenneGenerale !== null) {
          <div class="kpi-badge">
            <div class="kpi-label">Moyenne Générale</div>
            <div class="kpi-val">{{ moyennes().moyenneGenerale }}/20</div>
            <span class="badge" [ngClass]="mentionClass">{{ moyennes().mention }}</span>
          </div>
        }
        @if (moyennes()?.rang) {
          <div class="kpi-badge"><div class="kpi-label">Rang</div><div class="kpi-val">{{ moyennes().rang }} / {{ moyennes().effectif }}</div></div>
        }
      </div>
    </div>

    <div class="tabs animate-in" style="--d:140ms">
      @for (t of tabs; track t.key) {
        <button class="tab" [class.active]="active()===t.key" (click)="active.set(t.key)">{{ t.label }}</button>
      }
    </div>

    @if (active()==='moyennes') {
      <div class="grid grid-2" style="margin-top:16px">
        <div class="card" style="padding:18px">
          <h3>Moyennes par matière</h3>
          @if (!moyennes()) { <p style="color:var(--text-muted); margin-top:8px">Chargement...</p> }
          @else {
            <div class="table-wrap" style="margin-top:12px">
              <table>
                <tr><th>Matière</th><th>Coef</th><th>Moyenne</th><th>Notes</th></tr>
                @for (m of moyennes().parMatiere; track m.id) {
                  <tr>
                    <td><strong>{{m.matiere}}</strong> <span style="color:var(--text-muted)">{{m.code}}</span></td>
                    <td>{{m.coef}}</td>
                    <td><span class="badge" [style.background]="colorMoy(m.moyenne_mat)">{{m.moyenne_mat}}/20</span></td>
                    <td>{{m.nb_notes}}</td>
                  </tr>
                }
              </table>
            </div>
            <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap">
              @for (p of periodeKeys; track p) {
                <span class="badge badge-neutral">{{p}} : {{ moyennes().moyParPeriode[p] }}/20</span>
              }
            </div>
          }
        </div>
        <div class="card" style="padding:18px">
          <h3>Détail des notes</h3>
          <div style="margin-top:8px; display:flex; gap:8px"><span style="font-size:12px; color:var(--text-muted)">Toutes périodes confondues</span></div>
          <div class="table-wrap" style="margin-top:12px; max-height:420px; overflow:auto">
            <table>
              <tr><th>Date</th><th>Matière</th><th>Période</th><th>Note</th></tr>
              @for (n of notes(); track n.id) {
                <tr>
                  <td>{{n.date_eval | date:'shortDate'}}</td>
                  <td>{{n.matiere}} <span style="color:var(--text-muted); font-size:12px">{{n.type_eval}}</span></td>
                  <td><span class="badge badge-neutral">{{n.periode}}</span></td>
                  <td><strong>{{n.note}}/{{n.note_sur}}</strong> <span style="color:var(--text-muted)">coef {{n.coefficient}}</span></td>
                </tr>
              }
            </table>
          </div>
          @if (notes().length===0) { <p style="color:var(--text-muted); margin-top:8px; font-size:13px">Aucune note.</p> }
        </div>
      </div>
    }

    @if (active()==='presences') {
      <div class="card" style="padding:18px; margin-top:16px">
        <h3>Assiduité</h3>
        @if (presStats()) {
          <div class="grid grid-3" style="margin-top:12px">
            <div class="kpi card" style="box-shadow:none"><div class="kpi-label">Taux de présence</div><div class="kpi-value">{{presStats().tauxPresence}}%</div></div>
            <div class="kpi card" style="box-shadow:none"><div class="kpi-label">Absences</div><div class="kpi-value" style="color:var(--danger)">{{presStats().absents}}</div></div>
            <div class="kpi card" style="box-shadow:none"><div class="kpi-label">Retards</div><div class="kpi-value" style="color:var(--warning)">{{presStats().retards}}</div></div>
          </div>
        }
        <div class="table-wrap" style="margin-top:16px">
          <table>
            <tr><th>Date</th><th>Statut</th><th>Motif</th><th>Justifié</th></tr>
            @for (p of presences(); track p.id) {
              <tr>
                <td>{{p.date_jour | date:'mediumDate'}}</td>
                <td>
                  <span class="badge" [ngClass]="{'badge-success':p.statut==='present','badge-danger':p.statut==='absent','badge-warning':p.statut==='retard','badge-info':p.statut==='exclu'}">{{p.statut}}</span>
                </td>
                <td>{{p.motif || '—'}}</td>
                <td>{{p.justifie ? 'Oui' : 'Non'}}</td>
              </tr>
            }
          </table>
        </div>
      </div>
    }

    @if (active()==='paiements') {
      <div class="card" style="padding:18px; margin-top:16px">
        <h3>Historique des paiements</h3>
        @if (payStats()) {
          <div class="grid grid-3" style="margin-top:12px">
            <div class="kpi card" style="box-shadow:none"><div class="kpi-label">Total dû</div><div class="kpi-value">{{payStats().totalDu | number}} F</div></div>
            <div class="kpi card" style="box-shadow:none"><div class="kpi-label">Total payé</div><div class="kpi-value" style="color:var(--success)">{{payStats().totalPaye | number}} F</div></div>
            <div class="kpi card" style="box-shadow:none"><div class="kpi-label">Reste</div><div class="kpi-value" [style.color]="payStats().reste>0?'var(--danger)':'var(--success)'">{{payStats().reste | number}} F</div></div>
          </div>
        }
        <div class="table-wrap" style="margin-top:16px">
          <table>
            <tr><th>Réf</th><th>Type</th><th>Montant</th><th>Payé</th><th>Statut</th><th>Échéance</th><th>Mode</th></tr>
            @for (pay of paiements(); track pay.id) {
              <tr>
                <td>{{pay.reference}}</td>
                <td>{{pay.type_paiement}}</td>
                <td>{{pay.montant | number}} F</td>
                <td>{{pay.montant_paye | number}} F</td>
                <td><span class="badge" [ngClass]="{'badge-success':pay.statut==='paye','badge-warning':pay.statut==='partiel','badge-danger':pay.statut==='impaye'}">{{pay.statut}}</span></td>
                <td>{{pay.date_echeance | date:'shortDate'}}</td>
                <td>{{pay.mode_paiement || '—'}}</td>
              </tr>
            }
          </table>
        </div>
        @if (paiements().length===0) { <p style="color:var(--text-muted); margin-top:8px">Aucun paiement.</p>}
      </div>
    }

    @if (active()==='infos') {
      <div class="card" style="padding:18px; margin-top:16px">
        <h3>Infos de l'établissement</h3>
        <div style="display:grid; gap:12px; margin-top:12px">
          @for (info of infos(); track info.id) {
            <div class="info-card">
              <div style="display:flex; gap:8px; align-items:center">
                <span class="badge" [ngClass]="info.important?'badge-danger':'badge-info'">{{info.type_info}}</span>
                @if(info.important){<span class="badge badge-danger">Important</span>}
                <span style="font-size:12px; color:var(--text-muted)">{{info.date_publication | date:'mediumDate'}}</span>
              </div>
              <div style="font-weight:700; margin-top:6px">{{info.titre}}</div>
              <div style="font-size:14px; color:#334155; margin-top:4px">{{info.contenu}}</div>
              @if(info.date_evenement){<div style="font-size:12px; color:var(--primary); margin-top:6px">📅 {{info.date_evenement | date:'mediumDate'}}</div>}
            </div>
          }
          @if (infos().length===0) { <p style="color:var(--text-muted)">Aucune info.</p> }
        </div>
      </div>
    }

  } @else {
    <div class="card" style="padding:20px; margin-top:16px">Chargement...</div>
  }
  `,
  styles: [`
  .hero{
    position:relative; overflow:hidden;
    display:flex; gap:16px; align-items:center; padding:20px; flex-wrap:wrap;
    background:linear-gradient(180deg,#ffffff,#f0fdfa);
  }
  .hero::before{content:''; position:absolute; inset:0 0 auto 0; height:4px; background:linear-gradient(90deg,#14b8a6,#7c3aed,#f59e0b);}
  .avatar{
    width:60px; height:60px; border-radius:18px;
    background:linear-gradient(135deg,#14b8a6,#0f766e 55%,#7c3aed 130%); background-size:200% 200%;
    color:white; display:grid; place-items:center; font-weight:800; font-size:19px;
    box-shadow:0 8px 22px rgba(20,184,166,.4); animation:gradientShift 5s ease infinite;
    transition:transform .25s var(--ease-spring);
  }
  .avatar:hover{transform:scale(1.06) rotate(-4deg);}
  h1{font-size:24px;}
  .sub{font-size:13px; color:var(--text-muted); margin-top:2px;}
  .hero-kpis{margin-left:auto; display:flex; gap:12px; flex-wrap:wrap;}
  .kpi-badge{
    background:#f8fafc; border:1px solid var(--border); border-radius:16px; padding:12px 18px; text-align:center;
    transition:transform .2s var(--ease-spring), box-shadow .2s;
  }
  .kpi-badge:hover{transform:translateY(-3px); box-shadow:var(--shadow);}
  .kpi-label{font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;}
  .kpi-val{font-size:22px; font-weight:800; margin-top:4px;}
  .tabs{display:flex; gap:8px; margin-top:16px; overflow:auto; padding:4px 2px 6px;}
  .tab{
    padding:10px 18px; border-radius:999px; border:1px solid var(--border); background:white;
    font-weight:700; font-size:13px; cursor:pointer; white-space:nowrap;
    transition:transform .18s var(--ease-spring), background .2s, color .2s, box-shadow .2s, border-color .2s;
  }
  .tab:hover{transform:translateY(-2px); border-color:var(--primary); color:var(--primary); box-shadow:var(--shadow);}
  .tab:active{transform:scale(.95);}
  .tab.active{
    background:linear-gradient(135deg,#0f766e,#14b8a6); color:white; border-color:transparent;
    box-shadow:0 8px 20px rgba(15,118,110,.35); transform:translateY(-1px);
  }
  .info-card{padding:14px; border:1px solid var(--border); border-radius:14px; background:#f8fafc; transition:transform .2s var(--ease-spring), box-shadow .2s;}
  .info-card:hover{transform:translateY(-3px); box-shadow:var(--shadow);}
  `]
})
export class EleveDetail implements OnInit {
  private route = inject(ActivatedRoute);
  private srv = inject(EleveService);
  eleve = signal<any>(null);
  notes = signal<any[]>([]);
  moyennes = signal<any>(null);
  presences = signal<any[]>([]); presStats = signal<any>(null);
  paiements = signal<any[]>([]); payStats = signal<any>(null);
  infos = signal<any[]>([]);
  active = signal<'moyennes'|'presences'|'paiements'|'infos'>('moyennes');
  tabs = [{key:'moyennes', label:'Moyennes & Notes'}, {key:'presences', label:'Présences'}, {key:'paiements', label:'Paiements'}, {key:'infos', label:'Infos'}] as const;

  get periodeKeys(){ const m=this.moyennes(); return m? Object.keys(m.moyParPeriode||{}):[]; }
  get mentionClass(){
    const m=this.moyennes()?.mention;
    if(m==='Très bien'||m==='Bien') return 'badge-success';
    if(m==='Assez bien'||m==='Passable') return 'badge-warning';
    return 'badge-danger';
  }
  colorMoy(v:number){
    if(v>=14) return '#dcfce7';
    if(v>=10) return '#fef3c7';
    return '#fee2e2';
  }

  ngOnInit(){
    const id = Number(this.route.snapshot.paramMap.get('id'));
    this.srv.detail(id).subscribe(r=> this.eleve.set(r));
    this.srv.notes(id).subscribe(r=> this.notes.set(r));
    this.srv.moyennes(id).subscribe(r=> this.moyennes.set(r));
    this.srv.presences(id).subscribe(r=> { this.presences.set(r.presences); this.presStats.set(r.stats); });
    this.srv.paiements(id).subscribe(r=> { this.paiements.set(r.paiements); this.payStats.set(r.stats); });
    // infos liées à l'établissement
    this.srv.infos().subscribe(r=> this.infos.set(r));
    // switch via query param
    const q=this.route.snapshot.queryParamMap.get('tab');
    if(q && ['moyennes','presences','paiements','infos'].includes(q)) this.active.set(q as any);
  }
}
