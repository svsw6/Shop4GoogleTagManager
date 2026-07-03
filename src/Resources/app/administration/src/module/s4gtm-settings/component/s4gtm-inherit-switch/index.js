import template from './s4gtm-inherit-switch.html.twig';

const { Component } = Shopware;


Component.register('s4gtm-inherit-switch', {
    template,

    props: {
        value: {
            type: Boolean,
            required: false,
            default: null,
        },
        inheritedValue: {
            type: Boolean,
            required: false,
            default: false,
        },
        hasParent: {
            type: Boolean,
            required: false,
            default: false,
        },
        label: {
            type: String,
            required: false,
            default: '',
        },
        helpText: {
            type: String,
            required: false,
            default: '',
        },
    },

    emits: ['update:value'],
});
