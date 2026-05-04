@push('scripts')
    <script>
        if (! window.ticketBoard) {
            window.ticketBoard = function (config) {
                return {
                    selectedSectorId: config.selectedSectorId,
                    viewMode: config.viewMode,
                    dragThreshold: 10,
                    drag: {
                        active: false,
                        pending: false,
                        ticketId: null,
                        sourceGroupId: null,
                        targetGroupId: null,
                        pointerId: null,
                        startX: 0,
                        startY: 0,
                        currentX: 0,
                        currentY: 0,
                        offsetX: 0,
                        offsetY: 0,
                        cardEl: null,
                        previewEl: null,
                    },

                    init() {
                        this.restoreViewMode();

                        this.$watch('selectedSectorId', () => {
                            this.cancelPointerDrag();
                            this.restoreViewMode();
                        });

                        this.$watch('viewMode', (value) => {
                            if (value !== 'kanban') {
                                this.cancelPointerDrag();
                            }

                            this.persistViewMode(value);
                        });
                    },

                    storageKey() {
                        return this.selectedSectorId ? `tickets-board-view:${this.selectedSectorId}` : null;
                    },

                    restoreViewMode() {
                        const key = this.storageKey();

                        if (! key) {
                            return;
                        }

                        let storedMode = null;

                        try {
                            storedMode = window.localStorage.getItem(key);
                        } catch (error) {
                            return;
                        }

                        const nextMode = ['list', 'stages', 'kanban'].includes(storedMode) ? storedMode : 'list';

                        if (this.viewMode !== nextMode) {
                            this.$wire.setViewMode(nextMode);
                        } else {
                            this.persistViewMode(nextMode);
                        }
                    },

                    persistViewMode(value) {
                        if (! ['list', 'stages', 'kanban'].includes(value)) {
                            return;
                        }

                        const key = this.storageKey();

                        if (! key) {
                            return;
                        }

                        try {
                            window.localStorage.setItem(key, value);
                        } catch (error) {
                            return;
                        }
                    },

                    normalizeGroupValue(rawValue) {
                        if (rawValue === '' || rawValue === null || typeof rawValue === 'undefined') {
                            return null;
                        }

                        if (typeof rawValue === 'number' && Number.isInteger(rawValue)) {
                            return rawValue;
                        }

                        if (typeof rawValue === 'string' && /^\d+$/.test(rawValue)) {
                            return Number.parseInt(rawValue, 10);
                        }

                        return rawValue;
                    },

                    resetDragState() {
                        this.drag = {
                            active: false,
                            pending: false,
                            ticketId: null,
                            sourceGroupId: null,
                            targetGroupId: null,
                            pointerId: null,
                            startX: 0,
                            startY: 0,
                            currentX: 0,
                            currentY: 0,
                            offsetX: 0,
                            offsetY: 0,
                            cardEl: null,
                            previewEl: null,
                        };
                    },

                    changeGroup(ticketId, rawValue) {
                        this.cancelPointerDrag();
                        this.$wire.moveTicketToGroup(ticketId, this.normalizeGroupValue(rawValue));
                    },

                    areSameGroupId(left, right) {
                        return (left ?? null) === (right ?? null);
                    },

                    isInteractiveTarget(target) {
                        return Boolean(target?.closest('a, button, input, select, textarea, label, [data-no-drag]'));
                    },

                    beginPointerDrag(event, ticketId, groupId) {
                        if (this.viewMode !== 'kanban') {
                            return;
                        }

                        if (this.isInteractiveTarget(event.target)) {
                            return;
                        }

                        if (event.pointerType === 'mouse' && event.button !== 0) {
                            return;
                        }

                        this.cancelPointerDrag();

                        const cardEl = event.currentTarget;
                        const cardRect = cardEl.getBoundingClientRect();

                        this.drag.pending = true;
                        this.drag.ticketId = ticketId;
                        this.drag.sourceGroupId = this.normalizeGroupValue(groupId);
                        this.drag.targetGroupId = this.normalizeGroupValue(groupId);
                        this.drag.pointerId = event.pointerId ?? null;
                        this.drag.startX = event.clientX;
                        this.drag.startY = event.clientY;
                        this.drag.currentX = event.clientX;
                        this.drag.currentY = event.clientY;
                        this.drag.offsetX = event.clientX - cardRect.left;
                        this.drag.offsetY = event.clientY - cardRect.top;
                        this.drag.cardEl = cardEl;

                        if (typeof cardEl.setPointerCapture === 'function' && this.drag.pointerId !== null) {
                            try {
                                cardEl.setPointerCapture(this.drag.pointerId);
                            } catch (error) {
                                // Some browsers clear pointer capture automatically during touch gestures.
                            }
                        }
                    },

                    activatePointerDrag() {
                        if (! this.drag.pending || this.drag.active || ! this.drag.cardEl) {
                            return;
                        }

                        const previewEl = this.drag.cardEl.cloneNode(true);
                        const cardRect = this.drag.cardEl.getBoundingClientRect();

                        previewEl.classList.add('ui-kanban-drag-preview');
                        previewEl.classList.remove('ui-kanban-card-dragging', 'ui-kanban-card-lifted');
                        previewEl.setAttribute('aria-hidden', 'true');
                        previewEl.style.width = `${cardRect.width}px`;
                        previewEl.style.height = `${cardRect.height}px`;
                        previewEl.style.left = '0';
                        previewEl.style.top = '0';
                        previewEl.style.margin = '0';

                        this.$refs.dragLayer.replaceChildren(previewEl);
                        this.$refs.dragLayer.classList.remove('hidden');

                        this.drag.previewEl = previewEl;
                        this.drag.active = true;

                        document.body.classList.add('ui-kanban-drag-active');

                        this.updatePreviewPosition();
                        this.refreshDragTarget();
                    },

                    updatePointerDrag(event) {
                        if (! this.drag.pending && ! this.drag.active) {
                            return;
                        }

                        if (this.drag.pointerId !== null && event.pointerId !== this.drag.pointerId) {
                            return;
                        }

                        this.drag.currentX = event.clientX;
                        this.drag.currentY = event.clientY;

                        if (! this.drag.active) {
                            const deltaX = this.drag.currentX - this.drag.startX;
                            const deltaY = this.drag.currentY - this.drag.startY;

                            if (Math.hypot(deltaX, deltaY) < this.dragThreshold) {
                                return;
                            }

                            this.activatePointerDrag();
                        }

                        event.preventDefault?.();

                        this.updatePreviewPosition();
                        this.refreshDragTarget();
                    },

                    updatePreviewPosition() {
                        if (! this.drag.previewEl) {
                            return;
                        }

                        this.drag.previewEl.style.transform = `translate3d(${this.drag.currentX - this.drag.offsetX}px, ${this.drag.currentY - this.drag.offsetY}px, 0) rotate(1.5deg) scale(1.01)`;
                    },

                    resolveDropGroupFromPoint(x, y) {
                        if (! Number.isFinite(x) || ! Number.isFinite(y)) {
                            return undefined;
                        }

                        const columnEl = document.elementFromPoint(x, y)?.closest('[data-kanban-column]');

                        if (! columnEl) {
                            return undefined;
                        }

                        const rawGroupId = columnEl.dataset.kanbanGroupId;

                        return rawGroupId === '__null__'
                            ? null
                            : this.normalizeGroupValue(rawGroupId);
                    },

                    refreshDragTarget() {
                        if (! this.drag.active) {
                            return;
                        }

                        this.drag.targetGroupId = this.resolveDropGroupFromPoint(this.drag.currentX, this.drag.currentY);
                    },

                    isDragTarget(groupId) {
                        if (! this.drag.active || typeof this.drag.targetGroupId === 'undefined') {
                            return false;
                        }

                        return this.areSameGroupId(this.normalizeGroupValue(groupId), this.drag.targetGroupId);
                    },

                    isDraggingTicket(ticketId) {
                        return this.drag.active && this.drag.ticketId === ticketId;
                    },

                    isPointerCandidate(ticketId) {
                        return this.drag.pending && ! this.drag.active && this.drag.ticketId === ticketId;
                    },

                    cleanupPreview() {
                        if (this.drag.previewEl) {
                            this.drag.previewEl.remove();
                        }

                        if (this.$refs.dragLayer) {
                            this.$refs.dragLayer.replaceChildren();
                            this.$refs.dragLayer.classList.add('hidden');
                        }
                    },

                    endPointerDrag(event) {
                        if (! this.drag.pending && ! this.drag.active) {
                            return;
                        }

                        if (this.drag.pointerId !== null && event.pointerId !== this.drag.pointerId) {
                            return;
                        }

                        if (typeof event.clientX === 'number') {
                            this.drag.currentX = event.clientX;
                            this.drag.currentY = event.clientY;
                        }

                        if (! this.drag.active) {
                            this.cancelPointerDrag();

                            return;
                        }

                        const ticketId = this.drag.ticketId;
                        const targetGroupId = this.resolveDropGroupFromPoint(this.drag.currentX, this.drag.currentY);
                        const sourceGroupId = this.drag.sourceGroupId;

                        this.cancelPointerDrag();

                        if (ticketId === null || typeof targetGroupId === 'undefined' || this.areSameGroupId(targetGroupId, sourceGroupId)) {
                            return;
                        }

                        this.$wire.moveTicketToGroup(ticketId, targetGroupId);
                    },

                    cancelPointerDrag() {
                        if (this.drag.cardEl && this.drag.pointerId !== null && typeof this.drag.cardEl.hasPointerCapture === 'function' && this.drag.cardEl.hasPointerCapture(this.drag.pointerId)) {
                            try {
                                this.drag.cardEl.releasePointerCapture(this.drag.pointerId);
                            } catch (error) {
                                // Capture may already be released by the browser.
                            }
                        }

                        document.body.classList.remove('ui-kanban-drag-active');

                        this.cleanupPreview();
                        this.resetDragState();
                    },
                };
            };
        }
    </script>
@endpush
