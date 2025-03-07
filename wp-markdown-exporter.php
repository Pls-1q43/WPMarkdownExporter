<?php
/**
 * Plugin Name: WP Markdown Exporter
 * Plugin URI: https://github.com/Pls-1q43/WPMarkdownExporter
 * Description: Export WordPress posts as Markdown files with images
 * Version: 1.0.0
 * Author: Pls
 * Author URI: https://1q43.blog
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-markdown-exporter
 */

// 如果直接访问此文件，则退出
if (!defined('WPINC')) {
    die;
}

// 定义插件常量
define('WP_MARKDOWN_EXPORTER_VERSION', '1.0.0');
define('WP_MARKDOWN_EXPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_MARKDOWN_EXPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

// 在插件初始化之前添加更新检查器
require plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/Pls-1q43/WPMarkdownExporter',  // 替换为你的 GitHub 仓库地址
    __FILE__,
    'wp-markdown-exporter'  // 插件的唯一标识符
);

// 设置包含稳定版本的分支
$myUpdateChecker->setBranch('main'); // 或者 'master', 取决于你的默认分支名称

// 注册自动加载函数
spl_autoload_register(function ($class) {
    // 检查类名是否属于我们的插件命名空间
    $prefix = 'WP_Markdown_Exporter\\';
    $base_dir = WP_MARKDOWN_EXPORTER_PLUGIN_DIR;

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// 激活插件时的回调
register_activation_hook(__FILE__, function() {
    // 检查 WordPress 版本
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires WordPress version 5.0 or higher.');
    }
    
    // 创建必要的目录
    $upload_dir = wp_upload_dir();
    $export_dir = $upload_dir['basedir'] . '/wp-markdown-exports';
    if (!file_exists($export_dir)) {
        wp_mkdir_p($export_dir);
    }
});

// 停用插件时的回调
register_deactivation_hook(__FILE__, function() {
    // 清理临时文件
    $upload_dir = wp_upload_dir();
    $export_dir = $upload_dir['basedir'] . '/wp-markdown-exports';
    
    // 仅删除临时文件，保留已导出的文件
    $temp_files = glob($export_dir . '/temp-*');
    if ($temp_files) {
        foreach ($temp_files as $file) {
            unlink($file);
        }
    }
});

// 初始化插件
add_action('plugins_loaded', function() {
    // 加载文本域
    load_plugin_textdomain(
        'wp-markdown-exporter',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );

    // 初始化管理界面
    if (is_admin()) {
        // 加载所有必需的类文件
        require_once WP_MARKDOWN_EXPORTER_PLUGIN_DIR . 'includes/class-utils.php';
        require_once WP_MARKDOWN_EXPORTER_PLUGIN_DIR . 'includes/class-post-filter.php';
        require_once WP_MARKDOWN_EXPORTER_PLUGIN_DIR . 'includes/class-image-handler.php';
        require_once WP_MARKDOWN_EXPORTER_PLUGIN_DIR . 'includes/class-exporter.php';
        require_once WP_MARKDOWN_EXPORTER_PLUGIN_DIR . 'admin/class-admin.php';
        
        new WP_Markdown_Exporter\Admin();
    }
});

// 添加设置链接到插件页面
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('tools.php?page=wp-markdown-exporter') . '">' 
        . __('Settings', 'wp-markdown-exporter') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});