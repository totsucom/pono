<?php //DisplayProblem.phpから表示毎に呼ばれるAPI
session_start();

require_once 'Env.php';

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

try {
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


            $sql =<<<EOD
                SELECT
                    `climbmovie`.`moviefile`, `climbmovie`.`imagefile_t`, `userdata`.`name`, `userdata`.`dispname`
                FROM
                    `climbmovie`
                INNER JOIN
                    `userdata`
                ON
                    `climbmovie`.`userid` = `userdata`.`id`
                WHERE
                    `climbmovie`.`problemid` = ?
EOD;
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($result['problemid']));

            $result['movies'] = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ar = [];
                $ar['moviefile'] = $urlpaths['comp_movie'] . $row['moviefile'];     //動画パス  
                $ar['imagefile_t'] = $urlpaths['comp_movie'] . $row['imagefile_t']; //サムネイルパス
                $ar['name'] = htmlspecialchars((is_null($row['dispname'])) ? $row['name'] : $row['dispname'], ENT_QUOTES); //投稿者
                $result['movies'][] = $ar;
            }
        }
    } else {
        $result['error'] = 1;
        $errorMessage = '指定された課題が見つかりませんでした';
    }
} catch (PDOException $e) {
    $result['error'] = 1;
    $errorMessage = $e->getMessage();  //デバッグ
}


header("Access-Control-Allow-Origin: *");
echo json_encode($result);
?>
