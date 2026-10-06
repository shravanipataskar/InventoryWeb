require('./bootstrap');

document.addEventListener('DOMContentLoaded', function () {
    var openButton = document.querySelector('[data-sidebar-open]');
    var closeButtons = document.querySelectorAll('[data-sidebar-close]');

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
});
