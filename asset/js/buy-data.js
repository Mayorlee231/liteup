const volumeSelect = document.getElementById('volume');
const amountInput = document.getElementById('amount');
const bundlePrices = { '500MB': 150, '1GB': 300, '2GB': 500, '5GB': 1000 };

if (volumeSelect && amountInput) {
    const updateAmount = () => {
        amountInput.value = bundlePrices[volumeSelect.value] || 0;
    };
    volumeSelect.addEventListener('change', updateAmount);
    updateAmount();
}
