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
    var table;
    var rowoption;
    var pageSize;
    
    function getTable() {
        $('#dag-switcher-em-table-container').hide().html('');
        $('#dag-switcher-em-spin').show();
        rowoption = $('#dag-switcher-em-config-container input[name="rowoption-em"]:checked').val();
        $.get(getTableAjaxPath+'&rowoption='+rowoption).then(function(data) {
            $('#dag-switcher-em-spin').hide();
            $('#dag-switcher-em-table-container').html(data).show();
            initDataTable(rowoption, pageSize);
        });
    }
    
    function initDataTable(rowoption, pageSize) {
        var pageOpt = [], pageOptLbl = [];
        if (pageSize>0) {
            pageOpt.push(pageSize);
            pageOptLbl.push(pageSize);
        }
        pageOpt.push(-1);
        pageOptLbl.push("All");
        table = $('#dag-switcher-em-table').DataTable( { 
            deferRender: true,
            stateSave: true,
            searching: true,
            pagingType: "full_numbers",
            lengthMenu: [ pageOpt, pageOptLbl ],
            scrollX: true,
            /*scrollY: "500px",*/
            scrollCollapse: true,
            /*fixedHeader: { header: true }, header does not stay in container*/
            fixedColumns: { leftColumns: 1 }, /*this does weird things to the row colouring and you get overlapping cell wording when sorting on other columns */
            ajax: getTableRowsAjaxPath+'&rowoption='+rowoption,
            columnDefs: [ 
                {
                    "targets": 0,
                    "className": "dag-switcher-em-table-left-col",
                    "render": function ( celldata, type, row ) {
                        var celltext;
                        if (typeof celldata.is_super !== 'undefined' && celldata.is_super) {
                            celltext = '<span style="color:#777;" title="Super users see all!">'+celldata.rowref+'</span>';
                        } else {
                            celltext = celldata.rowref;
                        }
                        return celltext;
                    }
                },
                {
                    "targets": "_all",
                    "render": function ( celldata, type, row ) {
                        var checked = (celldata.enabled!==undefined && celldata.enabled)?'checked':'';
                        var disabled = (celldata.is_super!==undefined && celldata.is_super)?'disabled title="Super users see all!"':'';
                        if (type==='display') {
                            return "<input type='checkbox' data-dag='"+celldata.dagid+"' data-user='"+celldata.user+"' "+checked+" "+disabled+"  title='"+celldata.dagname+" : "+celldata.user+"'></input><img src='"+app_path_images+"progress_circle.gif' style='display:none;'>";
                        } else {
                            return celldata.enabled+'-'+celldata.rowref; // for sorting
                        }
                    }
                }
            ]
        });
        
        $('#dag-switcher-em-table tbody').on('change', 'input', function () {
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
                console.log(data);
                enabled = !enabled; // changing the selection failed so change it back to what it waa
            })
            .always(function(data) {
                cb.prop('checked', enabled);
                parentTd.effect('highlight', {color:colour}, 3000);
                spinner.hide();
                cb.show();
            });
        });
    }

    function refreshTableData() {
        if (rowoption==='dags') {
            table.ajax.reload( null, false );
        } else {
            getTable();
        }
    }
    
    return {
        initPage: function(app_path_img, getTablePath, getTableRowsPath, setUserDagPath, setPageSize) {
            app_path_images = app_path_img;
            getTableAjaxPath = getTablePath;
            getTableRowsAjaxPath = getTableRowsPath;
            setUserDagAjaxPath = setUserDagPath;
            pageSize = setPageSize;
            
            $('#dag-switcher-em-config-container').delegate('input[name=rowoption-em]','change', function () {
                getTable();
            });
            $('#dag-switcher-em-config-container').detach().insertAfter('#dag-switcher-config-container-parent').show();
            getTable();

            // The hidedagMsg() function is called after adding/deleting a group
            // Augment it with a refresh of the dag/user table
            var originalMethod = window.hidedagMsg;
            window.hidedagMsg = function () {
                originalMethod.apply(window, arguments);
                refreshTableData(); 
            };
        }
    };
})(window, document, jQuery);