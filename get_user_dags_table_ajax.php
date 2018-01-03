<?php
/**
 * REDCap External Module: DAG Switcher
 * @author Luke Stevens, Murdoch Children's Research Institute
 * Get HTML markup for DAG/user table columns (rows fetched by separate ajax)
 */
error_reporting(0);

// user must have DAG page permission 
if ($user_rights['data_access_groups'] != 1) { exit(''); }

$rowsAreDags = (isset($_GET['rowoption']) && $_GET['rowoption']==='users') ? false : true; // rows as dags is default

try {
        $module = new MCRI\DAGSwitcher\DAGSwitcher();
        $result = $module->getUserDAGsTable($rowsAreDags);
} catch (Exception $ex) {
        $result = '<div class="red">'.$ex->getMessage().'<br>'.nl2br($ex->getTraceAsString()).'</div>';
}
echo $result;