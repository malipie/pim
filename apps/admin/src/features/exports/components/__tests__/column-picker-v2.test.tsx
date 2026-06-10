import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import type { ColumnGroup } from '../ColumnPicker';
import { ColumnPickerV2 } from '../ColumnPickerV2';

const GROUPS: ColumnGroup[] = [
  {
    id: 'identity',
    labelKey: 'x.identity',
    defaultLabel: 'Identyfikacja',
    columns: [
      { key: 'sku', labelKey: 'x.sku', defaultLabel: 'SKU' },
      { key: 'name.pl', labelKey: 'x.name_pl', defaultLabel: 'Nazwa [pl]' },
    ],
  },
  {
    id: 'seo',
    labelKey: 'x.seo',
    defaultLabel: 'SEO',
    columns: [{ key: 'meta_title', labelKey: 'x.meta', defaultLabel: 'Meta title' }],
  },
];

describe('ColumnPickerV2', () => {
  it('keeps the locked key first and not removable', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <ColumnPickerV2
        groups={GROUPS}
        value={['sku', 'meta_title']}
        onChange={onChange}
        lockedKey="sku"
      />,
    );
    expect(screen.getByText('klucz')).toBeInTheDocument();

    await user.click(screen.getByRole('checkbox', { name: /Nazwa \[pl\]/ }));
    expect(onChange).toHaveBeenCalledWith(['sku', 'meta_title', 'name.pl']);
  });

  it('group checkbox selects the whole group', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<ColumnPickerV2 groups={GROUPS} value={['sku']} onChange={onChange} lockedKey="sku" />);
    await user.click(screen.getByRole('checkbox', { name: /Zaznacz całą grupę Identyfikacja/ }));
    expect(onChange).toHaveBeenCalledWith(['sku', 'name.pl']);
  });

  it('search narrows the available list', async () => {
    const user = userEvent.setup();
    render(<ColumnPickerV2 groups={GROUPS} value={[]} onChange={() => {}} />);
    await user.type(screen.getByRole('searchbox'), 'meta');
    expect(screen.getByText('Meta title')).toBeInTheDocument();
    expect(screen.queryByText('Nazwa [pl]')).toBeNull();
  });

  it('clear keeps only the locked column', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <ColumnPickerV2
        groups={GROUPS}
        value={['sku', 'meta_title', 'name.pl']}
        onChange={onChange}
        lockedKey="sku"
      />,
    );
    await user.click(screen.getByRole('button', { name: 'Wyczyść' }));
    expect(onChange).toHaveBeenCalledWith(['sku']);
  });
});
