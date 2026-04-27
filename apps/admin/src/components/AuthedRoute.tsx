import { useIsAuthenticated } from '@refinedev/core';
import { useTranslation } from 'react-i18next';
import { Navigate } from 'react-router';

interface Props {
  children: React.ReactNode;
}

export function AuthedRoute({ children }: Props) {
  const { t } = useTranslation();
  const { data, isLoading } = useIsAuthenticated();

  if (isLoading) {
    return <p className="p-6 text-sm text-muted-foreground">{t('app.loading')}</p>;
  }
  if (!data?.authenticated) {
    return <Navigate to="/login" replace />;
  }
  return <>{children}</>;
}
