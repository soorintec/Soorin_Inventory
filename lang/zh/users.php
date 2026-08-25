<?php

return [
    'label' => '用户', 'plural' => '用户', 'nav_group' => '管理与报表',
    'name' => '姓名', 'email' => '邮箱', 'mobile' => '手机', 'password' => '密码', 'password_hint' => '留空则不修改密码。',
    'user_type' => '账户类型', 'account' => '账户信息', 'permissions' => '权限',
    'permissions_hint' => '每个勾选框是一项权限。取消勾选会收回该用户的该权限——即使是管理员。更改账户类型会将勾选重置为该类型的默认值，之后可逐项调整。',
    'user_type_hint' => '仅决定预勾选项；实际权限以下方勾选为准。', 'active_hint' => '停用的用户完全无法登录。',
    'theme' => '主题', 'theme_hint' => '与用户菜单中的太阳/月亮开关相同；无论在哪更改都会保存到此处。',
    'active' => '启用', 'empty' => '尚无用户。',
];
