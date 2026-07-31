/* PayGuard checkout — highlight selected payment method */
(function () {
    function updateSelected() {
        document.querySelectorAll('.payguard-method').forEach(function (label) {
            var radio = label.querySelector('input[type="radio"]');
            label.classList.toggle('payguard-method--selected', radio && radio.checked);
        });
    }

    document.addEventListener('change', function (e) {
        if (e.target && e.target.name === 'payguard_provider') {
            updateSelected();
        }
    });

    // Run on WooCommerce checkout update (after AJAX refresh)
    document.body && document.body.addEventListener('updated_checkout', updateSelected);
})();
