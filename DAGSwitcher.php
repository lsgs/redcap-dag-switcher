<?php
/**
 * REDCap External Module: DAG Switcher
 * Enable project users to switch between any number of the project's Data 
 * Access Groups (and/or "No Assignment")
 * @author Luke Stevens, Murdoch Children's Research Institute
 */
namespace MCRI\DAGSwitcher;

use ExternalModules\AbstractExternalModule;
use \ExternalModules\ExternalModules;
use Logging;
use RCView;
use Project;
use REDCap;

/**
 * REDCap External Module: DAG Switcher
 */
class DAGSwitcher extends AbstractExternalModule
{
	const UI_STATE_OBJECT_PREFIX = 'external-modules.';
        
        const MODULE_JS_VARNAME = 'MCRI_DAG_Switcher';
        
        private $lang;
        private $page;
        private $project_id;
        private $super_user;
        private $user;
        private $user_rights;
        private $Proj;
        
        public static $IgnorePages = array('FileRepository','ProjectSetup','ExternalModules','UserRights','DataAccessGroups','SendItController');
        
        private static $SettingDefaults = array(
		"dag-switcher-block-text-pre" => "Current Data Access Group: ",
                "dag-switcher-block-text-post" => " ",
                "dag-switcher-dialog-title" => "Switch Data Access Group",
		"dag-switcher-dialog-text" => "Select the DAG to switch to: ",
		"dag-switcher-dialog-change-button-text" => "Switch",
		"dag-switcher-table-block-title" => "DAG Switcher: Enable Multiple DAGs for Users",
		"dag-switcher-table-block-info" => "Let users switch between the DAGs enabled for them below.<br>Note: <strong>this does not override a user's <u>current</u> DAG allocation</strong>, as set above or on the User Rights page.",
		"dag-switcher-table-row-option-dags" => "Rows are DAGs",
		"dag-switcher-table-row-option-users" => "Rows are Users"
        );

        public function __construct() {
                parent::__construct();
                global $Proj, $lang, $user_rights;
                $this->lang = &$lang;
                $this->page = PAGE;
                $this->project_id = intval(PROJECT_ID);
                $this->super_user = SUPER_USER;
                $this->user = strtolower(USERID);
                $this->user_rights = &$user_rights;
                $this->Proj = $Proj;
        }
        
        public function hook_every_page_top($project_id) {
                global $Proj;
                if (!isset($this->Proj)) { return; } // return if no project context even if project_id set (e.g. in export SendIController)

                $pageRoute = $this->getPageRoute();
                
                if ($pageRoute==='DataAccessGroups') {
                        
                        $this->renderDAGPageTableContainer();
                        $this->includeDAGPageJs();
                
                } else if ($pageRoute==='UserRights') {
                        
                        $this->includeUserRightsPageJs();
                
                }                                                           // Include display of current dag and option to switch when...
                else if (isset($this->project_id) && $this->project_id>0 && // on a project page, and
                    !($pageRoute==='DataEntry' && isset($_GET['id']))  &&   // not a data entry page with a record selected, and
                    !in_array($pageRoute, self::$IgnorePages)) {    // not on a page irrelevant to records
                        
                        $this->renderUserDAGInfo();
                        $this->includeProjectPageJs();
                } 
                else if (isset($this->project_id) && $this->project_id>0) {
                        $this->includeEveryPageJs();
                }
        }

        /**
         * @return string  A short reference to the current page
         */
        protected function getPageRoute() {
                // $this->page examples
                // index.php                              Project Home
                // ProjectSetup/index.php                 Project Setup
                // DataEntry/record_home.php              Record Home
                // DataEntry/record_status_dashboard.php  Record Status Dashboard
                // DataImportController:index             Data Import
                $pageRoute= '';
                $slash1Pos = strpos($this->page, '/');
                $colon1Pos = strpos($this->page, ':');
                if (strpos($this->page, 'ExternalModules')!==false) {
                        $pageRoute = 'ExternalModules';
                } else if ($slash1Pos > 0) {
                        $pageRoute = substr($this->page, 0, $slash1Pos);
                } else if ($colon1Pos > 0) {
                        $pageRoute = substr($this->page, 0, $colon1Pos);
                }
                return $pageRoute;
        }
        
        /**
         * Read the current configuration of users and enabled DAGs from the 
         * user-dag-mapping project setting (or fall back to most recent DAG 
         * Switcher record in redcap_log_event where stored until v1.2.1 - 
         * removed in v1.3.0)
         * @return array 
         *  keys: Usernames
         *  values: Array of DAGids user may switch to
         * [
         *   "user1": [],
         *   "user2: [0,123,124],
         *   "user3": [123,124]
         * ]
         */
        public function getUserDAGs() {
                $updateConfig = false;
                $userDags = json_decode($this->getProjectSetting('user-dag-mapping'), true);
                
                if (!is_array($userDags)) {
                        $userDags = array();
                }
                
                // return only valid group_id values (remove any DAGs that have been deleted)
                $sql = "select group_id from redcap_data_access_groups where project_id = ".db_escape($this->project_id)." ";
                $r = db_query($sql);
                if ($r->num_rows > 0) {
                        $currentDagIds = array();
                        while ($row = $r->fetch_assoc()) {
                                $currentDagIds[] = $row['group_id'];
                        }
                }
                
                foreach ($userDags as $user => $dags) {
                        foreach ($dags as $dagKey => $dagId) {
                                if ($dagId!=0 && !in_array($dagId, $currentDagIds)) {
                                        unset($userDags[$user][$dagKey]);
                                        $updateConfig = true;
                                }
                        }
                }
                
                if ($updateConfig) {
                        $this->setProjectSetting('user-dag-mapping', json_encode($userDags, JSON_PRETTY_PRINT));
                }
                
                return $userDags;
        }
        
        /**
         * Print table container to DAGs page. Table and user/DAG data fetched by ajax call.
         */
        protected function renderDAGPageTableContainer() {
            
                $dagTableBlockTitle = REDCap::filterHtml($this->getProjectSetting('dag-switcher-table-block-title'));
                $dagTableBlockInfo = REDCap::filterHtml($this->getProjectSetting('dag-switcher-table-block-info'));
                $dagTableRowOptionDags = REDCap::filterHtml($this->getProjectSetting('dag-switcher-table-row-option-dags'));
                $dagTableRowOptionUsers = REDCap::filterHtml($this->getProjectSetting('dag-switcher-table-row-option-users'));

                if ($this->getUserSetting('rowoption')==='users') {
                        $rowOptionCheckedD = ''; 
                        $rowOptionCheckedU = 'checked'; // rows are users, columns are dags
                } else {
                        $rowOptionCheckedD = 'checked'; // rows are dags, columns are users
                        $rowOptionCheckedU = '';
                }
                
                print RCView::div(array('id'=>'dag-switcher-config-container', 'class'=>'gray'),//,'style'=>'width:698px;display:none;margin-top:20px'), 
                            RCView::div(array('style'=>'float:right'), "<input type='radio' name='rowoption' value='dags' $rowOptionCheckedD>&nbsp; $dagTableRowOptionDags<br><input type='radio' name='rowoption' value='users' $rowOptionCheckedU>&nbsp; $dagTableRowOptionUsers<br>").
                            RCView::div(array('style'=>'font-weight:bold;font-size:120%'), RCView::i(array('class'=>'fas fa-cube fs14 mr-1')).$dagTableBlockTitle).
                            RCView::div(array('style'=>'margin:10px 0;'), $dagTableBlockInfo).
                            RCView::div(array('id'=>'dag-switcher-spin'),//, 'style'=>'width:100%;text-align:center;'),
                                    RCView::img(array('src'=>'progress_circle.gif'))
                            ).
                            RCView::div(array('id'=>'dag-switcher-table-container'),//, 'style'=>'width:100%;display:none;'),
                                    ''
                            )
                );
        }
        
        /**
         * Print DAG Switcher JavaScript code to DAGs page.
         */
        protected function includeDAGPageJs() {
                $jsPath = $this->getUrl('dag_user_config.js');
                $getTablePath = $this->getUrl('get_user_dags_table_ajax.php');
                $getTableRowsPath = $this->getUrl('get_user_dags_table_rows_ajax.php');
                $setUserDagPath = $this->getUrl('set_user_dag_ajax.php');
                
                $pageSize = $this->getProjectSetting('page-at-n-rows');
                $pageSize = (!is_null($pageSize) && intval($pageSize)>0) 
                        ? intval($pageSize)
                        : $pageSize = -1; // All
                
                ?>
<style type="text/css">
    #dag-switcher-config-container { width:100%; display:none; margin-top:20px; }
    #dag-switcher-spin { width:100%; text-align:center; }
    #dag-switcher-table-container { width:100%; display:none; }
    #dag-switcher-table tr.odd { background-color: #f1f1f1 !important; }
    #dag-switcher-table tr.even { background-color: #fafafa !important; }
    #dag-switcher-table td { text-align: center; }
    #dag-switcher-table td.highlight { background-color: whitesmoke !important; }
    .DTFC_LeftBodyLiner { overflow-x: hidden; }
    .dag-switcher-table-left-col { max-width: 300px; overflow: hidden; text-align: left; }
</style>
<script type="text/javascript" src="<?php echo $jsPath;?>"></script>
<script type="text/javascript">
    $(document).ready(function() {
        var getTableAjaxPath = '<?php echo $getTablePath;?>';
        var getTableRowsAjaxPath = '<?php echo $getTableRowsPath;?>';;
        var setUserDagAjaxPath = '<?php echo $setUserDagPath;?>';;
        var pageSize = <?php echo $pageSize;?>;;
        MCRI_DAG_Switcher_Config.initPage(app_path_images, getTableAjaxPath, getTableRowsAjaxPath, setUserDagAjaxPath, pageSize);
    });
</script>
                <?php
        }

        /**
         * Get HTML markup for table, including header columns of the selected 
         * type (dags or users)
         * @param bool $rowsAreDags Set to <i>true</i> to get column per user.
         * Set to <i>false</i> to get column per DAG.
         * @return string HTML 
         */
        public function getUserDAGsTable($rowsAreDags=true) {
                $html = '';
                $superusers = array();
                
                if ($rowsAreDags) { // columns are users
                        // column-per-user, row-per-dag (load via ajax)
                        $col0Hdr = $this->lang['global_22']; // Data Access Groups
                        $colGroupHdr = $this->lang['control_center_132']; // Users
                        $colSet = REDCap::getUsers();
                        uasort($colSet, array($this,'value_compare_func')); // sort in ascending order by value, case-insensitive, preserving keys
                        $this->setUserSetting('rowoption', 'dags');
                        $superusers = $this->readSuperUserNames();
                } else { // $rowsAreDags===false // columns are dags
                        // column-per-dag, row-per-user (load via ajax)
                        $col0Hdr = $this->lang['control_center_132']; // Users
                        $colGroupHdr = $this->lang['global_22']; // Data Access Groups
                        $colSet = REDCap::getGroupNames(false);
                        uasort($colSet, array($this,'value_compare_func')); // sort in ascending order by value, case-insensitive, preserving keys
                        $colSet = array(0=>$this->lang['data_access_groups_ajax_23']) + (array)$colSet; // [No Assignment]
                        $this->setUserSetting('rowoption', 'users');
                }
                
                $colhdrs = RCView::tr(array(),
                        RCView::th(array('rowspan'=>2, 'class'=>'', 'style'=>'border-top: 0px none; font-size:12px; padding:3px; white-space:normal; vertical-align:bottom;'),
                                RCView::div(array('style'=>'font-weight:bold;'),
                                        $col0Hdr 
                                )
                        ).
                        RCView::th(array('colspan'=>count($colSet), 'class'=>'', 'style'=>'border-top: 0px none; font-size:12px; padding:3px; white-space:normal; vertical-align:bottom;'),
                                RCView::div(array('style'=>'font-weight:bold;'),
                                        $colGroupHdr
                                )
                        )
                );
                foreach ($colSet as $col) {
                        if ($rowsAreDags && in_array($col, $superusers)) {
                                $col = RCView::span(array('style'=>'color:#777;','title'=>'Super users see all!'),$col);
                        }
                        $colhdrs .= RCView::th(array('class'=>'', 'style'=>'border-top: 0px none; font-size: 12px; text-align: center; padding: 3px; white-space: normal; vertical-align: bottom; width: 22px;'),
                                RCView::div(array('style'=>'font-weight:normal;'),
                                        RCView::span(array('class'=>'vertical-text'),
                                                RCView::span(array('class'=>'vertical-text-inner'),
                                                        $col
                                                )
                                        )
                                )
                        );
                }

                $html = RCView::table(array('class'=>'table table-striped table-bordered display nowrap compact no-footer','id'=>'dag-switcher-table'),
                                RCView::thead(array(), $colhdrs)
                        );

                return $html;
        }

        /**
         * Get table row data - rows as DAGs or rows as users, as appropriate
         * @param bool $rowsAreDags Set to <i>true</i> to get column per user.
         * Set to <i>false</i> to get column per DAG.
         * @return array
         */
        public function getUserDAGsTableRowData($rowsAreDags=true) {
                // [
                //   "user1": []
                //   "user2: [0,123,124]
                //   "user3": [123,124]
                // ]
                $usersEnabledDags = $this->getUserDAGs();

                $users = REDCap::getUsers();
                uasort($users, array($this,'value_compare_func')); // sort in ascending order by value, case-insensitive, preserving keys

                $dags = REDCap::getGroupNames(false);
                uasort($dags, array($this,'value_compare_func')); // sort in ascending order by value, case-insensitive, preserving keys
                $dags = array(0=>$this->lang['data_access_groups_ajax_23']) + (array)$dags; // [No Assignment]
                
                $rows = array();
                $superusers = $this->readSuperUserNames();
                
                if (count($users)===0) { // can only be a superuser viewing an orphan project so don't need anything fancy returned
                        $rows = null; 
                } else if ($rowsAreDags) {
                        foreach ($dags as $dagId => $dagName) {
                                $row = array();
                                $row[] = array('rowref'=>$dagName);
                                foreach ($users as $user) {
                                        $row[] = array(
                                            'rowref' => $dagName,
                                            'dagid' => $dagId,
                                            'dagname' => $dagName,
                                            'user' => $user,
                                            'enabled' => (in_array($dagId, $usersEnabledDags[$user]))?1:0,
                                            'is_super' => (in_array($user, $superusers))?1:0
                                        );
                                }
                                $rows[] = $row;
                        }
                } else {
                        foreach ($users as $user) {
                                $row = array();
                                $row[] = array('rowref'=>$user,'is_super' => (in_array($user, $superusers))?1:0);
                                foreach ($dags as $dagId => $dagName) {
                                        $row[] = array(
                                            'rowref' => $user,
                                            'dagid' => $dagId,
                                            'dagname' => $dagName,
                                            'user' => $user,
                                            'enabled' => (in_array($dagId, $usersEnabledDags[$user]))?1:0,
                                            'is_super' => (in_array($user, $superusers))?1:0
                                        );
                                }
                                $rows[] = $row;
                        }
                }
                return $rows;
        }

        /**
         * Read the list of superusers' usernames
         * @return array Usernames of superusers
         */
        protected function readSuperUserNames() {
                $superusers = array();
                
                $r = db_query('select username from redcap_user_information where super_user=1');
                if ($r->num_rows > 0) {
                        while ($row = $r->fetch_assoc()) {
                                $superusers[] = $row['username'];
                        }
                }
                return $superusers;
        }
        
        /**
         * Enable or disable a DAG for a user
         * @param int $user Valid username for project
         * @param int $dag Valid DAG id for project
         * @param int $enabled 1 to enable, 0 to disable
         * @return string '1' on successful save, '0' on failure
         */
        public function saveUserDAG($user, $dag, $enabled) {
                $enabled = (bool)$enabled;
                $projDags = array(0=>$this->lang['data_access_groups_ajax_23']) + (array)REDCap::getGroupNames();
                $projUsers = REDCap::getUsers();
                if (!array_key_exists($dag, $projDags) || !in_array($user, $projUsers)) { return '0'; } // invalid dag or user
            
                $userDags = $this->getUserDAGs();

                $key = array_search($dag, $userDags[$user]);
                
                if ($enabled) {
                    if ($key===false || is_null($key)) { $userDags[$user][] = $dag; } // dag id not present - add it now dag is enabled for user
                } else {
                    if ($key!==false) { 
                        unset($userDags[$user][$key]); 
                        $userDags[$user] = array_values($userDags[$user]); // rebase keys
                    } // dag id present - remove it now dag is disabled for user
                }
                sort($userDags[$user], SORT_NUMERIC);
                
                // save to module config
                $this->setProjectSetting('user-dag-mapping', json_encode($userDags, JSON_PRETTY_PRINT));
                REDCap::logEvent(db_escape($this->getModuleName()), json_encode($userDags, JSON_PRETTY_PRINT));
                return '1'; 
        }

        /**
         * If user may access more than one DAG then include a block at the top 
         * of the page which displays the current dag, and a button to switch to
         * another enabled DAG.
         */
        protected function renderUserDAGInfo() {
            
                if ($this->super_user) { return; } // super user always sees all
                

                $dags = REDCap::getGroupNames(false);

                if ($dags !== false) {
                        $dags = array(0=>$this->lang['data_access_groups_ajax_23']) + (array)$dags; // [No Assignment]

                        $changeButton = '';
                        $userDags = $this->getUserDAGs();

                        if (isset($userDags[$this->user]) && count($userDags[$this->user]) > 1) {

                                $pageBlockTextPre = REDCap::filterHtml($this->getProjectSetting('dag-switcher-block-text-pre'));
                                $pageBlockTextPost = REDCap::filterHtml($this->getProjectSetting('dag-switcher-block-text-post'));
                                $dagSwitchDialogText = REDCap::filterHtml($this->getProjectSetting('dag-switcher-dialog-text'));
                                $dagSwitchDialogBtnText = REDCap::filterHtml($this->getProjectSetting('dag-switcher-dialog-change-button-text'));

                                $currentDagId = ($this->user_rights['group_id'] !== '') ? 1*$this->user_rights['group_id'] : 0;
                                $currentDagName = $dags[$currentDagId];

                                $thisUserOtherDags = array();

                                foreach ($userDags[$this->user] as $id) {
                                        if (array_key_exists($id, $dags) && intval($id) !== intval($currentDagId)) {
                                                $thisUserOtherDags[$id] = $dags[$id];
                                        }
                                }

                                uasort($thisUserOtherDags, array($this,'value_compare_func')); // sort dag names alphabetically in dialog, preserving keys

                                $changeButton = RCView::button(array('id'=>'dag-switcher-change-button', 'class'=>'btn btn-sm btn-primaryrc'), '<i class="fas fa-random mr-1"></i>'.$dagSwitchDialogBtnText);

                                $dagSelect = RCView::select(array('id'=>'dag-switcher-change-select', 'class'=>'form-control'), $thisUserOtherDags);

                                $apiMsg = '';
                                if ($this->user_rights['api_export'] || $this->user_rights['api_import']) {
                                        $apiMsg = RCView::div(
                                            array('class'=>'blue', 'style'=>'margin:5px 0;'), 
                                            RCView::img(array('src'=>'computer.png')).
                                            RCView::span(array(), 'You may also use the <strong>API</strong>. ').
                                            RCView::a(
                                                    array('href'=>'javascript:', 'onclick'=>'if($("#dag-switch-api-info").is(":hidden")){$(this).html("[show less]");$("#dag-switch-api-info").slideDown();}else{$(this).html("[show more]");$("#dag-switch-api-info").slideUp();};'), 
                                                    RCView::span(array(), '[show info]')
                                            ).
                                            RCView::div(
                                                array('id'=>'dag-switch-api-info', 'style'=>'display:none;margin-top:10px;'), 
                                                '<strong>POST</strong> "token" and "dag" (id or unique name) to your API endpoint.<br>You must include the query string shown in this example:<br><span style="font-family:monospace;font-size:80%;">curl -d "token=ABCDEF0123456789ABCDEF0123456789&dag=site_a" <br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"'.APP_PATH_WEBROOT_FULL.'/api/?NOAUTH&type=module&prefix=dag_switcher&page=user_dag_switch_api"</span>'
                                            )
                                        );
                                }
                                
                                print RCView::div(
                                        array('id'=>'dag-switcher-change-dialog'),
                                        RCView::div(array('style'=>'margin:5px 0;'), $dagSwitchDialogText).
                                        $dagSelect.
                                        $apiMsg
                                );

                                print RCView::div(
                                        array('id'=>'dag-switcher-current-dag-block', 'class'=>'blue', 'style'=>'text-align:center;'),
                                        RCView::img(array('src'=>'information_frame.png')).
                                        RCView::span(array('style'=>'margin:0 10px; font-size:110%;'),
                                            $pageBlockTextPre.
                                            RCView::span(array('style'=>'font-weight:bold;margin:0 5px;'),$currentDagName).
                                            $pageBlockTextPost
                                        ).
                                        $changeButton
                                );
                        }
                }
        }

        /**
         * Print DAG Switcher JavaScript code for displaying current DAG and 
         * switching to another enabled DAG to project pages.
         */
        protected function includeProjectPageJs() {
                $jsPath = $this->getUrl('dag_switch.js');
                $savePath = $this->getUrl('user_dag_switch_ajax.php');
                $dagSwitchDialogTitle = REDCap::filterHtml($this->getProjectSetting('dag-switcher-dialog-title'));
                ?>
<style type="text/css">
    #dag-switcher-current-dag-block {
        text-align:center;
        margin:-15px 0 15px 0;
    }
</style>
<script type="text/javascript" src="<?php echo $jsPath;?>"></script>
<script type="text/javascript">
    $(document).ready(function() {
        var savePath = '<?php echo $savePath;?>';
        var dagSwitchDialogTitle = '<?php echo $dagSwitchDialogTitle;?>';;
        MCRI_DAG_Switcher_Switch.init(savePath, dagSwitchDialogTitle);
    });
</script>
                <?php
        }

        /**
         * Switch current user to the dag id provided
         * @param string $newDag id or unique name of DAG to switch to
         * @return string New DAG id on successful switch, error message on fail
         */
        public function switchToDAG($newDag) {
                
                $userDags = $this->getUserDAGs();

                $projDags = array(0=>$this->lang['data_access_groups_ajax_23']) + (array)REDCap::getGroupNames(true);
                
                if (!is_int($newDag)) {
                        foreach ($projDags as $id => $uname) {
                                if ($uname===$newDag) {
                                        $newDag = $id;
                                        break;
                                }
                        }
                }
                
                if (!array_key_exists($newDag, $projDags)) { return 'Invalid DAG'; }
                
                if (!array_key_exists($this->user, $userDags)) { return 'Invalid user'; }
                if (!in_array($newDag, $userDags[$this->user])) { return 'User/DAG assignment not permitted'; }

                // Code below adapted from redcap_v6.14.0/DataAccessGroups/data_access_groups_ajax.php
                if ($newDag == 0) {
                        $newDagVal = "NULL";
                        $logging_msg = "Remove user from data access group";
                        $group_name = $projDags[$this->user_rights['group_id']]; // group removing _from_ i.e. to "No assignment"
                } else {
                        $newDagVal = $newDag;
                        $logging_msg = "Assign user to data access group";
                        $group_name = $projDags[$newDag];
                }
                $sql = "update redcap_user_rights set group_id = ".db_escape($newDagVal)." where username = '".db_escape($this->user)."' and project_id = ".db_escape($this->project_id)." limit 1";
                $q = db_query($sql);

                if ($q) {
                        Logging::logEvent($sql,"redcap_user_rights","MANAGE",$this->user,"user = '$this->user',\ngroup = '" . $group_name . "'",$logging_msg);
                        return $newDag;
                }
                return 'Could not update user rights';
        }
        
        /**
         * Print DAG Switcher JavaScript code to User Rights page.
         * Users' current DAG display is augmented to indicate where user may 
         * switch to other DAGs.
         */
        protected function includeUserRightsPageJs() {
                $jsPath = $this->getUrl('user_rights.js');
                $userDags = $this->getUserDAGs();
                $dagNames = array(0=>$this->lang['data_access_groups_ajax_23']) + (array)REDCap::getGroupNames(false);
                $dagNames = array_map('htmlentities', $dagNames); // encode quotes etc. in dag names
                
                ?>
<script type="text/javascript" src="<?php echo $jsPath;?>"></script>
<script type="text/javascript">
    $(document).ready(function() {
        var userDags = JSON.parse('<?php echo json_encode($userDags);?>');
        var dagNames = JSON.parse('<?php echo json_encode($dagNames, JSON_HEX_APOS);?>');
        MCRI_DAG_Switcher_User_Rights.makePopovers(userDags, dagNames);        
        MCRI_DAG_Switcher_User_Rights.activatePopovers();        
    });
</script>
                <?php
        }

         /**
         * All project pages - inject current site name to Data Collection and 
         * Reports menu section headings
         */
        protected function includeEveryPageJs() {
                $dags = REDCap::getGroupNames(false);
                if ($dags !== false) {
                        $userDags = $this->getUserDAGs();
                        if (isset($userDags[$this->user]) && count($userDags[$this->user]) > 1) {
                                $currentDagId = ($this->user_rights['group_id'] !== '') ? 1*$this->user_rights['group_id'] : 0;
                                $currentDagName = ($currentDagId===0) ? $this->lang['dashboard_12'] : $dags[$currentDagId]; //dashboard_12 = "All"
                                echo "<span class='dag-switcher-dag-name' style='display:none;'>$currentDagName</span>";
                                ?>
<script type="text/javascript">
    $(window).on('load', function() {
        var dagName = $('.dag-switcher-dag-name');
        dagName.appendTo('div.x-panel-header > div:contains("<?php echo $this->lang['bottom_47'];?>")').css('color','#888').css('margin-left','3px').show(); //Data Collection
        dagName.clone().appendTo('div.x-panel-header > div:contains("<?php echo $this->lang['app_06'];?>")'); //Reports
    });
</script>
                                <?php
                        }
                }
        }

        public function apiDagSwitch($RestUtility, $newDag) {
                global $Proj;
                
                // look up token and set project context
                $request = $RestUtility::processRequest(true);

                $this->user = $request->getRequestVars()['username'];
                $this->project_id = $request->getRequestVars()['projectid'];
                $Proj = $this->Proj = new Project($this->project_id);

                if(!ExternalModules::getProjectSetting($this->PREFIX, $this->project_id, ExternalModules::KEY_ENABLED)) { 
                        return "The requested module is currently disabled on this project."; 
                }

                return $this->switchToDAG($newDag);
        }
        
        /**
         * Set default parameter values (using "default":"xyz" in config.json is deprecated)
         * @param type $version
         * @param type $project_id
         */
        public function redcap_module_project_enable($version, $project_id) {
                foreach (self::$SettingDefaults as $settingKey => $settingValue) {
                        $this->setProjectSetting($settingKey, $settingValue);
                }
        }
        
        /**
         * value_compare_func
         * Can't get asort($users, SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
         * to sort user and dag names in natural, case insensitive, order, so 
         * using user sort, uasort(), with this as compare function.
         * @param string $a
         * @param string $b
         * @return string
         */
        private static function value_compare_func(string $a, string $b) {
                return strcmp(strtolower($a), strtolower($b));
        }
}