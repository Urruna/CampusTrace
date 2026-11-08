SET NAMES utf8mb4;
USE campustrace_baseline;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE audit_events;
TRUNCATE TABLE reports;
TRUNCATE TABLE claims;
TRUNCATE TABLE post_images;
TRUNCATE TABLE posts;
TRUNCATE TABLE invite_codes;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO users (id, username, password_hash, role, contact_text, activated_at, created_at) VALUES
(
    1,
    'alice',
    '$2y$10$V9dPR3UTCf6n.WI.SKZuWOY/u.HOkbT5X39MhDq2qU7ib9q8so1.O',
    'user',
    '模拟联系方式：alice@example.test',
    '2026-11-01 09:00:00',
    '2026-11-01 09:00:00'
),
(
    2,
    'bob',
    '$2y$10$OzAdzb1u.e2Ggq1DmVkOA.w3tH0R8hzLehgY8OGXSDWfQ0tstwiRy',
    'user',
    '模拟联系方式：bob@example.test',
    '2026-11-01 09:05:00',
    '2026-11-01 09:05:00'
),
(
    3,
    'carol',
    '$2y$10$GqVe8R8LJRDfisNX9cmcG.O1Zm/g4mMMikLY7OP4bsJpe/QuFtKZW',
    'user',
    '模拟联系方式：carol@example.test',
    NULL,
    '2026-11-01 09:10:00'
),
(
    4,
    'admin',
    '$2y$10$mTRfQNb2HosfjhiVbfxZH.b1xSVcdfIJRxcHawNGwk0EWp0g4j4z6',
    'admin',
    '模拟联系方式：admin@example.test',
    '2026-11-01 09:15:00',
    '2026-11-01 09:15:00'
);

INSERT INTO invite_codes (code_hash, expires_at, used_by, used_at, created_at) VALUES
(SHA2('CAMPUS-CAROL-2026', 256), '2027-01-01 00:00:00', NULL, NULL, '2026-11-01 09:00:00'),
(SHA2('CAMPUS-USED-2026', 256), '2027-01-01 00:00:00', 2, '2026-11-01 09:05:00', '2026-11-01 09:00:00');

INSERT INTO posts
    (id, owner_id, post_type, title, description, category, location, status, current_image_id, created_at, updated_at)
VALUES
(
    1,
    1,
    'lost',
    '丢失蓝色校园卡套',
    '卡套内有模拟学生卡和一把宿舍钥匙。请通过认领申请说明卡套背面的标记。',
    '证件与卡包',
    '图书馆二楼阅览区',
    'open',
    NULL,
    '2026-11-05 14:20:00',
    '2026-11-05 14:20:00'
),
(
    2,
    2,
    'found',
    '捡到一把黑色折叠伞',
    '在教三楼门口捡到，伞柄处有白色贴纸。请联系失主认领。',
    '日用品',
    '教三楼东门',
    'open',
    NULL,
    '2026-11-06 18:10:00',
    '2026-11-06 18:10:00'
),
(
    3,
    1,
    'lost',
    '已解决的测试记录',
    '该记录用于验证已解决状态仍可查看，但不再接受新的认领申请。',
    '测试',
    '测试地点',
    'resolved',
    NULL,
    '2026-11-03 10:00:00',
    '2026-11-06 10:00:00'
);

INSERT INTO claims
    (post_id, requester_id, reason, clue, status, decided_by, decided_at, created_at)
VALUES
(
    1,
    2,
    '疑似是我的卡套',
    '卡套背面有一个蓝色圆形贴纸。',
    'pending',
    NULL,
    NULL,
    '2026-11-06 16:30:00'
);

INSERT INTO reports
    (post_id, reporter_id, reason, status, handled_by, handled_at, created_at)
VALUES
(
    2,
    1,
    '用于验证举报流程的模拟举报。',
    'pending',
    NULL,
    NULL,
    '2026-11-06 19:00:00'
);

INSERT INTO audit_events
    (actor_id, action, target_type, target_id, result, created_at)
VALUES
(1, 'post.create', 'post', 1, 'success', '2026-11-05 14:20:00'),
(2, 'post.create', 'post', 2, 'success', '2026-11-06 18:10:00'),
(2, 'claim.create', 'post', 1, 'success', '2026-11-06 16:30:00');

