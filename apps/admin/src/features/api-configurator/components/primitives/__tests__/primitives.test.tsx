import { render, screen } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';
import { describe, expect, it } from 'vitest';

import {
  ApiToggle,
  AuthBadge,
  ConnStatusPill,
  CoverageBar,
  DirectionBadge,
  DirToggle,
  Field,
  JsonView,
  MethodPill,
  PaginationPill,
  RolePill,
  SectionLabel,
  SecurityNote,
  Segmented,
  TypeCompat,
} from '..';

expect.extend(toHaveNoViolations);

function Demo() {
  return (
    <main>
      <AuthBadge type="api_key" hint="X-Api-Key" />
      <DirectionBadge dir="inbound" label="Inbound" />
      <ConnStatusPill status="active" label="aktywne" />
      <MethodPill method="GET" />
      <RolePill value="read_list" />
      <PaginationPill kind="cursor" />
      <CoverageBar mapped={8} total={10} />
      <TypeCompat ok title="Typy zgodne" />
      <TypeCompat ok={false} title="Niezgodność typów" />
      <JsonView value={{ sku: 'A-1', active: true, qty: 3, note: null }} />
      <DirToggle value="inbound" onChange={() => {}} title="Zmień kierunek" />
      <Segmented
        ariaLabel="Kierunek"
        value="inbound"
        onChange={() => {}}
        options={[
          { value: 'inbound', label: 'Inbound' },
          { value: 'outbound', label: 'Outbound' },
        ]}
      />
      <ApiToggle on onChange={() => {}} ariaLabel="Wiązanie aktywne" />
      <Field label="Base URL" hint="https" required>
        <input aria-label="Base URL" />
      </Field>
      <SecurityNote tone="emerald">Ruch przez klienta SSRF-safe.</SecurityNote>
      <SectionLabel>Endpointy</SectionLabel>
    </main>
  );
}

describe('api-configurator primitives', () => {
  it('renders the semantic labels and tokens', () => {
    render(<Demo />);
    expect(screen.getByText('aktywne')).toBeInTheDocument();
    expect(screen.getByText('GET')).toBeInTheDocument();
    expect(screen.getByText('8/10 · 80%')).toBeInTheDocument();
    expect(screen.getByText('cursor')).toBeInTheDocument();
  });

  it('has no axe violations', async () => {
    const { container } = render(<Demo />);
    expect(await axe(container)).toHaveNoViolations();
  });
});
