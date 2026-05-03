/**
 * VIEW-04 (#408) — emoji set for the category Wizualizacja section
 * (Create + Edit pages, plus the tree node fallback).
 *
 * Picked to cover the demo dataset (medical specialties + hairdressing
 * services from the prototype) plus generic retail/asset categories.
 * The list intentionally stays short: the IconPicker renders a 4×N grid
 * and operators are expected to use the existing icon if their category
 * matches; custom imports remain free-text.
 */
export const CATEGORY_ICONS = [
  '📁',
  '📂',
  '🩺',
  '💉',
  '🪒',
  '💆',
  '🛒',
  '🍔',
  '🎓',
  '🏗️',
  '🚗',
  '🎨',
  '📡',
  '🛍️',
] as const;

export type CategoryIcon = (typeof CATEGORY_ICONS)[number];
