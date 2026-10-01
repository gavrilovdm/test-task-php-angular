import { productsFeature } from './products.reducer';

export const selectAllProducts = productsFeature.selectProducts;
export const selectProductsLoading = productsFeature.selectLoading;
export const selectTotalPages = productsFeature.selectTotalPages;
export const selectProductsTotal = productsFeature.selectTotal;
export const selectProductsQuery = productsFeature.selectQuery;
export const selectProductsError = productsFeature.selectError;
