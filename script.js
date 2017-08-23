jQuery(function () {
    "use strict";
    var $ = jQuery;
    $('.task-repo.batch-select').each(function () {
        const $container = $(this);
        const $select = $container.find('select');
        $select.change(function () {
            const year = $(this).find(':selected').attr('data-year');
            $container.find('.year').hide();
            $container.find('.year[data-year="' + year + '"]').show();
        });
    });
});
