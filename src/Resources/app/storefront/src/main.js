import GtmPlugin from './plugin/gtm/gtm.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('S4GtmPlugin', GtmPlugin, '[data-s4gtm-data]');
