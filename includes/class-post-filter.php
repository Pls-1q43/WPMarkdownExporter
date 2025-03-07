<?php

namespace WP_Markdown_Exporter;

class Post_Filter {
    /**
     * 存储筛选条件
     */
    private $args = array();

    /**
     * 设置分类筛选
     *
     * @param array|int $categories 分类 ID 数组或单个分类 ID
     * @return self
     */
    public function set_categories($categories) {
        if (!empty($categories)) {
            $this->args['cat'] = is_array($categories) ? implode(',', $categories) : $categories;
        }
        return $this;
    }

    /**
     * 设置时间范围
     *
     * @param string $start_date 开始日期 (Y-m-d 格式)
     * @param string $end_date 结束日期 (Y-m-d 格式)
     * @return self
     */
    public function set_date_range($start_date, $end_date) {
        if (!empty($start_date)) {
            $this->args['date_query']['after'] = $start_date;
        }
        if (!empty($end_date)) {
            $this->args['date_query']['before'] = $end_date;
        }
        if (!empty($start_date) || !empty($end_date)) {
            $this->args['date_query']['inclusive'] = true;
        }
        return $this;
    }

    /**
     * 获取符合条件的文章
     *
     * @param int $posts_per_page 每页文章数，-1 表示所有
     * @param int $paged 当前页码
     * @return array 文章列表
     */
    public function get_posts($posts_per_page = -1, $paged = 1) {
        $default_args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $args = wp_parse_args($this->args, $default_args);
        $query = new \WP_Query($args);

        return $query->posts;
    }

    /**
     * 获取符合条件的文章总数
     *
     * @return int 文章总数
     */
    public function get_total_posts() {
        $default_args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        $args = wp_parse_args($this->args, $default_args);
        $query = new \WP_Query($args);

        return $query->found_posts;
    }

    /**
     * 获取所有可用的分类
     *
     * @return array 分类列表
     */
    public static function get_categories() {
        $categories = get_categories(array(
            'orderby' => 'name',
            'order' => 'ASC',
            'hide_empty' => false
        ));

        return array_map(function($category) {
            return array(
                'id' => $category->term_id,
                'name' => $category->name,
                'count' => $category->count
            );
        }, $categories);
    }

    /**
     * 重置筛选条件
     *
     * @return self
     */
    public function reset() {
        $this->args = array();
        return $this;
    }
}