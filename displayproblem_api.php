<?php //DisplayProblem.phpから表示毎に呼ばれるAPI
require_once 'Env.php';
session_start();

//日付を書式化
function date2str($dt) {
    if (date('Ymd') == date('Ymd', $dt)) {
       return '今日 '.date('H時i分', $dt);
    } else if (date('Ymd', strtotime('-1 day')) == date('Ymd', $dt)) {
        return '昨日 '.date('H時i分', $dt);
    } else if (date('Ym') == date('Ym', $dt)) {
        return '今月 '.date('d日', $dt);
    } else if (date('Y') == date('Y', $dt)) {
        return date('m月d日', $dt);
    } else {
        return date('Y年m月d日', $dt);
    }
}

//JSON形式のPOSTデータを受け取る
$json = file_get_contents("php://input");
$param = json_decode($json, true);
if ($param === NULL) exit;
//$param['id'] = 17;

/*
$param['id']    problem.id
*/

//結果配列を初期化
$result = [];
$result['error'] = 0;
$result['errorMessage'] = '';

$dsn = sprintf('mysql: host=%s; dbname=%s; charset=utf8', $db['host'], $db['dbname']);

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));

    //problem.userid から userdata.name を抜き出すために内部結合を使用
    $sql = <<<EOD
        SELECT
            `problem`.*, `userdata`.`name`, `userdata`.`dispname`
        FROM
            `problem`
        INNER JOIN
            `userdata`
        ON
            `problem`.`userid` = `userdata`.`id`
        WHERE
            `problem`.`id` = ?
EOD;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($param['id']));

    //$row[]に課題データを読み込む
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['publish'] == 0 && $row['userid'] != $_SESSION["ID"]) {
            $result['error'] = 1;
            $result['errorMessage'] = '指定された課題は非公開に設定されています';
        } else {

            $result['problemid'] = $row['id'];
            $result['active'] = $row['active'];
            $result['userid'] = $row['userid'];
            $result['name'] = htmlspecialchars((is_null($row['dispname'])) ? $row['name'] : $row['dispname'], ENT_QUOTES);
            $result['title'] = htmlspecialchars($row['title'], ENT_QUOTES);
            $result['grade'] = $grades[$row['grade']];
            $locations = '';
            foreach (explode(',', $row['location']) as $value) {
                if (strlen($value) > 0 && isset($walls[$value])) {
                    if (strlen($locations) > 0) $locations .= ' ';
                    $locations .= $walls[$value];
                }
            }
            $result['locations'] = $locations;
            $result['foorfree'] = $row['footfree'];
            $result['publish'] = $row['publish'];
            $result['comment'] = htmlspecialchars($row['comment'], ENT_QUOTES);
            $result['createdon'] = date2str(strtotime($row['createdon']));
            $result['imagefile'] = (strlen($row['imagefile']) > 0) ? $urlpaths['problem_image'] . $row['imagefile'] : '';
 

            $sql = "SELECT * FROM `climb` WHERE `userid` = ? AND `problemid` = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($_SESSION["ID"], $param['id']));
 
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $result['climbed'] = $row['climbed']; //0 or 1
            } else {
                $result['climbed'] = -1;
            }
        }
    } else {
        $result['error'] = 1;
        $errorMessage = '指定された課題が見つかりませんでした';
    }
} catch (PDOException $e) {
    $errorMessage = $e->getMessage();  //デバッグ
}


header("Access-Control-Allow-Origin: *");
echo json_encode($result);
?>
