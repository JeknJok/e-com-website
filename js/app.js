document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('img[data-fallback]').forEach(image => {
        image.addEventListener('error', () => {
            image.src = image.dataset.fallback;
        }, { once: true });
        if (image.complete && image.naturalWidth === 0) image.src = image.dataset.fallback;
    });
    const error = document.getElementById('form-error');
    if (error) error.focus();
});
