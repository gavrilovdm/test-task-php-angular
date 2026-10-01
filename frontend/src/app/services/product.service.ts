import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { API_BASE_URL } from '../core/app-config.tokens';
import { Paginated, Product, ProductQuery, ProductSummary } from '../models';

@Injectable({ providedIn: 'root' })
export class ProductService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${inject(API_BASE_URL)}/products`;

  /** Server-side paginated and filtered list: GET /api/products?page=&limit=&name=&price_min=&price_max= */
  getProducts(query: ProductQuery): Observable<Paginated<ProductSummary>> {
    let params = new HttpParams().set('page', query.page).set('limit', query.limit);
    if (query.name) {
      params = params.set('name', query.name);
    }
    if (query.priceMin != null) {
      params = params.set('price_min', query.priceMin);
    }
    if (query.priceMax != null) {
      params = params.set('price_max', query.priceMax);
    }
    return this.http.get<Paginated<ProductSummary>>(this.baseUrl, { params });
  }

  getProduct(id: number): Observable<Product> {
    return this.http.get<Product>(`${this.baseUrl}/${id}`);
  }
}
