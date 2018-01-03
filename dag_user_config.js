/**
 * REDCap External Module: DAG Switcher
 * @author Luke Stevens, Murdoch Children's Research Institute
 * DAG page JavaScript
 */
'use strict';
var MCRI_DAG_Switcher_Config = (function(window, document, $, undefined) {
    var app_path_images
    var getTableAjaxPath;
    var getTableRowsAjaxPath;
    var setUserDagAjaxPath;
    
    function getTable() {
        $('#dag-switcher-table-container').hide().html('');
        $('#dag-switcher-spin').show();
        var rowoption = $('#dag-switcher-config-container input[name="rowoption"]:checked').val();
        $.get(getTableAjaxPath+'&rowoption='+rowoption).then(function(data) {
            $('#dag-switcher-spin').hide();
            $('#dag-switcher-table-container').html(data).show();
            initDataTable(rowoption);
        });
    }
    
    function initDataTable(rowoption) {
        var table = $('#dag-switcher-table').DataTable( { 
            paging: false,
            searching: true,
            scrollX: true,
            scrollY: "350px",
            scrollCollapse: true,
            fixedHeader: { header: true },
            fixedColumns: { leftColumns: 1 }, 
            ajax: getTableRowsAjaxPath+'&rowoption='+rowoption,
            columnDefs: [ 
                {
                    "targets": 0,
                    "render": function ( celldata, type, row ) {
                        return celldata.rowref;
                    }
                },
                {
                    "targets": "_all",
                    "render": function ( celldata, type, row ) {
                        var checked = (celldata.enabled)?"checked":"";
                        if (type==='display') {
                            return "<input type='checkbox' data-dag='"+celldata.dagid+"' data-user='"+celldata.user+"' "+checked+"></input><img src='"+app_path_images+"progress_circle.gif' style='display:none;'>";
                        } else {
                            return celldata.enabled+'-'+celldata.rowref; // for sorting
                        }
                    }
                }
            ]
        });
        
        $('#dag-switcher-table tbody').on('change', 'input', function () {
            var cb = $(this);
            var parentTd = cb.parent('td');
            var spinner = parentTd.find('img');

            cb.hide();
            spinner.show();

            var colour = '#ff3300'; // redish
            var user = cb.data('user');
            var dag = cb.data('dag');
            var enabled = cb.is(':checked');
            
            $.ajax({
                method: 'POST',
                url: setUserDagAjaxPath,
                data: { user: user, dag: dag, enabled: enabled },
                dataType: 'json'
            })
            .done(function(data) {
                if (data.result==='1') { 
                    colour = '#66ff99'; // greenish
                } else {
                    enabled = !enabled; // changing the selection failed so change it back to what it waa
                }
            })
            .fail(function(data) {
                console.log(data.result);
                enabled = !enabled; // changing the selection failed so change it back to what it waa
            })
            .always(function(data) {
                cb.prop('checked', enabled);
                parentTd.effect('highlight', {color:colour}, 3000);
                spinner.hide();
                cb.show();
            });
        });
        
        var searchBox = $('#dag-switcher-table_filter');
        var searchBoxParentPrevDiv = searchBox.parent().prev('div');
        searchBox.detach().appendTo(searchBoxParentPrevDiv).css('float', 'left');
    }

    function refreshTableData() {
        $('#dag-switcher-table').DataTable().ajax.reload( null, false );
    }
    
    return {
        initPage: function(app_path_img, getTablePath, getTableRowsPath, setUserDagPath) {
            app_path_images = app_path_img;
            getTableAjaxPath = getTablePath;
            getTableRowsAjaxPath = getTableRowsPath;
            setUserDagAjaxPath = setUserDagPath;
            
            $('#dag-switcher-config-container').delegate('input[name=rowoption]','change', function () {
                getTable();
            });
            $('#dag-switcher-config-container').detach().insertAfter('#group_table').show();
            getTable();
        }
    };
})(window, document, jQuery);