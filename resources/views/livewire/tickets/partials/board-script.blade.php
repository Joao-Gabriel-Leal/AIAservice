@push('scripts')
    <script>
        if (! window.ticketBoard) {
            window.ticketBoard = function (config) {
                return {
                    selectedSectorId: config.selectedSectorId,
                    selectedBoardId: config.selectedBoardId,
                    viewMode: config.viewMode,
                    dragThreshold: 10,
                    drag: {
                        active: false,
                        pending: false,
                        ticketId: null,
                        sourceGroupId: null,
                        targetGroupId: null,
                        targetBeforeTicketId: null,
                        targetPlacement: 'top',
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

                        this.$watch('selectedBoardId', () => {
                            this.cancelPointerDrag();
                            this.restoreViewMode();
                        });

                        this.$watch('viewMode', (value) => {
                            if (! ['kanban', 'stages'].includes(value)) {
                                this.cancelPointerDrag();
                            }

                            this.persistViewMode(value);
                        });
                    },

                    storageKey() {
                        if (this.selectedBoardId) {
                            return `tickets-board-view:board:${this.selectedBoardId}`;
                        }

                        return this.selectedSectorId ? `tickets-board-view:sector:${this.selectedSectorId}` : null;
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
                            targetBeforeTicketId: null,
                            targetPlacement: 'top',
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
                        if (! ['kanban', 'stages'].includes(this.viewMode)) {
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

                    resolveDropGroup(dropZone) {
                        const rawGroupId = dropZone?.dataset.boardGroupId;

                        return rawGroupId === '__null__'
                            ? null
                            : this.normalizeGroupValue(rawGroupId);
                    },

                    resolveDropTarget(x, y) {
                        if (! Number.isFinite(x) || ! Number.isFinite(y)) {
                            return undefined;
                        }

                        const element = document.elementFromPoint(x, y);
                        const dropZone = element?.closest('[data-board-drop-zone]');

                        if (! dropZone) {
                            return undefined;
                        }

                        const groupId = this.resolveDropGroup(dropZone);
                        const placeholder = element?.closest('[data-board-drop-placement]');

                        if (placeholder && dropZone.contains(placeholder)) {
                            return {
                                groupId,
                                beforeTicketId: this.normalizeGroupValue(placeholder.dataset.boardDropBeforeTicketId),
                                placement: placeholder.dataset.boardDropPlacement ?? 'top',
                            };
                        }

                        const ticketElements = Array.from(dropZone.querySelectorAll('[data-board-ticket-id]'))
                            .filter((ticketEl) => this.normalizeGroupValue(ticketEl.dataset.boardTicketId) !== this.drag.ticketId);

                        const hoveredTicket = element?.closest('[data-board-ticket-id]');
                        const hoveredTicketId = this.normalizeGroupValue(hoveredTicket?.dataset.boardTicketId);

                        if (hoveredTicket && dropZone.contains(hoveredTicket) && hoveredTicketId !== this.drag.ticketId) {
                            const hoveredIndex = ticketElements.findIndex((ticketEl) => {
                                return this.normalizeGroupValue(ticketEl.dataset.boardTicketId) === hoveredTicketId;
                            });

                            if (hoveredIndex !== -1) {
                                const hoveredRect = hoveredTicket.getBoundingClientRect();
                                const isUpperHalf = y <= hoveredRect.top + (hoveredRect.height / 2);

                                if (isUpperHalf) {
                                    return {
                                        groupId,
                                        beforeTicketId: hoveredTicketId,
                                        placement: 'before',
                                    };
                                }

                                const nextTicketEl = ticketElements[hoveredIndex + 1] ?? null;

                                return {
                                    groupId,
                                    beforeTicketId: nextTicketEl
                                        ? this.normalizeGroupValue(nextTicketEl.dataset.boardTicketId)
                                        : null,
                                    placement: nextTicketEl ? 'before' : 'end',
                                };
                            }
                        }

                        const firstTicketEl = ticketElements[0] ?? null;

                        return {
                            groupId,
                            beforeTicketId: firstTicketEl
                                ? this.normalizeGroupValue(firstTicketEl.dataset.boardTicketId)
                                : null,
                            placement: firstTicketEl ? 'top' : 'top',
                        };
                    },

                    refreshDragTarget() {
                        if (! this.drag.active) {
                            return;
                        }

                        const target = this.resolveDropTarget(this.drag.currentX, this.drag.currentY);

                        if (typeof target === 'undefined') {
                            this.drag.targetGroupId = undefined;
                            this.drag.targetBeforeTicketId = null;
                            this.drag.targetPlacement = 'top';

                            return;
                        }

                        this.drag.targetGroupId = target.groupId;
                        this.drag.targetBeforeTicketId = target.beforeTicketId ?? null;
                        this.drag.targetPlacement = target.placement ?? 'top';
                    },

                    isDragTarget(groupId) {
                        if (! this.drag.active || typeof this.drag.targetGroupId === 'undefined') {
                            return false;
                        }

                        return this.areSameGroupId(this.normalizeGroupValue(groupId), this.drag.targetGroupId);
                    },

                    isDropIndicator(groupId, beforeTicketId) {
                        if (! this.isDragTarget(groupId)) {
                            return false;
                        }

                        return this.drag.targetBeforeTicketId === beforeTicketId;
                    },

                    isDropAtEnd(groupId) {
                        if (! this.isDragTarget(groupId)) {
                            return false;
                        }

                        return this.drag.targetBeforeTicketId === null && this.drag.targetPlacement === 'end';
                    },

                    isDropAtEmpty(groupId) {
                        if (! this.isDragTarget(groupId)) {
                            return false;
                        }

                        return this.drag.targetBeforeTicketId === null && this.drag.targetPlacement === 'top';
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
                        const target = this.resolveDropTarget(this.drag.currentX, this.drag.currentY);

                        this.cancelPointerDrag();

                        if (ticketId === null || typeof target === 'undefined') {
                            return;
                        }

                        this.$wire.moveTicketByDrag(
                            ticketId,
                            target.groupId,
                            target.beforeTicketId,
                            target.placement === 'end' ? 'end' : 'top',
                        );
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
