jQuery(document).ready(function($) {
    var searchTimer;
    
    function performSearch() {
        var searchTerm = $(this).val();
        var container = $(this).closest('.ect-search-container');
        var resultsContainer = container.find('.ect-search-results');

        clearTimeout(searchTimer);
        
        if(searchTerm.length > 0) {
            searchTimer = setTimeout(function() {
                $.ajax({
                    url: ect_vars.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ect_search',
                        nonce: ect_vars.nonce,
                        search: searchTerm
                    },
                    beforeSend: function() {
                        resultsContainer.html('<div class="ect-loading"><?php esc_html_e("Searching...", "ect"); ?></div>');
                    },
                    success: function(response) {
                        if(response.success) {
                            if(response.data.length > 0) {
                                var html = '<ul>';
                                $.each(response.data, function(index, post) {
                                    html += '<li><a href="' + post.url + '">' + post.title + '</a></li>';
                                });
                                html += '</ul>';
                            } else {
                                html = '<div class="ect-no-results"><?php esc_html_e("No results found", "ect"); ?></div>';
                            }
                            resultsContainer.html(html).show();
                        }
                    }
                });
            }, 500);
        } else {
            $.ajax({
                url: ect_vars.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ect_search',
                    nonce: ect_vars.nonce,
                    search: ''
                },
                success: function(response) {
                    if(response.success && response.data.length > 0) {
                        var html = '<ul>';
                        $.each(response.data, function(index, post) {
                            html += '<li><a href="' + post.url + '">' + post.title + '</a></li>';
                        });
                        html += '</ul>';
                        resultsContainer.html(html).show();
                    }
                }
            });
        }
    }

    $('.ect-search-field')
        .on('input', performSearch)
        .on('focus', function() {
            performSearch.call(this);
        });

    $(document).click(function(e) {
        if (!$(e.target).closest('.ect-search-container').length) {
            $('.ect-search-results').hide();
        }
    });
});