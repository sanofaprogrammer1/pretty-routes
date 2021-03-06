<script src="https://cdn.jsdelivr.net/npm/vue"></script>
<script src="https://cdn.jsdelivr.net/npm/vuetify"></script>
<script src="https://cdn.jsdelivr.net/npm/axios"></script>
<script src="https://cdn.jsdelivr.net/npm/lodash"></script>

<script>
    const trans = {!! json_encode(\PrettyRoutes\Facades\Trans::all(), JSON_UNESCAPED_UNICODE) !!};
    const isEnabledCleanup = {{ config('app.env') !== 'production' && (bool) config('app.debug') === true ? 'true' : 'false' }};

    const colorScheme = () => {
        switch ('{{ config('pretty-routes.color_scheme', 'auto') }}') {
            case 'dark':
                return true;
            case 'light':
                return false;
            default:
                return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
    };

    new Vue({
        el: '#app',
        vuetify: new Vuetify({
            theme: {
                dark: colorScheme()
            }
        }),

        data: {
            itemsPerPage: 15,
            loading: false,

            url: {
                routes: '{{ route("pretty-routes.list") }}',
                clean: '{{ route("pretty-routes.clear") }}'
            },

            repository: {
                url: 'https://github.com/andrey-helldar/pretty-routes',
                icon: 'https://github.com/fluidicon.png'
            },

            routes: [],

            headers: [
                { text: trans.priority, sortable: true, value: 'priority' },
                { text: trans.methods, sortable: true, value: 'methods' },
                { text: trans.domain, sortable: true, value: 'domain' },
                { text: trans.path, sortable: true, value: 'path' },
                { text: trans.name, sortable: true, value: 'name' },
                { text: trans.module, sortable: true, value: 'module' },
                { text: trans.action, sortable: true, value: 'action' },
                { text: trans.middlewares, sortable: true, value: 'middlewares' }
            ],

            badges: {
                GET: 'green darken-1',
                HEAD: 'grey darken-1',
                POST: 'blue darken-1',
                PUT: 'orange darken-1',
                PATCH: 'cyan lighten-1',
                DELETE: 'red darken-1',
                OPTIONS: 'lime darken-1'
            },

            filter: {
                deprecated: [],
                types: 'all',
                module: [],
                domain: [],
                value: null
            },

            items: {
                base: [
                    { key: 'without', value: trans.without, color: 'grey lighten-2' }
                ],

                deprecated: [
                    { key: 'only', value: trans.only },
                    { key: 'without', value: trans.without, color: 'grey lighten-2' }
                ],

                types: [
                    { key: 'all', value: trans.all },
                    { key: 'api', value: trans.api },
                    { key: 'web', value: trans.web }
                ],

                domains: [],
                modules: []
            },

            colors: [
                'white--text amber darken-4',
                'white--text blue darken-2',
                'white--text deep-orange darken-3',
                'white--text deep-purple darken-1',
                'white--text green darken-2',
                'white--text indigo darken-1',
                'white--text light-blue darken-1',
                'white--text light-green darken-3',
                'white--text lime darken-4',
                'white--text orange darken-4',
                'white--text pink darken-4',
                'white--text purple darken-3',
                'white--text teal darken-1',
                'white--text yellow darken-3'
            ]
        },

        computed: {
            filteredRoutes() {
                return this.routes.filter(route => {
                    return this.allowDeprecated(route)
                        && this.allowTypes(route)
                        && this.allow(route, 'domain')
                        && this.allow(route, 'module');
                });
            },

            filteredHeaders() {
                return this.headers.filter(item => {
                    switch (item.value) {
                        case 'domain':
                            return this.hasHeader('domain');
                        case 'module':
                            return this.hasHeader('module');
                        default:
                            return true;
                    }
                });
            },

            countRoutes() {
                if (this.loading === true || this.$refs.routes === undefined) {
                    return '...';
                }

                let all = this.routes.length;
                let filtered = this.$refs.routes.$children[0].filteredItems.length;
                let particle = this.trans('of');

                return all === filtered ? all : `${ filtered } ${ particle } ${ all }`;
            },

            hasModules() {
                return this.hasRoute('module');
            },

            hasDomains() {
                return this.hasRoute('domain');
            },

            hasDeprecated() {
                return _.filter(this.routes, item => item.deprecated === true).length > 0;
            },

            hasTypes() {
                return _.filter(this.routes, item => item.is_api === true || item.is_web === true).length > 0;
            },

            sortedDomains: {
                get: function () {
                    return this.filter.domain;
                },

                set: function (items) {
                    this.filter.domain = this.sortFilter('domain', items);
                }
            },

            sortedModules: {
                get: function () {
                    return this.filter.module;
                },

                set: function (items) {
                    this.filter.module = this.sortFilter('module', items);
                }
            },

            sortedDeprecated: {
                get: function () {
                    return this.filter.deprecated;
                },

                set: function (items) {
                    this.filter.deprecated = this.sortFilter('deprecated', items);
                }
            },

            sortedTypes: {
                get: function () {
                    return this.filter.types;
                },

                set: function (value) {
                    this.filter.types = value;
                }
            }
        },

        mounted() {
            this.getRoutes();
        },

        methods: {
            getRoutes(force = false) {
                if (this.loading === true && force === false) {
                    return;
                }

                this.loading = true;

                axios.get(this.url.routes)
                    .then(response => {
                        this.routes = response.data;

                        this.setDomains();
                        this.setModules();
                    })
                    .catch(error => console.error(error))
                    .finally(() => this.loading = false);
            },

            clearRoutes() {
                if (this.loading === true) {
                    return;
                }

                this.loading = true;

                axios.post(this.url.clean)
                    .then(response => {
                        this.getRoutes(true);
                    })
                    .catch(error => console.error(error))
                    .finally(() => this.loading = false);
            },

            getRoutesKey(key) {
                let result = [...this.items.base];

                _.each(this.routes, route => {
                    let name = route[key];

                    if (name !== null && ! this.inArray(result, name, 'key')) {
                        result.push({ key: name, value: name });
                    }
                });

                return _.map(result, (item, index) => {
                    if (item.color !== undefined) {
                        return item;
                    }

                    let color = this.getColor(index);

                    _.set(item, 'color', color);

                    return item;
                });
            },

            getColor(index) {
                return this.colors[index] === undefined
                    ? this.colors[0]
                    : this.colors[index];
            },

            setDomains() {
                this.items.domains = this.getRoutesKey('domain');
            },

            setModules() {
                this.items.modules = this.getRoutesKey('module');
            },

            setFilter(key, value) {
                if (key === 'value') {
                    this.filter.value = value;
                } else {
                    if (! this.inArray(this.filter[key], value)) {
                        this.pushFilter(key, value);
                    }
                }
            },

            unselectFilter(key, value) {
                this.filter[key] = _.filter(this.filter[key], val => val.toLowerCase() !== value.toLowerCase());
            },

            allowDeprecated(route) {
                let values = this.filter.deprecated;

                if (this.isEmptyValue(values)) {
                    return true;
                }

                let only = this.inArray(values, 'only') ? route.deprecated === true : false;
                let without = this.inArray(values, 'without') ? route.deprecated === false : false;

                return only || without;
            },

            allowTypes(route) {
                switch (this.filter.types) {
                    case 'api':
                        return route.is_api === true;
                    case 'web':
                        return route.is_web === true;
                    default:
                        return true;
                }
            },

            allow(route, key) {
                let filters = this.filter[key];
                let value = route[key];

                let all = this.isEmptyValue(filters);
                let without = this.inArray(filters, 'without') ? value === null : false;
                let search = this.inArray(filters, value);

                return all || without || search;
            },

            isDirty() {
                return this.isDoesntEmptyValue(this.filter.deprecated)
                    || this.isDoesntEmptyValue(this.filter.types)
                    || this.isDoesntEmptyValue(this.filter.domain)
                    || this.isDoesntEmptyValue(this.filter.module)
                    || this.isDoesntEmptyValue(this.filter.value);
            },

            isEmptyValue(value) {
                return _.isEmpty(value);
            },

            isDoesntEmptyValue(value) {
                return ! this.isEmptyValue(value);
            },

            resetFilters() {
                this.filter.deprecated = null;
                this.filter.types = null;
                this.filter.domain = null;
                this.filter.module = null;
                this.filter.value = null;
            },

            sortFilter(key, items) {
                return this.filter[key] = items.sort();
            },

            hasHeader(key) {
                return this.has(this.filteredRoutes, key);
            },

            hasRoute(key) {
                return this.has(this.routes, key);
            },

            has(items, key) {
                return _.filter(items, item => item[key] !== null).length > 0;
            },

            inArray(array, value, key = null) {
                return _.filter(array, (val, index) => {
                    if (key !== null) {
                        if (index === key && val === value)
                            return true;
                    } else {
                        if (val === value)
                            return true;
                    }

                    return false;
                }).length > 0;
            },

            highlight(value, regex, modifier) {
                return value.replace(regex, `<span class="orange--text text--darken-2">${ modifier }</span>`);
            },

            highlightParameters(value) {
                return this.highlight(value, /({[^}]+})/gi, '$1');
            },

            highlightMethod(value) {
                return this.highlight(value, /(@.*)$/gi, '$&');
            },

            pushFilter(key, value) {
                this.isEmptyValue(this.filter[key])
                    ? this.filter[key] = [value]
                    : this.filter[key].push(value);
            },

            isEnabledCleanup() {
                return window.isEnabledCleanup;
            },

            trans(key) {
                return trans[key];
            },

            openGitHubRepository() {
                window.open(this.repository.url);
            }
        }
    });
</script>
