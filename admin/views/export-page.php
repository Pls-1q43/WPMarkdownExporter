<div class="wrap">
    <h1><?php _e('WordPress Markdown Exporter', 'wp-markdown-exporter'); ?></h1>
    
    <div class="card">
        <h2><?php _e('Export Options', 'wp-markdown-exporter'); ?></h2>
        
        <form id="markdown-export-form" method="post">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Categories', 'wp-markdown-exporter'); ?></th>
                    <td>
                        <select name="categories[]" id="categories" multiple="multiple">
                            <?php
                            $categories = get_categories(array('hide_empty' => false));
                            foreach ($categories as $category) {
                                printf(
                                    '<option value="%d">%s</option>',
                                    $category->term_id,
                                    esc_html($category->name)
                                );
                            }
                            ?>
                        </select>
                        <p class="description">
                            <?php _e('Select categories to filter posts. Leave empty to export all categories.', 'wp-markdown-exporter'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="start_date"><?php _e('Date Range', 'wp-markdown-exporter'); ?></label>
                    </th>
                    <td>
                        <input type="date" name="start_date" id="start_date" class="regular-text">
                        <span><?php _e('to', 'wp-markdown-exporter'); ?></span>
                        <input type="date" name="end_date" id="end_date" class="regular-text">
                        <p class="description">
                            <?php _e('Optional. Filter posts by publish date.', 'wp-markdown-exporter'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="include_images"><?php _e('Include Images', 'wp-markdown-exporter'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="include_images" 
                                   id="include_images" 
                                   value="1" 
                                   <?php checked(true, true); ?>>
                            <?php _e('Download and include images in a separate archive', 'wp-markdown-exporter'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Images will be downloaded and image URLs in posts will be replaced with local paths.', 'wp-markdown-exporter'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <div class="submit-container">
                <?php wp_nonce_field('wp_markdown_exporter_nonce', 'markdown_export_nonce'); ?>
                <button type="submit" class="button button-primary" id="export-button">
                    <?php _e('Export to Markdown', 'wp-markdown-exporter'); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </form>
    </div>

    <div id="export-result" class="card" style="display: none;">
        <h2><?php _e('Export Result', 'wp-markdown-exporter'); ?></h2>
        <div class="result-message"></div>
        <div class="download-links" style="display: none;">
            <h3><?php _e('Download Files', 'wp-markdown-exporter'); ?></h3>
            <p>
                <div class="download-links">
                    <a href="#" class="button button-primary content-download">Download Posts Archive</a>
                    <a href="#" class="button button-secondary image-download">Download Images Archive</a>
                </div>
            </p>
        </div>
    </div>

    <!-- 将清理功能移到单独的 card 中 -->
    <div class="card">
        <h2><?php _e('Export Files Management', 'wp-markdown-exporter'); ?></h2>
        <div class="export-info">
            <p>
                <?php _e('All exported files will be stored in /wp-content/uploads/wp-markdown-exports/ directory. You can find the history or delete it directly here. You can also clear all files in this directory in time with this button.', 'wp-markdown-exporter'); ?>
            </p>
            <button type="button" id="clear-exports-button" class="button button-secondary">
                <?php _e('Clear All Export Files', 'wp-markdown-exporter'); ?>
            </button>
            <span class="spinner clear-spinner"></span>
            <div id="clear-result" class="notice" style="display:none; margin-top: 10px; padding: 10px;"></div>
        </div>
    </div>
</div>