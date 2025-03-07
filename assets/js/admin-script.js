jQuery(document).ready(function($) {
    const form = $('#markdown-export-form');
    const exportButton = $('#export-button');
    const spinner = $('.spinner');
    const resultContainer = $('#export-result');
    const resultMessage = $('.result-message');
    const downloadLinks = $('.download-links');
    const contentDownload = $('.content-download');
    const imageDownload = $('.image-download');

    // 处理表单提交
    form.on('submit', function(e) {
        e.preventDefault();

        // 重置状态
        resultContainer.hide();
        resultMessage.removeClass('error');
        downloadLinks.hide();
        
        // 显示加载状态
        exportButton.prop('disabled', true);
        spinner.addClass('is-active');
        
        // 准备表单数据
        const formData = new FormData(this);
        // 确保复选框值正确传递
        if (!formData.has('include_images')) {
            formData.append('include_images', '0');
        }
        formData.append('action', 'export_markdown');
        formData.append('nonce', wpMarkdownExporter.nonce);

        // 发送 AJAX 请求
        $.ajax({
            url: wpMarkdownExporter.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    handleSuccess(response.data);
                } else {
                    handleError(response.data);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = error;
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                resultMessage
                    .addClass('error')
                    .html(wpMarkdownExporter.i18n.exportError + ' ' + errorMessage);
                resultContainer.show();
                
                // 记录到控制台以便调试
                console.error('Export error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
            },  // 添加逗号
            complete: function() {
                // 恢复按钮状态
                exportButton.prop('disabled', false);
                spinner.removeClass('is-active');
            }
        });
    });

    // 处理成功响应
    function handleSuccess(data) {
        resultMessage.html(data.message);
        resultContainer.show();

        // 修改下载按钮的选择器和属性设置
        const postsButton = $('#export-result .button.content-download');
        const imagesButton = $('#export-result .button.image-download');

        if (data.downloads && data.downloads.markdown_url) {
            postsButton
                .attr('href', data.downloads.markdown_url)
                .show();
        } else {
            postsButton.hide();
        }

        if (data.downloads && data.downloads.images_url) {
            imagesButton
                .attr('href', data.downloads.images_url)
                .show();
        } else {
            imagesButton.hide();
        }

        downloadLinks.show();

        // 添加调试信息
        console.log('Response data:', data);
        console.log('Download URLs:', {
            posts: postsButton.attr('href'),
            images: imagesButton.attr('href')
        });

        // 滚动到结果区域
        $('html, body').animate({
            scrollTop: resultContainer.offset().top - 50
        }, 500);
    }

    // 处理错误响应
    function handleError(error) {
        resultMessage
            .addClass('error')
            .html(wpMarkdownExporter.i18n.exportError + ' ' + error);
        resultContainer.show();
    }

    // 日期选择器验证
    const startDate = $('#start_date');
    const endDate = $('#end_date');

    startDate.on('change', function() {
        endDate.attr('min', $(this).val());
    });

    endDate.on('change', function() {
        startDate.attr('max', $(this).val());
    });

    // 下载按钮点击事件
    // 在下载按钮点击事件中添加错误处理
    $('.download-links .button').on('click', function(e) {
        const url = $(this).attr('href');
        if (!url || url === '#') {
            e.preventDefault();
            console.error('Download URL is invalid:', url);
            alert('下载链接无效，请重试');
            return false;
        }
        console.log('Attempting to download:', url);
    });

    // 清除导出文件按钮点击事件
    $('#clear-exports-button').on('click', function(e) {
        e.preventDefault();
        
        const clearButton = $(this);
        const spinner = $('.clear-spinner');
        const resultDiv = $('#clear-result');
        
        clearButton.prop('disabled', true);
        spinner.addClass('is-active');
        resultDiv.hide();
        
        $.ajax({
            url: wpMarkdownExporter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'clear_export_files',
                nonce: wpMarkdownExporter.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultDiv
                        .removeClass('notice-error')
                        .addClass('notice-success')
                        .html('<p>' + response.data + '</p>')
                        .show();
                } else {
                    resultDiv
                        .removeClass('notice-success')
                        .addClass('notice-error')
                        .html('<p>' + response.data + '</p>')
                        .show();
                }
            },
            error: function() {
                resultDiv
                    .removeClass('notice-success')
                    .addClass('notice-error')
                    .html('<p>' + wpMarkdownExporter.i18n.clearError + '</p>')
                    .show();
            },
            complete: function() {
                clearButton.prop('disabled', false);
                spinner.removeClass('is-active');
            }
        });
    });
    
    // 删除重复的清除导出文件按钮点击事件处理函数
});