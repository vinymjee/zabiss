import { Injectable, signal, computed, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { tap } from 'rxjs';
import { environment } from '../../environments/environment';

export interface AdminUser { id:number; nom:string; email:string; role:string; }

@Injectable({ providedIn: 'root' })
export class AdminService {
  private http = inject(HttpClient);
  private router = inject(Router);
  private base = environment.apiUrl;

  token = signal<string|null>(localStorage.getItem('zabiss_admin_token'));
  admin = signal<AdminUser|null>(JSON.parse(localStorage.getItem('zabiss_admin') || 'null'));
  isLogged = computed(() => !!this.token());

  private authHeaders() {
    return { Authorization: `Bearer ${this.token()}` };
  }

  login(email:string, password:string) {
    return this.http.post<{token:string, admin:AdminUser}>(`${this.base}/admin/login`, { email, password }).pipe(
      tap(r => {
        localStorage.setItem('zabiss_admin_token', r.token);
        localStorage.setItem('zabiss_admin', JSON.stringify(r.admin));
        this.token.set(r.token); this.admin.set(r.admin);
      })
    );
  }
  logout() {
    this.http.post(`${this.base}/admin/logout`, {}, { headers: this.authHeaders() }).subscribe({complete:()=>{}});
    localStorage.removeItem('zabiss_admin_token'); localStorage.removeItem('zabiss_admin');
    this.token.set(null); this.admin.set(null);
    this.router.navigate(['/admin/login']);
  }
  stats() { return this.http.get<any>(`${this.base}/admin/stats`, { headers: this.authHeaders() }); }
  ecoles() { return this.http.get<any[]>(`${this.base}/admin/ecoles`, { headers: this.authHeaders() }); }
  createEcole(d:any) { return this.http.post<any>(`${this.base}/admin/ecoles`, d, { headers: this.authHeaders() }); }
  regenKey(id:number) { return this.http.post<any>(`${this.base}/admin/ecoles/${id}/regen-key`, {}, { headers: this.authHeaders() }); }
  eleves(search='', ecole_id?:number) {
    const params:any = {};
    if (search) params.search = search;
    if (ecole_id) params.ecole_id = String(ecole_id);
    return this.http.get<any[]>(`${this.base}/admin/eleves`, { headers: this.authHeaders(), params });
  }
  eleveDetail(id:number) { return this.http.get<any>(`${this.base}/admin/eleves/${id}`, { headers: this.authHeaders() }); }
  createEleve(d:any) { return this.http.post<any>(`${this.base}/admin/eleves`, d, { headers: this.authHeaders() }); }
  parents() { return this.http.get<any[]>(`${this.base}/admin/parents`, { headers: this.authHeaders() }); }
  infos() { return this.http.get<any[]>(`${this.base}/admin/infos`, { headers: this.authHeaders() }); }
  createInfo(d:any) { return this.http.post<any>(`${this.base}/admin/infos`, d, { headers: this.authHeaders() }); }
  deleteInfo(id:number) { return this.http.delete<any>(`${this.base}/admin/infos/${id}`, { headers: this.authHeaders() }); }
  dossiers(eleve_id?:number, cle_unique?:string) {
    const params:any = {};
    if (eleve_id) params.eleve_id = String(eleve_id);
    if (cle_unique) params.cle_unique = cle_unique;
    return this.http.get<any>(`${this.base}/admin/dossiers`, { headers: this.authHeaders(), params });
  }
}
