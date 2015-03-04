$(function() {
    $('table.sortable').tablesorter();

    $('body').on("keyup", function (event) {
        console.log(event);

        if(!$(event.target).is("body, a")) return;

        var artid = $('input[name=artid]').val(),
            op = false;

        if(event.which == 69)
            op = "edit";
        else if(event.which == 86)
            op = "view";
        else if(event.which == 72)
            op = "html";

        if(!(artid && op)) return;
        window.open("/insider/content?type=article#id=" + artid + "&modal=" + op);
    });

    $('div#back').on("click", "a", function (event) {
        window.history.back();
    });

    $('div#menu-phone').on("click", "a", function (event) {
        var sm = $('#sidemenu');
        if(!sm.size()) return;
        if(sm.hasClass("phone-sidemenu"))
        {
            sm.removeClass("phone-sidemenu");
            sm.insertAfter(".col2 #search");
        }
        else
        {
            sm.addClass("phone-sidemenu");
            sm.insertBefore("div.col1");
        }
    });
});


