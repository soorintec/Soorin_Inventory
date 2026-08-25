<?php

return [
    'label' => '备份', 'nav_group' => '管理与报表',
    'how_it_works' => '此页面的作用',
    'hint_create' => '“创建备份”按钮会生成整个数据库的 SQL 文件并保存在服务器上。请下载并妥善保存。',
    'hint_keep' => '仅保留最近 20 个备份；更早的自动删除。',
    'hint_restore' => '恢复会用文件数据替换当前数据。每次恢复前，系统会对当前状态做一次备份。',
    'hint_portable' => '导出的 SQL 文件为标准格式，也可用 phpMyAdmin 或 mysql 命令恢复。',
    'create' => '创建备份', 'created' => '备份已创建：:file',
    'list' => '备份文件', 'empty' => '尚无备份。', 'file' => '文件名', 'created_at' => '日期', 'size' => '大小',
    'download' => '下载', 'delete' => '删除', 'deleted' => '备份文件已删除。', 'delete_confirm' => '删除此备份文件？',
    'megabyte' => 'MB', 'kilobyte' => 'KB',
    'restore' => '从文件恢复', 'restore_heading' => '从备份文件恢复数据库',
    'restore_warning' => '警告：恢复会清除所有当前数据并以文件数据替换。不可逆。运行前系统会对当前状态做备份。',
    'restore_existing' => '从服务器上的备份中选择', 'restore_existing_hint' => '若所需备份已在服务器上，直接选择即可；无需下载后再上传。',
    'restore_existing_placeholder' => '选择一个，或在下方上传文件', 'restore_no_source' => '请选择现有备份或上传文件。',
    'restore_file' => '或上传备份文件（SQL 或 TXT）', 'restore_file_hint' => '之前下载的文件（.sql），或 mysqldump / phpMyAdmin 导出。也接受 .txt。',
    'restore_bad_type' => '仅接受 .sql 或 .txt 文件。', 'restore_understood' => '我明白当前数据将被清除且不可逆。',
    'restore_confirm_button' => '是的，恢复', 'restored' => '恢复成功完成。',
    'restored_safety' => '恢复前状态的备份已保存为“:file”。', 'restored_no_safety' => '数据库为空，因此无需恢复前备份。',
    'restore_failed' => '恢复失败。',
];
