import { AlertTriangle, ArrowLeft } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { Link } from 'react-router';

import { Button } from '@/components/ui/button';

/**
 * Issue #1043 — shared "not found / could not load" state for detail
 * pages. Replaces the previous behaviour where ProductDetailPage +
 * UniversalDetailPage stayed in an infinite `Ładowanie...` after the
 * underlying GET returned 404 (because the loading guard didn't
 * differentiate `data === undefined` while loading from
 * `data === undefined` after a failed fetch).
 *
 * Copy is passed through props (title / description / backLabel) so
 * `/products/{id}` and `/objects/:slug/:id` can each use their own
 * resource-typed wording without forking the component.
 *
 * a11y: outer container uses `role="alert"` so screen readers announce
 * the error state. Focus is moved to the back button on mount so
 * keyboard users can recover with Enter immediately.
 */
export interface DetailNotFoundStateProps {
  id: string;
  backHref: string;
  title: string;
  description: string;
  backLabel: string;
}

export function DetailNotFoundState({
  id: _id,
  backHref,
  title,
  description,
  backLabel,
}: DetailNotFoundStateProps) {
  const buttonRef = useRef<HTMLAnchorElement | null>(null);

  useEffect(() => {
    buttonRef.current?.focus();
  }, []);

  return (
    <div
      role="alert"
      className="mx-auto flex max-w-md flex-col items-center justify-center gap-4 px-6 py-16 text-center"
    >
      <div className="grid size-14 place-items-center rounded-full bg-amber-50 text-amber-700">
        <AlertTriangle className="size-7" aria-hidden="true" />
      </div>
      <h1 className="text-[20px] font-semibold tracking-tight text-zinc-900">{title}</h1>
      <p className="text-[13.5px] text-zinc-600">{description}</p>
      <Button asChild className="mt-2 h-9 rounded-xl px-4">
        <Link ref={buttonRef} to={backHref}>
          <ArrowLeft className="size-4" />
          {backLabel}
        </Link>
      </Button>
    </div>
  );
}
