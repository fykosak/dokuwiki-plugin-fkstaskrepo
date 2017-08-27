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

// Figures
jQuery(function () {
    const $figureContainer = $('.figures');
    let maxIndex = 0;

    const addRow = (index, path = '', cation = '') => {
        maxIndex = index;
        return '<div class="row">' +
            '<div class="col-6"><input type="text" class="form-control" name="problem[figures][' + index + '][path]" value="' + path + '"/></div>' +
            '<div class="col-6"><input type="text" class="form-control" name="problem[figures][' + index + '][caption]" value="' + cation + '"/></div>' +
            '</div>';
    };

    let html =  $.map( figures, (figure, index) => {
        return addRow(index, figure.path, figure.caption);
    }).join('');

    html = '<div class="row">' +
        '<div class="col-6">Cesta</div>' +
        '<div class="col-6">Popisek</div>' +
        '</div>' + html;

    $figureContainer.append(html);

    $figureContainer.on('input', '', function () {
        let hasValue = false;
        $figureContainer.find('.row').last().find('input').each(function () {
            hasValue = hasValue || (!!$(this).val());
        });
        if (hasValue) {
            $figureContainer.append(addRow(maxIndex + 1));
        }
    });

    $figureContainer.append(addRow(maxIndex + 1));

});
