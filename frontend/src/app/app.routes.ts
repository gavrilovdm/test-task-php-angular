import { Routes } from '@angular/router';

import { authGuard, guestGuard } from './core/auth.guard';

/** All pages are lazy-loaded standalone components. */
export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'products' },
  {
    path: 'login',
    canActivate: [guestGuard],
    title: 'Вход',
    loadComponent: () => import('./pages/login/login-page').then((m) => m.LoginPage),
  },
  {
    path: 'products',
    canActivate: [authGuard],
    title: 'Товары',
    loadComponent: () => import('./pages/products-list/products-list-page').then((m) => m.ProductsListPage),
  },
  {
    path: 'products/:id',
    canActivate: [authGuard],
    title: 'Карточка товара',
    loadComponent: () => import('./pages/product-detail/product-detail-page').then((m) => m.ProductDetailPage),
  },
  {
    path: 'import',
    canActivate: [authGuard],
    title: 'Импорт товаров',
    loadComponent: () => import('./pages/import/import-page').then((m) => m.ImportPage),
  },
  { path: '**', redirectTo: 'products' },
];
