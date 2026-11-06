export interface ImportIssue {
  row: number;
  externalCode: string | null;
  message: string;
  level: 'error' | 'warning';
}

export interface ImportJob {
  id: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  originalFilename: string;
  totalRows: number;
  processedRows: number;
  createdCount: number;
  updatedCount: number;
  errorCount: number;
  warningCount: number;
  progress: number;
  errors: ImportIssue[];
  message: string | null;
  createdAt: string;
  startedAt: string | null;
  finishedAt: string | null;
}
