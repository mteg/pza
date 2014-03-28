function show(o, n)
{
    var list = $(o).children("input[type=hidden]");
    if(n == null)
    {
        n = $(o).data("n");
        if(n == null)
            n = 0;
        else
            n++;

        if(n >= list.length) n = 0;
    }
    $(o).data("n", n);

    var im = list.eq(n).val();

    $(o).find(".pza-banner-switcher").children().removeClass("active");

    $(o).find("img").fadeOut(400, function() {
        $(this).attr('src', im).unbind('onreadystatechange load').
            bind('onreadystatechange load', function() {
            if(this.complete)
            {
                $(this).fadeIn(400);
                $(this).parent().children(".pza-banner-switcher").
                    children().
                    eq(n).addClass("active");
            }
        });
    });
}

$(function () {
    $('.pza-banners').each(function() {
       var o = $('<div class="pza-banner-switcher"/>');
       o.appendTo(this);
       $(this).children("input[type=hidden]").each(function(n) {
           $('<div>&nbsp;</div>').appendTo(o).data("seq", n);
       });
    });

    setInterval(function() {
        $('.pza-banners').each(function() {
            if($(this).is(":hover")) return;
            show(this);
        });
    }, 6000);

    $(".pza-banner-switcher").on("click", "div", function() {
        show($(this).parent().parent(), $(this).data("seq"));
    })

});
