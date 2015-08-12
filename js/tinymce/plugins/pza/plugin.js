tinymce.PluginManager.add(
    "pza",
    function(t) {
        alert("QQQ");
        t.addButton("jqmodal",
            {text: "Szczegóły", icon:!1,
                onclick: function() {
                    $('.mce-tinymce').css('zIndex', 99);
                }});
        t.addButton("save",
            {text: "Zapisz", icon:!1,
                onclick: function() {
                    $('.ui-dialog-buttonset .save-button').trigger("click");
                    $('.mce-tinymce').css('zIndex', 99);
                }});
        t.addButton("pzaquit",
            {text: "Anuluj", icon:!1,
                onclick: function() {
                    $('div.html-container').remove();
                    $('.table-dialog').dialog("close");
                    $('body').removeClass("mce-fullscreen");
                }});
    });

