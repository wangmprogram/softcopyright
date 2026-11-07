# 软著材料任务台

首次使用时，将 `.env.example` 复制为 `.env`，并设置本地数据库密码。`.env` 不纳入 Git，请勿提交密码。

在项目目录运行 `docker compose up -d --build`，访问 http://localhost:8088 。停止服务使用 `docker compose down`。

数据库数据保存在 Docker 数据卷，数据库仅在本机 127.0.0.1:3307 提供访问。首次初始化后修改 `.env` 不会自动更改已有数据库用户密码。请勿使用 `docker compose down -v`，该命令会删除项目数据卷。

当前版本支持新建、搜索、筛选和删除任务；材料生成尚未接入。项目 `output` 文件夹用于存放生成材料，不纳入 Git。删除任务记录不会删除该目录中的文件。
