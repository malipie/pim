import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { type PillTabItem, PillTabs } from '../pill-tabs';

const ITEMS: PillTabItem[] = [
  { id: 'sessions', label: 'Sesje', count: 12 },
  { id: 'profiles', label: 'Profile Eksportu', count: 3 },
  { id: 'targets', label: 'Cele', disabled: true },
];

describe('PillTabs', () => {
  it('marks the active tab and shows counts', () => {
    render(<PillTabs items={ITEMS} activeId="sessions" onChange={() => {}} ariaLabel="Eksporty" />);
    const active = screen.getByRole('tab', { name: /Sesje/ });
    expect(active).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByText('12')).toBeInTheDocument();
  });

  it('fires onChange for enabled tabs only', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<PillTabs items={ITEMS} activeId="sessions" onChange={onChange} />);
    await user.click(screen.getByRole('tab', { name: /Profile Eksportu/ }));
    expect(onChange).toHaveBeenCalledWith('profiles');
    await user.click(screen.getByRole('tab', { name: /Cele/ }));
    expect(onChange).toHaveBeenCalledTimes(1);
  });

  it('renders the soon badge on disabled tabs', () => {
    render(<PillTabs items={ITEMS} activeId="sessions" onChange={() => {}} />);
    const disabled = screen.getByRole('tab', { name: /Cele/ });
    expect(disabled).toHaveAttribute('aria-disabled', 'true');
    expect(disabled).toHaveTextContent('wkrótce');
  });
});
