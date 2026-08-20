# 宝塔单容器部署

该镜像把 GEOFlow 运行所需的 Nginx、PHP-FPM、PostgreSQL、pgvector、Redis、队列、Scheduler 和 Reverb 放在同一个容器中，适合在宝塔 Docker 中以“本地镜像 + 单容器”的方式安装。

容器只需要一个持久化目录：

```text
宿主机 /www/docker/geoflow-data  ->  容器 /data
```

`/data` 中保存数据库、Redis、上传文件、Laravel运行目录，以及包含 `APP_KEY` 和后台路径的 `.env`。删除容器但保留该目录后，重新创建容器不会丢失业务数据和后台配置。

## 一、在宝塔构建镜像

将完整项目上传到服务器，例如：

```text
/www/wwwroot/GEOFlow
```

在宝塔“Docker -> 本地镜像 -> 构建镜像”中填写：

| 配置 | 值 |
| --- | --- |
| 构建目录 | `/www/wwwroot/GEOFlow` |
| Dockerfile | `docker/Dockerfile.baota` |
| 镜像名称 | `geoflow-baota:latest` |

等价命令：

```bash
cd /www/wwwroot/GEOFlow
docker build -f docker/Dockerfile.baota -t geoflow-baota:latest .
```

构建过程会从 pgvector 官方 GitHub 压缩包下载固定版本，并校验 SHA-256。下载命令带有自动重试；如果服务器访问 GitHub 偶发中断，直接重新执行构建即可，前面成功的镜像层会复用缓存。

## 二、创建容器

在宝塔中使用 `geoflow-baota:latest` 创建容器：

| 配置 | 值 |
| --- | --- |
| 容器名称 | `geoflow` |
| 端口 | `127.0.0.1:18080 -> 8080/tcp` |
| 持久化目录 | `/www/docker/geoflow-data -> /data` |
| 重启策略 | `unless-stopped` |
| 建议内存 | 至少 2 GB，推荐 4 GB |

最少只需要设置站点地址；管理员密码建议同时设置：

```dotenv
APP_URL=https://geo.example.com
GEOFLOW_ADMIN_PASSWORD=请替换为后台管理员强密码
```

直接使用 HTTP 地址调试时，应改为：

```dotenv
APP_URL=http://服务器IP:18080
```

以下变量通常不需要修改：

```dotenv
DB_DATABASE=geo_flow
DB_USERNAME=geo_user
DB_PASSWORD=
REDIS_PASSWORD=
REVERB_APP_SECRET=
AUTO_MIGRATE=true
AUTO_INSTALL=true
AUTO_OPTIMIZE=true
```

PostgreSQL和Redis仅监听容器内部的 `127.0.0.1`，不会暴露到宿主机或公网。

未填写 `DB_PASSWORD`、`REVERB_APP_SECRET` 时会自动生成并保存到 `/data/.env`。WebSocket 外部地址和安全 Cookie 也会从 `APP_URL` 自动推导。

## 三、宝塔反向代理

在宝塔中新建网站、申请SSL证书，然后把站点反向代理到：

```text
http://127.0.0.1:18080
```

容器内Nginx同时处理普通HTTP请求和 `/reverb/` WebSocket请求。宝塔反向代理需要开启WebSocket支持。

## 四、首次启动

首次启动会依次执行：

1. 初始化PostgreSQL数据目录；
2. 创建数据库账号和数据库；
3. 启用pgvector；
4. 执行Laravel迁移；
5. 创建管理员和初始内容；
6. 启动全部常驻服务。

首次启动时间会比普通重启长。健康检查地址：

```text
http://127.0.0.1:18080/up
```

查看初始化进度和首次生成的管理员密码：

```bash
docker logs -f geoflow
```

默认后台地址为 `APP_URL/geo_admin`。

如果没有设置 `GEOFLOW_ADMIN_PASSWORD`，系统会生成一次性随机密码并输出到容器初始化日志。正式部署建议显式设置。

## 五、备份与更新

完整备份以下宿主机目录即可覆盖单容器的全部持久化数据：

```text
/www/docker/geoflow-data
```

更新流程：

1. 停止旧容器；
2. 备份 `/www/docker/geoflow-data`；
3. 使用新代码重新构建同名镜像；
4. 删除旧容器，但不要删除宿主机数据目录；
5. 使用相同端口、环境变量和目录挂载重新创建容器。

新容器启动时会在其他业务进程启动前自动执行数据库迁移。
