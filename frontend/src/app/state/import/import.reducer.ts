import { createReducer, on } from '@ngrx/store';
import { ImportJob } from '../../models/import-job.model';
import {
  importJobUpdated,
  importPollingStopped,
  restoreImport,
  uploadImport,
  uploadImportFailure,
  uploadImportSuccess,
} from './import.actions';

export const importFeatureKey = 'import';

export interface ImportState {
  uploading: boolean;
  polling: boolean;
  error: string | null;
  job: ImportJob | null;
  activeJobId: string | null;
}

export const initialImportState: ImportState = {
  uploading: false,
  polling: false,
  error: null,
  job: null,
  activeJobId: null,
};

export const importReducer = createReducer(
  initialImportState,
  on(restoreImport, (state) => ({
    ...state,
    error: null,
  })),
  on(uploadImport, (state) => ({
    ...state,
    uploading: true,
    error: null,
  })),
  on(uploadImportSuccess, (state, action) => ({
    ...state,
    uploading: false,
    polling: true,
    job: action.job,
    activeJobId: action.job.id,
  })),
  on(uploadImportFailure, (state, action) => ({
    ...state,
    uploading: false,
    error: action.error,
  })),
  on(importJobUpdated, (state, action) => ({
    ...state,
    job: action.job,
    activeJobId: action.job.id,
    polling: action.job.status === 'pending' || action.job.status === 'processing',
  })),
  on(importPollingStopped, (state) => ({
    ...state,
    polling: false,
  })),
);
