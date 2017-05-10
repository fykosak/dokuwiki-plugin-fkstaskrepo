jQuery(function () {
    var $ = jQuery;
    $('#FKS_taskrepo_select').change(function (e) {
        const $sel = $(this);
        $sel.parent('.FKS_taskrepo').find('div.year').hide();
        const year = $sel.find('option:selected').val();
        $sel.parent('.FKS_taskrepo').find(' div[data-year="' + year + '"]').show();
    });
});
