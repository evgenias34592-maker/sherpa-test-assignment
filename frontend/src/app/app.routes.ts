import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./pages/login/login').then((module) => module.Login),
  },
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () => import('./layout/shell').then((module) => module.Shell),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'products' },
      {
        path: 'products',
        loadComponent: () => import('./pages/product-list/product-list').then((module) => module.ProductList),
      },
      {
        path: 'products/:id',
        loadComponent: () => import('./pages/product-detail/product-detail').then((module) => module.ProductDetail),
      },
      {
        path: 'import',
        loadComponent: () => import('./pages/import/import-page').then((module) => module.ImportPage),
      },
    ],
  },
  { path: '**', redirectTo: 'products' },
];
