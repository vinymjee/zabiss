import { Injectable, inject } from '@angular/core';
import { ApiService } from './api';

export interface Eleve {
  id:number; matricule:string; nom:string; prenom:string; classe?:string; niveau?:string;
  etablissement?:string; date_naissance?:string; sexe?:string; photo_url?:string; lien?:string;
  lie_le?:string; annee_scolaire?:string;
}

@Injectable({providedIn:'root'})
export class EleveService {
  private api = inject(ApiService);
  mesEleves() { return this.api.get<Eleve[]>('/parent/eleves'); }
  lier(data:{matricule:string, login:string, password:string, lien?:string}) { return this.api.post<{ok:boolean, eleve:Eleve}>('/parent/eleves/link', data); }
  retirer(eleveId:number){ return this.api.delete(`/parent/eleves/${eleveId}`); }
  detail(id:number){ return this.api.get<Eleve>(`/eleves/${id}`); }
  notes(id:number, periode?:string){ return this.api.get<any[]>(`/eleves/${id}/notes`, periode?{periode}:undefined); }
  moyennes(id:number){ return this.api.get<any>(`/eleves/${id}/moyennes`); }
  presences(id:number){ return this.api.get<{presences:any[], stats:any}>(`/eleves/${id}/presences`); }
  paiements(id:number){ return this.api.get<{paiements:any[], stats:any}>(`/eleves/${id}/paiements`); }
  infos(){ return this.api.get<any[]>('/infos'); }
}
