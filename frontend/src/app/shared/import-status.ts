import { AsyncPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, OnInit } from '@angular/core';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';
import { ImportJob } from '../models/import-job.model';
import { restoreImport } from '../state/import/import.actions';
import { selectActiveImportJob } from '../state/import/import.selectors';

@Component({
  selector: 'app-import-status',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [AsyncPipe, MatProgressBarModule, RouterLink],
  templateUrl: './import-status.html',
  styleUrl: './import-status.scss',
})
export class ImportStatus implements OnInit {
  private readonly store = inject(Store);

  protected readonly job$ = this.store.select(selectActiveImportJob);

  ngOnInit(): void {
    this.store.dispatch(restoreImport());
  }

  protected title(job: ImportJob): string {
    if (job.status === 'completed') {
      return 'Импорт завершён';
    }
    if (job.status === 'failed') {
      return 'Импорт остановлен';
    }
    return 'Импорт выполняется';
  }
}
