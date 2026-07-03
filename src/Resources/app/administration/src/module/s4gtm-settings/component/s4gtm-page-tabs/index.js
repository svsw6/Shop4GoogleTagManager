import template from './s4gtm-page-tabs.html.twig';
import './s4gtm-page-tabs.scss';

const { Component } = Shopware;

const ROUTE_PREFIX = 's4gtm.settings.index.';

Component.register('s4gtm-page-tabs', {
    template,

    computed: {
        tabItems() {
            return [
                { name: 'configuration', label: this.$tc('s4gtm-settings.tabs.configuration') },
                { name: 'events', label: this.$tc('s4gtm-settings.tabs.events') },
            ];
        },

        activeTab() {
            const name = this.$route.name || '';
            return name.startsWith(ROUTE_PREFIX) ? name.slice(ROUTE_PREFIX.length) : 'configuration';
        },
    },

    methods: {
        onTabChange(name) {
            const routeName = `${ROUTE_PREFIX}${name}`;
            if (this.$route.name !== routeName) {
                this.$router.push({ name: routeName });
            }
        },
    },
});
