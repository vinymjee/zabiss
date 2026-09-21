import { Injectable, signal, computed, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { tap } from 'rxjs';
import { environment } from '../../environments/environment';

export interface Parent {
  id: number; nom: string; prenom: string; email: string; telephone?: string; statut?: string;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);
  private router = inject(Router);
  private base = environment.apiUrl;

  token = signal<string | null>(localStorage.getItem('zabiss_token'));
  parent = signal<Parent | null>(JSON.parse(localStorage.getItem('zabiss_parent') || 'null'));
  isLogged = computed(() => !!this.token());

  login(email: string, password: string) {
    return this.http.post<{token:string, parent:Parent}>(`${this.base}/auth/login`, { email, password }).pipe(
      tap(res => this.setSession(res.token, res.parent))
    );
  }
  register(data: {nom:string, prenom:string, email:string, telephone:string, password:string}) {
    return this.http.post<{token:string, parent:Parent}>(`${this.base}/auth/register`, data).pipe(
      tap(res => this.setSession(res.token, res.parent))
    );
  }
  me() {
    return this.http.get<{parent:Parent}>(`${this.base}/auth/me`).pipe(
      tap(r => { this.parent.set(r.parent); localStorage.setItem('zabiss_parent', JSON.stringify(r.parent)); })
    );
  }
  logout() {
    const t = this.token();
    if (t) this.http.post(`${this.base}/auth/logout`, {}).subscribe({complete:()=>{}});
    this.clear();
    this.router.navigate(['/login']);
  }
  private setSession(token:string, parent:Parent){
    localStorage.setItem('zabiss_token', token);
    localStorage.setItem('zabiss_parent', JSON.stringify(parent));
    this.token.set(token); this.parent.set(parent);
  }
  private clear(){
    localStorage.removeItem('zabiss_token'); localStorage.removeItem('zabiss_parent');
    this.token.set(null); this.parent.set(null);
  }
}
