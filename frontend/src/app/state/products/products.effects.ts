import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { of } from 'rxjs';
import { catchError, map, switchMap } from 'rxjs/operators';
import { ProductService } from '../../core/services/product.service';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

@Injectable()
export class ProductsEffects {
  private readonly actions$ = inject(Actions);
  private readonly products = inject(ProductService);

  readonly loadProducts$ = createEffect(() =>
    this.actions$.pipe(
      ofType(loadProducts),
      switchMap((action) =>
        this.products
          .load({
            page: action.page,
            limit: action.limit,
            name: action.name,
            priceMin: action.priceMin,
            priceMax: action.priceMax,
          })
          .pipe(
            map((response) =>
              loadProductsSuccess({
                products: response.data,
                total: response.meta.total,
                totalPages: response.meta.totalPages,
                page: response.meta.page,
                limit: response.meta.limit,
              }),
            ),
            catchError((error: unknown) =>
              of(
                loadProductsFailure({
                  error: error instanceof HttpErrorResponse
                    ? ((error.error as { error?: string } | null)?.error ?? 'Не удалось загрузить товары')
                    : 'Не удалось загрузить товары',
                }),
              ),
            ),
          ),
      ),
    ),
  );
}
