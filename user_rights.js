/**
 * REDCap External Module: DAG Switcher
 * @author Luke Stevens, Murdoch Children's Research Institute
 * Project page JavaScript for switching user DAG
 */
'use strict';
var MCRI_DAG_Switcher_User_Rights = (function(window, document, $, JSON, undefined) {
    var allDagNames;
    
    var makePopovers = function(userDags, dagNames) {
        allDagNames = dagNames;
        // get the dag name links for each user
        $('div.dagNameLinkDiv').each(function(){
            var dagLink = $(this).children('a:first');
            var gid = (dagLink.attr('gid')==='') ? 0 : dagLink.attr('gid');
            var uid = dagLink.attr('uid');

            // does this user currently have any other dags enabled?
            var otherDags = [];
            if (userDags[uid]) {
                userDags[uid].forEach(function(enabledDagId) {
                    if (gid!=enabledDagId) { otherDags.push(enabledDagId);}
                });
                if (otherDags.length>0) { appendDagInfo(dagLink, uid, otherDags); }
            }
        });
    };
    
    function appendDagInfo(appendAfter, user, dagIdList) {
        var content = '<div style=\'font-size:75%;padding:5px;\'>User <span class=\'text-primary\'>'+user+'</span> may switch to DAGs:<ul style=\'padding-left:10px;\'>';
        dagIdList.forEach(function(dagId) {
            var str = allDagNames[dagId];
            var singleDags = str.replace(/"/g, '');
            content += '<li><span class=\'text-info\'>'+singleDags+'</span></li>';
        });
        content += '</ul>';
        appendAfter.after(' <a href="#" data-toggle="popover" data-content="'+content+'" style="font-size:75%;color:gray;">(+'+dagIdList.length+')</a>');
    };
    
    var activatePopovers = function() {
        $('[data-toggle="popover"]').popover({
            title: 'DAG Switcher',
            html: true,
            trigger: 'hover',
            container: 'body',
            placement: 'right'
        });
    };
    
    return {
        makePopovers: function (userDags, dagNames) {
            makePopovers(userDags, dagNames);
        },
        activatePopovers: activatePopovers
    };
})(window, document, jQuery, JSON);
