(function () {
    function request(action, data) {
        var formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', ansarBlog.nonce);
        Object.keys(data).forEach(function (key) {
            formData.append(key, data[key]);
        });

        return fetch(ansarBlog.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function (response) {
            return response.json();
        });
    }

    function handleLoadMore() {
        var recentWrapper = document.querySelector('.ansar-blog-recent');
        if (!recentWrapper) {
            return;
        }

        var loadMoreButton = recentWrapper.querySelector('[data-ansar-load-more]');
        var articlesContainer = document.getElementById('ansar-blog-articles');
        if (!loadMoreButton || !articlesContainer) {
            return;
        }

        var featuredId = parseInt(recentWrapper.getAttribute('data-featured-id') || '0', 10);
        var loaded = parseInt(recentWrapper.getAttribute('data-loaded') || '0', 10);
        var remaining = parseInt(loadMoreButton.getAttribute('data-remaining') || '0', 10);

        if (!loadMoreButton.getAttribute('data-original-label')) {
            loadMoreButton.setAttribute('data-original-label', loadMoreButton.textContent);
        }

        if (!loadMoreButton.getAttribute('data-loading-label')) {
            var loadingLabel = ansarBlog.texts && ansarBlog.texts.loading ? ansarBlog.texts.loading : 'Loading…';
            loadMoreButton.setAttribute('data-loading-label', loadingLabel);
        }

        loadMoreButton.addEventListener('click', function (event) {
            event.preventDefault();
            loadMoreButton.disabled = true;
            loadMoreButton.textContent = loadMoreButton.getAttribute('data-loading-label');

            var limit = Math.min(ansarBlog.loadMoreStep, remaining);

            request('ansar_blog_load_more', {
                offset: loaded,
                featured: featuredId,
                limit: limit
            }).then(function (payload) {
                loadMoreButton.disabled = false;
                loadMoreButton.textContent = loadMoreButton.getAttribute('data-original-label');

                if (!payload || !payload.success || !payload.data) {
                    return;
                }

                articlesContainer.insertAdjacentHTML('beforeend', payload.data.html);
                loaded += payload.data.count;
                remaining = Math.max(0, remaining - payload.data.count);
                recentWrapper.setAttribute('data-loaded', loaded);
                loadMoreButton.setAttribute('data-remaining', remaining);

                if (remaining <= 0 || payload.data.count < limit) {
                    loadMoreButton.remove();
                }
            }).catch(function () {
                loadMoreButton.disabled = false;
                loadMoreButton.textContent = loadMoreButton.getAttribute('data-original-label');
            });
        });
    }

    function handleSearch() {
        var searchForm = document.querySelector('.ansar-blog-search');
        var searchResults = document.getElementById('ansar-blog-search-results');
        var recentWrapper = document.querySelector('.ansar-blog-recent');
        if (!searchForm || !searchResults || !recentWrapper) {
            return;
        }

        var closeButton = searchResults.querySelector('[data-ansar-search-close]');
        var resultsContainer = searchResults.querySelector('[data-ansar-search-container]');
        var searchInput = searchForm.querySelector('input[name="s"]');

        function showResults() {
            recentWrapper.classList.add('hidden');
            searchResults.classList.remove('hidden');
        }

        function hideResults() {
            searchResults.classList.add('hidden');
            recentWrapper.classList.remove('hidden');
            if (resultsContainer) {
                resultsContainer.innerHTML = '';
            }
        }

        searchForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!searchInput) {
                return;
            }

            var term = searchInput.value.trim();
            if (!term) {
                hideResults();
                return;
            }

            request('ansar_blog_search', { term: term }).then(function (payload) {
                if (!payload || !payload.data) {
                    return;
                }

                showResults();
                resultsContainer.innerHTML = payload.data.html;

                if (payload.data.count === 0) {
                    var noResults = ansarBlog.texts && ansarBlog.texts.noResults ? ansarBlog.texts.noResults : 'No matching articles found.';
                    resultsContainer.innerHTML = '<p class="text-gray-500 col-span-2">' + noResults + '</p>';
                }
            });
        });

        if (closeButton) {
            closeButton.addEventListener('click', function (event) {
                event.preventDefault();
                hideResults();
            });
        }
    }

    function handleSubscribe() {
        var form = document.querySelector('.ansar-blog-subscribe');
        if (!form) {
            return;
        }

        var messageEl = form.querySelector('[data-ansar-subscribe-message]');
        var emailInput = form.querySelector('input[name="email"]');

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!emailInput) {
                return;
            }

            var email = emailInput.value.trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                var invalidEmail = ansarBlog.texts && ansarBlog.texts.invalidEmail ? ansarBlog.texts.invalidEmail : 'Please enter a valid email address.';
                if (messageEl) {
                    messageEl.textContent = invalidEmail;
                    messageEl.classList.remove('text-green-600');
                    messageEl.classList.add('text-red-600');
                }
                return;
            }

            request('ansar_blog_subscribe', { email: email }).then(function (payload) {
                if (!payload) {
                    return;
                }

                if (messageEl) {
                    var fallbackMessage = ansarBlog.texts && ansarBlog.texts.subscriptionUpdated ? ansarBlog.texts.subscriptionUpdated : 'Subscription updated.';
                    messageEl.textContent = payload.data && payload.data.message ? payload.data.message : fallbackMessage;
                    if (payload.success) {
                        messageEl.classList.remove('text-red-600');
                        messageEl.classList.add('text-green-600');
                        emailInput.value = '';
                    } else {
                        messageEl.classList.remove('text-green-600');
                        messageEl.classList.add('text-red-600');
                    }
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof ansarBlog === 'undefined') {
            return;
        }

        handleLoadMore();
        handleSearch();
        handleSubscribe();
    });
})();
