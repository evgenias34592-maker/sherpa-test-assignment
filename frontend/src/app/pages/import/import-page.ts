import { AsyncPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, OnInit, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { Store } from '@ngrx/store';
import { restoreImport, uploadImport } from '../../state/import/import.actions';
import { selectImportView } from '../../state/import/import.selectors';

@Component({
  selector: 'app-import-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [AsyncPipe, MatButtonModule, MatProgressBarModule],
  templateUrl: './import-page.html',
  styleUrl: './import-page.scss',
})
export class ImportPage implements OnInit {
  private readonly store = inject(Store);

  protected readonly selected = signal<File | null>(null);
  protected readonly view$ = this.store.select(selectImportView);

  ngOnInit(): void {
    this.store.dispatch(restoreImport());
  }

  protected onFile(event: Event): void {
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) {
      return;
    }
    this.selected.set(input.files?.item(0) ?? null);
  }

  protected submit(): void {
    const file = this.selected();
    if (file) {
      this.store.dispatch(uploadImport({ file }));
    }
  }
}
