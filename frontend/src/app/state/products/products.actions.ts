import { createAction, props } from '@ngrx/store';
import { Product } from '../../models/product.model';

export const loadProducts = createAction(
  '[Products] Load Products',
  props<{
    page: number;
    limit: number;
    name: string;
    priceMin: number | null;
    priceMax: number | null;
  }>(),
);

export const loadProductsSuccess = createAction(
  '[Products] Load Products Success',
  props<{
    products: Product[];
    total: number;
    totalPages: number;
    page: number;
    limit: number;
  }>(),
);

export const loadProductsFailure = createAction(
  '[Products] Load Products Failure',
  props<{ error: string }>(),
);
