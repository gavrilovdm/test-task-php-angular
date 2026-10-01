import { createFeature, createReducer, on } from '@ngrx/store';

import { ProductQuery, ProductSummary } from '../../models';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

export const DEFAULT_PAGE_SIZE = 20;

export interface ProductsState {
  products: ProductSummary[];
  query: ProductQuery;
  total: number;
  totalPages: number;
  loading: boolean;
  error: string | null;
}

export const initialProductsState: ProductsState = {
  products: [],
  query: { page: 1, limit: DEFAULT_PAGE_SIZE, name: null, priceMin: null, priceMax: null },
  total: 0,
  totalPages: 0,
  loading: false,
  error: null,
};

export const productsFeature = createFeature({
  name: 'products',
  reducer: createReducer(
    initialProductsState,
    on(loadProducts, (state, { query }): ProductsState => ({ ...state, query, loading: true, error: null })),
    on(
      loadProductsSuccess,
      (state, { products, meta }): ProductsState => ({
        ...state,
        products,
        total: meta.total,
        totalPages: meta.totalPages,
        loading: false,
      }),
    ),
    on(loadProductsFailure, (state, { error }): ProductsState => ({ ...state, loading: false, error })),
  ),
});
