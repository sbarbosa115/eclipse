import {describe, expect, it} from 'vitest';
import {sectionOf} from './AppShell';

describe('the menu entry a path belongs to', () => {
  it('is the longest entry the path starts with', () => {
    expect(sectionOf('/reportes/impuestos')?.label).toBe(
      'shell.nav.taxReports',
    );
    expect(sectionOf('/reportes/impuestos/exogena')?.label).toBe(
      'shell.nav.taxReports',
    );
    expect(sectionOf('/reportes/clientes')?.label).toBe('shell.nav.reports');
  });

  it('knows the órdenes de compra and keeps the dashboard to its own path', () => {
    expect(sectionOf('/ordenes-compra/nueva')?.label).toBe(
      'shell.nav.purchaseOrders',
    );
    expect(sectionOf('/')?.label).toBe('shell.nav.dashboard');
    expect(sectionOf('/otra')).toBeUndefined();
  });
});
