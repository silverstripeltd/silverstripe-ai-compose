/* eslint-env jest */
/* eslint-disable import/first */
jest.mock('components/Button/Button', () => {
  const React = jest.requireActual('react');

  return ({ children, className = '', color, icon, ...props }) => React.createElement(
    'button',
    {
      ...props,
      className: `btn ${color ? `btn-${color}` : ''} ${className}`.trim(),
    },
    icon ? React.createElement('span', { className: `btn__icon font-icon-${icon}`, 'aria-hidden': 'true' }) : null,
    children,
  );
}, { virtual: true });

import React from 'react';
import { render, screen } from '@testing-library/react';
import { AiComposeActionButton } from '../../src/components/AiComposeActionButton';

test('renders a secondary preview toolbar button with compose labelling', () => {
  const { container } = render(
    <AiComposeActionButton
      fqcn={'App\\Page'}
      recordId={9}
    />
  );

  const button = screen.getByRole('button', { name: 'Compose' });

  expect(button.className).toContain('ai-compose__action');
  expect(button.className).toContain('ai-compose-toolbar__button');
  expect(button.className).toContain('btn-secondary');
  expect(button.getAttribute('data-fqcn')).toBe('App\\Page');
  expect(button.getAttribute('data-record-id')).toBe('9');
  expect(button.getAttribute('title')).toBe('Compose');
  expect(container.querySelector('.font-icon-edit')).not.toBeNull();
});
