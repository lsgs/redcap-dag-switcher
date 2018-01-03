<?php
/**
 * REDCap External Module: DAG Switcher
 * @author Luke Stevens, Murdoch Children's Research Institute
 * Switch current user to DAG supplied
 */
error_reporting(0);
header("Content-Type: application/json");

try {
        $module = new MCRI\DAGSwitcher\DAGSwitcher();
        $newDag = $_GET['dag'];
        $result = $module->switchToDAG($newDag);
} catch (Exception $ex) {
        http_response_code(500);
        $result = $ex->getMessage();
}
echo json_encode(array('result'=>($result==$newDag)?1:0,'msg'=>$result));