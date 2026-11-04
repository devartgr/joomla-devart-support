(function () {
    function bindFilterAutosubmit() {
        document.querySelectorAll('select[data-devart-support-autosubmit]').forEach(function (select) {
            select.addEventListener('change', function () {
                if (select.form) {
                    select.form.submit();
                }
            });
        });
    }

    function init() {
        bindFilterAutosubmit();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
