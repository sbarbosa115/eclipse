import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react';
import {ApiError, SIGNED_OUT_EVENT} from '@/shared/api';
import {
  fetchSession,
  signOut as endSession,
  type Session,
} from '../api/sessionApi';

type Status = 'loading' | 'signed-in' | 'signed-out' | 'failed';

export interface SessionState {
  status: Status;
  session: Session | null;
  /** After signing in or up: the server's answer becomes the session. */
  replace: (session: Session) => void;
  reload: () => void;
  signOut: () => Promise<void>;
}

const SessionContext = createContext<SessionState | null>(null);

/** Asks the server who is signed in, once, and keeps the answer for every page. */
export function SessionProvider({children}: {children: ReactNode}) {
  const [status, setStatus] = useState<Status>('loading');
  const [session, setSession] = useState<Session | null>(null);
  const [attempt, setAttempt] = useState(0);
  // Bumped whenever the session is set from outside (signing in): an older answer to the first "who is signed in?"
  // that arrives later must not undo it.
  const version = useRef(0);
  const signedIn = useRef(false);
  useEffect(() => {
    signedIn.current = status === 'signed-in';
  }, [status]);

  useEffect(() => {
    let cancelled = false;
    const asked = version.current;
    fetchSession()
      .then((found) => {
        if (cancelled || asked !== version.current) return;
        setSession(found);
        setStatus('signed-in');
      })
      .catch((error: unknown) => {
        if (cancelled || asked !== version.current) return;
        setSession(null);
        setStatus(
          error instanceof ApiError && error.status === 401
            ? 'signed-out'
            : 'failed',
        );
      });
    return () => {
      cancelled = true;
    };
  }, [attempt]);

  // Any call that finds the session over (shared/api): back to signed out, so the shell shows the sign-in page.
  useEffect(() => {
    const ended = () => {
      if (signedIn.current) {
        version.current += 1;
        setSession(null);
        setStatus('signed-out');
      }
    };
    window.addEventListener(SIGNED_OUT_EVENT, ended);
    return () => window.removeEventListener(SIGNED_OUT_EVENT, ended);
  }, []);

  const replace = useCallback((next: Session) => {
    version.current += 1;
    setSession(next);
    setStatus('signed-in');
  }, []);
  const reload = useCallback(() => {
    setStatus('loading');
    setAttempt((n) => n + 1);
  }, []);
  const signOut = useCallback(async () => {
    await endSession();
    version.current += 1;
    setSession(null);
    setStatus('signed-out');
  }, []);

  const value = useMemo(
    () => ({status, session, replace, reload, signOut}),
    [status, session, replace, reload, signOut],
  );
  return (
    <SessionContext.Provider value={value}>{children}</SessionContext.Provider>
  );
}

export function useSession(): SessionState {
  const value = useContext(SessionContext);
  if (!value) throw new Error('useSession() needs a <SessionProvider>.');
  return value;
}
