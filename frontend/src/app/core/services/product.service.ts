import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { PageMeta, ProductQuery } from '../../models/page.model';
import { Product, ProductDetails } from '../../models/product.model';

export interface ProductPage {
  data: Product[];
  meta: PageMeta;
}

@Injectable({ providedIn: 'root' })
export class ProductService {
  private readonly http = inject(HttpClient);

  load(query: ProductQuery): Observable<ProductPage> {
    return this.http.get<ProductPage>('/api/products', { params: this.params(query) });
  }

  get(id: number): Observable<ProductDetails> {
    return this.http
      .get<{ data: ProductDetails }>(`/api/products/${id}`)
      .pipe(map((response) => response.data));
  }

  export(query: ProductQuery): Observable<Blob> {
    return this.http.get('/api/products/export', {
      params: this.params(query),
      responseType: 'blob',
    });
  }

  private params(query: ProductQuery): HttpParams {
    let params = new HttpParams().set('page', query.page).set('limit', query.limit);
    if (query.name.trim() !== '') {
      params = params.set('name', query.name.trim());
    }
    if (query.priceMin !== null) {
      params = params.set('priceMin', query.priceMin);
    }
    if (query.priceMax !== null) {
      params = params.set('priceMax', query.priceMax);
    }
    return params;
  }
}
