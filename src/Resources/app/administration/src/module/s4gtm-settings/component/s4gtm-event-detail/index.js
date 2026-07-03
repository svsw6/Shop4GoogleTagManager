import template from './s4gtm-event-detail.html.twig';
import { CUSTOM_CONTEXTS } from '../../constant/event-catalog';

const { Component } = Shopware;

Component.register('s4gtm-event-detail', {
    template,

    props: {
        model: {
            type: Object,
            required: true,
        },
        kind: {
            type: String,
            required: true,
            validator(value) {
                return ['custom', 'standard'].includes(value);
            },
        },
    },

    data() {
        return {
            payloadRows: [],
            isOpen: true,
        };
    },

    computed: {
        isCustom() {
            return this.kind === 'custom';
        },

        contextOptions() {
            return CUSTOM_CONTEXTS.map((value) => ({
                value,
                label: this.$tc('s4gtm-settings.context.' + value),
            }));
        },

        ga4Placeholder() {
            return this.isCustom ? 'view_promotion' : this.model.event;
        },

        isValid() {
            if (this.isCustom) {
                return Boolean(this.model.technicalName && this.model.eventContext && this.model.ga4Event);
            }
            return true;
        },
    },

    created() {
        this.payloadRows = Object.entries(this.model.payload || {}).map(([key, value]) => ({
            id: Shopware.Utils.createId(),
            key,
            value,
        }));
    },

    methods: {
        onAddPayloadRow() {
            this.payloadRows.push({ id: Shopware.Utils.createId(), key: '', value: '' });
        },

        onRemovePayloadRow(row) {
            this.payloadRows = this.payloadRows.filter((entry) => entry.id !== row.id);
        },

        onSave() {
            if (!this.isValid) {
                return;
            }

            const payload = {};
            this.payloadRows.forEach((row) => {
                if (row.key) {
                    payload[row.key] = row.value;
                }
            });
            this.model.payload = payload;

            this.$emit('save', this.model);
        },

        onModalChange(isOpen) {
            this.isOpen = isOpen;
            if (!isOpen) {
                this.onClose();
            }
        },

        onClose() {
            this.$emit('close');
        },
    },
});
