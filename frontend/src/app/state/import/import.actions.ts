import { createAction, props } from '@ngrx/store';
import { ImportJob } from '../../models/import-job.model';

export const restoreImport = createAction('[Import] Restore Active Job');

export const startImportPolling = createAction('[Import] Start Polling', props<{ jobId: string }>());

export const uploadImport = createAction('[Import] Upload', props<{ file: File }>());

export const uploadImportSuccess = createAction('[Import] Upload Success', props<{ job: ImportJob }>());

export const uploadImportFailure = createAction('[Import] Upload Failure', props<{ error: string }>());

export const importJobUpdated = createAction('[Import] Job Updated', props<{ job: ImportJob }>());

export const importPollingStopped = createAction('[Import] Polling Stopped');
