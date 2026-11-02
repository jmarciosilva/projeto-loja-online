import test from 'node:test';
import assert from 'node:assert/strict';
import { BACK_TO_TOP_THRESHOLD, createBackToTop } from '../../resources/js/storefront/back-to-top.js';

function fakeBrowser({ width = 390, reducedMotion = false, scrollY = 0 } = {}) {
    const listeners = new Map();
    const motionListeners = new Set();
    const calls = [];
    const motionQuery = {
        matches: reducedMotion,
        addEventListener: (_event, callback) => motionListeners.add(callback),
        removeEventListener: (_event, callback) => motionListeners.delete(callback),
    };

    return {
        innerWidth: width,
        scrollY,
        calls,
        listeners,
        motionListeners,
        matchMedia: (query) => {
            assert.equal(query, '(prefers-reduced-motion: reduce)');

            return motionQuery;
        },
        addEventListener: (event, callback, options) => listeners.set(event, { callback, options }),
        removeEventListener: (event, callback) => {
            if (listeners.get(event)?.callback === callback) listeners.delete(event);
        },
        scrollTo: (options) => calls.push(options),
        setReducedMotion(value) {
            motionQuery.matches = value;
            motionListeners.forEach((callback) => callback({ matches: value }));
        },
        scrollToPosition(value) {
            this.scrollY = value;
            listeners.get('scroll')?.callback();
        },
    };
}

test('starts hidden and appears only after the scroll threshold on mobile and desktop', () => {
    for (const width of [360, 390, 768, 1440]) {
        const browser = fakeBrowser({ width });
        const button = createBackToTop(browser);

        button.init();
        assert.equal(button.visible, false, `initial state at ${width}px`);

        browser.scrollToPosition(BACK_TO_TOP_THRESHOLD);
        assert.equal(button.visible, false, `threshold state at ${width}px`);

        browser.scrollToPosition(BACK_TO_TOP_THRESHOLD + 1);
        assert.equal(button.visible, true, `scrolled state at ${width}px`);

        browser.scrollToPosition(0);
        assert.equal(button.visible, false, `top state at ${width}px`);
        button.destroy();
    }
});

test('returns to the top with smooth motion when allowed', () => {
    const browser = fakeBrowser();
    const button = createBackToTop(browser);
    button.init();

    button.goToTop();

    assert.deepEqual(browser.calls, [{ top: 0, behavior: 'smooth' }]);
    button.destroy();
});

test('uses immediate motion when reduced motion is preferred or enabled later', () => {
    const browser = fakeBrowser({ reducedMotion: true });
    const button = createBackToTop(browser);
    button.init();

    button.goToTop();
    browser.setReducedMotion(false);
    button.goToTop();
    browser.setReducedMotion(true);
    button.goToTop();

    assert.deepEqual(browser.calls, [
        { top: 0, behavior: 'auto' },
        { top: 0, behavior: 'smooth' },
        { top: 0, behavior: 'auto' },
    ]);
    button.destroy();
});

test('removes scroll and motion preference listeners when destroyed', () => {
    const browser = fakeBrowser();
    const button = createBackToTop(browser);
    button.init();

    assert.equal(browser.listeners.get('scroll').options.passive, true);
    assert.equal(browser.motionListeners.size, 1);

    button.destroy();

    assert.equal(browser.listeners.has('scroll'), false);
    assert.equal(browser.motionListeners.size, 0);
});
