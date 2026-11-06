import { ProductQuery } from './page.model';

const QUERY_KEYS = ['page', 'limit', 'name', 'priceMin', 'priceMax'] as const;

export function productQueryFromParams(read: (key: string) => string | null): ProductQuery {
  const limit = positive(read('limit'), 20);
  return {
    page: positive(read('page'), 1),
    limit: limit > 100 ? 100 : limit,
    name: (read('name') ?? '').trim(),
    priceMin: parsePrice(read('priceMin') ?? ''),
    priceMax: parsePrice(read('priceMax') ?? ''),
  };
}

export function productQueryParams(query: ProductQuery): Record<string, string> {
  const params: Record<string, string> = {
    page: String(query.page),
    limit: String(query.limit),
  };
  const name = query.name.trim();
  if (name !== '') {
    params['name'] = name;
  }
  if (query.priceMin !== null) {
    params['priceMin'] = String(query.priceMin);
  }
  if (query.priceMax !== null) {
    params['priceMax'] = String(query.priceMax);
  }
  return params;
}

export function sameProductQuery(actual: (key: string) => string | null, query: ProductQuery): boolean {
  const expected = productQueryParams(query);
  return QUERY_KEYS.every((key) => (actual(key) ?? null) === (expected[key] ?? null));
}

export function parsePrice(value: string): number | null {
  const trimmed = value.trim().replace(',', '.');
  if (!/^\d+(\.\d+)?$/.test(trimmed)) {
    return null;
  }
  const parsed = Number(trimmed);
  return Number.isFinite(parsed) ? parsed : null;
}

function positive(value: string | null, fallback: number): number {
  if (value === null || !/^[1-9]\d*$/.test(value)) {
    return fallback;
  }
  const parsed = Number(value);
  return Number.isSafeInteger(parsed) ? parsed : fallback;
}
