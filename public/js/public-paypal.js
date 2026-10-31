(async () => {
    const container = document.getElementById('paypal-checkout');
    if (!container) return;
    const feedback = document.getElementById('payment-feedback');
    const button = document.getElementById('paypal-button');
    const guestButton = document.getElementById('guest-card-button');
    const setBusy = value => { button.disabled = value; guestButton.disabled = value; };
    const message = () => { feedback.textContent = 'No pudimos confirmar el pago. Consulta el estado del enlace antes de volver a intentarlo o contacta con Quantum.'; };
    const post = async (url, body = {}) => {
        const response = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify(body)});
        if (!response.ok) throw new Error('Payment request unavailable');
        return response.json();
    };
    try {
        const sdk = await window.paypal.createInstance({clientId: container.dataset.clientId, components: ['paypal-payments', 'paypal-guest-payments'], pageType: 'checkout', locale: 'es-ES'});
        const methods = await sdk.findEligibleMethods({currencyCode: 'USD', amount: container.dataset.amount});
        if (!methods.isEligible('paypal') && !methods.isEligible('card')) { feedback.textContent = 'PayPal no está disponible para este pago. Contacta con Quantum.'; return; }
        const callbacks = {
            async onApprove(data) { setBusy(true); feedback.textContent = 'Confirmando el pago…'; try { const result = await post(container.dataset.captureUrl, {order_id: data.orderId}); if (result.paid) window.location.reload(); else message(); } catch { message(); } finally {setBusy(false);} },
            onCancel() { setBusy(false); feedback.textContent = 'Pago cancelado. Puedes intentarlo mientras el enlace siga vigente.'; },
            onError() { setBusy(false); message(); },
            onWarn() { feedback.textContent = 'Revisa los datos en el checkout de PayPal e inténtalo nuevamente.'; }
        };
        const connect = (control, session) => control.addEventListener('click', async () => {
            setBusy(true);
            try { await session.start({presentationMode: 'auto', targetElement: control}, post(container.dataset.orderUrl).then(data => ({orderId: data.id}))); } catch { message(); } finally { setBusy(false); }
        });
        if (methods.isEligible('paypal')) {
            const session = sdk.createPayPalOneTimePaymentSession(callbacks);
            if (session.hasReturned()) { await session.resume(); return; }
            button.hidden = false; connect(button, session);
            document.getElementById('paypal-method').hidden = false;
        }
        if (methods.isEligible('card')) {
            document.getElementById('guest-card-container').hidden = false;
            document.getElementById('card-method').hidden = false;
            connect(guestButton, sdk.createPayPalGuestOneTimePaymentSession(callbacks));
        }
        feedback.textContent = '';
    } catch { message(); }
})();
