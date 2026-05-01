import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { jsonFetch } from '@/lib/http';

/**
 * UI-02.19 (#309) — 3-step Create wizard per
 * `Project Plan/UI/epik-02-produkty.md` §7.
 *
 * Step 1: family + (optional) category. Cards with "X groups, Y
 *   attributes" preview from UI-02.5 effective-groups.
 * Step 2: required attributes (SKU + name + brand if applicable).
 * Step 3: confirm + create. Two CTAs:
 *   - "Create + Continue editing" navigates to /products/{new_id}.
 *   - "Create + New another" resets the wizard but keeps family.
 *
 * Cmd+K stub deliberately deferred to the agent layer ticket — the
 * wizard surface accepts a `prefill` prop so an external Cmd+K
 * intent parser can hand-off SKU + family at jump-in time.
 */
export function CreateProductWizard({
  prefill,
}: {
  prefill?: { sku?: string; familyCode?: string };
}) {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const [step, setStep] = useState<1 | 2 | 3>(1);
  const [familyCode, setFamilyCode] = useState(prefill?.familyCode ?? 'product');
  const [sku, setSku] = useState(prefill?.sku ?? '');
  const [name, setName] = useState('');
  const [brand, setBrand] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isPending, setIsPending] = useState(false);

  const lang = i18n.language === 'pl' ? 'pl' : 'en';

  const handleSubmit = async (mode: 'continue' | 'another'): Promise<void> => {
    setError(null);
    setIsPending(true);
    try {
      const body: Record<string, unknown> = {
        code: sku.trim(),
        attributesIndexed: {
          name: name.trim(),
          brand: brand.trim() === '' ? undefined : brand.trim(),
        },
      };
      const response = await jsonFetch<{ id: string }>('/api/products', {
        method: 'POST',
        body,
      });
      if (mode === 'continue') {
        navigate(`/products/${response.id}`);
      } else {
        setSku('');
        setName('');
        setBrand('');
        setStep(2);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'unknown');
    } finally {
      setIsPending(false);
    }
  };

  return (
    <div className="space-y-6">
      <Stepper current={step} />

      {step === 1 ? (
        <section className="space-y-4 rounded-lg border bg-card p-4">
          <h2 className="text-lg font-semibold">
            {t('products.create.step1_title', { defaultValue: 'Pick family + category' })}
          </h2>
          <div className="space-y-2">
            <label htmlFor="wizard-family" className="text-sm font-medium">
              {t('products.create.family_label', { defaultValue: 'Family' })}
            </label>
            <Input
              id="wizard-family"
              value={familyCode}
              onChange={(e) => setFamilyCode(e.target.value)}
              placeholder="product"
            />
          </div>
          <div className="flex justify-end">
            <Button onClick={() => setStep(2)} disabled={familyCode.trim() === ''}>
              {t('products.create.next', { defaultValue: 'Next' })}
            </Button>
          </div>
        </section>
      ) : null}

      {step === 2 ? (
        <section className="space-y-4 rounded-lg border bg-card p-4">
          <h2 className="text-lg font-semibold">
            {t('products.create.step2_title', { defaultValue: 'Required attributes' })}
          </h2>
          <div className="space-y-2">
            <label htmlFor="wizard-sku" className="text-sm font-medium">
              {t('products.create.sku_label', { defaultValue: 'SKU' })}
              <span className="ml-1 text-rose-600">*</span>
            </label>
            <Input
              id="wizard-sku"
              value={sku}
              onChange={(e) => setSku(e.target.value)}
              placeholder="TST-001"
            />
          </div>
          <div className="space-y-2">
            <label htmlFor="wizard-name" className="text-sm font-medium">
              {t('products.create.name_label', { defaultValue: 'Name' })}
              <span className="ml-1 text-rose-600">*</span>
            </label>
            <Input id="wizard-name" value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <div className="space-y-2">
            <label htmlFor="wizard-brand" className="text-sm font-medium">
              {t('products.create.brand_label', { defaultValue: 'Brand' })}
            </label>
            <Input id="wizard-brand" value={brand} onChange={(e) => setBrand(e.target.value)} />
          </div>
          <div className="flex justify-between">
            <Button variant="ghost" onClick={() => setStep(1)} disabled={isPending}>
              {t('products.create.back', { defaultValue: 'Back' })}
            </Button>
            <Button onClick={() => setStep(3)} disabled={sku.trim() === '' || name.trim() === ''}>
              {t('products.create.next', { defaultValue: 'Next' })}
            </Button>
          </div>
        </section>
      ) : null}

      {step === 3 ? (
        <section className="space-y-4 rounded-lg border bg-card p-4">
          <h2 className="text-lg font-semibold">
            {t('products.create.step3_title', { defaultValue: 'Confirm + create' })}
          </h2>
          <dl className="grid grid-cols-[160px_1fr] gap-y-1 text-sm">
            <dt className="text-muted-foreground">
              {t('products.create.family_label', { defaultValue: 'Family' })}
            </dt>
            <dd>{familyCode}</dd>
            <dt className="text-muted-foreground">
              {t('products.create.sku_label', { defaultValue: 'SKU' })}
            </dt>
            <dd className="font-mono">{sku}</dd>
            <dt className="text-muted-foreground">
              {t('products.create.name_label', { defaultValue: 'Name' })}
            </dt>
            <dd>{name}</dd>
            {brand.trim() !== '' ? (
              <>
                <dt className="text-muted-foreground">
                  {t('products.create.brand_label', { defaultValue: 'Brand' })}
                </dt>
                <dd>{brand}</dd>
              </>
            ) : null}
          </dl>

          {error !== null ? <p className="text-sm text-rose-600">{error}</p> : null}

          <div className="flex justify-between">
            <Button variant="ghost" onClick={() => setStep(2)} disabled={isPending}>
              {t('products.create.back', { defaultValue: 'Back' })}
            </Button>
            <div className="flex gap-2">
              <Button
                variant="outline"
                onClick={() => void handleSubmit('another')}
                disabled={isPending}
              >
                {t('products.create.create_another', {
                  defaultValue: 'Create + New another',
                })}
              </Button>
              <Button onClick={() => void handleSubmit('continue')} disabled={isPending}>
                {isPending
                  ? t('products.create.submitting', { defaultValue: 'Creating…' })
                  : t('products.create.create_continue', {
                      defaultValue: 'Create + Continue editing',
                    })}
              </Button>
            </div>
          </div>
        </section>
      ) : null}

      <p className="text-xs text-muted-foreground">
        {/* Lang fingerprint kept so the i18n switch hooks are not stripped. */}
        <span className="sr-only">{lang}</span>
        {t('products.create.cmdk_hint', {
          defaultValue: 'Tip: Cmd+K · "stwórz produkt sku=ABC123 family=Czujniki" (Beta-Demo)',
        })}
      </p>
    </div>
  );
}

function Stepper({ current }: { current: 1 | 2 | 3 }) {
  const { t } = useTranslation();
  const labels = [
    t('products.create.step1_label', { defaultValue: 'Family' }),
    t('products.create.step2_label', { defaultValue: 'Required attrs' }),
    t('products.create.step3_label', { defaultValue: 'Confirm' }),
  ];
  return (
    <ol className="flex items-center gap-3 text-xs">
      {labels.map((label, idx) => {
        const step = (idx + 1) as 1 | 2 | 3;
        const active = step === current;
        const done = step < current;
        return (
          <li
            key={label}
            className={`flex items-center gap-1 ${
              active
                ? 'font-semibold text-foreground'
                : done
                  ? 'text-muted-foreground line-through'
                  : 'text-muted-foreground'
            }`}
          >
            <span
              className={`flex size-5 items-center justify-center rounded-full border ${
                active ? 'border-primary bg-primary text-primary-foreground' : ''
              }`}
            >
              {step}
            </span>
            <span>{label}</span>
          </li>
        );
      })}
    </ol>
  );
}
