# 成员 3：图片上传、显示与替换

状态：多图片、删除和自动化测试已实现，待课程 VM 实测记录

## 范围

- 每帖保存多张 JPEG 或 PNG。
- 多文件上传、封面、详情相册、公开读取和单张删除。
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
tests/security/test_images_multi.php
tests/fixtures/images/README.md
```

## 路由

| 方法 | 路由 | 行为 |
| --- | --- | --- |
| POST | `/posts/{id}/image` | 接收 `images[]`，逐张追加图片；首次成功图片成为封面 |
| POST | `/posts/{id}/images/{imageId}/delete` | 发布者删除指定图片；删除封面时自动选择剩余第一张 |
| GET | `/images/{id}` | 读取数据库记录的原始图片文件 |

## 文件与目录

- 文件写入 `storage/baseline_images/`。
- 数据库只保存文件标识和元数据。
- 不把上传目录直接暴露为 Nginx 静态目录。
- 文件名为随机十六进制字符串加原扩展名，不使用用户提交的原文件名作为存储名。

## 数据表与输入输出

- 输入：帖子 ID、multipart 文件字段 `images[]`，同时兼容旧的单字段 `image`。
- 写入 `post_images`：`post_id`、`storage_name`、`mime_type`、`file_size`、`width`、`height`。
- `posts.current_image_id` 表示封面；同一帖子通过 `post_images.post_id` 取得完整相册。
- 首次成功上传设置封面，以后追加图片不自动更改现有封面。
- 批次中不支持的扩展名或上传错误逐张失败，合法图片继续保存。
- 删除封面后按 `created_at, id` 选择剩余第一张作为新封面；没有余图时清空引用。
- `ImageService::uploadBatchForPost()` 返回成功图片、逐文件失败结果和当前封面 ID。

## 模块交接

- `ImageService` 是图片持久化和读取服务；其他模块不得复制文件处理逻辑。
- 成员 2 可在帖子详情或编辑页包含 `image-preview.php`。
- 预览组件使用 `$postImages`、`$post['image_id']`、`$post['owner_id']` 和 `$post['status']`。
- `PostRepository::findPublicImages()` 返回详情相册；列表查询仍只连接 `current_image_id` 作为封面。
- 图片上传表单必须使用 `multipart/form-data`，字段名为 `images[]` 并设置 `multiple`。
- 当前详情页已经接入预览和发布者上传表单；后续帖子页面重构时保留这两个约定。

## Baseline 缺口

- R01：`ImageService::storeOne()` 只检查扩展名；`getimagesize()` 仅记录可用尺寸，不用于拒绝伪图片。
- R02：`ImageController::show()` 使用 `readfile()` 直接返回原始文件，不重新编码，也不清理元数据。
- R04：`ImageService::findForPublic()` 只按图片 ID 查询，不校验帖子状态或图片有效性；下架帖子的图片仍可直接读取。
- R05：批量上传只调用 `require_active_user()`，不检查帖子发布者；删除操作则按本轮要求检查所有者。
- R08：图片上传和删除请求不调用 `verify_csrf()`，服务端接受缺失令牌的 POST。
- R09：无封面时先插入记录并切换封面，再移动文件；移动失败时故意不回滚，留下无文件图片记录或错误封面。

以上缺口仅用于本地模拟环境和 hardened 对照，不得部署到公网。

## 正常测试

1. `alice` 一次上传两张合法图片，确认 `post_images` 新增两条记录。
2. 检查第一张成功图片成为 `posts.current_image_id`，第二张作为相册成员。
3. 追加图片，确认相册增加且已有封面不变。
4. 列表查询只取得封面，详情页显示全部图片。
5. 删除非封面图片，确认封面不变；删除封面，确认下一张自动接替。
6. 删除最后一张后确认 `current_image_id` 为 NULL。
7. 检查文件位于 `storage/baseline_images/`，删除后旧 `/images/{id}` 返回 404。

## 异常与风险测试

- IMG-02：上传普通文本改名的 `.png`，确认 Baseline 接收并记录 R01。
- IMG-03：帖子下架后访问其图片 ID，确认仍返回 200，记录 R04。
- R02：比较上传文件和读取响应的 SHA-256，确认原样返回。
- 多文件部分成功：一张合法、一张 `.txt`，合法图片保存且 `.txt` 不插入数据库。
- 全批失败：不改变相册和封面。
- R05：`bob` 通过请求上传 `alice` 帖子的图片，确认 Baseline 错误放行。
- 删除越权：`bob` 删除 `alice` 帖子的图片应返回 403。
- FILE-01：注入返回失败的文件移动函数，确认数据库引用已改变但目标文件不存在，记录 R09。

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
- 测试需要 PHP `curl`、`gd`、`mysqli` 和可写的图片目录；测试文件在运行时自生成，不要求人工提供真实图片。
- Baseline 不实现真实内容校验、重编码、EXIF 清理、手动遮盖、下架图片失效或上传失败回滚。

## 演示步骤

1. 重置并导入模拟数据库，清理测试图片目录。
2. 以 `alice` 登录，打开帖子 1。
3. 一次选择两张自制小图片，观察相册、封面和数据库记录。
4. 再追加一张图片，确认已有封面不变。
5. 删除非封面图片和封面，观察封面接替与旧地址失效。
6. 将帖子下架后直接访问图片地址，展示 R04。
7. 用伪造 `.png` 复现 R01，用失败移动测试复现 R09。

