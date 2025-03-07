<?php

namespace WP_Markdown_Exporter;

class Image_Handler {
    /**
     * 存储图片下载目录
     */
    private $image_dir;

    /**
     * 存储已下载的图片映射关系
     */
    private $image_map = array();

    /**
     * 构造函数
     *
     * @param string $export_dir 导出目录路径
     */
    public function __construct($export_dir) {
        $this->image_dir = $export_dir . '/images';
        if (!file_exists($this->image_dir)) {
            wp_mkdir_p($this->image_dir);
        }
    }

    /**
     * 下载并保存图片
     *
     * @param array $image_info 图片信息
     * @return string|WP_Error 保存后的本地路径或错误对象
     */
    public function download_image($image_info) {
        // 确保 image_info 是有效的数组且包含必要的键
        if (!is_array($image_info) || !isset($image_info['url'])) {
            return new \WP_Error('invalid_image_info', __('Invalid image information', 'wp-markdown-exporter'));
        }

        $url = $image_info['url'];
        
        // 检查是否已经下载过这个图片
        if (isset($this->image_map[$url])) {
            return $this->image_map[$url];
        }

        // 获取图片内容
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return new \WP_Error(
                'download_failed',
                sprintf(__('Failed to download image: %s', 'wp-markdown-exporter'), $url)
            );
        }

        $image_content = wp_remote_retrieve_body($response);
        if (empty($image_content)) {
            return new \WP_Error(
                'empty_content',
                sprintf(__('Empty image content: %s', 'wp-markdown-exporter'), $url)
            );
        }

        // 生成安全的文件名
        $filename = Utils::sanitize_filename(basename($url));
        $file_path = $this->image_dir . '/' . $filename;

        // 保存图片
        if (file_put_contents($file_path, $image_content) === false) {
            return new \WP_Error(
                'save_failed',
                sprintf(__('Failed to save image: %s', 'wp-markdown-exporter'), $url)
            );
        }

        // 记录已下载的图片
        $this->image_map[$url] = $filename;

        return $filename;
    }

    /**
     * 替换内容中的图片URL
     *
     * @param string $content Markdown内容
     * @return string 替换后的内容
     */
    public function replace_image_urls($content) {
        // 首先替换所有图片URL
        foreach ($this->image_map as $url => $local_path) {
            $content = str_replace($url, 'images/' . $local_path, $content);
        }

        // 移除 WordPress Gutenberg 注释块
        $content = preg_replace('/<!-- wp:.*?-->\s*/s', '', $content);
        $content = preg_replace('/<!-- \/wp:.*?-->\s*/s', '', $content);

        // 处理 WordPress 图片块的 HTML 结构
        $patterns = array(
            // 处理带标题的图片块（包含 img 标签的情况）
            '/<figure[^>]*>\s*<img[^>]*src="([^"]*)"[^>]*>\s*<figcaption[^>]*>(.*?)<\/figcaption>\s*<\/figure>/is' 
                => function($matches) {
                    return sprintf("![%s](%s)\n\n%s\n", '', $matches[1], trim($matches[2]));
                },
            
            // 处理已经是 Markdown 格式的图片带标题的情况
            '/!\[(.*?)\]\((.*?)\)<figcaption[^>]*>(.*?)<\/figcaption>/is'
                => function($matches) {
                    return sprintf("![%s](%s)\n\n%s\n", $matches[1], $matches[2], trim($matches[3]));
                },

            // 处理普通图片块（包含 img 标签）
            '/<figure[^>]*>\s*<img[^>]*src="([^"]*)"[^>]*>\s*<\/figure>/is' 
                => '![]($1)',
            
            // 处理普通的 Markdown 图片
            '/<figure[^>]*>\s*(!\[.*?\]\(.*?\))\s*<\/figure>/is' => '$1',
            
            // 处理列布局中的图片（更新这些模式）
            '/<div[^>]*class="[^"]*wp-block-columns[^"]*"[^>]*>(.*?)<\/div>/is' => '$1',
            '/<div[^>]*class="[^"]*wp-block-column[^"]*"[^>]*>(.*?)<\/div>/is' => '$1',
            
            // 处理任何剩余的 div 标签（如果它们包含 Markdown 图片语法）
            '/<div[^>]*>\s*(!\[.*?\]\(.*?\))\s*<\/div>/is' => '$1',
            '/<div[^>]*>\s*(!\[.*?\]\(.*?\))\s*(!\[.*?\]\(.*?\))\s*<\/div>/is' => "$1\n\n$2",
        );

        // 应用所有替换模式
        foreach ($patterns as $pattern => $replacement) {
            if (is_callable($replacement)) {
                $content = preg_replace_callback($pattern, $replacement, $content);
            } else {
                $content = preg_replace($pattern, $replacement, $content);
            }
        }

        // 清理多余的空行、HTML 标签和样式属性
        $content = preg_replace("/\n{3,}/", "\n\n", $content);
        $content = preg_replace('/<\/?(?:figure|div)[^>]*>/is', '', $content);
        $content = preg_replace('/style="[^"]*"/i', '', $content);
        
        return trim($content);
    }

    /**
     * 获取已下载的图片数量
     *
     * @return int 图片数量
     */
    public function get_downloaded_count() {
        return count($this->image_map);
    }

    /**
     * 获取图片目录路径
     *
     * @return string 图片目录路径
     */
    public function get_image_dir() {
        return $this->image_dir;
    }

    /**
     * 创建图片压缩包
     *
     * @param string $zip_file 压缩包路径
     * @return bool|WP_Error 是否成功
     */
    public function create_image_archive($zip_file) {
        if (!extension_loaded('zip')) {
            return new \WP_Error('zip_not_found', __('ZIP extension is not available', 'wp-markdown-exporter'));
        }

        $zip = new \ZipArchive();
        if ($zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return new \WP_Error('zip_create_failed', __('Failed to create ZIP archive', 'wp-markdown-exporter'));
        }

        $files = glob($this->image_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $zip->addFile($file, 'images/' . basename($file));
            }
        }

        return $zip->close();
    }

    /**
     * 复制图片到导出目录
     *
     * @param array $image_info 图片信息
     * @return string|WP_Error 保存后的本地路径或错误对象
     */
    public function copy_image($image_info) {
        // 如果已经处理过，直接返回
        if (isset($this->image_map[$image_info['url']])) {
            return $this->image_map[$image_info['url']];
        }

        // 获取安全的文件名
        $filename = Utils::sanitize_filename(basename($image_info['file_path']));
        $local_path = $this->image_dir . '/' . $filename;

        // 确保文件名唯一
        $filename_no_ext = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $counter = 1;

        while (file_exists($local_path)) {
            $filename = $filename_no_ext . '-' . $counter . '.' . $ext;
            $local_path = $this->image_dir . '/' . $filename;
            $counter++;
        }

        // 直接复制文件
        if (!copy($image_info['file_path'], $local_path)) {
            return new \WP_Error(
                'copy_failed',
                sprintf(__('Failed to copy image: %s', 'wp-markdown-exporter'), $filename)
            );
        }

        $this->image_map[$image_info['url']] = $local_path;
        return $local_path;
    }
}