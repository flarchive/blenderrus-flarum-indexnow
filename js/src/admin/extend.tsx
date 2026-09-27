import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import Button from 'flarum/common/components/Button';
import type ExtensionPage from 'flarum/admin/components/ExtensionPage';

const PREFIX = 'blenderrus-indexnow.';

const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`blenderrus-indexnow.admin.settings.${key}`, params);

/** Endpoint URL => translation key of its label. */
const ENDPOINTS: Record<string, string> = {
  'https://api.indexnow.org/indexnow': 'indexnow',
  'https://www.bing.com/indexnow': 'bing',
  'https://yandex.com/indexnow': 'yandex',
  'https://search.seznam.cz/indexnow': 'seznam',
  'https://searchadvisor.naver.com/indexnow': 'naver',
  'https://indexnow.yep.com/indexnow': 'yep',
  'https://internetarchive.indexnow.org/indexnow': 'internetarchive',
  'https://indexnow.amazonbot.amazon/indexnow': 'amazonbot',
  custom: 'custom',
};

function randomKey(): string {
  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);

  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

interface LastResult {
  time: string;
  endpoint: string;
  status: number | null;
  count: number;
  error: string | null;
}

function lastResult() {
  let result: LastResult | null = null;
  try {
    result = JSON.parse(app.data.settings[PREFIX + 'last_result'] || 'null');
  } catch (e) {
    // an unreadable value is shown as "nothing submitted yet"
  }

  return (
    <div className="Form-group IndexNowSettings-lastResult">
      <label>{t('last_result_heading')}</label>
      {result ? (
        <div className="helpText">
          {t('last_result_status', {
            time: dayjs(result.time).format('LLL'),
            count: result.count,
            endpoint: result.endpoint,
            status: result.status ?? '—',
          })}
          {result.status === 200 && <> ({t('last_result_status_accepted')})</>}
          {result.status === 202 && <> ({t('last_result_status_pending')})</>}
          {result.error && <div className="IndexNowSettings-error">{t('last_result_error', { error: result.error })}</div>}
        </div>
      ) : (
        <div className="helpText">{t('last_result_none')}</div>
      )}
    </div>
  );
}

export default [
  new Extend.Admin()
    .customSetting(() => <p className="IndexNowSettings-intro">{t('intro')}</p>, 110)
    .customSetting(function (this: ExtensionPage) {
      const key = this.setting(PREFIX + 'key');

      return (
        <div className="Form-group IndexNowSettings-key">
          <label>{t('key_label')}</label>
          <div className="helpText">{t('key_help', { url: `${app.forum.attribute('baseUrl')}/${key() || '…'}.txt` })}</div>
          <div className="IndexNowSettings-keyInput">
            <input className="FormControl" bidi={key} spellcheck={false} autocomplete="off" />
            <Button className="Button" icon="fas fa-dice" onclick={() => key(randomKey())}>
              {t('key_generate')}
            </Button>
          </div>
        </div>
      );
    }, 100)
    .setting(
      () => ({
        setting: PREFIX + 'endpoint',
        type: 'select',
        label: t('endpoint_label'),
        help: t('endpoint_help'),
        options: Object.fromEntries(Object.entries(ENDPOINTS).map(([url, id]) => [url, t(`endpoint_options.${id}`)])),
        default: 'https://api.indexnow.org/indexnow',
      }),
      90
    )
    .customSetting(function (this: ExtensionPage) {
      if (this.setting(PREFIX + 'endpoint')() !== 'custom') {
        return null;
      }

      return this.buildSettingComponent({
        setting: PREFIX + 'custom_endpoint',
        type: 'url',
        label: t('custom_endpoint_label'),
        help: t('custom_endpoint_help'),
        placeholder: 'https://example.com/indexnow',
      });
    }, 85)
    .customSetting(() => <h3 className="IndexNowSettings-heading">{t('events_heading')}</h3>, 80)
    .setting(() => ({ setting: PREFIX + 'submit_discussions', type: 'boolean', label: t('submit_discussions_label') }), 75)
    .setting(() => ({ setting: PREFIX + 'submit_replies', type: 'boolean', label: t('submit_replies_label') }), 74)
    .setting(() => ({ setting: PREFIX + 'submit_edits', type: 'boolean', label: t('submit_edits_label') }), 73)
    .setting(() => ({ setting: PREFIX + 'submit_removals', type: 'boolean', label: t('submit_removals_label') }), 72)
    .customSetting(() => <p className="helpText">{t('queue_help')}</p>, 70)
    .customSetting(lastResult, 60),
];
