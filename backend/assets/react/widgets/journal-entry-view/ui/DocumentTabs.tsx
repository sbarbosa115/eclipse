import {useState, type ReactNode} from 'react';
import {useTranslation} from '@/shared/i18n';
import {TabPanel, Tabs} from '@/shared/ui';
import type {EntrySourceType} from '../api/journalEntryApi';
import {JournalEntryView} from './JournalEntryView';

type View = 'document' | 'entries';

/**
 * Documento | Contabilización over a document page. The Documento tab is the page as it was; the entries are read
 * only when their tab is opened; a draft shows no tabs. `status` is part of the entries' key so a void shows its reversal at once.
 */
export function DocumentTabs({
  sourceType,
  sourceId,
  status,
  children,
}: {
  sourceType: EntrySourceType;
  sourceId?: string;
  status?: string;
  children: ReactNode;
}) {
  const {t} = useTranslation();
  const [view, setView] = useState<View>('document');
  // A draft (or a document not saved yet) has no entry: no tab, the page as it was. Emitted and voided ones do.
  if (!sourceId || !status || status === 'draft') return <>{children}</>;
  return (
    <>
      <Tabs
        id="document"
        label={t('journalEntry.tabsLabel')}
        value={view}
        options={[
          {value: 'document', label: t('journalEntry.document')},
          {value: 'entries', label: t('journalEntry.tab')},
        ]}
        onChange={setView}
      />
      <TabPanel id="document" value={view}>
        {view === 'document' ? (
          children
        ) : (
          <JournalEntryView
            key={status}
            sourceType={sourceType}
            sourceId={sourceId}
          />
        )}
      </TabPanel>
    </>
  );
}
