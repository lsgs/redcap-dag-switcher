/**
 * REDCap External Module: DAG Switcher
 * @author Luke Stevens, Murdoch Children's Research Institute
 * Project page JavaScript for switching user DAG
 */
'use strict';
var MCRI_DAG_Switcher_Switch = (function(window, document, $, undefined) {
    var switchSavePath;

    function doDagSwitch(newDag) {
        $(":button:contains('Ok')").html('Please wait...');
        $(":button:contains('Cancel')").css("display","none");

        $.ajax({
            url: switchSavePath, 
            data: { pid: pid, dag: newDag },
            success: function(data) {
                if (!data.result) {
                    alert('ERROR: '+data.msg);
                }
                window.location.reload(false);
            },
            dataType: 'json'
        });
    }
    
    return {
        init: function(savePath, dagSwitchDialogTitle) {
            switchSavePath = savePath;
            
            $('#dag-switcher-change-dialog').dialog({
                title: dagSwitchDialogTitle,
                autoOpen: false,
                modal: true,
                buttons: { 
                    Ok: function() { 
                        var newDag = $('#dag-switcher-change-select').val();
                        doDagSwitch(newDag); 
                    }, 
                    Cancel: function() { $( this ).dialog( "close" ); } 
                }
            });

            $('#dag-switcher-change-button').click(function(e) {
                e.preventDefault();
                $('#dag-switcher-change-dialog').dialog('open');
            });

            $('#dag-switcher-current-dag-block').detach().insertAfter('#subheader').show();
        }
    };
})(window, document, jQuery);