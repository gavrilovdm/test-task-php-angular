import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideNoopAnimations } from '@angular/platform-browser/animations';
import { provideRouter } from '@angular/router';
import { MockStore, provideMockStore } from '@ngrx/store/testing';

import { initialProductsState, loadProducts } from '../../store/products';
import { ProductsListPage } from './products-list-page';

describe('ProductsListPage', () => {
  let store: MockStore;
  let fixture: ComponentFixture<ProductsListPage>;

  const byTestId = <T extends HTMLElement>(id: string): T => fixture.nativeElement.querySelector(`[data-testid="${id}"]`) as T;

  function type(id: string, value: string): void {
    const input = byTestId<HTMLInputElement>(id);
    input.value = value;
    input.dispatchEvent(new Event('input'));
  }

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
    fixture = TestBed.createComponent(ProductsListPage);
    fixture.detectChanges();
  });

  it('dispatches loadProducts on init and renders store data with links to the card', () => {
    expect(store.dispatch).toHaveBeenCalledWith(loadProducts({ query: initialProductsState.query }));
    const link = byTestId<HTMLAnchorElement>('product-link');
    expect(link.textContent).toContain('Леггинсы Nero');
    expect(link.getAttribute('href')).toBe('/products/7');
  });

  it('applies filters from the form starting from page 1', () => {
    type('filter-name', ' Nero ');
    type('filter-price-min', '100,5');
    type('filter-price-max', 'abc');
    byTestId<HTMLButtonElement>('filter-apply').click();

    expect(store.dispatch).toHaveBeenCalledWith(
      loadProducts({ query: { page: 1, limit: 20, name: 'Nero', priceMin: 100.5, priceMax: null } }),
    );
  });
});
