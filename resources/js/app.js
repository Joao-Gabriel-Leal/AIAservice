import Chart from 'chart.js/auto';

import './echo';

const activeCharts = new Map();
const sidebarStorageKey = 'portal.sidebar.collapsed';

function applyPortalSidebarState(collapsed) {
    document.documentElement.classList.toggle('portal-sidebar-collapsed', collapsed);

    document.querySelectorAll('[data-portal-sidebar-toggle]').forEach((button) => {
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        button.setAttribute('aria-label', collapsed ? 'Expandir menu' : 'Recolher menu');
        button.setAttribute('title', collapsed ? 'Expandir menu' : 'Recolher menu');
    });
}

function initializePortalSidebar() {
    const collapsed = window.localStorage.getItem(sidebarStorageKey) === 'true';
    applyPortalSidebarState(collapsed);

    document.querySelectorAll('[data-portal-sidebar-toggle]').forEach((button) => {
        if (button.dataset.sidebarToggleBound === 'true') {
            return;
        }

        button.dataset.sidebarToggleBound = 'true';
        button.addEventListener('click', () => {
            const nextCollapsed = ! document.documentElement.classList.contains('portal-sidebar-collapsed');
            window.localStorage.setItem(sidebarStorageKey, String(nextCollapsed));
            applyPortalSidebarState(nextCollapsed);
        });
    });
}

let enhancedSelectListenersBound = false;

function enhancedSelectOptions(select) {
    return Array.from(select.options).map((option, index) => ({
        index,
        value: option.value,
        label: option.label || option.textContent?.trim() || '',
        disabled: option.disabled,
        selected: option.selected,
        hidden: option.hidden,
    })).filter((option) => ! option.hidden);
}

function selectedEnhancedSelectOption(select, options = enhancedSelectOptions(select)) {
    return options.find((option) => option.selected)
        || options.find((option) => ! option.disabled)
        || options[0]
        || { index: -1, label: '' };
}

function enabledEnhancedSelectOptions(options) {
    return options.filter((option) => ! option.disabled);
}

function closeEnhancedSelect(root) {
    const state = root?._uiEnhancedSelect;

    if (!state) {
        return;
    }

    root.dataset.open = 'false';
    state.button.setAttribute('aria-expanded', 'false');
}

function closeOtherEnhancedSelects(currentRoot = null) {
    document.querySelectorAll('.ui-enhanced-select[data-open="true"]').forEach((root) => {
        if (root !== currentRoot) {
            closeEnhancedSelect(root);
        }
    });
}

function setEnhancedSelectActiveOption(root, index) {
    const state = root?._uiEnhancedSelect;

    if (!state) {
        return;
    }

    const options = enhancedSelectOptions(state.select);
    const enabledOptions = enabledEnhancedSelectOptions(options);

    if (enabledOptions.length === 0) {
        state.activeIndex = -1;
        state.button.removeAttribute('aria-activedescendant');
        return;
    }

    const option = options.find((item) => item.index === index && ! item.disabled) ?? enabledOptions[0];
    state.activeIndex = option.index;
    state.button.setAttribute('aria-activedescendant', `${state.id}-option-${option.index}`);

    state.menu.querySelectorAll('[role="option"]').forEach((optionButton) => {
        optionButton.dataset.active = optionButton.id === `${state.id}-option-${option.index}` ? 'true' : 'false';
    });
}

function moveEnhancedSelectActiveOption(root, direction) {
    const state = root?._uiEnhancedSelect;

    if (!state) {
        return;
    }

    const options = enabledEnhancedSelectOptions(enhancedSelectOptions(state.select));

    if (options.length === 0) {
        return;
    }

    const currentIndex = options.findIndex((option) => option.index === state.activeIndex);
    const nextIndex = currentIndex === -1
        ? 0
        : (currentIndex + direction + options.length) % options.length;

    setEnhancedSelectActiveOption(root, options[nextIndex].index);
}

function chooseEnhancedSelectOption(root, optionIndex) {
    const state = root?._uiEnhancedSelect;
    const option = state?.select.options[optionIndex];

    if (!state || !option || option.disabled) {
        return;
    }

    state.select.value = option.value;
    option.selected = true;
    state.select.dispatchEvent(new Event('input', { bubbles: true }));
    state.select.dispatchEvent(new Event('change', { bubbles: true }));

    syncEnhancedSelect(root);
    closeEnhancedSelect(root);
    state.button.focus();
}

function openEnhancedSelect(root) {
    const state = root?._uiEnhancedSelect;

    if (!state || state.select.disabled) {
        return;
    }

    closeOtherEnhancedSelects(root);
    syncEnhancedSelect(root);

    root.dataset.open = 'true';
    state.button.setAttribute('aria-expanded', 'true');
    setEnhancedSelectActiveOption(root, selectedEnhancedSelectOption(state.select).index);
}

function syncEnhancedSelect(root) {
    const state = root?._uiEnhancedSelect;

    if (!state) {
        return;
    }

    const options = enhancedSelectOptions(state.select);
    const selectedOption = selectedEnhancedSelectOption(state.select, options);
    const isDisabled = state.select.disabled || enabledEnhancedSelectOptions(options).length === 0;

    root.classList.toggle('ui-enhanced-select-disabled', isDisabled);
    root.classList.toggle('ui-enhanced-select-pill', state.select.classList.contains('ui-native-select-pill'));
    state.button.disabled = isDisabled;
    state.value.textContent = selectedOption.label;
    state.menu.innerHTML = '';

    options.forEach((option) => {
        const optionButton = document.createElement('button');
        optionButton.type = 'button';
        optionButton.id = `${state.id}-option-${option.index}`;
        optionButton.className = 'ui-enhanced-select-option';
        optionButton.dataset.value = option.value;
        optionButton.dataset.active = option.index === state.activeIndex ? 'true' : 'false';
        optionButton.disabled = option.disabled;
        optionButton.setAttribute('role', 'option');
        optionButton.setAttribute('aria-selected', option.selected ? 'true' : 'false');
        optionButton.textContent = option.label;
        optionButton.addEventListener('click', () => chooseEnhancedSelectOption(root, option.index));
        optionButton.addEventListener('mouseenter', () => {
            if (!option.disabled) {
                setEnhancedSelectActiveOption(root, option.index);
            }
        });

        state.menu.append(optionButton);
    });

    setEnhancedSelectActiveOption(root, selectedOption.index);
}

function createEnhancedSelect(select) {
    if (select.dataset.uiSelectEnhanced === 'true' && select.nextElementSibling?.classList.contains('ui-enhanced-select')) {
        syncEnhancedSelect(select.nextElementSibling);
        return;
    }

    if (select.dataset.uiSelectEnhanced === 'true') {
        select.classList.remove('ui-enhanced-select-native');
        delete select.dataset.uiSelectEnhanced;
    }

    if (select.multiple || select.size > 1 || select.hasAttribute('data-flux-select-native') || select.hasAttribute('data-ui-native-select')) {
        return;
    }

    const id = select.id || `ui-enhanced-select-${Math.random().toString(36).slice(2)}`;
    const root = document.createElement('div');
    const button = document.createElement('button');
    const value = document.createElement('span');
    const chevron = document.createElement('span');
    const menu = document.createElement('div');
    const widthClasses = Array.from(select.classList).filter((className) => /^(w-|min-w-|max-w-|basis-|flex-)/.test(className));
    const nativePillDot = select.previousElementSibling?.classList.contains('ui-native-pill-dot')
        ? select.previousElementSibling
        : null;

    root.className = 'ui-enhanced-select';
    if (widthClasses.length > 0) {
        root.classList.add(...widthClasses);
    }
    root.dataset.open = 'false';
    root.dataset.uiEnhancedSelect = id;

    button.type = 'button';
    button.className = 'ui-enhanced-select-button';
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');

    if (select.getAttribute('aria-label')) {
        button.setAttribute('aria-label', select.getAttribute('aria-label'));
    } else if (select.name) {
        button.setAttribute('aria-label', select.name);
    }

    value.className = 'ui-enhanced-select-value';
    chevron.className = 'ui-enhanced-select-chevron';
    chevron.setAttribute('aria-hidden', 'true');
    chevron.innerHTML = '<svg viewBox="0 0 20 20" fill="none"><path d="M5.5 7.5 10 12l4.5-4.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/></svg>';

    if (select.classList.contains('ui-native-select-pill')) {
        const dot = document.createElement('span');
        dot.className = 'ui-enhanced-select-dot';
        button.append(dot);

        if (nativePillDot) {
            nativePillDot.dataset.uiHiddenByEnhancer = 'true';
        }
    }

    button.append(value, chevron);

    menu.className = 'ui-enhanced-select-menu';
    menu.setAttribute('role', 'listbox');

    root.append(button, menu);
    select.after(root);
    select.classList.add('ui-enhanced-select-native');
    select.dataset.uiSelectEnhanced = 'true';

    if (! select.hasAttribute('data-ui-original-tabindex')) {
        select.dataset.uiOriginalTabindex = select.getAttribute('tabindex') ?? '';
    }

    select.tabIndex = -1;

    root._uiEnhancedSelect = {
        id,
        select,
        button,
        value,
        menu,
        activeIndex: selectedEnhancedSelectOption(select).index,
    };

    button.addEventListener('click', () => {
        if (root.dataset.open === 'true') {
            closeEnhancedSelect(root);
            return;
        }

        openEnhancedSelect(root);
    });

    button.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeEnhancedSelect(root);
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            if (root.dataset.open !== 'true') {
                openEnhancedSelect(root);
            }

            moveEnhancedSelectActiveOption(root, event.key === 'ArrowDown' ? 1 : -1);
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();

            if (root.dataset.open !== 'true') {
                openEnhancedSelect(root);
                return;
            }

            chooseEnhancedSelectOption(root, root._uiEnhancedSelect.activeIndex);
        }
    });

    select.addEventListener('change', () => syncEnhancedSelect(root));
    select.addEventListener('input', () => syncEnhancedSelect(root));

    root._uiEnhancedSelect.observer = new MutationObserver(() => syncEnhancedSelect(root));
    root._uiEnhancedSelect.observer.observe(select, {
        attributes: true,
        attributeFilter: ['class', 'disabled', 'label', 'selected', 'style', 'value'],
        childList: true,
        subtree: true,
    });

    syncEnhancedSelect(root);
}

function initializeEnhancedSelects() {
    document.querySelectorAll('.portal-shell select').forEach((select) => createEnhancedSelect(select));

    if (enhancedSelectListenersBound) {
        return;
    }

    enhancedSelectListenersBound = true;

    document.addEventListener('click', (event) => {
        if (! event.target.closest?.('.ui-enhanced-select')) {
            closeOtherEnhancedSelects();
        }
    });

    window.addEventListener('resize', () => closeOtherEnhancedSelects());
}

if (! window.ticketInlineTitle) {
    window.ticketInlineTitle = function (config) {
        return {
            ticketId: config.ticketId,
            editing: false,
            value: config.title ?? '',
            original: config.title ?? '',

            startEditing() {
                this.original = this.value;
                this.editing = true;

                this.$nextTick(() => {
                    this.$refs.input?.focus();
                    this.$refs.input?.select();
                });
            },

            saveTitle(wire) {
                if (! this.editing) {
                    return;
                }

                const previous = String(this.original ?? '');
                const next = String(this.value ?? '').trim();

                if (next === '') {
                    this.value = previous;
                    this.editing = false;

                    return;
                }

                this.value = next;
                this.original = next;
                this.editing = false;

                if (next !== previous) {
                    wire.updateFixedField(this.ticketId, 'title', next);
                }
            },

            cancelEditing() {
                this.value = this.original;
                this.editing = false;
            },
        };
    };
}

if (! window.ticketComposerAutocomplete) {
    window.ticketComposerAutocomplete = function (config) {
        return {
            users: Array.isArray(config.users) ? config.users : [],
            templates: Array.isArray(config.templates) ? config.templates : [],
            message: config.message,
            mentionedIds: config.mentionedIds ?? [],
            tracksMentions: Object.prototype.hasOwnProperty.call(config, 'mentionedIds'),
            autocompleteOpen: false,
            autocompleteQuery: '',
            activeAutocompleteIndex: 0,
            triggerStart: null,
            suppressNextRefresh: false,

            init() {
                this.syncMentionIds();

                this.$watch('message', () => {
                    this.syncMentionIds();

                    if (this.suppressNextRefresh) {
                        this.suppressNextRefresh = false;
                        return;
                    }

                    this.refreshAutocompleteMenu();
                });
            },

            get filteredItems() {
                const query = this.normalizeText(this.autocompleteQuery);
                const selectedIds = new Set((this.mentionedIds ?? []).map((id) => Number(id)));
                const users = this.users
                    .filter((user) => !selectedIds.has(Number(user.id)))
                    .filter((user) => query === '' || this.normalizeText(user.name).includes(query))
                    .map((user) => ({
                        id: Number(user.id),
                        key: `user-${user.id}`,
                        type: 'user',
                        name: user.name,
                        preview: '',
                    }));
                const templates = this.templates
                    .filter((template) => {
                        if (query === '') {
                            return true;
                        }

                        return this.normalizeText(`${template.name} ${template.body}`).includes(query);
                    })
                    .map((template) => ({
                        id: Number(template.id),
                        key: `template-${template.id}`,
                        type: 'template',
                        name: template.name,
                        body: template.body,
                        isPersonal: Boolean(template.isPersonal),
                        preview: this.previewText(template.body),
                    }));

                return [...users, ...templates].slice(0, 8);
            },

            handleAutocompleteInput() {
                this.syncMentionIds();
                this.refreshAutocompleteMenu();
            },

            refreshAutocompleteMenu() {
                const textarea = this.$refs.composerTextarea;

                if (!textarea) {
                    return;
                }

                const caret = textarea.selectionStart ?? String(this.message ?? '').length;
                const beforeCaret = String(this.message ?? '').slice(0, caret);
                const atIndex = beforeCaret.lastIndexOf('@');

                if (atIndex < 0) {
                    this.closeAutocompleteMenu();
                    return;
                }

                const fragment = beforeCaret.slice(atIndex + 1);

                if (fragment.includes('\n') || fragment.length > 64) {
                    this.closeAutocompleteMenu();
                    return;
                }

                this.triggerStart = atIndex;
                this.autocompleteQuery = fragment;
                this.autocompleteOpen = this.filteredItems.length > 0;
                this.activeAutocompleteIndex = Math.min(
                    this.activeAutocompleteIndex,
                    Math.max(this.filteredItems.length - 1, 0),
                );
            },

            moveAutocomplete(direction) {
                if (!this.autocompleteOpen || this.filteredItems.length === 0) {
                    return;
                }

                const total = this.filteredItems.length;
                this.activeAutocompleteIndex = (this.activeAutocompleteIndex + direction + total) % total;
            },

            selectActiveAutocompleteItem() {
                if (!this.autocompleteOpen || this.filteredItems.length === 0) {
                    return;
                }

                this.selectAutocompleteItem(this.filteredItems[this.activeAutocompleteIndex]);
            },

            selectAutocompleteItem(item) {
                if (item.type === 'template') {
                    this.selectTemplate(item);
                    return;
                }

                this.selectUser(item);
            },

            selectUser(user) {
                const textarea = this.$refs.composerTextarea;

                if (!textarea || this.triggerStart === null) {
                    return;
                }

                const caret = textarea.selectionStart ?? String(this.message ?? '').length;
                const before = String(this.message ?? '').slice(0, this.triggerStart);
                const after = String(this.message ?? '').slice(caret);
                const mention = `@${user.name}`;
                const spacer = after.startsWith(' ') || after.startsWith('\n') ? '' : ' ';
                const nextMessage = `${before}${mention}${spacer}${after}`;
                const nextCaret = before.length + mention.length + spacer.length;

                this.suppressNextRefresh = true;
                this.message = nextMessage;
                this.mentionedIds = Array.from(new Set([
                    ...(this.mentionedIds ?? []).map((id) => Number(id)),
                    Number(user.id),
                ]));
                this.closeAutocompleteMenu();

                this.$nextTick(() => {
                    textarea.focus();
                    textarea.setSelectionRange(nextCaret, nextCaret);
                });
            },

            selectTemplate(template) {
                const textarea = this.$refs.composerTextarea;

                if (!textarea || this.triggerStart === null) {
                    return;
                }

                const caret = textarea.selectionStart ?? String(this.message ?? '').length;
                const before = String(this.message ?? '').slice(0, this.triggerStart);
                const after = String(this.message ?? '').slice(caret);
                const replacement = String(template.body ?? '').trim();
                const spacer = after === '' || after.startsWith(' ') || after.startsWith('\n') ? '' : ' ';
                const nextMessage = `${before}${replacement}${spacer}${after}`;
                const nextCaret = before.length + replacement.length + spacer.length;

                this.suppressNextRefresh = true;
                this.message = nextMessage;
                this.closeAutocompleteMenu();

                this.$nextTick(() => {
                    textarea.focus();
                    textarea.setSelectionRange(nextCaret, nextCaret);
                });
            },

            syncMentionIds() {
                if (!this.tracksMentions) {
                    return;
                }

                const currentIds = (this.mentionedIds ?? []).map((id) => Number(id));
                const nextIds = currentIds.filter((id) => {
                    const user = this.users.find((candidate) => Number(candidate.id) === id);

                    return user && this.messageIncludesUser(user);
                });

                if (nextIds.length !== currentIds.length || nextIds.some((id, index) => id !== currentIds[index])) {
                    this.mentionedIds = nextIds;
                }
            },

            messageIncludesUser(user) {
                return String(this.message ?? '').includes(`@${user.name}`);
            },

            closeAutocompleteMenu() {
                this.autocompleteOpen = false;
                this.autocompleteQuery = '';
                this.activeAutocompleteIndex = 0;
                this.triggerStart = null;
            },

            normalizeText(value) {
                return String(value ?? '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .trim();
            },

            previewText(value) {
                const text = String(value ?? '').replace(/\s+/g, ' ').trim();

                return text.length > 90 ? `${text.slice(0, 87)}...` : text;
            },

            initials(name) {
                return String(name ?? '')
                    .split(/\s+/)
                    .filter(Boolean)
                    .slice(0, 2)
                    .map((part) => part.charAt(0).toUpperCase())
                    .join('');
            },
        };
    };
}

function onlyDigits(value) {
    return value.replace(/\D/g, '');
}

function normalizeText(value) {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

function applyPhoneMask(value) {
    const digits = onlyDigits(value).slice(0, 11);

    if (digits.length <= 2) {
        return digits;
    }

    if (digits.length <= 6) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    }

    if (digits.length <= 10) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    }

    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

function applyCpfCnpjMask(value) {
    const digits = onlyDigits(value).slice(0, 14);

    if (digits.length <= 11) {
        return digits
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1-$2');
    }

    return digits
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2')
        .replace(/(\d{4})(\d)/, '$1-$2');
}

function applyCepMask(value) {
    const digits = onlyDigits(value).slice(0, 8);

    if (digits.length <= 5) {
        return digits;
    }

    return `${digits.slice(0, 5)}-${digits.slice(5)}`;
}

function applyPlateMask(value) {
    const normalized = value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);

    if (normalized.length <= 3) {
        return normalized;
    }

    return `${normalized.slice(0, 3)}-${normalized.slice(3)}`;
}

function inferMaskFromMetadata(input) {
    const explicitMask = input.dataset.mask;

    if (explicitMask && explicitMask !== 'auto') {
        return explicitMask;
    }

    const metadata = normalizeText([
        input.name,
        input.id,
        input.placeholder,
        input.dataset.maskLabel,
        input.dataset.maskPlaceholder,
    ].filter(Boolean).join(' '));

    if (/(telefone|celular|whatsapp|fone|contato)/.test(metadata)) {
        return 'phone';
    }

    if (/(cpf\/cnpj|cpf cnpj|cpf|cnpj|documento)/.test(metadata)) {
        return 'cpf-cnpj';
    }

    if (/\bcep\b|codigo postal|endereco/.test(metadata)) {
        return 'cep';
    }

    if (/\bplaca\b|veiculo/.test(metadata)) {
        return 'plate';
    }

    return null;
}

function applyInputMasks() {
    document.querySelectorAll('input[data-mask]').forEach((input) => {
        const applyMask = () => {
            switch (inferMaskFromMetadata(input)) {
                case 'phone':
                    input.value = applyPhoneMask(input.value);
                    break;
                case 'cpf-cnpj':
                    input.value = applyCpfCnpjMask(input.value);
                    break;
                case 'cep':
                    input.value = applyCepMask(input.value);
                    break;
                case 'plate':
                    input.value = applyPlateMask(input.value);
                    break;
                default:
                    break;
            }
        };

        if (input.dataset.maskBound === 'true') {
            applyMask();
            return;
        }

        input.dataset.maskBound = 'true';
        input.addEventListener('input', applyMask);
        input.addEventListener('blur', applyMask);
        applyMask();
    });
}

function themeIsDark() {
    return document.documentElement.classList.contains('dark');
}

function applyChartTheme(config) {
    const isDark = themeIsDark();
    const axisGridColor = isDark ? 'rgba(94, 118, 180, 0.18)' : 'rgba(148, 163, 184, 0.14)';
    const axisTextColor = isDark ? '#c7d6fb' : '#5f729d';

    Chart.defaults.color = axisTextColor;
    Chart.defaults.borderColor = axisGridColor;

    config.options ??= {};
    config.options.plugins ??= {};
    config.options.plugins.legend ??= {};
    config.options.plugins.legend.labels ??= {};
    config.options.plugins.legend.labels.color ??= axisTextColor;

    config.options.plugins.tooltip ??= {};
    config.options.plugins.tooltip.backgroundColor ??= isDark ? 'rgba(9, 18, 39, 0.96)' : 'rgba(255, 255, 255, 0.96)';
    config.options.plugins.tooltip.titleColor ??= isDark ? '#eef4ff' : '#243b74';
    config.options.plugins.tooltip.bodyColor ??= isDark ? '#d2ddff' : '#5b6f98';
    config.options.plugins.tooltip.borderColor ??= isDark ? 'rgba(86, 111, 172, 0.28)' : 'rgba(203, 213, 225, 0.65)';
    config.options.plugins.tooltip.borderWidth ??= 1;

    if (config.options.scales) {
        Object.values(config.options.scales).forEach((scale) => {
            scale.ticks ??= {};
            scale.grid ??= {};
            scale.ticks.color ??= axisTextColor;
            scale.grid.color ??= axisGridColor;
        });
    }

    return config;
}

function destroyMissingCharts() {
    const availableKeys = new Set(
        Array.from(document.querySelectorAll('canvas[data-chart-key]')).map((canvas) => canvas.dataset.chartKey),
    );

    for (const [key, chart] of activeCharts.entries()) {
        if (!availableKeys.has(key)) {
            chart.destroy();
            activeCharts.delete(key);
        }
    }
}

function initializeCharts() {
    document.querySelectorAll('canvas[data-chart]').forEach((canvas, index) => {
        if (!canvas.dataset.chartKey) {
            canvas.dataset.chartKey = `chart-${window.location.pathname}-${index}`;
        }

        const context = canvas.getContext('2d');

        if (!context) {
            return;
        }

        if (activeCharts.has(canvas.dataset.chartKey)) {
            activeCharts.get(canvas.dataset.chartKey)?.destroy();
            activeCharts.delete(canvas.dataset.chartKey);
        }

        const config = applyChartTheme(JSON.parse(canvas.dataset.chart));
        activeCharts.set(canvas.dataset.chartKey, new Chart(context, config));
    });

    destroyMissingCharts();
}

const confirmationState = {
    dialog: null,
    lastFocusedElement: null,
    resolver: null,
};

function confirmationIcon(variant) {
    const icons = {
        danger: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6m-8 4h10m-9 0 .7 12.2A2 2 0 0 0 10.7 21h2.6a2 2 0 0 0 2-1.8L16 7M10 11v6m4-6v6"/></svg>',
        warning: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.8 19a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L12 3Zm0 6v5m0 4h.01"/></svg>',
        success: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m20 6-11 11-5-5"/></svg>',
        info: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17v-6m0-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
    };

    return icons[variant] ?? icons.info;
}

function ensureConfirmationDialog() {
    if (confirmationState.dialog) {
        return confirmationState.dialog;
    }

    const dialog = document.createElement('div');
    dialog.className = 'ui-confirmation';
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-labelledby', 'ui-confirmation-title');
    dialog.setAttribute('aria-describedby', 'ui-confirmation-message');
    dialog.hidden = true;
    dialog.innerHTML = `
        <div class="ui-confirmation__backdrop" data-confirm-cancel></div>
        <div class="ui-confirmation__panel" role="document">
            <button type="button" class="ui-confirmation__close" aria-label="Fechar" data-confirm-cancel>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12"/></svg>
            </button>
            <div class="ui-confirmation__icon" data-confirm-icon></div>
            <h2 id="ui-confirmation-title" class="ui-confirmation__title"></h2>
            <p id="ui-confirmation-message" class="ui-confirmation__message"></p>
            <div class="ui-confirmation__actions">
                <button type="button" class="ui-confirmation__button ui-confirmation__button--cancel" data-confirm-cancel>Cancelar</button>
                <button type="button" class="ui-confirmation__button ui-confirmation__button--confirm" data-confirm-accept>Continuar</button>
            </div>
        </div>
    `;

    dialog.addEventListener('click', (event) => {
        if (event.target.closest('[data-confirm-cancel]')) {
            resolveConfirmation(false);
        }

        if (event.target.closest('[data-confirm-accept]')) {
            resolveConfirmation(true);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!dialog.hidden && event.key === 'Escape') {
            event.preventDefault();
            resolveConfirmation(false);
        }
    });

    document.body.append(dialog);
    confirmationState.dialog = dialog;

    return dialog;
}

function resolveConfirmation(confirmed) {
    const dialog = confirmationState.dialog;

    if (!dialog || dialog.hidden) {
        return;
    }

    dialog.hidden = true;
    document.documentElement.classList.remove('ui-confirmation-open');

    const resolver = confirmationState.resolver;
    confirmationState.resolver = null;
    confirmationState.lastFocusedElement?.focus?.();
    confirmationState.lastFocusedElement = null;
    resolver?.(confirmed);
}

function showConfirmation(options = {}) {
    const dialog = ensureConfirmationDialog();
    const variant = options.variant || 'info';

    dialog.dataset.variant = variant;
    dialog.querySelector('[data-confirm-icon]').innerHTML = confirmationIcon(variant);
    dialog.querySelector('#ui-confirmation-title').textContent = options.title || 'Confirmar ação?';
    dialog.querySelector('#ui-confirmation-message').textContent = options.message || 'Deseja continuar?';
    dialog.querySelector('.ui-confirmation__button--cancel').textContent = options.cancelLabel || 'Cancelar';
    dialog.querySelector('[data-confirm-accept]').textContent = options.confirmLabel || 'Continuar';

    confirmationState.lastFocusedElement = document.activeElement;
    dialog.hidden = false;
    document.documentElement.classList.add('ui-confirmation-open');

    requestAnimationFrame(() => {
        dialog.querySelector('[data-confirm-accept]')?.focus();
    });

    return new Promise((resolve) => {
        confirmationState.resolver = resolve;
    });
}

function confirmationOptionsFrom(element) {
    return {
        title: element.dataset.confirmTitle,
        message: element.dataset.confirmMessage || element.dataset.confirm,
        variant: element.dataset.confirmVariant,
        confirmLabel: element.dataset.confirmLabel,
        cancelLabel: element.dataset.confirmCancelLabel,
    };
}

const confirmedSubmissions = new WeakSet();
const confirmedClicks = new WeakSet();

document.addEventListener('submit', async (event) => {
    const form = event.target.closest?.('form[data-confirm]');

    if (!form || confirmedSubmissions.has(form)) {
        confirmedSubmissions.delete(form);
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    if (!await showConfirmation(confirmationOptionsFrom(form))) {
        return;
    }

    confirmedSubmissions.add(form);

    if (event.submitter && typeof form.requestSubmit === 'function') {
        form.requestSubmit(event.submitter);
        return;
    }

    form.submit();
}, true);

document.addEventListener('click', async (event) => {
    const trigger = event.target.closest?.('[data-confirm]');

    if (!trigger || trigger.matches('form')) {
        return;
    }

    if (confirmedClicks.has(trigger)) {
        confirmedClicks.delete(trigger);
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    if (!await showConfirmation(confirmationOptionsFrom(trigger))) {
        return;
    }

    confirmedClicks.add(trigger);
    trigger.dispatchEvent(new MouseEvent('click', {
        bubbles: true,
        cancelable: true,
        view: window,
    }));
}, true);

window.appConfirm = showConfirmation;

document.addEventListener('DOMContentLoaded', initializeCharts);
document.addEventListener('DOMContentLoaded', applyInputMasks);
document.addEventListener('DOMContentLoaded', initializePortalSidebar);
document.addEventListener('DOMContentLoaded', initializeEnhancedSelects);
document.addEventListener('livewire:navigated', () => {
    requestAnimationFrame(initializeCharts);
    requestAnimationFrame(applyInputMasks);
    requestAnimationFrame(initializePortalSidebar);
    requestAnimationFrame(initializeEnhancedSelects);
});
window.addEventListener('theme-preference-changed', () => requestAnimationFrame(initializeCharts));
