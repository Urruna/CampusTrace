# 成员 4：认领申请与联系授权

状态：待实现

## 范围

- 提交认领申请和核验线索。
- 发布者查看、批准、拒绝和撤回。
- 获批申请人读取模拟联系方式。
- 同一帖子最多一个有效获批申请人。

## 路由

- `POST /posts/{id}/claims`
- `GET /claims/{id}`
- `POST /claims/{id}/approve`
- `POST /claims/{id}/reject`
- `POST /claims/{id}/revoke`
- `GET /claims/{id}/contact`

## 必须交接

- 认领状态：`pending`、`approved`、`rejected`、`revoked`。
- 批准操作使用数据库事务。
- 发布者只能处理自己帖子下的申请。
- 联系方式只返回给当前有效获批申请人。

## Baseline 缺口

- R06：联系方式入口先只检查登录。
- R07：公开响应或旧联系入口保留受控的泄露缺口。

