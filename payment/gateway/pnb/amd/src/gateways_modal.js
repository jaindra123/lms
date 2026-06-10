define(['paygw_pnb/repository', 'core/str'], function(Repository, Str) {

    /**
     * Submit a POST form to the PNB gateway.
     *
     * @param {Object} formdata
     */
    const submitRedirectForm = (formdata) => {
        const form = document.createElement('form');
        form.method = formdata.method || 'post';
        form.action = formdata.gatewayurl;
        form.style.display = 'none';

        formdata.fields.forEach((field) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = field.name;
            input.value = field.value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    };

    /**
     * Process PNB payment by redirecting to the bank gateway.
     *
     * @param {string} component
     * @param {string} paymentArea
     * @param {number} itemId
     * @param {string} description
     * @returns {Promise<string>}
     */
    const process = (component, paymentArea, itemId, description) => {
        return Repository.getRedirectForm(component, paymentArea, itemId, description)
            .then((formdata) => {
                submitRedirectForm(formdata);
                // Leave the page navigating; prevent core_payment from redirecting to successurl.
                return new Promise(() => {});
            });
    };

    return {
        process,
    };
});
