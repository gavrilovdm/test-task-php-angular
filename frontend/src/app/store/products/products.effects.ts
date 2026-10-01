import { Injectable, inject } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { catchError, map, of, switchMap } from 'rxjs';

import { httpErrorMessage } from '../../core/http-error';
import { ProductService } from '../../services/product.service';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

@Injectable()
export class ProductsEffects {
  private readonly actions$ = inject(Actions);
  private readonly productService = inject(ProductService);

  readonly loadProducts$ = createEffect(() =>
    this.actions$.pipe(
      ofType(loadProducts),
      // switchMap cancels a stale request when filters / page change quickly
      switchMap(({ query }) =>
        this.productService.getProducts(query).pipe(
          map((response) => loadProductsSuccess({ products: response.data, meta: response.meta })),
          catchError((error: unknown) => of(loadProductsFailure({ error: httpErrorMessage(error, 'Не удалось загрузить товары') }))),
        ),
      ),
    ),
  );
}
