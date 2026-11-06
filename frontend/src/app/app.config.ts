import { registerLocaleData } from '@angular/common';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import localeRu from '@angular/common/locales/ru';
import { ApplicationConfig, LOCALE_ID, isDevMode, provideBrowserGlobalErrorListeners, provideZonelessChangeDetection } from '@angular/core';
import { MatPaginatorIntl } from '@angular/material/paginator';
import { provideRouter } from '@angular/router';
import { provideEffects } from '@ngrx/effects';
import { provideStore } from '@ngrx/store';
import { provideStoreDevtools } from '@ngrx/store-devtools';
import { apiInterceptor } from './core/interceptors/api.interceptor';
import { routes } from './app.routes';
import { ImportEffects } from './state/import/import.effects';
import { importFeatureKey, importReducer } from './state/import/import.reducer';
import { ProductsEffects } from './state/products/products.effects';
import { productsFeatureKey, productsReducer } from './state/products/products.reducer';

registerLocaleData(localeRu);

const paginator = new MatPaginatorIntl();
paginator.itemsPerPageLabel = 'На странице';
paginator.nextPageLabel = 'Дальше';
paginator.previousPageLabel = 'Назад';
paginator.firstPageLabel = 'В начало';
paginator.lastPageLabel = 'В конец';
paginator.getRangeLabel = (page, pageSize, length) => {
  if (length === 0 || pageSize === 0) {
    return `0 из ${length}`;
  }
  const start = page * pageSize;
  const end = Math.min(start + pageSize, length);
  return `${start + 1}–${end} из ${length}`;
};

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideZonelessChangeDetection(),
    provideRouter(routes),
    provideHttpClient(withInterceptors([apiInterceptor])),
    provideStore({
      [productsFeatureKey]: productsReducer,
      [importFeatureKey]: importReducer,
    }),
    provideEffects(ProductsEffects, ImportEffects),
    provideStoreDevtools({ maxAge: 25, logOnly: !isDevMode() }),
    { provide: LOCALE_ID, useValue: 'ru' },
    { provide: MatPaginatorIntl, useValue: paginator },
  ],
};
