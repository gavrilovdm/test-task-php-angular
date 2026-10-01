import { AsyncPipe, CurrencyPipe, DatePipe, DecimalPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, input, signal } from '@angular/core';
import { toObservable } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatDividerModule } from '@angular/material/divider';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { RouterLink } from '@angular/router';
import { Observable, catchError, map, of, startWith, switchMap, tap } from 'rxjs';

import { Product, ProductImage } from '../../models';
import { ProductService } from '../../services/product.service';
import { LatestImportPanel } from '../../shared/latest-import-panel/latest-import-panel';

interface ProductView {
  loading: boolean;
  product: Product | null;
  error: string | null;
}

@Component({
  selector: 'app-product-detail-page',
  imports: [
    AsyncPipe,
    CurrencyPipe,
    DatePipe,
    DecimalPipe,
    RouterLink,
    MatCardModule,
    MatButtonModule,
    MatChipsModule,
    MatDividerModule,
    MatIconModule,
    MatProgressBarModule,
    MatTableModule,
    LatestImportPanel,
  ],
  templateUrl: './product-detail-page.html',
  styleUrl: './product-detail-page.scss',
})
export class ProductDetailPage {
  private readonly productService = inject(ProductService);

  /** Route parameter :id (bound via withComponentInputBinding). */
  readonly id = input.required<string>();

  protected readonly selectedImage = signal(0);
  protected readonly attributeColumns = ['key', 'value'];

  protected readonly view$: Observable<ProductView> = toObservable(this.id).pipe(
    tap(() => this.selectedImage.set(0)),
    switchMap((id) =>
      this.productService.getProduct(Number(id)).pipe(
        map((product): ProductView => ({ loading: false, product, error: null })),
        catchError((err: unknown) =>
          of<ProductView>({
            loading: false,
            product: null,
            error: err instanceof HttpErrorResponse && err.status === 404 ? 'Товар не найден' : 'Не удалось загрузить товар',
          }),
        ),
        startWith<ProductView>({ loading: true, product: null, error: null }),
      ),
    ),
  );


  imageSrc(image: ProductImage): string {
    return image.path ?? image.url;
  }
}
