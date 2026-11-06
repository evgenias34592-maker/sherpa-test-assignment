import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { EMPTY, of, timer } from 'rxjs';
import { catchError, distinctUntilChanged, exhaustMap, map, mergeMap, switchMap, takeWhile, tap } from 'rxjs/operators';
import { ImportService } from '../../core/services/import.service';
import {
  importJobUpdated,
  importPollingStopped,
  restoreImport,
  startImportPolling,
  uploadImport,
  uploadImportFailure,
  uploadImportSuccess,
} from './import.actions';

export const ACTIVE_IMPORT = 'products.activeImport';

@Injectable()
export class ImportEffects {
  private readonly actions$ = inject(Actions);
  private readonly imports = inject(ImportService);

  readonly restoreImport$ = createEffect(() =>
    this.actions$.pipe(
      ofType(restoreImport),
      map(() => sessionStorage.getItem(ACTIVE_IMPORT)),
      map((jobId) => (jobId ? startImportPolling({ jobId }) : importPollingStopped())),
    ),
  );

  readonly uploadImport$ = createEffect(() =>
    this.actions$.pipe(
      ofType(uploadImport),
      switchMap((action) =>
        this.imports.upload(action.file).pipe(
          tap((job) => sessionStorage.setItem(ACTIVE_IMPORT, job.id)),
          map((job) => uploadImportSuccess({ job })),
          catchError((error: unknown) =>
            of(
              uploadImportFailure({
                error:
                  error instanceof HttpErrorResponse
                    ? ((error.error as { error?: string } | null)?.error ?? 'Не удалось начать импорт')
                    : 'Не удалось начать импорт',
              }),
            ),
          ),
        ),
      ),
    ),
  );

  readonly startPollingAfterUpload$ = createEffect(() =>
    this.actions$.pipe(
      ofType(uploadImportSuccess),
      map(({ job }) => startImportPolling({ jobId: job.id })),
    ),
  );

  readonly pollImportJob$ = createEffect(() =>
    this.actions$.pipe(
      ofType(startImportPolling),
      map(({ jobId }) => jobId),
      distinctUntilChanged(),
      switchMap((jobId) =>
        timer(0, 1000).pipe(
          exhaustMap(() =>
            this.imports.get(jobId).pipe(
              catchError(() => EMPTY),
            ),
          ),
          takeWhile((job) => job.status === 'pending' || job.status === 'processing', true),
          mergeMap((job) => {
            if (job.status === 'pending' || job.status === 'processing') {
              return of(importJobUpdated({ job }));
            }

            return of(importJobUpdated({ job }), importPollingStopped());
          }),
        ),
      ),
    ),
  );
}
