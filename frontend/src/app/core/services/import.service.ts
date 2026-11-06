import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ImportJob } from '../../models/import-job.model';

@Injectable({ providedIn: 'root' })
export class ImportService {
  private readonly http = inject(HttpClient);

  upload(file: File): Observable<ImportJob> {
    const body = new FormData();
    body.set('file', file, file.name);
    return this.http
      .post<{ data: ImportJob }>('/api/imports', body)
      .pipe(map((response) => response.data));
  }

  get(id: string): Observable<ImportJob> {
    return this.http
      .get<{ data: ImportJob }>(`/api/imports/${id}`)
      .pipe(map((response) => response.data));
  }
}
