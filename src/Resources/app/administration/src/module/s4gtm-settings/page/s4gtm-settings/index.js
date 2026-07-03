import template from './s4gtm-settings.html.twig';
import './s4gtm-settings.scss';

const { Component, Mixin } = Shopware;

const DOMAIN = 'Shop4GoogleTagManager.config';
const PREFIX = `${DOMAIN}.`;

Component.register('s4gtm-settings', {
    template,

    inject: ['systemConfigApiService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            salesChannelId: null,
            globalConfig: this.getDefaults(),
            channelConfig: {},

            smartBarTarget: false,

            showConsentWarningModal: false,
        };
    },

    computed: {
        hasParent() {
            return this.salesChannelId !== null;
        },

        editConfig() {
            return this.hasParent ? this.channelConfig : this.globalConfig;
        },

        effectiveContainerId() {
            const own = this.channelConfig.containerId;
            if (this.hasParent && (own === undefined || own === null || own === '')) {
                return this.globalConfig.containerId;
            }
            return this.editConfig.containerId;
        },

        effective() {
            return (key) => {
                const own = this.channelConfig[key];
                if (this.hasParent && (own === undefined || own === null)) {
                    return this.globalConfig[key];
                }
                return this.editConfig[key];
            };
        },

        consentSourceOptions() {
            return [
                { value: 'shopware', label: this.$tc('s4gtm-settings.card.consent.consentSourceShopware') },
                { value: 'cmp', label: this.$tc('s4gtm-settings.card.consent.consentSourceCmp') },
                { value: 'none', label: this.$tc('s4gtm-settings.card.consent.consentSourceNone') },
            ];
        },

        tagPositionOptions() {
            return [
                { value: 'head', label: this.$tc('s4gtm-settings.card.base.tagPositionHead') },
                { value: 'body', label: this.$tc('s4gtm-settings.card.base.tagPositionBody') },
            ];
        },

        enhancedConversionsOptions() {
            return [
                { value: 'off', label: this.$tc('s4gtm-settings.card.advanced.enhancedConversionsOff') },
                { value: 'email', label: this.$tc('s4gtm-settings.card.advanced.enhancedConversionsEmail') },
                { value: 'full', label: this.$tc('s4gtm-settings.card.advanced.enhancedConversionsFull') },
            ];
        },

        consentUnmanaged() {
            return this.effective('consentSource') === 'none';
        },

        advancedConsentActive() {
            return this.effective('consentSource') !== 'none'
                && this.effective('consentMode') === true
                && this.effective('advancedConsentMode') === true;
        },

        externalCmpBridgeActive() {
            return this.effective('consentSource') === 'cmp';
        },

        enhancedConversionsActive() {
            return this.effective('enhancedConversions') !== 'off';
        },

        // inline-fehler, falls die container-id nicht dem gtm-format entspricht
        containerIdError() {
            const value = this.effectiveContainerId;
            if (!value || /^GTM-[A-Z0-9]{1,20}$/.test(value)) {
                return null;
            }

            return { detail: this.$tc('s4gtm-settings.card.base.containerIdInvalid') };
        },
    },

    created() {
        this.loadConfig();
    },

    mounted() {
        this.smartBarTarget = true;
    },

    methods: {
        getDefaults() {
            return {
                active: true,
                containerId: '',
                debug: false,
                tagPosition: 'head',
                consentSource: 'shopware',
                consentMode: true,
                advancedConsentMode: false,
                consentWaitForUpdate: 500,
                dataLayerEnabled: true,
                enhancedEcommerce: true,
                checkoutTracking: true,
                remarketing: true,
                userIdTracking: false,
                customerTracking: true,
                enhancedConversions: 'off',
                eagerCheckoutLoad: false,
                trackContactForm: false,
                trackNewsletter: true,
                trackCustomForms: false,
                anonymizeSearchTerm: true,
            };
        },

        async loadConfig() {
            this.isLoading = true;
            try {
                const global = await this.systemConfigApiService.getValues(DOMAIN, null);
                this.globalConfig = { ...this.getDefaults(), ...this.stripPrefix(global) };

                this.channelConfig = this.hasParent
                    ? this.stripPrefix(await this.systemConfigApiService.getValues(DOMAIN, this.salesChannelId))
                    : {};
            } finally {
                this.isLoading = false;
            }
        },

        stripPrefix(values) {
            const out = {};
            Object.keys(values).forEach((fullKey) => {
                out[fullKey.replace(PREFIX, '')] = values[fullKey];
            });
            return out;
        },

        onSalesChannelChanged(salesChannelId) {
            this.salesChannelId = salesChannelId;
            this.loadConfig();
        },

        onSave() {
            if (this.consentUnmanaged) {
                this.showConsentWarningModal = true;
                return;
            }
            this.performSave();
        },

        onConfirmSaveWithoutConsent() {
            this.showConsentWarningModal = false;
            this.performSave();
        },

        onCancelConsentWarning() {
            this.showConsentWarningModal = false;
        },

        onConsentModalChange(isOpen) {
            this.showConsentWarningModal = isOpen;
        },

        performSave() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            const source = this.editConfig;
            const payload = {};
            Object.keys(this.getDefaults()).forEach((key) => {
                const value = source[key];
                payload[PREFIX + key] = (this.hasParent && value === undefined) ? null : value;
            });

            return this.systemConfigApiService
                .saveValues(payload, this.salesChannelId)
                .then(() => {
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        message: this.$tc('s4gtm-settings.general.saveSuccess'),
                    });
                    return this.loadConfig();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('s4gtm-settings.general.saveError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onSaveFinish() {
            this.isSaveSuccessful = false;
        },
    },
});
