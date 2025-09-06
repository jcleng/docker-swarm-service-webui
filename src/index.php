<?php
include_once "./vendor/autoload.php";
include_once "./ClientDocker.php";
include_once "./Dbmanager.php";
ini_set('date.timezone', 'Asia/Shanghai');

use think\facade\Db;

/**
composer require guzzlehttp/guzzle
https://docs.docker.com/reference/api/engine/version/v1.50/#tag/Service/operation/ServiceCreate
 */

$socketPath = '/var/run/docker.sock';
$docker = (new ClientDocker($socketPath));
Dbmanager::init();
// ! 获取对应节点的任务列表
// $docker->serviceDelete('test');
if (!empty($_GET['action'])) {
    if (!empty($_SERVER['HTTP_AUTHORIZATION']) && $_SERVER['HTTP_AUTHORIZATION'] == getenv("AUTHORIZATION")) {
        //
    } else {
        http_response_code(401);
        die();
    }
    $params = json_decode(file_get_contents("php://input"), true);
    header('Content-Type: application/json; charset=utf-8');
    try {
        switch ($_GET['action'] ?? '') {
            case '/services':
                echo json_encode($docker->serviceList());
                return;
                break;
            case '/scale':
                echo json_encode($docker->serviceReplicas($params['id'], $params['replicas']));
                return;
                break;
            case '/updateImage':
                echo json_encode($docker->serviceRollingUpdateImages($params['id'], $params['image']));
                return;
                break;
            case '/serviceCreate':
                $params = $_REQUEST;
                echo json_encode($docker->serviceCreate(
                    $params['name'],
                    $params['image'],
                    $params['replicas'],
                    $params
                ));
                return;
                break;
            case '/serviceDelete':
                echo json_encode($docker->serviceDelete($params['id']));
                return;
            case '/tasks':
                echo json_encode($docker->tasks($params['id']));
                return;
                break;
            case '/login_list':
                echo json_encode([
                    'data' => Db::table('login')->select()
                ]);
                return;
                break;
            case '/login':
                $res = $docker->login($params['username'], $params['password'], $params['serveraddress']);
                if ($res) {
                    Db::table('login')->insert([
                        'username' => $params['username'],
                        'password' => $params['password'],
                        'email' => $params['email'],
                        'serveraddress' => $params['serveraddress'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                echo json_encode($res);
                return;
                break;
            default:
                # code...
                break;
        }
    } catch (\Throwable $th) {
        http_response_code(500);
        echo json_encode(['message' => $th->getMessage()]);
    }
}
if (PHP_SAPI == 'cli') {
    $list = Db::table('login')->select();
    var_dump($list);
}
