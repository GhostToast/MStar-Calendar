(function() {
    tinymce.create('tinymce.plugins.mstar_calendar', {
        init : function(ed, url) {
            ed.addButton('mstar_calendar', {
                title : 'Insert MStar Events Calendar',
                image : url+'/mstar_calendar.png',
                onclick : function() {
                     ed.selection.setContent('[mstar_calendar history="12" future="12" cache_hours="24"]');
 
                }
            });
        },
        createControl : function(n, cm) {
            return null;
        },
    });
    tinymce.PluginManager.add('mstar_calendar', tinymce.plugins.mstar_calendar);
});