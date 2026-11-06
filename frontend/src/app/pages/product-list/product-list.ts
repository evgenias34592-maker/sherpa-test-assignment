import { AsyncPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { ActivatedRoute, ParamMap, Router, RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';
import { formatDiscount, formatMoney } from '../../core/format';
import { ProductService } from '../../core/services/product.service';
import { ProductQuery } from '../../models/page.model';
import { parsePrice, productQueryFromParams, productQueryParams, sameProductQuery } from '../../models/product-query';
import { loadProducts } from '../../state/products/products.actions';
import { selectProductsView } from '../../state/products/products.selectors';

@Component({
  selector: 'app-product-list',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    AsyncPipe,
    ReactiveFormsModule,
    RouterLink,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatPaginatorModule,
    MatProgressBarModule,
  ],
  templateUrl: './product-list.html',
  styleUrl: './product-list.scss',
})
export class ProductList {
  private readonly store = inject(Store);
  private readonly products = inject(ProductService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly formBuilder = inject(FormBuilder);

  protected readonly vm$ = this.store.select(selectProductsView);
  protected readonly money = formatMoney;
  protected readonly discount = formatDiscount;
  protected readonly exportError = signal<string | null>(null);
  protected readonly filters = this.formBuilder.nonNullable.group({
    name: '',
    priceMin: '',
    priceMax: '',
  });

  private query: ProductQuery = { page: 1, limit: 20, name: '', priceMin: null, priceMax: null };
  private filtersSynced = false;

  constructor() {
    this.route.queryParamMap.pipe(takeUntilDestroyed()).subscribe((params) => {
      this.applyParams(params);
    });
  }

  protected search(): void {
    const raw = this.filters.getRawValue();
    this.navigate({
      page: 1,
      limit: this.query.limit,
      name: raw.name,
      priceMin: parsePrice(raw.priceMin),
      priceMax: parsePrice(raw.priceMax),
    });
  }

  protected reset(): void {
    this.navigate({ page: 1, limit: this.query.limit, name: '', priceMin: null, priceMax: null });
  }

  protected onPage(event: PageEvent): void {
    this.navigate({ ...this.query, page: event.pageIndex + 1, limit: event.pageSize });
  }

  protected download(): void {
    this.exportError.set(null);
    this.products.export(this.query).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'products.xlsx';
        link.click();
        URL.revokeObjectURL(url);
      },
      error: (error: unknown) => {
        void this.readExportError(error).then((message) => this.exportError.set(message));
      },
    });
  }

  private applyParams(params: ParamMap): void {
    const next = productQueryFromParams((key) => params.get(key));
    if (!sameProductQuery((key) => params.get(key), next)) {
      void this.router.navigate([], {
        relativeTo: this.route,
        queryParams: productQueryParams(next),
        replaceUrl: true,
      });
      return;
    }
    const filtersChanged = !this.filtersSynced
      || next.name !== this.query.name
      || next.priceMin !== this.query.priceMin
      || next.priceMax !== this.query.priceMax;
    this.query = next;
    this.filtersSynced = true;
    if (filtersChanged) {
      this.filters.patchValue({
        name: next.name,
        priceMin: next.priceMin === null ? '' : String(next.priceMin),
        priceMax: next.priceMax === null ? '' : String(next.priceMax),
      }, { emitEvent: false });
    }
    this.exportError.set(null);
    this.store.dispatch(loadProducts(next));
  }

  private navigate(query: ProductQuery): void {
    const params = productQueryParams(query);
    if (sameProductQuery((key) => this.route.snapshot.queryParamMap.get(key), query)) {
      this.store.dispatch(loadProducts(query));
      return;
    }
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: params,
    });
  }

  private async readExportError(error: unknown): Promise<string> {
    const fallback = 'Не удалось выгрузить файл';
    if (!(error instanceof HttpErrorResponse)) {
      return fallback;
    }
    const body = error.error;
    if (body instanceof Blob) {
      try {
        const parsed = JSON.parse(await body.text()) as { error?: string };
        if (typeof parsed.error === 'string' && parsed.error !== '') {
          return parsed.error;
        }
      } catch {
        return fallback;
      }
    }
    if (typeof body === 'object' && body !== null && 'error' in body && typeof body.error === 'string') {
      return body.error;
    }
    return fallback;
  }
}
