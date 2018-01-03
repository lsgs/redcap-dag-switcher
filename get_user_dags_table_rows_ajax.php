<?php
/**
 * REDCap External Module: DAG Switcher
 * @author Luke Stevens, Murdoch Children's Research Institute
 * Get table rows (DAGs or users, as specified)
 */
error_reporting(0);
header("Content-Type: application/json");

if ($user_rights['data_access_groups'] != 1) { 
        $result = '0'; // user must have DAG page permission 
} else {

        $rowsAreDags = (isset($_GET['rowoption']) && $_GET['rowoption']==='users') ? false : true; // rows as dags is default

        try {
                $module = new MCRI\DAGSwitcher\DAGSwitcher();
                $result = $module->getUserDAGsTableRowData($rowsAreDags);
        } catch (Exception $ex) {
                http_response_code(500);
                $result = $ex->getMessage();
        }
}
$r=json_encode(array('data' => $result));
echo $r;