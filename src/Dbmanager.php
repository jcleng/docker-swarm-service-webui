<?php

use think\facade\Db;

/**
 * 数据库相关
 *
 * @author jcleng
 */
class Dbmanager

{
    public static function init()
    {
        $db_file_path = '/etc/docker-swarm-service-webui.db';
        if (!file_exists($db_file_path)) {
            new \PDO("sqlite:$db_file_path");
        }
        // 数据库配置信息设置（全局有效）
        Db::setConfig([
            // 默认数据连接标识
            'default' => 'sqlite',
            // 数据库连接信息
            'connections' => [
                'sqlite' => [
                    // 数据库类型
                    'type' => 'sqlite',
                    'database' => $db_file_path,
                    // 监听SQL
                    'trigger_sql' => true
                ],
            ],
        ]);
        // 进行数据库连接
        self::initTable();
    }
    public static function initTable()
    {
        // 镜像帐户表
        $tableName = 'login';
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='{$tableName}'";
        $result = Db::query($sql);
        if (empty($result)) {
            // 表不存在，创建表
            $createTableSql = <<<SQL
 CREATE TABLE {$tableName} (
     id INTEGER PRIMARY KEY AUTOINCREMENT,
     username TEXT NOT NULL,
     password TEXT NOT NULL,
     email TEXT NOT NULL,
     serveraddress TEXT NOT NULL,
     created_at INTEGER,
     updated_at INTEGER
 );
 SQL;
            Db::execute($createTableSql);
        }
    }
}
