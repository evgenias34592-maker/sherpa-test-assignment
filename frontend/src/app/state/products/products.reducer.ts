import { createReducer, on } from '@ngrx/store';
import { Product } from '../../models/product.model';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

export const productsFeatureKey = 'products';

export interface ProductsState {
  items: Product[];
  loading: boolean;
  error: string | null;
  page: number;
  limit: number;
  total: number;
  totalPages: number;
  name: string;
  priceMin: number | null;
  priceMax: number | null;
}

export const initialProductsState: ProductsState = {
  items: [],
  loading: false,
  error: null,
  page: 1,
  limit: 20,
  total: 0,
  totalPages: 0,
  name: '',
  priceMin: null,
  priceMax: null,
};

export const productsReducer = createReducer(
  initialProductsState,
  on(loadProducts, (state, action) => ({
    ...state,
    loading: true,
    error: null,
    page: action.page,
    limit: action.limit,
    name: action.name,
    priceMin: action.priceMin,
    priceMax: action.priceMax,
  })),
  on(loadProductsSuccess, (state, action) => ({
    ...state,
    loading: false,
    items: action.products,
    total: action.total,
    totalPages: action.totalPages,
    page: action.page,
    limit: action.limit,
  })),
  on(loadProductsFailure, (state, action) => ({
    ...state,
    loading: false,
    error: action.error,
  })),
);
