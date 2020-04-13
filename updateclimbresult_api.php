<?php //DisplayProblem.phpから結果をタップされる毎に呼ばれるAPI
session_start();

require_once 'Env.php';

//JSON形式のPOSTデータを受け取る
$json = file_get_contents("php://input");
$param = json_decode($json, true);
if ($param === NULL) exit;
/*
$param['problemid']
$param['climbed'] -1:未踏 0:失敗 1:完登
*/

if (!isset($param['problemid'],$param['climbed'])) exit;

$problemid = $param['problemid'];
$climbed = $param['climbed'];

if (!is_numeric($problemid) || !is_numeric($climbed)) exit;

$result = [];
try {
    if ($climbed == -1) {
        //レコードを削除
        $sql = 'DELETE FROM `climb` WHERE `userid` = ? AND `problemid` = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($_SESSION["ID"], $problemid));
    } else if ($climbed == 0 || $climbed == 1) {
        //レコードを更新
        $sql = 'UPDATE `climb` SET `climbed` = ? WHERE `userid` = ? AND `problemid` = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($climbed, $_SESSION["ID"], $problemid));

        if ($stmt->rowCount() == 0) {
            //更新できなかったので、追加
            $sql = 'INSERT INTO `climb` (`climbed`, `userid`, `problemid`) VALUES (?, ?, ?)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($climbed, $_SESSION["ID"], $problemid));
        }
    } else {
        exit;
    }
    $result['error'] = 0;

} catch (PDOException $e) {
    $result['error'] = 1;
    $result['errorMessage'] = htmlspecialchars($e->getMessage(), ENT_QUOTES);
}

header("Access-Control-Allow-Origin: *");
echo json_encode($result);
?>
