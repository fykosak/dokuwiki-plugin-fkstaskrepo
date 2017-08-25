figureManager = new function () {
    this.update = function () {
        $('.figures button').addClass('btn btn-danger').attr('type', 'button').attr('onclick', 'figureManager.remove(this);');
        var i = 0;
        $('.figures tr').each(function () {
            $('input', this).eq(0).addClass('form-control').attr('type', 'text').attr('name', 'problem[figures][' + i +  '][path]');
            $('input', this).eq(1).addClass('form-control').attr('type', 'text').attr('name', 'problem[figures][' + i +  '][caption]');
            i++;
        });
    };

    this.add = function () {
        $('.figures').append('<tr><td><input></td><td><input></td><td><button>X</button></td></tr>');
        this.update();
    };

    this.remove = function (btn) {
        $(btn).closest('tr').remove();
        this.update();
    };
};



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

    figureManager.update();
});

