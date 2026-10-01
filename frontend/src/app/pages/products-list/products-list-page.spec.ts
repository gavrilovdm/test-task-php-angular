import { TestBed } from '@angular/core/testing';
import { provideNoopAnimations } from '@angular/platform-browser/animations';
import { provideRouter } from '@angular/router';
import { MockStore, provideMockStore } from '@ngrx/store/testing';

import { initialProductsState, loadProducts } from '../../store/products';
import { ProductsListPage } from './products-list-page';

describe('ProductsListPage', () => {
  let store: MockStore;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ProductsListPage],
      providers: [
        provideRouter([]),
        provideNoopAnimations(),
        provideMockStore({
          initialState: {
            products: {
              ...initialProductsState,
              products: [{ id: 7, externalCode: 'EXT-7', name: 'Леггинсы Nero', price: 1599, discount: 33.33, image: null }],
              total: 1,
              totalPages: 1,
            },
          },
        }),
      ],
    }).compileComponents();
    store = TestBed.inject(MockStore);
    spyOn(store, 'dispatch');
  });

  it('dispatches loadProducts on init and renders store data with links to the card', () => {
    const fixture = TestBed.createComponent(ProductsListPage);
    fixture.detectChanges();

    expect(store.dispatch).toHaveBeenCalledWith(loadProducts({ query: initialProductsState.query }));
    const link = fixture.nativeElement.querySelector('[data-testid="product-link"]') as HTMLAnchorElement;
    expect(link.textContent).toContain('Леггинсы Nero');
    expect(link.getAttribute('href')).toBe('/products/7');
  });

  it('applies filters starting from page 1', () => {
    const fixture = TestBed.createComponent(ProductsListPage);
    fixture.detectChanges();
    const component = fixture.componentInstance as unknown as { filters: { setValue(v: object): void }; applyFilters(): void };

    component.filters.setValue({ name: ' Nero ', priceMin: '100,5', priceMax: 'abc' });
    component.applyFilters();

    expect(store.dispatch).toHaveBeenCalledWith(
      loadProducts({ query: { page: 1, limit: 20, name: 'Nero', priceMin: 100.5, priceMax: null } }),
    );
  });
});
