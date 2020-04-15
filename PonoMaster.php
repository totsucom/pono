<?php
session_start();

// ログイン状態チェック
if (!isset($_SESSION["NAME"])) {
    header("Location: Login.php");
    exit;
}

if (!$_SESSION['MASTER']) {
    echo 'このページを開く権限がありません';
    exit();
}

require_once 'Env.php';

if (isset($_POST['submit'],$_POST['wallname'])) {
    try {
/*
        $sql = "UPDATE `walls` SET `active` = 0 WHERE `id` IN ()";
        $sql = "UPDATE `walls` SET `active` = 1 WHERE `id` NOT IN ()";

        $_POST['order']のなかのシングルクォートで囲まれた名前を追加
        $_POST['order']にidを設定する
        $sql = "INSERT INTO `walls` (`active`,`disporder`,`tag`,`name`) VALUES ()";

        $_POST['order']順に表示順を書き込む
        $sql = "UPDATE `walls` SET `disporder` = ? WHERE `id` = ?";
*/

        $pdo->beginTransaction();
        try {

            if (isset($_POST['active'])) {
                $s = str_repeat('?,', count($_POST['active']) - 1) . '?';

                $sql = "UPDATE `walls` SET `active` = 1 WHERE `id` IN (${s})";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($_POST['active']);

                $sql = "UPDATE `walls` SET `active` = 0 WHERE `id` NOT IN (${s})";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($_POST['active']);
            } else {
                $sql = "UPDATE `walls` SET `active` = 0 WHERE 1";
                $stmt = $pdo->query($sql);
            }

            for ($i = 0; $i < count($_POST['order']); $i++) {
                $value = $_POST['order'][$i];

                if (substr($value, 0, 1) == "'" && substr($value, strlen($value) - 1, 1) == "'") {

                    //タグ一覧を取得(重複チェック用)
                    if (!isset($tags)) {
                        $tags = array();
                        $stmt = $pdo->query("SELECT `tag` FROM `walls`");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $tags[] = $row['tag'];
                        }
                    }

                    $name = substr($value, 1, strlen($value) - 2); //新しい壁の名称

                    if (preg_match('/[A-Z]/', mb_convert_kana($name, 'a'), $matches)) {
                        //何かしらアルファベットを取り出す
                    } else {
                        $matches = array('some');
                    }

                    //タグを決定する
                    unset($tag);
                    while (!isset($tag)) {
                        foreach ($matches as $abc) {
                            $w = isset($num) ? ($abc . $num) : $abc;
                            if (array_search($w, $tags, FALSE) === FALSE) {
                                $tag = $w;
                                break;
                            }
                        }
                        if (!isset($tag)) {
                            if (isset($num)) {
                                $num += 1;
                            } else {
                                $num = 2;
                            }
                        }
                    }

                    $sql = "INSERT INTO `walls` (`active`,`disporder`,`tag`,`name`) VALUES (?,?,?,?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array(
                        1, //active
                        0, //disporder (設定しない)
                        $tag,
                        $name
                    ));

                    //名称をIDに置換する
                    $_POST['order'][$i] = $pdo->lastInsertId();
                }
            }

            for ($i = 0; $i < count($_POST['order']); $i++) {
                $sql = "UPDATE `walls` SET `disporder` = ? WHERE `id` = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array($i + 1, $_POST['order'][$i]));
            }

            $pdo->commit();
            $message = 'データを更新しました';
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo 'データベースエラー<br>' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
            exit();
        }
    } catch (PDOException $e) {
        echo 'トランザクションエラー<br>' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
        exit();
    }
}

try {
    $sql = "SELECT * FROM `walls` ORDER BY `disporder`";
    $stmt = $pdo->query($sql);
} catch (PDOException $e) {
    echo 'データベースエラー<br>' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
    exit();
}


?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <link rel="stylesheet" href="./css/bootstrap-datepicker.min.css">
    <title>壁の一覧</title>
</head>
<body>
    <nav class="navbar navbar-expand-sm navbar-light bg-light">
        <a href="./Main.php" class="navbar-brand">
            <img src="./img/real-estate.png" width="18" height="18" alt="">
            Home
        </a>
        <button class="navbar-toggler" type="button"
            data-toggle="collapse"
            data-target="#navmenu1"
            aria-controls="navmenu1"
            aria-expanded="false"
            aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navmenu1">
            <div class="navbar-nav">
                <a class="nav-item nav-link" href="./Account.php">アカウント設定</a>
                <a class="nav-item nav-link" href="./Logout.php">ログアウト</a>
            </div>
        </div>
    </nav>

    <div class = "container">
        <?php
            if (isset($errorMessage)) {
                //エラー
                $msg = htmlspecialchars($errorMessage, ENT_QUOTES);
                echo <<<EOD
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>エラー！</strong> {$msg}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>
            </body>
        </html>

EOD;
                exit();
            } else if (isset($message)) {
                $msg = htmlspecialchars($message, ENT_QUOTES);
                echo <<<EOD
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>成功！</strong> {$msg}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>

EOD;
            }
        ?>
        <form action="#" method="post" class="mx-1" id="form1" enctype="multipart/form-data">

            <h3 class="mb-3 mx-1">壁の一覧</h3>

            <div class="text-info mx-1 mb-5">壁の追加や無効設定を行います。無効にするとその壁の課題が表示されなくなります。</div>

            <table class="table table-bordered justify-content-center mb-3" id="walls_table">
                <thead>
                    <tr>
                        <th class="text-center">名称</th>
                        <th class="text-center">有効</th>
                        <th class="text-center">表示順</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $names = [];
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $names[] = $row['name'];
                            $name = htmlspecialchars($row['name'], ENT_QUOTES);
                            $checked = ($row['active'] == 1) ? 'checked' : '';
                            $id = 'wall' . $row['id'];
                            echo <<<EOD
                                <tr>
                                    <td class="text-center align-middle">{$name}<input type="HIDDEN" name="order[]" value="{$row['id']}"></td>
                                    <td class="text-center align-middle">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" name="active[]" value="{$row['id']}" id="{$id}" {$checked}>
                                            <label class="custom-control-label" for="{$id}"></label>
                                        </div>
                                        <!--
                                        <input class="form-check-input" type="checkbox" name="active[]" value="{$row['id']}" id="{$id}" {$checked} >
                                        <label class="form-check-label" for="{$id}">無効</label>
                                        -->
                                    </td>
                                    <td class="text-center align-middle">
                                        <button class="btn btn-secondary moveup">↑</button>
                                        <button class="btn btn-secondary movedown">↓</button>
                                    </td>
                                </tr>

EOD;
                        }
                    ?>
                </tbody>
            </table>
            <div class="form-group">
                <div class="input-group">
                    <input type="text" class="form-control" name="wallname" id="wallname" maxlength="8" placeholder="新規名称" style="max-width:200px">
                    <button class="btn btn-secondary align-middle" id="add-button" >追加</button>
                </div>
            </div>
            <div class="text-danger" id="texterr" style="display:none"></div>

            <!-- 投稿ボタン -->
            <div class="my-5 col-12 clearfix">
                <div class="float-right">
                    <button class="btn btn-primary align-middle" type="submit" name="submit">更新する</button>
                </div>
            </div>
        </form>
    </div>

    <script type="text/javascript" src="./js/jquery-3.4.1.min.js"></script>
    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="./js/bootstrap-datepicker.min.js"></script>
    <script type="text/javascript" src="./js/bootstrap-datepicker.ja.min.js"></script>
    <script>$(function(){
        var names = [<?php if (count($names) > 0) echo "'",implode("','", $names),"'"; ?>];
        var newNames = [];

        $('tbody').on('click', 'button.moveup', function () {
            let $tr = $(this).closest("tr")
            let $tr_prev = $tr.prev("tr");
            if($tr_prev.length) {
                $tr.insertBefore($tr_prev);
            }
            return false;
        });

        $('tbody').on('click', 'button.movedown', function () {
            let $row = $(this).closest("tr");
            let $row_next = $row.next("tr");
            if($row_next.length) {
                $row.insertAfter($row_next);
            }
            return false;
        });

        $('tbody').on('click', 'button.delete', function () {
            var i = newNames.indexOf($(this).data('name'));
            newNames.splice(i, 1);
            $(this).parents('tr').remove();
            return false;
        });

        $('#wallname').on('change', function () {
            var s  =$(this).val();
            if (s.length == 0) {
                $('#texterr').hide();
            } else {
                if (s.indexOf('"') >= 0 || s.indexOf("'") >= 0) {
                    $('#texterr').show().html("&quot; および ' は使用できません");
                } else {
                    $('#texterr').hide();
                }
            }
        });

        $('#add-button').on('click', function () {
            var s = $('#wallname').val().trim();
            if (s.length == 0) {
                $('#texterr').show().html("名称が入力されていません");
            } else if (s.indexOf('"') >= 0 || s.indexOf("'") >= 0) {
                $('#texterr').show().html("&quot; および ' は使用できません");
            } else {
                var i,f=false;
                for (i=0; i<names.length; i++) {
                    if (names[i] == s) {
                        $('#texterr').show().html("名称が重複しています");
                        f = true;
                        break;
                    }
                }
                if (!f) {
                    for (i=0; i<newNames.length; i++) {
                        if (newNames[i] == s) {
                            $('#texterr').show().html("名称が重複しています");
                            f = true;
                            break;
                        }
                    }
                }
                if (!f) {
                    var html = '<tr>';
                    html += '<td class="text-center">'+s+'</td>';
                    html += '<td class="text-center"><button class="btn btn-danger delete" data-name="'+s+'">削除</button><input type="HIDDEN" name="order[]" value="'+"'"+s+"'"+'"></td>';
                    html += '<td class="text-center"><button class="btn btn-secondary moveup">↑</button> <button class="btn btn-secondary movedown">↓</button></td>';
                    html += '</tr>';
                    $('tbody').append(html);
                    $('#wallname').val('');
                    $('#texterr').hide();
                    newNames.push(s);
                }
            }
            return false;
        });

    });</script>
</body>
</html>
