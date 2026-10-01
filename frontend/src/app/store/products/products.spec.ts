import { TestBed } from '@angular/core/testing';
import { provideMockActions } from '@ngrx/effects/testing';
import { Action } from '@ngrx/store';
import { Observable, of, throwError } from 'rxjs';
import { HttpErrorResponse } from '@angular/common/http';

import { Paginated, ProductSummary } from '../../models';
import { ProductService } from '../../services/product.service';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';
import { ProductsEffects } from './products.effects';
import { initialProductsState, productsFeature } from './products.reducer';
import { selectAllProducts, selectProductsLoading, selectTotalPages } from './products.selectors';

const product: ProductSummary = { id: 1, externalCode: 'A', name: 'Боди', price: 1899, discount: 33.33, image: null };
const query = { page: 2, limit: 20, name: 'боди', priceMin: 100, priceMax: null };
const meta = { page: 2, limit: 20, total: 41, totalPages: 3 };

describe('products reducer', () => {
  const reducer = productsFeature.reducer;

  it('sets loading and query on loadProducts', () => {
    const state = reducer(initialProductsState, loadProducts({ query }));
    expect(state.loading).toBeTrue();
    expect(state.query).toEqual(query);
    expect(state.error).toBeNull();
  });

  it('stores page data on success', () => {
    const state = reducer({ ...initialProductsState, loading: true }, loadProductsSuccess({ products: [product], meta }));
    expect(state.loading).toBeFalse();
    expect(state.products).toEqual([product]);
    expect(state.total).toBe(41);
    expect(state.totalPages).toBe(3);
  });

  it('stores error on failure', () => {
    const state = reducer({ ...initialProductsState, loading: true }, loadProductsFailure({ error: 'boom' }));
    expect(state.loading).toBeFalse();
    expect(state.error).toBe('boom');
  });
});

describe('products selectors', () => {
  it('select products, loading and total pages', () => {
    const state = { products: { ...initialProductsState, products: [product], loading: true, totalPages: 7 } };
    expect(selectAllProducts(state)).toEqual([product]);
    expect(selectProductsLoading(state)).toBeTrue();
    expect(selectTotalPages(state)).toBe(7);
  });
});

describe('ProductsEffects', () => {
  let actions$: Observable<Action>;
  let productService: jasmine.SpyObj<ProductService>;
  let effects: ProductsEffects;

  beforeEach(() => {
    productService = jasmine.createSpyObj<ProductService>('ProductService', ['getProducts']);
    TestBed.configureTestingModule({
      providers: [ProductsEffects, provideMockActions(() => actions$), { provide: ProductService, useValue: productService }],
    });
    effects = TestBed.inject(ProductsEffects);
  });

  it('loads products through ProductService', (done) => {
    const response: Paginated<ProductSummary> = { data: [product], meta };
    productService.getProducts.and.returnValue(of(response));
    actions$ = of(loadProducts({ query }));

    effects.loadProducts$.subscribe((action) => {
      expect(productService.getProducts).toHaveBeenCalledWith(query);
      expect(action).toEqual(loadProductsSuccess({ products: [product], meta }));
      done();
    });
  });

  it('maps HTTP errors to loadProductsFailure', (done) => {
    productService.getProducts.and.returnValue(
      throwError(() => new HttpErrorResponse({ status: 422, error: { error: 'Invalid', errors: { limit: 'too big' } } })),
    );
    actions$ = of(loadProducts({ query }));

    effects.loadProducts$.subscribe((action) => {
      expect(action).toEqual(loadProductsFailure({ error: 'too big' }));
      done();
    });
  });
});
