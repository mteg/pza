/* Compare just file names, without paths */

function same_file(n1, n2) {
    var a1 = n1.split("/");
    var a2 = n2.split("/");

    /* If there is some name at all? */
    if (a1[a1.length - 1].length > 0)
        return (a1[a1.length - 1] == a2[a2.length - 1]);

    return false;
}

function init_controls(element) {
    var ajax_spinner_timeout = 0;

    /* Uruchomienie pól HTML */
    var textareas = $(element).find('textarea.html');
//    if(typeof textareas.tinymce == "function")
    if (1) {
        var fs_ed = false;
        textareas.each(function () {
            var parentdiv = $(this).closest("div.field");
            var d = parentdiv.closest(".table-dialog");

            if (d.size()) {
                $('body').prepend(parentdiv);
                parentdiv.addClass("html-container");

                $(this).tinymce({
                    script_url: '/js/tinymce/tinymce.min.js',
                    language: 'pl',
                    plugins: [
                        "code", "fullscreen", "paste", "table",
                        "lists", "link", "searchreplace", "noneditable", "pza"],
                    toolbar: "undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | inserttable link | jqmodal save pzaquit",
                    oninit: function (ed) {
                        $('.mce-tinymce').css('zIndex', 101);
                        ed.execCommand("mceFullScreen");
                        setTimeout(function () {
                            ed.execCommand("mceFullScreen");
                            ed.execCommand("mceFullScreen");
                        }, 100);
                    },
                    setup: function (ed) {
                        ed.on('keydown', function (e) {
                            if (e.keyCode == 27) {
                                $('div.html-container').remove();
                                d.dialog("close");
                                $('body').removeClass("mce-fullscreen");
                            }
                        });
                    }
                });

                console.log("hgtSet");
                d.dialog("option", "height", $(window).height());
                var buttons = d.dialog("option", "buttons"); // getter
                buttons.push({
                    "text": "Edycja treści",
                    click: function () {
                        $('.mce-tinymce').css('zIndex', 101);
                    }
                });
                d.dialog("option", "buttons", buttons); // setter
            }
            else {
                $(this).tinymce({
                    script_url: '/js/tinymce/tinymce.min.js',
                    language: 'pl',
                    plugins: [
                        "code", "fullscreen", "paste", "table",
                        "lists", "link", "searchreplace", "noneditable"],
                    toolbar: "undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | inserttable link"
                });

            }
        });

    }

    /*    textareas.each(function() {
     $(this).parent().parent().find(".html-fullscreen-link").on("click", function() {
     $(this).parent().find("textarea.html").tinymce().execCommand("mceFullScreen");
     })
     });*/


    /* Edycja czegokolwiek powoduje wyczyszczenie błędu */
    $(element).on("change", "input, textarea, select", function () {
        $(this).removeClass('err').siblings("div.error").html("");
    });

    /* Datepicker */
    $(element).find("input.table-datepicker").datepicker({
        dateFormat: "yy-mm-dd",
        changeMonth: true,
        changeYear: true,
        yearRange: "1900:2030",
        constrainInput: false
    });

    /* Autocomplete */
    $(element).find("input.table-autocomplete").each(function () {
        var src = $(this).closest(".source-domain").children("input[name=source]").val();
        src += "/complete?f=" + encodeURIComponent($(this).attr("name"));
        src += "&" +
            $(this).closest(".source-domain").children("input[name=params]").val();
        $(this).autocomplete({source: src, minLength: 2});
    });

    $(element).find("input.table-autocomplete2").each(function () {
        var src = $(this).closest(".source-domain").children("input[name=source]").val();
        src += "/complete?f=" + encodeURIComponent($(this).attr("name"));
        src += "&" +
            $(this).closest(".source-domain").children("input[name=params]").val();

            $(this).autocomplete({
                minLength: 2,
                src: src,
                source: function(request, response) {
                    var term = request.term.split(',').pop();
                    $.ajax({
                        dataType: "json",
                        type : 'Get',
                        url: src + "&term=" + term,
                        success: function(data) {
                            response(data);
                        }
                    });
                },
                select: function( event, ui ) {
                    var input = $(event.target);
                    var content = input.val().split(',');

                    if (!input.data('multiple')) {
                        // content.unshift(ui.item.label);
                        // input.val(content.join());
                        input.val(ui.item.label);
                    } else {
                        input.val('');

                        input.parent().append(generate_autocomplete_option(ui.item.label, input.attr('name')));
                        rebind_autocomplete_events();
                    }

                    return false;
                },
                focus: function (event, ui) {
                    // Zapobiegamy standardowemu zachowaniu przy wyborze
                    // podpowiedzi za pomocą klawiszy strzałek na klawiaturze
                    // nie chcemy zastępować całego pola nową wartością
                    return false;
                },
                create: function(event, ui) {
                    var input = $(event.target);

                    // tylko dla lementów typu multiple tworzymy boxy
                    console.log(input);
                    if (input.data('multiple')) {
                        var content = input.val();

                        if (content.length == 0) {
                            return true;
                        }

                        content = content.split(',');
                        $.each(content, function (idx) {
                            input.parent().append(generate_autocomplete_option(content[idx], input.attr('name')));
                        })
                        input.val('');

                        rebind_autocomplete_events();
                    }

                }
            });
    });

    /* Checkbox-y z flagami */
    $(element).find("input.table-flags").each(function () {

        /* Obsługa kliknięć w checkboxy */
        $(this).parent().on("change", "input[type=checkbox]", function () {
            var o = $(this).closest("span").find("input[type=hidden]");
            var s = o.val();

            /* Usuń flagę z listy */
            s = s.replace($(this).val(), "");

            /* Dodaj flagę, jeśli checkbox zaznaczony */
            if ($(this).prop("checked"))
                s += $(this).val();

            /* Nowa wartość pola */
            o.val(s);
        });

        /* Zaznacz checkboxy odpowiadające zaznaczonym flagom */
        var str = $(this).val(), i;
        for (i = 0; i < str.length; i++)
            $(this).parent().find("input[value=" + str[i] + "]").prop("checked", true);
    });

    var fu_container = $(element).find('.file_upload');
    if (fu_container.hasClass("photo_upload") || fu_container.hasClass("logo_upload")) {
        var xid = $(element).find("input[name=xid]").val();
        fu = fu_container.fileupload({
            url: '/upload.php?xid=' + xid + (fu_container.hasClass("photos") ? "&photos=1" : ""),
            autoUpload: true,
            disableImageResize: true,
            imageMaxWidth: 1024,
            imageMaxHeight: 1024,
            maxNumberOfFiles: 1
        }).bind("fileuploaddone", function (e, data) {
            var class_name = fu_container.hasClass("photo_upload") ? "photo" : "logo";
            var id = $(element).find("input[name=id]").val();
            window.location = "/insider/" + class_name + "/commit?xid=" + xid + (id ? "&id=" + id : "");
        });
    }
    else {

        var fu = fu_container.fileupload({
            url: '/upload.php?xid=' + $(element).find("input[name=xid]").val() + (fu_container.hasClass("photos") ? "&photos=1" : ""),
            autoUpload: true,
            disableImageResize: true,
            imageMaxWidth: 1024,
            imageMaxHeight: 1024
        }).bind("fileuploaddestroyed", function (e, data) {
            var mce = $("textarea.html");
            var content;
            if (!mce.size()) return;
            content = $(mce.tinymce().getContent());
            content.find("img").each(function () {
                if (same_file($(this).attr("src"), $(data.context).find("a").first().attr("href")))
                    $(this).remove();
            });
            console.log($("<div />").append(content.clone()).html());
            mce.tinymce().setContent($("<div />").append(content.clone()).html());
        }).bind("fileuploaddone", function (e, data) {
            var j;
            var mce = $("textarea.html");

            if (!mce.size()) return;

            mce = mce.tinymce();
            for (j = 0; j < data.result.files.length; j++) {
                var fname = data.result.files[j].url;
                if ((/\.(gif|jpg|jpeg|tiff|png)$/i).test(fname))
                    mce.dom.add(mce.getBody(), 'img', {src: fname, class: "mceNonEditable"});
            }
        }).bind("fileuploadsend", function (e, data) {
            var cnt = $(this).data("sending");
            if (!cnt) cnt = 0;
            $(this).data("sending", ++cnt);
            /* Form cannot be submitted */
            $(".ui-dialog-buttonset").find("button").button("disable");
        }).bind("fileuploadalways", function (e, data) {
            var cnt = $(this).data("sending");
            $(this).data("sending", --cnt);
            if (!cnt) /* Form can be submitted again */
                $(".ui-dialog-buttonset").find("button").button("enable");
        });

        if (fu_container.hasClass("photos"))
            fu.fileupload("option", "disableImageResize", false);
    }

    $(document)
        .ajaxStart(function () {
            // nie pokazujemy spinnera dla krótkich requestów,
            // po co migać użytkownikowi ekranem (modal box)
            ajax_spinner_timeout = setTimeout(function() {
                $('#ajax-loading').show();
            }, 400)
        })
        .ajaxStop(function () {
            clearTimeout(ajax_spinner_timeout);
            $('#ajax-loading').hide();
        });
}

function generate_autocomplete_option(label, name)
{
    return    '<span class="autocomplete-multiple-option">' + label +
        '<img src="/templates/insider/images/remove.png">' +
        '</span>' +
        '<input type="hidden" name="' + name + '[]" value="' + label + '"></input>';
}

/* Pobierz parametry par1=war1 znajdujące się w
 adresie po strony po znaku #
 */
function q_all() {
    var url = document.location.toString();
    var regex = new RegExp("^([^#]*)#(.*)$");
    var res = regex.exec(url);

    if (res == null) return "";
    return res[2];
}

/* Przetwórz parametry par1=war1&par2=war2 znajdujące
 się w adresie strony po znaku #
 */

function q_parse() {
    var res = q_all();
    var params = {};

    if (res.length < 1) return params;

    $.each(res.split("&"), function () {
        var p = this.split("=");
        if (p.length >= 2)
            params[decodeURIComponent(p[0])] = decodeURIComponent(p[1]);
    });

    return params;
}

/* Pobierz wartość parametru po # */
function q_get(param, def) {
    var params = q_parse();
    if (!(param in params)) return def;
    return params[param];
}

/* Ustaw wartość parametru po # */
function q_set(param, val) {
    var url = document.location.toString();
    var regex = new RegExp("^([^#]*)(#(.*))?$");
    var res = regex.exec(url);

    var params = q_parse(), p = "", qstr = "";
    params[param] = val;

    for (p in params)
        qstr += "&" + encodeURIComponent(p) + "=" + encodeURIComponent(params[p]);

    document.location = res[1] + "#" + qstr.substr(1);
}

function rebind_autocomplete_events() {
    $('.autocomplete-multiple-option > img').each(function(idx, elem) {
        $(elem).unbind('click').bind('click', function() {
            var box = $(this).parent('span');
            box.next().remove();
            box.remove();
        })
    })
}


$(function () {

    $('.navigation').on('mouseenter', '#menu > li > a', function (event) {
        $(this).closest(".navigation").find("a").removeClass("ui-state-active");
        $(this).addClass("ui-state-active");
    });

    $('.navigation li ul').on("mouseleave", function () {
        $('.navigation a.ui-state-active').removeClass("ui-state-active");
    });

    $('.main-header .profile').on('click', function (event) {
        if ($(this).hasClass("ui-state-active"))
            $(this).removeClass("ui-state-active");
        else
            $(this).addClass("ui-state-active");
    });

    init_controls($('body'));
});
