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
document.addEventListener('livewire:navigated', () => {
    requestAnimationFrame(initializeCharts);
    requestAnimationFrame(applyInputMasks);
    requestAnimationFrame(initializePortalSidebar);
});
window.addEventListener('theme-preference-changed', () => requestAnimationFrame(initializeCharts));
