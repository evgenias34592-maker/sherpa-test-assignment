import { ImportJob } from '../../models/import-job.model';
import { importJobUpdated, uploadImport, uploadImportFailure, uploadImportSuccess } from './import.actions';
import { importReducer, initialImportState } from './import.reducer';

const sampleJob = (status: ImportJob['status'] = 'pending'): ImportJob => ({
  id: '11111111-1111-4111-8111-111111111111',
  status,
  originalFilename: 'catalog.xlsx',
  totalRows: 10,
  processedRows: 0,
  createdCount: 0,
  updatedCount: 0,
  errorCount: 0,
  warningCount: 0,
  progress: 0,
  errors: [],
  message: null,
  createdAt: '2026-01-01T00:00:00+00:00',
  startedAt: null,
  finishedAt: null,
});

describe('importReducer', () => {
  it('marks upload in progress', () => {
    const state = importReducer(initialImportState, uploadImport({ file: new File([], 'a.xlsx') }));
    expect(state.uploading).toBe(true);
    expect(state.error).toBeNull();
  });

  it('stores job after successful upload', () => {
    const job = sampleJob();
    const state = importReducer(initialImportState, uploadImportSuccess({ job }));
    expect(state.uploading).toBe(false);
    expect(state.job).toEqual(job);
    expect(state.activeJobId).toBe(job.id);
    expect(state.polling).toBe(true);
  });

  it('keeps error message on failed upload', () => {
    const state = importReducer(initialImportState, uploadImportFailure({ error: 'Файл слишком большой' }));
    expect(state.uploading).toBe(false);
    expect(state.error).toBe('Файл слишком большой');
  });

  it('updates progress from polling', () => {
    const running = sampleJob('processing');
    const done = { ...running, status: 'completed' as const, progress: 100, processedRows: 10 };
    const state = importReducer(
      importReducer(initialImportState, uploadImportSuccess({ job: running })),
      importJobUpdated({ job: done }),
    );
    expect(state.job?.status).toBe('completed');
    expect(state.polling).toBe(false);
  });
});
