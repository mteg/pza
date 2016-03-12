
function table_modal(o, action)
{

    var d = $(".table-dialog");
    d.html("<img src='/i/ajax.gif'>");
    d.load(action, function() {

        /* Ustalenie tytułu */
        d.dialog("option", "title", $(this).find("input.dialog-title").val());
        d.dialog("open");

        /* Obsługa formularza wyszukiwania */
        $(this).find(".table-search").each(function() {
                /* Wczytanie zawartości filtrów */
                $(this).find("input[type=text], select").each(function() {
                    $(this).val(q_get($(this).attr("name"), ""));
                });

                init_controls(this);

                /* Oprogramowanie przycisków */
                d.dialog("option", "buttons", [{
                    text: "Wyszukaj",
                    click: function() {
                        $(this).find(".table-search input[type=text], .table-search select").each(function() {
                            q_set($(this).attr("name"), $(this).val());
                        });
                        q_set("offset", 0);
                        $(".table-container > input[name=source]").trigger("change");
                        d.dialog("close");
                    }
                }]);
            });

        /* Obsługa formularza edycji */
        $(this).find(".table-edit").each(function() {

            /* Oprogramowanie przycisków */
            d.dialog("option", "buttons", [{
                text: "Zapisz dane",
                "class": "save-button",
                click: function() {
                    var src  = $(this).find("input[name=source]").val();
                    var params = $(this).find("input[name=params]").val();
                    var method = $(this).find("input[name=method]").val();
                    var url;
                    if(method == null) method = "save";
                    if(params == null) params = "";

                    url = src + "/" + method + "?" + params;
                    if(method != "save")
                    {
                        url += "&" + $(this).find("form").serialize() + "&" + $('textarea.html').serialize();
                        $(".table-container > input[name=source]").trigger("change");
                        table_modal(null, url);
                        return;
                    }


                    $.ajax({
                            type: "POST",
                            url: url,
                            data: $(this).find("form").serialize() + "&" + $('textarea.html').serialize(),
                            success: function (data) {
                                if(Object.keys(data).length > 0)
                                {
                                   var msg = "", focus_o = false;
                                    /* Błędy */
                                   d.find("input").siblings("div.error").html("");
                                   $.each(data, function (key, value) {
                                        var o = d.find("*[name=" + key + "]");
                                        o.siblings("div.error").html(value);
                                        if(msg == "") focus_o = o;
                                        msg += "\n[" + key + "] " + value;
                                    });

                                    alert("Podczas zapisywania obiektu wystąpiły następujące błędy:" + msg);
    //                                if(focus_o) focus_o.focus();
                                }
                                else
                                {
                                    /* Wszystko OK */
                                    $(".table-container > input[name=source]").trigger("change");
                                    $('body').removeClass("mce-fullscreen");
                                    $('div.html-container').remove();
                                    d.dialog("close");
                                }
                            },
                            dataType: "json"
                    });
                }
            }]);

        });



        /* Uruchomienie pól typu autocomplete, date itd. */
        init_controls(this);

        $(this).find("script").each(function() {
            if($(this).attr("type") != "text/x-tmpl")
                eval($(this).text());
        });

        /* Obsługa formularza importu */
        $(this).find(".table-import").each(function() {

            /* Uruchomienie pól typu autocomplete, date itd. */
            init_controls(this);

            $(this).find("span.import-details").closest("div").hide();

            /* Oprogramowanie przycisków */
            d.dialog("option", "buttons", [{
                text: "Popraw",
                id: "import-back",
                click: function() {
                    $(d).find(".import-details").closest("div").hide();
                    $(d).find("textarea[name=entries]").closest("div").show();
                    $('#import-rescan').show();
                    $('#import-confirm').hide();
                    $("#import-back").hide();
                }
            },
            {
                text: "Przeanalizuj",
                id: "import-rescan",
                click: function() {
                    var src  = $(this).find("input[name=source]").val();
                    var params = $(this).find("input[name=params]").val();

                    $.ajax({
                        type: "POST",
                        url: src + "/import?" + params,
                        data: $(this).find("form").serialize(),
                        success: function (data) {
                            $(d).find(".import-details").html(data).closest("div").show();
                            $(d).find("textarea[name=entries]").closest("div").hide();
                            $('#import-rescan').hide();
                            $('#import-confirm').show();
                            $("#import-back").show();
                        },
                        dataType: "html"
                    });
                }
            }, {
                text: "Zatwierdź",
                id: "import-confirm",
                click: function() {
                    var src  = $(this).find("input[name=source]").val();
                    var params = $(this).find("input[name=params]").val();

                    $.ajax({
                        type: "POST",
                        url: src + "/import?commit=1&" + params,
                        data: $(this).find("form").serialize(),
                        success: function (data) {
                            $(".table-container > input[name=source]").trigger("change");
                            d.dialog("close");
                        },
                        dataType: "html"
                    });
                }
            }
            ]);
            $('#import-confirm').hide();
            $('#import-back').hide();
            $(d).find(".import-details").closest("div").hide();
        });


        /* Obsługa formularza podglądu i historii */
        $(this).find(".table-view, .table-history").each(function() {

            /* Sporządź listę przycisków */
            var buttons = [];

            /* Pobierz wszystkie operacje z menu akcji na rekordzie */
            $(this).find(".table-op-menu > ul > li > a").
                each(function() {
                    var capt = $(this).find("span").text();
                    var a_el = $(this);

                    if(capt == "Podgląd") return;

                    buttons.push({
                        text: capt,
                        click: function() {
                            $(a_el).trigger("click");
                        }
                    });
                });

            /* Dodaj przycisk "Zamknij" */
            buttons.push({
                text: "Zamknij",
                click: function() {
                    d.dialog("close");
                }});

            /* Oprogramowanie przycisków */
            d.dialog("option", "buttons", buttons);
        });
    });
}

function table_append_id(o, url)
{
    var ids = "";
    if($(o).closest(".table-op-menu-multiple").size())
    {
        $(o).closest("table").
            find("input[type=checkbox]:checked").
            closest("tr").find("input[name=id]").each(
            function() { ids += " " + $(this).val(); }
        );
        ids = ids.substr(1);
    }
    else
    {
        ids = $(o).closest("tr").find("input[name=id]").val();
        if(!ids) ids = $(o).closest(".source-domain").children("input[name=id]").val();
    }

    if(!ids.length)
    {
        alert("Nic nie zaznaczono!");
        return false;
    }

    return url + "&id=" + encodeURIComponent(ids);
}

function table_action(o, target, url)
{
    if(target == "_blank")
        window.open(url);
    else if(target == "_self")
        window.location = url;
    else if(target == "_top")
    {
        $.get(url, "", function(data) {
            if("msg" in data)
                alert(data["msg"]);
            $(".table-container > input[name=source]").trigger("change");
            $(".table-dialog").dialog("close");
        }, "json");
    }
    else if(target == "_mce")
    {

        table_modal(o, url);
    }
    else
        table_modal(o, url);
}

$(function() {

    /* Oprogramuj akcję odświeżania tabeli (filtry i przeglądanie) */
    $("table.table-table").siblings("input[name=source]").on("change", function () {

        /* Schowaj menu w bezpieczne miejsce */
        $(".table-op-menu").appendTo($(".table-op-menu").closest(".table-container"));

        var url = $(this).val() + "/table", sselect;
        url += "?" + q_all() + "&" + $(this).siblings("input[name=params]").val();
        $(this).nextAll("table.table-table").first().load(url, function() {
            $(window).scrollTop(0);
        });
    });

    /* a może trzeba ustawić selektor */
    var s = $("span#main_selector select[name=selector]");
    if(s.size())
    {
        s.val(q_get("selector", 0));
        q_set("selector", s.val());
    }

    /* Załaduj początkową zawartość tabeli */
    $("table.table-table").each(function() {
        $(this).siblings("input[name=source]").trigger("change");
    });

    /* Oprogramuj przyciski kolejnych/poprzednich wyników */
    $(".table-action-up, .table-action-dn").on("click", function() {
        var dir  = $(this).hasClass("table-action-dn") ? 1 : -1;
        var offs = parseInt(q_get("offset", 0)) + q_get("limit", 50) * dir;

        if(offs < 0) offs = 0;
        q_set("offset", offs);

        $(this).closest(".table-container").children("input[name=source]").trigger("change");
    });

    /* Oprogramuj przycisk zamiany zaznaczenia */
    $("table.table-table").on("click", ".table-action-toggle", function() {
        $(this).closest("table").find("input[type=checkbox]").each(function () {
                $(this).prop("checked", !$(this).prop("checked"));
            }
        );
    });

    /* Oprogramuj kliknięcie w wiersz - otwórz modalny dialog */
    $("table.table-table").on("click", "td:not(.table-noclick)", function() {
        /* Przesuń menu do właściwego wiersza */

        /* Znajdź menu i przesuń je do właściwego wiersza */
        $(this).closest(".table-container").find(".table-op-menu").
            removeClass("table-op-menu-multiple").addClass("table-op-menu-single").
            appendTo($(this).parent().children("td:last-child"));

        /* Schowaj menu */
        $("body").trigger("click");

        /* Otwórz okno akcji "click" */
        var src  = $(".table-container > input[name=source]").val();
        src += "/click?" + $(".table-container > input[name=params]").val();

        var m = $(this).closest(".table-container").find(".table-op-menu");
        var a = m.find('a[href$="/click"]'), target = false
        if(a.size())
            target = $(a).attr("target")

        table_action(this, target, src + "&id=" +
                   $(this).closest("tr").find("input[name=id]").val());
    });

    /* Upewnij się, że klikanie w checkboxy nie otworzy tego samego co klik w wiersz */
    $("table.table-table").on("click", "input", function(event, ui) {
        event.stopPropagation();
    });

    /* Oprogramuj kliknięcie w przycisk operacji - otwórz menu */
    $("table.table-table").on("click", ".table-action-menu", function() {

        /* Schowaj wszelkie stare menu */
        $("body").trigger("click");

        /* Znajdź menu */
        var m = $(this).closest(".table-container").find(".table-op-menu");

        /* Akcja na jednym czy wielu obiektach? */
        if($(this).closest("thead").size())
            m.addClass("table-op-menu-multiple").removeClass("table-op-menu-single");
        else
            m.removeClass("table-op-menu-multiple").addClass("table-op-menu-single");

        m.children("ul").menu();
        m.css("display", "block");
        m.position({my: "left top", at: "right top", of: $(this), collision: "flipfit flipfit"});
        m.appendTo($(this).parent());
        return false;
    });

    /* Oprogramowanie głównego pola wyboru */
    $("body").on("change", "span#main_selector select", function (event, ui)
    {
        q_set("selector", $(this).val());
        $(".table-container > input[name=source]").trigger("change");
    });

    /* Oprogramowanie przycisków funkcyjnych */
    $("body").on("click", ".table-action", function(event, ui) {
        event.stopPropagation();
        event.preventDefault();

        /* Potwierdzenie? */
        if($(this).attr("rel"))
            if(!confirm($(this).attr("rel")))
                return false;

        /* URL akcji */
        var url = $(this).attr("href"), selector = $(this).closest(".source-domain").find("select[name=selector]");
        url += '?';

        // + "?" + q_all(); (filtry przeszkadzają - czy to jest potrzebne?)

        url += "&" + $(this).closest(".source-domain").
            children("input[name=params]").val();

        /* Dodaj ID jeśli o to proszono */
        if($(this).hasClass("table-append-id"))
        {
            url = table_append_id(this, url);
            if(!url) return false;
        }

        /* Dodaj selektor jeśli jest */
        if(selector.size())
            url += "&selector=" + selector.val();

        /* Schowaj menu */
        $("body").trigger("click");

        /* Wykonaj operację, w zależności od atrybutu target */
        var target = $(this).attr("target");
        table_action($(this), target, url);

        return false;
    });

    /* Oprogramowanie przycisków funkcyjnych */
    $("body").on("click", ".dialog-action", function(event, ui) {
        event.stopPropagation();
        event.preventDefault();

        /* URL akcji */
        var url = $(this).attr("href");

        /* Schowaj menu */
        $("body").trigger("click");

        /* Wykonaj operację, w zależności od atrybutu target */
        var target = $(this).attr("target");
        table_action($(this), target, url);

        return false;
    });

    /* Oprogramowanie sortowania po kolumnach */
    $("body").on("click", ".table-order", function(event, ui) {
        event.stopPropagation();
        event.preventDefault();

        q_set("order", $(this).attr("href"));
        $(".table-container > input[name=source]").trigger("change");

        return false;
    });


    /* Oprogramuj kliknięcie gdziekolwiek - chowamy menu */
    $("body").on("click", function() {
        $(".table-op-menu, .table-op-menu-multiple").css("display", "none");
    });

    /* Przygotuj dialog do akcji */
    $('.table-dialog').dialog({
        width: $(window).width() * 0.9,
//        height: $(window).height() * 0.9,
        modal: true,
        autoOpen: false,
        beforeClose: function (ev, ui) {
            if($('.mce-tinymce').size())
            {
                $('.mce-tinymce').css('zIndex', 101);

                ev.stopPropagation();
                ev.stopImmediatePropagation();
                ev.preventDefault();
            }
        }
    });

    /* Klawisz ENTER wciska pierwszy przycisk dialogu */
    $(document).on('keypress', '.ui-dialog', function(e) {
        var tagName= e.target.tagName.toLowerCase();

        tagName= (tagName == 'input' && e.target.type == 'button') ? 'button' : tagName;

        if (e.which == $.ui.keyCode.ENTER && tagName != 'textarea' && tagName != 'select' && tagName != 'button') {
            e.stopPropagation();
            e.preventDefault();
            $(this).find('.ui-dialog-buttonset button').eq(0).trigger('click');
            return false;
        }
    });

    /* Wyłącz cache'owanie */
    $.ajaxSetup ({
        cache: false
    });

    /* tinyMCE w dialogu: http://fiddle.tinymce.com/rsdaab */
    $(document).on('focusin', function(event) {
        if ($(event.target).closest(".mce-window").length) {
            event.stopImmediatePropagation();
        }
    });

    /* a może zamówiono jakąś akcję? */
    var action = q_get("modal", false);
    if(action != false)
    {
        var url = $("input[name=source]").val() + "/" + action + "?id=" +
                    encodeURIComponent(q_get("id", "")) +
                    "&" + $("input[name=params]").val();
        table_modal(null, url);
    }

    /* sprawdzanie, czy użytkownik nie występuje już */
    $('body').on('change',
        '.table-users-add input[name=name], .table-users-add input[name=surname]',
        function() {
            var name = $('.table-users-add input[name=name]');
            var surname = $('.table-users-add input[name=surname]');
            if(name.val().length > 0 && surname.val().length > 0)
                name.siblings("div.error").
                   load("/insider/users/dupcheck?name=" +
                   encodeURIComponent(name.val()) +
                    "&surname=" + encodeURIComponent(surname.val()));
        });
});
