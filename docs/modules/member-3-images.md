# 成员 3：图片上传、显示与替换

状态：代码与自动化测试已实现，待课程 VM 实测记录

## 范围

- 每帖一张 JPEG 或 PNG。
- 上传、预览、公开显示和替换。
- 图片元数据写入 `post_images`。
- 通过 PHP 受控读取文件。

## 文件

```text
app/services/ImageService.php
app/controllers/ImageController.php
app/views/posts/image-preview.php
public/index.php
public/assets/app.css
tests/security/test_images.php
tests/fixtures/images/README.md
```

## 路由

| 方法 | 路由 | 行为 |
| --- | --- | --- |
| POST | `/posts/{id}/image` | 接收字段 `image`，上传或替换当前图片 |
| GET | `/images/{id}` | 读取数据库记录的原始图片文件 |

## 文件与目录

- 文件写入 `storage/baseline_images/`。
- 数据库只保存文件标识和元数据。
- 不把上传目录直接暴露为 Nginx 静态目录。
- 文件名为随机十六进制字符串加原扩展名，不使用用户提交的原文件名作为存储名。

## 数据表与输入输出

- 输入：帖子 ID、multipart 文件字段 `image`。
- 写入 `post_images`：`post_id`、`storage_name`、`mime_type`、`file_size`、`width`、`height`。
- 更新 `posts.current_image_id`，指向本次上传生成的图片 ID。
- 替换不会删除旧 `post_images` 记录或旧文件，以保留 IMG-03 对照入口。
- `ImageService::uploadForPost()` 返回图片 ID、帖子 ID、存储标识、MIME、大小和宽高。

## 模块交接

- `ImageService` 是图片持久化和读取服务；其他模块不得复制文件处理逻辑。
- 成员 2 可在帖子详情或编辑页包含 `image-preview.php`。
- 预览组件使用 `$post['image_id']`、`$post['owner_id']` 和 `$post['status']`。
- 图片上传表单必须使用 `multipart/form-data`，字段名为 `image`。
- 当前详情页已经接入预览和发布者上传表单；后续帖子页面重构时保留这两个约定。

## Baseline 缺口

- R01：`ImageService::uploadForPost()` 只检查扩展名；`getimagesize()` 仅记录可用尺寸，不用于拒绝伪图片。
- R02：`ImageController::show()` 使用 `readfile()` 直接返回原始文件，不重新编码，也不清理元数据。
- R04：`ImageService::findForPublic()` 只按图片 ID 查询，不校验帖子状态或当前图片引用。
- R05：`ImageController::upload()` 只调用 `require_active_user()`，不检查帖子发布者。
- R08：上传请求不调用 `verify_csrf()`，表单虽然有令牌，但服务端仍接受缺失令牌的 POST。
- R09：先插入图片记录并切换 `posts.current_image_id`，再移动文件；移动失败时故意不回滚。

以上缺口仅用于本地模拟环境和 hardened 对照，不得部署到公网。

## 正常测试

1. `alice` 登录并在自己的帖子详情上传 `valid.png`。
2. 检查 `post_images` 新增记录，`posts.current_image_id` 指向该记录。
3. 检查文件位于 `storage/baseline_images/`，不位于 `public/`。
4. 匿名访问 `/images/{id}`，确认得到与上传文件字节一致的 PNG。
5. 上传 JPEG 替换当前图片，确认当前引用改变。
6. 检查详情页显示新图片，并保留发布者上传表单。

## 异常与风险测试

- IMG-02：上传普通文本改名的 `.png`，确认 Baseline 接收并记录 R01。
- IMG-03：替换后访问旧图片 ID，确认仍返回 200，记录 R04。
- R02：比较上传文件和读取响应的 SHA-256，确认原样返回。
- STATE-01：将帖子改为 `resolved`、`removed` 后访问旧图片 ID，确认仍返回 200，记录 R04。
- R05：`bob` 通过请求替换 `alice` 帖子的图片，确认 Baseline 错误放行。
- FILE-01：注入返回失败的文件移动函数，确认数据库引用已改变但目标文件不存在，记录 R09。
- 非 JPEG/PNG 扩展名应返回 400；匿名上传应跳转登录。

## 测试命令

在课程 VM 内、项目根目录运行：

```powershell
C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe `
  .\tests\security\test_images.php `
  http://127.0.0.1:8080
```

脚本使用 GD 在 `tests/.tmp/` 生成小型自制图片，并在结束后删除测试帖子、图片记录、上传文件和临时夹具。

## 已知限制

- 当前开发主机没有 PHP/Nginx，未执行运行时测试；提交前必须在课程 VM 完成实测并填写结果。
- 测试需要 PHP `curl`、`gd`、`mysqli` 和可写的图片目录。
- Baseline 不实现真实内容校验、重编码、EXIF 清理、手动遮盖、旧链接失效或失败回滚。

## 演示步骤

1. 重置并导入模拟数据库，清理测试图片目录。
2. 以 `alice` 登录，打开帖子 1。
3. 上传自制小图片，观察预览、数据库记录和当前引用。
4. 再上传一张图片，直接访问旧 `/images/{旧ID}` 地址，展示 R04。
5. 用伪造 `.png` 复现 R01，用失败移动测试复现 R09。

