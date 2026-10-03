import {Suspense, useEffect, useState} from 'react';
import {Navigate, NavLink, Outlet, useLocation} from 'react-router-dom';
import {useSession, type Role, type Session} from '@/entities/session';
import {useTranslation} from '@/shared/i18n';
import {formatNit} from '@/shared/lib';
import {
  ErrorState,
  FullPageLoading,
  Icon,
  Loading,
  THEME_CHOICES,
  useTheme,
  type ThemeChoice,
} from '@/shared/ui';

interface NavItem {
  to: string;
  label: string;
  icon: string;
  end?: boolean;
  /** Who sees it; everyone when absent. */
  roles?: Role[];
}

interface NavSection {
  title: string;
  items: NavItem[];
}

/** The sections of stage 1 (docs/pdr/prd-accounting.md §4), in the order a company uses them. */
const MENU: NavSection[] = [
  {
    title: 'shell.sections.start',
    items: [{to: '/', label: 'shell.nav.dashboard', icon: 'layers', end: true}],
  },
  {
    title: 'shell.sections.sales',
    items: [
      {to: '/cotizaciones', label: 'shell.nav.quotations', icon: 'fileText'},
      {to: '/facturas-venta', label: 'shell.nav.salesInvoices', icon: 'tag'},
      {to: '/recibos-caja', label: 'shell.nav.cashReceipts', icon: 'download'},
    ],
  },
  {
    title: 'shell.sections.purchases',
    items: [
      {
        to: '/facturas-compra',
        label: 'shell.nav.purchaseInvoices',
        icon: 'shopping-bag',
      },
      {
        to: '/recibos-pago',
        label: 'shell.nav.supplierPayments',
        icon: 'external',
      },
    ],
  },
  {
    title: 'shell.sections.books',
    items: [
      {to: '/contabilidad', label: 'shell.nav.ledger', icon: 'calendar'},
      {to: '/reportes', label: 'shell.nav.reports', icon: 'sliders'},
    ],
  },
  {
    title: 'shell.sections.masters',
    items: [
      {to: '/terceros', label: 'shell.nav.terceros', icon: 'users'},
      {to: '/productos', label: 'shell.nav.products', icon: 'package'},
    ],
  },
  {
    title: 'shell.sections.settings',
    items: [
      {
        to: '/configuracion',
        label: 'shell.nav.settings',
        icon: 'store',
        roles: ['owner', 'accountant'],
      },
    ],
  },
];

function menuFor(session: Session): NavSection[] {
  return MENU.map((section) => ({
    ...section,
    items: section.items.filter(
      (item) => !item.roles || item.roles.includes(session.role as Role),
    ),
  })).filter((section) => section.items.length > 0);
}

/**
 * Every signed-in page: a sidebar with the company's sections (a drawer on phones and tablets). Nobody signed in goes
 * to the sign-in page, which comes back here afterwards.
 */
export function AppShell() {
  const {status, session, reload} = useSession();
  const {t} = useTranslation();
  const location = useLocation();

  if (status === 'loading') return <FullPageLoading />;
  if (status === 'failed') {
    return (
      <div className="auth-page">
        <ErrorState message={t('common.loadFailed')} onRetry={reload} />
      </div>
    );
  }
  if (status === 'signed-out' || !session) {
    // The whole address, query included (?tab=taxes), so signing in comes back to exactly where the link pointed.
    return (
      <Navigate
        to="/ingresar"
        replace
        state={{from: location.pathname + location.search}}
      />
    );
  }
  return <SignedInShell session={session} />;
}

function SignedInShell({session}: {session: Session}) {
  const {t} = useTranslation();
  const {signOut} = useSession();
  const location = useLocation();
  const [open, setOpen] = useState(false);

  // Close the drawer after navigating (adjusted while rendering, not in an effect), and on Escape.
  const [openedOn, setOpenedOn] = useState(location.pathname);
  if (openedOn !== location.pathname) {
    setOpenedOn(location.pathname);
    setOpen(false);
  }
  useEffect(() => {
    if (!open) return undefined;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false);
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open]);

  return (
    <div className={`shell${open ? ' sidebar-open' : ''}`}>
      <header className="mobile-bar">
        <button
          type="button"
          className="icon-btn"
          aria-label={t('common.menu')}
          aria-expanded={open}
          aria-controls="app-sidebar"
          onClick={() => setOpen(true)}
        >
          <Icon name="menu" size={22} />
        </button>
        <Brand />
      </header>

      <aside id="app-sidebar" className="sidebar">
        <div className="sidebar-header">
          <Brand />
          <button
            type="button"
            className="icon-btn sidebar-close"
            aria-label={t('common.closeMenu')}
            onClick={() => setOpen(false)}
          >
            <Icon name="close" size={20} />
          </button>
        </div>

        <div className="sidebar-company">
          <span className="sidebar-user-name">{session.company_name}</span>
          <span className="sidebar-user-role">
            NIT {formatNit(session.company_nit, session.company_check_digit)}
          </span>
        </div>

        <nav className="sidebar-nav" aria-label={t('shell.navLabel')}>
          {menuFor(session).map((section) => (
            <div key={section.title} className="nav-section">
              <span className="nav-section-title">{t(section.title)}</span>
              {section.items.map((item) => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  end={item.end}
                  className={({isActive}) =>
                    `nav-link${isActive ? ' active' : ''}`
                  }
                >
                  <Icon name={item.icon} size={18} />
                  <span>{t(item.label)}</span>
                </NavLink>
              ))}
            </div>
          ))}
        </nav>

        <div className="sidebar-footer">
          <div className="sidebar-user">
            <span className="sidebar-user-name">{session.name}</span>
            <span className="sidebar-user-role">
              {t(`shell.roles.${session.role}`)}
            </span>
          </div>
          <ThemePicker />
          <button
            type="button"
            className="btn btn-ghost btn-sm btn-block"
            onClick={() => void signOut()}
          >
            <Icon name="logout" size={16} />
            {t('common.signOut')}
          </button>
        </div>
      </aside>

      <div
        className="sidebar-backdrop"
        aria-hidden="true"
        onClick={() => setOpen(false)}
      />

      <main className="content">
        {/* Pages are lazy chunks: the menu stays while one loads. */}
        <Suspense fallback={<Loading />}>
          <Outlet />
        </Suspense>
      </main>
    </div>
  );
}

function Brand() {
  const {t} = useTranslation();
  return (
    <div className="brand">
      <span className="brand-name">{t('auth.brand.name')}</span>
      <span className="brand-sub">{t('auth.brand.sub')}</span>
    </div>
  );
}

function ThemePicker() {
  const {t} = useTranslation();
  const {choice, choose} = useTheme();
  return (
    <label className="theme-picker">
      <span className="nav-section-title">{t('shell.theme.label')}</span>
      <select
        value={choice}
        onChange={(e) => choose(e.target.value as ThemeChoice)}
      >
        {THEME_CHOICES.map((option) => (
          <option key={option} value={option}>
            {t(`shell.theme.${option}`)}
          </option>
        ))}
      </select>
    </label>
  );
}
