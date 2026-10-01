import { AsyncPipe, CurrencyPipe, DecimalPipe } from '@angular/common';
import { Component, OnInit, inject } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';

import { ProductQuery } from '../../models';
import {
  loadProducts,
  selectAllProducts,
  selectProductsError,
  selectProductsLoading,
  selectProductsQuery,
  selectProductsTotal,
  selectTotalPages,
} from '../../store/products';

@Component({
  selector: 'app-products-list-page',
  imports: [
    AsyncPipe,
    CurrencyPipe,
    DecimalPipe,
    RouterLink,
    ReactiveFormsModule,
    MatCardModule,
    MatTableModule,
    MatPaginatorModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
  ],
  templateUrl: './products-list-page.html',
  styleUrl: './products-list-page.scss',
})
export class ProductsListPage implements OnInit {
  private readonly store = inject(Store);

  // Data for the template comes only from the store via store.select() + async pipe.
  protected readonly products$ = this.store.select(selectAllProducts);
  protected readonly loading$ = this.store.select(selectProductsLoading);
  protected readonly totalPages$ = this.store.select(selectTotalPages);
  protected readonly total$ = this.store.select(selectProductsTotal);
  protected readonly query$ = this.store.select(selectProductsQuery);
  protected readonly error$ = this.store.select(selectProductsError);

  protected readonly columns = ['image', 'name', 'externalCode', 'price', 'discount'];
  protected readonly pageSizes = [10, 20, 50, 100];

  private readonly currentQuery = this.store.selectSignal(selectProductsQuery);

  protected readonly filters = inject(NonNullableFormBuilder).group({
    name: this.currentQuery().name ?? '',
    priceMin: this.currentQuery().priceMin?.toString() ?? '',
    priceMax: this.currentQuery().priceMax?.toString() ?? '',
  });

  ngOnInit(): void {
    // Reuses the last query kept in the store (e.g. when coming back from a product card).
    this.load(this.currentQuery());
  }

  applyFilters(): void {
    const { name, priceMin, priceMax } = this.filters.getRawValue();
    this.load({
      ...this.currentQuery(),
      page: 1,
      name: name.trim() || null,
      priceMin: toNumber(priceMin),
      priceMax: toNumber(priceMax),
    });
  }

  resetFilters(): void {
    this.filters.reset({ name: '', priceMin: '', priceMax: '' });
    this.applyFilters();
  }

  onPage(event: PageEvent): void {
    this.load({ ...this.currentQuery(), page: event.pageIndex + 1, limit: event.pageSize });
  }

  private load(query: ProductQuery): void {
    this.store.dispatch(loadProducts({ query }));
  }
}

function toNumber(value: string): number | null {
  const normalized = value.replace(',', '.').trim();
  if (normalized === '') {
    return null;
  }
  const parsed = Number(normalized);
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : null;
}
