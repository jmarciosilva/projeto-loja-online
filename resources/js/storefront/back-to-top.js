export const BACK_TO_TOP_THRESHOLD = 400;

export function createBackToTop(browser) {
    return {
        visible: false,
        reducedMotion: false,
        scrollHandler: null,
        motionQuery: null,
        motionChange: null,

        init() {
            this.motionQuery = browser.matchMedia('(prefers-reduced-motion: reduce)');
            this.reducedMotion = this.motionQuery.matches;
            this.motionChange = (event) => { this.reducedMotion = event.matches; };
            this.motionQuery.addEventListener('change', this.motionChange);
            this.scrollHandler = () => this.updateVisibility();
            browser.addEventListener('scroll', this.scrollHandler, { passive: true });
            this.updateVisibility();
        },

        destroy() {
            browser.removeEventListener('scroll', this.scrollHandler);
            this.motionQuery?.removeEventListener('change', this.motionChange);
        },

        updateVisibility() {
            this.visible = browser.scrollY > BACK_TO_TOP_THRESHOLD;
        },

        goToTop() {
            browser.scrollTo({ top: 0, behavior: this.reducedMotion ? 'auto' : 'smooth' });
        },
    };
}
