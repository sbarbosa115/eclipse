import {useEffect, useRef, useState} from 'react';

/**
 * Loads a report whenever `key` changes (its query string), keeping the last answer while the next one loads.
 */
export function useLoaded<T>(key: string, load: () => Promise<T>) {
  const [data, setData] = useState<T | null>(null);
  const [failed, setFailed] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const latest = useRef(load);
  useEffect(() => {
    latest.current = load;
  });

  useEffect(() => {
    let cancelled = false;
    latest
      .current()
      .then((answer) => {
        if (cancelled) return;
        setData(answer);
        setFailed(false);
      })
      .catch(() => {
        if (!cancelled) setFailed(true);
      });
    return () => {
      cancelled = true;
    };
  }, [key, attempt]);

  return {data, failed, retry: () => setAttempt((n) => n + 1)};
}
