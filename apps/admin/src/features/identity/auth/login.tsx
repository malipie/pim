import { zodResolver } from '@hookform/resolvers/zod';
import { useLogin } from '@refinedev/core';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const loginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
});

type LoginValues = z.infer<typeof loginSchema>;

export function LoginPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { mutate: login, isPending } = useLogin<LoginValues>();
  const {
    register,
    handleSubmit,
    formState: { errors },
    setError,
  } = useForm<LoginValues>({ resolver: zodResolver(loginSchema) });

  const submit = handleSubmit((values) => {
    login(values, {
      onSuccess: (response) => {
        // Refine collapses both validation and HTTP failures into a successful
        // mutation with `success: false`; the routerProvider would normally
        // honor the redirect — we do it manually since the admin uses plain
        // react-router-7 instead of @refinedev/react-router.
        if (response?.success === false) {
          const message =
            (response.error as { message?: string } | undefined)?.message ?? 'auth.login_failed';
          setError('root', { message });
          return;
        }
        const target = response?.redirectTo ?? '/dashboard';
        navigate(typeof target === 'string' ? target : '/dashboard', { replace: true });
      },
      onError: () => {
        setError('root', { message: 'auth.login_failed' });
      },
    });
  });

  const rootErrorKey = errors.root?.message ?? null;

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle>{t('auth.login_title')}</CardTitle>
          <CardDescription>{t('auth.login_subtitle')}</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4" noValidate>
            <div className="space-y-2">
              <Label htmlFor="email">{t('auth.email')}</Label>
              <Input
                id="email"
                type="email"
                autoComplete="username"
                aria-invalid={errors.email ? 'true' : 'false'}
                {...register('email')}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">{t('auth.password')}</Label>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                aria-invalid={errors.password ? 'true' : 'false'}
                {...register('password')}
              />
            </div>
            {rootErrorKey ? (
              <p className="text-sm text-destructive" role="alert">
                {t(rootErrorKey)}
              </p>
            ) : null}
            <Button type="submit" className="w-full" disabled={isPending}>
              {isPending ? t('auth.submitting') : t('auth.submit')}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
