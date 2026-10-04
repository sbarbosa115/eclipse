import {
  createContext,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from 'react';

// The app's theme: light, dark, or the device's. Kept in this browser only (a per-viewer convenience).
export const THEME_CHOICES = ['system', 'light', 'dark'] as const;
export type ThemeChoice = (typeof THEME_CHOICES)[number];

const KEY = 'mustang.theme';

function stored(): ThemeChoice {
  try {
    const value = window.localStorage.getItem(KEY);
    return THEME_CHOICES.includes(value as ThemeChoice)
      ? (value as ThemeChoice)
      : 'system';
  } catch {
    return 'system';
  }
}

function deviceTheme(): 'light' | 'dark' {
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches
    ? 'dark'
    : 'light';
}

/** The chosen theme and the one in effect ("system" follows the device, also when it changes). */
function useThemeState() {
  const [choice, setChoice] = useState<ThemeChoice>(stored);
  const [device, setDevice] = useState(deviceTheme);

  useEffect(() => {
    const query = window.matchMedia?.('(prefers-color-scheme: dark)');
    if (!query) return undefined;
    const onChange = () => setDevice(deviceTheme());
    query.addEventListener('change', onChange);
    return () => query.removeEventListener('change', onChange);
  }, []);

  const choose = (next: ThemeChoice) => {
    setChoice(next);
    try {
      window.localStorage.setItem(KEY, next);
    } catch {
      // Private mode: the choice holds until the page is closed.
    }
  };

  return {choice, choose, theme: choice === 'system' ? device : choice};
}

type ThemeState = ReturnType<typeof useThemeState>;

const ThemeContext = createContext<ThemeState | null>(null);

/** The root of every admin page: the `.admin` scope of admin.css, in the chosen theme. */
export function AdminRoot({children}: {children: ReactNode}) {
  const state = useThemeState();
  return (
    <ThemeContext.Provider value={state}>
      <div className="admin" data-theme={state.theme}>
        {children}
      </div>
    </ThemeContext.Provider>
  );
}

export function useAdminTheme(): ThemeState {
  const value = useContext(ThemeContext);
  if (!value) throw new Error('useAdminTheme() needs an <AdminRoot>.');
  return value;
}
