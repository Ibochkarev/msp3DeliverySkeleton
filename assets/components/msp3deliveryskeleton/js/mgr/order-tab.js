import { defineComponent, ref, watch } from 'vue'

function getCfg() {
  return window.msp3DeliverySkeletonConfig || {}
}

function t(key, fallback) {
  const value = (getCfg().lexicon || {})[key]
  return typeof value === 'string' && value.trim() !== '' ? value : fallback
}

function request(suffix, method) {
  const cfg = getCfg()
  const route = (cfg.routePrefix || '') + suffix
  const url =
    cfg.connectorUrl +
    '?action=' +
    encodeURIComponent('MiniShop3\\Processors\\Api\\Router') +
    '&route=' +
    encodeURIComponent(route)
  const headers = { Accept: 'application/json' }
  if (window.MODx && window.MODx.siteId) {
    headers.modAuth = window.MODx.siteId
  }
  return fetch(url, {
    method,
    credentials: 'same-origin',
    headers,
  }).then(function (res) {
    return res.json()
  })
}

const SkeletonShipmentTab = defineComponent({
  name: 'SkeletonShipmentTab',
  props: {
    orderId: { type: [Number, String], default: 0 },
    order: { type: Object, default: null },
    isCreateMode: { type: Boolean, default: false },
  },
  setup() {
    const loading = ref(false)
    const error = ref('')
    const shipment = ref(null)

    function apply(json) {
      if (!json || json.success === false) {
        error.value = (json && json.message) || t('err_generic', 'Request failed')
        return
      }
      shipment.value = json.data && Object.keys(json.data).length ? json.data : null
    }

    function load() {
      loading.value = true
      error.value = ''
      request('', 'GET')
        .then(apply)
        .catch(function () {
          error.value = t('err_generic', 'Request failed')
        })
        .finally(function () {
          loading.value = false
        })
    }

    function run(suffix) {
      loading.value = true
      error.value = ''
      request(suffix, 'POST')
        .then(apply)
        .catch(function () {
          error.value = t('err_generic', 'Request failed')
        })
        .finally(function () {
          loading.value = false
        })
    }

    watch(
      () => getCfg().routePrefix,
      function (prefix) {
        if (prefix) {
          load()
        }
      },
      { immediate: true }
    )

    return { loading, error, shipment, load, run, t }
  },
  template: `
    <section class="msp3-skeleton-tab">
      <p v-if="loading" class="msp3-skeleton-tab__status">{{ t('loading', 'Loading…') }}</p>
      <p v-else-if="error" class="msp3-skeleton-tab__error">{{ error }}</p>
      <p v-else-if="!shipment" class="msp3-skeleton-tab__empty">{{ t('empty', 'No shipment yet.') }}</p>
      <dl v-else class="msp3-skeleton-tab__list">
        <div class="msp3-skeleton-tab__row">
          <dt class="msp3-skeleton-tab__label">{{ t('provider', 'Provider') }}</dt>
          <dd class="msp3-skeleton-tab__value">Delivery Skeleton</dd>
        </div>
        <div class="msp3-skeleton-tab__row">
          <dt class="msp3-skeleton-tab__label">{{ t('external_id', 'External ID') }}</dt>
          <dd class="msp3-skeleton-tab__value">{{ shipment.external_id || '—' }}</dd>
        </div>
        <div class="msp3-skeleton-tab__row">
          <dt class="msp3-skeleton-tab__label">{{ t('tracking', 'Tracking') }}</dt>
          <dd class="msp3-skeleton-tab__value">{{ shipment.tracking_number || '—' }}</dd>
        </div>
        <div class="msp3-skeleton-tab__row">
          <dt class="msp3-skeleton-tab__label">{{ t('status', 'Status') }}</dt>
          <dd class="msp3-skeleton-tab__value">{{ shipment.status || '—' }}</dd>
        </div>
        <div class="msp3-skeleton-tab__row">
          <dt class="msp3-skeleton-tab__label">{{ t('label', 'Label') }}</dt>
          <dd class="msp3-skeleton-tab__value">
            <a v-if="shipment.label_url" class="msp3-skeleton-tab__link" :href="shipment.label_url" target="_blank" rel="noopener">{{ shipment.label_url }}</a>
            <span v-else>—</span>
          </dd>
        </div>
      </dl>
      <div class="msp3-skeleton-tab__actions">
        <button type="button" class="msp3-skeleton-tab__btn msp3-skeleton-tab__btn--primary" :disabled="loading" @click="run('/create')">{{ t('create', 'Create') }}</button>
        <button type="button" class="msp3-skeleton-tab__btn" :disabled="loading || !shipment" @click="run('/sync')">{{ t('sync', 'Sync') }}</button>
        <button type="button" class="msp3-skeleton-tab__btn msp3-skeleton-tab__btn--danger" :disabled="loading || !shipment" @click="run('/cancel')">{{ t('cancel', 'Cancel') }}</button>
      </div>
    </section>
  `,
})

if (window.MS3OrderTabsRegistry && typeof window.MS3OrderTabsRegistry.register === 'function') {
  window.MS3OrderTabsRegistry.register({
    key: 'msp3deliveryskeleton',
    title: t('tab_title', 'Carrier shipment'),
    type: 'vue',
    component: SkeletonShipmentTab,
    position: 10,
    hideOnCreate: true,
  })
} else {
  window.msp3DeliverySkeletonPendingTab = SkeletonShipmentTab
}

export default SkeletonShipmentTab
