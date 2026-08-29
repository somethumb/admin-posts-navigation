(function() {
    'use strict';

    if (
        typeof wp === 'undefined' ||
        !wp.element ||
        !wp.components ||
        !wp.editPost ||
        !wp.plugins ||
        !wp.data
    ) {
        console.warn(
            'TWF Admin Posts Navigation: Required WordPress objects not available'
        );
        return;
    }

    const {
        createElement: e,
        useState,
        useEffect,
        useCallback
    } = wp.element;

    const {
        Button,
        Spinner,
        SelectControl
    } = wp.components;

    const {
        PluginDocumentSettingPanel
    } = wp.editor || wp.editPost;

    const {
        registerPlugin
    } = wp.plugins;

    const {
        useSelect
    } = wp.data;


    function TWFAdminPostsNavigationPanel() {

        const [navigationData, setNavigationData] = useState({
            prev: null,
            next: null,
            current_position: 0,
            total_posts: 0
        });

        const [loading, setLoading] = useState(true);
        const [error, setError] = useState(null);

        const [sortMethod, setSortMethod] = useState('date');
        const [sortOrder, setSortOrder] = useState('ASC');

        const [sortUpdating, setSortUpdating] = useState(false);


        const currentPostId = useSelect(function(select) {

            const editor = select('core/editor');

            return editor
                ? editor.getCurrentPostId()
                : null;

        });


        /**
         * Load previous / next navigation data.
         */
        const loadNavigationData = useCallback(function() {

            if (
                !currentPostId ||
                typeof twfAdminPostsNavigation === 'undefined'
            ) {
                setLoading(false);
                return;
            }

            setLoading(true);
            setError(null);

            const formData = new FormData();

            formData.append(
                'action',
                'twf_admin_posts_nav_get_posts'
            );

            formData.append(
                'post_id',
                currentPostId
            );

            formData.append(
                'nonce',
                twfAdminPostsNavigation.nonce
            );


            const controller = new AbortController();

            const timeoutId = setTimeout(function() {
                controller.abort();
            }, 10000);


            fetch(
                twfAdminPostsNavigation.ajaxUrl,
                {
                    method: 'POST',
                    body: formData,
                    signal: controller.signal,
                    credentials: 'same-origin'
                }
            )

            .then(function(response) {

                clearTimeout(timeoutId);

                if (!response.ok) {
                    throw new Error(
                        'Network error: ' + response.status
                    );
                }

                return response.json();

            })

            .then(function(data) {

                if (
                    data &&
                    data.success &&
                    data.data
                ) {

                    setNavigationData(
                        data.data
                    );

                } else {

                    setError(
                        'Could not load navigation'
                    );

                }

                setLoading(false);

            })

            .catch(function(err) {

                clearTimeout(timeoutId);

                if (err.name === 'AbortError') {

                    setError(
                        'Request timeout'
                    );

                } else {

                    setError(
                        'Navigation error: ' +
                        err.message
                    );

                }

                setLoading(false);

            });


            return function() {

                clearTimeout(timeoutId);
                controller.abort();

            };

        }, [currentPostId]);


        /**
         * Initialize sort preferences supplied by PHP.
         */
        useEffect(function() {

            if (
                typeof twfAdminPostsNavigation !== 'undefined'
            ) {

                if (
                    twfAdminPostsNavigation.currentSort
                ) {

                    setSortMethod(
                        twfAdminPostsNavigation.currentSort
                    );

                }

                if (
                    twfAdminPostsNavigation.currentOrder
                ) {

                    setSortOrder(
                        twfAdminPostsNavigation.currentOrder
                    );

                }

            }

        }, []);


        /**
         * Initial navigation load.
         */
        useEffect(
            loadNavigationData,
            [loadNavigationData]
        );


        /**
         * Save both sorting preferences.
         */
        const updateSortPreference = useCallback(
            function(
                newSortMethod,
                newSortOrder
            ) {

                if (
                    typeof twfAdminPostsNavigation === 'undefined' ||
                    sortUpdating
                ) {
                    return;
                }


                setSortUpdating(true);

                setSortMethod(
                    newSortMethod
                );

                setSortOrder(
                    newSortOrder
                );


                const formData = new FormData();

                formData.append(
                    'action',
                    'twf_admin_posts_nav_update_sort'
                );

                formData.append(
                    'post_type',
                    twfAdminPostsNavigation.postType
                );

                formData.append(
                    'sort_method',
                    newSortMethod
                );

                formData.append(
                    'sort_order',
                    newSortOrder
                );

                formData.append(
                    'nonce',
                    twfAdminPostsNavigation.sortNonce
                );


                fetch(
                    twfAdminPostsNavigation.ajaxUrl,
                    {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    }
                )

                .then(function(response) {

                    return response.json();

                })

                .then(function(data) {

                    if (
                        data &&
                        data.success
                    ) {

                        loadNavigationData();

                    } else {

                        setError(
                            'Failed to update sort preference'
                        );

                        setSortMethod(
                            twfAdminPostsNavigation.currentSort ||
                            'date'
                        );

                        setSortOrder(
                            twfAdminPostsNavigation.currentOrder ||
                            'ASC'
                        );

                    }

                })

                .catch(function(err) {

                    setError(
                        'Failed to update sort preference: ' +
                        err.message
                    );

                    setSortMethod(
                        twfAdminPostsNavigation.currentSort ||
                        'date'
                    );

                    setSortOrder(
                        twfAdminPostsNavigation.currentOrder ||
                        'ASC'
                    );

                })

                .finally(function() {

                    setSortUpdating(false);

                });

            },
            [
                loadNavigationData,
                sortUpdating
            ]
        );


        /**
         * Sort By dropdown.
         */
        const handleSortChange = useCallback(
            function(newSortMethod) {

                updateSortPreference(
                    newSortMethod,
                    sortOrder
                );

            },
            [
                updateSortPreference,
                sortOrder
            ]
        );


        /**
         * ASC / DESC dropdown.
         */
        const handleOrderChange = useCallback(
            function(newSortOrder) {

                updateSortPreference(
                    sortMethod,
                    newSortOrder
                );

            },
            [
                updateSortPreference,
                sortMethod
            ]
        );


        if (
            typeof twfAdminPostsNavigation === 'undefined'
        ) {

            return e(
                'p',
                {
                    style: {
                        color: '#d63638',
                        fontSize: '12px'
                    }
                },
                'Configuration error'
            );

        }


        /**
         * Sort options.
         */
        const sortOptions = [

            {
                label:
                    twfAdminPostsNavigation
                        .sortOptions?.menu_order ||
                    'Menu Order',

                value: 'menu_order'
            },

            {
                label:
                    twfAdminPostsNavigation
                        .sortOptions?.date ||
                    'Date Published',

                value: 'date'
            },

            {
                label:
                    twfAdminPostsNavigation
                        .sortOptions?.alphabetical ||
                    'Alphabetical',

                value: 'alphabetical'
            },

            {
                label:
                    twfAdminPostsNavigation
                        .sortOptions?.post_id ||
                    'Post ID',

                value: 'post_id'
            }

        ];


        /**
         * Sort direction options.
         */
        const orderOptions = [

            {
                label: 'Ascending',
                value: 'ASC'
            },

            {
                label: 'Descending',
                value: 'DESC'
            }

        ];


        /**
         * Loading state.
         */
        if (loading) {

            return e(
                'div',
                {
                    style: {
                        textAlign: 'center',
                        padding: '20px'
                    }
                },

                e(Spinner),

                e(
                    'p',
                    {
                        style: {
                            marginTop: '10px',
                            fontSize: '12px'
                        }
                    },
                    'Loading navigation...'
                )
            );

        }


        /**
         * Main panel.
         */
        return e(
            'div',
            {
                className:
                    'twf-admin-posts-navigation-gutenberg'
            },


            /**
             * Sorting controls.
             */
            e(
                'div',
                {
                    className:
                        'twf-admin-posts-nav-sort-gutenberg'
                },


                e(
                    SelectControl,
                    {
                        label: 'Sort By',

                        value:
                            sortMethod,

                        options:
                            sortOptions,

                        onChange:
                            handleSortChange,

                        disabled:
                            sortUpdating,

                        className:
                            'twf-admin-posts-nav-sort-select'
                    }
                ),


                e(
                    SelectControl,
                    {
                        label: 'Order',

                        value:
                            sortOrder,

                        options:
                            orderOptions,

                        onChange:
                            handleOrderChange,

                        disabled:
                            sortUpdating,

                        className:
                            'twf-admin-posts-nav-order-select'
                    }
                ),


                sortUpdating &&
                e(
                    'p',
                    {
                        style: {
                            fontSize: '11px',
                            color: '#666',
                            marginTop: '4px',
                            marginBottom: '0'
                        }
                    },
                    'Updating sort order...'
                )

            ),


            /**
             * Error message.
             */
            error &&
            e(
                'p',
                {
                    style: {
                        color: '#d63638',
                        fontSize: '12px',
                        marginBottom: '12px'
                    }
                },
                error
            ),


            /**
             * No adjacent posts.
             */
            (
                !navigationData.prev &&
                !navigationData.next
            )

            ?

            e(
                'div',
                null,

                e(
                    'p',
                    {
                        style: {
                            fontSize: '12px',
                            color: '#757575'
                        }
                    },

                    'No adjacent ' +
                    (
                        twfAdminPostsNavigation
                            .postTypeLabelPlural ||
                        'posts'
                    ).toLowerCase() +
                    ' found.'
                ),


                navigationData.total_posts > 0 &&

                e(
                    'p',
                    {
                        style: {
                            fontSize: '11px',
                            color: '#999'
                        }
                    },

                    'Total: ' +
                    navigationData.total_posts +
                    ' ' +
                    (
                        twfAdminPostsNavigation
                            .postTypeLabelPlural ||
                        'posts'
                    ).toLowerCase()
                )

            )

            :

            /**
             * Navigation buttons.
             */
            e(
                'div',
                null,


                /**
                 * Position information.
                 */
                e(
                    'div',
                    {
                        style: {
                            marginBottom: '12px',
                            fontSize: '11px',
                            color: '#666',
                            textAlign: 'center'
                        }
                    },

                    'Position: ' +
                    navigationData.current_position +
                    ' of ' +
                    navigationData.total_posts +
                    ' ' +
                    (
                        twfAdminPostsNavigation
                            .postTypeLabelPlural ||
                        'posts'
                    ).toLowerCase()

                ),


                /**
                 * Previous button.
                 */
                navigationData.prev &&

                e(
                    Button,
                    {
                        variant: 'secondary',

                        className:
                            'twf-admin-posts-navigation-btn ' +
                            'twf-admin-posts-navigation-prev',

                        title:
                            navigationData.prev.title,

                        style: {
                            marginBottom: '8px',
                            width: '100%',
                            justifyContent: 'flex-start'
                        },

                        onClick: function() {

                            window.location.href =
                                navigationData.prev.edit_link
                                    .replace(
                                        /&amp;/g,
                                        '&'
                                    );

                        }
                    },

                    '← Previous: ' +

                    (
                        navigationData.prev.title &&
                        navigationData.prev.title.length > 25

                        ?

                        navigationData.prev.title.substring(
                            0,
                            25
                        ) + '...'

                        :

                        navigationData.prev.title ||
                        'Untitled'
                    )

                ),


                /**
                 * Next button.
                 */
                navigationData.next &&

                e(
                    Button,
                    {
                        variant: 'secondary',

                        className:
                            'twf-admin-posts-navigation-btn ' +
                            'twf-admin-posts-navigation-next',

                        title:
                            navigationData.next.title,

                        style: {
                            width: '100%',
                            justifyContent: 'flex-start'
                        },

                        onClick: function() {

                            window.location.href =
                                navigationData.next.edit_link
                                    .replace(
                                        /&amp;/g,
                                        '&'
                                    );

                        }
                    },

                    'Next: ' +

                    (
                        navigationData.next.title &&
                        navigationData.next.title.length > 25

                        ?

                        navigationData.next.title.substring(
                            0,
                            25
                        ) + '...'

                        :

                        navigationData.next.title ||
                        'Untitled'
                    )

                    + ' →'

                )

            )
        );

    }


    /**
     * Error boundary.
     */
    function TWFAdminPostsNavigationWithErrorBoundary() {

        try {

            return e(
                TWFAdminPostsNavigationPanel
            );

        } catch (error) {

            console.error(
                'TWF Admin Posts Navigation: Component error',
                error
            );

            return e(
                'p',
                {
                    style: {
                        color: '#d63638',
                        fontSize: '12px'
                    }
                },
                'Navigation error'
            );

        }

    }


    /**
     * Register Gutenberg sidebar panel.
     */
    registerPlugin(
        'twf-admin-posts-navigation',
        {

            render: function() {

                return e(
                    PluginDocumentSettingPanel,
                    {
                        name:
                            'twf-admin-posts-navigation',

                        title:
                            (
                                twfAdminPostsNavigation
                                    .postTypeLabel ||
                                'Post'
                            ) +
                            ' Navigation',

                        className:
                            'twf-admin-posts-navigation-panel'
                    },

                    e(
                        TWFAdminPostsNavigationWithErrorBoundary
                    )
                );

            }

        }
    );

})();