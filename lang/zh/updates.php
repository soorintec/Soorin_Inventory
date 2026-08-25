<?php

return [
    'badge_tooltip'   => '有新版本可用 — 点击以更新。',
    'label' => '更新', 'current_version' => '当前版本', 'offline_mode' => '仅文件更新',
    'how_it_works' => '此页面的作用',
    'hint_check' => '“检查更新”会将当前版本与 GitHub 上的最新版本比较。',
    'hint_git' => '若应用通过 git 安装且有新版本，“从 GitHub 更新”会获取它。',
    'hint_zip' => '若无网络，请用“从文件更新”上传新版本的 zip 文件。',
    'hint_backup' => '每次更新前，系统会自行备份数据库。',
    'check' => '检查更新', 'check_failed' => '无法检查更新。', 'up_to_date' => '应用已是最新；没有更新版本。', 'available' => '有新版本：:version',
    'update_git' => '从 GitHub 更新', 'update_zip' => '从文件更新',
    'update_warning' => '应用将更新到新版本。会先备份数据库。可能需要片刻；请勿关闭页面。',
    'updated' => '应用已更新到版本 :version。', 'updated_backup' => '更新前备份：:file', 'update_failed' => '更新失败。',
    'zip_file' => '新版本 zip 文件', 'zip_hint' => '应用包文件（含 artisan）。仅限 .zip 文件。', 'zip_bad_type' => '仅接受 .zip 文件。',
    'link_git' => '连接到 GitHub',
    'link_git_warning' => '此安装来自 zip，未连接 GitHub。此操作会创建 git 文件夹并连接到仓库，以便“从 GitHub 更新”可用。应用文件将与仓库版本对齐；设置和数据（.env 和数据库）不受影响，并会预先备份。',
    'link_git_url' => 'GitHub 仓库地址', 'link_git_url_hint' => '若仓库为私有，请在地址中放入令牌：https://<TOKEN>@github.com/…',
    'link_git_done' => '已连接到 GitHub。“从 GitHub 更新”现已可用。', 'link_git_failed' => '连接 GitHub 失败。',
];
