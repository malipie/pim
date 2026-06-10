import { useParams } from 'react-router';

import { ProductDetailPage } from './components/product-detail-page';

/**
 * /products/:id — renders the unified object detail page (#1348/#1351).
 * The UX-09 `?universal=1` preview branch and the separate
 * UniversalDetailPage are retired: ProductDetailPage is now poly-kind
 * and serves /objects/:slug/:id too. `requireKind` keeps the legacy
 * sugar-route behaviour of 404-ing non-product ids.
 */
export function ProductShowPage() {
  const params = useParams<{ id: string }>();
  const productId = params.id ?? '';

  return <ProductDetailPage mode="edit" productId={productId} requireKind="product" />;
}
