require('./bootstrap');

document.addEventListener('DOMContentLoaded', function () {
    var openButton = document.querySelector('[data-sidebar-open]');
    var closeButtons = document.querySelectorAll('[data-sidebar-close]');
    var sidebarNav = document.querySelector('.sidebar-nav');
    var sidebarScrollKey = 'inventory-sidebar-scroll';

    if (sidebarNav) {
        function saveSidebarScroll() {
            sessionStorage.setItem(sidebarScrollKey, sidebarNav.scrollTop);
        }

        var savedScroll = sessionStorage.getItem(sidebarScrollKey);
        if (savedScroll !== null) {
            window.requestAnimationFrame(function () {
                sidebarNav.scrollTop = parseInt(savedScroll, 10) || 0;
            });
        }

        sidebarNav.addEventListener('scroll', saveSidebarScroll);
        sidebarNav.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                saveSidebarScroll();
            }
        });
        window.addEventListener('pagehide', saveSidebarScroll);
    }

    function closeSidebar() {
        document.body.classList.remove('sidebar-open');
        if (openButton) {
            openButton.setAttribute('aria-expanded', 'false');
        }
    }

    if (openButton) {
        openButton.addEventListener('click', function () {
            document.body.classList.add('sidebar-open');
            openButton.setAttribute('aria-expanded', 'true');
        });
    }

    Array.prototype.forEach.call(closeButtons, function (button) {
        button.addEventListener('click', closeSidebar);
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-toast-close]'), function (button) {
        button.addEventListener('click', function () {
            var toast = button.closest('[data-toast]');
            if (toast) {
                toast.remove();
            }
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-product-image-input]'), function (input) {
        var preview = document.querySelector('[data-product-image-preview]');
        var previewImage = preview ? preview.querySelector('[data-product-image]') : null;
        var clearButton = preview ? preview.querySelector('[data-clear-product-image]') : null;
        var removeCheckbox = document.querySelector('[data-remove-product-image]');
        var currentImage = document.querySelector('[data-current-product-image]');
        var previewUrl = null;

        function clearPreview() {
            input.value = '';
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
            }
            if (preview) {
                preview.hidden = true;
            }
            if (previewImage) {
                previewImage.removeAttribute('src');
            }
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];

            if (!file) {
                clearPreview();
                return;
            }

            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
            previewUrl = URL.createObjectURL(file);
            previewImage.src = previewUrl;
            preview.hidden = false;

            if (removeCheckbox) {
                removeCheckbox.checked = false;
            }
            if (currentImage) {
                currentImage.hidden = true;
            }
        });

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                clearPreview();
                if (currentImage) {
                    currentImage.hidden = !!(removeCheckbox && removeCheckbox.checked);
                }
            });
        }

        if (removeCheckbox && currentImage) {
            removeCheckbox.addEventListener('change', function () {
                currentImage.hidden = removeCheckbox.checked;
                if (removeCheckbox.checked) {
                    clearPreview();
                }
            });
            currentImage.hidden = removeCheckbox.checked;
        }
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-enhanced-table]'), function (tableContainer) {
        var rows = Array.prototype.slice.call(tableContainer.querySelectorAll('[data-table-row]'));
        var search = tableContainer.querySelector('[data-table-search]');
        var filters = Array.prototype.slice.call(tableContainer.querySelectorAll('[data-filter-key]'));
        var dateFrom = tableContainer.querySelector('[data-date-from]');
        var dateTo = tableContainer.querySelector('[data-date-to]');
        var summary = tableContainer.querySelector('[data-table-summary]');
        var pagination = tableContainer.querySelector('[data-table-pagination]');
        var empty = tableContainer.querySelector('[data-filter-empty]');
        var pageSize = parseInt(tableContainer.getAttribute('data-page-size'), 10) || 10;
        var currentPage = 1;
        var visibleRows = rows;

        function renderTable() {
            if (!rows.length) {
                if (empty) {
                    empty.hidden = true;
                }
                if (summary) {
                    summary.textContent = '';
                }
                if (pagination) {
                    pagination.hidden = true;
                }
                return;
            }

            var query = search ? search.value.trim().toLowerCase() : '';
            var filterValues = {};
            var from = dateFrom ? dateFrom.value : '';
            var to = dateTo ? dateTo.value : '';
            var pageCount;

            filters.forEach(function (filter) {
                filterValues[filter.getAttribute('data-filter-key')] = filter.value;
            });

            visibleRows = rows.filter(function (row) {
                var matchesSearch = !query || row.textContent.toLowerCase().indexOf(query) !== -1;
                var matchesFilters = Object.keys(filterValues).every(function (key) {
                    return !filterValues[key] || row.getAttribute('data-' + key) === filterValues[key];
                });
                var date = row.getAttribute('data-date') || '';
                var matchesDate = (!from || date >= from) && (!to || date <= to);

                return matchesSearch && matchesFilters && matchesDate;
            });

            pageCount = Math.max(1, Math.ceil(visibleRows.length / pageSize));
            currentPage = Math.min(currentPage, pageCount);

            rows.forEach(function (row) {
                row.hidden = true;
            });

            visibleRows.slice((currentPage - 1) * pageSize, currentPage * pageSize).forEach(function (row) {
                row.hidden = false;
            });

            if (summary) {
                summary.textContent = visibleRows.length
                    ? 'Showing ' + (((currentPage - 1) * pageSize) + 1) + '–' + Math.min(currentPage * pageSize, visibleRows.length) + ' of ' + visibleRows.length
                    : 'No matching records';
            }

            if (empty) {
                empty.hidden = visibleRows.length !== 0;
            }

            if (pagination) {
                pagination.innerHTML = '';
                pagination.hidden = pageCount <= 1;

                if (pageCount > 1) {
                    var previous = document.createElement('button');
                    previous.type = 'button';
                    previous.className = 'pagination-button';
                    previous.textContent = '‹';
                    previous.setAttribute('aria-label', 'Previous page');
                    previous.disabled = currentPage === 1;
                    previous.addEventListener('click', function () {
                        currentPage -= 1;
                        renderTable();
                    });
                    pagination.appendChild(previous);

                    for (var page = 1; page <= pageCount; page += 1) {
                        (function (pageNumber) {
                            var pageButton = document.createElement('button');
                            pageButton.type = 'button';
                            pageButton.className = 'pagination-button' + (pageNumber === currentPage ? ' is-current' : '');
                            pageButton.textContent = pageNumber;
                            pageButton.setAttribute('aria-label', 'Page ' + pageNumber);
                            pageButton.addEventListener('click', function () {
                                currentPage = pageNumber;
                                renderTable();
                            });
                            pagination.appendChild(pageButton);
                        }(page));
                    }

                    var next = document.createElement('button');
                    next.type = 'button';
                    next.className = 'pagination-button';
                    next.textContent = '›';
                    next.setAttribute('aria-label', 'Next page');
                    next.disabled = currentPage === pageCount;
                    next.addEventListener('click', function () {
                        currentPage += 1;
                        renderTable();
                    });
                    pagination.appendChild(next);
                }
            }
        }

        if (search) {
            search.addEventListener('input', function () {
                currentPage = 1;
                renderTable();
            });
        }

        filters.concat([dateFrom, dateTo].filter(Boolean)).forEach(function (filter) {
            filter.addEventListener('change', function () {
                currentPage = 1;
                renderTable();
            });

        });

        var reset = tableContainer.querySelector('[data-filter-reset]');
        if (reset) {
            reset.addEventListener('click', function () {
                if (search) {
                    search.value = '';
                }
                filters.forEach(function (filter) {
                    filter.value = '';
                });
                if (dateFrom) {
                    dateFrom.value = '';
                }
                if (dateTo) {
                    dateTo.value = '';
                }
                currentPage = 1;
                renderTable();
            });
        }

        renderTable();
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-location-selector]'), function (selector) {
        var hallSelect = selector.querySelector('[data-location-hall]');
        var rackSelect = selector.querySelector('[data-location-rack]');
        var shelfSelect = selector.querySelector('[data-location-shelf]');
        var errorMessage = selector.querySelector('[data-location-error]');
        var rackRequest = 0;
        var shelfRequest = 0;
        var initialRack = selector.getAttribute('data-initial-rack') || '';
        var initialShelf = selector.getAttribute('data-initial-shelf') || '';
        var currentHall = selector.getAttribute('data-current-hall') || '';
        var preserveRackId = selector.getAttribute('data-preserve-rack-id') || '';
        var preserveRackName = selector.getAttribute('data-preserve-rack-name') || '';
        var preserveShelfId = selector.getAttribute('data-preserve-shelf-id') || '';
        var preserveShelfName = selector.getAttribute('data-preserve-shelf-name') || '';

        if (!hallSelect || !rackSelect || !shelfSelect) {
            return;
        }

        function resetSelect(select, placeholder) {
            select.innerHTML = '';
            var option = document.createElement('option');
            option.value = '';
            option.textContent = placeholder;
            select.appendChild(option);
            select.disabled = true;
        }

        function showError(message) {
            if (errorMessage) {
                errorMessage.textContent = message;
                errorMessage.hidden = false;
            }
        }

        function clearError() {
            if (errorMessage) {
                errorMessage.textContent = '';
                errorMessage.hidden = true;
            }
        }

        function fetchOptions(url) {
            return fetch(url, {
                headers: { Accept: 'application/json' }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Location options could not be loaded.');
                }
                return response.json();
            });
        }

        function fillSelect(select, placeholder, options, selectedId, preservedId, preservedName) {
            resetSelect(select, placeholder);
            var hasPreservedOption = options.some(function (item) {
                return String(item.id) === String(preservedId);
            });

            if (preservedId && selectedId && String(preservedId) === String(selectedId) && !hasPreservedOption) {
                options.push({ id: preservedId, name: preservedName });
            }

            options.forEach(function (item) {
                var option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name;
                select.appendChild(option);
            });

            select.disabled = false;
            select.value = selectedId || '';
        }

        function loadShelves(rackId, selectedShelfId, preserveCurrent) {
            shelfRequest += 1;
            var requestId = shelfRequest;
            resetSelect(shelfSelect, rackId ? 'Loading Shelves...' : 'Select Rack First');
            clearError();

            if (!rackId) {
                return Promise.resolve();
            }

            var url = selector.getAttribute('data-shelves-url').replace('__RACK__', encodeURIComponent(rackId));
            return fetchOptions(url).then(function (options) {
                if (requestId === shelfRequest) {
                    fillSelect(
                        shelfSelect,
                        'Select Shelf (Optional)',
                        options,
                        selectedShelfId,
                        preserveCurrent ? preserveShelfId : '',
                        preserveCurrent ? preserveShelfName : ''
                    );
                }
            }).catch(function () {
                if (requestId === shelfRequest) {
                    showError('Unable to load Shelves. Please try again.');
                }
            });
        }

        function loadRacks(hallId, selectedRackId, selectedShelfId, preserveCurrent) {
            rackRequest += 1;
            shelfRequest += 1;
            var requestId = rackRequest;
            resetSelect(rackSelect, hallId ? 'Loading Racks...' : 'Select Hall First');
            resetSelect(shelfSelect, 'Select Rack First');
            clearError();

            if (!hallId) {
                return Promise.resolve();
            }

            var url = selector.getAttribute('data-racks-url').replace('__HALL__', encodeURIComponent(hallId));
            return fetchOptions(url).then(function (options) {
                if (requestId !== rackRequest) {
                    return;
                }

                fillSelect(
                    rackSelect,
                    'Select Rack (Optional)',
                    options,
                    selectedRackId,
                    preserveCurrent ? preserveRackId : '',
                    preserveCurrent ? preserveRackName : ''
                );
                return loadShelves(
                    rackSelect.value,
                    selectedShelfId,
                    preserveCurrent && String(rackSelect.value) === String(preserveRackId)
                );
            }).catch(function () {
                if (requestId === rackRequest) {
                    showError('Unable to load Racks. Please try again.');
                }
            });
        }

        hallSelect.addEventListener('change', function () {
            initialRack = '';
            initialShelf = '';
            loadRacks(hallSelect.value, '', '', false);
        });

        rackSelect.addEventListener('change', function () {
            initialShelf = '';
            loadShelves(rackSelect.value, '', false);
        });

        if (hallSelect.value) {
            loadRacks(
                hallSelect.value,
                initialRack,
                initialShelf,
                String(hallSelect.value) === String(currentHall)
            );
        } else {
            resetSelect(rackSelect, 'Select Hall First');
            resetSelect(shelfSelect, 'Select Rack First');
        }
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-image-preview]'), function (preview) {
        var input = preview.querySelector('[data-image-input]');
        var previewBox = preview.querySelector('[data-image-preview-box]');
        var image = preview.querySelector('[data-image-preview-img]');
        var placeholder = preview.querySelector('[data-image-placeholder]');
        var previewUrl = null;

        if (!input || !previewBox || !image || !placeholder) {
            return;
        }

        input.addEventListener('change', function () {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
            }

            if (!input.files || !input.files.length) {
                var currentImage = image.getAttribute('data-current-image');
                if (currentImage) {
                    image.src = currentImage;
                    image.hidden = false;
                    placeholder.hidden = true;
                    previewBox.hidden = false;
                } else {
                    image.removeAttribute('src');
                    image.hidden = true;
                    placeholder.hidden = false;
                    previewBox.hidden = true;
                }
                return;
            }

            previewUrl = URL.createObjectURL(input.files[0]);
            image.src = previewUrl;
            image.hidden = false;
            placeholder.hidden = true;
            previewBox.hidden = false;
        });
    });
});
