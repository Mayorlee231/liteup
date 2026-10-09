document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-confirm]');
    if (form && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-history-back]')) {
        window.history.back();
    }
});
