export interface PageMeta {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export interface ProductQuery {
  page: number;
  limit: number;
  name: string;
  priceMin: number | null;
  priceMax: number | null;
}
