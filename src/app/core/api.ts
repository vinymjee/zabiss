import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environments/environment';

@Injectable({ providedIn: 'root' })
export class ApiService {
  private http = inject(HttpClient);
  private base = environment.apiUrl; // ex: http://localhost:8080/api

  get<T>(path: string, params?: Record<string,string>) {
    return this.http.get<T>(`${this.base}${path}`, { params });
  }
  post<T>(path: string, body: unknown) {
    return this.http.post<T>(`${this.base}${path}`, body);
  }
  put<T>(path: string, body: unknown) {
    return this.http.put<T>(`${this.base}${path}`, body);
  }
  delete<T>(path: string) {
    return this.http.delete<T>(`${this.base}${path}`);
  }
}
