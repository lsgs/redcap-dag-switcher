<?php
/**
 * REDCap External Module: DAG Switcher
 * Enable project users to switch between any number of the project's Data 
 * Access Groups (and/or "No Assignment")
 * @author Luke Stevens, Murdoch Children's Research Institute
 */
namespace MCRI\DAGSwitcher;

use ExternalModules\AbstractExternalModule;
use Exception;
use Logging;
use RCView;
use REDCap;
use UIState;

/**
 * REDCap External Module: DAG Switcher
 */
class DAGSwitcher extends AbstractExternalModule
{
        const MODULE_JS_VARNAME = 'MCRI_DAG_Switcher';
        
        private $lang;
        private $page;
        private $project_id;
        private $super_user;
        private $user;
        private $user_rights;
        
        public static $IgnorePages = array('FileRepository','ProjectSetup','ExternalModules','UserRights','DataAccessGroups');

        public function __construct() {
                parent::__construct();
                global $lang, $user_rights;
                $this->lang = $lang;
                $this->page = PAGE;
                $this->project_id = intval(PROJECT_ID);
                $this->super_user = SUPER_USER;
                $this->user = strtolower(USERID);
                $this->user_rights = $user_rights;
        }
        
        public function hook_every_page_top($project_id) {
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
         * most recent DAG Switcher record in redcap_log_event 
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
                $userDags = array();

                $sql = "select data_values ".
                       "from redcap_log_event ".
                       "where project_id = ".db_escape($this->project_id)." ".
                       "and description = '".db_escape($this->getModuleName())."' ".
                       "order by log_event_id desc limit 1 ";
                $r = db_query($sql);
                if ($r->num_rows > 0) {
                    while ($row = $r->fetch_assoc()) {
                        $userDags = json_decode($row['data_values'], true);
                    }
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

                if ($this->getUIStateValue('rowoption')==='users') {
                        $rowOptionCheckedD = ''; 
                        $rowOptionCheckedU = 'checked'; // rows are users, columns are dags
                } else {
                        $rowOptionCheckedD = 'checked'; // rows are dags, columns are users
                        $rowOptionCheckedU = '';
                }
                
                print RCView::div(array('id'=>'dag-switcher-config-container', 'class'=>'gray'),//,'style'=>'width:698px;display:none;margin-top:20px'), 
                            RCView::div(array('style'=>'float:right'), "<input type='radio' name='rowoption' value='dags' $rowOptionCheckedD>&nbsp; $dagTableRowOptionDags<br><input type='radio' name='rowoption' value='users' $rowOptionCheckedU>&nbsp; $dagTableRowOptionUsers<br>").
                            RCView::div(array('style'=>'font-weight:bold;font-size:120%'), RCView::img(array('src'=>'puzzle_small.png')).$dagTableBlockTitle).
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
                ?>
<style type="text/css">
    #dag-switcher-config-container { width:698px; display:none; margin-top:20px; }
    #dag-switcher-spin { width:100%; text-align:center; }
    #dag-switcher-table-container { width:100%; display:none; }
    #dag-switcher-table tr.odd { background-color: #f1f1f1 !important; }
    #dag-switcher-table tr.even { background-color: #fafafa !important; }
    #dag-switcher-table td.highlight { background-color: whitesmoke !important; }
</style>
<script type="text/javascript" src="<?php echo $jsPath;?>"></script>
<script type="text/javascript">
    $(document).ready(function() {
        var getTableAjaxPath = '<?php echo $getTablePath;?>';
        var getTableRowsAjaxPath = '<?php echo $getTableRowsPath;?>';;
        var setUserDagAjaxPath = '<?php echo $setUserDagPath;?>';;
        MCRI_DAG_Switcher_Config.initPage(app_path_images, getTableAjaxPath, getTableRowsAjaxPath, setUserDagAjaxPath);
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
                
                if ($rowsAreDags) { // columns are users
                        // column-per-user, row-per-dag (load via ajax)
                        $col0Hdr = $this->lang['global_22']; // Data Access Groups
                        $colGroupHdr = $this->lang['control_center_132']; // Users
                        $colSet = REDCap::getUsers();
                        $this->saveUIStateValue('rowoption', 'dags');
                } else { // $rowsAreDags===false // columns are dags
                        // column-per-dag, row-per-user (load via ajax)
                        $col0Hdr = $this->lang['control_center_132']; // Users
                        $colGroupHdr = $this->lang['global_22']; // Data Access Groups
                        $colSet = REDCap::getGroupNames(false);
                        asort($colSet); // sort associative arrays in ascending order, according to the value, preserving keys
                        $colSet = array(0=>$this->lang['data_access_groups_ajax_23']) + $colSet; // [No Assignment]
                        $this->saveUIStateValue('rowoption', 'users');
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
                asort($users); 

                $dags = REDCap::getGroupNames(false);
                asort($dags); // sort associative arrays in ascending order, according to the value, preserving keys
                $dags = array(0=>$this->lang['data_access_groups_ajax_23']) + $dags; // [No Assignment]
                
                $rows = array();
                
                if (count($dags)===1 || count($users)===0) {
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
                                            'enabled' => (in_array($dagId, $usersEnabledDags[$user]))?1:0
                                        );
                                }
                                $rows[] = $row;
                        }
                } else {
                        foreach ($users as $user) {
                                $row = array();
                                $row[] = array('rowref'=>$user);
                                foreach ($dags as $dagId => $dagName) {
                                        $row[] = array(
                                            'rowref' => $user,
                                            'dagid' => $dagId,
                                            'dagname' => $dagName,
                                            'user' => $user,
                                            'enabled' => (in_array($dagId, $usersEnabledDags[$user]))?1:0
                                        );
                                }
                                $rows[] = $row;
                        }
                }
                return $rows;
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
                $projDags = array(0=>$this->lang['data_access_groups_ajax_23']) + REDCap::getGroupNames();
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
                
                // save to new log_event record
                REDCap::logEvent(db_escape($this->getModuleName()), json_encode($userDags));
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
                        $dags = array(0=>$this->lang['data_access_groups_ajax_23']) + $dags; // [No Assignment]

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
                                        if (intval($id) !== intval($currentDagId)) {
                                                $thisUserOtherDags[$id] = $dags[$id];
                                        }
                                }

                                asort($thisUserOtherDags); // sort dag names alphabetically in dialog, preserving keys

                                $changeButton = RCView::button(array('id'=>'dag-switcher-change-button', 'class'=>'btn btn-sm btn-primary'), $dagSwitchDialogBtnText);

                                $dagSelect = RCView::select(array('id'=>'dag-switcher-change-select', 'class'=>'form-control'), $thisUserOtherDags);

                                print RCView::div(
                                        array('id'=>'dag-switcher-change-dialog'),
                                        RCView::div(array('style'=>'margin:5px 0;'), $dagSwitchDialogText).
                                        $dagSelect
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
         * @param string $newDag
         * @return string New DAG id on successful switch, error message on fail
         */
        public function switchToDAG($newDag) {
                
                $userDags = $this->getUserDAGs();

                $projDags = array(0=>$this->lang['data_access_groups_ajax_23']) + REDCap::getGroupNames();
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
         * Users' current DAG display is augmentied to indicate where user may 
         * switch to other DAGs.
         */
        protected function includeUserRightsPageJs() {
                $jsPath = $this->getUrl('user_rights.js');
                $userDags = $this->getUserDAGs();
                $dagNames = array(0=>$this->lang['data_access_groups_ajax_23']) + REDCap::getGroupNames(false);
                ?>
<script type="text/javascript" src="<?php echo $jsPath;?>"></script>
<script type="text/javascript">
    $(document).ready(function() {
        var userDags = JSON.parse('<?php echo json_encode($userDags);?>');
        var dagNames = JSON.parse('<?php echo json_encode($dagNames);?>');
        MCRI_DAG_Switcher_User_Rights.makePopovers(userDags, dagNames);        
    });
    
    $(window).load(function() {
        MCRI_DAG_Switcher_User_Rights.activatePopovers();        
    });
</script>
                <?php
        }

        // TODO Remove these UI state config methods once they are implemented in AbstractExternalModule
        
        /**
         * Return a value from the UI state config. Return null if key doesn't exist.
         * @param int/string $key key
         * @return mixed - value if exists, else return null
         */
        public function getUIStateValue($key)
	{
                return UIState::getUIStateValue($this->project_id/*self::detectProjectId()*/, get_class($this), $key);
	}
	
        /**
         * Save a value in the UI state config (e.g., $object = 'sidebar')
         * @param int/string $key key
         * @param mixed $value value for key
         */
	public function saveUIStateValue($key, $value)
	{
		UIState::saveUIStateValue($this->project_id/*self::detectProjectId()*/, get_class($this), $key, $value);
	}
	
        /**
         * Remove key-value from the UI state config
         * @param int/string $key key
         */
	public function removeUIStateValue($key)
	{
		UIState::removeUIStateValue($this->project_id/*self::detectProjectId()*/, get_class($this), $key);
	}
}