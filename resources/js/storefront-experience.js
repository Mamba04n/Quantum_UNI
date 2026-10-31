const CONSENT_KEY = 'quantum.storefront.consent.v1';
const MAX_AGE = 180 * 24 * 60 * 60 * 1000;

function startPreferences() {
    const dialog = document.querySelector('[data-cookie-dialog]');
    if (!dialog || dialog.dataset.initialized) return;
    dialog.dataset.initialized = 'true';
    let consent = null;
    let opener = null;
    try {
        const saved = JSON.parse(localStorage.getItem(CONSENT_KEY));
        if (saved?.version === 1 && saved.necessary === true && typeof saved.external === 'boolean'
            && Number.isFinite(saved.savedAt) && saved.savedAt <= Date.now() && Date.now() - saved.savedAt < MAX_AGE) consent = saved;
    } catch { /* Invalid or unavailable storage leaves optional services disabled. */ }

    const external = dialog.querySelector('[data-cookie-external]');
    const apply = () => {
        const enabled = consent?.external === true;
        document.querySelectorAll('iframe[data-consent-src]').forEach(frame => {
            if (enabled && !frame.getAttribute('src')) frame.setAttribute('src', frame.dataset.consentSrc);
            if (!enabled) frame.removeAttribute('src');
            frame.hidden = !enabled;
        });
        document.querySelectorAll('[data-map-consent-placeholder]').forEach(element => element.hidden = enabled);
        window.dispatchEvent(new CustomEvent('quantum-consent-changed', { detail: { necessary: true, external: enabled } }));
    };
    const open = (settings = false) => {
        opener = document.activeElement;
        external.checked = consent?.external === true;
        dialog.querySelector('[data-cookie-close]').hidden = !consent;
        dialog.querySelector('[data-cookie-settings]').open = settings;
        if (!dialog.open) dialog.showModal();
        document.body.classList.add('cookie-choice-open');
    };
    const close = () => {
        dialog.close();
        document.body.classList.remove('cookie-choice-open');
        if (opener instanceof HTMLElement && opener.isConnected) opener.focus();
    };
    const save = allowed => {
        consent = { version: 1, necessary: true, external: allowed, savedAt: Date.now() };
        try { localStorage.setItem(CONSENT_KEY, JSON.stringify(consent)); } catch { /* Choice still applies to this page. */ }
        apply();
        close();
    };
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        if (consent) close();
    });
    dialog.querySelector('[data-cookie-close]').addEventListener('click', close);
    dialog.querySelector('[data-cookie-necessary]').addEventListener('click', () => save(false));
    dialog.querySelector('[data-cookie-accept]').addEventListener('click', () => save(true));
    dialog.querySelector('[data-cookie-save]').addEventListener('click', () => save(external.checked));
    document.addEventListener('click', event => {
        if (event.target.closest('[data-cookie-open]')) { event.preventDefault(); open(true); }
    });
    const observer = new MutationObserver(() => {
        document.querySelectorAll('iframe[data-consent-src]').forEach(frame => {
            const enabled = consent?.external === true;
            if (enabled && !frame.getAttribute('src')) frame.setAttribute('src', frame.dataset.consentSrc);
            frame.hidden = !enabled;
        });
        document.querySelectorAll('[data-map-consent-placeholder]').forEach(element => element.hidden = consent?.external === true);
    });
    observer.observe(document.body, { childList: true, subtree: true });
    apply();
    if (!consent && dialog.dataset.policyPage !== 'true') open();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startPreferences);
else startPreferences();

function startStorefrontContact() {
    const quoteSteps = document.querySelector('[data-quote-steps]');
    if (quoteSteps && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) {
                quoteSteps.classList.add('is-visible');
                observer.disconnect();
            }
        }, { threshold: 0.15 });
        observer.observe(quoteSteps);
    }
    document.querySelectorAll('[data-brand-marquee]').forEach(section => {
        const button = section.querySelector('[data-brand-pause]');
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) button.hidden = true;
        button.addEventListener('click', () => {
            const paused = section.classList.toggle('is-paused');
            button.setAttribute('aria-pressed', String(paused));
            button.textContent = paused ? 'Continuar movimiento' : 'Pausar movimiento';
        });
    });
    const bubble = document.querySelector('[data-whatsapp-bubble]');
    if (!bubble || bubble.dataset.initialized) return;
    bubble.dataset.initialized = 'true';
    const key = 'quantum.whatsapp.message-dismissed';
    let closed = false;
    try { closed = sessionStorage.getItem(key) === '1'; } catch { /* Optional storage. */ }
    const phrases = ['¿Encontraste lo que buscabas?', '¿Necesitas ayuda con una pieza?', 'Envíanos una foto o el número de parte.'];
    let index = 0;
    let timer;
    const show = () => {
        if (closed) return;
        if (document.hidden || document.querySelector('dialog[open]') || document.body.classList.contains('menu-open')) {
            timer = setTimeout(show, 3000);
            return;
        }
        bubble.querySelector('[data-whatsapp-message]').textContent = phrases[index++ % phrases.length];
        bubble.hidden = false;
        timer = setTimeout(show, 14000);
    };
    bubble.querySelector('[data-whatsapp-close]').addEventListener('click', () => {
        closed = true;
        clearTimeout(timer);
        bubble.hidden = true;
        try { sessionStorage.setItem(key, '1'); } catch { /* Dismissal still applies to this page. */ }
    });
    if (!closed) timer = setTimeout(show, 6000);
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startStorefrontContact);
else startStorefrontContact();
