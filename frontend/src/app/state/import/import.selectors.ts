import { createFeatureSelector, createSelector } from '@ngrx/store';
import { ImportState, importFeatureKey } from './import.reducer';

export const selectImportState = createFeatureSelector<ImportState>(importFeatureKey);

export const selectImportJob = createSelector(selectImportState, (state) => state.job);

export const selectImportError = createSelector(selectImportState, (state) => state.error);

export const selectImportUploading = createSelector(selectImportState, (state) => state.uploading);

export const selectImportPolling = createSelector(selectImportState, (state) => state.polling);

export const selectActiveImportJob = createSelector(selectImportState, (state) =>
  state.activeJobId && state.job ? state.job : null,
);

export const selectImportView = createSelector(
  selectImportJob,
  selectImportError,
  selectImportUploading,
  (job, error, uploading) => {
    if (uploading) {
      return { state: 'uploading' as const, job: null as ImportState['job'], error: null as string | null };
    }
    if (error) {
      return { state: 'error' as const, job: null as ImportState['job'], error };
    }
    if (job) {
      return { state: 'job' as const, job, error: null as string | null };
    }
    return { state: 'idle' as const, job: null as ImportState['job'], error: null as string | null };
  },
);
