jQuery(function () {
    var $ = jQuery;
    $('.task-repo.batch-select').each(function (e) {

        const $container = $(this);
        const $dropDownItems = $container.find('.dropdown .dropdown-item');
        $dropDownItems.click(function () {
            "use strict";
            const year = $(this).attr('data-year');
            $container.find('.year').hide();
            $container.find('.year[data-year="' + year + '"]').show();
        });
    });
});
