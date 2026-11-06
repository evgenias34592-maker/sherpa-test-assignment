import { ProductAttribute } from './product-attribute.model';
import { ProductImage } from './product-image.model';

export interface Product {
  id: number;
  externalCode: string;
  name: string;
  price: string;
  discount: string;
  updatedAt: string;
  cover: string | null;
}

export interface ProductDetails extends Omit<Product, 'cover'> {
  description: string;
  attributes: ProductAttribute[];
  images: ProductImage[];
}
