jQuery(function () {
    var $ = jQuery;
   $







    $('.FKS_taskrepo.task h3').each(function () {
        $(this).html(($(this).html().replace(/(\(.*\))/, "<small>$1</small>")));
    });


    $('#FKS_taskrepo_select').change(function (e) {
        var $sel = $(this);

        $sel.parent('.FKS_taskrepo').find('div.year').hide();
        var year = $sel.find('option:selected').val();
        $sel.parent('.FKS_taskrepo').find(' div[data-year="' + year + '"]').show();
    });

    //  $('.FKS_taskrepo.probfig a').prettyPhoto();


});

