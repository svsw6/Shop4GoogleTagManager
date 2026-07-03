import './component/s4gtm-page-tabs';
import './component/s4gtm-inherit-switch';
import './page/s4gtm-settings-index';
import './page/s4gtm-settings';
import './page/s4gtm-events';
import './component/s4gtm-event-detail';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('s4gtm-settings', {
    type: 'plugin',
    name: 'Shop4GoogleTagManager',
    title: 's4gtm-settings.general.mainMenuItemGeneral',
    description: 's4gtm-settings.general.description',
    color: '#1a73e8',
    icon: 'regular-cog',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        index: {
            component: 's4gtm-settings-index',
            path: 'index',
            redirect: { name: 's4gtm.settings.index.configuration' },
            meta: {
                parentPath: 'sw.settings.index.plugins',
                privilege: 'system.system_config',
            },
            children: {
                configuration: {
                    component: 's4gtm-settings',
                    path: 'configuration',
                    meta: {
                        parentPath: 'sw.settings.index.plugins',
                        privilege: 'system.system_config',
                    },
                },
                events: {
                    component: 's4gtm-events',
                    path: 'events',
                    meta: {
                        parentPath: 'sw.settings.index.plugins',
                        privilege: 'system.system_config',
                    },
                },
            },
        },
    },

    settingsItem: {
        group: 'plugins',
        to: 's4gtm.settings.index',
        icon: 'regular-cog',
        name: 'Shop4GoogleTagManager',
        label: 's4gtm-settings.general.mainMenuItemGeneral',
    },
});
