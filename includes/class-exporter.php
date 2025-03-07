<?php

namespace WP_Markdown_Exporter;

class Exporter {
    private $post_filter;
    private $image_handler;
    private $export_dir;
    private $include_images;

    /**
     * 构造函数
     *
     * @param bool $include_images 是否包含图片
     */
    public function __construct($include_images = true) {
        $this->post_filter = new Post_Filter();
        $this->include_images = $include_images;
    }

    /**
     * 初始化导出环境
     *
     * @return bool|WP_Error
     */
    private function init_export() {
        // 创建临时导出目录
        $temp_dir = Utils::create_temp_dir();
        if (is_wp_error($temp_dir)) {
            return $temp_dir;
        }
        $this->export_dir = $temp_dir;

        // 如果需要处理图片，初始化图片处理器
        if ($this->include_images) {
            $this->image_handler = new Image_Handler($this->export_dir);
        }

        return true;
    }

    /**
     * 导出文章
     *
     * @param array $categories 分类ID数组
     * @param string $start_date 开始日期
     * @param string $end_date 结束日期
     * @return array|WP_Error 导出结果
     */
    public function export($categories = array(), $start_date = '', $end_date = '') {
        // 初始化导出环境
        $init_result = $this->init_export();
        if (is_wp_error($init_result)) {
            return $init_result;
        }

        // 只在需要处理图片时创建图片目录
        if ($this->include_images) {
            if (!is_dir($this->export_dir . '/images')) {
                wp_mkdir_p($this->export_dir . '/images');
            }
        }

        // 设置筛选条件
        $this->post_filter->reset()
            ->set_categories($categories)
            ->set_date_range($start_date, $end_date);

        // 获取文章列表
        $posts = $this->post_filter->get_posts();
        if (empty($posts)) {
            return new \WP_Error('no_posts', __('No posts found matching the criteria', 'wp-markdown-exporter'));
        }

        // 处理每篇文章
        foreach ($posts as $post) {
            $result = $this->process_post($post);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        // 创建压缩包
        $result = $this->create_archives();
        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'post_count' => count($posts),
            'image_count' => $this->include_images ? $this->image_handler->get_downloaded_count() : 0,
            'content_archive' => $result['content_archive'],
            'image_archive' => $result['image_archive']
        );
    }

    /**
     * 处理单篇文章
     *
     * @param WP_Post $post 文章对象
     * @return bool|WP_Error
     */
    private function process_post($post) {
        // 转换内容为Markdown
        $content = Utils::html_to_markdown($post->post_content);
    
        // 获取发布日期并格式化（YYYY-MM-DD）
        $post_date = get_the_date('Y-m-d', $post->ID);
        
        // 生成基础文件名（日期-标题）
        $base_filename = $post_date . '-' . $post->post_title;
        
        // 如果同名文件已存在，添加递增数字
        $counter = 1;
        do {
            $filename = Utils::sanitize_filename($base_filename . ($counter > 1 ? "-{$counter}" : '') . '.md');
            $file_path = $this->export_dir . '/' . $filename;
            $counter++;
        } while (file_exists($file_path));
    
        // 只在用户选择包含图片时处理图片
        if ($this->include_images) {
            $images = Utils::extract_images($post->post_content);
            foreach ($images as $image_url) {
                $result = $this->image_handler->download_image($image_url);
                if (is_wp_error($result)) {
                    return $result;
                }
            }
            $content = $this->image_handler->replace_image_urls($content);
        }

        // 准备文章元数据
        $meta = $this->prepare_post_meta($post);
        $content = $meta . "\n\n" . $content;

        // 保存文件
        $filename = Utils::sanitize_filename($post->post_name . '.md');
        $file_path = $this->export_dir . '/' . $filename;
        
        if (file_put_contents($file_path, $content) === false) {
            return new \WP_Error(
                'save_failed',
                sprintf(__('Failed to save post: %s', 'wp-markdown-exporter'), $post->post_title)
            );
        }

        return true;
    }

    private function process_post_images($content) {
        if (!$this->include_images) {
            return $content;
        }

        $images = Utils::extract_images($content);
        foreach ($images as $image_info) {
            // 确保 image_info 包含必要的信息
            if (!isset($image_info['url'])) {
                continue;
            }

            $result = $this->image_handler->download_image($image_info);
            
            if (is_wp_error($result)) {
                continue;
            }
            
            // 更新内容中的图片路径
            $relative_path = 'images/' . $result;
            $content = str_replace($image_info['url'], $relative_path, $content);
        }

        return $content;
    }

    /**
     * 准备文章元数据
     *
     * @param WP_Post $post 文章对象
     * @return string 元数据YAML格式
     */
    private function prepare_post_meta($post) {
        $categories = wp_get_post_categories($post->ID, array('fields' => 'names'));
        $tags = wp_get_post_tags($post->ID, array('fields' => 'names'));

        $meta = array(
            'title' => $post->post_title,
            'date' => get_the_date('Y-m-d H:i:s', $post),
            'categories' => $categories,
            'tags' => $tags
        );

        $yaml = "---\n";
        foreach ($meta as $key => $value) {
            if (is_array($value)) {
                $yaml .= $key . ":\n";
                foreach ($value as $item) {
                    $yaml .= "  - " . $item . "\n";
                }
            } else {
                $yaml .= $key . ": " . $value . "\n";
            }
        }
        $yaml .= "---";

        return $yaml;
    }

    /**
     * 创建压缩包
     *
     * @return array|WP_Error 压缩包路径
     */
    private function create_archives() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/wp-markdown-exports';
        
        // 创建内容压缩包
        $content_archive = $base_dir . '/posts-' . time() . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($content_archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return new \WP_Error('zip_create_failed', __('Failed to create content archive', 'wp-markdown-exporter'));
        }

        $files = glob($this->export_dir . '/*.md');
        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        // 确保文件可下载
        if (!Utils::ensure_file_downloadable($content_archive)) {
            return new \WP_Error('file_permission_error', __('Failed to set file permissions', 'wp-markdown-exporter'));
        }
    
        $result = array('content_archive' => $content_archive);
    
        // 如果包含图片，创建图片压缩包
        if ($this->include_images && $this->image_handler->get_downloaded_count() > 0) {
            $image_archive = $base_dir . '/images-' . time() . '.zip';
            $result_images = $this->image_handler->create_image_archive($image_archive);
            if (is_wp_error($result_images)) {
                return $result_images;
            }
            // 确保图片压缩包也可下载
            if (!Utils::ensure_file_downloadable($image_archive)) {
                return new \WP_Error('file_permission_error', __('Failed to set file permissions', 'wp-markdown-exporter'));
            }
            $result['image_archive'] = $image_archive;
        }
    
        return $result;
    }

    /**
     * 清理临时文件
     */
    public function cleanup() {
        if (!empty($this->export_dir)) {
            Utils::remove_dir($this->export_dir);
        }
    }
}