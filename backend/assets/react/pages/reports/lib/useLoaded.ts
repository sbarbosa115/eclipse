import {useEffect, useRef, useState} from 'react';

/**
 * Loads a report whenever `key` changes (its query string), keeping the last answer on screen while the next one
 * loads (`busy`), and tells a failed load from a slow one.
 */
export function useLoaded<T>(key: string, load: () => Promise<T>) {
  const [state, setState] = useState<
    {key: string; data: T} | {key: string; failed: true} | null
  >(null);
  const [attempt, setAttempt] = useState(0);
  const latest = useRef(load);
  useEffect(() => {
    latest.current = load;
  });

  useEffect(() => {
    let cancelled = false;
    latest
      .current()
      .then((data) => !cancelled && setState({key, data}))
      .catch(() => !cancelled && setState({key, failed: true}));
    return () => {
      cancelled = true;
    };
  }, [key, attempt]);

  const answered = state !== null && state.key === key;
  return {
    data: state !== null && 'data' in state ? state.data : null,
    failed: answered && 'failed' in state,
    busy: !answered,
    retry: () => setAttempt((n) => n + 1),
  };
}
