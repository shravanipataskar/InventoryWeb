<script>
    // Prevent accidental double submission of workflow forms; the server also enforces idempotency.
    document.querySelectorAll('form[data-submit-once]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = '1';
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = true;
                button.dataset.label = button.textContent;
                button.textContent = 'Processing…';
            });
        });
    });
</script>
