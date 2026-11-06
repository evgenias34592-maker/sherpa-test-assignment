import { productQueryFromParams, productQueryParams, sameProductQuery } from './product-query';

describe('productQuery', () => {
  it('reads a saved catalog link', () => {
    const params = new URLSearchParams('page=2&limit=50&name=Бермуды&priceMin=1000&priceMax=1400');
    const query = productQueryFromParams((key) => params.get(key));

    expect(query).toEqual({
      page: 2,
      limit: 50,
      name: 'Бермуды',
      priceMin: 1000,
      priceMax: 1400,
    });
    expect(sameProductQuery((key) => params.get(key), query)).toBe(true);
  });

  it('falls back when the link has no catalog params', () => {
    const query = productQueryFromParams(() => null);

    expect(query).toEqual({ page: 1, limit: 20, name: '', priceMin: null, priceMax: null });
    expect(productQueryParams(query)).toEqual({ page: '1', limit: '20' });
  });

  it('drops an empty name and keeps a zero price', () => {
    expect(productQueryParams({
      page: 1,
      limit: 10,
      name: '  ',
      priceMin: 0,
      priceMax: null,
    })).toEqual({ page: '1', limit: '10', priceMin: '0' });
  });

  it('ignores a broken price and a limit above the api cap', () => {
    const params = new URLSearchParams('page=0&limit=500&priceMin=abc');
    const query = productQueryFromParams((key) => params.get(key));

    expect(query.page).toBe(1);
    expect(query.limit).toBe(100);
    expect(query.priceMin).toBeNull();
  });
});
