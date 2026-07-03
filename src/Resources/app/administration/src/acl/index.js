Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: null,
    key: 's4gtm_event',
    roles: {
        viewer: {
            privileges: [
                's4gtm_event:read',
                's4gtm_event_sales_channel:read',
                'sales_channel:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                's4gtm_event:update',
                's4gtm_event_sales_channel:create',
                's4gtm_event_sales_channel:delete',
            ],
            dependencies: [
                's4gtm_event.viewer',
            ],
        },
        creator: {
            privileges: [
                's4gtm_event:create',
            ],
            dependencies: [
                's4gtm_event.viewer',
                's4gtm_event.editor',
            ],
        },
        deleter: {
            privileges: [
                's4gtm_event:delete',
            ],
            dependencies: [
                's4gtm_event.viewer',
            ],
        },
    },
});
