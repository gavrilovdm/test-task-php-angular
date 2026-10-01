export interface ProductAttribute {
  id: number;
  key: string;
  value: string;
}

export interface ProductImage {
  id: number;
  /** Original link from the import file. */
  url: string;
  /** Local copy served by the backend; null when the download failed. */
  path: string | null;
  position: number;
}

/** Product as returned by the list endpoint. */
export interface ProductSummary {
  id: number;
  externalCode: string;
  name: string;
  price: number;
  /** (price − purchase price) / price × 100 */
  discount: number | null;
  image: string | null;
}

/** Full product card. */
export interface Product extends ProductSummary {
  description: string | null;
  createdAt: string;
  updatedAt: string;
  attributes: ProductAttribute[];
  images: ProductImage[];
}

export interface ProductQuery {
  page: number;
  limit: number;
  name?: string | null;
  priceMin?: number | null;
  priceMax?: number | null;
}
