define(['core/ajax'], function(Ajax) {

    const getRedirectForm = (component, paymentArea, itemId, description) => {
        const request = {
            methodname: 'paygw_pnb_get_redirect_form',
            args: {
                component: component,
                paymentarea: paymentArea,
                itemid: itemId,
                description: description,
            },
        };
        return Ajax.call([request])[0];
    };

    return {
        getRedirectForm,
    };
});
