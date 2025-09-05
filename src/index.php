<?php
include_once "./vendor/autoload.php";
include_once "./ClientDocker.php";
ini_set('date.timezone', 'Asia/Shanghai');

/**
composer require guzzlehttp/guzzle
https://docs.docker.com/reference/api/engine/version/v1.50/#tag/Service/operation/ServiceCreate
 */

$socketPath = '/var/run/docker.sock';
$docker = (new ClientDocker($socketPath));
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
            echo json_encode($docker->serviceCreate($params['name'], $params['image'], $params['replicas'], $params['hostPort'], $params['containerPort']));
            return;
            break;
        case '/serviceDelete':
            echo json_encode($docker->serviceDelete($params['id']));
            return;
        case '/tasks':
            echo json_encode($docker->tasks($params['id']));
            return;
            break;
        default:
            # code...
            break;
    }
}
