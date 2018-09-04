<?php
/**
 * REDCap External Module: DAG Switcher
 * @author Luke Stevens, Murdoch Children's Research Institute
 * Switch current user to DAG supplied
 */
error_reporting(0);
header("Content-Type: application/json");

try {
        if (!isset($_POST['dag'])) { echo json_encode(array('result'=>'No dag supplied')); }
        $result = $module->apiDagSwitch('RestUtility', $_POST['dag']);
} catch (Exception $ex) {
        http_response_code(500);
        $result = $ex->getMessage();
}
echo json_encode(array('result'=>$result));