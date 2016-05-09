jQuery(function () {
    function split(val) {
        return val.split(/,\s*/);
    }
    function extractLast(term) {
        return split(term).pop();
    }
    var $tags = jQuery("#fkstaskrepo-tags");
    if (!$tags) {
        return;
    }

    var availableTags = $tags.data('tags');

    $tags.bind("keydown", function (event) {
        if (event.keyCode === jQuery.ui.keyCode.TAB &&
                jQuery(this).data("ui-autocomplete").menu.active) {
            event.preventDefault();
        }
    }).autocomplete({
        minLength: 2,
        source: function (request, response) {
            response(jQuery.ui.autocomplete.filter(
                    availableTags, extractLast(request.term)));
        },
        focus: function () {
            return false;
        },
        select: function (event, ui) {
            var terms = split(this.value);
            terms.pop();
            terms.push(ui.item.value);
            terms.push("");
            this.value = terms.join(", ");
            return false;
        }
    });

    var $ = jQuery;
    $('#FKS_taskrepo_select').change(function (e) {
        var $sel = $(this);
        $sel.parent('.FKS_taskrepo').find('div.year').hide();
        var year = $sel.find('option:selected').val();
        $sel.parent('.FKS_taskrepo').find(' div[data-year="' + year + '"]').show();
    });

    $('.FKS_taskrepo.probfig img').click(function (event) {
        this.srcSet = "srcset";
        this.zoomClass = "zoomed";
        var $probfig = $(this).parents('.probfig');
        var $source = $probfig.find('picture source[data-full]');   
        if ($source.length) {
            if ($probfig.hasClass(zoomClass)) {
                var d = $source.attr(srcSet);
                $source.removeAttr(srcSet);
                $source.data(srcSet, d);
            } else {
                var d = $source.data(srcSet);
                $source.attr(srcSet, d);
            }
        }
        $probfig.toggleClass(zoomClass);
    });

});

