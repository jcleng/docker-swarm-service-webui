<?php

use \GuzzleHttp\Client;
use think\facade\Db;

class ClientDocker
{
    private $client;
    public function __construct($socketPath)
    {
        $this->client = new Client([
            'base_uri' => 'http://localhost', // 域名会被忽略，但需要设置
            'handler' => (function () use ($socketPath) {
                $handler = new \GuzzleHttp\Handler\CurlHandler();
                $stack = \GuzzleHttp\HandlerStack::create($handler);
                $stack->push(function (callable $handler) use ($socketPath) {
                    return function ($request, array $options) use ($handler, $socketPath) {
                        $options['curl'] = [
                            CURLOPT_UNIX_SOCKET_PATH => $socketPath
                        ];
                        return $handler($request, $options);
                    };
                });
                return $stack;
            })(),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }
    /**
     * 获取请求client
     *
     * @return \GuzzleHttp\Client
     * @author jcleng
     */
    public function getClient()
    {
        return $this->client;
    }
    /**
     * 获取服务的版本号
     *
     * @param string $service_name
     * @return int
     * @author jcleng
     */
    public function serviceVersion($service_name)
    {
        $response = $this->client->get("/services/$service_name");
        return json_decode($response->getBody(), true)['Version']['Index'] ?? null;
    }
    /**
     * 获取任务列表
     *
     * @param string $service_name
     * @return array 数组按照ID降序
     * @author jcleng
     */
    public function tasks($service_name)
    {
        $task = $this->client->get("/tasks?filters=" . json_encode([
            'service' => [
                $service_name => true
            ]
        ]));
        $task_res = json_decode($task->getBody(), true);
        usort($task_res, function ($a, $b) {
            $a = $a['Version']['Index'];
            $b = $b['Version']['Index'];
            if ($a == $b) return 0;
            return ($a > $b) ? -1 : 1;
        });
        return $task_res;
    }
    /**
     * 获取服务详情
     *
     * @param string $service_name
     * @return array
     * @author jcleng
     */
    public function serviceInspect($service_name)
    {
        $response = $this->client->get("/services/$service_name");
        $update_res = json_decode($response->getBody(), true);
        return $update_res;
    }
    /**
     * 服务列表
     *
     * @return array
     * @author jcleng
     */
    public function serviceList()
    {
        $response = $this->client->get("/services");
        $update_res = json_decode($response->getBody(), true);
        return $update_res;
    }
    /**
     * 服务平滑更新镜像创建任务
     * * 注意时间是纳秒, 有基于curl的80健康检查且自动回滚
     * @param string $service_name
     * @return bool
     * @author jcleng
     */
    public function serviceRollingUpdateImages($service_name, $image)
    {
        $version = $this->serviceVersion($service_name);
        $inspect = $this->serviceInspect($service_name);
        $update_data = $inspect['Spec'];
        $update_data['TaskTemplate']['ContainerSpec']['Image'] = $image;
        $update_data = $this->removeEmptyKeys($update_data);
        $response = $this->client->post("/services/$service_name/update?version=$version", [
            'json' => $update_data
        ]);
        return true;
    }
    /**
     * 缩放服务pod创建任务
     *
     * @param string $service_name
     * @param int $replicas
     * @return bool
     * @author jcleng
     */
    public function serviceReplicas($service_name, $replicas)
    {
        $version = $this->serviceVersion($service_name);
        $inspect = $this->serviceInspect($service_name);
        $update_data = $inspect['Spec'];
        $update_data['Mode']['Replicated']['Replicas'] = $replicas;
        $update_data = $this->removeEmptyKeys($update_data);
        $response = $this->client->post("/services/$service_name/update?version=$version", [
            'json' => $update_data
        ]);
        return true;
    }
    /**
     * 递归去掉空数组
     *
     * @param array $array
     * @return array
     * @author jcleng
     */
    public function removeEmptyKeys(array $array): array
    {
        foreach ($array as $key => $value) {
            // 递归处理子数组
            if (is_array($value)) {
                $array[$key] = $this->removeEmptyKeys($value);
                if (empty($array[$key])) {
                    unset($array[$key]);
                }
            }
            // 检查非数组空值（null、空字符串）
            elseif ($value === null || $value === '' || $value === []) {
                unset($array[$key]);
            }
        }
        return $array;
    }
    /**
     * 创建服务任务
     * * 带有curl 80的健康检查, 以及平滑更新
     * @param string $service_name
     * @param string $image
     * @param int $replicas
     * @param array $ports 多个端口键值对
     * @return bool
     * @author jcleng
     */
    public function serviceCreate($service_name, $image, $replicas, $params)
    {
        $ports = $params['ports'] ?? [];
        $service = [
            "Name" => trim($service_name),
            "TaskTemplate" => [
                "Labels" => [
                    "create_with" => "docker-swarm-service-webui"
                ],
                "ContainerSpec" => [
                    "Image" => trim($image),
                    "Mounts" => (function () use ($params) {
                        if (empty($params['volumes'])) {
                            return [];
                        }
                        $volumesList = [];
                        foreach ($params['volumes'] as $_key => $item) {
                            $volumesList[] = [
                                "Type" => "bind",
                                "Source" => $item['hostPath'],
                                "Target" => $item['containerPath']
                            ];
                        }
                        return $volumesList;
                    })(),
                    "HealthCheck" => (function () use ($params) {
                        if (empty($params['healthCheckCommand'])) {
                            return [];
                        }
                        return [
                            "Test" => [
                                "CMD-SHELL",
                                trim($params['healthCheckCommand'])
                            ],
                            "Interval" => 5000000000,
                            "Timeout" => 3000000000,
                            "Retries" => 3,
                            "StartPeriod" => 45000000000
                        ];
                    })()
                ]
            ],
            "Mode" => [
                "Replicated" => [
                    "Replicas" => intval($replicas)
                ]
            ],
            "EndpointSpec" => [
                "Mode" => "vip",
                "Ports" => (function () use ($ports) {
                    $portList = [];
                    foreach ($ports as $_key => $item) {
                        $portList[] = [
                            "Name" => "string",
                            "Protocol" => "tcp",
                            "TargetPort" => intval($item['containerPort']),
                            "PublishedPort" => intval($item['hostPort']),
                            "PublishMode" => "ingress"
                        ];
                    }
                    return $portList;
                })(),
            ],
            "UpdateConfig" => [
                "Parallelism" => 1,
                "Delay" => 30000000000,
                "FailureAction" => "rollback",
                "Monitor" => 15000000000,
                "MaxFailureRatio" => 0,
                "Order" => "start-first"
            ],
            "RollbackConfig" => [
                "Parallelism" => 1,
                "Delay" => 10000000000,
                "FailureAction" => "pause",
                "Monitor" => 15000000000,
                "MaxFailureRatio" => 0,
                "Order" => "start-first"
            ]
        ];
        $options = [
            'json' => $this->removeEmptyKeys($service),
        ];
        if (!empty($params['imagePrivateId'])) {
            $login_info = Db::table('login')->where('id', $params['imagePrivateId'])
                ->findOrFail();
            $options['headers'] = [
                // 私有镜像凭证, 源json数据不能有换行和空格: https://docs.docker.com/reference/api/engine/version/v1.50/#section/Authentication
                'X-Registry-Auth' => base64_encode(json_encode($login_info))
            ];
        }
        $response = $this->client->post("/services/create", $options);
        // $update_res = json_decode($response->getBody(), true);
        return true;
    }
    /**
     * 删除服务
     * 删除之后tasks也不存在了
     * @param string $service_name
     * @return bool
     * @author jcleng
     */
    public function serviceDelete($service_name)
    {
        $response = $this->client->delete("/services/$service_name");
        return true;
    }
    /**
     * 用户登录主节点
     *
     * @param string $username
     * @param string $password
     * @param string $serveraddress
     * @return void
     * @author jcleng
     */
    public function login($username, $password, $serveraddress)
    {
        $response = $this->client->post("/auth", [
            'json' => [
                "username" => $username,
                "password" => $password,
                "serveraddress" => $serveraddress,
            ],
        ]);
        $res = json_decode($response->getBody(), true);
        if (($res['Status'] ?? '') !== 'Login Succeeded') {
            throw new \Exception($response->getBody());
        }
        return true;
    }
}
