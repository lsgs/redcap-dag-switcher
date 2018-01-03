<?php
/**
 * REDCap External Module: DAG Switcher
 * @author Luke Stevens, Murdoch Children's Research Institute
 * Enable or disable the specified DAG for the specified user
 */
error_reporting(0);
header("Content-Type: application/json");

if ($user_rights['data_access_groups'] != 1) { 
    $result = '0'; // user must have DAG page permission 
} else {
        try {
                $user = $_POST['user'];
                $dag = $_POST['dag'];
                $enabled = $_POST['enabled']=='true';

                $module = new MCRI\DAGSwitcher\DAGSwitcher();
                $result = $module->saveUserDAG($user, $dag, $enabled);
        } catch (Exception $ex) {
                http_response_code(500);
                $result = 'Exception: '.$ex->getMessage();
        }
}
$r=json_encode(array('result'=>$result));
echo $r;