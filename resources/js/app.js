import './bootstrap';
import { createBackToTop } from './storefront/back-to-top';

document.addEventListener('alpine:init', () => {
    Alpine.data('heroCarousel', (slideCount) => ({
        slideCount,
        current: 0,
        pausedByUser: false,
        hovered: false,
        focused: false,
        reducedMotion: false,
        timer: null,
        motionQuery: null,
        motionChange: null,

        init() {
            this.motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
            this.reducedMotion = this.motionQuery.matches;
            this.motionChange = (event) => {
                this.reducedMotion = event.matches;
                this.syncTimer();
            };
            this.motionQuery.addEventListener('change', this.motionChange);
            this.syncTimer();
        },

        destroy() {
            this.stopTimer();
            this.motionQuery?.removeEventListener('change', this.motionChange);
        },

        get canAdvanceAutomatically() {
            return this.slideCount > 1 && !this.pausedByUser && !this.hovered && !this.focused && !this.reducedMotion;
        },

        syncTimer() {
            this.stopTimer();
            if (this.canAdvanceAutomatically) {
                this.timer = window.setInterval(() => this.advance(1, false), 5000);
            }
        },

        stopTimer() {
            if (this.timer !== null) {
                window.clearInterval(this.timer);
                this.timer = null;
            }
        },

        setHovered(value) {
            this.hovered = value;
            this.syncTimer();
        },

        setFocused(value) {
            this.focused = value;
            this.syncTimer();
        },

        togglePause() {
            this.pausedByUser = !this.pausedByUser;
            this.syncTimer();
        },

        advance(amount, manual = true) {
            this.current = (this.current + amount + this.slideCount) % this.slideCount;
            if (manual) {
                this.pausedByUser = true;
                this.syncTimer();
            }
        },

        goTo(index) {
            this.current = index;
            this.pausedByUser = true;
            this.syncTimer();
        },
    }));

    Alpine.data('backToTop', () => createBackToTop(window));
});
