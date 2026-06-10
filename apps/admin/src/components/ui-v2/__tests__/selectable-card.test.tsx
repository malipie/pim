import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { SelectableCard, SelectableCardGroup } from '../selectable-card';

describe('SelectableCard', () => {
  it('shows the selected badge and aria-checked', () => {
    render(<SelectableCard title="Produkty" selected onSelect={() => {}} />);
    const radio = screen.getByRole('radio', { name: /Produkty/ });
    expect(radio).toHaveAttribute('aria-checked', 'true');
    expect(radio).toHaveTextContent('wybrane');
  });

  it('blocks selection and shows the soon badge when disabled', async () => {
    const user = userEvent.setup();
    const onSelect = vi.fn();
    render(<SelectableCard title="Google Sheets" disabled onSelect={onSelect} />);
    const radio = screen.getByRole('radio', { name: /Google Sheets/ });
    expect(radio).toHaveTextContent('wkrótce');
    await user.click(radio);
    expect(onSelect).not.toHaveBeenCalled();
  });
});

describe('SelectableCardGroup', () => {
  it('moves selection with arrow keys (radiogroup keyboard model)', async () => {
    const user = userEvent.setup();
    const onSelect = vi.fn();
    render(
      <SelectableCardGroup ariaLabel="Typ encji">
        <SelectableCard title="Produkty" selected onSelect={() => {}} />
        <SelectableCard title="Kategorie" onSelect={onSelect} />
      </SelectableCardGroup>,
    );
    screen.getByRole('radio', { name: /Produkty/ }).focus();
    await user.keyboard('{ArrowRight}');
    expect(onSelect).toHaveBeenCalled();
    expect(screen.getByRole('radio', { name: /Kategorie/ })).toHaveFocus();
  });

  it('skips disabled cards during arrow navigation', async () => {
    const user = userEvent.setup();
    const onSelectLast = vi.fn();
    render(
      <SelectableCardGroup ariaLabel="Format">
        <SelectableCard title="CSV" selected onSelect={() => {}} />
        <SelectableCard title="PDF" disabled onSelect={() => {}} />
        <SelectableCard title="XLSX" onSelect={onSelectLast} />
      </SelectableCardGroup>,
    );
    screen.getByRole('radio', { name: /CSV/ }).focus();
    await user.keyboard('{ArrowRight}');
    expect(onSelectLast).toHaveBeenCalled();
  });
});
