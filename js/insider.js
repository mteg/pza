
/* Compare just file names, without paths */
function same_file(n1, n2)
{
    var a1 = n1.split("/");
    var a2 = n2.split("/");

    /* If there is some name at all? */
    if(a1[a1.length - 1].length > 0)
        return (a1[a1.length - 1] == a2[a2.length - 1]);

    return false;
}

function init_controls(element)
{
    /* Uruchomienie pól HTML */
    var textareas = $(element).find('textarea.html');
    if(typeof textareas.tinymce == "function")
        textareas.tinymce({
            plugins: [
                "code", "fullscreen", "paste", "table",
                "lists", "link", "searchreplace", "image", "noneditable", "example"],
            toolbar: "undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | inserttable link fullscreen | example"
        });


    textareas.each(function() {
        $(this).parent().parent().find(".html-fullscreen-link").on("click", function() {
            $(this).parent().find("textarea.html").tinymce().execCommand("mceFullScreen");
        })
    });


    /* Edycja czegokolwiek powoduje wyczyszczenie błędu */
    $(element).on("change", "input, textarea, select", function() {
        $(this).removeClass('err').siblings("div.error").html("");
    });

    /* Datepicker */
    $(element).find("input.table-datepicker").datepicker({
        dateFormat: "yy-mm-dd",
        changeMonth: true,
        changeYear: true,
        constrainInput: false
    });

    /* Autocomplete */
    $(element).find("input.table-autocomplete").each(function() {
        var src = $(this).closest(".source-domain").children("input[name=source]").val();
        src += "/complete?f=" + encodeURIComponent($(this).attr("name"));
        src += "&" +
            $(this).closest(".source-domain").children("input[name=params]").val();
        $(this).autocomplete({source: src, minLength: 2});
    });

    /* Checkbox-y z flagami */
    $(element).find("input.table-flags").each(function() {

        /* Obsługa kliknięć w checkboxy */
        $(this).parent().on("change", "input[type=checkbox]", function() {
            var o = $(this).closest("span").find("input[type=hidden]");
            var s = o.val();

            /* Usuń flagę z listy */
            s = s.replace($(this).val(), "");

            /* Dodaj flagę, jeśli checkbox zaznaczony */
            if($(this).prop("checked"))
                s += $(this).val();

            /* Nowa wartość pola */
            o.val(s);
        });

        /* Zaznacz checkboxy odpowiadające zaznaczonym flagom */
        var str = $(this).val(), i;
        for(i = 0; i<str.length; i++)
            $(this).parent().find("input[value=" + str[i] + "]").prop("checked", true);
    });

    var fu_container = $(element).find('.file_upload');
    var fu = fu_container.fileupload({
            url: '/upload.php?xid=' + $(element).find("input[name=xid]").val() + (fu_container.hasClass("photos") ? "&photos=1" : ""),
            autoUpload: true,
            disableImageResize: true,
            imageMaxWidth: 1024,
            imageMaxHeight: 1024
    }).bind("fileuploaddestroyed", function(e, data) {
            var mce = $("textarea.html");
            var content;
            if(!mce.size()) return;
            content = $(mce.tinymce().getContent());
            content.find("img").each(function() {
                if(same_file($(this).attr("src"), $(data.context).find("a").first().attr("href")))
                    $(this).remove();
            });
            console.log($("<div />").append(content.clone()).html());
            mce.tinymce().setContent($("<div />").append(content.clone()).html());
    }).bind("fileuploaddone",  function(e, data) {
            var j;
            var mce = $("textarea.html");

            if(!mce.size()) return;

            mce = mce.tinymce();
            for(j = 0; j<data.result.files.length; j++)
            {
                var fname = data.result.files[j].url;
                if((/\.(gif|jpg|jpeg|tiff|png)$/i).test(fname))
                    mce.dom.add(mce.getBody(), 'img', {src: fname, class: "mceNonEditable"});
            }
    }).bind("fileuploadsend", function (e, data) {
        var cnt = $(this).data("sending");
        if(!cnt) cnt = 0;
        $(this).data("sending", ++cnt);
        /* Form cannot be submitted */
        $(".ui-dialog-buttonset").find("button").button("disable");
    }).bind("fileuploadalways", function (e, data) {
        var cnt = $(this).data("sending");
        $(this).data("sending", --cnt);
        if(!cnt) /* Form can be submitted again */
            $(".ui-dialog-buttonset").find("button").button("enable");
    });

    if(fu_container.hasClass("photos"))
        fu.fileupload("option", "disableImageResize", false);
}

/* Pobierz parametry par1=war1 znajdujące się w
    adresie po strony po znaku #
 */
function q_all()
{
    var url   = document.location.toString();
    var regex = new RegExp("^([^#]*)#(.*)$");
    var res   = regex.exec(url);

    if(res == null) return "";
    return res[2];
}

/* Przetwórz parametry par1=war1&par2=war2 znajdujące
    się w adresie strony po znaku #
 */

function q_parse()
{
    var res = q_all();
    var params= { };

    if(res.length < 1) return params;

    $.each(res.split("&"), function() {
        var p = this.split("=");
        if(p.length >= 2)
            params[decodeURIComponent(p[0])] = decodeURIComponent(p[1]);
    });

    return params;
}

/* Pobierz wartość parametru po # */
function q_get(param, def)
{
    var params = q_parse();
    if(!(param in params)) return def;
    return params[param];
}

/* Ustaw wartość parametru po # */
function q_set(param, val)
{
    var url   = document.location.toString();
    var regex = new RegExp("^([^#]*)(#(.*))?$");
    var res = regex.exec(url);

    var params = q_parse(), p = "", qstr = "";
    params[param] = val;

    for(p in params)
        qstr += "&" + encodeURIComponent(p) + "=" + encodeURIComponent(params[p]);

    document.location = res[1] + "#" + qstr.substr(1);
}


$(function() {

    $('.navigation').on('mouseenter', '#menu > li > a', function (event) {
        $(this).closest(".navigation").find("a").removeClass("ui-state-active");
        $(this).addClass("ui-state-active");
    });

    $('.main-header .profile').on('click', function (event) {
        if($(this).hasClass("ui-state-active"))
            $(this).removeClass("ui-state-active");
        else
            $(this).addClass("ui-state-active");
    });

    init_controls($('body'));
});
