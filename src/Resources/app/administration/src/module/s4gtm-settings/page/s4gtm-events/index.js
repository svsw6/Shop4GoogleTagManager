import template from './s4gtm-events.html.twig';
import './s4gtm-events.scss';
import { CONTEXT_ORDER, STANDARD_EVENTS } from '../../constant/event-catalog';

const { Component, Mixin, Data } = Shopware;
const { Criteria, EntityCollection } = Data;

const STD_PREFIX = 'Shop4GoogleTagManager.config.std.';
const STD_OVERRIDE_PREFIX = 'Shop4GoogleTagManager.config.stdOverride.';

Component.register('s4gtm-events', {
    template,

    inject: ['systemConfigApiService', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            standardEvents: {},
            standardOverrides: {},
            customEvents: null,
            activeContext: 'all',
            editModel: null,
            editKind: 'custom',
        };
    },

    computed: {
        gtmEventRepository() {
            return this.repositoryFactory.create('s4gtm_event');
        },

        contextTabs() {
            return ['all', ...CONTEXT_ORDER];
        },

        contextTabItems() {
            return this.contextTabs.map((context) => ({
                name: context,
                label: context === 'all'
                    ? this.$tc('s4gtm-settings.events.tabs.all')
                    : this.$tc('s4gtm-settings.context.' + context),
            }));
        },

        eventRows() {
            const rows = [];

            Object.keys(STANDARD_EVENTS).forEach((contextKey) => {
                STANDARD_EVENTS[contextKey].forEach((event) => {
                    rows.push({
                        id: 'std-' + event,
                        kind: 'standard',
                        event,
                        configKey: STD_PREFIX + event,
                        name: event,
                        eventContext: contextKey,
                        ga4Event: this.standardOverrides[event]?.ga4Event || event,
                        active: this.standardEvents[STD_PREFIX + event] !== false,
                    });
                });
            });

            if (this.customEvents) {
                this.customEvents.forEach((entity) => {
                    rows.push({
                        id: entity.id,
                        kind: 'custom',
                        entity,
                        name: entity.technicalName,
                        eventContext: entity.eventContext,
                        ga4Event: entity.ga4Event,
                        active: entity.active,
                    });
                });
            }

            return rows.sort((a, b) => {
                const ctx = CONTEXT_ORDER.indexOf(a.eventContext) - CONTEXT_ORDER.indexOf(b.eventContext);
                if (ctx !== 0) {
                    return ctx;
                }
                if (a.kind !== b.kind) {
                    return a.kind === 'standard' ? -1 : 1;
                }
                return a.name.localeCompare(b.name);
            });
        },

        filteredRows() {
            let rows = this.eventRows;

            if (this.activeContext !== 'all') {
                rows = rows.filter((row) => row.eventContext === this.activeContext);
            }

            return rows;
        },

        eventColumns() {
            return [
                { property: 'name', label: this.$tc('s4gtm-settings.events.grid.name') },
                { property: 'kind', label: this.$tc('s4gtm-settings.events.grid.type') },
                { property: 'eventContext', label: this.$tc('s4gtm-settings.events.grid.context') },
                { property: 'ga4Event', label: this.$tc('s4gtm-settings.events.grid.ga4Event') },
                { property: 'active', label: this.$tc('s4gtm-settings.events.grid.active'), align: 'center' },
            ];
        },
    },

    created() {
        this.loadStandardEvents();
        this.loadCustomEvents();
    },

    methods: {
        loadStandardEvents() {
            this.isLoading = true;

            return this.systemConfigApiService
                .getValues('Shop4GoogleTagManager.config', null)
                .then((values) => {
                    const toggles = {};
                    const overrides = {};
                    Object.keys(STANDARD_EVENTS).forEach((contextKey) => {
                        STANDARD_EVENTS[contextKey].forEach((event) => {
                            const toggleKey = STD_PREFIX + event;
                            toggles[toggleKey] = values[toggleKey] !== undefined ? values[toggleKey] : true;
                            const override = values[STD_OVERRIDE_PREFIX + event];
                            overrides[event] = {
                                ga4Event: override?.ga4Event || '',
                                payload: override?.payload || {},
                            };
                        });
                    });
                    this.standardEvents = toggles;
                    this.standardOverrides = overrides;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        loadCustomEvents() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('priority', 'ASC'));
            criteria.addSorting(Criteria.sort('technicalName', 'ASC'));
            criteria.addAssociation('salesChannels');

            return this.gtmEventRepository.search(criteria, Shopware.Context.api).then((result) => {
                this.customEvents = result;
            });
        },

        onContextTab(context) {
            this.activeContext = context;
        },

        onToggleActive(item, value) {
            if (item.kind === 'standard') {
                this.standardEvents[item.configKey] = value;
                this.systemConfigApiService
                    .saveValues({ [item.configKey]: value }, null)
                    .then(() => this.createNotificationSuccess({ message: this.$tc('s4gtm-settings.general.saveSuccess') }))
                    .catch(() => this.createNotificationError({ message: this.$tc('s4gtm-settings.general.saveError') }));
                return;
            }

            item.entity.active = value;
            this.gtmEventRepository.save(item.entity, Shopware.Context.api)
                .then(() => {
                    this.createNotificationSuccess({ message: this.$tc('s4gtm-settings.general.saveSuccess') });
                    this.loadCustomEvents();
                })
                .catch(() => this.createNotificationError({ message: this.$tc('s4gtm-settings.general.saveError') }));
        },

        onCreateEvent() {
            const entity = this.gtmEventRepository.create(Shopware.Context.api);
            entity.technicalName = '';
            entity.eventContext = 'product';
            entity.ga4Event = '';
            entity.payload = {};
            entity.active = true;
            entity.priority = 0;

            if (!entity.salesChannels) {
                entity.salesChannels = new EntityCollection(
                    `/s4gtm-event/${entity.id}/salesChannels`,
                    'sales_channel',
                    Shopware.Context.api,
                    new Criteria(),
                );
            }
            this.editKind = 'custom';
            this.editModel = entity;
        },

        onEditEvent(item) {
            if (item.kind === 'custom') {
                const criteria = new Criteria(1, 1);
                criteria.addAssociation('salesChannels');
                this.gtmEventRepository.get(item.id, Shopware.Context.api, criteria).then((entity) => {
                    if (!entity.salesChannels) {
                        entity.salesChannels = new EntityCollection(
                            `/s4gtm-event/${entity.id}/salesChannels`,
                            'sales_channel',
                            Shopware.Context.api,
                            new Criteria(),
                        );
                    }
                    this.editKind = 'custom';
                    this.editModel = entity;
                });
                return;
            }

            const override = this.standardOverrides[item.event] || { ga4Event: '', payload: {} };
            this.editKind = 'standard';
            this.editModel = {
                event: item.event,
                eventContext: item.eventContext,
                active: item.active,
                ga4Event: override.ga4Event || '',
                payload: { ...override.payload },
            };
        },

        onDeleteEvent(item) {
            this.gtmEventRepository.delete(item.id, Shopware.Context.api).then(() => {
                this.createNotificationSuccess({ message: this.$tc('s4gtm-settings.events.deleteSuccess') });
                this.loadCustomEvents();
            });
        },

        onModalSave(model) {
            if (this.editKind === 'custom') {
                this.gtmEventRepository.save(model, Shopware.Context.api).then(() => {
                    this.createNotificationSuccess({ message: this.$tc('s4gtm-settings.general.saveSuccess') });
                    this.editModel = null;
                    this.loadCustomEvents();
                }).catch(() => {
                    this.createNotificationError({ message: this.$tc('s4gtm-settings.general.saveError') });
                });
                return;
            }

            const values = {
                [STD_PREFIX + model.event]: model.active,
                [STD_OVERRIDE_PREFIX + model.event]: {
                    ga4Event: model.ga4Event || '',
                    payload: model.payload || {},
                },
            };
            this.systemConfigApiService.saveValues(values, null).then(() => {
                this.createNotificationSuccess({ message: this.$tc('s4gtm-settings.general.saveSuccess') });
                this.editModel = null;
                this.loadStandardEvents();
            }).catch(() => {
                this.createNotificationError({ message: this.$tc('s4gtm-settings.general.saveError') });
            });
        },

        onModalClose() {
            this.editModel = null;
        },
    },
});
