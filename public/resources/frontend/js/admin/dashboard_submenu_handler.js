document.addEventListener('DOMContentLoaded', function() {
    // Form auto-submit handlers
    document.getElementById('time_period').addEventListener('change', function() {
        this.form.submit();
    });

    document.querySelectorAll('#category, #status').forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
});