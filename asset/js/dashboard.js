const balanceToggle = document.getElementById('toggleBalance');
const balanceValue = document.getElementById('balanceAmount');

if (balanceToggle && balanceValue) {
    const visibleBalance = balanceValue.textContent;
    balanceToggle.addEventListener('click', () => {
        const willHide = balanceToggle.textContent === 'Hide';
        balanceValue.textContent = willHide ? '₦••••••••' : visibleBalance;
        balanceToggle.textContent = willHide ? 'Show' : 'Hide';
        balanceToggle.setAttribute('aria-label', willHide ? 'Show wallet balance' : 'Hide wallet balance');
        balanceToggle.title = balanceToggle.getAttribute('aria-label');
    });
}
