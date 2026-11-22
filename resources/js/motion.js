/**
 * Motion directives (PRD: Animation guidelines).
 *
 *   x-reveal="80"        fade + rise when scrolled into view (optional delay in ms)   GSAP
 *   x-count-up="1234"    count a number up when visible (.currency for ₦)              GSAP
 *   x-pop / x-pop.click  quick scale pop on insert / on click                          Anime.js
 *   $pop(el)             imperative pop
 *
 * GSAP and Anime.js load on first use only, so pages without motion pay nothing.
 * Every effect stays under 300 ms, animates only transform/opacity, never blocks
 * a tap, and is skipped entirely under prefers-reduced-motion.
 */

const DURATION = 0.28;

export const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

let gsapLoader;
const loadGsap = () => (gsapLoader ??= import('gsap').then((m) => m.gsap ?? m.default));

let animeLoader;
// Subpath import keeps the chunk to the animation engine only.
const loadAnime = () => (animeLoader ??= import('animejs/animation'));

function whenVisible(el, callback) {
    if (!('IntersectionObserver' in window)) {
        callback();
        return () => {};
    }

    const observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                observer.disconnect();
                callback();
            }
        },
        { rootMargin: '0px 0px -8% 0px' },
    );

    observer.observe(el);

    return () => observer.disconnect();
}

export async function pop(el) {
    if (!el || prefersReducedMotion()) return;

    const { animate } = await loadAnime();

    animate(el, {
        scale: [
            { to: 1.16, duration: 110 },
            { to: 1, duration: 150 },
        ],
        ease: 'outQuad',
    });
}

export function registerMotion(Alpine) {
    Alpine.directive('reveal', (el, { expression }, { cleanup }) => {
        if (prefersReducedMotion()) return;

        // Blade renders a bare attribute as x-reveal="x-reveal", so only digits count as a delay.
        const delay = /^\d+$/.test(expression ?? '') ? Number(expression) / 1000 : 0;
        el.style.opacity = '0';

        cleanup(
            whenVisible(el, async () => {
                const gsap = await loadGsap();
                gsap.fromTo(
                    el,
                    { opacity: 0, y: 10 },
                    { opacity: 1, y: 0, duration: DURATION, delay, ease: 'power2.out', clearProps: 'transform,opacity' },
                );
            }),
        );
    });

    Alpine.directive('count-up', (el, { expression, modifiers }, { evaluate, cleanup }) => {
        const target = Number(evaluate(expression)) || 0;
        const format = new Intl.NumberFormat(
            'en-NG',
            modifiers.includes('currency')
                ? { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }
                : { maximumFractionDigits: modifiers.includes('decimal') ? 1 : 0 },
        );

        el.textContent = format.format(target);

        if (prefersReducedMotion() || target === 0) return;

        cleanup(
            whenVisible(el, async () => {
                const gsap = await loadGsap();
                const state = { value: 0 };

                gsap.to(state, {
                    value: target,
                    duration: DURATION,
                    ease: 'power1.out',
                    onUpdate: () => (el.textContent = format.format(state.value)),
                    onComplete: () => (el.textContent = format.format(target)),
                });
            }),
        );
    });

    Alpine.directive('pop', (el, { modifiers }, { cleanup }) => {
        if (modifiers.includes('click')) {
            const handler = () => pop(el);
            el.addEventListener('click', handler);
            cleanup(() => el.removeEventListener('click', handler));
            return;
        }

        pop(el);
    });

    Alpine.magic('pop', () => (el) => pop(el));
}
