import {useSearchParams} from 'react-router-dom';
import {useTranslation} from '@/shared/i18n';
import {PageHeader, TabPanel, Tabs} from '@/shared/ui';
import {ChartOfAccounts} from '@/widgets/chart-of-accounts';
import {CompanySettings} from '@/widgets/company-settings';
import {PaymentMethodSettings} from '@/widgets/payment-method-settings';
import {PostingRules} from '@/widgets/posting-rules';
import {ResolutionSettings} from '@/widgets/resolution-settings';
import {TaxSettings} from '@/widgets/tax-settings';
import {UserAdmin} from '@/widgets/user-admin';

const TABS = [
  'company',
  'resolution',
  'users',
  'taxes',
  'paymentMethods',
  'chart',
  'postingRules',
] as const;
type Tab = (typeof TABS)[number];

const PANELS: Record<Tab, () => React.JSX.Element> = {
  company: CompanySettings,
  resolution: ResolutionSettings,
  users: UserAdmin,
  taxes: TaxSettings,
  paymentMethods: PaymentMethodSettings,
  chart: ChartOfAccounts,
  postingRules: PostingRules,
};

/**
 * Configuración: one tab per area, each a widget owned by one item of the split, so no two items edit this page.
 * The open tab is in the address (?tab=taxes), so a link can open it.
 */
export function SettingsPage() {
  const {t} = useTranslation();
  const [params, setParams] = useSearchParams();
  const asked = params.get('tab');
  const tab: Tab = TABS.includes(asked as Tab) ? (asked as Tab) : 'company';
  const Panel = PANELS[tab];

  return (
    <>
      <PageHeader title={t('settings.title')} />
      <Tabs
        id="settings"
        label={t('settings.title')}
        value={tab}
        options={TABS.map((value) => ({
          value,
          label: t(`settings.tabs.${value}`),
        }))}
        onChange={(value) => setParams({tab: value})}
      />
      <TabPanel id="settings" value={tab}>
        <Panel />
      </TabPanel>
    </>
  );
}
