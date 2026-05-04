import { useParams } from 'react-router';

import { ProductDetailPage } from './components/product-detail-page';

export function ProductShowPage() {
  const params = useParams<{ id: string }>();
  return <ProductDetailPage mode="edit" productId={params.id ?? ''} />;
}
