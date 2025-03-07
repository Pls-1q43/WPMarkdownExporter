<?php

namespace WP_Markdown_Exporter;

class Utils {
    /**
     * 将 HTML 内容转换为 Markdown
     *
     * @param string $content HTML 内容
     * @return string Markdown 内容
     */
    public static function html_to_markdown($content) {
        // 移除 Gutenberg 注释块
        $content = preg_replace('/<!-- wp:.*?-->/', '', $content);
        $content = preg_replace('/<!-- \/wp:.*?-->/', '', $content);
    
        // 标题转换 - 使用 preg_replace_callback 替代 preg_replace
        $content = preg_replace_callback('/<h([1-6]).*?>(.*?)<\/h\1>/i', function($matches) {
            return str_repeat('#', $matches[1]) . ' ' . strip_tags($matches[2]) . "\n\n";
        }, $content);
    
        // 段落转换
        $content = preg_replace('/<p.*?>(.*?)<\/p>/is', "$1\n\n", $content);
    
        // 链接转换
        $content = preg_replace('/<a.*?href="(.*?)".*?>(.*?)<\/a>/i', '[$2]($1)', $content);
    
        // 图片转换
        $content = preg_replace('/<img.*?src="(.*?)".*?>/i', '![]($1)', $content);
    
        // 列表转换
        $content = preg_replace('/<ul.*?>(.*?)<\/ul>/is', "$1\n", $content);
        $content = preg_replace('/<ol.*?>(.*?)<\/ol>/is', "$1\n", $content);
        $content = preg_replace('/<li.*?>(.*?)<\/li>/i', "* $1\n", $content);
    
        // 粗体和斜体
        $content = preg_replace('/<strong.*?>(.*?)<\/strong>/i', '**$1**', $content);
        $content = preg_replace('/<em.*?>(.*?)<\/em>/i', '*$1*', $content);
    
        // 清理多余的空行和空格
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
        $content = trim($content);
    
        // 添加最终清理步骤，移除所有残留的 HTML 标签
        $content = strip_tags($content);
        
        return $content;
    }

    /**
     * 获取文章中的所有图片信息
     *
     * @param string $content 文章内容
     * @return array 图片信息数组
     */
    public static function extract_images($content) {
        $images = array();
        $image_ids = array();
        
        // 从内容中提取所有图片 ID
        preg_match_all('/<img.*?class=".*?wp-image-(\d+).*?".*?>/i', $content, $matches);
        if (!empty($matches[1])) {
            $image_ids = array_unique($matches[1]);
        }
        
        // 获取图片附件信息
        foreach ($image_ids as $image_id) {
            $image_data = wp_get_attachment_metadata($image_id);
            $upload_dir = wp_upload_dir();
            
            if ($image_data && isset($image_data['file'])) {
                $images[] = array(
                    'id' => $image_id,
                    'file_path' => $upload_dir['basedir'] . '/' . $image_data['file'],
                    'url' => wp_get_attachment_url($image_id),
                    'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
                );
            }
        }
        
        // 提取所有没有wp-image-ID但有src属性的图片
        preg_match_all('/<img[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $content, $src_matches);
        if (!empty($src_matches[1])) {
            foreach ($src_matches[1] as $src) {
                // 检查这个URL是否已经在前面处理过
                $already_processed = false;
                foreach ($images as $image) {
                    if ($image['url'] === $src) {
                        $already_processed = true;
                        break;
                    }
                }
                
                if (!$already_processed) {
                    // 尝试从URL获取附件ID
                    $attachment_id = attachment_url_to_postid($src);
                    if ($attachment_id) {
                        $image_data = wp_get_attachment_metadata($attachment_id);
                        $upload_dir = wp_upload_dir();
                        
                        if ($image_data && isset($image_data['file'])) {
                            $images[] = array(
                                'id' => $attachment_id,
                                'file_path' => $upload_dir['basedir'] . '/' . $image_data['file'],
                                'url' => $src,
                                'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true)
                            );
                            continue;
                        }
                    }
                    
                    // 如果无法获取附件ID，仍然记录URL以便替换
                    $images[] = array(
                        'id' => null,
                        'file_path' => null,
                        'url' => $src,
                        'alt' => ''
                    );
                }
            }
        }
        
        return $images;
    }

    /**
     * 生成安全的文件名
     *
     * @param string $filename 原始文件名
     * @return string 处理后的文件名
     */
    public static function sanitize_filename($filename) {
        // URL 解码文件名
        $filename = urldecode($filename);
        
        // 只替换文件系统不安全的字符
        $unsafe = array('/', '\\', ':', '*', '?', '"', '<', '>', '|');
        $filename = str_replace($unsafe, '-', $filename);
        
        // 将连续的连字符替换为单个连字符
        $filename = preg_replace('/-+/', '-', $filename);
        
        // 移除首尾的连字符
        $filename = trim($filename, '-');
        
        // 确保文件名不为空
        if (empty($filename)) {
            $filename = 'post_' . time();
        }
        
        // 限制文件名长度（考虑到文件系统限制）
        if (mb_strlen($filename) > 100) {
            $filename = mb_substr($filename, 0, 100);
        }
        
        return $filename;
    }

    /**
     * 创建临时目录
     *
     * @return string|WP_Error 临时目录路径或错误对象
     */
    public static function create_temp_dir() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/wp-markdown-exports';
        $temp_dir = $base_dir . '/temp-' . time() . '-' . wp_rand();

        if (!wp_mkdir_p($temp_dir)) {
            return new \WP_Error('failed_create_dir', __('Failed to create temporary directory', 'wp-markdown-exporter'));
        }

        return $temp_dir;
    }

    /**
     * 递归删除目录
     *
     * @param string $dir 要删除的目录路径
     * @return bool 是否成功删除
     */
    public static function remove_dir($dir) {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::remove_dir($path) : unlink($path);
        }

        return rmdir($dir);
    }

    /**
     * 确保文件可下载
     *
     * @param string $file_path 文件路径
     * @return bool 是否成功
     */
    public static function ensure_file_downloadable($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        // 确保文件有正确的权限
        chmod($file_path, 0644);

        // 确保目录有正确的权限
        $dir = dirname($file_path);
        chmod($dir, 0755);

        return true;
    }
    
    /**
     * 清除所有导出文件
     *
     * @return array 清除结果，包含状态和消息
     */
    public static function clear_export_files() {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/wp-markdown-exports';
        
        if (!is_dir($export_dir)) {
            return array(
                'success' => false,
                'message' => __('Export directory does not exist', 'wp-markdown-exporter')
            );
        }
        
        $files_deleted = 0;
        $dirs_deleted = 0;

        // 清理所有 zip 文件
        $files = glob($export_dir . '/*.zip');
        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                $files_deleted++;
            }
        }

        // 清理所有临时目录
        $temp_dirs = glob($export_dir . '/temp-*');
        foreach ($temp_dirs as $dir) {
            if (is_dir($dir) && self::remove_dir($dir)) {
                $dirs_deleted++;
            }
        }
        
        return array(
            'success' => true,
            'message' => sprintf(
                __('Successfully deleted %d export files and %d temporary directories', 'wp-markdown-exporter'),
                $files_deleted,
                $dirs_deleted
            )
        );
    }
}