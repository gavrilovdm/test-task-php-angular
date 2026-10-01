import { createAction, props } from '@ngrx/store';

import { PaginationMeta, ProductQuery, ProductSummary } from '../../models';

export const loadProducts = createAction('[Products] Load Products', props<{ query: ProductQuery }>());

export const loadProductsSuccess = createAction(
  '[Products API] Load Products Success',
  props<{ products: ProductSummary[]; meta: PaginationMeta }>(),
);

export const loadProductsFailure = createAction('[Products API] Load Products Failure', props<{ error: string }>());
