import {useState} from 'react';
import {Link} from 'react-router-dom';
import {useTranslation, type Translate} from '@/shared/i18n';
import {formatMoney} from '@/shared/lib';
import {
  Alert,
  Badge,
  ErrorState,
  FilterBar,
  Loading,
  MoneyInput,
  TabIntro,
  TabPanel,
  Tabs,
} from '@/shared/ui';
import {ReportTable, type ReportColumn} from '@/widgets/report-table';
import {
  BY_CONCEPT,
  EXOGENA_FORMATS,
  exogenaParams,
  exogenaPath,
  fetchExogena,
  type ExogenaFormat,
  type ExogenaRow,
} from '../api/exogenaApi';
import {useLoaded} from '../lib/useLoaded';
import {useExogenaFilters} from '../model/useExogenaFilters';
import {TaxExports} from './TaxExports';
import './exogena.css';

/**
 * Exógena (observations PRD §4.7 d): the working papers of formats 1001 to 1009 for a taxable year, one sub-tab per
 * format, per tercero with its DIAN identification (and account and concept in 1001 and 1007). Under a threshold the
 * terceros are grouped as cuantías menores (222222222). The files are withheld while a tercero has no municipio.
 */
export function ExogenaView() {
  const {t} = useTranslation();
  const {format, year, threshold, filters, setFormat, setThreshold} =
    useExogenaFilters();
  const filtersNow = {year, threshold};
  const {data, failed, busy, retry} = useLoaded(
    `exogena|${format}|${year}|${threshold}`,
    () => fetchExogena(format, filtersNow),
  );
  const paper = data?.format === format ? data : null;

  return (
    <>
      <TabIntro>{t('taxReports.exogena.intro')}</TabIntro>
      <Tabs<ExogenaFormat>
        id="exogena-formats"
        sub
        label={t('taxReports.exogena.formatsLabel')}
        value={format}
        options={EXOGENA_FORMATS.map((value) => ({
          value,
          label: t(`taxReports.exogena.formats.${value}.tab`),
        }))}
        onChange={setFormat}
      />
      <TabPanel id="exogena-formats" value={format}>
        <p className="exogena-format-title">
          {t(`taxReports.exogena.formats.${format}.title`)}
        </p>
        <FilterBar filters={filters}>
          <Threshold
            key={threshold}
            value={threshold}
            onCommit={setThreshold}
          />
        </FilterBar>
        {failed ? (
          <ErrorState message={t('common.loadFailed')} onRetry={retry} />
        ) : !paper ? (
          <Loading />
        ) : (
          <>
            {paper.missing_municipality > 0 ? (
              <Alert kind="warning">
                {t('taxReports.exogena.missingMunicipality', {
                  count: paper.missing_municipality,
                })}{' '}
                <Link to="/terceros?missing_municipality=1">
                  {t('taxReports.exogena.missingMunicipalityLink')}
                </Link>
              </Alert>
            ) : (
              <TaxExports
                name={t('taxReports.exogena.fileName', {format})}
                path={exogenaPath(format)}
                params={exogenaParams(filtersNow)}
              />
            )}
            {paper.missing_concepts > 0 && (
              <Alert kind="info">
                {paper.missing_concepts === 1
                  ? t('taxReports.exogena.missingConcepts.one')
                  : t('taxReports.exogena.missingConcepts.other', {
                      count: paper.missing_concepts,
                    })}{' '}
                <Link to="/configuracion?tab=exogena">
                  {t('taxReports.exogena.missingConceptsLink')}
                </Link>
              </Alert>
            )}
            <ReportTable<ExogenaRow>
              busy={busy}
              rows={[...paper.rows, ...paper.minor_amounts_rows]}
              rowKey={(r) =>
                `${r.tercero_id ?? r.identification_number}|${r.account_code ?? ''}|${r.concept_code ?? ''}`
              }
              empty={t('taxReports.exogena.empty', {format, year})}
              totalLabel={t('taxReports.exogena.total')}
              columns={columnsOf(
                t,
                BY_CONCEPT.has(format),
                paper.value_columns,
                paper.totals,
              )}
            />
          </>
        )}
      </TabPanel>
    </>
  );
}

/** The cuantías menores threshold: typed freely, applied when the field is left or Enter is pressed. */
function Threshold({
  value,
  onCommit,
}: {
  value: string;
  onCommit: (value: string) => boolean;
}) {
  const {t} = useTranslation();
  const [draft, setDraft] = useState(value);
  const [invalid, setInvalid] = useState(false);
  const commit = () => setInvalid(!onCommit(draft));

  return (
    <label className="filter-select exogena-threshold">
      <span className="filter-select-label">
        {t('taxReports.exogena.threshold')}
      </span>
      <MoneyInput
        value={draft}
        onChange={setDraft}
        onBlur={commit}
        onKeyDown={(event) => {
          if (event.key === 'Enter') commit();
        }}
        placeholder={t('taxReports.exogena.thresholdPlaceholder')}
        aria-invalid={invalid || undefined}
        title={invalid ? t('taxReports.exogena.thresholdInvalid') : undefined}
      />
    </label>
  );
}

function columnsOf(
  t: Translate,
  byConcept: boolean,
  valueColumns: string[],
  totals: Record<string, string>,
): ReportColumn<ExogenaRow>[] {
  const name = (r: ExogenaRow) =>
    r.business_name ??
    [r.last_names, r.first_names].filter(Boolean).join(' ');
  const identification = (r: ExogenaRow) => {
    if (r.tercero_id === null) return r.identification_number;
    const type = t(`taxReports.exogena.documentTypes.${r.document_type_code}`);
    const number = r.check_digit
      ? `${r.identification_number}-${r.check_digit}`
      : r.identification_number;
    return `${type} ${number}`;
  };
  const municipality = (r: ExogenaRow) =>
    r.municipality_code ??
    (r.tercero_id === null ? (
      ''
    ) : r.country_code !== 'CO' ? (
      r.country_code
    ) : (
      <Badge value="invalid">{t('taxReports.exogena.noMunicipality')}</Badge>
    ));

  return [
    ...(byConcept
      ? [
          {
            header: t('taxReports.exogena.columns.concept'),
            cell: (r: ExogenaRow) => r.concept_code ?? '',
          },
          {
            header: t('taxReports.exogena.columns.account'),
            cell: (r: ExogenaRow) => r.account_code ?? '',
          },
        ]
      : []),
    {header: t('taxReports.exogena.columns.identification'), cell: identification},
    {header: t('taxReports.exogena.columns.name'), cell: name},
    {header: t('taxReports.exogena.columns.municipality'), cell: municipality},
    ...valueColumns.map((key) => ({
      header: t(`taxReports.exogena.values.${key}`),
      numeric: true,
      cell: (r: ExogenaRow) => formatMoney(r.values[key] ?? '0'),
      total: formatMoney(totals[key] ?? '0'),
    })),
  ];
}
