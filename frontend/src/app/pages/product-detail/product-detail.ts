import { AsyncPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';
import { Observable, catchError, map, of, startWith, switchMap } from 'rxjs';
import { formatDate, formatDiscount, formatMoney } from '../../core/format';
import { ProductService } from '../../core/services/product.service';
import { productQueryParams } from '../../models/product-query';
import { ProductDetails } from '../../models/product.model';
import { selectProductsQuery } from '../../state/products/products.selectors';

interface DetailView {
  status: 'loading' | 'ready' | 'error';
  product: ProductDetails | null;
  error: string | null;
}

@Component({
  selector: 'app-product-detail',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [AsyncPipe, RouterLink, MatButtonModule],
  templateUrl: './product-detail.html',
  styleUrl: './product-detail.scss',
})
export class ProductDetail {
  private readonly route = inject(ActivatedRoute);
  private readonly products = inject(ProductService);
  private readonly store = inject(Store);

  protected readonly listQuery$ = this.store.select(selectProductsQuery).pipe(
    map((query) => productQueryParams(query)),
  );

  protected readonly money = formatMoney;
  protected readonly discount = formatDiscount;
  protected readonly updated = formatDate;
  protected readonly selected = signal(0);

  protected readonly view$: Observable<DetailView> = this.route.paramMap.pipe(
    map((params) => Number(params.get('id'))),
    switchMap((id) => {
      this.selected.set(0);
      if (!Number.isInteger(id) || id <= 0) {
        return of({ status: 'error' as const, product: null, error: 'Товар не найден' });
      }
      return this.products.get(id).pipe(
        map((product) => ({ status: 'ready' as const, product, error: null })),
        startWith({ status: 'loading' as const, product: null, error: null }),
        catchError((error: unknown) =>
          of({
            status: 'error' as const,
            product: null,
            error: error instanceof HttpErrorResponse
              ? ((error.error as { error?: string } | null)?.error ?? 'Не удалось загрузить товар')
              : 'Не удалось загрузить товар',
          }),
        ),
      );
    }),
  );

  protected choose(index: number): void {
    this.selected.set(index);
  }
}
