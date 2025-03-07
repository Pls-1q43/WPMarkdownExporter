<?php

namespace WP_Markdown_Exporter;

class Admin {
    /**
     * 初始化管理界面
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_export_markdown', array($this, 'handle_export'));
        add_action('wp_ajax_clear_export_files', array($this, 'handle_clear_exports'));
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_management_page(
            __('Markdown Exporter', 'wp-markdown-exporter'),
            __('Markdown Exporter', 'wp-markdown-exporter'),
            'manage_options',
            'wp-markdown-exporter',
            array($this, 'render_admin_page')
        );
    }

    /**
     * 加载资源文件
     */
    public function enqueue_assets($hook) {
        if ('tools_page_wp-markdown-exporter' !== $hook) {
            return;
        }
    
        // 添加 Select2 依赖
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));
    
        wp_enqueue_style(
            'wp-markdown-exporter-admin',
            plugins_url('assets/css/admin-style.css', dirname(__FILE__)),
            array(),
            WP_MARKDOWN_EXPORTER_VERSION
        );
    
        wp_enqueue_script(
            'wp-markdown-exporter-admin',
            plugins_url('assets/js/admin-script.js', dirname(__FILE__)),
            array('jquery', 'select2'),
            WP_MARKDOWN_EXPORTER_VERSION,
            true
        );
    
        wp_localize_script('wp-markdown-exporter-admin', 'wpMarkdownExporter', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_markdown_exporter_nonce'),
            'i18n' => array(
                'exportingPosts' => __('Exporting posts...', 'wp-markdown-exporter'),
                'exportSuccess' => __('Export completed successfully!', 'wp-markdown-exporter'),
                'exportError' => __('Export failed:', 'wp-markdown-exporter'),
                'selectCategories' => __('Select categories...', 'wp-markdown-exporter')
            )
        ));
    }

    /**
     * 渲染管理页面
     */
    public function render_admin_page() {
        include_once WP_MARKDOWN_EXPORTER_PLUGIN_DIR . 'admin/views/export-page.php';
    }
    
    /**
     * 处理导出请求
     */
    public function handle_export() {
        try {
            check_ajax_referer('wp_markdown_exporter_nonce', 'nonce');
    
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Permission denied', 'wp-markdown-exporter'));
            }
    
            // 确保上传目录可写
            $upload_dir = wp_upload_dir();
            if (!wp_mkdir_p($upload_dir['basedir'])) {
                wp_send_json_error(__('Unable to create upload directory', 'wp-markdown-exporter'));
            }
    
            $categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : array();
            $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
            $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
            // 修改这里：正确处理复选框值
            $include_images = isset($_POST['include_images']) && $_POST['include_images'] === '1';
    
            $exporter = new Exporter($include_images);  // 确保这里使用了正确的命名空间
            $result = $exporter->export($categories, $start_date, $end_date);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
    
            // 返回下载链接
            $download_urls = array(
                'markdown_url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $result['content_archive'])
            );
    
            if (isset($result['image_archive'])) {
                $download_urls['images_url'] = str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $result['image_archive']);
            }
    
            wp_send_json_success(array(
                'message' => __('Export completed successfully!', 'wp-markdown-exporter'),
                'downloads' => $download_urls
            ));
    
        } catch (Exception $e) {
            error_log('WordPress Markdown Exporter Error: ' . $e->getMessage());
            wp_send_json_error(__('An unexpected error occurred. Please check the error log.', 'wp-markdown-exporter'));
        }
    }
    
    /**
     * 处理清除导出文件请求
     */
    public function handle_clear_exports() {
        try {
            check_ajax_referer('wp_markdown_exporter_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Permission denied', 'wp-markdown-exporter'));
            }
            
            $result = Utils::clear_export_files();
            
            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}