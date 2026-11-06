import { createFeatureSelector, createSelector } from '@ngrx/store';
import { ProductsState, productsFeatureKey } from './products.reducer';

export const selectProductsState = createFeatureSelector<ProductsState>(productsFeatureKey);

export const selectAllProducts = createSelector(selectProductsState, (state) => state.items);

export const selectProductsLoading = createSelector(selectProductsState, (state) => state.loading);

export const selectTotalPages = createSelector(selectProductsState, (state) => state.totalPages);

export const selectProductsError = createSelector(selectProductsState, (state) => state.error);

export const selectProductsPage = createSelector(selectProductsState, (state) => state.page);

export const selectProductsLimit = createSelector(selectProductsState, (state) => state.limit);

export const selectProductsTotal = createSelector(selectProductsState, (state) => state.total);

export const selectProductsQuery = createSelector(selectProductsState, (state) => ({
  page: state.page,
  limit: state.limit,
  name: state.name,
  priceMin: state.priceMin,
  priceMax: state.priceMax,
}));

export const selectProductsView = createSelector(
  selectAllProducts,
  selectProductsLoading,
  selectTotalPages,
  selectProductsError,
  selectProductsPage,
  selectProductsLimit,
  selectProductsTotal,
  (products, loading, totalPages, error, page, limit, total) => ({
    products,
    loading,
    totalPages,
    error,
    page,
    limit,
    total,
  }),
);
