jQuery(function () {
    var $ = jQuery;
    $('.task-repo.batch-select').each(function (e) {
        const $container = $(this);
        const $select = $container.find('select');
        $select.change(function () {
            "use strict";
            const year = $(this).find(':selected').attr('data-year');
            $container.find('.year').hide();
            $container.find('.year[data-year="' + year + '"]').show();
        });
    });
});
