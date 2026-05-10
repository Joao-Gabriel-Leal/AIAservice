import Chart from 'chart.js/auto';

import './echo';

const activeCharts = new Map();

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

document.addEventListener('DOMContentLoaded', initializeCharts);
document.addEventListener('DOMContentLoaded', applyInputMasks);
document.addEventListener('livewire:navigated', () => {
    requestAnimationFrame(initializeCharts);
    requestAnimationFrame(applyInputMasks);
});
window.addEventListener('theme-preference-changed', () => requestAnimationFrame(initializeCharts));
